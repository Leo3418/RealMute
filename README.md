# RealMute: A PocketMine chat management plugin by Leo3418
RealMute is a plugin that allows adminstrator to mute players in chat. 

## Upgrading from version 1.x?
Because of changes of internal mechanisms of muting players, the **config.yml** will be automatically updated to v2.x format.

## Features
* Mute and unmute individual players in chat
* Keep muting players even if they quit and join server again
* Mute all players in chat
* See list of muted players and status of the plugin
* An optional function to notify muted players when they are sending messages
* An optional function to keep allowing OPs sending messages while all players are muted
* An optional function to block players' private messages alongside chat messages
* Block and mute players if they send any prohibited contents set by adminstrator

## Usage
* `/rmute <player>` Mute a player
* `/runmute <player>` Unmute a player
* `/muteall` Mute all players
* `/unmuteall` Unmute all players
* `/realmute help <page>` View help
* `/realmute notify` Toggle notification to muted players
* `/realmute muteop` Include/Exclude OPs from muting all players
* `/realmute wordmute` Turn on/off auto-muting players if they send banned words
* `/realmute banpm` Turn on/off blocking private messages from muted players
* `/realmute addword <word>` Add a keyword to banned word list
* `/realmute delword <word>` Delete a keyword from banned word list
* `/realmute status` View current status of this plugin
* `/realmute list` List muted players
* `/realmute about` Show information about this plugin  
Note on muting/unmuting players: Player name is not case-sensitive.  
Note on adding keywords: You can add an exclamation mark before the word if you want to match the whole word only. For example, if you add **!bo**, **boy** will not be blocked, but **bo y** will be blocked.

## Default Configuration
| Description | Default Setting |
| :---: | :---: |
| Notification to muted players | OFF |
| Exclude OPs from muting all players | ON |
| Auto-mute players if they send banned words | OFF |
| Blocking muted players； private messages | OFF |

## Permissions
* `realmute` Allows all RealMute commands
  * `realmute.option` Allows using RealMute options
  * `realmute.muteignored` Allows sending messages when all players are muted
  * `realmute.mute` Allows muting individual players
  * `realmute.unmute` Allows unmuting individual players
  * `realmute.muteall` Allows muting all players
  * `realmute.unmuteall` Allows unmuting individual players

Default setting of those permissions is **OP**.

## Future Plan
* [x] An optional function to exclude OPs in muting all players
* [x] See all muted players and settings of this plugin
* [x] Automatically mute players if they send any prohibited contents in chat
* [x] Improve mechanism of checking contents that players send
* [x] An optional function to ban private messages along with chat
* [ ] Timer-mute

## License
This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.  
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.  
You should have received a copy of the GNU General Public License along with this program. [If not, click here.](http://www.gnu.org/licenses/)