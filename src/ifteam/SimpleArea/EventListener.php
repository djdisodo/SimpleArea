<?php

namespace ifteam\SimpleArea;

use pocketmine\event\entity\EntityBlockChangeEvent;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityCombustEvent;
use pocketmine\event\player\PlayerDeathEvent;
use ifteam\SimpleArea\database\area\AreaProvider;
use ifteam\SimpleArea\database\world\WhiteWorldProvider;
use ifteam\SimpleArea\database\user\UserProperties;
use pocketmine\Player;
use ifteam\SimpleArea\database\area\AreaManager;
use ifteam\SimpleArea\database\world\WhiteWorldManager;
use ifteam\SimpleArea\database\area\AreaSection;
use ifteam\SimpleArea\database\world\WhiteWorldData;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityCombustByBlockEvent;
use pocketmine\block\Fire;
use pocketmine\block\Block;
use pocketmine\command\CommandSender;
use pocketmine\command\Command;
use ifteam\SimpleArea\database\minefarm\MineFarmManager;
use ifteam\SimpleArea\event\AreaModifyEvent;
use ifteam\SimpleArea\database\rent\RentManager;
use ifteam\SimpleArea\database\rent\RentProvider;
use ifteam\SimpleArea\database\rent\RentSection;
use pocketmine\utils\TextFormat;
use pocketmine\math\Vector3;
use pocketmine\tile\Sign;
use pocketmine\event\player\PlayerQuitEvent;

class EventListener implements Listener
{
	private $plugin;
	private $areaProvider;
	private $whiteWorldProvider;
	private $userProperties;
	private $areaManager;
	private $whiteworldManager;
	private $mineFarmManager;
	private $rentProvider;

	private $rentManager;
	public $queue;

	/* solo */
	public $movelist = [];

	public function __construct(SimpleArea $plugin) {
		$this->plugin = $plugin;
		$this->areaProvider = AreaProvider::getInstance();
		$this->rentProvider = RentProvider::getInstance();
		$this->whiteWorldProvider = WhiteWorldProvider::getInstance();
		$this->userProperties = UserProperties::getInstance();
		$this->areaManager = AreaManager::getInstance();
		$this->whiteworldManager = WhiteWorldManager::getInstance();
		$this->mineFarmManager = MineFarmManager::getInstance();
		$this->rentManager = RentManager::getInstance();

	}


	public function onMove(PlayerMoveEvent $event) : void {
		$user = strtolower($event->getPlayer()->getName());

		if (isset($this->movelist[$user])) {
			if ($this->movelist[$user] > microtime(true)) {
				return;
			}
		}

		$this->movelist[$user] = microtime(true) + 1.5;

		$player = $event->getPlayer();
		$posX = $player->getFloorX();
		$posY = $player->y;
		$posZ = $player->getFloorZ();
		$level = $player->getLevel();

		if (!isset($this->queue['movePos'][$user])) {
			$this->queue['movePos'][$user] = [
				'x' => $posX,
				'z' => $posZ,
				'areaId' => -1,
				'rentId' => -1
			];
		} else {
			if (
				abs(($posX) - ($this->queue ['movePos'][$user]['x'])) > 2 ||
				abs(($posZ) - ($this->queue ['movePos'][$user] ['z'])) > 2
			) {
				$this->queue['movePos'][$user]['x'] = $posX;
				$this->queue['movePos'][$user]['z'] = $posZ;
			} else {
				return;
			}
		}//!isset movePos

		$msg = '';
		$area = $this->areaProvider->getArea($level, $posX, $posZ, $user);
		if ($area instanceof AreaSection) {
			if (!$player->isOp()) {
				if ($area->isAccessDeny() && !$area->isResident($user)) {
					$x = $area->get("startX") - 2;
					$z = $area->get("startZ") - 2;
					$y = $level->getHighestBlockAt($x, $z);
					$player->teleport(new Vector3 ($x, $y, $z));
					$msg .= '§c' . $this->get('this-area-is-only-can-access-resident') . "\n";
				}
			}

			if ($this->queue['movePos'][$user]['areaId'] != $area->getId()) {
				$welcomeMsg = $area->getWelcome();
				if ($area->isHome()) {
					if ($area->isOwner($user)) {
						$msg .= '§b' . $this->get('welcome-area-sir') . "\n";
						if ($welcomeMsg == null) {
							$msg .= '§b' . $this->get('please-set-to-welcome-msg') . "\n";
						}
					} else {
						if ($area->getOwner() != '') {
							$msg .= '§b' . $this->get("here-is") . $area->getOwner() . $this->get("his-land") . "\n";
						}
						if ($welcomeMsg != null) {
							$msg .= '§b' . $welcomeMsg . "\n";
						}
					}
				} else {
					if ($player->isOp()) {
						$msg .= '§b' . $this->get("welcome-op-area-sir") . "\n";
						if ($welcomeMsg == null) {
							$msg .= '§b' . $this->get("please-set-to-welcome-msg") . "\n";
						}
					}
				}
				if ($area->isCanBuy()) {
					$msg .= '§b' . $this->get('you-can-buy-here') . $area->getPrice() . " " . $this->get('show-buy-command') . "\n";
				}
			}

			$this->queue ['movePos'][$user]['areaId'] = $area->getId();
		}

		//Rent Part
		$rent = $this->rentProvider->getRent($level, $posX, $posY, $posZ);
		if ($rent instanceof RentSection) {
			if ($this->queue['movePos'][$user]['rentId'] != $rent->getRentId()) {
				$welcomeMsg = $rent->getWelcome();
				if ($welcomeMsg != null) {
					$msg .= '§b' . $welcomeMsg . "\n";
				}
				if ($rent->isOwner($user)) {
					$msg .= '§b' . $this->get("welcome-rent-area-sir") . "\n";
					if ($welcomeMsg == null) {
						$msg .= '§b' . $this->get("please-set-to-welcome-msg") . "\n";
					}
				}
				$this->queue['movePos'][$user]['rentId'] = $rent->getRentId();
			}
		} //$area instanceof AreaSection
		if (!empty($msg)) {
			$this->tip($player, $msg);
		}

	}


