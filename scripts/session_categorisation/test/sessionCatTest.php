<?php

require_once '../visits.php';

class ajwp_SessionTest implements ajwp_SessionI
{
	public function getEvents()
	{
		$events = array();
		
		$e = new ajwp_SessionEvent(10);
		$e->addCategory(1);
		//$e->addCategory(2);
		$events[] = $e;
		
		$e = new ajwp_SessionEvent(20);
		$e->addCategory(1);
		//$e->addCategory(2);
		$events[] = $e;
		
		return $events;
	}
}

$o = new ajwp_SessionToCat();
echo $o->categoriseSession(new ajwp_SessionTest(), $hist); echo "\n";
print_r($hist);
