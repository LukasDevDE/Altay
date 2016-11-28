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

namespace pocketmine\level\format\generic;

class SubChunk{
	protected $y;

	protected $ids;
	protected $data;
	protected $blockLight;
	protected $skyLight;

	private static function assignData(&$target, $data, $length){
		if(strlen($data) !== $length){
			assert($data === "", "Invalid non-zero length given, expected $length, got " . strlen($data));
			$target = str_repeat("\x00", $length);
		}else{
			$target = $data;
		}
	}

	public function __construct(int $y, string $ids = "", string $data = "", string $blockLight = "", string $skyLight = ""){
		$this->y = $y;
		self::assignData($this->ids, $ids, 4096);
		self::assignData($this->data, $data, 2048);
		self::assignData($this->blockLight, $blockLight, 2048);
		self::assignData($this->skyLight, $skyLight, 2048);
	}
	
	public function getY() : int{
		return $this->y;
	}

	public function isEmpty() : bool{
		assert(strlen($this->ids) === 4096, "Wrong length of ID array, expecting 4096 bytes, got " . strlen($this->ids));
		return substr_count($this->ids, "\x00") === 4096;
	}

	public function getBlockId(int $x, int $y, int $z) : int{
		return ord($this->ids{($x << 8) | ($z << 4) | $y});
	}

	public function setBlockId(int $x, int $y, int $z, int $id){
		$this->ids{($x << 8) | ($z << 4) | $y} = chr($id);
	}

	public function getBlockData(int $x, int $y, int $z) : int{
		$m = ord($this->data{($x << 7) + ($z << 3) + ($y >> 1)});
		if(($y & 1) === 0){
			return $m & 0x0f;
		}else{
			return $m >> 4;
		}
	}

	public function setBlockData(int $x, int $y, int $z, int $data){
		$i = ($x << 7) | ($z << 3) | ($y >> 1);
		$current = ord($this->data{$i});
		if(($y & 1) === 0){
			$this->data{$i} = chr(($current & 0xf0) | ($data & 0x0f));
		}else{
			$this->data{$i} = chr((($data & 0x0f) << 4) | ($current & 0x0f));
		}
		$this->hasChanged = true;
	}

	public function getFullBlock(int $x, int $y, int $z) : int{
		$i = ($x << 8) | ($z << 4) | $y;
		if(($y & 1) === 0){
			return (ord($this->ids{$i}) << 4) | (ord($this->data{$i >> 1}) & 0x0f);
		}else{
			return (ord($this->ids{$i}) << 4) | (ord($this->data{$i >> 1}) >> 4);
		}
	}

	public function setBlock(int $x, int $y, int $z, $id = null, $data = null) : bool{
		$i = ($x << 8) | ($z << 4) | $y;
		$changed = false;
		if($id !== null){
			$block = chr($id);
			if($this->ids{$i} !== $block){
				$this->ids{$i} = $block;
				$changed = true;
			}
		}

		if($data !== null){
			$i >>= 1;
			$byte = ord($this->data{$i});
			if(($y & 1) === 0){
				$this->data{$i} = chr(($byte & 0xf0) | ($data & 0x0f));
			}else{
				$this->data{$i} = chr((($data & 0x0f) << 4) | ($byte & 0x0f));
			}
			if($this->data{$i} !== $byte){
				$changed = true;
			}
		}

		return $changed;
	}

	public function getBlockSkyLight(int $x, int $y, int $z) : int{
		$byte = ord($this->skyLight{($x << 7) + ($z << 3) + ($y >> 1)});
		if(($y & 1) === 0){
			return $byte & 0x0f;
		}else{
			return $byte >> 4;
		}
	}

	public function setBlockSkyLight(int $x, int $y, int $z, int $level){
		$i = ($x << 7) + ($z << 3) + ($y >> 1);
		$byte = ord($this->skyLight{$i});
		if(($y & 1) === 0){
			$this->skyLight{$i} = chr(($byte & 0xf0) | ($level & 0x0f));
		}else{
			$this->skyLight{$i} = chr((($level & 0x0f) << 4) | ($byte & 0x0f));
		}
	}

	public function getBlockLight(int $x, int $y, int $z) : int{
		$byte = ord($this->blockLight{($x << 7) + ($z << 3) + ($y >> 1)});
		if(($y & 1) === 0){
			return $byte & 0x0f;
		}else{
			return $byte >> 4;
		}
	}

	public function setBlockLight(int $x, int $y, int $z, int $level){
		$i = ($x << 7) + ($z << 3) + ($y >> 1);
		$byte = ord($this->blockLight{$i});
		if(($y & 1) === 0){
			$this->blockLight{$i} = chr(($byte & 0xf0) | ($level & 0x0f));
		}else{
			$this->blockLight{$i} = chr((($level & 0x0f) << 4) | ($byte & 0x0f));
		}
	}

	public function getHighestBlockAt(int $x, int $z) : int{
		for($y = 15; $y >= 0; --$y){
			if($this->ids{($x << 8) | ($z << 4) | $y} !== "\x00"){
				return $y;
			}
		}

		return -1; //highest block not in this subchunk
	}
	
	public function getBlockIdColumn(int $x, int $z) : string{
		return substr($this->ids, (($x << 8) | ($z << 4)), 16);
	}
	
	public function getBlockDataColumn(int $x, int $z) : string{
		return substr($this->data, (($x << 7) | ($z << 3)), 8);
	}
	
	public function getBlockLightColumn(int $x, int $z) : string{
		return substr($this->blockLight, (($x << 7) | ($z << 3)), 8);
	}
	
	public function getSkyLightColumn(int $x, int $z) : string{
		return substr($this->skyLight, (($x << 7) | ($z << 3)), 8);
	}

	public function getBlockIdArray() : string{
		assert(strlen($this->ids) === 4096, "Wrong length of ID array, expecting 4096 bytes, got " . strlen($this->ids));
		return $this->ids;
	}

	public function getBlockDataArray() : string{
		assert(strlen($this->data) === 2048, "Wrong length of data array, expecting 2048 bytes, got " . strlen($this->data));
		return $this->data;
	}

	public function getBlockLightArray() : string{
		assert(strlen($this->blockLight) === 2048, "Wrong length of light array, expecting 2048 bytes, got " . strlen($this->blockLight));
		return $this->blockLight;
	}

	public function getSkyLightArray() : string{
		assert(strlen($this->skyLight) === 2048, "Wrong length of skylight array, expecting 2048 bytes, got " . strlen($this->skyLight));
		return $this->skyLight;
	}
}