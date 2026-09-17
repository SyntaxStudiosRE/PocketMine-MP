<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine;

use function define;
use function defined;
use function dirname;

// composer autoload doesn't use require_once and also pthreads can inherit things
if(defined('pocketmine\_CORE_CONSTANTS_INCLUDED')){
	return;
}
define('pocketmine\_CORE_CONSTANTS_INCLUDED', true);

define('pocketmine\PATH', dirname(__DIR__) . '/');
define('pocketmine\RESOURCE_PATH', dirname(__DIR__) . '/resources/');
define('pocketmine\BEDROCK_DATA_PATH', dirname(__DIR__) . '/vendor/nethergamesmc/bedrock-data/');
//2026-09-17: holds Bedrock block/item data that isn't in the nethergamesmc/bedrock-data version
//actually pinned in composer.lock - tracked directly in this repo instead of vendor/ so a fresh
//`composer install` (e.g. in CI) still produces a working build. This is NOT just for 1.26.50+:
//a 2026-09-17 production crash (protocol 2169 connecting) revealed 1.26.40/1.26.45 support had the
//exact same gap since ~August - composer.lock was never bumped after those files were added locally,
//so the first ever from-scratch `composer install` (this same day's CI release build) silently
//produced a phar missing them. See resources/vanilla-bedrock-data-overrides/README.md.
define('pocketmine\LOCAL_BEDROCK_DATA_PATH', dirname(__DIR__) . '/resources/vanilla-bedrock-data-overrides/');
define('pocketmine\LOCALE_DATA_PATH', dirname(__DIR__) . '/resources/translations/');
define('pocketmine\BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH', dirname(__DIR__) . '/vendor/pocketmine/bedrock-block-upgrade-schema/');
define('pocketmine\BEDROCK_ITEM_UPGRADE_SCHEMA_PATH', dirname(__DIR__) . '/vendor/pocketmine/bedrock-item-upgrade-schema/');
define('pocketmine\COMPOSER_AUTOLOADER_PATH', dirname(__DIR__) . '/vendor/autoload.php');
