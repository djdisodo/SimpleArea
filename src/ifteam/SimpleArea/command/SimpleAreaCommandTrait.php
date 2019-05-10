<?php
namespace ifteam\SimpleArea\command;

use ifteam\SimpleArea\database\area\AreaManager;
use ifteam\SimpleArea\database\area\AreaProvider;
use ifteam\SimpleArea\database\world\WhiteWorldManager;
use ifteam\SimpleArea\database\world\WhiteWorldProvider;
use ifteam\SimpleArea\SimpleArea;

trait SimpleAreaCommandTrait {
	private $simpleArea;
	public function getSimpleArea() : SimpleArea {
		return $this->simpleArea;
	}
	public function getAreaManager() : AreaManager {
		return AreaManager::getInstance();
	}
	public function getWhiteWorldProvider() : WhiteWorldProvider {
		return WhiteWorldProvider::getInstance();
	}
	public function getAreaProvider() : AreaProvider {
		return AreaProvider::getInstance();
	}
}
