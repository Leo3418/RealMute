RealMute: A PocketMine chat management plugin by Leo3418
==========

RealMute is a plugin that allows adminstrator to mute players in chat. 

Upgrading from version 1.x?
----------
Because of changes of internal mechanisms of muting players, the **config.yml** will be automatically updated to 2.x format.<br />

Features
----------
- Mute and unmute individual players in chat
- Keep muting players even if they quit and join server again
- Mute all players in chat
- An optional function to notify muted players when they are sending messages
- An optional function to keep allowing OPs sending messages while all players are muted

Usage
----------
- `/rmute <player>` Mute a player
- `/runmute <player>` Unmute a player
- `/muteall` Mute all players
- `/unmuteall` Unmute all players
- `/realmute help` View options
- `/realmute notify` Toggle notification to muted players
- `/realmute muteop` Include/Exclude OPs from muting all players
- `/realmute about` Show information about this plugin <br />
Note: Starting from v1.1.1, player name is no longer case-sensitive.

Permissions
----------
- `realmute` Allows all RealMute commands
- `realmute.option` Allows changing RealMute options
- `realmute.muteignored` Allows sending messages when all players are muted
- `realmute.mute` Allows muting individual players
- `realmute.unmute` Allows unmuting individual players
- `realmute.muteall` Allows muting all players
- `realmute.unmuteall` Allows unmuting individual players <br />
Default setting of those permissions is **OP**.

Future Plan
----------
- [x] An optional function to exclude OPs in muting all players
- [ ] See all muted players and settings of this plugin
- [ ] Automatically mute players if they send any prohibited contents in chat
- [ ] Timer-mute

License
----------
This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. <br />

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details. <br />

You should have received a copy of the GNU General Public License
along with this program. [If not, click here.] (http://www.gnu.org/licenses/)
