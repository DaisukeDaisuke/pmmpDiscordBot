<?php

namespace pmmpDiscordBot;

use pocketmine\utils\TextFormat;

class discordThread extends \Thread{
	public $file;

	public $stopped = false;
	public $started = false;
	public $content;
	public $no_vendor;
	private $token;
	public $send_guildId;
	public $send_channelId;
	public $receive_channelId;
	public $send_interval;
	public $receive_check_interval;

	protected $D2P_Queue;
	protected $P2D_Queue;

	public function __construct($file, $no_vendor, string $token, string $send_guildId, string $send_channelId, string $receive_channelId, int $send_interval = 1){
		$this->file = $file;
		$this->no_vendor = $no_vendor;
		$this->token = $token;
		$this->send_guildId = $send_guildId;
		$this->send_channelId = $send_channelId;
		$this->receive_channelId = $receive_channelId;

		$this->send_interval = $send_interval;

		$this->D2P_Queue = new \Threaded;
		$this->P2D_Queue = new \Threaded;

		$this->start();
	}

	public function run(){
		if(!$this->no_vendor){
			include $this->file."vendor/autoload.php";
		}

		$loop = \React\EventLoop\Factory::create();
		//$emitter = new \Evenement\EventEmitter();

		$discord = new \Discord\Discord([
			'token' => $this->token,
			"loop" => $loop,
		]);

		//sleep(1);//...?

		$timer = $loop->addPeriodicTimer(1, function() use ($discord){
			if($this->stopped){
				$discord->close();
				$discord->loop->stop();
				$this->started = false;
				return;
			}
		});

		$timer1 = $loop->addPeriodicTimer(1, function() use ($discord){
			$this->task($discord);
		});


		unset($this->token);

		$discord->on('ready', function($discord){
			$this->started = true;
			echo "Bot is ready.", PHP_EOL;
			// Listen for events here
			$botUserId = $discord->user->id;
			$receive_channelId = $this->receive_channelId;

			$discord->on('message', function($message) use ($botUserId, $receive_channelId){
				if($message->channel->id === $receive_channelId){
					if($message->author->user->id === $botUserId) return;
					$this->D2P_Queue[] = serialize([
						'username' => $message->author->username,
						'content' => $message->content
					]);
				}
			});
		});
		$discord->run();
	}

	public function task($discord){
		if(!$this->started) return;

		$guild = $discord->guilds->get('id', $this->send_guildId);
		$channel = $guild->channels->get('id', $this->send_channelId);

		$send = "";

		while(count($this->P2D_Queue) > 0){
			$message = unserialize($this->P2D_Queue->shift());//
			$message = preg_replace(['/\]0;.*\%/', '/[\x07]/', "/Server thread\//"], '', TextFormat::clean(substr($message, 0, 1900)));//processtile,ANSIコードの削除を実施致します...
			if($message === "") continue;
			$send .= $message;
			if(strlen($send) >= 1800){
				break;
			}
		}
		if($send !== ""){
			$channel->sendMessage("```".$send."```");
		}

	}

	//===メインスレッド呼び出し専用関数にてございます...===
	public function shutdown(){
		$this->stopped = true;
	}

	public function sendMessage(string $message){
		//var_dump("send".$message);
		$this->P2D_Queue[] = serialize($message);
	}

	public function fetchMessages(){
		//var_dump("?!?!");
		$messages = [];
		while(count($this->D2P_Queue) > 0){
			$messages[] = unserialize($this->D2P_Queue->shift());
		}
		//var_dump($messages);
		return $messages;
	}
}
