<?php

class Shipserv_Report_KpiTrend_KpiGmvContainer {

	public $date;
	public $totalGmv = 0;
	public $totalRevenue = 0;
	public $payingRotalGmv = 0;
	public $payingTotalRevenue = 0;
	public $avgMonetisation = 0;
	public $avgPayingMonetisation = 0;
	public $totalOrd = 0;
	public $orderConversion = 0;
	public $totalOrdNoDirect = 0;
	public $totalGmvNoDirect = 0;
	public $orderConversionNoDirect = 0;
	private $shortDate;

	function __construct($yearFrom, $monthFrom, $directOrders, $competitiveOrders, $nonCompetitiveOrders)
	{
		//$this->date = $yearFrom.'/'.sprintf("%02.0f", $monthFrom);
		$this->shortDate = $yearFrom.sprintf('%02d',$monthFrom);
		$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		$this->date = $monthNames[ (int)$monthFrom -1 ].' '.$yearFrom;
		foreach ($directOrders as $rec) {
			if ($rec['DATE_YEAR'] == $yearFrom && $rec['DATE_MONTH'] == $monthFrom) {
				$this->totalGmv += $rec['QOTVALUEDIRECTNORFQ'];
				$this->totalOrd += $rec['POVALUEDIRECTNORFQ'];
			}
		}

		foreach ($competitiveOrders as $rec) {
			if ($rec['DATE_YEAR'] == $yearFrom && $rec['DATE_MONTH'] == $monthFrom) {
				$this->totalGmv += $rec['QOTVALUE'];
				$this->totalOrd += $rec['POVALUE'];

			}
		}

		foreach ($nonCompetitiveOrders as $rec) {
			if ($rec['DATE_YEAR'] == $yearFrom && $rec['DATE_MONTH'] == $monthFrom) {
				$this->totalGmv += $rec['SUM_QOT_PRICE'];
				$this->totalOrd += $rec['SUM_PRICE'];
			}

			if ($this->totalGmv != 0 ) {
					$this->orderConversion =  $this->totalOrd  / $this->totalGmv  * 100;
				}	
				if ($this->totalGmvNoDirect != 0 ) {
					$this->orderConversionNoDirect =  $this->totalOrdNoDirect / $this->totalGmvNoDirect * 100;
				}
		}

	}

	public function getShortDate()
	{
		return $this->shortDate;
	}
}