<?php
/* 
	RealMute, a PocketMine-MP chat management plugin with many extra features.
	Copyright (C) 2016, 2017 Leo3418 (https://github.com/Leo3418)

	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see (http://www.gnu.org/licenses/). 
*/

namespace RealMute;

use pocketmine\scheduler\PluginTask;
use pocketmine\utils\Config;

class CheckTime extends PluginTask{
	public $plugin;
	public function __construct(Main $plugin){
		parent::__construct($plugin);
		$this->plugin = $plugin;
	}
	public function getPlugin(){
		return $this->plugin;
	}
	public function onRun($tick){
		$list = explode(",",$this->getPlugin()->getConfig()->get("mutedplayers"));
		array_pop($list);
		foreach($list as $player){
			if(is_file($this->getPlugin()->getDataFolder()."players/".strtolower($player[0])."/".strtolower($player).".yml")){
				$userconfig = new Config($this->getPlugin()->getDataFolder()."players/".strtolower($player[0])."/".strtolower($player).".yml");
				if($userconfig->get("unmutetime") != false){
					$unmutetime = $userconfig->get("unmutetime");
					if($unmutetime < time()){
						$this->getPlugin()->remove("mutedplayers", $player);
						$this->getPlugin()->removeIdentity($player);
					}
				}
			}
		}
	}
}