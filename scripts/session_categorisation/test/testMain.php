<?php

require_once 'lib/common.php';

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

class ajwp_DummyIterator2 implements ajwp_IteratorI
{
	private $i = 1;
	
	public function next ()
	{
		return new ajwp_SessionImpl($this->i++);
	}
}

class ajwp_MainTest extends ajwp_Main
{
	protected function makeSessionIterator ()
	{
		return new ajwp_DummyIterator2();
	}
}

$m = new ajwp_MainTest();
$m->run();
