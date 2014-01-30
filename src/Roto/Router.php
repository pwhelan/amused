<?php

namespace Roto;

class Router
{
	protected $routes;
	
	public function __construct()
	{
		$this->routes = [];
	}
	
	public function __call($func, $args)
	{
		$method = strtoupper($func);
		if (!isset($this->routes[$method])) {
			$this->routes[$method] = [];
		}
		$this->routes[$method][] = new Route($args[0], $args[1]);
	}
	
	public function request($route, $callback)
	{
		$this->routes['REQUEST'][] = new Route($route, $callback);
	}
	
	public function route()
	{
		$self = $this;
		
		return function ($request, $response) use ($self) {
			
			$match = $self->resolveRoute($request);
			if ($match instanceof Match) {
				return $match((object)['request' => $request, 'response' => $response]);
			}
			else {
				$response->writeHead(404, array('Content-Type' => 'text/plain'));
				$response->end("Not Found");
			}
		};
	}
	
	public function resolveRoute($request)
	{
		$method = strtoupper($request->getMethod());
		if (isset($this->routes[$method])) {
			foreach ($this->routes[$method] as $route) {
				$match = $route->match($request->getPath());
				if ($match) {
					return $match;
				}
			}
		}
		if (isset($this->routes['REQUEST'])) {
			foreach ($this->routes['REQUEST'] as $route) {
				$match = $route->match($request->getPath());
				if ($match) {
					return $match;
				}
			}
		}
		
		return false;
	}
}