	public function onCommand(CommandSender $player, Command $command, string $label, array $args) : bool {
		if (!$player instanceof Player) {
			switch (strtolower($command)) {
				case $this->get("commands-area") :
					if (!isset ($args [0])) {
						break;
					}
					if (isset ($args [0]) and $args [0] == "?") {
						break;
					}
				case $this->get("commands-rent") :
					if (!isset ($args [0])) {
						break;
					}
					if (isset ($args [0]) and $args [0] == "?") {
						break;
					}
				case $this->get("commands-whiteworld") :
					if (!isset ($args [0])) {
						break;
					}
					if (isset ($args [0]) and $args [0] == "?") {
						break;
					}
				case $this->get("commands-minefarm") :
					if (!isset ($args [0])) {
						break;
					}
					if (isset ($args [0]) and $args [0] == $this->get("commands-minefarm-start")) {
						break;
					}
					if (isset ($args [0]) and $args [0] == "?") {
						break;
					}
					if (isset ($args [0]) and $args [0] == "구매") {
						break;
					}
				default :
					$this->alert($player, $this->get("only-in-game"));
					return true;
			}
		}
		switch (strtolower($command->getName())) {
			case $this->get("commands-whiteworld") :
				if (!isset ($args [0])) {
					$this->message($player, $this->get("commands-whiteworld-help-1"));
					$this->message($player, $this->get("commands-whiteworld-help-2"));
					$this->message($player, $this->get("commands-whiteworld-help-3"));
					$this->message($player, $this->get("commands-whiteworld-help-4"));
					$this->message($player, $this->get("commands-whiteworld-help-5"));
					$this->message($player, $this->get("commands-whiteworld-help-6"));
					return true;
				}
				switch (strtolower($args [0])) {


					case $this->get("commands-whiteworld-info") :
						if (!$player->hasPermission("simplearea.whiteworld.info")) {
							return false;
						}
						$this->whiteworldManager->info($player->getLevel(), $player);
						break;


					case $this->get("commands-whiteworld-protect") :
						if (!$player->hasPermission("simplearea.whiteworld.protect")) {
							return false;
						}
						$this->whiteworldManager->protect($player->getLevel(), $player);
						break;


					case $this->get("commands-whiteworld-areaprice") :
						if (!$player->hasPermission("simplearea.whiteworld.areaprice")) {
							return false;
						}
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-whiteworld-areaprice-help"));
							return true;
						}
						$this->whiteworldManager->areaPrice($player->getLevel(), $args [1], $player);
						break;


					case $this->get("commands-whiteworld-setfence") :
						if (!$player->hasPermission("simplearea.whiteworld.setfence")) {
							return false;
						}
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-whiteworld-setfence-help"));
							return true;
						}
						$this->whiteworldManager->setFence($player->getLevel(), $args [1], $player);
						break;


					case $this->get("commands-whiteworld-setinvensave") :
						if (!$player->hasPermission("simplearea.whiteworld.setinvensave")) {
							return false;
						}
						$this->whiteworldManager->setInvenSave($player->getLevel(), $player);
						break;


					case $this->get("commands-whiteworld-setautocreateallow") :
						if (!$player->hasPermission("simplearea.whiteworld.setautocreateallow")) {
							return false;
						}
						$this->whiteworldManager->setAutoCreateAllow($player->getLevel(), $player);
						break;


					case $this->get("commands-whiteworld-setmanualcreate") :
						if (!$player->hasPermission("simplearea.whiteworld.setmanualcreate")) {
							return false;
						}
						$this->whiteworldManager->setManualCreate($player->getLevel(), $player);
						break;


					case $this->get("commands-whiteworld-areaholdlimit") :
						if (!$player->hasPermission("simplearea.whiteworld.areaholdlimit")) {
							return false;
						}
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-whiteworld-areaholdlimit-help"));
							return true;
						}
						$this->whiteworldManager->areaHoldLimit($player->getLevel(), $args [1], $player);
						break;


					case $this->get("commands-whiteworld-defaultareasize") :
						if (!$player->hasPermission("simplearea.whiteworld.defaultareasize")) {
							return false;
						}
						if (!isset ($args [1]) or !isset ($args [2])) {
							$this->message($player, $this->get("commands-whiteworld-defaultareasize-help"));
							return true;
						}
						$this->whiteworldManager->defaultAreaSize($player->getLevel(), $args [1], $args [2], $player);
						break;
					case $this->get("commands-whiteworld-accessdeny") :
						if (!$player->hasPermission("simplearea.whiteworld.defaultareasize")) {
							return false;
						}
						$this->whiteworldManager->setAccessDeny($player->getLevel(), $player);
						break;


					case $this->get("commands-whiteworld-sizeup") :
						if (!$player->hasPermission("simplearea.whiteworld.defaultareasize")) {
							return false;
						}
						$this->whiteworldManager->setAreaSizeUp($player->getLevel(), $player);
						break;


					case $this->get("commands-whiteworld-sizedown") :
						if (!$player->hasPermission("simplearea.whiteworld.defaultareasize")) {
							return false;
						}
						$this->whiteworldManager->setAreaSizeDown($player->getLevel(), $player);
						break;


					case $this->get("commands-whiteworld-pvpallow") :
						if (!$player->hasPermission("simplearea.whiteworld.pvpallow")) {
							return false;
						}
						$this->whiteworldManager->pvpAllow($player->getLevel(), $player);
						break;


