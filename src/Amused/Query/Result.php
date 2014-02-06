<?php

namespace Amused\Query;

use \Illuminate\Support\Collection;

class Result
{
	protected $rows;
	protected $affected;
	protected $insertId;
	protected $result;
	
	public function __construct(\React\MySQL\Commands\QueryCommand $result)
	{
		$this->result = $result;
		
		if (isset($result->resultRows)) {
			$this->rows = new Collection(
				array_map(
					function ($row) {
						return new Row($row);
					},
					$result->resultRows
				)
			);
		}
		else {
			$this->affected = $result->affectedRows;
			$this->insertId = $result->insertId;
		}
	}
	
	public function numRows()
	{
		if ($this->rows instanceof Collection) {
			return $this->rows->count();
		}
		else {
			return $this->affected;
		}
	}
	
	public function lastInsertId()
	{
		return $this->insertId;
	}
	
	public function __call($func, $args)
	{
		return call_user_func_array([$this->rows, $func], $args);
	}
}