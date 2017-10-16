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

class CheckTime extends PluginTask
{
    private $plugin;

    /** @var Config configuration file storing a list of muted identities */
    private $mutedIdentities;

    public function __construct(RealMute $plugin)
    {
        parent::__construct($plugin);
        $this->plugin = $plugin;
        // Registers muted identity list as a configuration file
        $this->mutedIdentities = new Config($this->getPlugin()->getDataFolder() .
            "identity.txt", Config::ENUM);
    }

    private function getPlugin()
    {
        return $this->plugin;
    }

    /**
     * In every tick, checks remaining mute time of every player and ummutes
     * if needed.
     * 
     * @param int $tick
     */
    public function onRun(int $tick)
    {
        $list = explode(",", $this->getPlugin()->getConfig()->get("mutedplayers"));
        array_pop($list);
        foreach ($list as $player) {
            if (is_file($this->getPlugin()->getDataFolder() .
                "players/" . strtolower($player[0]) . "/" .
                strtolower($player) . ".yml")) {
                $userConfig = new Config($this->getPlugin()->getDataFolder() .
                    "players/" . strtolower($player[0]) . "/" .
                    strtolower($player) . ".yml");
                if ($userConfig->get("unmutetime") != false) {
                    $unmuteTime = $userConfig->get("unmutetime");
                    if ($unmuteTime < time()) {
                        $this->remove("mutedplayers", $player);
                        $this->removeIdentity($player);
                    }
                }
            }
        }
    }

    /**
     * Removes an item from a list in config.yml.
     *
     * @param string $option name of the list: the flag of the list in config.yml
     * @param string $target the item being removed
     */
    private function remove(string $option, string $target)
    {
        $newList = "";
        $count = 0;
        foreach ((explode(",",
            $this->getPlugin()->getConfig()->get($option))) as $item) {
            if (strcmp(strtolower($target), $item) == 0) {
                if (count(explode(",",
                        $this->getPlugin()->getConfig()->get($option))) == 2) {
                    $this->getPlugin()->getConfig()->set($option, "");
                    break;
                }
            } else {
                ++$count;
                if (strcmp($count,
                        substr_count($this->getPlugin()->getConfig()->get($option),
                        ",")) == 0) {
                    $newList .= $item;
                } else {
                    $newList .= $item . ",";
                }
            }
        }
        $this->getPlugin()->getConfig()->set($option, $newList);
        $this->getPlugin()->getConfig()->save();
        // If unmuting a player, removes mute time record in the player's
        // configuration
        if ($option == "mutedplayers" &&
            is_file($this->getPlugin()->getDataFolder() . "players/" .
                strtolower($target[0]) . "/" .
                strtolower($target) . ".yml")) {
            $userConfig = new Config($this->getPlugin()->getDataFolder() .
                "players/" . strtolower($target[0]) . "/" .
                strtolower($target) . ".yml");
            $userConfig->remove("unmutetime");
            $userConfig->save();
        }
    }

    /**
     * Removes a player's identity from the list of muted identities.
     *
     * @param string $player the player's user name
     */
    private function removeIdentity(string $player)
    {
        if ($this->getPlugin()->getConfig()->get("muteidentity")) {
            $userConfig = new Config($this->getPlugin()->getDataFolder() .
                "players/" . strtolower($player[0]) . "/" .
                strtolower($player) . ".yml");
            $userIdentity = $userConfig->get("identity");
            $this->mutedIdentities->remove($userIdentity);
            $this->mutedIdentities->save();
        }
    }
}