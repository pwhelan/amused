<?php

namespace Amused\Service;

use \GetId3\GetId3Core as GetId3;

class Scan
{
	private $__socket = null;
	private $__id3 = null;
	private $__redis = null;
	private $__loop = null;
	private $__inotify = null;
	private $__pubsub = null;
	private static $_instance = null;
	
	public function __construct()
	{
		$this->__id3 = new GetID3;
		$this->__id3
			->setOptionMD5Data(true)
			->setEncoding('UTF-8');
		
		// TODO: use promises to keep server from going up before connecting to redis..
		$client = new \Predis\Client(array(
			'host'	=> '127.0.0.1',
			'port'	=> 6379,
			'read_write_timeout' => 0
		));
		
		// Initialize a new pubsub context
		$this->__pubsub = $client->pubsub();
		$this->__pubsub->subscribe('musicwatch:scandir');
		
		$redis = new \Predis\Client(array(
			'host'	=> '127.0.0.1',
			'port'	=> 6379,
			'read_write_timeout' => 0
		));
		
		$this->__redis = $redis;
		
		if (self::$_instance == NULL) {
			self::$_instance = $this;
		}
	}
	
	public function getTags($path)
	{
		$info = $this->__id3->analyze($path);
		if (!$info) {
			print "ID3 Failed to Analyze: {$path}\n";
			return NULL;
		}
		
		$tags = array();
		
		
		if (isset($info['tags'])) {
			foreach(array('vorbiscomment', 'id3v2', 'id3v1') as $format) {
				if (isset($info['tags'][$format])) {
					$tag = array_map(function($item) {
						return $item[0];
					},
					$info['tags'][$format]);
					
					if (!isset($tag['artist']) && isset($tag['albumartist'])) {
						$tag['artist'] = $tag['albumartist'];
					}
					
					$tags[] = $tag;
				}
			}
		}
		
		if (preg_match('/^([^\d]*)([\d]{0,2})[\s\.\-]+([^\-]+)[\s\-]+(.+)\.(flac|mp3)$/', basename($path), $m)) {
			$paths = explode('/', dirname($path));
			
			if (is_numeric($paths[4])) {
				$year = $paths[4];
				$album = $paths[5];
			}
			else {
				$stat = stat($path);
				$year = $stat['ctime'];
				$album = $path[4];
			}
			
			$tags[] = array(
				'artist'	=> trim($m[3]), 
				'title'		=> trim($m[4]),
				'year'		=> $year,
				'album'		=> $album
			);
		}
		
		if (count($tags) > 0) {
			$tags = (object)call_user_func_array(
				'array_merge', 
				array_reverse($tags)
			);
		}
		else {
			$tags = new \stdClass;
		}
		
		return (object)array(
			'tags' => $tags, 
			'id3' => $info
		);
	}
	
	public static function instance()
	{
		if (self::$_instance == NULL) {
			self::$_instance = new Scan;
		}
		return self::$_instance;
	}
	
	private function __queue($file)
	{
		switch($file->getExtension()) {
		case 'mp3':
		case 'flc':
		case 'flac':
			try {
				$info = self::instance()->getTags($file->getPathname());
				if ($info == NULL) {
					print 'NO ID3 for = '.$path."\n";
					return;
				}
				foreach(array('album', 'artist', 'title', 'year') as $tag) {
					if (!isset($info->tags->$tag)) {
						print "INCOMPLETE TAGS FOR = ".$file->getFilename()."\n";
						//print_r($info);
						return;
					}
				}
				
				if (!isset($info->id3['md5_data'])) {
					print "NO MD5 sum: ".$file->getPathname()."\n";
					print_r($info->id3);
					die();
					return;
				}
				
				$info->md5 = $info->id3['md5_data'];
				$info->filenamepath = $info->id3['filenamepath'];
				unset($info->id3);
				
				$json = json_encode($info);
				if (strlen($json) < 0) {
					print "BAD JSON\n";
					return;
				}
				
				//print "SENDING....\n";
				$this->__redis->rpush(
					"musicwatch:library:scanned", 
					$json
				);
				//if (isset($info->titles) && count($info->titles) > 1) {
				//	$info->title = $info->titles[array_keys($info->titles[0])];
				//}
				
				//print "TRACK: {$info->tags->year} {$info->tags->album} . {$info->tags->artist} - {$info->tags->title}\n";
				//print_r($info);
				//print "TRACK: {$info->tags->id3v1->"
			}
			catch (\Exception $e) {
				print "ERROR: unable to read {$path}, ".$e->getMessage()."\n";
			}
			//print_r($info);
			//$this->__inotify->add($dir->getPathname(), IN_MODIFY);
			//print "FILE = ".$d->getFilename()."\n";
		}
	}
	
	public function run()
	{
		print "Scanner Online\n";
		
		while(1) {
			foreach($this->__pubsub as $message) {
				switch($message->kind) {
				case 'subscribe':
					print "Subscribed to Scan {$message->channel}\n";
					break;
				case 'message':
					if ($message->channel == 'musicwatch:scandir') {
						print "SCANNING DIRECTORY = {$message->payload}\n";
						$this->scan($message->payload);
					}
				}
			}
		}
	}
	
	private function __dirwalk($dir)
	{
		foreach($dir as $d) {
			if (!$d->isDir()) {
				switch($d->getExtension()) {
				case 'mp3':
				case 'flc':
				case 'flac':
					$this->__queue($d);
				}
			}
			else {
				$this->__queue($d);
				$this->__dirwalk($dir->getChildren());
			}
		}
	}
	
	public function scan($dirname)
	{	
		print "SCANNING: {$dirname}\n";
		$dir = new \RecursiveDirectoryIterator($dirname, \FilesystemIterator::SKIP_DOTS);
		$this->__dirwalk($dir);
		print "DONE SCAN\n";
	}
}
