<?php

namespace Roto;

class Match
{
	private $callback;
	private $params;
	private $reflection;
	
	public function __construct(\Closure $callback, $params = [])
	{
		$this->callback = $callback;
		$this->params = $params;
		$this->reflection = new \ReflectionFunction($callback);
	}
	
	public function __invoke($self)
	{
		$callback = $this->callback->bindTo($self);
		$args = [];
		
		foreach ($this->reflection->getParameters() as $idx => $parameter) {
			
			if (isset($this->params[$parameter->getName()])) {
				$args[$parameter->getPosition()] = $this->params[$parameter->getName()];
			}
		}
		
		return call_user_func_array($callback, $args);
	}
}
