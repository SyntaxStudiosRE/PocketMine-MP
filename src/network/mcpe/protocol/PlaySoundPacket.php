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

namespace pocketmine\network\mcpe\protocol;

use pmmp\encoding\ByteBufferReader;
use pmmp\encoding\ByteBufferWriter;
use pmmp\encoding\LE;
use pmmp\encoding\VarInt;
use pocketmine\network\mcpe\protocol\serializer\CommonTypes;
use pocketmine\network\mcpe\protocol\types\BlockPosition;

class PlaySoundPacket extends DataPacket implements ClientboundPacket{
	public const NETWORK_ID = ProtocolInfo::PLAY_SOUND_PACKET;

	public string $soundName;
	public float $x;
	public float $y;
	public float $z;
	public float $volume;
	public float $pitch;
	public ?int $serverSoundHandle = null;
	//2026-09-16: new in Bedrock 1.26.50 (protocol 2192, see CloudburstMC/Protocol's
	//PlaySoundSerializer_v2192). Defaults preserve pre-2192 behaviour: play once, don't bypass the
	//listener range check, and start from the beginning.
	public int $loopCount = 0;
	public bool $bypassListenerRangeCheck = false;
	public ?float $playbackPositionSeconds = null;

	/**
	 * @generate-create-func
	 */
	public static function create(
		string $soundName,
		float $x,
		float $y,
		float $z,
		float $volume,
		float $pitch,
		?int $serverSoundHandle,
		int $loopCount = 0,
		bool $bypassListenerRangeCheck = false,
		?float $playbackPositionSeconds = null,
	) : self{
		$result = new self;
		$result->soundName = $soundName;
		$result->x = $x;
		$result->y = $y;
		$result->z = $z;
		$result->volume = $volume;
		$result->pitch = $pitch;
		$result->serverSoundHandle = $serverSoundHandle;
		$result->loopCount = $loopCount;
		$result->bypassListenerRangeCheck = $bypassListenerRangeCheck;
		$result->playbackPositionSeconds = $playbackPositionSeconds;
		return $result;
	}

	protected function decodePayload(ByteBufferReader $in, int $protocolId) : void{
		$this->soundName = CommonTypes::getString($in);
		$blockPosition = CommonTypes::getBlockPosition($in, $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);
		$this->x = $blockPosition->getX() / 8;
		$this->y = $blockPosition->getY() / 8;
		$this->z = $blockPosition->getZ() / 8;
		$this->volume = LE::readFloat($in);
		$this->pitch = LE::readFloat($in);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_50){
			//1.26.50 moves serverSoundHandle after these two new fields (see PlaySoundSerializer_v2192,
			//which intentionally skips the 2168-era ancestor and re-adds serverSoundHandle itself).
			$this->loopCount = VarInt::readUnsignedInt($in);
			$this->bypassListenerRangeCheck = CommonTypes::getBool($in);
			$this->serverSoundHandle = CommonTypes::readOptional($in, LE::readUnsignedLong(...));
			$this->playbackPositionSeconds = CommonTypes::readOptional($in, LE::readFloat(...));
		}elseif($protocolId >= ProtocolInfo::PROTOCOL_1_26_20){
			$this->serverSoundHandle = CommonTypes::readOptional($in, LE::readUnsignedLong(...));
		}
	}

	protected function encodePayload(ByteBufferWriter $out, int $protocolId) : void{
		CommonTypes::putString($out, $this->soundName);
		CommonTypes::putBlockPosition($out, new BlockPosition((int) ($this->x * 8), (int) ($this->y * 8), (int) ($this->z * 8)), $protocolId >= ProtocolInfo::PROTOCOL_1_26_10);
		LE::writeFloat($out, $this->volume);
		LE::writeFloat($out, $this->pitch);
		if($protocolId >= ProtocolInfo::PROTOCOL_1_26_50){
			VarInt::writeUnsignedInt($out, $this->loopCount);
			CommonTypes::putBool($out, $this->bypassListenerRangeCheck);
			CommonTypes::writeOptional($out, $this->serverSoundHandle, LE::writeUnsignedLong(...));
			CommonTypes::writeOptional($out, $this->playbackPositionSeconds, LE::writeFloat(...));
		}elseif($protocolId >= ProtocolInfo::PROTOCOL_1_26_20){
			CommonTypes::writeOptional($out, $this->serverSoundHandle, LE::writeUnsignedLong(...));
		}
	}

	public function handle(PacketHandlerInterface $handler) : bool{
		return $handler->handlePlaySound($this);
	}
}
