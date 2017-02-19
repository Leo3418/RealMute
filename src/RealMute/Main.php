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

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener{
	public function onEnable(){
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->notice("Copyright (C) 2016, 2017 Leo3418");
		$this->getLogger()->notice("RealMute is free software licensed under GNU GPLv3 with the absence of any warranty");
		if(!is_dir($this->getDataFolder())) mkdir($this->getDataFolder());
		if(!is_dir($this->getDataFolder()."players")) mkdir($this->getDataFolder()."players", 0777, true);
		$defaultconfig = array(
			"version" => $this->getDescription()->getVersion(),
			"muteall" => false,
			"notification" => true,
			"excludeop" => true,
			"wordmute" => false,
			"banpm" => false,
			"banspam" => false,
			"banlengthy" => false,
			"bansign" => false,
			"muteidentity" => false,
			"spamthreshold" => 1,
			"automutetime" => false,
			"lengthlimit" => false,
			"mutedplayers" => "",
			"bannedwords" => "",
		);
		if(file_exists($this->getDataFolder()."config.yml") && strcmp("3", $this->getConfig()->get("version")[0]) < 0){
			copy($this->getDataFolder()."config.yml", $this->getDataFolder()."config.bak");
			$this->getConfig()->setAll($defaultconfig);
			$this->getLogger()->warning("Your config.yml is for a higher version of RealMute.");
			$this->getLogger()->warning("config.yml has been downgraded to version 3.x. Old file was renamed to config.bak.");
		}
		$ver2 = false;
		if(file_exists($this->getDataFolder()."config.yml") && strcmp("2", $this->getConfig()->get("version")[0]) == 0) $ver2 = true;
		if(file_exists($this->getDataFolder()."config.yml") && strcmp($this->getConfig()->get("version"), $this->getDescription()->getVersion()) !== 0) $this->getConfig()->set("version", $this->getDescription()->getVersion());
		if(file_exists($this->getDataFolder()."config.yml")){
			$config = fopen($this->getDataFolder()."config.yml", "r");
			$ver1 = false;
			$copied = false;
			while(!feof($config)){
				$line = fgets($config);
				if(strpos($line, ".mute")){
					$ver1 = true;
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
			if($ver1){
				$this->getLogger()->info("An old version of config.yml detected. Old file was renamed to config.bak.");
				$this->getLogger()->info("Your config.yml has been updated so that it is compatible with version 3.x!");
			}
			if($ver2){
				$this->getLogger()->warning("If you want to downgrade RealMute back to version 2.x, please make sure you use v2.7.5 or v2.0.x-2.3.x.");
				$this->getLogger()->warning("You can download version 2.7.5 at https://github.com/Leo3418/RealMute/releases/tag/v2.7.5");
			}
		}
		$config = new Config($this->getDataFolder()."config.yml", Config::YAML, $defaultconfig);
		$this->getConfig()->save();
		if(strcmp("1", $this->getServer()->getApiVersion()[0]) != 0) $this->supportcid = true;
		else $this->supportcid = false;
		$this->identity = new Config($this->getDataFolder()."identity.txt", Config::ENUM);
		$this->identity->save();
		$this->lastmsgsender = "";
		$this->lastmsgtime = "";
		$this->consecutivemsg = 1;
		$checkTimeTask = new CheckTime($this);
		$handler = $this->getServer()->getScheduler()->scheduleRepeatingTask($checkTimeTask, 20);
		$checkTimeTask->setHandler($handler);
	}
	public function onDisable(){
		$this->getConfig()->save();
		$this->identity->save();
	}
	public function onJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer()->getName();
		if(!is_dir($this->getDataFolder()."players/".strtolower($player[0]))) mkdir($this->getDataFolder()."players/".strtolower($player[0]), 0777, true);
		$userconfig = new Config($this->getDataFolder()."players/".strtolower($player[0])."/".strtolower($player).".yml", Config::YAML);
		if($this->supportcid) $userconfig->set("identity", strval($event->getPlayer()->getClientId()));
		else $userconfig->set("identity", strval($event->getPlayer()->getAddress()));
		$userconfig->save();
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
					if(count($args) !== 1 || $args[0] == 1){
						$helpmsg  = TextFormat::AQUA."[RealMute] Options".TextFormat::WHITE." (Page 1/3)"."\n";
						$helpmsg .= TextFormat::GOLD."/realmute help <page> ".TextFormat::WHITE."Jump to another page of Help\n";
						$helpmsg .= TextFormat::GOLD."/realmute notify <on|off|fake>".TextFormat::WHITE."Toggle notification to muted players, or show a fake chat message to muted players\n";
						$helpmsg .= TextFormat::GOLD."/realmute muteop ".TextFormat::WHITE."When muting all players, include/exclude OPs\n";
						$helpmsg .= TextFormat::GOLD."/realmute wordmute ".TextFormat::WHITE."Turn on/off auto-muting players if they send banned words\n";
						$helpmsg .= TextFormat::GOLD."/realmute banpm ".TextFormat::WHITE."Turn on/off blocking muted players' private messages\n";
						$helpmsg .= TextFormat::GOLD."/realmute banspam ".TextFormat::WHITE."Turn on/off auto-muting players if they flood the chat screen\n";		
						$sender->sendMessage($helpmsg);
						return true;
					}
					if($args[0] == 2){
						$helpmsg  = TextFormat::AQUA."[RealMute] Options".TextFormat::WHITE." (Page 2/3)"."\n";
						$helpmsg .= TextFormat::GOLD."/realmute banlengthy <mute|slice|off> ".TextFormat::WHITE."Mute/Slice/Allow messages exceeding the length limit\n";
						$helpmsg .= TextFormat::GOLD."/realmute bansign ".TextFormat::WHITE."Allow/Disallow muted players to use signs\n";
						if($this->supportcid) $helpmsg .= TextFormat::GOLD."/realmute mutedevice ".TextFormat::WHITE."Turn on/off muting players' devices alongside usernames\n";
						else $helpmsg .= TextFormat::GOLD."/realmute muteip ".TextFormat::WHITE."Turn on/off muting players' IPs alongside usernames\n";
						$helpmsg .= TextFormat::GOLD."/realmute spamth <time in seconds> ".TextFormat::WHITE."Set minimun interval allowed between two messages sent by a player (Allowed range: 1-3)\n";
						$helpmsg .= TextFormat::GOLD."/realmute amtime <time in minutes> ".TextFormat::WHITE."Set time limit of auto-mute, set 0 to disable\n";
						$helpmsg .= TextFormat::GOLD."/realmute length <number of characters> ".TextFormat::WHITE."Set length limit of chat messages, set 0 to disable\n";
						$sender->sendMessage($helpmsg);
						return true;
					}
					else{
						$helpmsg  = TextFormat::AQUA."[RealMute] Options".TextFormat::WHITE." (Page 3/3)"."\n";
						$helpmsg .= TextFormat::GOLD."/realmute addword <word> ".TextFormat::WHITE."Add a keyword to banned-word list. If you want to match the whole word only, please add an exclamation mark before the word\n";
						$helpmsg .= TextFormat::GOLD."/realmute delword <word> ".TextFormat::WHITE."Delete a keyword from banned-word list\n";
						$helpmsg .= TextFormat::GOLD."/realmute status ".TextFormat::WHITE."View current status of this plugin\n";
						$helpmsg .= TextFormat::GOLD."/realmute list ".TextFormat::WHITE."List muted players\n";
						$helpmsg .= TextFormat::GOLD."/realmute word ".TextFormat::WHITE."Show the banned-word list\n";
						$helpmsg .= TextFormat::GOLD."/realmute about ".TextFormat::WHITE . "Show information about this plugin\n";
						$sender->sendMessage($helpmsg);
						return true;
					}
				}
				if($option == "muteop" || $option == "wordmute" || $option == "banpm" || $option == "banspam" || $option == "bansign"){
					$this->toggle($option, $sender);
					return true;
				}
				if($option == "notify"){
					if(count($args) !== 1){
						$sender->sendMessage("Usage: /realmute notify <on|off|fake>");
						return true;
					}
					switch(array_shift($args)){
						case "on":
							if($this->getConfig()->get("notification") !== true){
								$this->getConfig()->set("notification", true);
								$this->getConfig()->save();
								$sender->sendMessage(TextFormat::GREEN."[RealMute] Muted players will be notified when they are sending messages.");
								return true;
							}
							else{
								$sender->sendMessage(TextFormat::RED."[RealMute] You have already chosen this option.");
								return true;
							}
						case "off":
							if($this->getConfig()->get("notification") !== false){
								$this->getConfig()->set("notification", false);
								$this->getConfig()->save();
								$sender->sendMessage(TextFormat::YELLOW."[RealMute] Muted players will not be notified.");
								return true;
							}
							else{
								$sender->sendMessage(TextFormat::RED."[RealMute] You have already chosen this option.");
								return true;
							}
						case "fake":
							if($this->getConfig()->get("notification") !== "fake"){
								$this->getConfig()->set("notification", "fake");
								$this->getConfig()->save();
								$sender->sendMessage(TextFormat::AQUA."[RealMute] Muted players will see a fake chat message in their client when they send one. The message is still invisible to other players.");
								return true;
							}
							else{
								$sender->sendMessage(TextFormat::RED."[RealMute] You have already chosen this option.");
								return true;
							}
						default:
							$sender->sendMessage("Usage: /realmute notify <on|off|fake>");
							return true;
					}
				}
				if($option == "banlengthy"){
					if(count($args) !== 1){
						$sender->sendMessage("Usage: /realmute banlengthy <mute|slice|off>");
						return true;
					}
					switch(array_shift($args)){
						case "mute":
							if($this->getConfig()->get("banlengthy") !== "mute"){
								$this->getConfig()->set("banlengthy", "mute");
								$this->getConfig()->save();
								$sender->sendMessage(TextFormat::GREEN."[RealMute] Players will be automatically muted if their message exceeds length limit.");
								return true;
							}
							else{
								$sender->sendMessage(TextFormat::RED."[RealMute] You have already chosen this option.");
								return true;
							}
						case "slice":
							if($this->getConfig()->get("banlengthy") !== "slice"){
								$this->getConfig()->set("banlengthy", "slice");
								$this->getConfig()->save();
								$sender->sendMessage(TextFormat::AQUA."[RealMute] If players' message exceeds length limit, the message will be sliced only.");
								return true;
							}
							else{
								$sender->sendMessage(TextFormat::RED."[RealMute] You have already chosen this option.");
								return true;
							}
						case "off":
							if($this->getConfig()->get("banlengthy") !== false){
								$this->getConfig()->set("banlengthy", false);
								$this->getConfig()->save();
								$sender->sendMessage(TextFormat::YELLOW."[RealMute] Players will not be muted if their message is too long, the message will not be sliced.");
								return true;
							}
							else{
								$sender->sendMessage(TextFormat::RED."[RealMute] You have already chosen this option.");
								return true;
							}
						default:
							$sender->sendMessage("Usage: /realmute banlengthy <mute|slice|off>");
							return true;
					}
				}
				if($this->supportcid && $option == "mutedevice"){
					if($this->getConfig()->get("muteidentity") == false){
						$this->getConfig()->set("muteidentity", true);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::GREEN."[RealMute] When muting a username, corresponding device will also be muted.");
						return true;
					}
					else{
						$this->getConfig()->set("muteidentity", false);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::YELLOW."[RealMute] Muted players' devices will not be muted.");
						return true;
					}
				}
				if(!$this->supportcid && $option == "muteip"){
					if($this->getConfig()->get("muteidentity") == false){
						$this->getConfig()->set("muteidentity", true);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::GREEN."[RealMute] When muting a username, corresponding IP will also be muted.");
						return true;
					}
					else{
						$this->getConfig()->set("muteidentity", false);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::YELLOW."[RealMute] Muted players' IPs will not be muted.");
						return true;
					}
				}
				if($option == "spamth"){
					if(count($args) !== 1){
						$sender->sendMessage("Usage: /realmute spamth <time in seconds>\nAllowed range for time: 1-3");
						return true;
					}
					$threshold = intval(array_shift($args));
					if($threshold >= 1 && $threshold <= 3){
						$this->getConfig()->set("spamthreshold", $threshold);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully set spam threshold to ".$threshold." second(s).");
						return true;
					}
					else{
						$sender->sendMessage("Usage: /realmute spamth <time in seconds>\nAllowed range for time: 1-3");
						return true;
					}
				}
				if($option == "amtime"){
					if(count($args) !== 1){
						$sender->sendMessage("Usage: /realmute amtime <time in minutes>\nSet 0 to disable");
						return true;
					}
					$time = intval(array_shift($args));
					if($time > 0){
						$this->getConfig()->set("automutetime", $time);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully set time limit of auto-mute to ".$time." minute(s).");
						return true;
					}
					elseif($time == 0){
						$this->getConfig()->set("automutetime", false);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::YELLOW."[RealMute] Auto-mute will not time-limitedly mute players.");
						return true;
					}
					else{
						$sender->sendMessage("Usage: /realmute amtime <time in minutes>\nSet 0 to disable");
						return true;
					}
				}
				if($option == "length"){
					if(count($args) !== 1){
						$sender->sendMessage("Usage: /realmute length <number of characters>\nSet 0 to disable");
						return true;
					}
					$length = intval(array_shift($args));
					if($length > 0){
						$this->getConfig()->set("lengthlimit", $length);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully set length limit of message to ".$length." character(s).");
						return true;
					}
					elseif($time == 0){
						$this->getConfig()->set("lengthlimit", false);
						$this->getConfig()->save();
						$sender->sendMessage(TextFormat::YELLOW."[RealMute] Message length limit is removed.");
						return true;
					}
					else{
						$sender->sendMessage("Usage: /realmute length <number of characters>\nSet 0 to disable");
						return true;
					}
				}
				if($option == "status"){
					$status = TextFormat::AQUA."[RealMute] Status\n";
					$status .= TextFormat::WHITE."Mute all players: ".$this->isOn("muteall")."\n";
					if($this->getConfig()->get("notification") === false) $status .= TextFormat::WHITE."Notify muted players: ".TextFormat::YELLOW."OFF"."\n";
					elseif($this->getConfig()->get("notification") === "fake") $status .= TextFormat::WHITE."Notify muted players: ".TextFormat::AQUA."Fake message"."\n";
					else $status .= TextFormat::WHITE."Notify muted players: ".TextFormat::GREEN."ON"."\n";
					$status .= TextFormat::WHITE."Exclude OPs when muting all players: ".$this->isOn("excludeop")."\n";
					$status .= TextFormat::WHITE."Auto-mute players if they send banned words: ".$this->isOn("wordmute")."\n";
					$status .= TextFormat::WHITE."Block muted players' private messages: ".$this->isOn("banpm")."\n";
					$status .= TextFormat::WHITE."Auto-mute players if they flood chat screen: ".$this->isOn("banspam")."\n";
					switch($this->getConfig()->get("banlengthy")){
						case "mute":
							$status .= TextFormat::WHITE."Restriction on messages exceeding length limit: ".TextFormat::GREEN."Mute"."\n";
							break;
						case "slice":
							$status .= TextFormat::WHITE."Restriction on messages exceeding length limit: ".TextFormat::AQUA."Slice"."\n";
							break;
						default:
							$status .= TextFormat::WHITE."Restriction on messages exceeding length limit: ".TextFormat::YELLOW."OFF"."\n";
							break;
					}
					$status .= TextFormat::WHITE."Muted players cannot use signs: ".$this->isOn("bansign")."\n";
					if($this->supportcid) $status .= TextFormat::WHITE."Mute devices alongside usernames: ".$this->isOn("muteidentity")."\n";
					else $status .= TextFormat::WHITE."Mute IPs alongside usernames: ".$this->isOn("muteidentity")."\n";
					$status .= TextFormat::WHITE."Spam threshold: ".TextFormat::AQUA.($this->getConfig()->get("spamthreshold"))." second(s)\n";
					if($this->getConfig()->get("automutetime") == false) $status .= TextFormat::WHITE."Time limit of auto-mute: ".$this->isOn("automutetime")."\n";
					else $status .= TextFormat::WHITE."Time limit of auto-mute: ".TextFormat::AQUA.($this->getConfig()->get("automutetime"))." minute(s)\n";
					if($this->getConfig()->get("lengthlimit") == false) $status .= TextFormat::WHITE."Length limit of chat messages: ".$this->isOn("lengthlimit")."\n";
					else $status .= TextFormat::WHITE."Length limit of chat messages: ".TextFormat::AQUA.($this->getConfig()->get("lengthlimit"))." character(s)\n";
					$status .= TextFormat::WHITE."Number of muted players: ".TextFormat::AQUA.(count(explode(",",$this->getConfig()->get("mutedplayers"))) - 1)."\n";
					$status .= TextFormat::WHITE."Number of banned words: ".TextFormat::AQUA.(count(explode(",",$this->getConfig()->get("bannedwords"))) - 1)."\n";
					$sender->sendMessage($status);
					return true;
				}
				if($option == "list"){
					$list = explode(",",$this->getConfig()->get("mutedplayers"));
					array_pop($list);
					$product = array();
					$timelimited = false;
					foreach($list as $player){
						if(is_file($this->getDataFolder()."players/".strtolower($player[0])."/".strtolower($player).".yml")){
							$userconfig = new Config($this->getDataFolder()."players/".strtolower($player[0])."/".strtolower($player).".yml");
							if($userconfig->get("unmutetime") != false){
								$timelimited = true;
								$unmutetime = $userconfig->get("unmutetime");		
								$remaining = (ceil(($unmutetime - time())/60));
								$player = $player."(".$remaining.")";
							}
						}
						$product[] = $player;
					}
					$output = TextFormat::AQUA."[RealMute] Muted players ".TextFormat::WHITE."(".(count(explode(",",$this->getConfig()->get("mutedplayers"))) - 1.).")\n";
					$output .= implode(", ", $product);
					if($timelimited) $output .= "\nNote: If there is a number X in brackets next to a player's name, this player will be unmuted in X minute(s).";
					$sender->sendMessage($output);
					return true;
				}
				if($option == "addword"){
					if(count($args) !== 1){
						$sender->sendMessage("Usage: /realmute addword <word>");
						return true;
					}
					$word = array_shift($args);
					if(stripos($word, ",") !== false){
						$sender->sendMessage(TextFormat::RED."[RealMute] Please do not include comma in the word.");
						return true;
					}
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
						$sender->sendMessage("Usage: /realmute delword <word>");
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
					$list = explode(",",$this->getConfig()->get("bannedwords"));
					array_pop($list);
					$output = TextFormat::AQUA."[RealMute] Banned words ".TextFormat::WHITE."(".(count(explode(",",$this->getConfig()->get("bannedwords"))) - 1.).")\n";
					$output .= implode(", ", $list);
					$output .= "\nNote: If a word begins with the exclamation mark, it will only be blocked if player sends it as an individual word.";
					$sender->sendMessage($output);
					return true;
				}
				if($option == "about"){
					$aboutmsg = TextFormat::AQUA."[RealMute] Version ".$this->getDescription()->getVersion()."\n";
					$aboutmsg .= "RealMute is a chat management plugin with many extra features.\n";
					$aboutmsg .= "Copyright (C) 2016, 2017 Leo3418 (https://github.com/Leo3418)\n";
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
				if(count($args) !== 1 && count($args) !== 2){
					$sender->sendMessage("Usage: ".$command->getUsage());
					return true;
				}
				$name = array_shift($args);
				if($this->getServer()->getPlayer($name) instanceof Player) $name = $this->getServer()->getPlayer($name)->getName();
				if(!$this->inList("mutedplayers", $name)){	
					if(count($args) == 1){
						$time = intval(array_shift($args));
						if($time > 0){
							$this->tmMute($name, $time);
							$sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully muted ".$name." for ".$time." minute(s).");
						}
						else{
							$sender->sendMessage("Usage: ".$command->getUsage());
							return true;
						}
					}
					else $sender->sendMessage(TextFormat::GREEN."[RealMute] Successfully muted ".$name.".");
					$this->add("mutedplayers", $name);
					$this->addIdentity($name);
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
				if($this->getServer()->getPlayer($name) instanceof Player) $name = $this->getServer()->getPlayer($name)->getName();
				if($this->inList("mutedplayers", $name)){
					$this->remove("mutedplayers", $name);
					$this->removeIdentity($name);
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
				if($this->getConfig()->get("muteall")){
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
		$mutedidentity = $this->identity->getAll(true);
		$userconfig = new Config($this->getDataFolder()."players/".strtolower($player[0])."/".strtolower($player).".yml");
		$useridentity = $userconfig->get("identity");
		if($this->getConfig()->get("muteall")){
			if($this->getConfig()->get("excludeop") && $event->getPlayer()->hasPermission("realmute.muteignored")) return true;
			else{
				$event->setCancelled(true);
				if($this->getConfig()->get("notification")) $event->getPlayer()->sendMessage(TextFormat::RED."Administrator has muted all players in chat.");
				return true;
			}
		}
		elseif((!$this->inList("mutedplayers", $player) && !in_array($useridentity, $mutedidentity)) && $this->lastmsgsender == $player && time() - $this->lastmsgtime <= ($this->getConfig()->get("spamthreshold"))){
			if($this->consecutivemsg < 2){
				$this->lastmsgsender = $player;
				$this->lastmsgtime = time();
				++$this->consecutivemsg;
				return true;
			}
			$event->setCancelled(true);
			if($this->getConfig()->get("banspam")){
				$this->add("mutedplayers", $player);
				if($this->getConfig()->get("automutetime") !== false) $this->tmMute($player, $this->getConfig()->get("automutetime"));
				if($this->getConfig()->get("notification")) $event->getPlayer()->sendMessage(TextFormat::RED."Because you are flooding the chat screen, you are now muted in chat.");
				$this->getLogger()->notice($player." flooded the chat screen and has been muted automatically.");
			}
			$event->getPlayer()->sendMessage(TextFormat::RED."Do not flood the chat screen.");
			$this->lastmsgsender = $player;
			$this->lastmsgtime = time();
			return true;
		}
		elseif($this->inList("mutedplayers", $player) || ($this->getConfig()->get("muteidentity") && in_array($useridentity, $mutedidentity))){
			$userconfig = new Config($this->getDataFolder()."players/".strtolower($player[0])."/".strtolower($player).".yml");
			if($userconfig->get("unmutetime") != false){
				$unmutetime = $userconfig->get("unmutetime");
				$event->setCancelled(true);
				if($this->getConfig()->get("notification")) $event->getPlayer()->sendMessage(TextFormat::RED."You have been muted in chat. You will be unmuted in ".(ceil(($unmutetime - time())/60))." minute(s).");
				return true;
			}
			$event->setCancelled(true);
			if($this->getConfig()->get("notification")) $event->getPlayer()->sendMessage(TextFormat::RED."You have been muted in chat.");
			return true;
		}
		foreach(explode(",",$this->getConfig()->get("bannedwords")) as $bannedword){
			if(strlen($bannedword)!== 0 && $bannedword[0] == "!"){
				$bannedword = substr($bannedword, 1);
				foreach(explode(" ",$message) as $word){
					if(strcmp(strtolower($word), $bannedword) == 0){
						$event->setCancelled(true);
						if($this->getConfig()->get("wordmute")){
							if($this->getConfig()->get("notification")) $event->getPlayer()->sendMessage(TextFormat::RED."Your message contains banned word set by administrator. You are now muted in chat.");
							else $event->getPlayer()->sendMessage(TextFormat::RED."Your message contains banned word set by administrator.");
							$this->add("mutedplayers", $player);
							$this->addIdentity($player);
							if($this->getConfig()->get("automutetime") !== false) $this->tmMute($player, $this->getConfig()->get("automutetime"));
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
					if($this->getConfig()->get("wordmute")){
						if($this->getConfig()->get("notification")) $event->getPlayer()->sendMessage(TextFormat::RED."Your message contains banned word set by administrator. You are now muted in chat.");
						else $event->getPlayer()->sendMessage(TextFormat::RED."Your message contains banned word set by administrator.");
						$this->add("mutedplayers", $player);
						$this->addIdentity($player);
						if($this->getConfig()->get("automutetime") !== false) $this->tmMute($player, $this->getConfig()->get("automutetime"));
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
		if($this->getConfig()->get("lengthlimit") !== false && mb_strlen($message, "UTF8") > $this->getConfig()->get("lengthlimit")){
			if($this->getConfig()->get("banlengthy") == "mute"){
				$event->setCancelled(true);
				if($this->getConfig()->get("notification")) $event->getPlayer()->sendMessage(TextFormat::RED."Your message exceeds length limit set by administrator. You are now muted in chat.");
				else $event->getPlayer()->sendMessage(TextFormat::RED."Your message exceeds length limit set by administrator.");
				$this->add("mutedplayers", $player);
				$this->addIdentity($player);
				if($this->getConfig()->get("automutetime") !== false) $this->tmMute($player, $this->getConfig()->get("automutetime"));
				$this->getLogger()->notice($player." sent lengthy message in chat and has been muted automatically.");
				return true;
			}
			elseif($this->getConfig()->get("banlengthy") == "slice"){
				$event->setMessage(mb_substr($message, 0, $this->getConfig()->get("lengthlimit"), "UTF8"));
				$event->getPlayer()->sendMessage(TextFormat::RED."Your message exceeds length limit set by administrator and has been sliced.");
			}
		}
		$this->lastmsgsender = $player;
		$this->lastmsgtime = time();
		$this->consecutivemsg = 1;
	}
	public function onPlayerCommand(PlayerCommandPreprocessEvent $event){
		$player = $event->getPlayer()->getName();
		$command = strtolower($event->getMessage());
		$mutedidentity = $this->identity->getAll(true);
		$userconfig = new Config($this->getDataFolder()."players/".strtolower($player[0])."/".strtolower($player).".yml");
		$useridentity = $userconfig->get("identity");
		if($this->getConfig()->get("banpm") && ($this->inList("mutedplayers", $player) || ($this->getConfig()->get("muteidentity") && in_array($useridentity, $mutedidentity))) && (substr($command, 0, 6) == "/tell " || substr($command, 0, 5) == "/msg " || substr($command, 0, 3) == "/m " || substr($command, 0, 9) == "/whisper " || (!$this->getServer()->getPluginManager()->getPlugin("SWorld") && substr($command, 0, 3) == "/w "))){
			$event->setCancelled(true);
			if($this->getConfig()->get("notification")) $event->getPlayer()->sendMessage(TextFormat::RED."You are not allowed to send private messages until you get unmuted in chat.");
			return true;
		}
	}
	public function onPlaceEvent(BlockPlaceEvent $event){
		$player = $event->getPlayer()->getName();
		$mutedidentity = $this->identity->getAll(true);
		$userconfig = new Config($this->getDataFolder()."players/".strtolower($player[0])."/".strtolower($player).".yml");
		$useridentity = $userconfig->get("identity");
		if($this->getConfig()->get("bansign") && ($this->inList("mutedplayers", $player) || ($this->getConfig()->get("muteidentity") && in_array($useridentity, $mutedidentity))) && ($event->getBlock()->getID() == 323 || $event->getBlock()->getID() == 63 || $event->getBlock()->getID() == 68)){
			$event->setCancelled(true);
			if($this->getConfig()->get("notification")) $event->getPlayer()->sendMessage(TextFormat::RED."You are not allowed to use signs until you get unmuted in chat.");
			return true;
		}
	}
	public function inList($opt, $target){
		foreach((explode(",",$this->getConfig()->get($opt))) as $item){
			if(strcmp(strtolower($target), $item) == 0){
				return true;
				break;
			}
		}
		return false;
	}
	public function add($opt, $target){
		if(count(explode(",",$this->getConfig()->get($opt))) == 1) $this->getConfig()->set($opt, strtolower($target).",");
		else $this->getConfig()->set($opt, $this->getConfig()->get($opt).strtolower($target).",");
		$this->getConfig()->save();
	}
	public function remove($opt, $target){
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
				++$count;
				if(strcmp($count, substr_count($this->getConfig()->get($opt), ",")) == 0) $newlist .= $item;
				else $newlist .= $item.",";
			}
		}
		$this->getConfig()->set($opt, $newlist);
		$this->getConfig()->save();
		if($opt == "mutedplayers" && is_file($this->getDataFolder()."players/".strtolower($target[0])."/".strtolower($target).".yml")){
			$time = new Config($this->getDataFolder()."players/".strtolower($target[0])."/".strtolower($target).".yml");
			$time->remove("unmutetime");
			$time->save();
		}
	}
	public function addIdentity($player){
		if(is_file($this->getDataFolder()."players/".strtolower($player[0])."/".strtolower($player).".yml") && $this->getConfig()->get("muteidentity")){
			$userconfig = new Config($this->getDataFolder()."players/".strtolower($player[0])."/".strtolower($player).".yml");
			$useridentity = $userconfig->get("identity");
			$this->identity->set($useridentity);
			$this->identity->save();
		}
	}
	public function removeIdentity($player){
		if($this->getConfig()->get("muteidentity")){
			$userconfig = new Config($this->getDataFolder()."players/".strtolower($player[0])."/".strtolower($player).".yml");
			$useridentity = $userconfig->get("identity");
			$this->identity->remove($useridentity);
			$this->identity->save();
		}
	}
	public function isOn($opt){
		if($this->getConfig()->get($opt)) $text = TextFormat::GREEN."ON";
		else $text = TextFormat::YELLOW."OFF";
		return $text;
	}
	public function tmMute($name, $time){
		$now = time();
		$unmutetime = $now + $time * 60;
		if(!is_dir($this->getDataFolder()."players/".strtolower($name[0]))) mkdir($this->getDataFolder()."players/".strtolower($name[0]), 0777, true);
		$userconfig = new Config($this->getDataFolder()."players/".strtolower($name[0])."/".strtolower($name).".yml", CONFIG::YAML);
		$userconfig->set("unmutetime", $unmutetime);
		$userconfig->save();
		return true;
	}
	public function toggle($option, $sender){
		switch($option){
			case "muteop":
				$flag = "excludeop";
				$turnonmsg = "When muting all players, OPs will be excluded.";
				$turnoffmsg = "OPs will be muted with all players.";
				break;
			case "wordmute":
				$flag = "wordmute";
				$turnonmsg = "Players will be automatically muted if they send banned words.";
				$turnoffmsg = "Players will not muted if they send banned words.";
				break;
			case "banpm":
				$flag = "banpm";
				$turnonmsg = "Private messages sent by muted players will be blocked.";
				$turnoffmsg = "Players can send private messages when they are muted in chat.";
				break;
			case "banspam":
				$flag = "banspam";
				$turnonmsg = "Players will be automatically muted if they flood the chat screen.";
				$turnoffmsg = "Players will not muted if they flood the chat screen.";
				break;
			case "bansign":
				$flag = "bansign";
				$turnonmsg = "Muted players cannot use signs.";
				$turnoffmsg = "Muted players are allowed to use signs.";
				break;
		}
		if($this->getConfig()->get($flag) == false){
			$this->getConfig()->set($flag, true);
			$this->getConfig()->save();
			$sender->sendMessage(TextFormat::GREEN."[RealMute] ".$turnonmsg);
			return true;
		}
		else{
			$this->getConfig()->set($flag, false);
			$this->getConfig()->save();
			$sender->sendMessage(TextFormat::YELLOW."[RealMute] ".$turnoffmsg);
			return true;
		}
	}
	public function isMuted($player){
		if($player instanceof Player) $name = $player->getName();
		elseif(gettype($player) != "object") $name = $player;
		else return false;
		$mutedidentity = $this->identity->getAll(true);
		if(is_file($this->getDataFolder()."players/".strtolower($name[0])."/".strtolower($name).".yml")){
			$userconfig = new Config($this->getDataFolder()."players/".strtolower($name[0])."/".strtolower($name).".yml");
			$useridentity = $userconfig->get("identity");
			if($this->getConfig()->get("muteidentity") && in_array($useridentity, $mutedidentity)) return true;
		}
		return $this->inList("mutedplayers", $name);
	}
	public function mutePlayer($player){
		if($player instanceof Player) $name = $player->getName();
		elseif(gettype($player) != "object") $name = $player;
		else return false;
		if(!$this->inList("mutedplayers", $name)){
			$this->add("mutedplayers", $name);
			$this->addIdentity($name);
			return true;
		}
		return false;
	}
	public function unmutePlayer($player){
		if($player instanceof Player) $name = $player->getName();
		elseif(gettype($player) != "object") $name = $player;
		else return false;
		if($this->inList("mutedplayers", $name)){
			$this->remove("mutedplayers", $name);
			$this->removeIdentity($name);
			return true;
		}
		return false;
	}
}
?>