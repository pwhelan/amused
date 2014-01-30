<?php

namespace Amused\Service;


class Watch
{
	private $__socket = null;
	private $__redis = null;
	private $__loop = null;
	protected static $_instance = null;
	
	public function __construct()
	{
		self::$_instance = $this;
		
		
		$this->__socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
		socket_set_option($this->__socket, SOL_SOCKET, SO_BROADCAST, 1); 
		
		$this->__loop = \React\EventLoop\Factory::create();
		$this->__inotify = new \MKraemer\ReactInotify\Inotify($this->__loop);
		
		$this->__redis = new \Predis\Async\Client('tcp://127.0.0.1:6379', $this->__loop);
		
		
		$this->__redis->connect(function($client) {
			print "Connected Music Watch to Redis\n";
			$client->brpoplpush(
				"musicwatch:library:scanned", 
				"musicwatch:library:sent",
				0,
				array($this, 'send')
			);
		});
		
		$this->__inotify->on(IN_CREATE, function ($path) {
			///$this->__queue(IN_CREATE, new SplFileInfo($path));
		});

		$this->__inotify->on(IN_DELETE, function ($path) {
			//$this->__queue(IN_DELETE, $path);
		});

		$this->__inotify->on(IN_MODIFY, function ($path) {
			//$this->__queue(IN_MODIFY, $path);
		});

		$this->__inotify->on(IN_MOVED_FROM, function ($path) {
			//$this->__queue(IN_MOVED_FROM, $path);
		});

		$this->__inotify->on(IN_MOVED_TO, function ($path) {
			//$this->__queue(IN_MOVED_TO, $path);
		});
	}
	
	public static function sendMessage($message)
	{
		self::instance()->send($message);
	}
	
	public function send($message)
	{
		print "RPOPLPUSHED\n";
		print "==========\n";
		print_r($message);
		print "==========\n";
		
		try {
			$song = json_decode($message);
			$info = new \SplFileInfo($song->filenamepath);
			
			if ($info->isDir()) {
				$this->__inotify->add($info->getPathname(), 
					IN_CREATE | IN_DELETE | IN_MOVED_FROM | IN_MOVED_TO);
			}
			else {
				$this->__inotify->add($info->getPathname(), 
					IN_MODIFY | IN_MOVED_FROM | IN_DELETE);
			}
			
			print "STORED SONG = {$song->md5}\n";
			
			$json = json_encode(array('song' => $song->md5));
			socket_sendto($this->__socket, $json, strlen($json), 0, 
				"192.168.1.255", 50333);
		}
		catch (\Exception $e) {
		}
		finally {
			$this->__redis->brpoplpush(
				"musicwatch:library:scanned", 
				"musicwatch:library:sent",
				0,
				array('MusicWatch', 'sendMessage')
			);
		}
		
	}
	
	public static function instance()
	{
		if (self::$_instance == NULL) {
			self::$_instance = new MusicWatch;
		}
		return self::$_instance;
	}
	
	public function run()
	{
		$this->__loop->run();
	}
}
