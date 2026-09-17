# Bedrock 1.26.50+ local data overlay

Block/item data for Bedrock 1.26.50 (protocol 2192) and 1.26.51 (protocol 2193), sourced from
[axolotl-pm/BedrockData](https://github.com/axolotl-pm/BedrockData)'s `bedrock-1.26.50` branch. Kept
here instead of `vendor/nethergamesmc/bedrock-data/` (this fork's usual composer-managed data source)
because that upstream package doesn't have 1.26.50 support yet - a fresh `composer install` would not
fetch these files, breaking the build. Consumed via `LOCAL_BEDROCK_DATA_PATH` (`src/CoreConstants.php`).

`block_state_upgrade_schema/` holds one project-authored schema (not from Mojang/upstream) that adds
the `minecraft:connection_east/north/south/west` block state properties fences/glass panes/bars gained
in 1.26.50 to older, already-persisted block state data (shop items, chests, etc. saved before this was
modeled) - see `WorldDataVersions::BLOCK_STATES` and `GlobalBlockStateHandlers::getUpgrader()`.

Regenerate `generated/data/bedrock/BedrockDataFiles.php` after adding/removing files here via
`php build/codegen/bedrockdata-path-consts.php`.
