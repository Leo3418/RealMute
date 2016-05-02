<?php  
/* 
	RealMute, a plugin that allows adminstrator to mute players in chat.
	Copyright (C) 2016 Leo3418 (https://github.com/Leo3418)

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

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\CommandExecutor;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\level\Position;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener{
	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->notice("Copyright (C) 2016 Leo3418");
		$this->getLogger()->notice("RealMute is free software licensed under GNU GPLv3 with the absence of any warranty");
		@mkdir($this->getDataFolder());
		$defaultconfig = array(
			"version" => $this->getDescription()->getVersion(),
			"muteall" => false,
			"notification" => false,
			"excludeop" => true,
			"mutedplayers" => "",
		);
		if(file_exists($this->getDataFolder()."config.yml") && strcmp("2", $this->getConfig()->get("version")[0]) < 0){
			copy($this->getDataFolder()."config.yml", $this->getDataFolder()."config.bak");
			$this->getConfig()->setAll($defaultconfig);
			$this->getLogger()->warning("Your config.yml is for a higher version of RealMute.");
			$this->getLogger()->warning("config.yml has been downgraded to version 2.x. Old file was renamed to config.bak.");
		}
		else $config = new Config($this->getDataFolder()."config.yml", Config::YAML, $defaultconfig);
		if(strcmp($this->getConfig()->get("version"), $this->getDescription()->getVersion()) !== 0) $this->getConfig()->set("version", $this->getDescription()->getVersion());
		$config = fopen($this->getDataFolder()."config.yml", "r");
		$oldVersion = false;
		$copied = false;
		while(!feof($config)){
			$line = fgets($config);
			if(strpos($line, ".mute")){
				$oldVersion = true;
				while(!$copied){
					copy($this->getDataFolder()."config.yml", $this->getDataFolder()."config.bak");
					$copied = true;
				}
				$i = strrpos($line, ".mute");
				$name = substr($line, 0, $i);
				$this->getConfig()->remove($name.".mute");
				if(strlen($this->getConfig()->get("mutedplayers")) == 0) $this->getConfig()->set("mutedplayers", strtolower($name).",");
				else $this->getConfig()->set("mutedplayers", $this->getConfig()->get("mutedplayers").strtolower($name).",");
			}
		}
		$this->getConfig()->save();
		if($oldVersion){
			$this->getLogger()->info("An old version of config.yml detected. Old file was renamed to config.bak.");
			$this->getLogger()->info("Your config.yml has been updated so that it is compatible with version 2.x!");
		}
		return true;
	}
	public function onDisable(){
		$this->getConfig()->save();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "realmute":
				if(count($args) !== 1){
					$sender->sendMessage("Usage: ".$command->getUsage());
					return true;
				}
				$option = array_shift($args);
				if($option == "help"){
					$helpmsg  = TextFormat::AQUA."[RealMute] Options\n";
					$helpmsg .= TextFormat::GOLD."/realmute notify ".TextFormat::WHITE."Toggle notification to muted players\n";
					$helpmsg .= TextFormat::GOLD."/realmute muteop ".TextFormat::WHITE."Include/Exclude OPs from muting all players\n";
					$helpmsg .= TextFormat::GOLD."/realmute status ".TextFormat::WHITE."View current status of this plugin\n";
					$helpmsg .= TextFormat::GOLD."/realmute list ".TextFormat::WHITE."List muted players\n";
					$helpmsg .= TextFormat::GOLD."/realmute about ".TextFormat::WHITE . "Show information about this plugin\n";
					$sender->sendMessage($helpmsg);
					return true;
				}
				if($option == "notify"){
					if($this->getConfig()->__get("notification") == false){
						$this->getConfig()->set("notification", true);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::GREEN."[RealMute] Muted players will be notified when they are sending messages.");
						return true;
					}
					else{
						$this->getConfig()->set("notification", false);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::YELLOW."[RealMute] Muted players will not be notified.");
						return true;
					}
				}
				if($option == "muteop"){
					if($this->getConfig()->get("excludeop") == true){
						$this->getConfig()->set("excludeop", false);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::YELLOW."[RealMute] OPs will be muted when all players are muted.");
						return true;
					}
					else{
						$this->getConfig()->set("excludeop", true);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::GREEN."[RealMute] OPs will be excluded in muting all players.");
						return true;
					}
				}
				if($option == "status"){
					$status = TextFormat::AQUA."[RealMute] Status\n";
					$status .= TextFormat::WHITE."Mute all players: ".$this->isOn("muteall")."\n";
					$status .= TextFormat::WHITE."Notify muted players: ".$this->isOn("notification")."\n";
					$status .= TextFormat::WHITE."Exclude OPs in muting all players: ".$this->isOn("excludeop")."\n";
					$status .= TextFormat::WHITE."Number of muted players: ".(count(explode(",",$this->getConfig()->get("mutedplayers"))) - 1)."\n";
					$status .= TextFormat::WHITE."To see list of muted players, please use ".TextFormat::GOLD."/realmute list\n";
					$sender->sendMessage($status);
					return true;
				}
				if($option == "list"){
					$list = explode(",",$this->getConfig()->get("mutedplayers"));
					array_pop($list);
					$output = TextFormat::AQUA."[RealMute] Muted players (".(count(explode(",",$this->getConfig()->get("mutedplayers"))) - 1.).")\n";
					$output .= implode(", ", $list);
					$sender->sendMessage($output);
					return true;
				}
				if($option == "about"){
					$aboutmsg = TextFormat::AQUA."RealMute Version ".$this->getDescription()->getVersion() . "\n";
					$aboutmsg .= "RealMute is a plugin that allows adminstrator to mute players in chat.\n";
					$aboutmsg .= "Copyright (C) 2016 Leo3418 (https://github.com/Leo3418)\n";
					$aboutmsg .= "This is free software licensed under GNU GPLv3 with the absence of any warranty.\n";
					$aboutmsg .= "See http://www.gnu.org/licenses/ for details.\n";
					$aboutmsg .= "You can find updates and source code of this plugin, report bug, and contribute to this project at ".$this->getDescription()->getWebsite()."\n";
					$sender->sendMessage($aboutmsg);
					return true;
				}
				else{
					$sender->sendMessage("Usage: ".$command->getUsage());
					return true;
				}
			case "rmute":
				if(count($args) !== 1){
					$sender->sendMessage("Usage: ".$command->getUsage());
					return true;
				}
				$name = array_shift($args);
				if(!$this->isPlayerMuted($name)){
					if(strlen($this->getConfig()->get("mutedplayers")) == 0) $this->getConfig()->set("mutedplayers", strtolower($name).",");
					else $this->getConfig()->set("mutedplayers", $this->getConfig()->get("mutedplayers").strtolower($name).",");
					$this->getConfig()->save();
					$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully muted ".$name.".");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED."[RealMute] ".$name." has been already muted.");
					return true;
				}
			case "runmute":
				if(count($args) !== 1){
					$sender->sendMessage("Usage: ".$command->getUsage());
					return true;
				}
				$name = array_shift($args);
				if($this->isPlayerMuted($name)){
					$this->unmute($name);
					$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully unmuted ".$name.".");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED."[RealMute] ".$name." is not muted.");
					return true;
				}
			case "muteall":
				if(count($args) !== 0){
					$sender->sendMessage("Usage: ".$command->getUsage());
					return true;
				}
				if($this->getConfig()->__get("muteall") == false){
					$this->getConfig()->set("muteall", true);
					$this->getConfig()->set("muteall", true);
					$this->getConfig()->save();
					$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully muted all players.");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED."[RealMute] Players have been already muted.");
					return true;
				}
			case "unmuteall":
				if(count($args) !== 0){
					$sender->sendMessage("Usage: ".$command->getUsage());
					return true;
				}
				if($this->getConfig()->__get("muteall") == true){
					$this->getConfig()->set("muteall", false);
					$this->getConfig()->save();
					$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully unmuted all players.");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED."[RealMute] Players are not muted.");
					return true;
				}
		}
	}
	public function onPlayerChat(PlayerChatEvent $event){
		$player = $event->getPlayer()->getName();
		if($this->getConfig()->get("muteall") == true){
			if($this->getConfig()->get("excludeop") == true && $event->getPlayer()->hasPermission("realmute.muteignored")) return true;
			else{
				$event->setCancelled(true);
				if($this->getConfig()->get("notification") == true) $event->getPlayer()->sendMessage(TextFormat::RED."You have been muted in chat.");
				return true;
			}
		}
		elseif($this->isPlayerMuted($player)){
			$event->setCancelled(true);
			if($this->getConfig()->get("notification") == true) $event->getPlayer()->sendMessage(TextFormat::RED."You have been muted in chat.");
			return true;
		}
		else return true;
	}
	protected function isPlayerMuted($name){
		foreach((explode(",",$this->getConfig()->get("mutedplayers"))) as $player){
			if(strcmp(strtolower($name), $player) == 0){
				return true;
				break;
			}
		}
		return false;
	}
	protected function unmute($name){
		$mp = "";
		$count = 0;
		foreach((explode(",",$this->getConfig()->get("mutedplayers"))) as $player){
			if(strcmp(strtolower($name), $player) == 0){
				if(count(explode(",",$this->getConfig()->get("mutedplayers"))) == 2){
					$this->getConfig()->set("mutedplayers", "");
					break;
				}
			}
			else{
				$count += 1;
				if(strcmp($count, substr_count($this->getConfig()->get("mutedplayers"), ",")) == 0) $mp .= $player;
				else $mp .= $player.",";
			}
		}
		$this->getConfig()->set("mutedplayers", $mp);
		$this->getConfig()->save();
	}
	protected function isOn($opt){
		if($this->getConfig()->get($opt) == true) $text = TextFormat::GREEN."ON";
		else $text = TextFormat::YELLOW."OFF";
		return $text;
	}
}
?>