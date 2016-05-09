# RealMute: A PocketMine chat management plugin by Leo3418
RealMute is a plugin that allows administrator to mute players in chat. 

## Upgrading from version 1.x?
Because of changes of internal mechanisms of muting players, the **config.yml** will be automatically updated to v2.x format.

## Features
* Mute and unmute individual players in chat
* Keep muting players even if they quit and join server again
* Mute all players in chat
* Time-limited mute
* Block and mute(optional) players who send spam messages
* Block and mute(optional) players if they send any prohibited contents set by administrator
* An optional function to disallow muted players to use signs
* An optional function to block players' private messages alongside chat messages
* An optional function to keep allowing OPs sending messages while all players are muted
* An optional function to notify muted players when they are sending messages
* See list of muted players and status of the plugin
* Time-limitedly mute players who are automatically muted by this plugin

## Usage
* `/rmute <player> [time]` Mute a player, you can specify time(in minutes) to use time-limited mute
* `/runmute <player>` Unmute a player
* `/muteall` Mute all players
* `/unmuteall` Unmute all players
* `/realmute help <page>` View help
* `/realmute notify` Toggle notification to muted players
* `/realmute muteop` Include/Exclude OPs from muting all players
* `/realmute wordmute` Turn on/off auto-muting players if they send banned words
* `/realmute banpm` Turn on/off blocking private messages from muted players
* `/realmute banspam` Turn on/off auto-muting players if they send spam messages
* `/realmute bansign` Allow/Disallow muted players to use signs
* `/realmute spamth <time>` Specify spam threshold in seconds (If players sends consecutive messages within this time interval, they will be blocked)
* `/realmute amtime <time>` Set time limit(in minutes) of auto-mute, set 0 to disable
* `/realmute addword <word>` Add a keyword to banned word list
* `/realmute delword <word>` Delete a keyword from banned word list
* `/realmute status` View current status of this plugin
* `/realmute list` List muted players
* `/realmute word` Show the banned-word list
* `/realmute about` Show information about this plugin  
Note on muting/unmuting players: Player name is not case-sensitive.  
Note on adding keywords: You can add an exclamation mark before the word if you want to match the whole word only. For example, if you add **!bo**, **boy** will not be blocked, but **bo y** will be blocked.

## Default Configuration
| Description | Default Setting |
| :---: | :---: |
| Notification to muted players | OFF |
| Exclude OPs from muting all players | ON |
| Auto-mute players if they send banned words | OFF |
| Blocking muted players' private messages | OFF |
| Auto-mute players if they send spam messages | OFF |
| Spam threshold | 1 second |
| Time limit of auto-mute | OFF |
| Muted players cannot use signs | OFF |

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
All done! This plugin will not be updated anymore unless there is a bug or an issue needed to fix, or I come up with any new feature.  
Please feel free to open an issue if you find any problem, or send a pull request when you have any suggestion or idea to this plugin.  
* [x] An optional function to exclude OPs in muting all players
* [x] See all muted players and settings of this plugin
* [x] Automatically mute players if they send any prohibited contents in chat
* [x] Improve mechanism of checking contents that players send
* [x] An optional function to ban private messages along with chat
* [x] Time-limited mute
* [x] Automatically block players who send spam messages
* [x] Time-limitedly mute players who are automatically blocked by this plugin

## License
This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.  
This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.  
You should have received a copy of the GNU General Public License along with this program. [If not, click here.](http://www.gnu.org/licenses/)