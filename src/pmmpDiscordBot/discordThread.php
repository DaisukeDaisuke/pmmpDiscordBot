<?php

namespace pmmpDiscordBot;

use Discord\Parts\Channel\Message;
use Discord\WebSockets\Event;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use pocketmine\console\ConsoleCommandSender;
use pocketmine\Server;
use pocketmine\snooze\SleeperNotifier;
use pocketmine\thread\Thread;
use pocketmine\utils\TextFormat;
use React\EventLoop\Loop;

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

	/** pmmp api */

	/** @var \ThreadedLogger */
	protected $logger;
	/** @var SleeperNotifier */
	protected $notifier;
	/** @var ConsoleCommandSender */
	private static $consoleSender;

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

		$server = Server::getInstance();
		self::$consoleSender = new ConsoleCommandSender($server, $server->getLanguage());

		$this->logger = Server::getInstance()->getLogger();
		$this->initSleeperNotifier();
		$this->start();
	}

	private function initSleeperNotifier() : void{
		if(isset($this->notifier)) throw new \LogicException("SleeperNotifier has already been initialized.");
		$this->notifier = new SleeperNotifier();
		Server::getInstance()->getTickSleeper()->addNotifier($this->notifier, function(){
			$this->onWake();
		});
	}

	protected function onRun() : void{
		ini_set('memory_limit', '-1');

		if(!$this->no_vendor){
			include $this->file."vendor/autoload.php";
		}

		$loop = Loop::get();

		$debug = $this->debug;
		$logger = new Logger('Logger');
		if($debug === true){
			$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
		}else{
			//$logger->pushHandler(new NullHandler());
			$logger->pushHandler(new StreamHandler('php://stdout', Logger::WARNING));
		}

		$discord = new \Discord\Discord([
			'token' => $this->token,
			'loop' => $loop,
			'logger' => $logger
		]);

		$timer = $loop->addPeriodicTimer(1, function() use ($discord){
			if($this->isKilled){
				// $discord->getChannel($this->send_channelId)->sendMessage("サーバーを停止しています...")->then(function (Message $message) use ($discord){
				// 	$discord->close();
				// 	$discord->loop->stop();
				// 	$this->started = false;
				// });
				$discord->close();
				$discord->loop->stop();
				$this->started = false;
				return;
			}
			$this->task($discord);
		});

		unset($this->token);

		$discord->on("ready", function($discord){
			$this->started = true;
			$this->logger->info("Bot is ready.");
			// Listen for events here
			$botUserId = $discord->user->id;
			$receive_channelId = $this->receive_channelId;

			$discord->on(Event::MESSAGE_CREATE, function(Message $message) use ($botUserId, $receive_channelId){
				if($message->channel_id === $receive_channelId){
					if($message->type !== Message::TYPE_NORMAL) return;//join message etc...
					if($message->author->id === $botUserId) return;
					$this->D2P_Queue[] = serialize([
						'username' => $message->author->username,
						'content' => $message->content
					]);
					/** @see onWake() */
					$this->notifier->wakeupSleeper();
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
			$message = preg_replace(['/\]0;.*\%/', '/[\x07]/', "/Server thread\//"], '', TextFormat::clean(substr($message, 0, 1900)))."\n";//processtile,ANSIコードの削除を実施致します...
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

	/**
	 * スレッドを停止します。
	 *
	 * @internal pmmp内部より、プラグイン無効化後に起動
	 * @return void
	 */
	public function quit() : void{
		Server::getInstance()->getTickSleeper()->removeNotifier($this->notifier);
		parent::quit();
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
		return $messages;
	}

	private function onWake() : void{
		foreach($this->fetchMessages() as $message){
			$content = $message["content"];
			var_dump($content);
			if($content === ""){
				continue;
			}

			if($content[0] === "/"||$content[0] === "!"||$content[0] === "?"){
				Server::getInstance()->dispatchCommand(self::$consoleSender, substr($content, 1));
			}else{
				Server::getInstance()->dispatchCommand(self::$consoleSender, "me ".$content);
			}
		}
	}
}
