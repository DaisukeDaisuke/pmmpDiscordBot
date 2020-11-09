<?php

namespace pmmpDiscordBot;

use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;

class pmmpDiscordBot extends PluginBase implements Listener{
	/** @var discordThread */
	public $client;
	public $started = false;

	public $receive_check_interval;

	public $messageids = [];

	public function onEnable(){
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
		//$receive_channelId = $tokenConfig->get("receive_channelId", "your-channel-id");

		unset($tokenConfig);

		if($token === "your-auth-token"||$send_guildId === "your-guild-id"||$send_channelId === "your-channel-id"){
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

		if($error === true){
			$this->getLogger()->info("§cこのプラグインを無効化致します。§r");
			$this->getServer()->getPluginManager()->disablePlugin($this);
			return;
		}

		$this->client = new discordThread($this->getFile(), $no_vendor, $token, $send_guildId, $send_channelId, $send_interval);
		$this->started = true;

		unset($token);

		/*$this->getScheduler()->scheduleDelayedTask(new ClosureTask(
			function(int $currentTick): void{
				$this->started = true;
				$this->getLogger()->info("出力バッファリングを開始致します。");
				ob_start();
			}
		), 10);

		$this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(
			function(int $currentTick): void{
				if(!$this->started) return;
				$string = ob_get_contents();

				if($string === "") return;
				$this->client->sendMessage($string);
				ob_flush();
			}
		), 10, 1);*/

		$this->getScheduler()->scheduleDelayedRepeatingTask(new ClosureTask(
			function(int $currentTick): void{
				foreach($this->client->fetchMessages() as $message){
					switch($message[discordThread::MESSAGE_TYPE]){
						case discordThread::MESSAGE_TYPE_REPLY:
							/*if($message->channel->id !== $receive_channelId){
								return;
							}*/
							/** @var mixed[] $message */
							if($message[discordThread::MESSAGE_IS_MYSELF]){
								$this->messageids[] = [$message[discordThread::MESSAGE_ID], $message[discordThread::MESSAGE_CHANNELID]];//
								//$this->client->editMessage($message[discordThread::MESSAGE_ID], $message[discordThread::MESSAGE_CHANNELID],$contents);
								//var_dump("!!!!!!");
								//$this->client->editMessage($message[discordThread::MESSAGE_ID], null,"test!!!!!!!!!");
								//$this->editMessage($message, "test!!!!!!!!");

								return;
							}
							if($message[discordThread::MESSAGE_ISBOT]){
								return;
							}
							$content = $message[discordThread::MESSAGE];

							//var_dump($content);

							//$this->replyMessage($message,"test!!!!!");
							/*$this->replyMessage($message, "test!!",
								[
									"description" => "▸全てのサービスは正常に稼働しています。 \n▸All Service are working!\n ",
									"color" => 65280,
									"author" => [
										"name" => "Server Status",
									],
									"fields" => [
										[
											"name" => ":white_check_mark:Online",
											"value" => "- Players: 0/10\n- Game: 待機中"
										],
									]
								]
							);*/

							/*$this->sendMessage("test!!", null,
								[
									"description" => "▸全てのサービスは正常に稼働しています。 \n▸All Service are working!\n ",
									"color" => 65280,
									"author" => [
										"name" => "Server Status",
									],
									"fields" => [
										[
											"name" => ":white_check_mark:Online",
											"value" => "- Players: 0/10\n- Game: 待機中"
										],
									]
								]
							);*/

							//receive message

							/*if($content[0] === "/"){
								Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), substr($content, 1));
							}else{
								Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), "me ".$content);
							}*/
							break;
						case discordThread::MESSAGE_TYPE_EMOJI_ADD:
							//var_dump($message);
							break;
						case discordThread::MESSAGE_TYPE_EMOJI_REMOVE:
							//var_dump($message);
							break;
						case discordThread::MESSAGE_TYPE_MEMBER_ADD:
							//var_dump("MESSAGE_TYPE_MEMBER_ADD",$message,"MESSAGE_TYPE_MEMBER_ADD");
							break;
						case discordThread::MESSAGE_TYPE_MEMBER_REMOVE:
							//var_dump("MESSAGE_TYPE_MEMBER_REMOVE",$message,"MESSAGE_TYPE_MEMBER_REMOVE");
							break;
						case discordThread::MESSAGE_TYPE_DELETE:
							//var_dump($message);
							break;
					}
				}
			}
		), 5, $this->receive_check_interval);

	}

	public function editMessage(array $message, string $contents){
		$this->client->editMessage($message[discordThread::MESSAGE_ID], $message[discordThread::MESSAGE_CHANNELID], $contents);
	}

	public function replyMessage(array $message, string $contents, ?array $embeds = null){
		$this->client->replyMessage($message[discordThread::MESSAGE_USERID], $message[discordThread::MESSAGE_CHANNELID], $contents, $embeds);
	}

	public function sendMessage(string $contents, ?string $channelId = null, ?array $embeds = null){
		$this->client->sendMessage($contents, $channelId, $embeds);
	}

	public function onDisable(){
		if(!$this->started) return;
		//$this->getLogger()->info("出力バッファリングを終了しています...");
		$this->client->shutdown();
		//ob_flush();
		//ob_end_clean();
		$this->getLogger()->info("discordBotの終了を待機しております...");
		$this->client->join();
	}
}
