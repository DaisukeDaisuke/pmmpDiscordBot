<?php

namespace pmmpDiscordBot;

use pocketmine\thread\log\ThreadSafeLoggerAttachment;
use pmmp\thread\Thread;

class ThreadedLogListener extends ThreadSafeLoggerAttachment{
	/** @var int */
	private $mainThreadId;
	/** @var discordThread */
	private static $discord;

	public function __construct(discordThread $discord){
		self::$discord = $discord;
		$this->mainThreadId = Thread::getCurrentThreadId();
	}

	public function log(string $level, string $message) : void{
		if(Thread::getCurrentThreadId() === $this->mainThreadId){
			//server thread
			self::$discord->sendMessage($message);
		}else{
			//chilled thread

		}

	}
}