<?php
class Shipserv_Report_Dashboard extends Shipserv_Object
{
	protected $memcacheTTL = 3600;
	public $startDate;
	public $endDate;
	
	public function setParams( $params = null)
	{
		$this->params = $params;
	}
	
	public function getStartDate()
	{
		return $this->startDate;
	}
	
	public function getEndDate()
	{
		return $this->endDate;
	}
	
	
}
