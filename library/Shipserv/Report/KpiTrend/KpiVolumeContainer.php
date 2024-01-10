<?php

class Shipserv_Report_KpiTrend_KpiVolumeContainer {
	public $date;
	public $rfqCount = 0;
	public $qotCount = 0;
	public $poCount = 0;
	public $pocCount = 0;
	public $declinedRfqCount = 0;
	public $overallResponseRate = 0;
	public $quoteRate = 0;
	public $declineRate = 0;
	public $winRate = 0;

	function __construct($yearFrom, $monthFrom, $res)
	{
		//$this->date = $yearFrom.'/'.sprintf("%02.0f", $monthFrom);
		$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		$this->date = $monthNames[ (int)$monthFrom -1 ].' '.$yearFrom;
		foreach ($res as $rec) {
			if ($rec['VOLUME_YEAR'] == $yearFrom && $rec['VOLUME_MONTH'] == $monthFrom) {
				$this->rfqCount = $rec['RFQ_COUNT'];
				$this->qotCount = $rec['QOT_COUNT'];
				$this->poCount = $rec['PO_COUNT'];
				$this->pocCount = $rec['POC_COUNT'];
				$this->declinedRfqCount = $rec['DECLINED_RFQ_COUNT'];
				//todo calculate quote Rate, Win Rate, ....
				if ($this->rfqCount != 0) {
					$this->overallResponseRate = sprintf("%03.2f", ($this->qotCount + $this->declinedRfqCount) / $this->rfqCount * 100);
					$this->quoteRate =  sprintf("%03.2f",$this->qotCount  / $this->rfqCount * 100);
					$this->declineRate = sprintf("%03.2f",$this->declinedRfqCount  / $this->rfqCount * 100);
				}

				if ($this->qotCount != 0) {
					$this->winRate = sprintf("%03.2f",$this->poCount  / $this->qotCount * 100);
				}
			}
		}
	}
}