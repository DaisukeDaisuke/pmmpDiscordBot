<?php

namespace pmmpDiscordBot;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;

class pmmpDiscordBot extends PluginBase implements Listener{
	public discordThread $client;
	public bool $started = false;
	public int $receive_check_interval;
	private ThreadedLogListener $loggerListener;

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		$this->getLogger()->info("discordbotをバックグラウンドにて起動しております...");

		$this->saveResource("token.yml");
		$this->saveResource("setting.yml");
		$tokenConfig = new Config($this->getDataFolder()."token.yml", Config::YAML);
		$settingConfig = new Config($this->getDataFolder()."setting.yml", Config::YAML);

		$error = false;
		$token = $tokenConfig->get("token", "your-auth-token");
		$send_guildId = $tokenConfig->get("send_guildId", "your-guild-id");
		$send_channelId = $tokenConfig->get("send_channelId", "your-channel-id");
		$receive_channelId = $tokenConfig->get("receive_channelId", "your-channel-id");

		unset($tokenConfig);

		$debug = false;
		$debuglog = (bool) $settingConfig->get("debuglog", false);
		if($debuglog === true){
			$this->getLogger()->notice("debugモードは有効です。コンソールへdiscordbotのログを出力します。");
			$debug = true;
		}

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

		$send_interval = (int) $settingConfig->get("send-discord-interval", 2);
		$this->receive_check_interval = (int) $settingConfig->get("receive-check-interval", 1);

		if($send_interval < 1||$this->receive_check_interval < 1){
			$this->getLogger()->error("「send-discord-interval」または「receive-check-interval」設定の値を1以下にすることは出来ません。");
			$error = true;
		}

		$send_interval = (bool) $settingConfig->get("send-discord-interval", false);
		$this->receive_check_interval = (int) $settingConfig->get("receive-check-interval", 1);

		if($error === true){
			$this->getLogger()->info("§cこのプラグインを無効化致します。§r");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$this->client = new discordThread($this->getFile(), $no_vendor, $token, $send_guildId, $send_channelId, $receive_channelId, $send_interval, $debug);

		unset($token);

		$this->getScheduler()->scheduleDelayedTask(new ClosureTask(
			function() : void{
				$this->started = true;
				$this->getLogger()->info("出力バッファリングを開始致します。");
				$this->loggerListener = new ThreadedLogListener($this->client);
				$this->getServer()->getLogger()->addAttachment($this->loggerListener);
			}
		), 10);

//		$this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(
//			function() : void{
//				if(!$this->started) return;
//				$string = ob_get_contents();
//				var_dump($string);
//				if($string === "") return;
//				$this->client->sendMessage($string);
//				ob_flush();
//			}
//		), 10, 1);


	}

	public function onDisable() : void{
		if(isset($this->loggerListener)){
			$this->getServer()->getLogger()->removeAttachment($this->loggerListener);
		}
		if(!$this->started) return;
		//$this->client->shutdown();
		$this->getLogger()->info("出力バッファリングを終了しています...");
	}
}
