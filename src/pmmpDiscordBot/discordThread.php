<?php
namespace pmmpDiscordBot;

use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\command\ConsoleCommandSender;

class discordThread extends \Thread{
    public $file;

	public $test = "";
	public $stopped = false;
	public $started = false;
	public $synchronized = false;
	public $synchronized1 = false;
	public $content;
	public $no_vendor;
	public $token;
	public $send_guildId;
	public $send_channelId;
	public $receive_channelId;

	public function __construct($file,$no_vendor,String $token,String $send_guildId,String $send_channelId,String $receive_channelId){
		$this->file = $file;
		$this->no_vendor = $no_vendor;
		$this->token = $token;
		$this->send_guildId = $send_guildId;
		$this->send_channelId = $send_channelId;
		$this->receive_channelId = $receive_channelId;
		
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

		unset($this->token);

		$send_guildId = $this->send_guildId;
		$send_channelId = $this->send_channelId;

		$timer = $loop->addPeriodicTimer(2, function() use ($discord,$send_guildId,$send_channelId,$loop){
			$this->task($discord,$send_guildId,$send_channelId,$loop);
		});

		$discord->on('ready', function($discord){
			$this->started = true;
			echo "Bot is ready.", PHP_EOL;
			// Listen for events here
			$botUserId = $discord->user->id;
			$receive_channelId = $this->receive_channelId;
			$discord->on('message', function($message) use ($discord,$botUserId,$receive_channelId){
				//var_dump($message);
				if($message->channel->id === $receive_channelId){
					//echo "Recieved a message from {$message->author->username}: {$message->content}", PHP_EOL;
					if($message->author->user->id === $botUserId) return;
					$content = $message->content;
					//ヒント: 109行付近より同期スレッド間データー受け渡しを実施しております...
					$this->synchronized(function() use ($content){
						$this->content = $content;
						$this->synchronized1 = true;
						$this->wait();
					});
				}
			});
		});
		$discord->run();
		//var_dump("stop!!");
	}

	public function task($discord,$send_guildId,$send_channelId,$loop){
		if(!$this->started) return;
		if($this->stopped){
			$discord->close();
			$loop->stop();
			$this->started = false;
			return;
		}
		$test = preg_replace(['/\]0;.*\%/','/[\x07]/',"/Server thread\//"],'',TextFormat::clean(substr($this->test,0,1900)));//processtile,ANSIコードの削除を実施致します...
		$this->test = strlen($this->test) <= 1900 ? "" : substr($this->test,1900);//
		if($test !== ""){
	        $guild = $discord->guilds->get('id', $send_guildId);
			$channel = $guild->channels->get('id', $send_channelId);
			$channel->sendMessage("```\n".$test."\n```");
			$this->test = "";
		}
		if(!$this->started) return;
		if(!$this->synchronized) return;

		//ヒント: 101行付近より同期スレッド間データー受け渡しを実施しております...
		$this->synchronized(function() use ($discord){
			$this->synchronized = false;
            $this->wait();
        });
        
	}

	//===メインスレッド呼び出し専用関数にてございます...===

	public function closeThread(){
		$this->synchronized(function($thread){
			$this->synchronized = true;
			$thread->stopped = true;
			$thread->notify();
			//var_dump("closeThread");
		}, $this);
	}

	public function addText($text){
		$this->synchronized = true;

		//ヒント: 83行付近より同期スレッド間データー受け渡しを実施しております...
		$this->synchronized(function($thread) use ($text){
			//
			//var_dump("addText: ".$text);
			$thread->test .= $text;
			$thread->notify();
		}, $this);
	}

	public function mainThreadTask(){
		//ヒント: 56行付近より同期スレッド間データー受け渡しを実施しております...
		if($this->synchronized1 === true){
			$this->synchronized1 = false;
			$this->synchronized(function($thread){
				//var_dump($thread->content);
				$content = $thread->content;
				if($content[0] === "/"){
					//var_dump("!!");
					Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), substr($content, 1));
				}else{
					//var_dump("??");
					Server::getInstance()->dispatchCommand(new ConsoleCommandSender(), "me ".$content);
				}
				
				$thread->content = "";
				//var_dump($thread->content);
				$thread->notify();
			}, $this);
		}
	}
}
