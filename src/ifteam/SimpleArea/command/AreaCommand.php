<?php

namespace ifteam\SimpleArea\command;

use ifteam\SimpleArea\database\area\AreaSection;
use ifteam\SimpleArea\SimpleArea;
use pocketmine\command\CommandSender;
use pocketmine\command\PluginCommand;
use pocketmine\Player;
use pocketmine\plugin\Plugin;

class AreaCommand extends PluginCommand
{
	use SimpleAreaCommandTrait;
	
	public function __construct(string $name, SimpleArea $simpleArea) {
		parent::__construct($name, $simpleArea);
		$this->simpleArea = $simpleArea;
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool {
		if (!$sender instanceof Player) {
			/* TODO : add message */
			return true;
		}
		$args[0] = $args[0] ?? "default";
		switch (strtolower($args[0])) {
			case $this->getSimpleArea()->get("commands-area-move") :
				if (!$sender->hasPermission("simplearea.area.move")) {
					return false;
				}
				if (!isset ($args [1])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-move-help"));
					return true;
				}
				$this->getAreaManager()->move($sender, $args [1]);
				break;
			case $this->getSimpleArea()->get("commands-area-autocreate") :
				if (!$sender->hasPermission("simplearea.area.autocreate")) {
					return false;
				}
				$whiteWorld = $this->getWhiteWorldProvider()->get($sender->getLevel());
				if (!$whiteWorld->isAutoCreateAllow()) {
					$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("whiteworld-autocreate-not-allowed"));
					return true;
				}
				$this->getAreaManager()->autoCreate($sender);
				break;
			case $this->getSimpleArea()->get("commands-area-manualcreate") :
				if (!$sender->hasPermission("simplearea.area.manualcreate")) {
					return false;
				}
				$whiteWorld = $this->getWhiteWorldProvider()->get($sender->getLevel());
				if (!$whiteWorld->isManualCreateAllow()) {
					$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("whiteworld-manualcreate-not-allowed"));
					return true;
				}
				if (isset ($this->queue ["manual"] [strtolower($sender->getName())])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("please-choose-two-pos"));
					return true;
				}
				if (!isset ($this->queue ["manual"] [strtolower($sender->getName())])) {
					$this->queue ["manual"] [strtolower($sender->getName())] = [
						"startX" => null,
						"endX" => null,
						"startZ" => null,
						"endZ" => null,
						"startLevel" => $sender->getLevel()->getFolderName()
					];
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("start-manual-create-area"));
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("please-choose-two-pos"));
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("you-can-stop-create-manual-area"));
				}
				break;
			case $this->getSimpleArea()->get("commands-area-buy") :
				if (!$sender->hasPermission("simplearea.area.buy")) {
					return false;
				}
				$area = $this->getAreaProvider()->getArea($sender->getLevel(), $sender->x, $sender->z);
				if (!$area instanceof AreaSection) {
					$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("area-doesent-exist"));
					$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("commands-area-info-help"));
					return false;
				}
				if (!isset ($this->queue ["areaBuy"] [strtolower($sender->getName())])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("do-you-want-buy-this-area"));
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("if-you-want-to-buy-please-command"));
					$this->queue ["areaBuy"] [strtolower($sender->getName())] = [
						"time" => $this->makeTimestamp()
					];
					return true;
				}
				$before = $this->queue ["areaBuy"] [strtolower($sender->getName())] ["time"];
				$after = $this->makeTimestamp();
				$timeout = intval($after - $before);

				if ($timeout <= 10) {
					$this->getAreaManager()->buy($sender);
				} else {
					$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("area-buy-time-over"));
				}
				unset ($this->queue ["areaBuy"] [strtolower($sender->getName())]);
				break;
			case $this->getSimpleArea()->get("commands-area-sell") :
				if (!$sender->hasPermission("simplearea.area.sell")) {
					return false;
				}
				$area = $this->getAreaProvider()->getArea($sender->getLevel(), $sender->x, $sender->z);
				if (!$area instanceof AreaSection) {
					$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("area-doesent-exist"));
					$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("commands-area-info-help"));
					return false;
				}
				if (!isset ($this->queue ["areaSell"] [strtolower($sender->getName())])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("do-you-want-sell-this-area"));
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("if-you-want-to-sell-please-command"));
					$this->queue ["areaSell"] [strtolower($sender->getName())] = [
						"time" => $this->makeTimestamp()
					];
					return true;
				}
				$before = $this->queue ["areaSell"] [strtolower($sender->getName())] ["time"];
				$after = $this->makeTimestamp();
				$timeout = intval($after - $before);


				if ($timeout <= 10) {
					$this->getAreaManager()->sell($sender);
				} else {
					$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("area-sell-time-over"));
				}
				unset ($this->queue ["areaSell"] [strtolower($sender->getName())]);
				break;
			case $this->getSimpleArea()->get("commands-area-give") :
				if (!$sender->hasPermission("simplearea.area.give")) {
					return false;
				}
				if (!isset ($args [1])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-give-help"));
					return true;
				}
				$this->getAreaManager()->give($sender, $args [1]);
				break;
			case $this->getSimpleArea()->get("commands-area-info") :
				if (!$sender->hasPermission("simplearea.area.info")) {
					return false;
				}
				$this->getAreaManager()->info($sender);
				break;
			case $this->getSimpleArea()->get("commands-area-share") :
				if (!$sender->hasPermission("simplearea.area.share")) {
					return false;
				}
				if (!isset ($args [1])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-share-help"));
					return true;
				}
				$this->getAreaManager()->share($sender, $args [1]);
				break;
			case $this->getSimpleArea()->get("commands-area-deport") :
				if (!$sender->hasPermission("simplearea.area.deport")) {
					return false;
				}
				if (!isset ($args [1])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-deport-help"));
					return true;
				}
				$this->getAreaManager()->deport($sender, $args [1]);
				break;
			case $this->getSimpleArea()->get("commands-area-sharelist") :
				if (!$sender->hasPermission("simplearea.area.sharelist")) {
					return false;
				}
				$this->getAreaManager()->shareList($sender);
				break;
			case $this->getSimpleArea()->get("commands-area-welcome") :
				if (!$sender->hasPermission("simplearea.area.welcome")) {
					return false;
				}
				if (!isset ($args [1])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-welcome-help"));
					return true;
				}
				array_shift($args);
				$string = implode(" ", $args);
				$this->getAreaManager()->welcome($sender, $string);
				break;
			case $this->getSimpleArea()->get("commands-area-protect") :
				if (!$sender->hasPermission("simplearea.area.protect")) {
					return false;
				}
				$this->getAreaManager()->protect($sender);
				break;
			case $this->getSimpleArea()->get("commands-area-areaprice") :
				if (!$sender->hasPermission("simplearea.area.areaprice")) {
					return false;
				}
				if (!isset ($args [1])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-areaprice-help"));
					return true;
				}
				$this->getAreaManager()->areaPrice($sender, $args [1]);
				break;
			case $this->getSimpleArea()->get("commands-area-setfence") :
				if (!$sender->hasPermission("simplearea.area.setfence")) {
					return false;
				}
				if (!isset ($args [1])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-setfence-help"));
					return true;
				}
				$this->getAreaManager()->setFence($sender, $args [1]);
				break;
			case $this->getSimpleArea()->get("commands-area-setinvensave") :
				if (!$sender->hasPermission("simplearea.area.setinvensave")) {
					return false;
				}
				$this->getAreaManager()->setInvenSave($sender);
				break;
			case $this->getSimpleArea()->get("commands-area-changemode") :
				if (!$sender->hasPermission("simplearea.area.changemode")) {
					return false;
				}
				$this->getAreaManager()->changeMode($sender);
				break;
			case $this->getSimpleArea()->get("commands-area-abandon") :
				if (!$sender->hasPermission("simplearea.area.abandon")) {
					return false;
				}
				if (!isset ($this->queue ["areaAbandon"] [strtolower($sender->getName())])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("do-you-want-area-abandon"));
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("if-you-want-to-abandon-do-again"));
					$this->queue ["areaAbandon"] [strtolower($sender->getName())] ["time"] = $this->makeTimestamp();
				} else {
					$before = $this->queue ["areaAbandon"] [strtolower($sender->getName())] ["time"];
					$after = $this->makeTimestamp();
					$timeout = intval($after - $before);

					if ($timeout <= 10) {
						$this->getAreaManager()->abandon($sender);
					} else {
						$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("area-abandon-time-over"));
					}
					if (isset ($this->queue ["areaAbandon"] [strtolower($sender->getName())])) {
						unset ($this->queue ["areaAbandon"] [strtolower($sender->getName())]);
					}
				}
				break;
			case $this->getSimpleArea()->get("commands-area-cancel") :
				if (isset ($this->queue ["manual"] [strtolower($sender->getName())])) {
					unset ($this->queue ["manual"] [strtolower($sender->getName())]);
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("area-cancel-stopped"));
				} else {
					if (isset ($this->queue ["rentCreate"] [strtolower($sender->getName())])) {
						unset ($this->queue ["rentCreate"] [strtolower($sender->getName())]);
						$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("area-cancel-stopped"));
					} else {
						if (isset ($this->queue ["areaSizeUp"] [strtolower($sender->getName())])) {
							unset ($this->queue ["areaSizeUp"] [strtolower($sender->getName())]);
							$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("area-cancel-stopped"));
						} else {
							if (isset ($this->queue ["areaSizeDown"] [strtolower($sender->getName())])) {
								unset ($this->queue ["areaSizeDown"] [strtolower($sender->getName())]);
								$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("area-cancel-stopped"));
							} else {
								$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("area-cancel-failed"));
							}
						}
					}
				}
				break;
			case $this->getSimpleArea()->get("commands-area-canbuylist") :
				if (!$sender->hasPermission("simplearea.area.canbuylist")) {
					return false;
				}
				if (!isset ($args [1]) or !is_numeric($args [1])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-move-help"));
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-canbuylist-help"));
					$this->getAreaManager()->saleList($sender);
					return true;
				}
				$this->getAreaManager()->saleList($sender, $args [1]);
				break;
			case $this->getSimpleArea()->get("commands-area-accessdeny") :
				if (!$sender->hasPermission("simplearea.area.accessdeny")) {
					return false;
				}
				$this->getAreaManager()->accessDeny($sender);
				break;
			case $this->getSimpleArea()->get("commands-area-sizeup") :
				if (!$sender->hasPermission("simplearea.area.sizeup")) {
					return false;
				}
				if (!isset ($this->queue ["areaSizeUp"] [strtolower($sender->getName())])) {
					$area = $this->getAreaProvider()->getArea($sender->getLevel(), $sender->x, $sender->z);
					if (!$area instanceof AreaSection) {
						$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("area-doesent-exist"));
						$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("commands-area-info-help"));
						return true;
					}
					if (!$area->isOwner($sender->getName())) {
						$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("youre-not-owner"));
						return true;
					}
					$this->queue ["areaSizeUp"] [strtolower($sender->getName())] = [
						"startX" => 0,
						"endX" => 0,
						"startZ" => 0,
						"endZ" => 0,
						"id" => $area->getId(),
						"isTouched" => false,
						"resizePrice" => 0,
						"startLevel" => $sender->getLevel()->getFolderName()
					];
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("area-size-up-start"));
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("please-choose-one-point"));
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("you-can-stop-create-manual-area"));
					return true;
				}
				$sizeUpData = $this->queue ["areaSizeUp"] [strtolower($sender->getName())];
				if (!$sizeUpData ["isTouched"]) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("please-choose-one-point"));
					return true;
				}
				$level = $sizeUpData ["startLevel"];
				$id = $sizeUpData ["id"];
				$startX = $sizeUpData ["startX"];
				$endX = $sizeUpData ["endX"];
				$startZ = $sizeUpData ["startZ"];
				$endZ = $sizeUpData ["endZ"];
				$price = $sizeUpData ["resizePrice"];
				$this->getAreaManager()->areaSizeUp($sender, $level, $id, $startX, $endX, $startZ, $endZ, $price);
				if (isset ($this->queue ["areaSizeUp"] [strtolower($sender->getName())])) {
					unset ($this->queue ["areaSizeUp"] [strtolower($sender->getName())]);
				}
				break;
			case $this->getSimpleArea()->get("commands-area-sizedown") :
				if (!$sender->hasPermission("simplearea.area.sizedown")) {
					return false;
				}
				if (!isset ($this->queue ["areaSizeDown"] [strtolower($sender->getName())])) {
					$area = $this->getAreaProvider()->getArea($sender->getLevel(), $sender->x, $sender->z);
					if (!$area instanceof AreaSection) {
						$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("area-doesent-exist"));
						$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("commands-area-info-help"));
						return true;
					}
					if (!$area->isOwner($sender->getName())) {
						$this->getSimpleArea()->alert($sender, $this->getSimpleArea()->get("youre-not-owner"));
						return true;
					}
					$this->queue ["areaSizeDown"] [strtolower($sender->getName())] = [
						"startX" => 0,
						"endX" => 0,
						"startZ" => 0,
						"endZ" => 0,
						"id" => $area->getId(),
						"isTouched" => false,
						"startLevel" => $sender->getLevel()->getFolderName()
					];
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("area-size-down-start"));
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("please-choose-inside-point"));
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("you-can-stop-create-manual-area"));
					return true;
				}
				$sizeUpData = $this->queue ["areaSizeDown"] [strtolower($sender->getName())];
				if (!$sizeUpData ["isTouched"]) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("please-choose-inside-point"));
					return true;
				}
				$level = $sizeUpData ["startLevel"];
				$id = $sizeUpData ["id"];
				$startX = $sizeUpData ["startX"];
				$endX = $sizeUpData ["endX"];
				$startZ = $sizeUpData ["startZ"];
				$endZ = $sizeUpData ["endZ"];
				$this->getAreaManager()->areaSizeDown($sender, $level, $id, $startX, $endX, $startZ, $endZ);
				unset ($this->queue ["areaSizeDown"] [strtolower($sender->getName())]);
				break;
			case $this->getSimpleArea()->get("commands-area-delete") :
				if (!$sender->hasPermission("simplearea.area.delete")) {
					return false;
				}
				if (!isset ($this->queue ["areaDelete"] [strtolower($sender->getName())])) {
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("do-you-want-area-delete"));
					$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("if-you-want-to-delete-do-again"));
					$this->queue ["areaDelete"] [strtolower($sender->getName())] ["time"] = $this->makeTimestamp();
				} else {
					$before = $this->queue ["areaDelete"] [strtolower($sender->getName())] ["time"];
					$after = $this->makeTimestamp();
					$timeout = intval($after - $before);

					if ($timeout <= 10) {
						$this->getAreaManager()->delete($sender);
					} else {
						$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("area-delete-time-over"));
					}
					if (isset ($this->queue ["areaDelete"] [strtolower($sender->getName())])) {
						unset ($this->queue ["areaDelete"] [strtolower($sender->getName())]);
					}
				}
				break;
			case $this->getSimpleArea()->get("commands-area-getlist") :
				if (!$sender->hasPermission("simplearea.area.getlist")) {
					return false;
				}
				$this->getAreaManager()->getList($sender);
				break;
			case $this->getSimpleArea()->get("commands-area-pvpallow") :
				if (!$sender->hasPermission("simplearea.area.pvpallow")) {
					return false;
				}
				$this->getAreaManager()->pvpallow($sender);
				break;

			default :
				$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-help-1"));
				$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-help-2"));
				$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-help-3"));
				$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-help-4"));
				$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-help-5"));
				$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-help-6"));
				$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-help-7"));
				$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-help-8"));
				$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-help-9"));
				$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-help-10"));
				$this->getSimpleArea()->message($sender, $this->getSimpleArea()->get("commands-area-help-11"));
		}
	}
}