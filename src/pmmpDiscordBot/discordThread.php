<?php

namespace pmmpDiscordBot;

use Discord\Discord;
use Discord\Parts\Channel\Channel;
use Discord\Parts\Channel\Message;
use Discord\Parts\User\Member;
use Discord\WebSockets\Event;
use pocketmine\utils\TextFormat;

class discordThread extends \Thread{
	const MESSAGE_TYPE = 0;//int
	const MESSAGE = 1;//string
	const MESSAGE_ID = 2;//string
	const MESSAGE_GUILDID = 3;//string
	const MESSAGE_CHANNELID = 4;//string
	const MESSAGE_USERNAME = 5;//string
	const MESSAGE_USERID = 6;//string
	const MESSAGE_ISBOT = 7;//bool
	const MESSAGE_IS_MYSELF = 8;//bool
	const MESSAGE_IS_DM = 9;//bool

	const MESSAGE_EMBEDS = 10;

	const MESSAGE_EMOJI = 11;//絵文字本体「🤔」等...
	const MESSAGE_EMOJI_ID = 12;//????? 絵文字Id「null」等...
	const MESSAGE_DISCRIMINATOR = 13;
	//const MESSAGE = 1;
	//const MESSAGE_ID = 2;

	public $file;

	public $stopped = false;
	public $started = false;
	public $content;
	public $no_vendor;
	private $token;
	public $send_guildId;
	public $send_channelId;
	public $send_interval;
	public $receive_check_interval;

	protected $D2P_Queue;
	protected $P2D_Queue;

	const MESSAGE_TYPE_REPLY = -1;
	const MESSAGE_TYPE_SEND = 0;
	const MESSAGE_TYPE_EDIT = 1;
	const MESSAGE_TYPE_EMOJI_ADD = 2;
	const MESSAGE_TYPE_EMOJI_REMOVE = 3;
	const MESSAGE_TYPE_MEMBER_ADD = 4;
	const MESSAGE_TYPE_MEMBER_REMOVE = 5;
	const MESSAGE_TYPE_DELETE = 6;

