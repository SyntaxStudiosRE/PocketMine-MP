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

use pocketmine\data\runtime\RuntimeDataDescriber;

/**
 * 2026-09-17: ported from axolotl-pm/PocketMine-MP to model the connection_east/north/south/west
 * block states Mojang added to fences/glass panes/bars in Bedrock 1.26.50 - previously these blocks
 * had no network-visible connection state at all here, so real 1.26.50+ clients couldn't map them to
 * anything in the palette and they fell back to a placeholder block (originally info_update, now
 * stone - see BlockTranslator's fallbackStateData).
 */
trait HorizontalConnectableTrait{
	/** @var int[] facing => facing */
	protected array $connections = [];

	/**
	 * @see Block::describeBlockOnlyState()
	 */
	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->horizontalFacingFlags($this->connections);
	}

	public function isConnectedAt(int $facing) : bool{
		return isset($this->connections[$facing]);
	}

	public function setConnectedAt(int $facing, bool $connected) : void{
		if($connected){
			$this->connections[$facing] = $facing;
		}else{
			unset($this->connections[$facing]);
		}
	}

	/**
	 * @see Block::onNearbyBlockChange()
	 */
	public function onNearbyBlockChange() : void{
		if($this->recalculateConnections()){
			$this->position->getWorld()->setBlock($this->position, $this);
		}
	}

	/**
	 * Implement this to (re)compute connections from the block's neighbours. Must return whether anything changed.
	 */
	abstract protected function recalculateConnections() : bool;
}
