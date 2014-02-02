<?php

namespace Amused\Service;


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
			
			$redis->brpop(
				"musicwatch:library:scanned", 
				0,
				$this->_doSave($redis, $mysql)
			);
			
		});
		
	}
	
	private function _doSave($redis, $mysql)
	{
		return function ($message) use ($redis, $mysql)
		{
			print "MESSAGE\n";
			print_r($message);
			
			$song = json_decode($message[1]);
			
			$mysql->query(
				'INSERT INTO tracks (artist, title) '.
				"VALUES ('{$song->tags->artist}', '{$song->tags->title}')", 
				function ($command, $mysql) use ($song, $redis) {
					//test whether the query was executed successfully
					if ($command->hasError()) {
						//error 
						$error = $command->getError();// get the error object, instance of Exception.
						$redis->lpush("musicwatch:library:error", json_encode($song));
					} else {
						$results = $command->affectedRows; //get the results
						$insertId  = $command->insertId; // get table fields
						
						$song->id = $insertId;
						$redis->lpush("musicwatch:library:saved", json_encode($song));
						
						print "SONG\n";
						print_r($song);
					}
					
					$redis->brpop(
						"musicwatch:library:scanned", 
						0,
						$this->_doSave($redis, $mysql)
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
