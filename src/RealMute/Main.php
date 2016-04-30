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
		if(!is_dir($this->getDataFolder())) @mkdir($this->getDataFolder(), 0777, true);
		$config = new Config($this->getDataFolder()."config.yml", Config::YAML, array(
			"muteall" => false,
			"notification" => false,
		));
	}
	public function onDisable(){
		$this->getConfig()->save();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "rm":
				if(count($args) !== 1){
					$sender->sendMessage("Usage: " . $command->getUsage());
					return true;
				}
				$option = array_shift($args);
				if($option == "help"){
					$helpmsg  = TextFormat::AQUA . "[RealMute] Options\n";
					$helpmsg .= TextFormat::GOLD . "/rm notify " . TextFormat::WHITE . "Toggle notification to muted players\n";
					$helpmsg .= TextFormat::GOLD . "/rm about " . TextFormat::WHITE . "Show information about this plugin\n";
					$sender->sendMessage($helpmsg);
					return true;
				}
				if($option == "notify"){
					if($this->getConfig()->__get("notification") == false){
						$this->getConfig()->set("notification", true);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::GREEN ."[RealMute] Muted players will be notified when they are sending messages.");
						return true;
					}
					else{
						$this->getConfig()->set("notification", false);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::YELLOW ."[RealMute] Muted players will not be notified.");
						return true;
					}
				}
				if($option == "about"){
					$aboutmsg  = TextFormat::AQUA . "RealMute Version " . $this->getDescription()->getVersion() . "\n";
					$aboutmsg .= "RealMute is a plugin that allows adminstrator to mute players in chat.\n";
					$aboutmsg .= "Copyright (C) 2016 Leo3418 (https://github.com/Leo3418)\n";
					$aboutmsg .= "This is free software licensed under GNU GPLv3 with the absence of any warranty.\n";
					$aboutmsg .= "See http://www.gnu.org/licenses/ for details.\n";
					$aboutmsg .= "You can find updates and source code of this plugin at " . $this->getDescription()->getWebsite() . "\n";
					$sender->sendMessage($aboutmsg);
					return true;
				}
				else{
					$sender->sendMessage("Usage: " . $command->getUsage());
					return true;
				}
			case "rmute":
				if(count($args) !== 1){
					$sender->sendMessage("Usage: " . $command->getUsage());
					return true;
				}
				$name = array_shift($args);
				if(!$this->getConfig()->get($name.".mute")){ 
					$this->getConfig()->set($name.".mute", true);
					$this->getConfig()->save();
					$sender->sendMessage(TextFormat::GREEN ."[RealMute] Successfully muted " . $name . ".");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED . "[RealMute] " . $name . " has been already muted.");
					return true;
				}
			case "runmute":
				if(count($args) !== 1){
					$sender->sendMessage("Usage: " . $command->getUsage());
					return true;
				}
				$name = array_shift($args);
				if($this->getConfig()->get($name.".mute") == true){
					$this->getConfig()->remove($name.".mute", true);
					$this->getConfig()->save();
					$sender->sendMessage(TextFormat::GREEN ."[RealMute] Successfully unmuted " . $name . ".");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED . "[RealMute] " . $name . " is not muted.");
					return true;
				}
			case "muteall":
				if (count($args) !== 0){
					$sender->sendMessage("Usage: " . $command->getUsage());
					return true;
				}
				if($this->getConfig()->__get("muteall") == false){
					$this->getConfig()->set("muteall", true);
					$this->getConfig()->save();
					$sender->sendMessage(TextFormat::GREEN ."[RealMute] Successfully muted all players.");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED . "[RealMute] Players have been already muted.");
					return true;
				}
			case "unmuteall":
				if(count($args) !== 0){
					$sender->sendMessage("Usage: " . $command->getUsage());
					return true;
				}
				if($this->getConfig()->__get("muteall") == true){
					$this->getConfig()->set("muteall", false);
					$this->getConfig()->save();
					$sender->sendMessage(TextFormat::GREEN ."[RealMute] Successfully unmuted all players.");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED . "[RealMute] Players have been already unmuted.");
					return true;
				}
		}
	}
	public function onPlayerChat(PlayerChatEvent $event) {
		$player = $event->getPlayer()->getName();
		if($this->getConfig()->get("muteall") == true){
			$event->setCancelled(true);
			if($this->getConfig()->get("notification") == true) $event->getPlayer()->sendMessage(TextFormat::RED . "You have been muted in chat.");
			return true;
		}
		elseif($this->getConfig()->get($player.".mute") == true){
			$event->setCancelled(true);
			if($this->getConfig()->get("notification") == true) $event->getPlayer()->sendMessage(TextFormat::RED . "You have been muted in chat.");
			return;
		}
		else return true;
	}
}