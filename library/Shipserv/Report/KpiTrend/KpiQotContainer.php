<?php

class Shipserv_Report_KpiTrend_KpiQotContainer {
	public $date;
	
	public $qotTotalCostUsd = 0;
	public $totalCostOrderedUsd = 0;
	public $orderRate = 0;
	private $shortDate;

	function __construct($yearFrom, $monthFrom, $res)
	{
		//$this->date = $yearFrom.'/'.sprintf("%02.0f", $monthFrom);
		$this->shortDate = $yearFrom.sprintf('%02d',$monthFrom);
		$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		$this->date = $monthNames[ (int)$monthFrom -1 ].' '.$yearFrom;
		foreach ($res as $rec) {
			if ($rec['DOC_YEAR'] == $yearFrom && $rec['DOC_MONTH'] == $monthFrom) {
				$this->qotTotalCostUsd = $rec['QOT_TOTAL_COST_USD'];
				$this->totalCostOrderedUsd= $rec['TOTAL_COST_ORDERED_USD'];
				if ($this->qotTotalCostUsd != 0) {	
					$this->orderRate = $this->totalCostOrderedUsd / $this->qotTotalCostUsd   * 100;
				}
			}
		}
	}

	public function getShortDate()
	{
		return $this->shortDate;
	}
}