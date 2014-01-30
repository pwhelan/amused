<?php

namespace Amused\Service;

use ICanBoogie\Inflector;
use \GetId3\GetId3Core as GetId3;
use Monolog\Logger;
use Monolog\Handler\NullHandler;
use FFMpeg\FFMpeg;


class Encode
{
	private $__queue = array();
	private $__queue_backlog = array();
	private static $__instance = NULL;
	private $__queue_size = 4;
	private $__loop = NULL;
	
	public function __construct($queue_size = 4)
	{
		if (self::$__instance == NULL) {
			self::$__instance = $this;
		}
		
		$this->__loop = \React\EventLoop\Factory::create();
		
		$this->__loop->addPeriodicTimer(5, function () {
			while(($pid = pcntl_wait($status, WNOHANG)) > 0) {
				if (array_key_exists($pid, $this->__queue)) {
					$deferred = $this->__queue[$pid];
					unset($this->__queue[$pid]);
					
					$deferred->resolver()->resolve($status);
				}
			}
		});
	}
	
	public static function instance()
	{
		if (self::$__instance == NULL) {
			new self;
		}
		return self::$__instance;
	}
	
	
	public function doEncode()
	{
		
	}
	
	public function queueEncoder($format, $pathname)
	{
		if (count($this->__queue) < $this->__queue_size) {
			
			switch(($pid = pcntl_fork())) {
			default:
				$deferred = new \React\Promise\Deferred();
				$this->__queue[$pid] = $deferred;
			case 0:
				try {
					// Create a logger
					$logger = new Logger('MyLogger');
					$logger->pushHandler(new NullHandler());
					
					// You have to pass a Monolog logger
					// This logger provides some usefull infos about what's happening
					$ffmpeg = FFMpeg::load($logger);
					
					$tmp = tempnam('/tmp/musicmaster', 'MP3');
					
					$codec = new FFMpeg\Format\Audio\Mp3;
					$codec->setAudioKiloBitrate(320);
					
					// open a video, extract an image at second 5 and 12 then close
					$ffmpeg->open($pathname)
						->encode($codec, $tmp)
						->close();
					
				}
				catch (\Exception $e) {
					exit(-1);
				}
				exit(0);
				
			case -1:
				return FALSE;
			}
			
			// ADD TIMER if it's not there...
		}
		
		if (count($this->__queue_backlog) > 0) {
			$promise = \React\Promise\When::any($this->__queue);
			$promise->then(function() {
				$song = array_shift($this->__queued);
				self::encode($song);
			});
		}
	}
	
	public static function encode($format, $pathname)
	{
		self::instance()->doEncode($format, $pathname);
	}
	
	public function run()
	{
		$this->__loop->run();
	}
}
