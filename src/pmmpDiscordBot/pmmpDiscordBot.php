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
        $tokenConfig = new Config($this->getDataFolder()."token.yml",Config::YAML);

        $token = $tokenConfig->get("token","your-auth-token");
        $error = false;
        if($token === "your-auth-token"){
            $this->getLogger()->error("トークンに関しましては、入力致しましておりません為、discordbotを起動することは出来ません。");
            $error = true;
        }

        $send_guildId = $tokenConfig->get("send_guildId","your-guild-id");
        if($send_guildId === "your-guild-id"){
            $this->getLogger()->error("コンソール出力を実施致します、ギルドidに関しましては、入力致しましておりません為、discordbotを起動することは出来ません。");
            $error = true;
        }

        $send_channelId = $tokenConfig->get("send_channelId","your-channel-id");
        if($send_channelId === "your-channel-id"){
            $this->getLogger()->error("コンソール出力を実施致します、チェンネルidに関しましては、入力致しましておりません為、discordbotを起動することは出来ません。");
            $error = true;
        }

        $receive_channelId = $tokenConfig->get("receive_channelId","your-channel-id");
        if($receive_channelId === "your-channel-id"){
            $this->getLogger()->error("コマンド,チャット入力を受け付けます、チェンネルidに関しましては、入力致しましておりません為、discordbotを起動することは出来ません。");
            $error = true;
        }

        if($error === true){
            $this->getLogger()->info("§cこのプラグインを無効化致します。§r");
            $this->getServer()->getPluginManager()->disablePlugin($this);
            return;
        }

        $this->client = new discordThread($this->getFile(),$token,$send_guildId,$send_channelId,$receive_channelId);

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
        ob_flush();
        ob_end_clean();
        $this->getLogger()->info("出力バッファリングを終了しています...");
    }
}
