<?php

namespace Amused\Query;

class Builder
{
	private $builder;
	private $connection;
	private $driver;
	
	public function __construct(\React\MySQL\Connection $driver)
	{
		// Fake Date for Pixie DB...
		$config = array(
			'driver'    => 'Amused\\FakePDO', // Db driver
			'host'      => 'localhost',
			'database'  => 'your-database',
			'username'  => 'root',
			'password'  => 'your-password',
			'charset'   => 'utf8', // Optional
			'collation' => 'utf8_unicode_ci', // Optional
			'prefix'    => '', // Table prefix, optional
		);
		
		$this->connection = new \Pixie\Connection('mysqlasync', $config);
		$this->builder = $this->connection->getQueryBuilder();
		$this->driver = $driver;
	}
	
	public function __call($func, $args)
	{
		switch ($func) {
			case 'first':
				$this->builder->limit(1);
			
			case 'delete':
			case 'get':
				$deferred = new \React\Promise\Deferred;
				
				if ($args[0] instanceof \Closure) {
					$argclosure = array_shift($args);
				}
				else {
					$argclosure = function() {};
				}
				
				$closure = function ($results) use ($deferred, $argclosure) {
					if ($results->hasError()) {
						//throw new \Exception($command->getError()->)
						print_r($command->getError());
					}
					$qresults = new Result($results);
					$argclosure($qresults);
					$deferred->resolve($qresults);
				};
				
				$queryObject = $this->builder->getQuery(($func == 'get' ? 'select' : $func));
				$this->driver->query(
					/// WHY? I don't know...
					" ".$queryObject->getSql(),
					$queryObject->getBindings(),
					$closure
				);
				
				/*
				$command->on('result', function ($row) use ($deferred) {
					$deferred->progress(new \Amused\Query\Row($row));
				});
				*/
				
				return $deferred;
				
			case 'into':
				$this->builder->from($args);
				return $this;
			
			case 'update':
			case 'insert':
				$deferred = new \React\Promise\Deferred;
				
				
				$data = array_shift($args);
				$queryObject = $this->builder->getQuery($func, $data);
				
				if (count($args) > 0) {
					$closure = array_shift($args);
					if (!$closure instanceof \Closure) {
						$argclosure = function() {};
					}
					else {
						$argclosure = $closure;
					}
				}
				else {
					$argclosure = function() {};
				}
				
				$closure = function ($results) use ($deferred, $argclosure) {
					$qresults = new Result($results);
					$argclosure($qresults);
					$deferred->resolve($qresults);
				};
				
				$cargs = [" ".$queryObject->getSql()];
				
				foreach ($queryObject->getBindings() as $binding) {
					$cargs[] = $binding;
				}
				
				
				$cargs[] = $closure;
				$command = call_user_func_array([$this->driver, 'query'], $cargs);
				
				return $deferred;
			
			default:
				$rc = call_user_func_array([$this->builder, $func], $args);
				return $this;
		}
		
	}
}