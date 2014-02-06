<?php

namespace Amused\Query;

use Illuminate\Support\Contracts\ArrayableInterface;
use Illuminate\Support\Contracts\JsonableInterface;

class Row extends \ArrayObject implements ArrayableInterface, JsonableInterface
{
	public function __construct($row)
	{
		parent::__construct($row, \ArrayObject::ARRAY_AS_PROPS);
	}
	
	public function toArray()
	{
		return (array)$this;
	}
	
	public function toJson($options = 0)
	{
		return json_encode((array)$this);
	}
}
