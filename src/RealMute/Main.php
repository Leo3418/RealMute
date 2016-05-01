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
		$config = new Config($this->getDataFolder()."config.yml", Config::YAML, array(
			"version" => $this->getDescription()->getVersion(),
			"muteall" => false,
			"notification" => false,
			"excludeop" => true,
			"mutedplayers" => "",
			));
		# Clean up old player-mute config in config.yml
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
					$helpmsg .= TextFormat::GOLD."/realmute notify " . TextFormat::WHITE . "Toggle notification to muted players\n";
					$helpmsg .= TextFormat::GOLD."/realmute muteop " . TextFormat::WHITE . "Include/Exclude OPs from muting all players\n";
					$helpmsg .= TextFormat::GOLD."/realmute about " . TextFormat::WHITE . "Show information about this plugin\n";
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
				if($option == "about"){
					$aboutmsg = TextFormat::AQUA."RealMute Version " . $this->getDescription()->getVersion() . "\n";
					$aboutmsg .= "RealMute is a plugin that allows adminstrator to mute players in chat.\n";
					$aboutmsg .= "Copyright (C) 2016 Leo3418 (https://github.com/Leo3418)\n";
					$aboutmsg .= "This is free software licensed under GNU GPLv3 with the absence of any warranty.\n";
					$aboutmsg .= "See http://www.gnu.org/licenses/ for details.\n";
					$aboutmsg .= "You can find updates and source code of this plugin at " . $this->getDescription()->getWebsite() . "\n";
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
					$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully muted " . $name . ".");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED."[RealMute] " . $name . " has been already muted.");
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
					$this->getConfig()->save();
					$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully unmuted " . $name . ".");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED."[RealMute] " . $name . " is not muted.");
					return true;
				}
			case "muteall":
				if (count($args) !== 0){
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
					$sender->sendMessage(TextFormat::RED."[RealMute] Players have been already unmuted.");
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
				if($this->getConfig()->get("notification") == true) $event->getPlayer()->sendMessage(TextFormat::RED . "You have been muted in chat.");
				return true;
			}
		}
		elseif($this->isPlayerMuted($player)){
			$event->setCancelled(true);
			if($this->getConfig()->get("notification") == true) $event->getPlayer()->sendMessage(TextFormat::RED . "You have been muted in chat.");
			return true;
		}
		else return true;
	}
	protected function isPlayerMuted($name){
		foreach((explode(",",$this->getConfig()->get("mutedplayers"))) as $player){
			if(strtolower($name) == $player) return true;
			else return false;
		}
	}
	protected function unmute($name){
		foreach((explode(",",$this->getConfig()->get("mutedplayers"))) as $player){
			if(substr_count($this->getConfig()->get("mutedplayers"), ",") == 1){
				$this->getConfig()->set("mutedplayers", "");
				return true;
			}
			else{
				if(strtolower($name) !== $player) {
					$this->getConfig()->set("mutedplayers", strtolower($player).",");
					return true;
				}
				else return true;
			}
		}
	}
}
?>