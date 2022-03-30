<?php

namespace pmmpDiscordBot;

class ThreadedLogListener extends \ThreadedLoggerAttachment{
	/** @var int */
	private $mainThreadId;
	/** @var discordThread */
	private static $discord;

	public function __construct(discordThread $discord){
		self::$discord = $discord;
		$this->mainThreadId = \Thread::getCurrentThreadId();
	}

	public function log($level, $message){
		if(\Thread::getCurrentThreadId() === $this->mainThreadId){
			//server thread
			self::$discord->sendMessage($message);
		}else{
			//chilled thread

		}

	}
}