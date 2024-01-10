<?php

require_once '../visits.php';

class ajwp_DummyIterator implements ajwp_IteratorI
{
	private $arr;
	private $nxtPos = 0;
	
	public function __construct (array $arr)
	{
		$this->arr = array_values($arr);
	}
	
	public function next ()
	{
		if (! array_key_exists($this->nxtPos, $this->arr))
		{
			return false;
		}
		
		return $this->arr[$this->nxtPos++];
	}
}

class ajwp_SessionEventIteratorTest extends ajwp_SessionEventIterator
{
	private static $counter = 0;
	
	protected function makeIteratorFromSql($sql)
	{
		$myCounter = self::$counter++;
		
		if ($myCounter == 0)
		{
			$forIt = array (
				array('MARKER' => 'A', 'TIME_STR' => '2010-02-10 12:00:00'),
				array('MARKER' => 'A', 'TIME_STR' => '2010-02-11 12:00:00'),
				array('MARKER' => 'A', 'TIME_STR' => '2010-02-12 12:00:00'),
				array('MARKER' => 'A', 'TIME_STR' => '2010-02-13 12:00:00'),
			);
		}
		elseif ($myCounter == 1)
		{
			$forIt = array (
				array('MARKER' => 'B', 'TIME_STR' => '2010-02-10 09:00:00'),
				array('MARKER' => 'B', 'TIME_STR' => '2010-02-11 15:00:00'),
				array('MARKER' => 'B', 'TIME_STR' => '2010-02-12 15:00:00'),
				array('MARKER' => 'B', 'TIME_STR' => '2010-02-13 15:00:00'),
			);
		}
		elseif ($myCounter == 2)
		{
			$forIt = array (
				array('MARKER' => 'C', 'TIME_STR' => '2010-02-10 10:00:00'),
				array('MARKER' => 'C', 'TIME_STR' => '2010-02-11 14:00:00'),
				array('MARKER' => 'C', 'TIME_STR' => '2010-02-12 17:00:00'),
				array('MARKER' => 'C', 'TIME_STR' => '2010-02-13 14:00:00'),
			);
		}
		else
		{
			throw new Exception();
		}
		
		return new ajwp_DummyIterator($forIt);
	}
}

$it = new ajwp_SessionEventIteratorImpl();
while ($row = $it->next())
{
	print_r($row);
}
