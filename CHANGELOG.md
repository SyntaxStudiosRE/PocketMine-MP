# SyntaxStudios PocketMine-MP Changelog

This changelog covers changes made in this fork on top of [NetherGamesMC/PocketMine-MP](https://github.com/NetherGamesMC/PocketMine-MP). For the upstream PocketMine-MP changelog (protocol/version history up to the point this fork was based on), see the [`changelogs/`](changelogs/) directory inherited from upstream.

## v5.44.2-syntax.17

### Fixed a crash right-clicking a named/enchanted block item (2168/2169)

`NetworkInventoryAction`'s windowId/sourceFlags "no dummy bool" format (introduced for 1.26.50 in v5.44.2-syntax.14) was wrongly gated to `>= PROTOCOL_1_26_40` instead of `>= PROTOCOL_1_26_50` - the live proxy test that confirmed it only ever ran against a 1.26.51 client, never an actual 2168/2169 one. That range still needs the old double-bool format.

Confirmed live in production: a real 2169 client sending a `USE_ITEM` transaction for a custom block item with a display name/lore/fake-enchant NBT (right-clicking a plugin's "Partner Package" - an Ender Chest - against the ground) decoded as complete garbage a few fields in ("Invalid raw value 116 for TriggerType"), kicking the player. Reproduced and fixed in test before touching production - full round-trip verified byte-for-byte against the real captured packet (612 bytes, zero unread after the fix).

### Fixed fences/panes/bars/stairs invisible and unplaceable on 2168/2169

A second, more widely-impactful bug found while investigating the crash above: for protocol >= 1.26.40, network block runtime IDs are content hashes - a client only recognizes an ID that matches what it independently computes from its own real vanilla palette. `BlockStateDictionary::loadFromString()` was upgrading every protocol's raw palette entries through the same `BlockStateUpgrader` used for our own internal/world-storage format, which includes our local `connection_east`/`minecraft:corner` schemas added in the last two releases. That injected those properties into 2168/2169's dictionary entries too, producing hashes a real 1.26.40-45 client - whose actual palette has no such properties at all - never computes on its own, making every fence/pane/bars/stair unrecognizable: invisible in the world, and any placement attempt silently rejected since the client's local prediction could never agree with the server.

Split the two upgrade contexts, which need different behavior:
- `GlobalBlockStateHandlers::getUpgrader()` (world/item storage - our own internal format evolving over time) keeps including our local schemas.
- `GlobalBlockStateHandlers::getVendorOnlyBlockStateUpgrader()` (new) is used by `BlockStateDictionary::loadFromString()` instead - real upstream schemas only, so each protocol's dictionary matches a real client's own hash.

Fences/panes were coincidentally unaffected in practice (a single-variant "fast path" in the dictionary bypasses property matching entirely), but stairs (8 variants per facing/upside-down combination) needed an explicit fallback: `BlockStateDictionary::lookupStateIdFromDataWithFallback()` retries with the new properties removed entirely (not just defaulted - the dictionary doesn't have the key at all) instead of falling back to the generic stone placeholder. Used by both `BlockTranslator` (world/chunk rendering) and `ItemTranslator` (item icons, e.g. creative inventory - found separately: stairs placed/rendered correctly but still showed the "unknown item" icon in creative until this was applied there too).

Verified against real BetterAltay/BedrockData palette hashes, not just internal self-consistency: 128 combinations across stairs (all facings, upside-down, shapes) and fences/panes/bars (connected/unconnected) on protocol 2168, all matching ground truth exactly. Re-verified all 32 accepted protocols, including everything below 1.26.40, are unaffected.

## v5.44.2-syntax.16

### Stairs now render corners correctly on 1.26.50+ instead of stone

Mojang added a `minecraft:corner` block state to stairs in 1.26.50 (straight/inner-left/inner-right/outer-left/outer-right) - the same kind of previously-client-only visual property that fences/glass panes gained (see v5.44.2-syntax.14). Found independently, then confirmed by `axolotl-pm/BedrockBlockUpgradeSchema` adding the identical fix (same 97 block names, same "none" default) shortly after.

This fork already computed stair shape server-side (`Stair::readStateFromWorld()`, used for collision boxes/support type) but only as a transient, per-access value - chunk data sent to clients reads the block's *persistent* state ID directly and never called that code path, so it would always have serialized as the default "none" regardless of a stair's real neighbours. Ported the same event-driven, state-ID-persisted pattern already used by `Wall`/the `HorizontalConnectableTrait` blocks: shape is now part of `Stair::describeBlockOnlyState()`, recalculated by a new `onNearbyBlockChange()` (replacing `readStateFromWorld()`), and written back via `World::setBlock()` when it changes - same as walls placing/updating next to each other.

Also added a `BlockTranslator` fallback: protocols below 1.26.50 have no `minecraft:corner` property at all, so a real (non-"none") shape can never match their palette - instead of falling back to the generic stone placeholder, it now retries the lookup degraded to "none" first, matching how those clients already computed the visual shape themselves before this change.

Same `WorldDataVersions`-versioned upgrade schema mechanism as the fence/pane fix, extended to add `minecraft:corner: "none"` to old persisted stair data.

### Fixed stairs, fences, glass panes, and bars missing from creative inventory and recipes

Introduced by the schema changes above (and the earlier fence/pane one) - `CraftingManagerFromDataHelper` builds creative inventory and recipe item stacks from static JSON data authored by `nethergamesmc/bedrock-data`, which has no idea about our local `connection_east`/`minecraft:corner` schema additions. It tagged that data as `BlockStateData::current()` directly instead of running it through `BlockStateUpgrader` first, so deserializing any affected block silently failed (caught and swallowed as "probably an unknown item") the moment its properties stopped matching - no crash, just missing entries and missing recipes for every fence, pane, bars, and stair. Fixed by upgrading that data the same way world/item storage already does, tagged with a new `WorldDataVersions::PRE_LOCAL_SCHEMA_BLOCK_STATES` baseline so exactly our own schemas apply. Creative inventory went from 1581 to 1695 items; recipes for all affected blocks are back.

## v5.44.2-syntax.15

### Fixed a production crash on 1.26.40/1.26.45 (protocol 2168/2169) connect

v5.44.2-syntax.14 crashed the whole server the moment a real 1.26.40-1.26.45 client connected (confirmed live in production shortly after that release): `BlockTranslator::loadFromProtocolId()` tried to read `canonical_block_states-1.26.40.nbt` from `vendor/nethergamesmc/bedrock-data/`, but that file only exists in a newer upstream commit than the one pinned in `composer.lock` - it had been added to this machine's `vendor/` directly back in August (when 1.26.40 support was first built) without ever bumping the lock file. This went unnoticed for over a month because every phar build before yesterday's release reused this same machine's already-populated `vendor/`; yesterday's CI release build was the first ever *genuinely fresh* `composer install`, which silently produced a phar missing the file.

Same root cause and same fix as the previous release's 1.26.50 local data (see above) - just an older, previously-undiscovered instance of it. The three affected files (`canonical_block_states`/`block_state_meta_map`/`required_item_list`, all `-1.26.40`-suffixed) now live in `resources/vanilla-bedrock-data-overrides/` (renamed from `resources/vanilla-1.26.50-data/`, which now covers both gaps) alongside the 1.26.50 data. This time, verified before redeploying by loading every accepted protocol's `BlockTranslator`/`ItemTypeDictionaryFromDataHelper`/`ItemTagToIdMap` directly against the actual release-candidate build - the same code path that crashed - not just the newly-touched ones.

## v5.44.2-syntax.14

### Bedrock 1.26.50/1.26.51 support (protocol 2192/2193)

Native support for Bedrock 1.26.50 (protocol 2192) and 1.26.51 (protocol 2193, a pure renumbering of 2192 confirmed via CloudburstMC/Protocol's `f222c34bb` commit) alongside every previously-supported protocol - no translation proxy involved. No actively-maintained multiversion fork had 1.26.50 support yet at the time this was built, so block/item data came from `axolotl-pm/BedrockData`'s `bedrock-1.26.50` branch (the only complete, correctly-formatted source found) and cross-referencing `axolotl-pm/PocketMine-MP`/`BetterAltayBedrock/BetterAltay`'s own from-scratch ports.

- **Root cause of the initial instant-disconnect on connect** ("Boat" error, no reason given, right as chunk data started sending): two new packets, `JigsawStructureDataPacket` and `VoxelShapesPacket`, must be sent before `StartGamePacket` in 1.26.50+ - this fork already had the packet classes and wiring from an earlier merge, just not the data-loading/sending glue. Fixed in `PreSpawnPacketHandler`/`StaticPacketCache`.
- **The recurring "redundant dummy bool" pattern**: 1.26.30-1.26.45 wraps several optional fields in a double-bool (`getBool() && getBool()`, first one always true/redundant); 1.26.50+ drops the redundant bool, leaving a single real optional bool. Found and fixed in five places: `PlayerAuthInputPacket` (input flags, item interaction/stack-request/block-action sections), `InventoryTransactionPacket` (transaction type/data), `NetworkInventoryAction` (windowId/sourceFlags), and `ItemStackResponse` (top-level `hasContainers`). An initial fix for `NetworkInventoryAction` based on a nested-optional pattern cross-referenced from BakuTeam/Essential passed self-testing but broke placing blocks specifically (worked for breaking) - live proxy testing caught it, replaced with the simpler single-bool format.
- **`UseItemTransactionData`**: gained a new `hand` field (unsigned varint) for 1.26.50+.
- **`PlaySoundPacket`**: gained `loopCount`/`bypassListenerRangeCheck`/`playbackPositionSeconds` fields, with `serverSoundHandle` reordered after them.
- **Unmapped-blockstate fallback** changed from `info_update` (PMMP's reserved debug placeholder) to `stone` - real 1.26.50+ palettes can have genuine schema gaps against this fork's block classes for perfectly normal world terrain, and a real client silently rejected the connection the moment it received chunk data containing `info_update` blocks.

### Fixed a disconnect when moving armor from inventory to an equipped slot

A sixth instance of the dummy-bool pattern above, in `ItemStackResponseSlotInfo`'s `itemStackId` field - found live (not via code review) while investigating a real, reproducible disconnect on moving armor from an inventory slot into its equipped slot on 1.26.50+.

### Fences, glass panes, and bars now render correctly instead of falling back to stone

Mojang added `connection_east/north/south/west` block state properties to fences, glass panes (including colored/hardened variants), and iron/copper bars in 1.26.50 - previously client-side-only visual connections, now part of the network blockstate. This fork's block classes didn't model them, so 1.26.50+ clients couldn't map any connected variant to the new palette and fell back to the placeholder block above.

- Ported `HorizontalConnectable`/`HorizontalConnectableTrait` from `axolotl-pm/PocketMine-MP`, applied to `Fence` and `Thin` (the base class for glass panes and bars). Confirmed `Wall` already modeled its own long-standing connection states correctly and needed no change.
- Updated block registration (`VanillaBlockMappings`) for glass panes, hardened glass panes, both colored variants, iron bars, copper bars, nether brick fence, all wood fences, and a cosmetic-only dummy-property fix for tripwire (mirrors a Mojang data quirk, not real connection logic).
- **Old persisted block state data broke on load** (shop items in particular: `Failed to deserialize item data: Property "minecraft:connection_east" is missing`) since it predates these new properties. Fixed with a project-authored block state upgrade schema (`resources/vanilla-1.26.50-data/block_state_upgrade_schema/`) that adds the four new properties (default `false`) to old data, and a `WorldDataVersions::BLOCK_STATES` revision bump. This same upgrade schema is also what keeps 2168/2169 clients unaffected - their block palette lookup for these block types either ignores extra properties entirely (single-variant fast path) or now matches correctly against the upgraded data; verified empirically across all affected block types on every supported protocol, connected and unconnected.

### Repository changes

- Added `resources/vanilla-1.26.50-data/` (tracked in git, not `vendor/`) holding the 1.26.50 block/item data and the custom upgrade schema above - `nethergamesmc/bedrock-data`/`pocketmine/bedrock-block-upgrade-schema` don't support this protocol range yet, so a fresh `composer install` wouldn't have fetched it, silently breaking the GitHub Actions release build. `LOCAL_BEDROCK_DATA_PATH` (`CoreConstants.php`) and `build/codegen/bedrockdata-path-consts.php` were updated accordingly.

## v5.44.2-syntax.13

### Protocol-correct skin geometry data and engine version

Found while cross-referencing BakuTeam/Essential (another active NetherGamesMC-lineage multiversion fork) for anything we might have missed: `CommonTypes::putSkin()` always wrote a skin's raw `geometryData`/`geometryDataEngineVersion` regardless of the recipient's protocol. Protocol >= 1.26.40 needs the literal `"{}"` for an empty geometry document (a genuinely empty string doesn't resolve the referenced default geometry there) and expects `"0.0.0"` as the engine version marker; older protocols need the raw values instead. This affects every skin sent to every player, not just the default-skin substitution from v5.44.2-syntax.6-9 - may improve skin/player rendering more broadly on 2168+.

Also fixed the same "always uses the default/1.26.30 protocol's block translator regardless of the actual viewer" bug in two HardcoreFactions plugin utilities (`FakeBlockUtils`, `GlassWalls`) used for `/f map` claim-corner pillars and claim-boundary glass walls - block-state network IDs differ between protocol ranges, so those fake blocks were silently invisible for viewers on a different protocol than 1.26.30 (2168/2169 in particular).

## v5.44.2-syntax.12

### Likely root cause of the protocol-589/685 mass-disconnect incident

`PlayerAuthInputPacket`'s `interactRotation`/`cameraOrientation`/`rawMove` properties are typed with no default value, only ever assigned when `protocol >= PROTOCOL_1_21_40` (748) - or, for `interactRotation`, also when in VR play mode. Below that protocol, in normal play mode, they were left genuinely uninitialized - not null, untouched. PHP throws `Error` the instant *anything* reads an uninitialized typed property, which can happen anywhere later in a completely different call stack and tick than decode - explaining why v5.44.2-syntax.10's new visible decode-time logging never caught anything for this incident.

Found by cross-referencing BakuTeam/Essential, another actively-maintained NetherGamesMC-lineage multiversion fork, which independently hit and fixed the identical bug in the identical properties. All three now get a real zero-value default set at the top of `decodePayload()` for protocols below 1.21.40, same as BakuTeam's fix.

Confirmed live: a real player on protocol 685 (having been on 589, since dropped, before their client updated) correlated with a repeating mass-disconnect cycle hitting most of the rest of the playerbase. This is the first candidate root cause found that isn't a decode-time exception. Re-adding protocol 589 support will be considered once this is confirmed to hold up live.

## v5.44.2-syntax.11

### Dropped Bedrock 1.20.0 (protocol 589) support

Live incident (2026-09-13): a real player on this protocol - the single oldest one this fork accepted - correlated with a repeating mass-disconnect cycle hitting most of the rest of the playerbase. Confirmed by A/B testing: temp-banning that one account stopped the cycle every time, across multiple clean observation windows, with no server-side exception firing anywhere (checked with v5.44.2-syntax.10's just-added visible logging) - the same "silent corruption, no exception" shape as the earlier SetScorePacket incident rather than an uncaught error.

`AvailableCommandsPacket`'s `>= PROTOCOL_1_20_10` branch is the only code path this protocol alone exercised among currently-accepted protocols, making it the prime suspect, but the exact root cause is not yet isolated. Dropping the single oldest, least-used, and only implicated protocol stops the ongoing harm to everyone else immediately instead of leaving it live during what could be a multi-day investigation. Will be re-added only once a confirmed fix exists.

## v5.44.2-syntax.10

### Packet processing errors are now visible regardless of debug.level

While investigating a live mass-disconnect incident affecting mostly 1.26.45 clients (2026-09-13), found that both existing decode-error catches in `NetworkSession` only logged via `->debug()` - production's default `debug.level: 1` silently drops those entirely, so there was no way to tell whether they were even firing. Bumped both to `->warning()` (never suppressed), and added protocol id, full stack trace, and raw packet bytes to each, so the next occurrence can be triaged directly from the log instead of needing a live packet-trace capture.

No behavior change - this is observability only.

## v5.44.2-syntax.9

### Fixed real custom-skin players going missing from 2168+ viewers' tab list and "@" mention autocomplete

`TypeConverter::isUnsafeSkinForPlayerList()` (added in v5.44.2-syntax.6 to stop default/Persona skins from crashing protocol 2168+ viewers) decided "is this a default skin" by guessing from the shape of the skin ID string - no `.` in the ID was treated as "unmodified default". A production packet capture showed this was wrong more often than not: well over half of real, non-Persona, non-default custom skins also have a dot-less skin ID server-side, so any 2168+ (1.26.40-1.26.45) viewer was silently missing a large chunk of genuinely custom-skinned players from their tab list and native "@" chat-mention autocomplete, while everything else about those players worked normally. Confirmed live in production before this fix (a 1.26.45 client not seeing players on lower point releases in its tab list) and confirmed fixed in test (every connected player, across protocols, now appears).

- **`Skin`**: gained an explicit `personaOrDefault` flag (`isPersonaOrDefault()`), set at the point a skin is actually decoded from the client's real data, instead of being re-guessed later from the ID's shape.
- **`LegacySkinAdapter::fromSkinData()`**: sets the flag from the real `SkinData::isPersona()` signal (plus the classic default skin pack's known `c18e65aa-...` ID prefix), for both the Persona placeholder skin and normal custom skins.
- **`LoginPacketHandler`**: sets the flag on the login-time placeholder skin it substitutes for a default/Persona 2168+ client (see v5.44.2-syntax.6) - unchanged behavior, just now tagged correctly instead of relying on its dot-less ID shape alone.
- **`TypeConverter::isUnsafeSkinForPlayerList()`**: now reads `Skin::isPersonaOrDefault()` directly instead of inspecting the ID string. Still 2168+-only, still hides default/Persona players from other 2168+ viewers' tab lists the same as before (that mitigation is unrelated and unchanged) - this only removes the false positives that were hiding real custom skins too.

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
