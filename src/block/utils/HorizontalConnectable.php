<?php

/*
 * This file is part of BedrockProtocol.
 * Copyright (C) 2014-2022 PocketMine Team <https://github.com/pmmp/BedrockProtocol>
 *
 * BedrockProtocol is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 */

declare(strict_types=1);

namespace pocketmine\block\utils;

/**
 * Implemented by blocks (fences, glass panes, bars, ...) that carry per-horizontal-facing connection state.
 */
interface HorizontalConnectable{

	/**
	 * @param int $facing one of Facing::NORTH/EAST/SOUTH/WEST
	 */
	public function isConnectedAt(int $facing) : bool;

	/**
	 * @param int $facing one of Facing::NORTH/EAST/SOUTH/WEST
	 */
	public function setConnectedAt(int $facing, bool $connected) : void;
}
