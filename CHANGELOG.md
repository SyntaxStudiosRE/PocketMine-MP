# SyntaxStudios PocketMine-MP Changelog

This changelog covers changes made in this fork on top of [NetherGamesMC/PocketMine-MP](https://github.com/NetherGamesMC/PocketMine-MP). For the upstream PocketMine-MP changelog (protocol/version history up to the point this fork was based on), see the [`changelogs/`](changelogs/) directory inherited from upstream.

## v5.44.2-syntax.8

### Bedrock 1.26.45 support (protocol 2169)

Mojang bumped the protocol number for the first time since 1.26.40 - previously every 1.26.40/42/44 point release kept the same number (2168) despite real client-side behavior changes, which is what most of this fork's 2168-era investigation was about. CloudburstMC/Protocol's `Bedrock_v2169` codec confirms 1.26.45 is a pure protocol-number/version-string bump over `Bedrock_v2168` with zero serializer changes, so this fork reuses every existing 2168 codepath and data table entry instead of porting anything new:

- Added `ProtocolInfo::PROTOCOL_1_26_45 = 2169` to `ACCEPTED_PROTOCOL`. Most of the codebase already branches on `protocol >= PROTOCOL_1_26_40`, so 2169 falls into those automatically.
- The handful of protocol-keyed exact-match tables needed an explicit new entry pointing at the same 2168 data: `BlockTranslator`/`ItemTagToIdMap`/`ItemTypeDictionaryFromDataHelper`'s `PATHS`, and `ItemTranslator::getItemSchemaId()`'s `match` (which would otherwise throw `AssumptionFailedError` for an unlisted protocol).
- Tested live with a real 1.26.45 client before release.

## v5.44.2-syntax.7

### Fixed the default-skin fallback rendering invisible on some devices

v5.44.2-syntax.6's fallback skin used all-zero pixel bytes (fully transparent). That rendered as a "Steve" fallback on the two accounts it was tested with, but a real production player on a different device rendered the same texture as literally invisible instead - cosmetic only (combat/hit detection was unaffected), but confusing and inconsistent across clients. `LoginPacketHandler` now uses a solid opaque gray for the fallback skin instead, so no client's undefined behavior for a fully-transparent texture is relied on.

## v5.44.2-syntax.6

### Default/Persona skins no longer disconnect on protocol 2168

Previously, a Bedrock 1.26.40+ (protocol 2168) client logging in with a default/Persona skin was rejected outright, since the client self-disconnects a few seconds after spawning with that skin otherwise (see v5.44.2-syntax.2/.3's investigation). Confirmed live with real 2168 clients:

- **`LoginPacketHandler`**: instead of disconnecting, the login now substitutes a known-safe blank skin (`Standard_Custom`, the same one `AimTrapEntity`/`WayPoint` already use) before it reaches `PlayerInfo`/spawn, so nothing the server sends ever carries the flagged skin identity. The player joins normally with a plain fallback appearance instead of being kicked. The substitute skinId must stay dot-less - a dotted variant, tried to also satisfy `TypeConverter::isUnsafeSkinForPlayerList()`'s "looks like a real custom skin" heuristic, reintroduced the same self-disconnect.
- `isUnsafeSkinForPlayerList()`'s existing behavior (omitting these players from other 2168 viewers' `PlayerListPacket`) is unchanged and still required - it isn't just a third-party-viewer mitigation, it's load-bearing for this fix too (disabling it, even for a single player with nobody else online, reproduced the self-disconnect via their own list self-entry). Two default-skin players can join and see each other in-world at the same time; neither appears in the other's tab list or gets native "@" chat-mention autocomplete for the other - an accepted, pre-existing tradeoff this change doesn't touch. A server-side chat mention plugin that doesn't depend on the tab list still works if the full username is typed manually.

## v5.44.2-syntax.5

### Harden packet decode error handling

Investigating reports of several players getting disconnected at once whenever a specific player on an old protocol (1.20.62, protocol 649) joined - distinct from, and outside the protocol-2168-only scope of, the 1.26.44 fixes below.

- **`NetworkSession::handleDataPacket()`**: the packet decode step only caught `PacketDecodeException`. An unusual protocol/version combination hitting a rarely-exercised branch can make `decode()` throw something else entirely (e.g. a `TypeError` from a narrower type check) - left uncaught, that escaped past this method instead of going through the existing, already-correct `wrap() -> PacketHandlingException -> disconnect just this session` path, a much less controlled failure mode. Widened the catch to also catch `\Throwable`, log it, and route it through that same existing path. BetterAltay shipped the identical fix (same failure mode, same fix shape) the same day in their `PlayerNetworkSessionAdapter.php`.
- Not yet confirmed whether this is the full fix for the reported mass-disconnects - deployed as a hardening measure while the exact triggering packet/protocol combination is still being tracked down.

## v5.44.2-syntax.4

### Self-hosted update checker

PMMP's built-in update checker defaulted to `update.pmmp.io`, which has no knowledge of this fork's releases - anyone running it with the shipped default config would never be notified a new `syntax.X` build exists.

- Published a static JSON API matching the engine's expected `UpdateInfo` schema via GitHub Pages (`docs/api/`, built from the `stable` branch). The release workflow now regenerates it with the new tag's info on every release.
- Pointed `resources/pocketmine.yml`'s `auto-updater.host` at that endpoint instead.
- The release workflow was never passing a `--build` number to `server-phar.php` - it silently defaulted to `0` on every prior release, and the update checker requires `build > 0` before it'll even compare versions against the API response, so the whole mechanism could never have worked regardless of the host it pointed at. Now passes the workflow run number as the build.
- Verified end-to-end live: a build older than the published API build correctly shows the "your version is out of date" console warning with working details/download links; a build at or above it correctly shows nothing.

## v5.44.2-syntax.3

### The real fix for 1.26.44 disconnects: `SetScorePacket`

v5.44.2-syntax.2's `buildPlatform` fix turned out to be necessary but not sufficient - 1.26.44 clients kept disconnecting a second or two into any sustained movement (teleporting, walking near an active event waypoint), independent of login. Root cause, found by diffing CloudburstMC/Protocol's Java implementation (they shipped a same-day `Bedrock_v2168_hotfix4` codec revision) against this fork's `SetScorePacket`:

- **`SetScorePacket` `TYPE_INVALID` (removal) entries**: 1.26.44 added an extra boolean field inside the "objective name present" branch of this entry type that 1.26.40/42 doesn't have - and there's no way to tell which format a given 2168 connection expects apart from the client's exact point version, which isn't observable at the protocol-id level. Any removal entry sent with a non-empty objective name (which a scoreboard/HUD refresh cycle does constantly, multiple times a second) desynced the following bytes for a 1.26.44 client specifically, corrupting decode from that point on until the client gave up and disconnected - explaining both the delayed, variable-length timing and why it kept recurring after the login-specific `buildPlatform` fix. Fixed in `src/network/mcpe/protocol/SetScorePacket.php` by never sending an objective name on a removal entry at all - it isn't needed to remove by `scoreboardId`, and omitting it produces the same one-byte encoding both format versions already agree on, sidestepping the ambiguity entirely instead of guessing which of two incompatible formats to use.
- The earlier BetterAltay cross-check (v5.44.2-syntax.2) had concluded this fork already matched BetterAltay's `SetScorePacket` fix - that was only true for the empty-string-guard part; the additional hotfix4-era boolean was missed because it postdated the commit that check compared against.

## v5.44.2-syntax.2

### Bedrock 1.26.44 support (still protocol 2168)

Bedrock 1.26.44 shipped with no protocol version bump - it's still 2168, same as 1.26.40/1.26.42 - but real client-side behavior changed anyway, causing 1.26.44 clients specifically to disconnect a few seconds after a successful login. Root cause:

- **`AddPlayerPacket`/`PlayerListEntry.buildPlatform`**: defaulted to `DeviceOS::UNKNOWN` (`-1`) everywhere a player is introduced to another client, as a deliberate privacy choice (never reveal a player's real device). 1.26.44 appears to validate this field more strictly in a client-side subsystem that runs a few seconds after spawn rather than at packet-decode time, and disconnects on an unknown value. Changed the default to `DeviceOS::ANDROID` - a generic, non-identifying placeholder that's still a *valid* value - in `src/entity/Human.php`, `src/network/mcpe/protocol/AddPlayerPacket.php`, and `src/network/mcpe/protocol/types/PlayerListEntry.php`. Matches the equivalent fix independently shipped by BetterAltay for the same release.
- Cross-checked BetterAltay's own 1.26.44 and 1.26.30 protocol support commits against this fork afterward - confirmed no other gaps for either version range. Their only other 1.26.44-era fix (`SetScorePacket`'s extra leading bool + empty-string guard on TYPE_REMOVE entries) was already present here from earlier work.

### Other fixes

- **`SubChunkRequestPacket::encodePayload()`**: the entry-count field's VarInt-vs-fixed-width switch for protocol >= 1001 was backwards relative to `decodePayload()` (read one way, wrote the other). No practical impact - this packet is server-bound only, so the server never calls `encodePayload()` on it - but corrected for correctness/symmetry.

## v5.44.2-syntax.1

First public release. Everything below was built on top of NetherGamesMC's multi-protocol base after `pmmp/PocketMine-MP` was archived upstream (2026-07-09) and Bedrock 1.26.40 shipped with no multi-protocol fork supporting it yet.

### Native Bedrock 1.26.40/42 (protocol 2168) support

The headline change: this server now natively speaks protocol 2168 (Bedrock 1.26.40/1.26.42) *alongside* 1.26.20-33 (975/1001) and 1.21.111 (844) in the same running server - no translation proxy involved. `NetherGamesMC/BedrockProtocol` (now merged directly into this repo, see below) had no 2168 support at all going in; every packet/type change below was reverse-engineered from scratch.

Notable fixes along the way, roughly in the order they were found:

- **`StartGamePacket`**: `blockNetworkIdsAreHashes` was hardcoded `false`, causing real client-side crashes (not just disconnects) because the client interpreted block network IDs as palette indices instead of hashes.
- **`StartGamePacket.isLoggingChat`**: field removed by Mojang in 2168 but was still being sent - this was the single fix that first let a 2168 client complete login instead of hanging indefinitely.
- **Block state hashing**: `BlockStateDictionaryEntry` FNV1a-32 hash computation, verified byte-for-byte against an independent implementation.
- **`CreativeContentPacket`**: full 108-group/1695-item creative inventory re-encoded and verified byte-perfect against CloudburstMC's reference decoder for 2168.
- **`CraftingDataPacket`**: two separate format changes - `ShapedRecipe` needs an explicit ingredient-count prefix in 2168 (not redundant, `helper.readArray()` always expects it), and `ItemDescriptorType`'s "name" variant switched from a numeric id+meta pair to a full string identifier (`minecraft:stick`) plus signed aux value.
- **Item stack encoding**: several related but distinct bugs across `AddItemActorPacket`/`AddPlayerPacket`/`CraftingEventPacket`/`InventoryContentPacket` - the "air" shortcut encoding no longer applies in 2168 (air now needs the full item header like any other item), and three packets were using the wrong item-stack format entirely (`getItemStackWrapper` instead of the descriptor format 2168 actually requires).
- **`PlayerAuthInputPacket`**: rewritten for 2168 - input flags moved from a dense fixed-width bitset to a sparse list of signed VarInt indices, `interactionMode` became signed, several optional sections gained an extra wrapping bool, and the flag enum grew a new value (`INTERNAL_UPDATE`) that a real client sends and that crashed decoding if unhandled.
- **`ItemStackRequestPacket`/`ItemStackResponsePacket`**: a chain of five encoding bugs (redundant legacy byte per action, `stackNetworkId` switching between VarInt/fixed-int depending on request vs. response, two new wrapping bools in the response, a completely different item descriptor format specific to the 2x2 "craft without table" action) and, most impactful, a full renumbering of `ItemStackRequestActionType` - 2168 assigns wire IDs from scratch instead of inheriting them, so every crafting/inventory action beyond the first few was silently being misinterpreted as a different action entirely.
- **`PlayerListPacket` join-order sensitivity**: a 2168 client silently disconnects if it receives its *own* `PlayerListPacket` entry before finishing its own spawn sequence - moved that entry to be sent from the post-spawn handler instead of pre-spawn, found via live bisection after independent byte-level verification (CloudburstMC, BetterAltay, official Mojang docs) had already ruled out a wire-format bug.
- **`NetworkItemStackDescriptor` variant field**: 2168 added a "variant" field to the wire format ahead of the stack ID in a subset of protocols (975-1001), missed on the initial port; a follow-up fix that copied the official upstream diff too literally also flipped the stack ID itself from signed to unsigned VarInt, breaking all inventory interaction across every protocol using that function until corrected. Fixing this also resolved a long-standing "armor invisible between real players" limitation on 2168 that had previously been attributed to an unfixable client-side restriction - it was the same encoding bug.
- **`AddPlayerPacket` carried item**: per Mojang's official r/26_u4 changelog, this field must omit the item-stack net ID entirely and have its NBT stripped to just an empty enchantment marker - our general item conversion path didn't do either, which is likely to matter for any player-typed entity spawning with a non-air held item on 2168.

**Known client-side limitation, not fixable server-side:** a default/unmodified Bedrock skin (the classic "Steve/Alex" auto-assigned identity, or the modern Persona system) crashes a protocol 2168 viewer - confirmed via extensive live A/B testing (disabling every candidate fix and testing with a real client) that this is a genuine 2168 client policy, not a server-side encoding issue. The server rejects logins with such skins on 2168 and hides other-protocol players who have one from a 2168 viewer's player list.

### Other fixes

- Player-list identity (`PlayerListPacket`) now uses the player's plain username instead of `getDisplayName()` - a rank/prefix plugin's colored display name was leaking into Bedrock's native "@" chat-mention autocomplete, producing garbled mentions for any player with a rank.

### Repository changes

- Merged `nethergamesmc/bedrock-protocol` (our `SyntaxStudiosRE/BedrockProtocol` fork) directly into this repository under `src/network/mcpe/protocol/`, removing the separate composer dependency and the friction that came with it (manual composer.lock hash pinning after every change, a standalone clone just to push vendor fixes, and repeated dependency-resolution failures for the private fork's dist downloads).
