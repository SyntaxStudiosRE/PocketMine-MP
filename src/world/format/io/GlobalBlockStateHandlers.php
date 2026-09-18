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

namespace pocketmine\world\format\io;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\data\bedrock\block\convert\BlockObjectToStateSerializer;
use pocketmine\data\bedrock\block\convert\BlockSerializerDeserializerRegistrar;
use pocketmine\data\bedrock\block\convert\BlockStateToObjectDeserializer;
use pocketmine\data\bedrock\block\convert\VanillaBlockMappings;
use pocketmine\data\bedrock\block\upgrade\BlockDataUpgrader;
use pocketmine\data\bedrock\block\upgrade\BlockIdMetaUpgrader;
use pocketmine\data\bedrock\block\upgrade\BlockStateUpgrader;
use pocketmine\data\bedrock\block\upgrade\BlockStateUpgradeSchemaUtils;
use pocketmine\data\bedrock\block\upgrade\LegacyBlockIdToStringIdMap;
use pocketmine\data\bedrock\BedrockDataFiles;
use pocketmine\utils\Filesystem;
use Symfony\Component\Filesystem\Path;
use const PHP_INT_MAX;
use const pocketmine\BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH;

/**
 * Provides global access to blockstate serializers for all world providers.
 * TODO: Get rid of this. This is necessary to enable plugins to register custom serialize/deserialize handlers, and
 * also because we can't break BC of WorldProvider before PM5. While this is a sucky hack, it provides meaningful
 * benefits for now.
 */
final class GlobalBlockStateHandlers{
	private static ?BlockDataUpgrader $blockDataUpgrader = null;

	private static ?BlockStateUpgrader $vendorOnlyBlockStateUpgrader = null;

	private static ?BlockStateData $unknownBlockStateData = null;

	private static ?BlockSerializerDeserializerRegistrar $registrar = null;

	public static function getRegistrar() : BlockSerializerDeserializerRegistrar{
		if(self::$registrar === null){
			$deserializer = new BlockStateToObjectDeserializer();
			$serializer = new BlockObjectToStateSerializer();
			self::$registrar = new BlockSerializerDeserializerRegistrar($deserializer, $serializer);
			VanillaBlockMappings::init(self::$registrar);
		}
		return self::$registrar;
	}

	public static function getDeserializer() : BlockStateToObjectDeserializer{
		return self::getRegistrar()->deserializer;
	}

	public static function getSerializer() : BlockObjectToStateSerializer{
		return self::getRegistrar()->serializer;
	}

	public static function getUpgrader() : BlockDataUpgrader{
		if(self::$blockDataUpgrader === null){
			//2026-09-17: BLOCK_STATE_UPGRADE_SCHEMA holds a project-authored schema (not from Mojang/
			//upstream pocketmine/bedrock-block-upgrade-schema) that adds the connection_east/north/
			//south/west properties fences/glass panes/bars gained in Bedrock 1.26.50 to older, already-
			//persisted block state data - see resources/vanilla-1.26.50-data/README.md.
			$blockStateUpgrader = new BlockStateUpgrader(BlockStateUpgradeSchemaUtils::loadSchemas(
				Path::join(BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH, 'nbt_upgrade_schema'),
				PHP_INT_MAX
			) + BlockStateUpgradeSchemaUtils::loadSchemas(
				BedrockDataFiles::BLOCK_STATE_UPGRADE_SCHEMA,
				PHP_INT_MAX
			));
			self::$blockDataUpgrader = new BlockDataUpgrader(
				BlockIdMetaUpgrader::loadFromString(
					Filesystem::fileGetContents(Path::join(
						BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH,
						'id_meta_to_nbt/1.12.0.bin'
					)),
					LegacyBlockIdToStringIdMap::getInstance(),
					$blockStateUpgrader
				),
				$blockStateUpgrader
			);
		}

		return self::$blockDataUpgrader;
	}

	public static function getUnknownBlockStateData() : BlockStateData{
		return self::$unknownBlockStateData ??= BlockStateData::current(BlockTypeNames::INFO_UPDATE, []);
	}

	/**
	 * 2026-09-18: used only for loading a *network* BlockStateDictionary (one protocol's own real
	 * vanilla palette file), deliberately WITHOUT our own local schemas (connection_east/minecraft:corner
	 * - see getUpgrader()). Those describe changes to OUR internal "current" format, not real changes a
	 * given older protocol's actual client ever received - applying them here would inject properties
	 * into that protocol's block state hashes (network IDs are content hashes for protocol >= 1.26.40)
	 * that a real client's own independently-computed hash for the same block would never include,
	 * making the ID unrecognisable to it. Confirmed live: real 1.26.45 client, fences/stairs invisible
	 * and unplaceable after the shared upgrader (with our local schemas) got used here too.
	 */
	public static function getVendorOnlyBlockStateUpgrader() : BlockStateUpgrader{
		return self::$vendorOnlyBlockStateUpgrader ??= new BlockStateUpgrader(BlockStateUpgradeSchemaUtils::loadSchemas(
			Path::join(BEDROCK_BLOCK_UPGRADE_SCHEMA_PATH, 'nbt_upgrade_schema'),
			PHP_INT_MAX
		));
	}
}