					case $this->get("commands-whiteworld-priceperblock") :
						if (!$player->hasPermission("simplearea.whiteworld.priceperblock")) {
							return false;
						}
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-whiteworld-priceperblock-help"));
							return true;
						}
						$this->whiteworldManager->setPricePerBlock($player->getLevel(), $player, $args [1]);
						break;


					case $this->get("commands-whiteworld-checkshare") :
						if (!$player->hasPermission("simplearea.whiteworld.checkshare")) {
							return false;
						}
						$this->whiteworldManager->setCountShareArea($player->getLevel(), $player);
						break;


					case "?" :
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-whiteworld-help-1"));
							$this->message($player, $this->get("commands-whiteworld-help-2"));
							$this->message($player, $this->get("commands-whiteworld-help-3"));
							$this->message($player, $this->get("commands-whiteworld-help-4"));
							$this->message($player, $this->get("commands-whiteworld-help-5"));
							$this->message($player, $this->get("commands-whiteworld-help-6"));
							return true;
						}
						switch (strtolower($args [1])) {
							case $this->get("commands-whiteworld-info") :
								$this->message($player, $this->get("commands-whiteworld-info-help"));
								break;
							case $this->get("commands-whiteworld-protect") :
								$this->message($player, $this->get("commands-whiteworld-protect-help"));
								break;
							case $this->get("commands-whiteworld-areaprice") :
								$this->message($player, $this->get("commands-whiteworld-areaprice-help"));
								break;
							case $this->get("commands-whiteworld-setfence") :
								$this->message($player, $this->get("commands-whiteworld-setfence-help"));
								break;
							case $this->get("commands-whiteworld-areaholdlimit") :
								$this->message($player, $this->get("commands-whiteworld-areaholdlimit-help"));
								break;
							case $this->get("commands-whiteworld-defaultareasize") :
								$this->message($player, $this->get("commands-whiteworld-defaultareasize-help"));

								break;
							case $this->get("commands-whiteworld-accessdeny") :
								$this->message($player, $this->get("commands-whiteworld-accessdeny-help"));
								break;
							case $this->get("commands-whiteworld-sizeup") :
								$this->message($player, $this->get("commands-whiteworld-sizeup-help"));
								break;
							case $this->get("commands-whiteworld-sizedown") :
								$this->message($player, $this->get("commands-whiteworld-sizedown-help"));
								break;
							case $this->get("commands-whiteworld-pvpallow") :
								$this->message($player, $this->get("commands-whiteworld-pvpallow-help"));
								break;
							case $this->get("commands-whiteworld-priceperblock") :
								$this->message($player, $this->get("commands-whiteworld-priceperblock-help"));
								break;
							case $this->get("commands-whiteworld-checkshare") :
								$this->message($player, $this->get("commands-whiteworld-checkshare-help"));
								break;
							default :
								$this->message($player, $this->get("commands-whiteworld-help-1"));
								$this->message($player, $this->get("commands-whiteworld-help-2"));
								$this->message($player, $this->get("commands-whiteworld-help-3"));
								$this->message($player, $this->get("commands-whiteworld-help-4"));
								$this->message($player, $this->get("commands-whiteworld-help-5"));
								$this->message($player, $this->get("commands-whiteworld-help-6"));
								break;
						}
						break;
					default :
						$this->message($player, $this->get("commands-whiteworld-help-1"));
						$this->message($player, $this->get("commands-whiteworld-help-2"));
						$this->message($player, $this->get("commands-whiteworld-help-3"));
						$this->message($player, $this->get("commands-whiteworld-help-4"));
						$this->message($player, $this->get("commands-whiteworld-help-5"));
						$this->message($player, $this->get("commands-whiteworld-help-6"));
						break;
				}
				break;
			case $this->get("commands-areatax") :
				if (!isset ($args [0]) or !is_numeric($args [0])) {
					$this->message($player, $this->get("commands-areatax-help-1"));
					$this->message($player, $this->get("commands-areatax-help-2"));
					return true;
				}
				$this->whiteWorldProvider->get($player->getLevel())->setAreaTax($args [0]);
				$this->message($player, $this->get("areatax-changed") . $args [0]);
				break;
			case $this->get("commands-minefarm") :
				if (!isset ($args [0])) {
					$this->message($player, $this->get("commands-minefarm-help-1"));
					$this->message($player, $this->get("commands-minefarm-help-2"));

					if ($player->isOp()) {
						$this->message($player, $this->get("commands-minefarm-help-3"));
					}
					$this->message($player, $this->get("commands-minefarm-help-4"));
					$this->message($player, $this->get("commands-minefarm-help-5"));
					return true;
				}
				switch (strtolower($args [0])) {
					case $this->get("commands-minefarm-buy") :
						if (!$player->hasPermission("simplearea.minefarm.buy")) {
							return false;
						}
						$this->mineFarmManager->buy($player);
						break;
					case $this->get("commands-minefarm-delete") :
						if (!$player->hasPermission("simplearea.minefarm.delete")) {
							return false;
						}
						$this->mineFarmManager->delete($player);
						break;
					case $this->get("commands-minefarm-move") :
						if (!$player->hasPermission("simplearea.minefarm.move")) {
							return false;
						}
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-minefarm-move-help"));
							$this->mineFarmManager->move($player, 0);
							return true;
						}
						$this->mineFarmManager->move($player, $args [1]);
						break;
					case $this->get("commands-minefarm-list") :
						if (!$player->hasPermission("simplearea.minefarm.list")) {
							return false;
						}
						$this->mineFarmManager->getList($player);
						break;
					case $this->get("commands-minefarm-start") :
						if (!$player->hasPermission("simplearea.minefarm.start")) {
							return false;
						}
						$this->mineFarmManager->start($player);
						break;
					case $this->get("commands-minefarm-setprice") :
						if (!$player->hasPermission("simplearea.minefarm.setprice")) {
							return false;
						}
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-minefarm-setprice-help"));
							return true;
						}
						$this->mineFarmManager->setPrice($player, $args [1]);
						break;
					case $this->get("commands-minefarm-farmholdlimit") :
						if (!$player->hasPermission("simplearea.minefarm.farmholdlimit")) {
							return false;
						}
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-minefarm-farmholdlimit-help"));
							return true;
						}
						$this->mineFarmManager->farmHoldLimit($player, $args [1]);
						break;
					case "?" :
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-minefarm-help-3"));
							return true;
						}
						switch (strtolower($args [1])) {
							case $this->get("commands-minefarm-buy") :
								$this->message($player, $this->get("commands-minefarm-buy-help"));
								break;
							case $this->get("commands-minefarm-delete") :
								$this->message($player, $this->get("commands-minefarm-delete-help"));
								break;
							case $this->get("commands-minefarm-move") :
								$this->message($player, $this->get("commands-minefarm-move-help"));
								break;
							case $this->get("commands-minefarm-list") :
								$this->message($player, $this->get("commands-minefarm-list-help"));
								break;
							case $this->get("commands-minefarm-start") :
								$this->message($player, $this->get("commands-minefarm-start-help"));
								break;
							case $this->get("commands-minefarm-setprice") :
								$this->message($player, $this->get("commands-minefarm-setprice-help"));
								break;
							case $this->get("commands-minefarm-farmholdlimit") :
								$this->message($player, $this->get("commands-minefarm-farmholdlimit-help"));
								break;
							default :
								$this->message($player, $this->get("commands-minefarm-help-3"));
								break;
						}
						break;
					default :
						$this->message($player, $this->get("commands-minefarm-help-1"));
						if ($player->isOp()) {
							$this->message($player, $this->get("commands-minefarm-help-2"));
						}
						$this->message($player, $this->get("commands-minefarm-help-3"));
						$this->message($player, $this->get("commands-minefarm-help-4"));
						$this->message($player, $this->get("commands-minefarm-help-5"));
						break;
				}
				break;
			case $this->get("commands-rent") :
				if (!isset ($args [0])) {
					$this->message($player, $this->get("commands-rent-help-1"));
					$this->message($player, $this->get("commands-rent-help-2"));
					$this->message($player, $this->get("commands-rent-help-3"));
					$this->message($player, $this->get("commands-rent-help-4"));
					$this->message($player, $this->get("commands-rent-help-5"));
					return true;
				}
				switch ($args [0]) {
					case $this->get("commands-rent-move") :
						if (!$player->hasPermission("simplearea.rent.move")) {
							return false;
						}
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-rent-move-help"));
							return true;
						}
						$this->rentManager->move($player, $args [1]);
						break;
					case $this->get("commands-rent-list") :
						if (!$player->hasPermission("simplearea.rent.list")) {
							return false;
						}
						$this->rentManager->getList($player);
						break;
					case $this->get("commands-rent-create") :
						if (!$player->hasPermission("simplearea.rent.create")) {
							return false;
						}
						if (isset ($this->queue ["rentCreate"] [strtolower($player->getName())])) {
							if ($this->queue ["rentCreate"] [strtolower($player->getName())] ["startX"] === null) {
								$this->message($player, $this->get("please-choose-pos1"));
								return true;
							}
							if ($this->queue ["rentCreate"] [strtolower($player->getName())] ["endX"] === null) {
								$this->message($player, $this->get("please-choose-pos2"));
								return true;
							}
							$startX = $this->queue ["rentCreate"] [strtolower($player->getName())] ["startX"];
							$startY = $this->queue ["rentCreate"] [strtolower($player->getName())] ["startY"];
							$startZ = $this->queue ["rentCreate"] [strtolower($player->getName())] ["startZ"];
							$endX = $this->queue ["rentCreate"] [strtolower($player->getName())] ["endX"];
							$endY = $this->queue ["rentCreate"] [strtolower($player->getName())] ["endY"];
							$endZ = $this->queue ["rentCreate"] [strtolower($player->getName())] ["endZ"];
							$areaId = $this->queue ["rentCreate"] [strtolower($player->getName())] ["areaId"];
							$rentPrice = $this->queue ["rentCreate"] [strtolower($player->getName())] ["rentPrice"];
							$startLevel = $this->queue ["rentCreate"] [strtolower($player->getName())] ["startLevel"];
							$this->rentManager->create($player, $startLevel, $startX, $endX, $startY, $endY, $startZ, $endZ, $areaId, $rentPrice);
							if (isset ($this->queue ["rentCreate"] [strtolower($player->getName())])) {
								unset ($this->queue ["rentCreate"] [strtolower($player->getName())]);
							}
							return true;
						}
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-rent-create-help"));
							return true;
						}
						$area = $this->areaProvider->getArea($player->getLevel(), $player->x, $player->z);
						if (!$area instanceof AreaSection) {
							$this->alert($player, $this->get("cant-find-area-rent-failed"));
							return true;
						}
						if (!$player->isOp()) {
							if ($area->getOwner() != strtolower($player->getName())) {
								$this->alert($player, $this->get("youre-not-owner-rent-failed"));
								return true;
							}
						}
						$this->queue ["rentCreate"] [strtolower($player->getName())] = [
							"startX" => null,
							"startY" => null,
							"startZ" => null,
							"endX" => null,
							"endY" => null,
							"endZ" => null,
							"areaId" => $area->getId(),
							"rentPrice" => $args [1],
							"startLevel" => $player->getLevel()->getFolderName()
						];
						$this->message($player, $this->get("start-create-rent-area"));
						$this->message($player, $this->get("please-choose-two-pos"));
						$this->message($player, $this->get("you-can-stop-create-manual-area"));
						break;
					case $this->get("commands-rent-out") :
						if (!$player->hasPermission("simplearea.rent.out")) {
							return false;
						}
						$this->rentManager->out($player);
						break;
					case $this->get("commands-rent-salelist") :
						if (!$player->hasPermission("simplearea.rent.salelist")) {
							return false;
						}
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-rent-salelist-help"));
							$this->rentManager->saleList($player, 0);
							return true;
						}
						$this->rentManager->saleList($player, $args [1]);
						break;
					case $this->get("commands-rent-welcome") :
						if (!$player->hasPermission("simplearea.rent.welcome")) {
							return false;
						}
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-rent-welcome-help"));
							return true;
						}
						array_shift($args);
						$string = implode(' ', $args);
						$this->rentManager->setWelcome($player, $string);
						break;
					case "?" :
						if (!isset ($args [1])) {
							$this->message($player, $this->get("commands-rent-help-1"));
							$this->message($player, $this->get("commands-rent-help-2"));
							$this->message($player, $this->get("commands-rent-help-3"));
							$this->message($player, $this->get("commands-rent-help-4"));
							$this->message($player, $this->get("commands-rent-help-5"));
							return true;
						}
						switch ($args [1]) {
							case $this->get("commands-rent-move") :
								$this->message($player, $this->get("commands-rent-move-help"));
								break;
							case $this->get("commands-rent-list") :
								$this->message($player, $this->get("commands-rent-list-help"));
								break;
							case $this->get("commands-rent-create") :
								$this->message($player, $this->get("commands-rent-create-help"));
								break;
							case $this->get("commands-rent-out") :
								$this->message($player, $this->get("commands-rent-out-help"));
								break;
							case $this->get("commands-rent-salelist") :
								$this->message($player, $this->get("commands-rent-salelist-help"));
								break;
							case $this->get("commands-rent-welcome") :
								$this->message($player, $this->get("commands-rent-welcome-help"));
								break;
							default :
								$this->message($player, $this->get("commands-rent-help-1"));
								$this->message($player, $this->get("commands-rent-help-2"));
								$this->message($player, $this->get("commands-rent-help-3"));
								$this->message($player, $this->get("commands-rent-help-4"));
								$this->message($player, $this->get("commands-rent-help-5"));
								break;
						}
						break;
					default :
						$this->message($player, $this->get("commands-rent-help-1"));
						$this->message($player, $this->get("commands-rent-help-2"));
						$this->message($player, $this->get("commands-rent-help-3"));
						$this->message($player, $this->get("commands-rent-help-4"));
						$this->message($player, $this->get("commands-rent-help-5"));
						break;
				}
				break;
		}
		return true;
	}

	public function onBlockPlaceEvent(BlockPlaceEvent $event) : void {
		$this->onBlockChangeEvent($event);
	}

	public function onBlockBreakEvent(BlockBreakEvent $event) : void {
		$area = $this->areaProvider->getArea($event->getBlock()->level, $event->getBlock()->x, $event->getBlock()->z, strtolower($event->getPlayer()->getName()));
		if ($area instanceof AreaSection) {
			$rent = $this->rentProvider->getRent($event->getBlock()->level, $event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z);
			if (($rent instanceof RentSection) and !$rent->isBuySingNull()) {

				$buySignPos = $rent->getBuySignPos();
				$buySignPosString = "{$buySignPos->x}:{$buySignPos->y}:{$buySignPos->z}";

				$blockPos = $event->getBlock();
				$blockPosString = "{$blockPos->x}:{$blockPos->y}:{$blockPos->z}";

				if ($buySignPosString != $blockPosString) {
					return;
				}

				if ($area->isOwner($event->getPlayer()->getName())) {
					$rent->deleteRent();
					$this->message($event->getPlayer(), $this->get("rent-delete-complete"));
					return;
				}

				$event->setCancelled();

				if (!$rent->isOwner($event->getPlayer()->getName())) {
					$this->message($event->getPlayer(), $this->get("rent-buy-sign-delete-must-be-owner"));
					return;
				}

				if (!isset ($this->queue ["rentout"] [strtolower($event->getPlayer()->getName())])) {
					$this->message($event->getPlayer(), $this->get("do-you-want-rent-out"));
					$this->message($event->getPlayer(), $this->get("if-you-want-do-break-again"));
					$this->queue ["rentout"] [strtolower($event->getPlayer()->getName())] = [
						"time" => $this->makeTimestamp()
					];
					return;
				}
				$before = $this->queue ["rentout"] [strtolower($event->getPlayer()->getName())] ["time"];
				$after = $this->makeTimestamp();
				$timeout = intval($after - $before);

				if ($timeout <= 10) {
					$rent->out();
					$this->message($event->getPlayer(), $this->get("rent-out-complete"));
				} else {
					$this->message($event->getPlayer(), $this->get("rent-breaking-time-over"));
				}
				if (isset ($this->queue ["rentout"] [strtolower($event->getPlayer()->getName())])) {
					unset ($this->queue ["rentout"] [strtolower($event->getPlayer()->getName())]);
				}
				return;
			}
		}
		switch (true) {
			case ($event->getBlock()->getID() == Block::SIGN_POST) :
				$sign = $event->getPlayer()->getLevel()->getTile($event->getBlock());
				break;
			case ($event->getBlock()->getSide(Vector3::SIDE_UP)->getId() == Block::SIGN_POST) :
				$sign = $event->getPlayer()->getLevel()->getTile($event->getBlock()->getSide(Vector3::SIDE_UP));
				break;
		}
		if (isset ($sign)) {
			if (!$sign instanceof Sign) {
				return;
			}
			$lines = $sign->getText();

			if ($lines [0] == $this->get("automatic-post-1") && $lines [3] == $this->get("automatic-post-4")) {
				if ($lines [2] == $this->get("automatic-post-3")) {

					if (!$event->getPlayer()->isOp()) {
						$event->setCancelled();
						$this->alert($event->getPlayer(), $this->get("automatic-post-is-only-delete-op"));
						return;
					}

					if (!isset ($this->queue ["autoPostDelete"] [strtolower($event->getPlayer()->getName())])) {
						$event->setCancelled();
						$this->queue ["autoPostDelete"] [strtolower($event->getPlayer()->getName())] = $this->makeTimestamp();
						$this->message($event->getPlayer(), $this->get("do-you-want-delete-automatic-post"));
						$this->message($event->getPlayer(), $this->get("if-you-want-to-delete-automatic-post-do-again"));
						return;
					}
					$before = $this->queue ["autoPostDelete"] [strtolower($event->getPlayer()->getName())];
					$after = $this->makeTimestamp();
					$timeout = intval($after - $before);

					if ($timeout <= 10) {
						$size = explode("x", strtolower($lines [1]));
						$dmg = $event->getBlock()->getDamage();
						if ($dmg != 0 and $dmg != 4 and $dmg != 8 and $dmg != 12) {
							if ($dmg < 4) {
								$event->getBlock()->getLevel()->setBlock($event->getBlock(), Block::get(Block::SIGN_POST, 0));
							} else {
								if ($dmg < 8) {
									$event->getBlock()->getLevel()->setBlock($event->getBlock(), Block::get(Block::SIGN_POST, 4));
								} else {
									if ($dmg < 12) {
										$event->getBlock()->getLevel()->setBlock($event->getBlock(), Block::get(Block::SIGN_POST, 8));
									} else {
										$event->getBlock()->getLevel()->setBlock($event->getBlock(), Block::get(Block::SIGN_POST, 12));
									}
								}
							}
						}
						$this->areaManager->destructPrivateArea($event->getPlayer(), $event->getBlock(), $size [0], $size [1], $event->getBlock()->y - 1, $dmg);
						$this->message($event->getPlayer(), $this->get("automatic-post-deleted"));
					} else {
						$event->setCancelled();
						$this->message($event->getPlayer(), $this->get("automatic-post-time-over"));
					}
					if (isset ($this->queue ["autoPostDelete"] [strtolower($event->getPlayer()->getName())])) {
						unset ($this->queue ["autoPostDelete"] [strtolower($event->getPlayer()->getName())]);
					}
					return;
				}
			}
		}
		$this->onBlockChangeEvent($event);
	}

	public function onSignChangeEvent(SignChangeEvent $event) : void {
		$area = $this->areaProvider->getArea($event->getBlock()->level, $event->getBlock()->x, $event->getBlock()->z, strtolower($event->getPlayer()->getName()));
		if ($area instanceof AreaSection) {
			$rent = $this->rentProvider->getRent($event->getBlock()->level, $event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z);
			if (($rent instanceof RentSection) and $rent->isBuySingNull()) {
				if (!$area->isOwner($event->getPlayer()->getName())) {
					$this->alert($event->getPlayer(), $this->get("youre-not-owner"));
					return;
				}
				$event->setLine(0, TextFormat::LIGHT_PURPLE . $this->get("rent-buy-sign-1"));
				$event->setLine(1, TextFormat::LIGHT_PURPLE . $this->get("rent-buy-sign-2"));
				$event->setLine(2, TextFormat::LIGHT_PURPLE . $this->get("rent-buy-sign-3"));
				$event->setLine(3, TextFormat::LIGHT_PURPLE . $this->get("rent-buy-sign-4"));

				$rent->setBuySignPos($event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z);
				$this->message($event->getPlayer(), $this->get("rent-buy-sign-set-complete"));
				return;
			}
		}
		if ($event->getLine(0) == $this->get("automatic-post-1")) {
			if ($event->getLine(2) == $this->get("automatic-post-3")) {
				if ($event->getLine(3) == $this->get("automatic-post-4")) {
					if (!$event->getPlayer()->isOp()) {
						$this->alert($event->getPlayer(), $this->get("automatic-post-is-only-create-op"));
						$event->setCancelled();
						return;
					}
				}
			}
		}
		if ($event->getLine(0) == $this->get("easy-automatic-post")) {
			if (!$event->getPlayer()->isOp()) {
				$this->alert($event->getPlayer(), $this->get("automatic-post-is-only-create-op"));
				$event->setCancelled();
				return;
			}
			if ($event->getBlock()->getId() != Block::SIGN_POST) {
				$this->alert($event->getPlayer(), $this->get("wall-sign-doesnt-use-it"));
				$event->setCancelled();
				return;
			}
			$size = strtolower($event->getLine(1));
			$size = explode("x", $size);
			if (!isset ($size [1]) or !is_numeric($size [0]) or !is_numeric($size [1])) {
				$this->alert($event->getPlayer(), $this->get("automatic-post-fail-size-problem"));
				$event->setCancelled();
				return;
			}
			$dmg = $event->getBlock()->getDamage();
			if ($dmg != 0 and $dmg != 4 and $dmg != 8 and $dmg != 12) {
				if ($dmg < 4) {
					$event->getBlock()->getLevel()->setBlock($event->getBlock(), Block::get(Block::SIGN_POST, 0));
				} else {
					if ($dmg < 8) {
						$event->getBlock()->getLevel()->setBlock($event->getBlock(), Block::get(Block::SIGN_POST, 4));
					} else {
						if ($dmg < 12) {
							$event->getBlock()->getLevel()->setBlock($event->getBlock(), Block::get(Block::SIGN_POST, 8));
						} else {
							$event->getBlock()->getLevel()->setBlock($event->getBlock(), Block::get(Block::SIGN_POST, 12));
						}
					}
				}
			}
			$bool = $this->areaManager->initialPrivateArea($event->getPlayer(), $event->getBlock(), $size [0], $size [1], $event->getBlock()->y - 1, $dmg);
			if (!$bool) {
				$event->setCancelled();
				return;
			}
			$event->setLine(0, $this->get("automatic-post-1"));
			$event->setLine(1, $size [0] . $this->get("automatic-post-2") . $size [1]);
			$event->setLine(2, $this->get("automatic-post-3"));
			$event->setLine(3, $this->get("automatic-post-4"));
			return;
		}
		$this->onBlockChangeEvent($event);
	}


	public function onBlockChangeEvent(EntityBlockChangeEvent $event) : void {
		/** @var $player Player */
		$player = $event->getEntity();
		if (!$player instanceof Player) {
			return;
		} elseif ($player->hasPermission('simplearea.modallarea')) {
			return;
		}
		$area = $this->areaProvider->getArea($event->getBlock()->getLevel(), $event->getBlock()->x, $event->getBlock()->z, strtolower($player->getName()));
		if ($area instanceof AreaSection) {
			if ($area->isHome()) {
				if ($area->isResident($player->getName())) {
					return;
				}
			}
			if ($area->isOwner($player->getName())) {
				return;
			}
			$rent = $this->rentProvider->getRent($event->getBlock()->getLevel(), $event->getBlock()->x, $event->getBlock()->y, $event->getBlock()->z);
			if ($rent instanceof RentSection) {
				if ($rent->isOwner($player->getName())) {
					return;
				}
			}
			if ($area->isProtected()) {
				switch (true) {
					case $event instanceof BlockPlaceEvent :
						$type = AreaModifyEvent::PLACE_PROTECT_AREA;
						break;
					case $event instanceof BlockBreakEvent :
						$type = AreaModifyEvent::BREAK_PROTECT_AREA;
						break;
					case $event instanceof SignChangeEvent :
						$type = AreaModifyEvent::SIGN_CHANGE_PROTECT_AREA;
						break;
				}
				if (isset ($type)) {
					$ev = new AreaModifyEvent ($player, $event->getBlock()->getLevel()->getFolderName(), $area->getId(), $event->getBlock(), $type);
					$this->plugin->getServer()->getPluginManager()->callEvent($ev);
					if ($ev->isCancelled()) {
						return;
					}
				}
				$event->setCancelled();
				return;
			}
			return;
		}

		$whiteWorld = $this->whiteWorldProvider->get($event->getBlock()->getLevel());
		if (!$whiteWorld instanceof WhiteWorldData) {
			return;
		}

		if ($whiteWorld->isProtected()) {
			switch (true) {
				case $event instanceof BlockPlaceEvent :
					$type = AreaModifyEvent::PLACE_WHITE;
					break;
				case $event instanceof BlockBreakEvent :
					$type = AreaModifyEvent::BREAK_WHITE;
					break;
				case $event instanceof SignChangeEvent :
					$type = AreaModifyEvent::SIGN_CHANGE_WHITE;
					break;
			}
			if (isset ($type)) {
				$ev = new AreaModifyEvent ($player, $event->getBlock()->getLevel()->getFolderName(), null, $event->getBlock(), $type);
				$this->plugin->getServer()->getPluginManager()->callEvent($ev);
				if ($ev->isCancelled()) {
					return;
				}
			}
			$event->setCancelled();
			return;
		}
	}


	public function onPlayerInteractEvent(PlayerInteractEvent $event) : void {
		if ($event->getAction() == PlayerInteractEvent::RIGHT_CLICK_BLOCK) {
			$player = $event->getPlayer();
			$lowerpname = strtolower($event->getPlayer()->getName());
			if (isset($this->queue['manual'][$lowerpname])) {
				$event->setCancelled();
				$startLevel = $this->queue['manual'][$lowerpname]['startLevel'];
				if ($startLevel !== $player->getLevel()->getFolderName()) {
					$this->alert($player, $this->get("manual-area-cant-cross-two-world"));
					$this->message($player, $this->get("you-can-stop-create-manual-area"));
					return;
				}
				$startX = $this->queue ['manual'][$lowerpname]['startX'];
				if ($startX === null) {
					$this->queue['manual'][$lowerpname]['startX'] = $event->getBlock()->getX();
					$this->queue ["manual"] [strtolower($event->getPlayer()->getName())] ["startZ"] = $event->getBlock()->getZ();
					$this->message($player, $this->get("first-pos-choosed"));
					return;
				}
				$endX = $this->queue['manual'][$lowerpname]['endX'];
				if ($endX === null) {
					$this->queue['manual'][$lowerpname]['endX'] = $event->getBlock()->getX();
					$this->queue['manual'][$lowerpname]['endZ'] = $event->getBlock()->getZ();

					$pricePerBlock = $this->whiteWorldProvider->get($player->getLevel())->getPricePerBlock();

					$startX = $this->queue['manual'][$lowerpname]['startX'];
					$endX = $this->queue['manual'][$lowerpname]['endX'];
					$startZ = $this->queue['manual'][$lowerpname]['startZ'];
					$endZ = $this->queue['manual'][$lowerpname]['endZ'];

					$xSize = $endX - $startX;
					$zSize = $endZ - $startZ;

					$areaPrice = (abs($xSize * $zSize)) * $pricePerBlock;
					$this->message($player, $this->get('second-pos-choosed'));

					($player->isOp()) ? $isHome = false : $isHome = true;
					$this->areaManager->manualCreate($player, $startX, $endX, $startZ, $endZ, $isHome);
					if (isset ($this->queue['manual'][$lowerpname])) {
						unset ($this->queue['manual'][$lowerpname]);
					}
				}
			} else {
				if (isset($this->queue['rentCreate'][$lowerpname])) {
					$event->setCancelled();
					$startLevel = $this->queue['rentCreate'][$lowerpname]['startLevel'];
					if ($startLevel !== $player->getLevel()->getFolderName()) {
						$this->alert($player, $this->get('rent-area-cant-cross-two-world'));
						return;
					}

					$touched = $event->getBlock();
					$area = $this->areaProvider->getArea($player->getLevel()->getName(), $touched->x, $touched->z, strtolower($event->getPlayer()->getName()));
					if (!$area instanceof AreaSection) {
						$this->message($player, $this->get("this-position-not-exist-area"));
						return;
					}

					$areaId = $this->queue['rentCreate'][$lowerpname]['areaId'];
					if ($areaId != $area->getId()) {
						$this->message($player, $this->get('this-position-some-other-area-exist'));
						return;
					}

					$startX = $this->queue['rentCreate'][$lowerpname]['startX'];
					if ($startX === null) {
						$this->queue['rentCreate'][$lowerpname] ['startX'] = $event->getBlock()->getX();
						$this->queue['rentCreate'][$lowerpname]['startY'] = $event->getBlock()->getY();
						$this->queue['rentCreate'][$lowerpname]['startZ'] = $event->getBlock()->getZ();
						$this->message($player, $this->get('first-pos-choosed'));
						$this->message($player, $this->get('you-can-stop-create-manual-area'));
						return;
					}

					$endX = $this->queue['rentCreate'][$lowerpname]['endX'];
					if ($endX === null) {
						$this->queue['rentCreate'][$lowerpname]["endX"] = $event->getBlock()->getX();
						$this->queue['rentCreate'][$lowerpname] ['endY'] = $event->getBlock()->getY();
						$this->queue['rentCreate'][$lowerpname] ['endZ'] = $event->getBlock()->getZ();

						$rentPrice = $this->queue['rentCreate'] [$lowerpname]['rentPrice'];

						$this->message($player, $this->get('second-pos-choosed'));
						$this->message($player, $this->get('do-you-want-create-area') . $rentPrice);
						$this->message($player, $this->get('if-you-want-to-create-try-again'));
						$this->message($player, $this->get('you-can-stop-create-manual-area'));
						return;
					}
				} else {
					if (isset($this->queue['areaSizeUp'][$lowerpname])) {
						$event->setCancelled();
						$sizeUpData = $this->queue['areaSizeUp'][$lowerpname];
						if (!$sizeUpData['isTouched']) {
							$area = $this->areaProvider->getAreaToId($sizeUpData ['startLevel'], $sizeUpData ['id']);

							$startX = $area->get("startX");
							$endX = $area->get("endX");
							$startZ = $area->get("startZ");
							$endZ = $area->get("endZ");

							$touchX = $event->getBlock()->x;
							$touchZ = $event->getBlock()->z;

							$rstartX = 0;
							$rendX = 0;
							$rstartZ = 0;
							$rendZ = 0;

							if ($startX > $touchX) {
								$rstartX = $startX - $touchX;
							} else {
								if ($endX < $touchX) {
									$rendX = $touchX - $endX;
								}
							}
							if ($startZ > $touchZ) {
								$rstartZ = $startZ - $touchZ;
							} else {
								if ($endZ < $touchZ) {
									$rendZ = $touchZ - $endZ;
								}
							}

							if ($rstartX == 0 and $rendX == 0 and $rstartZ == 0 and $rendZ == 0) {
								$this->alert($player, $this->get('you-need-touch-out-side'));
								$this->alert($player, $this->get('you-can-stop-create-manual-area'));
								return;
							}

							$this->queue ['areaSizeUp'][$lowerpname]['startX'] = $rstartX;
							$this->queue ['areaSizeUp'][$lowerpname]['endX'] = $rendX;
							$this->queue ['areaSizeUp'][$lowerpname]['startZ'] = $rstartZ;
							$this->queue ['areaSizeUp'][$lowerpname]['endZ'] = $rendZ;
							$this->queue ['areaSizeUp'][$lowerpname]['isTouched'] = true;

							$resizePrice = 0;
							$xSize = $endX - $startX;
							$zSize = $endZ - $startZ;
							$whiteWorld = $this->whiteWorldProvider->get($sizeUpData ['startLevel']);

							if (!$player->isOp()) {
								if ($rstartX != 0) {
									$resizePrice += (abs($rstartX * $zSize) * $whiteWorld->getPricePerBlock());
								}
								if ($rendX != 0) {
									$resizePrice += (abs($rendX * $zSize) * $whiteWorld->getPricePerBlock());
								}
								if ($rstartZ != 0) {
									$resizePrice += (abs($rstartZ * $xSize) * $whiteWorld->getPricePerBlock());
								}
								if ($rendZ != 0) {
									$resizePrice += (abs($rendZ * $xSize) * $whiteWorld->getPricePerBlock());
								}
							}
							$this->queue ['areaSizeUp'][$lowerpname]['resizePrice'] = $resizePrice;

							$this->message($player, $this->get('do-you-want-size-up') . $resizePrice);
							$this->message($player, $this->get('if-you-want-to-size-up-please-command'));
							$this->message($player, $this->get('you-can-stop-create-manual-area'));
							return;
						}
					} else {
						if (isset ($this->queue['areaSizeDown'] [$lowerpname])) {
							$event->setCancelled();
							$sizeDownData = $this->queue ['areaSizeDown'] [$lowerpname];
							if (!$sizeDownData ['isTouched']) {
								$area = $this->areaProvider->getAreaToId($sizeDownData ['startLevel'], $sizeDownData ["id"]);

								$startX = $area->get("startX");
								$endX = $area->get("endX");
								$startZ = $area->get("startZ");
								$endZ = $area->get("endZ");

								$touchX = $event->getBlock()->x;
								$touchZ = $event->getBlock()->z;

								$rstartX = 0;
								$rendX = 0;
								$rstartZ = 0;
								$rendZ = 0;

								if ($startX < $touchX) {
									$rstartX = $startX - $touchX;
								}
								if ($endX > $touchX) {
									$rendX = $touchX - $endX;
								}
								if ($startZ < $touchZ) {
									$rstartZ = $startZ - $touchZ;
								}
								if ($endZ > $touchZ) {
									$rendZ = $touchZ - $endZ;
								}

								if ($rstartX >= 0 or $rendX >= 0 or $rstartZ >= 0 or $rendZ >= 0) {
									$this->alert($player, $this->get('you-need-touch-in-side'));
									$this->alert($player, $this->get('you-can-stop-create-manual-area'));
									return;
								}

								if ($rstartX > $rendX) {
									if ($rstartZ > $rendZ) {
										if ($rstartX > $rstartZ) {
											$this->queue ['areaSizeDown'] [$lowerpname] ['startX'] = $rstartX;
										} else {
											$this->queue ['areaSizeDown'] [$lowerpname] ['startZ'] = $rstartZ;
										}
									} else { // $rstartZ < $rendZ
										if ($rstartX > $rendZ) {
											$this->queue ['areaSizeDown'] [$lowerpname] ['startX'] = $rstartX;
										} else {
											$this->queue ['areaSizeDown'] [$lowerpname] ['endZ'] = $rendZ;
										}
									}
								} else { // $rstartX < $rendX
									if ($rstartZ > $rendZ) {
										if ($rstartX > $rstartZ) {
											$this->queue ['areaSizeDown'] [$lowerpname] ['endX'] = $rendX;
										} else {
											$this->queue ['areaSizeDown'] [$lowerpname] ['startZ'] = $rstartZ;
										}
									} else { // $rstartZ < $rendZ
										if ($rstartX > $rendZ) {
											$this->queue ['areaSizeDown'] [$lowerpname] ['endX'] = $rendX;
										} else {
											$this->queue ['areaSizeDown'] [$lowerpname] ['endZ'] = $rendZ;
										}
									}
								}
								$this->queue ['areaSizeDown'] [$lowerpname] ['isTouched'] = true;

								$this->message($player, $this->get('do-you-want-size-down'));
								$this->message($player, $this->get('if-you-want-to-size-down-please-command'));
								$this->message($player, $this->get('you-can-stop-create-manual-area'));
								return;
							}
						}
					}
				}
			}
			$block = $event->getBlock();
			$level = $block->getLevel();
			$posX = $block->x;
			$posY = $block->y;
			$posZ = $block->z;
			$rent = $this->rentProvider->getRent($level, $posX, $posY, $posZ);
			$area = $this->areaProvider->getArea($level, $posX, $posZ, strtolower($event->getPlayer()->getName()));
			if ($rent instanceof RentSection && $area instanceof AreaSection) {
				$buySignPos = $rent->getBuySignPos();
				$buySignPosString = "{$buySignPos->x}:{$buySignPos->y}:{$buySignPos->z}";

				$blockPos = $event->getBlock();
				$blockPosString = "{$blockPos->x}:{$blockPos->y}:{$blockPos->z}";

				if ($buySignPosString == $blockPosString) {
					$event->setCancelled();
					if ($area->getOwner() == $lowerpname) {
						$this->message($player, $this->get("cant-owner-self-buying-rent-area"));
						return;
					}
					if (!$rent->isCanBuy()) {
						if ($rent->getOwner() == $lowerpname) {
							$this->message($player, $this->get("this-rent-area-already-sold-you"));
						} else {
							$this->message($player, $this->get("this-rent-area-already-sold"));
						}
						$this->message($player, $this->get('this-rent-is-already-owner-exist') . $rent->getOwner());
						return;
					}
					if (!isset ($this->queue ['rentBuy'] [$lowerpname])) {
						$this->queue ['rentBuy'] [$lowerpname] = [
							"time" => $this->makeTimestamp()
						];
						$this->message($player, $this->get('if-you-want-to-buying-this-rent-touch-again'));
						$this->message($player, $this->get('rent-hour-per-price') . $rent->getPrice());
					} else {
						$before = $this->queue ['rentBuy'] [$lowerpname] ['time'];
						$after = $this->makeTimestamp();
						$timeout = intval($after - $before);

						if ($timeout > 6) {
							if (isset ($this->queue ['rentBuy'] [$lowerpname])) {
								unset ($this->queue ['rentBuy'] [$lowerpname]);
							}
							$this->message($player, $this->get('rent-buying-time-over'));
							return;
						}

						$rent->buy($player);
					}
				}
			}
			if ($block->getID() == Block::SIGN_POST) {
				$sign = $event->getPlayer()->getLevel()->getTile($event->getBlock());
				if (!$sign instanceof Sign) {
					return;
				}

				$lines = $sign->getText();
				if ($lines [0] != $this->get("automatic-post-1") or $lines [3] != $this->get("automatic-post-4")) {
					return;
				}
				if ($lines [2] != $this->get("automatic-post-3")) {
					return;
				}

				$event->setCancelled();

				$size = explode($this->get("automatic-post-2"), $lines [1]);
				if (!isset ($size [1]) or !is_numeric($size [0]) or !is_numeric($size [1])) {
					$this->alert($event->getPlayer(), $this->get("automatic-post-fail-size-problem"));
					return;
				}

				$xSize = ($size [0] - 1);
				$zSize = ($size [1] - 1);

				switch ($sign->getBlock()->getDamage()) {
					case 0 : // 63:0 x+ z- XxZ
						$startX = ($event->getBlock()->x + 1);
						$startZ = ($event->getBlock()->z - 1);
						$endX = ($event->getBlock()->x + $xSize - 1);
						$endZ = ($event->getBlock()->z - $zSize + 1);
						break;
					case 4 : // 63:4 x+ z+ ZxX
						$startX = ($event->getBlock()->x + 1);
						$startZ = ($event->getBlock()->z + 1);
						$endX = ($event->getBlock()->x + $zSize - 1);
						$endZ = ($event->getBlock()->z + $xSize - 1);
						break;
					case 8 : // 63:8 x- z+ XxZ
						$startX = ($event->getBlock()->x - 1);
						$startZ = ($event->getBlock()->z + 1);
						$endX = ($event->getBlock()->x - $xSize + 1);
						$endZ = ($event->getBlock()->z + $zSize - 1);
						break;
					case 12 : // 63:12 x- z- ZxX
						$startX = ($event->getBlock()->x - 1);
						$startZ = ($event->getBlock()->z - 1);
						$endX = ($event->getBlock()->x - $zSize + 1);
						$endZ = ($event->getBlock()->z - $xSize + 1);
						break;
					default :
						$this->alert($event->getPlayer(), $this->get("automatic-post-fail-sign-problem"));
						return;
				}
				$level = $event->getBlock()->getLevel();

				$owner = "";
				$isHome = true;

				$area = $this->areaProvider->addArea($level, $startX, $endX, $startZ, $endZ, $owner, $isHome, false);

				if ($area instanceof AreaSection) {
					$this->message($event->getPlayer(), $area->getId() . $this->get("automatic-post-complete"));
					$this->message($event->getPlayer(), $this->get("you-can-use-area-buy-command"));
				} else {
					$overlap = $this->areaProvider->checkOverlap($level, $startX, $endX, $startZ, $endZ);
					if ($overlap instanceof AreaSection) {
						$this->message($event->getPlayer(), $overlap->getId() . $this->get("automatic-post-fail-overlap-problem"));
					} else {
						$this->message($event->getPlayer(), $this->get("automatic-post-fail-etc-problem-1"));
						$this->message($event->getPlayer(), $this->get("automatic-post-fail-etc-problem-2"));
					}
				}
				return;
			}
			if ($event->getPlayer()->isOp()) {
				return;
			}
			if ($event->getBlock()->getId() == Block::SIGN_POST) {
				return;
			}
			if ($event->getBlock()->getId() == Block::WALL_SIGN) {
				return;
			}
			if ($event->getBlock()->getId() == Block::CRAFTING_TABLE) {
				return;
			}
			if ($event->getBlock()->getId() == Block::FURNACE) {
				return;
			}
			$this->onBlockChangeEvent($event);
		}
	}


	public function onPlayerQuitEvent(PlayerQuitEvent $event) : void {
		$userName = strtolower($event->getPlayer()->getName());
		if (isset ($this->queue ["movePos"] [$userName])) {
			unset ($this->queue ["movePos"] [$userName]);
		}
	}


	public function onEntityDamageEvent(EntityDamageEvent $event) : void {
		if (!$event instanceof EntityDamageByEntityEvent) {
			return;
		} else {
			if (!$event->getDamager() instanceof Player) {
				return;
			} else {
				if (!$event->getEntity() instanceof Player) {
					return;
				}
			}
		}
		$player = $event->getEntity();
		$area = $this->areaProvider->getArea($player->getLevel(), $player->x, $player->z, strtolower($player->getName()));

		if ($area instanceof AreaSection) {
			if ($area->isPvpAllow()) {
				return;
			}
		} else {
			$whiteWorld = $this->whiteWorldProvider->get($player->getLevel());
			if ($whiteWorld instanceof WhiteWorldData) {
				if ($whiteWorld->isPvpAllow()) {
					return;
				}
			}
		}
		$event->setCancelled();
	}


	public function onEntityCombustEvent(EntityCombustEvent $event) : void {
		if (!$event instanceof EntityCombustByBlockEvent) {
			return;
		} else {
			if (!$event->getCombuster() instanceof Fire) {
				return;

			} else {
				if (!$event->getEntity() instanceof Player) {
					return;
				}
			}
		}
		$player = $event->getEntity();
		$area = $this->areaProvider->getArea($player->getLevel(), $player->x, $player->z, strtolower($player->getName()));

		if ($area instanceof AreaSection) {
			if ($area->isPvpAllow()) {
				return;
			}
		} else {
			$whiteWorld = $this->whiteWorldProvider->get($player->getLevel());
			if ($whiteWorld instanceof WhiteWorldData) {
				if ($whiteWorld->isPvpAllow()) {
					return;
				}
			}
		}
		$event->setCancelled();
	}


	public function onPlayerDeathEvent(PlayerDeathEvent $event) : void {
		$player = $event->getEntity();

		$area = $this->areaProvider->getArea($player->getLevel(), $player->x, $player->z, strtolower($player->getName()));
		if ($area instanceof AreaSection) {
			if ($area->isInvenSave()) {
				$event->setKeepInventory(true);
			}
			return;
		}
		$whiteWorld = $this->whiteWorldProvider->get($player->getLevel());
		if ($whiteWorld instanceof WhiteWorldData) {
			if ($whiteWorld->isInvenSave()) {
				$event->setKeepInventory(true);
			}
			return;
		}
	}

	public function get($var) : string {
		return $this->plugin->get($var);
	}

	public function message(CommandSender $player, $text = "", $mark = null) : void {
		$this->plugin->message($player, $text, $mark);
	}

	public function alert(CommandSender $player, $text = "", $mark = null) : void {
		$this->plugin->alert($player, $text, $mark);
	}

	public function tip(CommandSender $player, $text = "", $mark = null) : void {
		$this->plugin->tip($player, $text, $mark);
	}

	public function makeTimestamp(?string $date) : int {
		if (is_null($date)) {
			$date = date("Y-m-d H:i:s");
		}
		$yy = substr($date, 0, 4);
		$mm = substr($date, 5, 2);
		$dd = substr($date, 8, 2);
		$hh = substr($date, 11, 2);
		$ii = substr($date, 14, 2);
		$ss = substr($date, 17, 2);
		return mktime($hh, $ii, $ss, $mm, $dd, $yy);
	}
}
