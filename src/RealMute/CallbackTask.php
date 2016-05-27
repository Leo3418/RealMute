<?php

/*
	The author of this portion of code is PocketMine Team.
	It is originally licensed under GNU Lesser General Public
	License version 3 published by the Free Software Foundation.
*/

namespace RealMute;

use pocketmine\scheduler\Task;

class CallbackTask extends Task{

	/** @var callable */
	protected $callable;

	/** @var array */
	protected $args;

	/**
	 * @param callable $callable
	 * @param array    $args
	 */
	public function __construct(callable $callable, array $args = array()){
		$this->callable = $callable;
		$this->args = $args;
		$this->args[] = $this;
	}

	/**
	 * @return callable
	 */
	public function getCallable(){
		return $this->callable;
	}

	public function onRun($currentTicks){
		call_user_func_array($this->callable, $this->args);
	}

}
?>