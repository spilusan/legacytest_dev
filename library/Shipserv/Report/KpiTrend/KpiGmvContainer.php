<?php

class Shipserv_Report_KpiTrend_KpiGmvContainer {

	public $date;
	public  $sir2GMV;
	public  $sir2ORD;
	public  $sir2Conversion;
	private $shortDate;

	function __construct($yearFrom, $monthFrom)
	{
		//$this->date = $yearFrom.'/'.sprintf("%02.0f", $monthFrom);
		$this->shortDate = $yearFrom.sprintf('%02d',$monthFrom);
		$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		$this->date = $monthNames[ (int)$monthFrom -1 ].' '.$yearFrom;

	}

	public function getShortDate()
	{
		return $this->shortDate;
	}
}