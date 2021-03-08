<?php

namespace pmmpDiscordBot;

use Discord\Parts\Channel\Message;
use Monolog\Logger;
use pocketmine\Thread;
use pocketmine\utils\TextFormat;

class discordThread extends Thread{
	public $file;

	public $started = false;
	public $content;
	public $no_vendor;
	private $token;
	public $send_guildId;
	public $send_channelId;
	public $receive_channelId;
	public $send_interval;
	public $receive_check_interval;
	public $debug;

	protected $D2P_Queue;
	protected $P2D_Queue;

	public function __construct($file, $no_vendor, string $token, string $send_guildId, string $send_channelId, string $receive_channelId, int $send_interval = 1, bool $debug = false){
		$this->file = $file;
		$this->no_vendor = $no_vendor;
		$this->token = $token;
		$this->send_guildId = $send_guildId;
		$this->send_channelId = $send_channelId;
		$this->receive_channelId = $receive_channelId;

		$this->send_interval = $send_interval;

		$this->debug = $debug;

		$this->D2P_Queue = new \Threaded;
		$this->P2D_Queue = new \Threaded;

		$this->start(PTHREADS_INHERIT_CONSTANTS);
	}

	public function run(){
		//ini_set('memory_limit', '512M');
		error_reporting(-1);
		$this->registerClassLoader();
		gc_enable();

		if(!$this->no_vendor){
			include $this->file."vendor/autoload.php";
		}

		$loop = \React\EventLoop\Factory::create();
		//$emitter = new \Evenement\EventEmitter();

		$debug = $this->debug;

		$discord = new \Discord\Discord([
			'token' => $this->token,
			"loop" => $loop,
			'loggerLevel' => ($debug ? Logger::INFO : Logger::WARNING),
		]);

		//sleep(1);//...?

		$timer = $loop->addPeriodicTimer(1, function() use ($discord){
			if($this->isKilled){
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

			$discord->on('message', function(Message $message) use ($botUserId, $receive_channelId){
				if($message->channel_id === $receive_channelId){
					if($message->type !== Message::TYPE_NORMAL) return;//join message etc...
					if($message->author->id === $botUserId) return;

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
		$this->isKilled = true;
		//usleep(500000);
		//$this->quit();
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
