# pmmpDiscordBot
DiscordからPocketMine-MPのコンソールを操作します。

## download
開発バージョンに関しましては、GitHub Actionsよりダウンロード可能にてございます。
https://github.com/DaisukeDaisuke/pmmpDiscordBot/actions

## build
```
git clone git@github.com:DaisukeDaisuke/pmmpDiscordBot.git
cd pmmpDiscordBot
composer install --no-dev --prefer-dist --no-suggest
php -dphar.readonly=0 ./make-phar.php enableCompressAll
# 以上のコマンドに関しましては、うまく動作致しません場合、以下のコマンドを実行していただきたいです。
# php -dphar.readonly=0 ./make-phar.php 
```
