<?php

/**
 * 
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Enquiry_Report extends Shipserv_Object implements Myshipserv_Converter
{
	const TMP_DIR = '/tmp/';
	
	public $enquiries;
	public $tnid;
	public $supplier;
	public $period;
	
	function __construct($tnid, $enquiries, $period)
	{
		$this->supplier = Shipserv_Supplier::fetch($tnid);
		$this->enquiries = $enquiries;	
		$this->statistic = $this->supplier->getEnquiriesStatistic( $period );
		$this->period = $period;
	}
	
	public function getCompany()
	{
		return $this->supplier;
	}
}