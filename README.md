# pmmpDiscordBot
DiscordからPocketMine-MPのコンソールを操作します。

## download
開発バージョンに関しましては、GitHub Actionsよりダウンロード可能にてございます。  
The development version can be downloaded from GitHub Actions.
https://github.com/DaisukeDaisuke/pmmpDiscordBot/actions

## build
```
git clone git@github.com:DaisukeDaisuke/pmmpDiscordBot.git
cd pmmpDiscordBot
composer install --no-dev --prefer-dist --no-suggest
php -dphar.readonly=0 ./make-phar.php enableCompressAll
```
##### If make-phar.php does not work, please try the following command.
```
# php -dphar.readonly=0 ./make-phar.php
```
