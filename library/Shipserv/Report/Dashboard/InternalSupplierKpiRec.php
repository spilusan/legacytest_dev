<?php 
/**
* Class for store record to Internal KPI Dasboard, and do basic calculation
*/
class Shipserv_Report_Dashboard_InternalSupplierKpiRec 
{
	public $avgMonetisation = 0;
	public $avgPayingMonetisation = 0;
	public $dat = null;
	public $dispDat = null;
	public $payingTotalGmv = 0;
	public $payingTotalRevenue = 0;
	public $spbCount = 0;
	public $spbInterface = 0;
	public $totalGmv = 0;
	public $totalRevenue = 0;
	public $annulisedRunRate = 0;

	/**
	* Requires an array af record, see fields below, or if null, no initial values are set
	* @param array $rec
	*/
	function __construct( $rec = null )
	{
		$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		$this->date = $monthNames[ (int)$monthFrom -1 ].' '.$yearFrom;
		if ($rec !== null)
		{
			if (is_object($rec)) {
				$this->avgMonetisation = $rec->avgMonetisation;
				$this->avgPayingMonetisation = $rec->avgPayingMonetisation;
				$this->dat = $rec->dat;
				$this->dispDat = $this->getConvertedMonthName($rec->dat);
				$this->payingTotalGmv = $rec->payingTotalGmv;
				$this->payingTotalRevenue = $rec->payingTotalRevenue;
				$this->spbCount = $rec->spbCount;
				$this->spbInterface = $rec->spbInterface;
				$this->totalGmv = $rec->totalGmv;
				$this->totalRevenue = $rec->totalRevenue;
				$this->annulisedRunRate = $this->annulisedRunRate;

			} else {

				$this->avgMonetisation = round($rec['AVG_MONETISATION'], 2);
				$this->avgPayingMonetisation = round($rec['AVG_PAYING_MONETISATION'], 2);
				$this->dat = $rec['DAT'];
				$this->dispDat = $this->getConvertedMonthName($rec['DAT']);
				$this->payingTotalGmv = round($rec['PAYING_TOTAL_GMV']);
				$this->payingTotalRevenue = round($rec['PAYING_TOTAL_REVENUE']);
				$this->spbCount = $rec['SPB_COUNT'];
				$this->spbInterface = $rec['SPB_INTERFACE'];
				$this->totalGmv = round($rec['TOTAL_GMV']);
				$this->totalRevenue = round($rec['TOTAL_REVENUE']);
				$this->annulisedRunRate = $this->totalGmv * 12;
			}
		}

	}

	/**
	* Add two Shipserv_Report_Dashboard_InternalSupplierKpiRec together for createing summaries
	* @param Shipserv_Report_Dashboard_InternalSupplierKpiRec $kpiRec
	* @return object self
	*/
	public function Add(Shipserv_Report_Dashboard_InternalSupplierKpiRec $kpiRec)
	{
			$this->payingTotalGmv += $kpiRec->payingTotalGmv;
			$this->payingTotalRevenue += $kpiRec->payingTotalRevenue;
			$this->spbCount += $kpiRec->spbCount;
			$this->spbInterface += $kpiRec->spbInterface;
			$this->totalGmv += $kpiRec->totalGmv;
			$this->totalRevenue += $kpiRec->totalRevenue;
			$this->dat = $kpiRec->dat;
			$this->dispDat = $kpiRec->dispDat;
			return self;
	}

	public function getConvertedMonthName( $name )
	{
		$parts = explode("-",$name);
		$monthNames = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
		return $monthNames[ (int)$parts[1] -1 ].' '.$parts[0];
	}

}