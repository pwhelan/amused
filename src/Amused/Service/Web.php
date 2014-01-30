<?php

namespace Amused\Service;


class Web
{
	private $__loop;
	
	
	public function __construct()
	{
		$deferred = new \React\Promise\Deferred;
		$promise = $deferred->promise();
		
		$this->__loop = \React\EventLoop\Factory::create();
		
		
		$redis = new \Predis\Async\Client('tcp://127.0.0.1:6379', $this->__loop);
		$redis->connect(function($client) use ($deferred) {
			print "Connected Web Server to Redis\n";
			$deferred->resolve(true);
		});
		
		
		$socket = new \React\Socket\Server($this->__loop);
		$http = new \React\Http\Server($socket);
		
		
		$router = new \Roto\Router;
		
		$router->get('/scan', function() use ($redis) {
			
		});
		
		$http->on('request', $router->route());
		
		
		$promise->then(function() use ($socket) {
			$socket->listen(50333);
		});
		
	}
	
	public function run()
	{
		$this->__loop->run();		
	}
}
