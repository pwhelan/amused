<?php

namespace Amused\Service;

use Amused\Query\Result;

class Database
{
	private $__loop;
	
	
	public function __construct()
	{
		
		$redisconnected = new \React\Promise\Deferred;
		$mysqlconnected = new \React\Promise\Deferred;
		$promise = \React\Promise\When::all([
			$redisconnected->promise(),
			$mysqlconnected->promise()
		]);
		
		$this->__loop = \React\EventLoop\Factory::create();
		
		
		$redis = new \Predis\Async\Client('tcp://127.0.0.1:6379', $this->__loop);
		$redis->connect(function($client) use ($redisconnected) {
			print "Connected Web Server to Redis\n";
			$redisconnected->resolve($client);
		});
		
		//create a mysql connection for executing queries
		$mysql = new \React\MySQL\Connection($this->__loop, [
			'dbname' => 'amused',
			'user'   => 'root',
			'passwd' => ''
		]);
		
		$mysql->connect(function() use ($mysqlconnected) {
			$mysqlconnected->resolve(true);
		});
		
		
		$promise->then(function($results) use ($redis, $mysql) {
			
			$builder = new \Amused\Query\Builder($mysql);
			
			$redis->brpop(
				"musicwatch:library:scanned", 
				0,
				$this->_doSave($redis, $builder)
			);
			
		});
		
	}
	
	private function _doSave($redis, $builder)
	{
		return function ($message) use ($redis, $builder)
		{
			$song = json_decode($message[1]);
			if (empty($song)) {
				$redis->brpop(
					"musicwatch:library:scanned", 
					0,
					$this->_doSave($redis, $builder)
				);
			}
			
			$trackdata = array_only(
				(array)$song->tags,
				['artist', 'title', 'album', 'date', 'bpm']
			);
			
			$trackdata['filename'] = $song->filenamepath;
			
			$insert = $builder->into('tracks')
					->insert($trackdata);
			
			$insert->then(
				function (Result $result) use ($song, $redis, $builder) {
					/* 
					[artist] => Liber Chaos
					[title] => The Unabated Aum (Konvndrvm's Ether Bath Rmx)
					[year] => 1389352974
					[album] => CetaCreate - Benefit for CinderVomit Vol. 3
					[date] => 2012
					[comment] => Visit http://jellyfishfrequency.bandcamp.com
					[tracknumber] => 9
					[albumartist] => VA
					[description] => Visit http://jellyfishfrequency.bandcamp.com
					[compilation] => 1
					[rating:banshee] => 0.5
					 */
					$song->id = $result->lastInsertId();
					$redis->lpush("musicwatch:library:saved", json_encode($song));
					
					$redis->brpop(
						"musicwatch:library:scanned", 
						0,
						$this->_doSave($redis, $builder)
					);
				}
			);
			
		};
	}
	
	public function run()
	{
		$this->__loop->run();		
	}
}