	public function __construct($file, $no_vendor, string $token, string $send_guildId, string $send_channelId, int $send_interval = 1){
		$this->file = $file;
		$this->no_vendor = $no_vendor;
		$this->token = $token;
		$this->send_guildId = $send_guildId;
		$this->send_channelId = $send_channelId;

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

		unset($this->token);

		$discord->on('ready', function(\Discord\Discord $discord) use ($loop){
			$this->started = true;
			echo "Bot is ready.", PHP_EOL;

			$guild = $discord->guilds->get('id', $this->send_guildId);
			//$channel = $guild->channels->get('id', $this->send_channelId);

			//var_dump("!!");
			/*var_dump($guild->emojis->save($guild)->then(function ($test){
				$test->freshen();
			}));*/
			//var_dump("!!");

			$channel = $discord->factory(Channel::class, ['id' => $this->send_channelId]);

			$timer = $loop->addPeriodicTimer(1, function() use ($discord){
				if($this->stopped){
					$discord->close();
					$discord->loop->stop();
					$this->started = false;
					return;
				}
			});

			$timer1 = $loop->addPeriodicTimer(1, function() use ($discord, $channel){
				$this->task($discord, $channel);
			});

			// Listen for events here
			$botUserId = $discord->user->id;
			// = $this->receive_channelId;

			$discord->on('message', function(\Discord\Parts\Channel\Message $message) use ($botUserId){//, $receive_channelId
				//$message->react("🤔");
				if($message->author instanceof Member){
					$this->D2P_Queue[] = serialize([
						self::MESSAGE_TYPE => self::MESSAGE_TYPE_REPLY,//
						self::MESSAGE => $message->content,
						self::MESSAGE_ID => $message->id,
						self::MESSAGE_GUILDID => $message->channel->guild_id,
						self::MESSAGE_CHANNELID => $message->channel->id,
						self::MESSAGE_USERNAME => $message->author->username,
						self::MESSAGE_USERID => $message->author->id,
						self::MESSAGE_ISBOT => $message->author->user->bot ?? false,
						self::MESSAGE_IS_MYSELF => ($message->author->user->id === $botUserId),
						self::MESSAGE_IS_DM => false,
						//'md5' => md5($message["content"]),
					]);
				}else{
					$this->D2P_Queue[] = serialize([
						self::MESSAGE_TYPE => self::MESSAGE_TYPE_REPLY,//
						self::MESSAGE => $message->content,
						self::MESSAGE_ID => $message->id,
						self::MESSAGE_GUILDID => $message->channel->guild_id,
						self::MESSAGE_CHANNELID => $message->channel->id,
						self::MESSAGE_USERNAME => $message->author->username,
						self::MESSAGE_USERID => $message->author->id,
						self::MESSAGE_ISBOT => $message->author->bot ?? false,
						self::MESSAGE_IS_MYSELF => ($message->author->id === $botUserId),
						self::MESSAGE_IS_DM => true,
						//'md5' => md5($message["content"]),
					]);
				}
			});
			$discord->on("MESSAGE_REACTION_ADD", function(\stdClass $obj, Discord $discord) use ($botUserId){
				if(isset($obj->member)){
					$this->D2P_Queue[] = serialize([
						self::MESSAGE_TYPE => self::MESSAGE_TYPE_EMOJI_ADD,//

						self::MESSAGE_ID => $obj->message_id,
						self::MESSAGE_GUILDID => $obj->guild_id,
						self::MESSAGE_CHANNELID => $obj->channel_id,
						self::MESSAGE_USERNAME => $obj->member->user->username,
						self::MESSAGE_USERID => $obj->user_id,

						self::MESSAGE_IS_MYSELF => ($obj->user_id === $botUserId),
						self::MESSAGE_IS_DM => false,

						self::MESSAGE_EMOJI => $obj->emoji->name,
						self::MESSAGE_EMOJI_ID => $obj->emoji->id,

						//'md5' => md5($message["content"]),
					]);
				}else{
					$this->D2P_Queue[] = serialize([
						self::MESSAGE_TYPE => self::MESSAGE_TYPE_EMOJI_ADD,//

						self::MESSAGE_ID => $obj->message_id,
						self::MESSAGE_USERID => $obj->user_id,

						self::MESSAGE_IS_MYSELF => ($obj->user_id === $botUserId),
						self::MESSAGE_IS_DM => true,

						self::MESSAGE_EMOJI => $obj->emoji->name,
						self::MESSAGE_EMOJI_ID => $obj->emoji->id,
					]);

				}
			});
			$discord->on("MESSAGE_REACTION_REMOVE",function(\stdClass $obj, Discord $discord) use ($botUserId){
				if(isset($obj->guild_id)){
					$this->D2P_Queue[] = serialize([
						self::MESSAGE_TYPE => self::MESSAGE_TYPE_EMOJI_REMOVE,//

						self::MESSAGE_ID => $obj->message_id,
						self::MESSAGE_GUILDID => $obj->guild_id,
						self::MESSAGE_CHANNELID => $obj->channel_id,
						self::MESSAGE_USERID => $obj->user_id,

						self::MESSAGE_IS_MYSELF => ($obj->user_id === $botUserId),
						self::MESSAGE_IS_DM => false,

						self::MESSAGE_EMOJI => $obj->emoji->name,
						self::MESSAGE_EMOJI_ID => $obj->emoji->id,

						//'md5' => md5($message["content"]),
					]);
				}else{
					$this->D2P_Queue[] = serialize([
						self::MESSAGE_TYPE => self::MESSAGE_TYPE_EMOJI_REMOVE,//

						self::MESSAGE_ID => $obj->message_id,
						self::MESSAGE_CHANNELID => $obj->channel_id,
						self::MESSAGE_USERID => $obj->user_id,

						self::MESSAGE_IS_MYSELF => ($obj->user_id === $botUserId),
						self::MESSAGE_IS_DM => true,

						self::MESSAGE_EMOJI => $obj->emoji->name,
						self::MESSAGE_EMOJI_ID => $obj->emoji->id,
					]);
				}
			});
			$discord->on("GUILD_MEMBER_ADD",function(Member $member,Discord $discord) use ($botUserId){
				var_dump("test");
				$this->D2P_Queue[] = serialize([
					self::MESSAGE_TYPE => self::MESSAGE_TYPE_MEMBER_ADD,//

					self::MESSAGE_GUILDID => $member->guild_id,
					self::MESSAGE_USERNAME => $member->user->username,
					self::MESSAGE_USERID => $member->user->id,

					self::MESSAGE_ISBOT => $member->user->bot ?? false,
					self::MESSAGE_IS_MYSELF => ($member->user->id === $botUserId),

					self::MESSAGE_DISCRIMINATOR => $member->user->discriminator,
				]);
			});
			$discord->on("GUILD_MEMBER_REMOVE",function(Member $member,Discord $discord) use ($botUserId){
				$this->D2P_Queue[] = serialize([
					self::MESSAGE_TYPE => self::MESSAGE_TYPE_MEMBER_REMOVE,//

					self::MESSAGE_GUILDID => $member->guild_id,
					self::MESSAGE_USERNAME => $member->user->username,
					self::MESSAGE_USERID => $member->user->id,

					self::MESSAGE_ISBOT => $member->user->bot ?? false,
					self::MESSAGE_IS_MYSELF => ($member->user->id === $botUserId),

					self::MESSAGE_DISCRIMINATOR => $member->user->discriminator,
					//joined_at...?
				]);
			});
			$discord->on("MESSAGE_DELETE",function(\stdClass $obj, Discord $discord) use ($botUserId){
				$this->D2P_Queue[] = serialize([
					self::MESSAGE_TYPE => self::MESSAGE_TYPE_DELETE,//

					self::MESSAGE_ID => $obj->id,
					self::MESSAGE_CHANNELID => $obj->channel_id,
					self::MESSAGE_GUILDID => $obj->guild_id,
				]);
			});
		});
		$discord->run();
	}

