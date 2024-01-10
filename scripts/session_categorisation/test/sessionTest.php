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

class ajwp_SessionTest extends ajwp_Session
{	
	protected function makeEventFromSearch ($searchText, $timeStamp)
	{
		echo "Search: $searchText, $timeStamp\n";
		return;
		
		$event = ajwp_SessionEvent::fromSearch($searchText, $timeStamp);
		return $event;
	}
	
	protected function makeEventFromSupplier($supplierId, $timeStamp)
	{
		echo "Supplier: $supplierId, $timeStamp\n";
		return;
		
		$event = ajwp_SessionEvent::fromSupplier($supplierId, $timeStamp);
		return $event;
	}
	
	protected function makeEventFromInquiry(array $supplierIdArr, $timeStamp)
	{
		echo "Inquiry: [ ". join(', ', $supplierIdArr) . " ], $timeStamp\n";
		return;
		
		$event = ajwp_SessionEvent::fromInquiry($supplierIdArr, $timeStamp);
		return $event;
	}
}

$o = new ajwp_SessionImpl(1);
$r = $o->getEvents();
//var_dump($r);
