# pmmpDiscordBot
DiscordからPocketMine-MPのコンソールを操作します。
## vs
This is a plugin written for my server, so if you want to write a discord bot with pmmp, the following plugins are better.  
## DiscordBot
https://github.com/DiscordBot-PMMP/DiscordBot  
## MCPEDiscordRelay
https://github.com/nomadjimbob/MCPEDiscordRelay/  
  
https://poggit.pmmp.io/p/MCPEDiscordRelay  
## MCPEToDiscord (archived)
https://github.com/JaxkDev/MCPEToDiscord/

https://poggit.pmmp.io/p/MCPEToDiscord
## download
### release
https://github.com/DaisukeDaisuke/pmmpDiscordBot/releases  

### develop
開発バージョンに関しましては、GitHub Actionsよりダウンロード可能にてございます。  
The development version can be downloaded from GitHub Actions.  
https://github.com/DaisukeDaisuke/pmmpDiscordBot/actions  

## build
```
git clone git@github.com:DaisukeDaisuke/pmmpDiscordBot.git
cd pmmpDiscordBot
composer install --no-dev --ignore-platform-reqs
php -dphar.readonly=0 ./make-phar.php enableCompressAll
```
##### If make-phar.php does not work, please try the following command.
```
# php -dphar.readonly=0 ./make-phar.php
```
