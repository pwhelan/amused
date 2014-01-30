<?php

namespace Roto;


// http://blog.sosedoff.com/2009/09/20/rails-like-php-url-router/

class Route
{
	public $pattern;
	private $conditions = [];
 	
 	// Add $conditions in a chainable call?
	public function __construct($pattern, $callback) 
	{
		$this->pattern = $pattern;
		$this->callback = $callback;
	}
 	
 	public function match($url)
 	{
 		//$this->params = [];
		//$this->conditions = $conditions;
		$p_names = []; 
		$p_values = [];
 		$params = [];
 		
		preg_match_all('@:([\w]+)@', $this->pattern, $p_names, PREG_PATTERN_ORDER);
		$p_names = $p_names[0];
 		
		$url_regex = preg_replace_callback('@:[\w]+@', [$this, 'regex_url'], $this->pattern);
		$url_regex .= '/?';
 		
 		$is_matched = false;
 		
 		if (preg_match('@^' . $url_regex . '$@', $url, $p_values)) {
			array_shift($p_values);
			foreach($p_names as $index => $value) $params[substr($value,1)] = urldecode($p_values[$index]);
			//foreach($target as $key => $value) $params[$key] = $value;
			
			$is_matched = true;
		}
 		
		unset($p_names); 
		unset($p_values);
		
		if ($is_matched) {
			return new Match($this->callback, $params);
		}
		
		return false;
 	}
 	
	function regex_url($matches) 
	{
		$key = str_replace(':', '', $matches[0]);
		if (array_key_exists($key, $this->conditions)) {
			return '('.$this->conditions[$key].')';
		} 
		else {
			return '([a-zA-Z0-9_\+\-%]+)';
		}
	}
}
