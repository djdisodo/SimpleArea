<?php

namespace ifteam\SimpleArea\event;

use pocketmine\event\Event;
use pocketmine\event\Cancellable;
use ifteam\SimpleArea\database\area\AreaProvider;
use ifteam\SimpleArea\database\area\AreaSection;

class AreaBuyEvent extends Event implements Cancellable
{
	public static $handlerList = null;
	public static $eventPool = [];
	public static $nextEvent = 0;
	protected $player, $level, $id;
	protected $buyer;

	/**
	 * __construct()
	 *
	 * @param string $player
	 * @param string $level
	 * @param string $id
	 * @param string $buyer
	 */
	public function __construct($player, $level, $id, $buyer) {
		$this->player = $player;
		$this->level = $level;
		$this->id = $id;
		$this->buyer = $buyer;
	}

	public function getBuyer() {
		return $this->buyer;
	}

	/**
	 * getPlayer()
	 *
	 * @return string
	 */
	public function getPlayer() {
		return $this->player;
	}

	/**
	 * getAreaId()
	 *
	 * @return string $id
	 */
	public function getAreaId() {
		return $this->id;
	}

	/**
	 * getAreaData()
	 *
	 * @return AreaSection $area
	 */
	public function getAreaData() {
		return AreaProvider::getInstance()->getAreaToId($this->level, $this->id);
	}
}

?>