<?php

namespace ifteam\SimpleArea\task;

use pocketmine\scheduler\Task;
use ifteam\SimpleArea\SimpleArea;

class AutoSaveTask extends Task {
	public function __construct(SimpleArea $owner) {
		parent::__construct ( $owner );
	}
	public function onRun(int $currentTick) {
		$this->getOwner ()->autoSave ();
	}
}

?>