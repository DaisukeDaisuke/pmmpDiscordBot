<?php
namespace pmmpDiscordBot;

use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\Config;
use pocketmine\event\Listener;
use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;

class pmmpDiscordBot extends PluginBase implements Listener{
    public $client;
    public $started = false;

    public function onEnable(){
        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("discordbotをバックグラウンドにて起動しております...");

        $this->saveResource("token.yml");
        $this->saveResource("setting.yml");
        $tokenConfig = new Config($this->getDataFolder()."token.yml",Config::YAML);
        $settingConfig = new Config($this->getDataFolder()."setting.yml",Config::YAML);

        $error = false;
        $token = $tokenConfig->get("token","your-auth-token");
        $send_guildId = $tokenConfig->get("send_guildId","your-guild-id");
        $send_channelId = $tokenConfig->get("send_channelId","your-channel-id");
        $receive_channelId = $tokenConfig->get("receive_channelId","your-channel-id");
        if($token === "your-auth-token"||$send_guildId === "your-guild-id"||$send_channelId === "your-channel-id"||$receive_channelId === "your-channel-id"){
            $this->getLogger()->info("[PocketMine-MP]/plugin_data/pmmpDiscordBot/token.yml 上の一部の設定に関しましては不正の為、ファイルの中身を編集していただきたいです...");
            $error = true;
        }

        $no_vendor = $settingConfig->get("no-vendor");
        if(!$no_vendor&&!file_exists($this->getFile()."vendor/autoload.php")){
            $this->getLogger()->error($this->getFile()."vendor/autoload.php ファイルに関しましては存在致しません為、discordbotを起動することは出来ません。");
            $this->getLogger()->info("§ehttps://github.com/DaisukeDaisuke/pmmpDiscordBot/releases よりphar形式のプラグインをダウンロードお願い致します。§r");
            $error = true;
        }
        
        if($error === true){
            $this->getLogger()->info("§cこのプラグインを無効化致します。§r");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->client = new discordThread($this->getFile(),$no_vendor,$token,$send_guildId,$send_channelId,$receive_channelId);

        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(
            function (int $currentTick): void {
                $this->started = true;
                $this->getLogger()->info("出力バッファリングを開始致します。");
                ob_start();
            }
        ),10);

        $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(
            function (int $currentTick): void{
                if(!$this->started) return;
                $string = ob_get_contents();
                //file_put_contents($this->getDataFolder()."/test.txt",preg_replace('/\]0;.*\%/','',TextFormat::clean($string)),FILE_APPEND);
                //var_dump(preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '',preg_replace('/[\x1b|\x07].*[\x1b|\x07]/', '', $string)));
                //var_dump(preg_replace(['/\]0;.*\%/','/[\x07]/'],'',TextFormat::clean($string)));
                $this->client->addText($string);
                //echo "!";
                ob_flush();
            }
        ),10,1);
        
        $this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(
            function(int $currentTick): void{
                $string = ob_get_contents();
                $this->client->mainThreadTask();//非同期スレッドよりコマンドを受信、コマンドを実行致します。
            }
        ), 10, 2);
    }

    public function onDisable(){
        if(!$this->started) return;
        $this->getLogger()->info("出力バッファリングを終了しています...");
        $this->client->closeThread();
        ob_flush();
        ob_end_clean();
        $this->getLogger()->info("discordBotの終了を待機しております...");
        $this->client->join();
    }
}
