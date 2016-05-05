<?php  
/* 
	RealMute, a plugin that allows administrator to mute players in chat.
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
use pocketmine\event\player\PlayerCommandPreprocessEvent;

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
			"wordmute" => false,
			"banpm" => false,
			"mutedplayers" => "",
			"bannedwords" => "",
		);
		if(file_exists($this->getDataFolder()."config.yml") && strcmp("2", $this->getConfig()->get("version")[0]) < 0){
			copy($this->getDataFolder()."config.yml", $this->getDataFolder()."config.bak");
			$this->getConfig()->setAll($defaultconfig);
			$this->getLogger()->warning("Your config.yml is for a higher version of RealMute.");
			$this->getLogger()->warning("config.yml has been downgraded to version 2.x. Old file was renamed to config.bak.");
		}
		if(file_exists($this->getDataFolder()."config.yml") && strcmp($this->getConfig()->get("version"), $this->getDescription()->getVersion()) !== 0) $this->getConfig()->set("version", $this->getDescription()->getVersion());
		if(file_exists($this->getDataFolder()."config.yml")){
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
					$this->add("mutedplayers", $name);
				}
			}
			if($oldVersion){
				$this->getLogger()->info("An old version of config.yml detected. Old file was renamed to config.bak.");
				$this->getLogger()->info("Your config.yml has been updated so that it is compatible with version 2.x!");
			}
		}
		$config = new Config($this->getDataFolder()."config.yml", Config::YAML, $defaultconfig);
		$this->getConfig()->save();
	}
	public function onDisable(){
		$this->getConfig()->save();
	}
	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		switch($command->getName()){
			case "realmute":
				if(count($args) !== 1 && count($args) !== 2){
					$sender->sendMessage("Usage: ".$command->getUsage());
					return true;
				}
				$option = array_shift($args);
				if($option == "help"){
					if(count($args) !== 1 || array_shift($args) == 1){
						$helpmsg  = TextFormat::AQUA."[RealMute] Options".TextFormat::WHITE." (Page 1/2)"."\n";
						$helpmsg .= TextFormat::GOLD."/realmute help <page> ".TextFormat::WHITE."Jump to another page of Help\n";
						$helpmsg .= TextFormat::GOLD."/realmute notify ".TextFormat::WHITE."Toggle notification to muted players\n";
						$helpmsg .= TextFormat::GOLD."/realmute muteop ".TextFormat::WHITE."When muting all players, include/exclude OPs\n";
						$helpmsg .= TextFormat::GOLD."/realmute wordmute ".TextFormat::WHITE."Turn on/off auto-muting players if they send banned words\n";
						$helpmsg .= TextFormat::GOLD."/realmute banpm ".TextFormat::WHITE."Turn on/off blocking muted players' private messages\n";
						$sender->sendMessage($helpmsg);
						return true;
					}
					else{
						$helpmsg  = TextFormat::AQUA."[RealMute] Options".TextFormat::WHITE." (Page 2/2)"."\n";
						$helpmsg .= TextFormat::GOLD."/realmute addword <word> ".TextFormat::WHITE."Add a keyword to banned-word list, if you want to match the whole word only, please add an exclamation mark before the word\n";
						$helpmsg .= TextFormat::GOLD."/realmute delword <word> ".TextFormat::WHITE."Delete a keyword from banned-word list\n";
						$helpmsg .= TextFormat::GOLD."/realmute status ".TextFormat::WHITE."View current status of this plugin\n";
						$helpmsg .= TextFormat::GOLD."/realmute list ".TextFormat::WHITE."List muted players\n";
						$helpmsg .= TextFormat::GOLD."/realmute word ".TextFormat::WHITE."Show the banned-word list\n";
						$helpmsg .= TextFormat::GOLD."/realmute about ".TextFormat::WHITE . "Show information about this plugin\n";
						$sender->sendMessage($helpmsg);
						return true;
					}
				}
				if($option == "notify"){
					if($this->getConfig()->get("notification") == false){
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
						$sender->sendMessage(TextFormat::YELLOW."[RealMute] OPs will be muted with all players.");
						return true;
					}
					else{
						$this->getConfig()->set("excludeop", true);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::GREEN."[RealMute] When muting all players, OPs will be excluded.");
						return true;
					}
				}
				if($option == "wordmute"){
					if($this->getConfig()->get("wordmute") == false){
						$this->getConfig()->set("wordmute", true);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::GREEN."[RealMute] Players will be automatically muted if they send banned words.");
						return true;
					}
					else{
						$this->getConfig()->set("wordmute", false);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::YELLOW."[RealMute] Players will not muted if they send banned words.");
						return true;
					}
				}
				if($option == "banpm"){
					if($this->getConfig()->get("banpm") == false){
						$this->getConfig()->set("banpm", true);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::GREEN."[RealMute] Private messages sent by muted players will be blocked.");
						return true;
					}
					else{
						$this->getConfig()->set("banpm", false);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::YELLOW."[RealMute] Players can send private messages when they are muted in chat.");
						return true;
					}
				}
				if($option == "status"){
					$status = TextFormat::AQUA."[RealMute] Status\n";
					$status .= TextFormat::WHITE."Mute all players: ".$this->isOn("muteall")."\n";
					$status .= TextFormat::WHITE."Notify muted players: ".$this->isOn("notification")."\n";
					$status .= TextFormat::WHITE."Exclude OPs when muting all players: ".$this->isOn("excludeop")."\n";
					$status .= TextFormat::WHITE."Auto-mute players if they send banned words: ".$this->isOn("wordmute")."\n";
					$status .= TextFormat::WHITE."Block muted players' private messages: ".$this->isOn("banpm")."\n";
					$status .= TextFormat::WHITE."Number of muted players: ".TextFormat::AQUA.(count(explode(",",$this->getConfig()->get("mutedplayers"))) - 1)."\n";
					$status .= TextFormat::WHITE."Number of banned words: ".TextFormat::AQUA.(count(explode(",",$this->getConfig()->get("bannedwords"))) - 1)."\n";
					$sender->sendMessage($status);
					return true;
				}
				if($option == "list"){
					$list = explode(",",$this->getConfig()->get("mutedplayers"));
					array_pop($list);
					$output = TextFormat::AQUA."[RealMute] Muted players ".TextFormat::WHITE."(".(count(explode(",",$this->getConfig()->get("mutedplayers"))) - 1.).")\n";
					$output .= implode(", ", $list);
					$sender->sendMessage($output);
					return true;
				}
				if($option == "addword"){
					if(count($args) !== 1){
						$sender->sendMessage("Usage: ".$command->getUsage());
						return true;
					}
					$word = array_shift($args);
					if(!$this->inList("bannedwords", $word)){
						$this->add("bannedwords", $word);
						$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully added ".$word." to banned-word list.");
						return true;
					}
					else{
						$sender->sendMessage(TextFormat::RED."[RealMute] ".$word." has been already added to banned-word list.");
						return true;
					}
				}
				if($option == "delword"){
					if(count($args) !== 1){
						$sender->sendMessage("Usage: ".$command->getUsage());
						return true;
					}
					$word = array_shift($args);
					if($this->inList("bannedwords", $word)){
						$this->remove("bannedwords", $word);
						$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully deleted ".$word." from banned-word list.");
						return true;
					}
					else{
						$sender->sendMessage(TextFormat::RED."[RealMute] ".$word." is not in the banned-word list.");
						return true;
					}
				}
				if($option == "word"){
					if(count($args) !== 0){
						$sender->sendMessage("Usage: ".$command->getUsage());
						return true;
					}
					$list = explode(",",$this->getConfig()->get("bannedwords"));
					array_pop($list);
					$output = TextFormat::AQUA."[RealMute] Banned words ".TextFormat::WHITE."(".(count(explode(",",$this->getConfig()->get("bannedwords"))) - 1.).")\n";
					$output .= implode(", ", $list);
					$output .= TextFormat::GOLD."\nNote: ".TextFormat::WHITE."If a word begins with the exclamation mark, it will only be blocked if player sends it as an individual word.";
					$sender->sendMessage($output);
					return true;
				}
				if($option == "about"){
					$aboutmsg = TextFormat::AQUA."[RealMute] Version ".$this->getDescription()->getVersion()."\n";
					$aboutmsg .= "RealMute is a plugin that allows administrator to mute players in chat.\n";
					$aboutmsg .= "Copyright (C) 2016 Leo3418 (https://github.com/Leo3418)\n";
					$aboutmsg .= "This is free software licensed under GNU GPLv3 with the absence of any warranty.\n";
					$aboutmsg .= "See http://www.gnu.org/licenses/ for details.\n";
					$aboutmsg .= "You can find updates, documentations and source code of this plugin, report bug, and contribute to this project at ".$this->getDescription()->getWebsite()."\n";
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
				if(!$this->inList("mutedplayers", $name)){
					$this->add("mutedplayers", $name);
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
				if($this->inList("mutedplayers", $name)){
					$this->remove("mutedplayers", $name);
					$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully unmuted ".$name.".");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED."[RealMute] ".$name." has not been muted yet.");
					return true;
				}
			case "muteall":
				if($this->getConfig()->get("muteall") == false){
					$this->getConfig()->set("muteall", true);
					$this->getConfig()->set("muteall", true);
					$this->getConfig()->save();
					$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully muted all players.");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED."[RealMute] You have already muted all players.");
					return true;
				}
			case "unmuteall":
				if($this->getConfig()->get("muteall") == true){
					$this->getConfig()->set("muteall", false);
					$this->getConfig()->save();
					$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully unmuted all players.");
					return true;
				}
				else{
					$sender->sendMessage(TextFormat::RED."[RealMute] You need to mute all players first.");
					return true;
				}
		}
	}
	public function onPlayerChat(PlayerChatEvent $event){
		$player = $event->getPlayer()->getName();
		$message = $event->getMessage();
		if($this->getConfig()->get("muteall") == true){
			if($this->getConfig()->get("excludeop") == true && $event->getPlayer()->hasPermission("realmute.muteignored")) return true;
			else{
				$event->setCancelled(true);
				if($this->getConfig()->get("notification") == true) $event->getPlayer()->sendMessage(TextFormat::RED."Administrator has muted all players in chat.");
				return true;
			}
		}
		elseif($this->inList("mutedplayers", $player)){
			$event->setCancelled(true);
			if($this->getConfig()->get("notification") == true) $event->getPlayer()->sendMessage(TextFormat::RED."You have been muted in chat.");
			return true;
		}
		foreach(explode(",",$this->getConfig()->get("bannedwords")) as $bannedword){
			if(strlen($bannedword)!== 0 && $bannedword[0] == "!"){
				$bannedword = substr($bannedword, 1);
				foreach(explode(" ",$message) as $word){
					if(strcmp(strtolower($word), $bannedword) == 0){
						$event->setCancelled(true);
						if($this->getConfig()->get("wordmute") == true){
							if($this->getConfig()->get("notification") == true) $event->getPlayer()->sendMessage(TextFormat::RED."Your message contains banned word set by administrator. You are now muted in chat.");
							else $event->getPlayer()->sendMessage(TextFormat::RED."Your message contains banned word set by administrator.");
							$this->add("mutedplayers", $player);
							$this->getLogger()->notice($player." sent banned words in chat and has been muted automatically.");
							return true;
							break;
						}
						else $event->getPlayer()->sendMessage(TextFormat::RED."Your message contains banned word set by administrator.");
						return true;
						break;
					}
				}
			}
			else{
				if(stripos($message, $bannedword) !== false){
					$event->setCancelled(true);
					if($this->getConfig()->get("wordmute") == true){
						if($this->getConfig()->get("notification") == true) $event->getPlayer()->sendMessage(TextFormat::RED."Your message contains banned word set by administrator. You are now muted in chat.");
						else $event->getPlayer()->sendMessage(TextFormat::RED."Your message contains banned word set by administrator.");
						$this->add("mutedplayers", $player);
						$this->getLogger()->notice($player." sent banned words in chat and has been muted automatically.");
						return true;
						break;
					}
					else $event->getPlayer()->sendMessage(TextFormat::RED."Your message contains banned word set by administrator.");
					return true;
					break;
				}
			}
		}
	}
	public function onPlayerCommand(PlayerCommandPreprocessEvent $event){
		$player = $event->getPlayer()->getName();
		$command = strtolower($event->getMessage());
		if($this->getConfig()->get("banpm") == true && $this->inList("mutedplayers", $player) && substr($command, 0, 6) == "/tell "){
			$event->setCancelled(true);
			if($this->getConfig()->get("notification") == true) $event->getPlayer()->sendMessage(TextFormat::RED."You are not allowed to send private messages.");
			return true;
		}
	}
	protected function inList($opt, $target){
		foreach((explode(",",$this->getConfig()->get($opt))) as $item){
			if(strcmp(strtolower($target), $item) == 0){
				return true;
				break;
			}
		}
		return false;
	}
	protected function add($opt, $target){
		if(count(explode(",",$this->getConfig()->get($opt))) == 1) $this->getConfig()->set($opt, strtolower($target).",");
		else $this->getConfig()->set($opt, $this->getConfig()->get($opt).strtolower($target).",");
		$this->getConfig()->save();
	}
	protected function remove($opt, $target){
		$newlist = "";
		$count = 0;
		foreach((explode(",",$this->getConfig()->get($opt))) as $item){
			if(strcmp(strtolower($target), $item) == 0){
				if(count(explode(",",$this->getConfig()->get($opt))) == 2){
					$this->getConfig()->set($opt, "");
					break;
				}
			}
			else{
				$count += 1;
				if(strcmp($count, substr_count($this->getConfig()->get($opt), ",")) == 0) $newlist .= $item;
				else $newlist .= $item.",";
			}
		}
		$this->getConfig()->set($opt, $newlist);
		$this->getConfig()->save();
	}
	protected function isOn($opt){
		if($this->getConfig()->get($opt) == true) $text = TextFormat::GREEN."ON";
		else $text = TextFormat::YELLOW."OFF";
		return $text;
	}
}
?>