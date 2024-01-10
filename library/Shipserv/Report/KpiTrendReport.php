<?php

/**
 * KPI Trend Report
 * 
 * @package ShipServ
 * @author Attila O 
 * @copyright Copyright (c) 2015, ShipServ
 */
class Shipserv_Report_KpiTrendReport extends Shipserv_Object
{
	protected $startDate;
	protected $endDate;
	protected $tnid;
	protected $showChild;

	const 
		REPORT_VOLUME = 0,
		DIRECT_VOLUME = 1,
		REPORT_GMV = 2,
		REPORT_QUOTE = 3
		;

	public static function getInstance($tnid, $showChild, $dateFrom = null, $dateTo = null) {
    	$object = new self;
    	$object->setStartDate($dateFrom);
    	$object->setEndDate($dateTo);
    	$object->setTnid($tnid);
    	$object->showChild = $showChild;

        return $object;
    }


    /**
    * Set date range start date
    * @param string $startDate  The string representation of date
    */
	public function setStartDate( $startDate )
	{
		$this->startDate = $startDate;
	}

    /**
    * Set date range end date
    * @param strinf $endDate  The string representation of date
    */
	public function setEndDate( $endDate )
	{
		$this->endDate = $endDate;
	}

	/**
	* Setter for set TNID
	*/
	public function setTnid( $tnid )
	{
		$this->tnid = (int)$tnid;
	}
	 
	public function getReport($reportType)
	{
		switch ($reportType) {
			case self::REPORT_VOLUME:
				$service = new Shipserv_Report_KpiTrend_KpiVolume();
				break;
			case self::DIRECT_VOLUME:
				$service = new Shipserv_Report_KpiTrend_KpiDirectVolume();
				break;
			case self::REPORT_GMV:
				$service = new Shipserv_Report_KpiTrend_KpiGmv();
				break;
			case self::REPORT_QUOTE:
				$service = new Shipserv_Report_KpiTrend_KpiQot();
				break;
			default:
				throw new Myshipserv_Exception_MessagedException("Invalid report type", 500);
				break;
		}

		//Enable this two lines for testing
		//$effectiveDate = date('Ymd', strtotime("+1 months", strtotime($this->endDate)));
		//return $service->getResult($this->startDate, $effectiveDate, $this->tnid, $this->showChild);
		return $service->getResult($this->startDate, $this->endDate, $this->tnid, $this->showChild);
	}

}