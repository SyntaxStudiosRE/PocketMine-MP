# Bedrock data overrides not covered by the pinned composer package

Block/item data files that this fork needs but that `vendor/nethergamesmc/bedrock-data/` - the
composer-managed data source, version-pinned in `composer.lock` - doesn't have. Kept here (tracked in
git) instead of directly in `vendor/` so a fresh `composer install` (e.g. in CI, or a new dev machine)
still produces a working build. Consumed via `LOCAL_BEDROCK_DATA_PATH` (`src/CoreConstants.php`).

- **`*-1.26.40.*`**: Bedrock 1.26.40-1.26.45 (protocol 2168/2169) support, added ~August 2026. These
  files exist in a *newer* `nethergamesmc/bedrock-data` commit than the one actually pinned in
  `composer.lock` - someone fetched them locally at the time (or `vendor/` was updated ad hoc) without
  bumping the lock file. Undiscovered until 2026-09-17, when the first-ever from-scratch
  `composer install` (that day's CI release build) silently produced a phar missing them, and a real
  2169 client connecting to production crashed the whole server (`BlockTranslator::loadFromProtocolId`
  threw trying to read a file phar-packed builds didn't have). Rather than bump the pinned commit
  (risks pulling in unrelated upstream changes untested), the known-good files already validated in
  production were copied here directly.
- **`*-1.26.50.*`, `data_driven_blocks-1.26.50.nbt`, `jigsaw_structures_data-1.26.50.nbt`,
  `voxel_shapes-1.26.50.json`**: Bedrock 1.26.50/1.26.51 (protocol 2192/2193) support, sourced from
  [axolotl-pm/BedrockData](https://github.com/axolotl-pm/BedrockData)'s `bedrock-1.26.50` branch since
  `nethergamesmc/bedrock-data` doesn't have this protocol range yet at all.

`block_state_upgrade_schema/` holds one project-authored schema (not from Mojang/upstream) that adds
the `minecraft:connection_east/north/south/west` block state properties fences/glass panes/bars gained
in 1.26.50 to older, already-persisted block state data (shop items, chests, etc. saved before this was
modeled) - see `WorldDataVersions::BLOCK_STATES` and `GlobalBlockStateHandlers::getUpgrader()`.

**Whenever a file is added or removed here**, regenerate `generated/data/bedrock/BedrockDataFiles.php`
via `php build/codegen/bedrockdata-path-consts.php`, and - just as importantly - actually test a real
`composer install --no-dev` from a clean checkout (or trust the CI release build, then boot-test the
resulting phar against every supported protocol) before deploying. A file only present in the ambient
`vendor/` of whichever machine built the phar is invisible until someone does a truly fresh install.
