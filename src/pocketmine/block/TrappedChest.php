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

namespace pocketmine\block;

use pocketmine\item\Item;
use pocketmine\item\Tool;
use pocketmine\math\AxisAlignedBB;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\Enum;
use pocketmine\nbt\tag\Int as IntTag;
use pocketmine\nbt\tag\String as StringTag;
use pocketmine\Player;
use pocketmine\tile\Chest as TileChest;
use pocketmine\tile\Tile;

class TrappedChest extends Transparent{

	protected $id = self::TRAPPED_CHEST;
	protected $lastPower = 0;

	public function getName(){
		return "Trapped Chest";
	}

	public function getPowerLevel(){
		return $this->lastPower;
	}

	public function isStronglyPowering(Block $block){
		return $this->subtract(0, 1)->equals($block) and !$block->isTransparent();
	}

	public function place(Item $item, Block $block, Block $target, $face, $fx, $fy, $fz, Player $player = null){
		$faces = [
			0 => 4,
			1 => 2,
			2 => 5,
			3 => 3,
		];

		$chest = null;
		$this->meta = $faces[$player instanceof Player ? $player->getDirection() : 0];

		for($side = 2; $side <= 5; ++$side){
			if(($this->meta === 4 or $this->meta === 5) and ($side === 4 or $side === 5)){
				continue;
			}elseif(($this->meta === 3 or $this->meta === 2) and ($side === 2 or $side === 3)){
				continue;
			}
			$c = $this->getSide($side);
			if($c instanceof Chest and $c->getDamage() === $this->meta){
				$tile = $this->getLevel()->getTile($c);
				if($tile instanceof TileChest and !$tile->isPaired()){
					$chest = $tile;
					break;
				}
			}
		}

		$this->getLevel()->setBlock($block, $this, true, true);
		$nbt = new CompoundTag("", [
			new Enum("Items", []),
			new StringTag("id", Tile::CHEST),
			new IntTag("x", $this->x),
			new IntTag("y", $this->y),
			new IntTag("z", $this->z)
		]);
		$nbt->Items->setTagType(NBT::TAG_Compound);

		if($item->hasCustomName()){
			$nbt->CustomName = new StringTag("CustomName", $item->getCustomName());
		}

		if($item->hasCustomBlockData()){
			foreach($item->getCustomBlockData() as $key => $v){
				$nbt->{$key} = $v;
			}
		}

		$tile = Tile::createTile("Chest", $this->getLevel()->getChunk($this->x >> 4, $this->z >> 4), $nbt);

		if($chest instanceof TileChest and $tile instanceof TileChest){
			$chest->pairWith($tile);
			$tile->pairWith($chest);
		}

		return true;
	}

	public function recalculatePower(){
		$new = max(min($this->countViewers(), 15), 0);
		if($this->lastPower !== $new){
			$this->lastPower = $new;
			$this->getLevel()->updateAround($this, Level::BLOCK_UPDATE_REDSTONE);
		}
		$this->getLevel()->setBlock($this, new Air(), true, true);

		return true;
	}

	public function onActivate(Item $item, Player $player = null){
		if($player instanceof Player){
			$top = $this->getSide(1);
			if($top->isTransparent() !== true){
				return true;
			}

			$t = $this->getLevel()->getTile($this);
			$chest = null;
			if($t instanceof TileChest){
				$chest = $t;
			}else{
				$nbt = new CompoundTag("", [
					new Enum("Items", []),
					new StringTag("id", Tile::CHEST),
					new IntTag("x", $this->x),
					new IntTag("y", $this->y),
					new IntTag("z", $this->z)
				]);
				$nbt->Items->setTagType(NBT::TAG_Compound);
				$chest = Tile::createTile("Chest", $this->getLevel()->getChunk($this->x >> 4, $this->z >> 4), $nbt);
			}

			if(isset($chest->namedtag->Lock) and $chest->namedtag->Lock instanceof String){
				if($chest->namedtag->Lock->getValue() !== $item->getCustomName()){
					return true;
				}
			}

			if($player->isCreative()){
				return true;
			}
			$player->addWindow($chest->getInventory());
		}

		return true;
	}

	public function getPoweringSides(){
		return [self::SIDE_DOWN];
	}
}
