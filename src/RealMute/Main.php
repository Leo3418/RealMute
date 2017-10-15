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
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\TranslationContainer;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener
{
    /** @var bool whether the current PocketMine-MP API version supports CID */
    private $supportCid;

    /** @var Config configuration file storing a list of muted identities */
    private $mutedIdentities;

    // These variables store information of the last chat message
    private $lastMsgSender = "";
    private $lastMsgTime = "";
    private $consecutiveMsg = 1;

    public function onEnable()
    {
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->notice("Copyright (C) 2016, 2017 Leo3418");
        $this->getLogger()->notice("RealMute is free software licensed under " .
            "GNU GPLv3 with the absence of any warranty");

        // Creates configuration folders
        if (!is_dir($this->getDataFolder())) {
            mkdir($this->getDataFolder());
        }
        if (!is_dir($this->getDataFolder() . "players")) {
            mkdir($this->getDataFolder() . "players", 0777, true);
        }

        $defaultConfig = array(
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
            "spamthreshold" => false,
            "automutetime" => false,
            "lengthlimit" => false,
            "mutedplayers" => "",
            "bannedwords" => "",
        );

        // Configuration version checker
        if (file_exists($this->getDataFolder() . "config.yml") &&
            strcmp("4", $this->getConfig()->get("version")[0]) < 0) {
            copy($this->getDataFolder() . "config.yml",
                $this->getDataFolder() . "config.bak");
            $this->getConfig()->setAll($defaultConfig);
            $this->getLogger()->warning("Your config.yml is for a higher " .
                "version of RealMute.");
            $this->getLogger()->warning("config.yml has been downgraded to " .
                "version 4.x. Old file was renamed to config.bak.");
        }
        // Checks whether the configuration file is created by RealMute v2.x.x
        $isVer2 = false;
        if (file_exists($this->getDataFolder() . "config.yml") && 
            strcmp("2", $this->getConfig()->get("version")[0]) == 0) {
            $isVer2 = true;
        }
        // Changes configuration file version to current version number
        if (file_exists($this->getDataFolder() . "config.yml") &&
            strcmp($this->getConfig()->get("version"),
                $this->getDescription()->getVersion()) !== 0) {
            $this->getConfig()->set("version",
                $this->getDescription()->getVersion());
        }
        // Configuration auto-updater for file created by RealMute v1.x.x
        if (file_exists($this->getDataFolder() . "config.yml")) {
            $config = fopen($this->getDataFolder() . "config.yml", "r");
            $isVer1 = false;
            $copied = false; // Has the old file been backed-up
            while (!feof($config)) {
                $line = fgets($config);
                // Checks whether the configuration file is created by
                // RealMute v1.x.x
                if (strpos($line, ".mute")) {
                    $isVer1 = true;
                    while (!$copied) {
                        copy($this->getDataFolder() . "config.yml",
                            $this->getDataFolder() . "config.bak");
                        $copied = true;
                    }
                    // Converts configuration file
                    $i = strrpos($line, ".mute");
                    $name = substr($line, 0, $i);
                    $this->getConfig()->remove($name . ".mute");
                    $this->add("mutedplayers", $name);
                }
            }
            if ($isVer1) {
                $this->getLogger()->info("An old version of config.yml detected. " .
                    "Old file was renamed to config.bak.");
                $this->getLogger()->info("Your config.yml has been updated " .
                    "so that it is compatible with version 3.x!");
            }
            if ($isVer2) {
                $this->getLogger()->warning("If you want to downgrade RealMute " .
                    "back to version 2.x, please make sure you use v2.7.5 or v2.0.x-2.3.x.");
                $this->getLogger()->warning("You can download version 2.7.5 at " .
                    "https://github.com/Leo3418/RealMute/releases/tag/v2.7.5");
            }
        }

        // Registers plugin configuration file
        new Config($this->getDataFolder() . "config.yml", Config::YAML,
            $defaultConfig);
        $this->getConfig()->save();

        // Checks whether the current PocketMine-MP API version supports CID
        $this->supportCid = (strcmp("1", 
                $this->getServer()->getApiVersion()[0]) != 0);

        // Registers muted identity list as a configuration file
        $this->mutedIdentities = new Config($this->getDataFolder() .
            "identity.txt", Config::ENUM);
        $this->mutedIdentities->save();

        // Sets up time checking task
        $checkTimeTask = new CheckTime($this);
        $handler = $this->getServer()->getScheduler()->scheduleRepeatingTask(
            $checkTimeTask, 20);
        $checkTimeTask->setHandler($handler);
    }

    public function onDisable()
    {
        // Writes configuration files to disk
        $this->getConfig()->save();
        $this->mutedIdentities->save();
    }

    public function onJoin(PlayerJoinEvent $event)
    {
        $player = $event->getPlayer()->getName();
        // Creates user configuration for the player if they do not have one
        if (!is_dir($this->getDataFolder() . "players/" .
            strtolower($player[0]))) {
            mkdir($this->getDataFolder() . "players/" .
                strtolower($player[0]), 0777, true);
        }
        $userConfig = new Config($this->getDataFolder() . "players/" .
            strtolower($player[0]) . "/" . strtolower($player) . ".yml",
            Config::YAML);
        // Writes the player's identity to their user configuration
        if ($this->supportCid) {
            $userConfig->set("identity", 
                strval($event->getPlayer()->getClientId()));
        } else {
            $userConfig->set("identity", 
                strval($event->getPlayer()->getAddress()));
        }
        $userConfig->save();
    }

    public function onCommand(CommandSender $sender, Command $command,
                              string $label, array $args): bool
    {
        switch ($command->getName()) {
            case "realmute":
                if (count($args) != 1 && count($args) != 2) {
                    $sender->sendMessage("Usage: " . $command->getUsage());
                    break;
                }
                $option = array_shift($args);
                switch ($option) {
                    case "help":
                        if (count($args) != 1 || 
                            (count($args) == 1 && $args[0] == 1)) {
                            $helpMsg = TextFormat::AQUA . "[RealMute] Options" . 
                                TextFormat::WHITE . " (Page 1/3)" . "\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute help [:page] " .
                                TextFormat::WHITE . "Jump to another page of Help\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute notify [on|off|fake] " .
                                TextFormat::WHITE . "Toggle notification to muted players, " .
                                "or show a fake chat message to muted players\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute muteop " . 
                                TextFormat::WHITE . "When muting all players, include/exclude OPs\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute wordmute " . 
                                TextFormat::WHITE . "Turn on/off auto-muting players if they send banned words\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute banpm " . 
                                TextFormat::WHITE . "Turn on/off blocking muted players' private messages\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute banspam " . 
                                TextFormat::WHITE . "Turn on/off auto-muting players if they flood the chat screen\n";
                            $sender->sendMessage($helpMsg);
                        } elseif (count($args) == 1 && $args[0] == 2) {
                            $helpMsg = TextFormat::AQUA . "[RealMute] Options" . 
                                TextFormat::WHITE . " (Page 2/3)" . "\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute banlengthy [mute|slice|off] " . 
                                TextFormat::WHITE . "Mute/Slice/Allow messages exceeding the length limit\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute bansign " . 
                                TextFormat::WHITE . "Allow/Disallow muted players to use signs\n";
                            if ($this->supportCid) {
                                $helpMsg .= TextFormat::GOLD . "/realmute muteidentity " . 
                                    TextFormat::WHITE . "Turn on/off muting players' devices alongside user names\n";
                            } else {
                                $helpMsg .= TextFormat::GOLD . "/realmute muteidentity " . 
                                    TextFormat::WHITE . "Turn on/off muting players' IPs alongside user names\n";
                            }
                            $helpMsg .= TextFormat::GOLD . "/realmute spamth [time in seconds] " . 
                                TextFormat::WHITE . "Set minimum interval allowed " .
                                "between two messages sent by a player (Allowed range: 1-3), set 0 to disable\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute amtime [time in minutes] " . 
                                TextFormat::WHITE . "Set time limit of auto-mute, set 0 to disable\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute length [number of characters] " . 
                                TextFormat::WHITE . "Set length limit of chat messages, set 0 to disable\n";
                            $sender->sendMessage($helpMsg);
                        } else {
                            $helpMsg = TextFormat::AQUA . "[RealMute] Options" . 
                                TextFormat::WHITE . " (Page 3/3)" . "\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute addword [word] " . 
                                TextFormat::WHITE . "Add a keyword to banned-word list. " .
                                "If you want to match the whole word only, " .
                                "please add an exclamation mark before the word\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute delword [word] " . 
                                TextFormat::WHITE . "Remove a keyword from banned-word list\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute status " . 
                                TextFormat::WHITE . "View current status of this plugin\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute list " . 
                                TextFormat::WHITE . "List muted players\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute word " . 
                                TextFormat::WHITE . "Show the banned-word list\n";
                            $helpMsg .= TextFormat::GOLD . "/realmute about " . 
                                TextFormat::WHITE . "Show information about this plugin\n";
                            $sender->sendMessage($helpMsg);
                        }
                        break;

                    case "muteop":
                    case "wordmute":
                    case "banpm":
                    case "banspam":
                    case "bansign":
                        $this->toggle($option, $sender);
                        break;

                    case "notify":
                        if (count($args) != 1) {
                            $sender->sendMessage("Usage: /realmute notify " .
                                "[on|off|fake]");
                            break;
                        }
                        switch (array_shift($args)) {
                            case "on":
                                if ($this->getConfig()->get("notification") !== true) {
                                    $this->getConfig()->set("notification", true);
                                    $this->getConfig()->save();
                                    $sender->sendMessage(TextFormat::GREEN .
                                        "[RealMute] Muted players will be " .
                                        "notified when they are sending messages.");
                                } else {
                                    $sender->sendMessage(TextFormat::RED .
                                        "[RealMute] You have already chosen " .
                                        "this option.");
                                }
                                break;
                            case "off":
                                if ($this->getConfig()->get("notification") !== false) {
                                    $this->getConfig()->set("notification", false);
                                    $this->getConfig()->save();
                                    $sender->sendMessage(TextFormat::YELLOW .
                                        "[RealMute] Muted players will not " .
                                        "be notified.");
                                } else {
                                    $sender->sendMessage(TextFormat::RED .
                                        "[RealMute] You have already chosen " .
                                        "this option.");
                                }
                                break;
                            case "fake":
                                if ($this->getConfig()->get("notification") !== "fake") {
                                    $this->getConfig()->set("notification", "fake");
                                    $this->getConfig()->save();
                                    $sender->sendMessage(TextFormat::AQUA .
                                        "[RealMute] Muted players will see a " .
                                        "fake chat message in their client when " .
                                        "they send one. The message is still " .
                                        "invisible to other players.");
                                } else {
                                    $sender->sendMessage(TextFormat::RED .
                                        "[RealMute] You have already chosen " .
                                        "this option.");
                                }
                                break;
                            default:
                                $sender->sendMessage("Usage: /realmute notify ".
                                    "[on|off|fake]");
                        }
                        break;

                    case "banlengthy":
                        if (count($args) != 1) {
                            $sender->sendMessage("Usage: /realmute banlengthy " .
                                "[mute|slice|off]");
                            break;
                        }
                        switch (array_shift($args)) {
                            case "mute":
                                if ($this->getConfig()->get("banlengthy") !== "mute") {
                                    $this->getConfig()->set("banlengthy", "mute");
                                    $this->getConfig()->save();
                                    $sender->sendMessage(TextFormat::GREEN .
                                        "[RealMute] Players will be " .
                                        "automatically muted if their message exceeds length limit.");
                                } else {
                                    $sender->sendMessage(TextFormat::RED .
                                        "[RealMute] You have already chosen " .
                                        "this option.");
                                }
                                break;
                            case "slice":
                                if ($this->getConfig()->get("banlengthy") !== "slice") {
                                    $this->getConfig()->set("banlengthy", "slice");
                                    $this->getConfig()->save();
                                    $sender->sendMessage(TextFormat::AQUA .
                                        "[RealMute] If players' message " .
                                        "exceeds length limit, the message " .
                                        "will be sliced only.");
                                } else {
                                    $sender->sendMessage(TextFormat::RED .
                                        "[RealMute] You have already chosen " .
                                        "this option.");
                                }
                                break;
                            case "off":
                                if ($this->getConfig()->get("banlengthy") !== false) {
                                    $this->getConfig()->set("banlengthy", false);
                                    $this->getConfig()->save();
                                    $sender->sendMessage(TextFormat::YELLOW .
                                        "[RealMute] Players will not be muted " .
                                        "if their message is too long, the message will not be sliced.");
                                } else {
                                    $sender->sendMessage(TextFormat::RED .
                                        "[RealMute] You have already chosen " .
                                        "this option.");
                                }
                                break;
                            default:
                                $sender->sendMessage("Usage: /realmute banlengthy " .
                                    "[mute|slice|off]");
                        }
                        break;

                    case "muteidentity":
                        if ($this->getConfig()->get("muteidentity") == false) {
                            $this->getConfig()->set("muteidentity", true);
                            $this->getConfig()->save();
                            if ($this->supportCid) {
                                $sender->sendMessage(TextFormat::GREEN .
                                    "[RealMute] When muting a username, " .
                                    "corresponding device will also be muted.");
                            } else {
                                $sender->sendMessage(TextFormat::GREEN .
                                    "[RealMute] When muting a username, " .
                                    "corresponding IP will also be muted.");
                            }
                        } else {
                            $this->getConfig()->set("muteidentity", false);
                            $this->getConfig()->save();
                            if ($this->supportCid) {
                                $sender->sendMessage(TextFormat::YELLOW .
                                    "[RealMute] Muted players' devices will " .
                                    "not be muted.");
                            } else {
                                $sender->sendMessage(TextFormat::YELLOW .
                                    "[RealMute] Muted players' IPs will not " .
                                    "be muted.");
                            }
                        }
                        break;

                    case "spamth":
                        if (count($args) != 1) {
                            $sender->sendMessage("Usage: /realmute spamth [time in seconds]\n" .
                                "Allowed range for time: 1-3\n" .
                                "Set 0 to disable");
                            break;
                        }
                        $threshold = intval(array_shift($args));
                        if ($threshold >= 1 && $threshold <= 3) {
                            $this->getConfig()->set("spamthreshold", $threshold);
                            $this->getConfig()->save();
                            $sender->sendMessage(TextFormat::GREEN .
                                "[RealMute] Successfully set spam threshold to " .
                                $threshold . " second(s).");
                        } elseif ($threshold == 0) {
                            $this->getConfig()->set("spamthreshold", false);
                            $this->getConfig()->save();
                            $sender->sendMessage(TextFormat::YELLOW .
                                "[RealMute] Chat flooding blocking has " .
                                "been disabled.");
                        } else {
                            $sender->sendMessage("Usage: /realmute spamth [time in seconds]\n" .
                                "Allowed range for time: 1-3\n" .
                                "Set 0 to disable");
                        }
                        break;

                    case "amtime":
                        if (count($args) != 1) {
                            $sender->sendMessage("Usage: /realmute amtime [time in minutes]\n" .
                                "Set 0 to disable");
                            break;
                        }
                        $time = intval(array_shift($args));
                        if ($time > 0) {
                            $this->getConfig()->set("automutetime", $time);
                            $this->getConfig()->save();
                            $sender->sendMessage(TextFormat::GREEN .
                                "[RealMute] Successfully set time limit of " .
                                "auto-mute to " . $time . " minute(s).");
                        } elseif ($time == 0) {
                            $this->getConfig()->set("automutetime", false);
                            $this->getConfig()->save();
                            $sender->sendMessage(TextFormat::YELLOW .
                                "[RealMute] Auto-mute will not " .
                                "time-limitedly mute players.");
                        } else {
                            $sender->sendMessage("Usage: /realmute amtime [time in minutes]\n" .
                                "Set 0 to disable");
                        }
                        break;

                    case "length":
                        if (count($args) != 1) {
                            $sender->sendMessage("Usage: /realmute length [number of characters]\n" .
                                "Set 0 to disable");
                            break;
                        }
                        $length = intval(array_shift($args));
                        if ($length > 0) {
                            $this->getConfig()->set("lengthlimit", $length);
                            $this->getConfig()->save();
                            $sender->sendMessage(TextFormat::GREEN .
                                "[RealMute] Successfully set length limit of " .
                                "message to " . $length . " character(s).");
                        } elseif ($length == 0) {
                            $this->getConfig()->set("lengthlimit", false);
                            $this->getConfig()->save();
                            $sender->sendMessage(TextFormat::YELLOW .
                                "[RealMute] Message length limit is removed.");
                        } else {
                            $sender->sendMessage("Usage: /realmute length [number of characters]\n" .
                                "Set 0 to disable");
                        }
                        break;

                    case "status":
                        $status = TextFormat::AQUA . "[RealMute] Status\n";
                        $status .= TextFormat::WHITE . "Mute all players: " .
                            $this->isOn("muteall") . "\n";
                        $status .= TextFormat::WHITE . "Notify muted players: ";
                        // Not using `if (!$this->getConfig()->get("notification"))`
                        // because "notification" can be a string
                        if ($this->getConfig()->get("notification") === false) {
                            $status .= TextFormat::YELLOW . "OFF" . "\n";
                        } elseif ($this->getConfig()->get("notification") === "fake") {
                            $status .= TextFormat::AQUA . "Fake message" . "\n";
                        } else {
                            $status .= TextFormat::GREEN . "ON" . "\n";
                        }
                        $status .= TextFormat::WHITE . "Exclude OPs when muting all players: " .
                            $this->isOn("excludeop") . "\n";
                        $status .= TextFormat::WHITE . "Auto-mute players if they send banned words: " .
                            $this->isOn("wordmute") . "\n";
                        $status .= TextFormat::WHITE . "Block muted players' private messages: " .
                            $this->isOn("banpm") . "\n";
                        $status .= TextFormat::WHITE . "Auto-mute players if they flood chat screen: " .
                            $this->isOn("banspam") . "\n";
                        $status .= TextFormat::WHITE . "Restriction on messages exceeding length limit: ";
                        switch ($this->getConfig()->get("banlengthy")) {
                            case "mute":
                                $status .= TextFormat::GREEN . "Mute" . "\n";
                                break;
                            case "slice":
                                $status .= TextFormat::AQUA . "Slice" . "\n";
                                break;
                            default:
                                $status .= TextFormat::YELLOW . "OFF" . "\n";
                                break;
                        }
                        $status .= TextFormat::WHITE . "Muted players cannot use signs: " .
                            $this->isOn("bansign") . "\n";
                        if ($this->supportCid) {
                            $status .= TextFormat::WHITE . "Mute devices alongside user names: ";
                        } else {
                            $status .= TextFormat::WHITE . "Mute IPs alongside user names: ";
                        }
                        $status .= $this->isOn("muteidentity") . "\n";
                        $status .= TextFormat::WHITE . "Spam threshold: ";
                        // Not using `if (!$this->getConfig()->get("spamthreshold"))`
                        // because "spamthreshold" can be an integer
                        if ($this->getConfig()->get("spamthreshold") === false) {
                            $status .= $this->isOn("spamthreshold") . "\n";
                        } else {
                            $status .= TextFormat::AQUA .
                                ($this->getConfig()->get("spamthreshold")) . " second(s)\n";
                        }
                        $status .= TextFormat::WHITE . "Time limit of auto-mute: ";
                        // Not using `if (!$this->getConfig()->get("automutetime"))`
                        // because "automutetime" can be an integer
                        if ($this->getConfig()->get("automutetime") === false) {
                            $status .= $this->isOn("automutetime") . "\n";
                        } else {
                            $status .= TextFormat::AQUA .
                                ($this->getConfig()->get("automutetime")) . " minute(s)\n";
                        }
                        $status .= TextFormat::WHITE . "Length limit of chat messages: ";
                        // Not using `if (!$this->getConfig()->get("lengthlimit"))`
                        // because "lengthlimit" can be an integer
                        if ($this->getConfig()->get("lengthlimit") === false) {
                            $status .= $this->isOn("lengthlimit") . "\n";
                        } else {
                            $status .= TextFormat::AQUA .
                                ($this->getConfig()->get("lengthlimit")) . " character(s)\n";
                        }
                        $status .= TextFormat::WHITE . "Number of muted players: " .
                            TextFormat::AQUA . (count(explode(",",
                                    $this->getConfig()->get("mutedplayers"))) - 1) . "\n";
                        $status .= TextFormat::WHITE . "Number of banned words: " .
                            TextFormat::AQUA . (count(explode(",",
                                    $this->getConfig()->get("bannedwords"))) - 1) . "\n";
                        $sender->sendMessage($status);
                        break;

                    case "list":
                        $list = explode(",", $this->getConfig()->get("mutedplayers"));
                        array_pop($list);
                        // An array of players muted for limited time
                        $product = array();
                        // A boolean storing whether any player is muted for
                        // limited time
                        $timeLimited = false;
                        foreach ($list as $player) {
                            if (is_file($this->getDataFolder() .
                                "players/" . strtolower($player[0]) . "/" .
                                strtolower($player) . ".yml")) {
                                $userConfig = new Config($this->getDataFolder() .
                                    "players/" . strtolower($player[0]) . "/" .
                                    strtolower($player) . ".yml");
                                if ($userConfig->get("unmuteTime") != false) {
                                    $timeLimited = true;
                                    $unmuteTime = $userConfig->get("unmuteTime");
                                    $remaining = (ceil(($unmuteTime - time()) / 60));
                                    $player = $player . "(" . $remaining . ")";
                                }
                            }
                            $product[] = $player;
                        }
                        $output = TextFormat::AQUA . "[RealMute] Muted players " .
                            TextFormat::WHITE . "(" . (count(explode(",",
                                    $this->getConfig()->get("mutedplayers"))) - 1.) . ")\n";
                        $output .= implode(", ", $product);
                        if ($timeLimited) {
                            $output .= "\nNote: If there is a number X in " .
                                "brackets next to a player's name, this player " .
                                "will be unmuted in X minute(s).";
                        }
                        $sender->sendMessage($output);
                        break;

                    case "addword":
                        if (count($args) != 1) {
                            $sender->sendMessage("Usage: /realmute addword [word]");
                            break;
                        }
                        $word = array_shift($args);
                        if (stripos($word, ",") != false) {
                            $sender->sendMessage(TextFormat::RED .
                                "[RealMute] Please do not include comma in the word.");
                            break;
                        }
                        if (!$this->inList("bannedwords", $word)) {
                            $this->add("bannedwords", $word);
                            $sender->sendMessage(TextFormat::GREEN .
                                "[RealMute] Successfully added " . $word .
                                " to banned-word list.");
                        } else {
                            $sender->sendMessage(TextFormat::RED .
                                "[RealMute] " . $word . " has been already " .
                                "added to banned-word list.");
                        }
                        break;

                    case "delword":
                        if (count($args) != 1) {
                            $sender->sendMessage("Usage: /realmute delword [word]");
                            break;
                        }
                        $word = array_shift($args);
                        if ($this->inList("bannedwords", $word)) {
                            $this->remove("bannedwords", $word);
                            $sender->sendMessage(TextFormat::GREEN .
                                "[RealMute] Successfully deleted " . $word .
                                " from banned-word list.");
                        } else {
                            $sender->sendMessage(TextFormat::RED .
                                "[RealMute] " . $word . " is not in the banned-word list.");
                        }
                        break;

                    case "word":
                        $list = explode(",", $this->getConfig()->get("bannedwords"));
                        array_pop($list);
                        $output = TextFormat::AQUA . "[RealMute] Banned words " .
                            TextFormat::WHITE . "(" . (count(explode(",",
                                    $this->getConfig()->get("bannedwords"))) - 1.) . ")\n";
                        $output .= implode(", ", $list);
                        $output .= "\nNote: If a word begins with an " .
                            "exclamation mark, it will only be blocked if " .
                            "player sends it as an individual word.";
                        $sender->sendMessage($output);
                        break;

                    case "about":
                        $aboutMsg = TextFormat::AQUA . "[RealMute] Version " .
                            $this->getDescription()->getVersion() . "\n";
                        $aboutMsg .= "RealMute is a chat management plugin " .
                            "with many extra features.\n";
                        $aboutMsg .= "Copyright (C) 2016, 2017 Leo3418 " .
                            "(https://github.com/Leo3418)\n";
                        $aboutMsg .= "This is free software licensed under " .
                            "GNU GPLv3 with the absence of any warranty.\n";
                        $aboutMsg .= "See http://www.gnu.org/licenses/ for details.\n";
                        $aboutMsg .= "You can find updates, documentation " .
                            "and source code of this plugin, report bug, and " .
                            "contribute to this project at " .
                            $this->getDescription()->getWebsite() . "\n";
                        $sender->sendMessage($aboutMsg);
                        break;

                    default:
                        $sender->sendMessage("Usage: " . $command->getUsage());
                }
                break;

            case "rmute":
                if (count($args) != 1 && count($args) != 2) {
                    $sender->sendMessage("Usage: " . $command->getUsage());
                    break;
                }
                $name = array_shift($args);
                if ($this->getServer()->getPlayer($name) instanceof Player) {
                    $name = $this->getServer()->getPlayer($name)->getName();
                }
                if (!$this->inList("mutedplayers", $name)) {
                    if (count($args) == 1) {
                        $time = intval(array_shift($args));
                        if ($time > 0) {
                            $this->tmMute($name, $time);
                            $sender->sendMessage(TextFormat::GREEN .
                                "[RealMute] Successfully muted " . $name .
                                " for " . $time . " minute(s).");
                        } else {
                            $sender->sendMessage("Usage: " . $command->getUsage());
                            break;
                        }
                    } else {
                        $sender->sendMessage(TextFormat::GREEN .
                            "[RealMute] Successfully muted " . $name . ".");
                    }
                    $this->add("mutedplayers", $name);
                    $this->addIdentity($name);
                } else {
                    $sender->sendMessage(TextFormat::RED . "[RealMute] " .
                        $name . " has been already muted.");
                }
                break;
            case "runmute":
                if (count($args) != 1) {
                    $sender->sendMessage("Usage: " . $command->getUsage());
                    break;
                }
                $name = array_shift($args);
                if ($this->getServer()->getPlayer($name) instanceof Player) {
                    $name = $this->getServer()->getPlayer($name)->getName();
                }
                if ($this->inList("mutedplayers", $name)) {
                    $this->remove("mutedplayers", $name);
                    $this->removeIdentity($name);
                    $sender->sendMessage(TextFormat::GREEN .
                        "[RealMute] Successfully unmuted " . $name . ".");
                } else {
                    $sender->sendMessage(TextFormat::RED .
                        "[RealMute] " . $name . " has not been muted yet.");
                }
                break;
            case "muteall":
                // TODO: add time-limited all-player mute
                if ($this->getConfig()->get("muteall") == false) {
                    $this->getConfig()->set("muteall", true);
                    $this->getConfig()->set("muteall", true);
                    $this->getConfig()->save();
                    $sender->sendMessage(TextFormat::GREEN .
                        "[RealMute] Successfully muted all players.");
                } else {
                    $sender->sendMessage(TextFormat::RED .
                        "[RealMute] You have already muted all players.");
                }
                break;
            case "unmuteall":
                if ($this->getConfig()->get("muteall")) {
                    $this->getConfig()->set("muteall", false);
                    $this->getConfig()->save();
                    $sender->sendMessage(TextFormat::GREEN .
                        "[RealMute] Successfully unmuted all players.");
                } else {
                    $sender->sendMessage(TextFormat::RED .
                        "[RealMute] You need to mute all players first.");
                }
                break;
        }
        return true;
    }

    public function onPlayerChat(PlayerChatEvent $event)
    {
        $player = $event->getPlayer()->getName();
        $message = $event->getMessage();
        $identitiesMuted = $this->mutedIdentities->getAll(true);
        $userConfig = new Config($this->getDataFolder() . "players/" .
            strtolower($player[0]) . "/" . strtolower($player) . ".yml");
        $userIdentity = $userConfig->get("identity");

        // Checks if administrator has muted all players
        if ($this->getConfig()->get("muteall")) {
            if ($this->getConfig()->get("excludeop") &&
                $event->getPlayer()->hasPermission("realmute.muteignored")) {
            }
            else {
                $event->setCancelled(true);
                if ($this->getConfig()->get("notification") === true) {
                    $event->getPlayer()->sendMessage(TextFormat::RED .
                        "Administrator has muted all players in chat.");
                } elseif ($this->getConfig()->get("notification") === "fake") {
                    $this->sendFakeMessage($event);
                }
            }
            return true;
        }

        // Checks if any player is flooding the chat screen
        // Ignores players who have already been muted
        elseif ($this->getConfig()->get("spamthreshold") !== false &&
            (!$this->inList("mutedplayers", $player) &&
                !in_array($userIdentity, $identitiesMuted)) &&
            $this->lastMsgSender == $player &&
            time() - $this->lastMsgTime <= ($this->getConfig()->get("spamthreshold"))) {
            if ($this->consecutiveMsg < 2) {
                $this->lastMsgSender = $player;
                $this->lastMsgTime = time();
                ++$this->consecutiveMsg;
                return true;
            }
            $event->setCancelled(true);
            if ($this->getConfig()->get("banspam")) {
                $this->add("mutedplayers", $player);
                if ($this->getConfig()->get("automutetime") !== false) {
                    $this->tmMute($player, $this->getConfig()->get("automutetime"));
                }
                if ($this->getConfig()->get("notification") === true) {
                    $event->getPlayer()->sendMessage(TextFormat::RED .
                        "Because you are flooding the chat screen, you are now muted in chat.");
                }
                $this->getLogger()->notice($player .
                    " flooded the chat screen and has been muted automatically.");
            }
            if ($this->getConfig()->get("notification") !== "fake") {
                $event->getPlayer()->sendMessage(TextFormat::RED .
                    "Do not flood the chat screen.");
            } else {
                $this->sendFakeMessage($event);
            }
            $this->lastMsgSender = $player;
            $this->lastMsgTime = time();
            return true;
        }

        // Checks if a player is muted individually
        elseif ($this->inList("mutedplayers", $player) ||
            ($this->getConfig()->get("muteidentity") && 
                in_array($userIdentity, $identitiesMuted))) {
            $userConfig = new Config($this->getDataFolder() . "players/" . 
                strtolower($player[0]) . "/" . strtolower($player) . ".yml");
            if ($userConfig->get("unmuteTime") != false) {
                $unmuteTime = $userConfig->get("unmuteTime");
                $event->setCancelled(true);
                if ($this->getConfig()->get("notification") === true) {
                    $event->getPlayer()->sendMessage(TextFormat::RED .
                        "You have been muted in chat. You will be unmuted in " .
                        (ceil(($unmuteTime - time()) / 60)) . " minute(s).");
                } elseif ($this->getConfig()->get("notification") === "fake") {
                    $this->sendFakeMessage($event);
                }
                return true;
            }
            $event->setCancelled(true);
            if ($this->getConfig()->get("notification") === true) {
                $event->getPlayer()->sendMessage(TextFormat::RED .
                    "You have been muted in chat.");
            }
            elseif ($this->getConfig()->get("notification") === "fake") {
                $this->sendFakeMessage($event);
            }
            return true;
        }

        // Checks if a player's message contains any banned word
        foreach (explode(",", $this->getConfig()->get("bannedwords")) as $bannedWord) {
            if (strlen($bannedWord) != 0 && $bannedWord[0] == "!") {
                $bannedWord = substr($bannedWord, 1);
                foreach (explode(" ", $message) as $word) {
                    if (strcmp(strtolower($word), $bannedWord) == 0) {
                        $event->setCancelled(true);
                        if ($this->getConfig()->get("wordmute")) {
                            if ($this->getConfig()->get("notification") === true) {
                                $event->getPlayer()->sendMessage(TextFormat::RED .
                                    "Your message contains banned word set by " .
                                    "administrator. You are now muted in chat.");
                            } elseif ($this->getConfig()->get("notification") === "fake") {
                                $this->sendFakeMessage($event);
                            } else {
                                $event->getPlayer()->sendMessage(TextFormat::RED .
                                    "Your message contains banned word set by administrator.");
                            }
                            $this->add("mutedplayers", $player);
                            $this->addIdentity($player);
                            if ($this->getConfig()->get("automutetime") !== false) {
                                $this->tmMute($player, $this->getConfig()->get("automutetime"));
                            }
                            $this->getLogger()->notice($player .
                                " sent banned words in chat and has been muted automatically.");
                            return true;
                            break;
                        } elseif ($this->getConfig()->get("notification") !== "fake") {
                            $event->getPlayer()->sendMessage(TextFormat::RED .
                                "Your message contains banned word set by administrator.");
                        } else $this->sendFakeMessage($event);
                        return true;
                        break;
                    }
                }
            } else {
                if (stripos($message, $bannedWord) != false) {
                    $event->setCancelled(true);
                    if ($this->getConfig()->get("wordmute")) {
                        if ($this->getConfig()->get("notification") === true) {
                            $event->getPlayer()->sendMessage(TextFormat::RED .
                                "Your message contains banned word set by " .
                                "administrator. You are now muted in chat.");
                        } elseif ($this->getConfig()->get("notification") === "fake") {
                            $this->sendFakeMessage($event);
                        } else {
                            $event->getPlayer()->sendMessage(TextFormat::RED .
                                "Your message contains banned word set by administrator.");
                        }
                        $this->add("mutedplayers", $player);
                        $this->addIdentity($player);
                        if ($this->getConfig()->get("automutetime") !== false) {
                            $this->tmMute($player, $this->getConfig()->get("automutetime"));
                        }
                        $this->getLogger()->notice($player .
                            " sent banned words in chat and has been muted automatically.");
                        return true;
                        break;
                    } elseif ($this->getConfig()->get("notification") !== "fake") {
                        $event->getPlayer()->sendMessage(TextFormat::RED .
                            "Your message contains banned word set by administrator.");
                    } else {
                        $this->sendFakeMessage($event);
                    }
                    return true;
                    break;
                }
            }
        }

        // Checks if a player's message is too long
        if ($this->getConfig()->get("lengthlimit") !== false &&
            mb_strlen($message, "UTF8") > $this->getConfig()->get("lengthlimit")) {
            if ($this->getConfig()->get("banlengthy") == "mute") {
                $event->setCancelled(true);
                if ($this->getConfig()->get("notification") === true) {
                    $event->getPlayer()->sendMessage(TextFormat::RED .
                        "Your message exceeds length limit set by " .
                        "administrator. You are now muted in chat.");
                } elseif ($this->getConfig()->get("notification") === "fake") {
                    $this->sendFakeMessage($event);
                } else {
                    $event->getPlayer()->sendMessage(TextFormat::RED .
                        "Your message exceeds length limit set by administrator.");
                }
                $this->add("mutedplayers", $player);
                $this->addIdentity($player);
                if ($this->getConfig()->get("automutetime") !== false) {
                    $this->tmMute($player, $this->getConfig()->get("automutetime"));
                }
                $this->getLogger()->notice($player .
                    " sent lengthy message in chat and has been muted automatically.");
                return true;
            } elseif ($this->getConfig()->get("banlengthy") == "slice") {
                if ($this->getConfig()->get("notification") !== "fake") {
                    $event->getPlayer()->sendMessage(TextFormat::RED .
                        "Your message exceeds length limit set by " .
                        "administrator and has been sliced." . TextFormat::RESET);
                } else {
                    $recipients = $event->getRecipients();
                    $newRecipients = array();
                    foreach ($recipients as $player) {
                        if ($player !== $event->getPlayer()) {
                            $newRecipients[] = $player;
                        }
                    }
                    $event->setRecipients($newRecipients);
                    $this->sendFakeMessage($event);
                }
                $event->setMessage(mb_substr($message, 0,
                    $this->getConfig()->get("lengthlimit"), "UTF8"));
                return true;
            }
        }

        // Refreshes variables storing information of the last chat message
        $this->lastMsgSender = $player;
        $this->lastMsgTime = time();
        $this->consecutiveMsg = 1;
        return true;
    }

    /**
     * If needed, blocks players' private messages.
     *
     * TODO: add check of banned words
     *
     * @param PlayerCommandPreprocessEvent $event
     */
    public function onPlayerCommand(PlayerCommandPreprocessEvent $event)
    {
        $player = $event->getPlayer()->getName();
        $command = strtolower($event->getMessage());
        $identitiesMuted = $this->mutedIdentities->getAll(true);
        $userConfig = new Config($this->getDataFolder() .
            "players/" . strtolower($player[0]) . "/" . strtolower($player) . ".yml");
        $userIdentity = $userConfig->get("identity");
        if ($this->getConfig()->get("banpm") &&
            ($this->inList("mutedplayers", $player) ||
                ($this->getConfig()->get("muteidentity") &&
                    in_array($userIdentity, $identitiesMuted))) &&
            (substr($command, 0, 6) == "/tell " ||
                substr($command, 0, 5) == "/msg " ||
                substr($command, 0, 3) == "/m " ||
                substr($command, 0, 9) == "/whisper " ||
                (!$this->getServer()->getPluginManager()->getPlugin("SWorld") &&
                    substr($command, 0, 3) == "/w "))) {
            $event->setCancelled(true);
            if ($this->getConfig()->get("notification") === true) {
                $event->getPlayer()->sendMessage(TextFormat::RED .
                    "You are not allowed to send private messages until you " .
                    "get unmuted in chat.");
            }
        }
    }

    /**
     * If needed, prevent players from placing signs.
     *
     * @param BlockPlaceEvent $event
     */
    public function onPlaceEvent(BlockPlaceEvent $event)
    {
        $player = $event->getPlayer()->getName();
        $mutedIdentity = $this->mutedIdentities->getAll(true);
        $userConfig = new Config($this->getDataFolder() . "players/" .
            strtolower($player[0]) . "/" .
            strtolower($player) . ".yml");
        $userIdentity = $userConfig->get("identity");
        if ($this->getConfig()->get("bansign") &&
            ($this->inList("mutedplayers", $player) ||
                ($this->getConfig()->get("muteidentity") &&
                    in_array($userIdentity, $mutedIdentity))) &&
            ($event->getBlock()->getID() == 323 ||
                $event->getBlock()->getID() == 63 ||
                $event->getBlock()->getID() == 68)) {
            $event->setCancelled(true);
            if ($this->getConfig()->get("notification") === true) {
                $event->getPlayer()->sendMessage(TextFormat::RED .
                    "You are not allowed to use signs until you get unmuted in chat.");
            }
        }
    }

    /**
     * Adds an item to a list in config.yml.
     *
     * @param string $option name of the list: the flag of the list in config.yml
     * @param string $target the item being added
     */
    public function add(string $option, string $target)
    {
        if (count(explode(",", $this->getConfig()->get($option))) == 1) {
            $this->getConfig()->set($option, strtolower($target) . ",");
        } else {
            $this->getConfig()->set($option, $this->getConfig()->get($option) .
                strtolower($target) . ",");
        }
        $this->getConfig()->save();
    }

    /**
     * Removes an item from a list in config.yml.
     *
     * @param string $option name of the list: the flag of the list in config.yml
     * @param string $target the item being removed
     */
    public function remove(string $option, string $target)
    {
        $newList = "";
        $count = 0;
        foreach ((explode(",", $this->getConfig()->get($option))) as $item) {
            if (strcmp(strtolower($target), $item) == 0) {
                if (count(explode(",", $this->getConfig()->get($option))) == 2) {
                    $this->getConfig()->set($option, "");
                    break;
                }
            } else {
                ++$count;
                if (strcmp($count, substr_count($this->getConfig()->get($option),
                        ",")) == 0) {
                    $newList .= $item;
                } else {
                    $newList .= $item . ",";
                }
            }
        }
        $this->getConfig()->set($option, $newList);
        $this->getConfig()->save();
        // If unmuting a player, removes mute time record in the player's
        // configuration
        if ($option == "mutedplayers" && is_file($this->getDataFolder() .
                "players/" . strtolower($target[0]) . "/" .
                strtolower($target) . ".yml")) {
            $userConfig = new Config($this->getDataFolder() . "players/" .
                strtolower($target[0]) . "/" . strtolower($target) . ".yml");
            $userConfig->remove("unmuteTime");
            $userConfig->save();
        }
    }

    /**
     * Adds a player's identity to the list of muted identities.
     *
     * @param string $player the player's user name
     */
    public function addIdentity(string $player)
    {
        if (is_file($this->getDataFolder() . "players/" . strtolower($player[0]) .
                "/" . strtolower($player) . ".yml") &&
            $this->getConfig()->get("muteidentity")) {
            $userConfig = new Config($this->getDataFolder() . "players/" .
                strtolower($player[0]) . "/" . strtolower($player) . ".yml");
            $userIdentity = $userConfig->get("identity");
            $this->mutedIdentities->set($userIdentity);
            $this->mutedIdentities->save();
        }
    }

    /**
     * Removes a player's identity from the list of muted identities.
     *
     * @param string $player the player's user name
     */
    public function removeIdentity(string $player)
    {
        if ($this->getConfig()->get("muteidentity")) {
            $userConfig = new Config($this->getDataFolder() . "players/" .
                strtolower($player[0]) . "/" . strtolower($player) . ".yml");
            $userIdentity = $userConfig->get("identity");
            $this->mutedIdentities->remove($userIdentity);
            $this->mutedIdentities->save();
        }
    }

    /**
     * Checks whether an item is in a list in config.yml.
     *
     * @param string $option name of the list: the flag of the list in config.yml
     * @param string $target the item being queried
     * @return bool if the item is in the list
     */
    private function inList(string $option, string $target)
    {
        foreach ((explode(",", $this->getConfig()->get($option))) as $item) {
            if (strcmp(strtolower($target), $item) == 0) {
                return true;
                break;
            }
        }
        return false;
    }

    /**
     * Checks whether a setting is turned on.
     *
     * @param string $option name of the setting: the flag of the option in config.yml
     * @return string "ON" or "OFF"
     */
    private function isOn(string $option)
    {
        if ($this->getConfig()->get($option)) $text = TextFormat::GREEN . "ON";
        else $text = TextFormat::YELLOW . "OFF";
        return $text;
    }

    /**
     * Starts a time-limited mute.
     *
     * @param string $name name of the player being muted
     * @param int $time time of the mute in seconds
     */
    private function tmMute(string $name, int $time)
    {
        $now = time();
        $unmuteTime = $now + $time * 60;
        if (!is_dir($this->getDataFolder() . "players/" . strtolower($name[0]))) {
            mkdir($this->getDataFolder() . "players/" .
                strtolower($name[0]), 0777, true);
        }
        $userConfig = new Config($this->getDataFolder() . "players/" .
            strtolower($name[0]) . "/" . strtolower($name) . ".yml", CONFIG::YAML);
        $userConfig->set("unmuteTime", $unmuteTime);
        $userConfig->save();
    }

    /**
     * For settings that can only be either "ON" or "OFF", flips value of the
     * setting ("ON" -> "OFF" or "OFF" -> "ON"), and returns a message to the
     * person initiating the change.
     *
     * @param string $option name of the setting: the flag of the option in config.yml
     * @param CommandSender $sender the person initiating the change
     */
    private function toggle(string $option, CommandSender $sender)
    {
        $flag = "";
        $turnOnMsg = "";
        $turnOffMsg = "";
        switch ($option) {
            case "muteop":
                $flag = "excludeop";
                $turnOnMsg = "When muting all players, OPs will be excluded.";
                $turnOffMsg = "OPs will be muted with all players.";
                break;
            case "wordmute":
                $flag = "wordmute";
                $turnOnMsg = "Players will be automatically muted if they send banned words.";
                $turnOffMsg = "Players will not muted if they send banned words.";
                break;
            case "banpm":
                $flag = "banpm";
                $turnOnMsg = "Private messages sent by muted players will be blocked.";
                $turnOffMsg = "Players can send private messages when they are muted in chat.";
                break;
            case "banspam":
                $flag = "banspam";
                $turnOnMsg = "Players will be automatically muted if they flood the chat screen.";
                $turnOffMsg = "Players will not muted if they flood the chat screen.";
                break;
            case "bansign":
                $flag = "bansign";
                $turnOnMsg = "Muted players cannot use signs.";
                $turnOffMsg = "Muted players are allowed to use signs.";
                break;
        }
        if (!$this->getConfig()->get($flag)) {
            $this->getConfig()->set($flag, true);
            $this->getConfig()->save();
            $sender->sendMessage(TextFormat::GREEN . "[RealMute] " . $turnOnMsg);
        } else {
            $this->getConfig()->set($flag, false);
            $this->getConfig()->save();
            $sender->sendMessage(TextFormat::YELLOW . "[RealMute] " . $turnOffMsg);
        }
    }

    private function sendFakeMessage(PlayerChatEvent $event)
    {
        $format = $event->getFormat();
        if ($format != "chat.type.text") {
            $fakeMessage = $format;
        } else {
            $fakeMessage = new TranslationContainer("%chat.type.text",
                [$event->getPlayer()->getName(), $event->getMessage()]);
        }
        $event->getPlayer()->sendMessage($fakeMessage);
    }

    /**
     * API method
     * Checks if a player is muted.
     *
     * @param mixed $player Player instance or player name
     * @return bool true if the player is muted
     */
    public function isMuted($player)
    {
        $name = $this->getPlayerName($player);
        if ($name == null) {
            return false;
        }
        $identityList = $this->mutedIdentities->getAll(true);
        if (is_file($this->getDataFolder() . "players/" .
            strtolower($name[0]) . "/" . strtolower($name) . ".yml")) {
            $userConfig = new Config($this->getDataFolder() . "players/" .
                strtolower($name[0]) . "/" . strtolower($name) . ".yml");
            $userIdentity = $userConfig->get("identity");
            if ($this->getConfig()->get("muteidentity") &&
                in_array($userIdentity, $identityList)) {
                return true;
            }
        }
        return $this->inList("mutedplayers", $name);
    }

    /**
     * API method
     * Mutes a player.
     *
     * @param mixed $player Player instance or player name
     * @return bool true if the operation is successful
     */
    public function mutePlayer($player)
    {
        $name = $this->getPlayerName($player);
        if ($name != null && !$this->inList("mutedplayers", $name)) {
            $this->add("mutedplayers", $name);
            $this->addIdentity($name);
            return true;
        }
        return false;
    }

    /**
     * API method
     * Unmutes a player.
     *
     * @param mixed $player Player instance or player name
     * @return bool true if the operation is successful
     */
    public function unmutePlayer($player)
    {
        $name = $this->getPlayerName($player);
        if ($name != null && $this->inList("mutedplayers", $name)) {
            $this->remove("mutedplayers", $name);
            $this->removeIdentity($name);
            return true;
        }
        return false;
    }

    /**
     * Gets a player's name, no matter what data type of player is given.
     *
     * TODO: test this function
     *
     * @param mixed $player Player instance or player name
     * @return string the player's name
     */
    private function getPlayerName($player) : string
    {
        if ($player instanceof Player) { // Player instance
            return $player->getName();
        } elseif (gettype($player) != "object") { // Player name
            return $player;
        } else {
            return null;
        }
    }
}