	public function messageUpdate($discord, string $messageId, $channel, string $contents){
		$channel = $channel instanceof Channel ? $channel : $discord->factory(Channel::class, ['id' => $channel]);
		$message = $discord->factory(Message::class, ['id' => $messageId]);
		$channel->editMessage($message,$contents);
	}

	public function task($discord, $channel){
		if(!$this->started) return;
		$send = "";

		while(count($this->P2D_Queue) > 0){
			$message = unserialize($this->P2D_Queue->shift());
			switch($message[self::MESSAGE_TYPE]){
				case self::MESSAGE_TYPE_SEND:
					$channel = $message[self::MESSAGE_CHANNELID] === null ? $channel : $discord->factory(Channel::class, ['id' => $message[self::MESSAGE_CHANNELID]]);
					$channelId = $channel->id;
					$send = preg_replace(['/\]0;.*\%/', '/[\x07]/', "/Server thread\//"], '', TextFormat::clean(substr($message[self::MESSAGE], 0, 1900)));//processtile,ANSIコードの削除を実施致します...
					if($send === "") continue 2;
					//$send .= $message;//."\n";
					//if(strlen($send) >= 1800){
					$channel->sendMessage($send, false, $message[self::MESSAGE_EMBEDS] ?? null);//message, tts message, embeds message
					break;
				case self::MESSAGE_TYPE_EDIT:
					$this->messageUpdate($discord, $message[self::MESSAGE_ID], $message[self::MESSAGE_CHANNELID] ?? $channel, $message[self::MESSAGE]);
					break;
			}
			//$message = unserialize($this->P2D_Queue->shift());//

		}

	}

	//===メインスレッド呼び出し専用関数にてございます...===
	public function shutdown(){
		$this->stopped = true;
	}

	public function replyMessage(string $userId, ?string $channelId, string $message, ?array $embeds = null){
		$this->sendMessage("<@".$userId.">, ".$message, $channelId, $embeds);
	}

	public function sendMessage(string $message, ?string $channelId = null, ?array $embeds = null){
		//var_dump("send".$message);
		$this->P2D_Queue[] = serialize([
			self::MESSAGE_TYPE => self::MESSAGE_TYPE_SEND,
			self::MESSAGE => $message,
			self::MESSAGE_CHANNELID => $channelId,
			self::MESSAGE_EMBEDS => $embeds,
		]);
	}

	public function editMessage(string $messageId, ?string $channelId = null, string $message){
		$this->P2D_Queue[] = serialize([
			self::MESSAGE_TYPE => self::MESSAGE_TYPE_EDIT,
			self::MESSAGE => $message,
			self::MESSAGE_ID => $messageId,
			self::MESSAGE_CHANNELID => $channelId,
			//self::MESSAGE_EMBEDS => $embeds,
		]);
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
