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

namespace pocketmine\data\bedrock;

use pocketmine\world\format\io\leveldb\ChunkVersion;
use pocketmine\world\format\io\leveldb\SubChunkVersion;

/**
 * All version infos related to current Minecraft data version support
 * These are mostly related to world storage but may also influence network stuff
 */
final class WorldDataVersions{
	/**
	 * Bedrock version of the most recent backwards-incompatible change to blockstates.
	 *
	 * This is *NOT* the same as current game version. It should match the numbers in the
	 * newest blockstate upgrade schema used in BedrockBlockUpgradeSchema.
	 *
	 * 2026-09-17: bumped revision 33 -> 34 for our own local schema
	 * (0332_1.21.60.33_to_1.21.60.34_syntaxstudios_horizontal_connections.json) that adds the
	 * minecraft:connection_east/north/south/west properties Mojang added to fences/glass panes/bars
	 * in Bedrock 1.26.50 - without this, block state data (shop items, chests, etc) saved before the
	 * HorizontalConnectableTrait port fails to deserialize with "Property ... is missing".
	 */
	public const BLOCK_STATES =
		(1 << 24) | //major
		(21 << 16) | //minor
		(60 << 8) | //patch
		(34); //revision

	public const CHUNK = ChunkVersion::v1_21_120;
	public const SUBCHUNK = SubChunkVersion::PALETTED_MULTI;

	public const STORAGE = 10;

	/**
	 * Highest NetworkVersion of Bedrock worlds currently supported by PocketMine-MP.
	 *
	 * This may be lower than the current protocol version if PocketMine-MP does not yet support features of the newer
	 * version. This allows the protocol to be updated independently of world format support.
	 */
	public const NETWORK = 924;

	public const LAST_OPENED_IN = [
		1, //major
		26, //minor
		0, //patch
		2, //revision
		0 //is beta
	];
}
