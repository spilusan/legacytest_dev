<?php
/**
* Record representation for KPI Direct Voulme
*/
class Shipserv_Report_KpiTrend_KpiDirectVolumeContainer
{
	public $date;
	public $rfqCount = 0;
	public $qotCount = 0;
	public $poCount = 0;
	public $pocCount = 0;

	/**
	* Construct values for the record according to the resource record
	* @param integer $yearFrom  The year 
	* @param integer $monthFrom The month
	* @param array   $res       The record array
	*
	* @return object
	*/
	function __construct($yearFrom, $monthFrom, $res)
	{
		//$this->date = $yearFrom.'/'.sprintf("%02.0f", $monthFrom);
		$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		$this->date = $monthNames[ (int)$monthFrom -1 ].' '.$yearFrom;
		foreach ($res as $rec) {
			if ($rec['DOC_YEAR'] == $yearFrom && $rec['DOC_MONTH'] == $monthFrom) {
				switch ($rec['DOC_TYPE']) {
					case 'RFQ':
						$this->rfqCount += $rec['DOC_COUNT'];
						break;
					case 'PRFQ':
						$this->rfqCount += $rec['DOC_COUNT'];
						break;	
					case 'QOT':
						$this->qotCount = $rec['DOC_COUNT'];
						break;
					case 'ORD':
						$this->poCount = $rec['DOC_COUNT'];
						break;
					case 'POC':
						$this->pocCount = $rec['DOC_COUNT'];
						break;
					
					default:
						break;
				}
	
			}
		}
	}
}