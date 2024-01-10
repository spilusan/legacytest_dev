<?php

/**
 * Supplier's Value Report (SVR)
 * 
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Report extends Shipserv_Object
{
	
	// store tradenet id
	protected $tnid;
	
	// store periods
	protected $startDate1;
	protected $endDate1;
	protected $startDate2;
	protected $endDate2;
	
	// store filters
	protected $ports;
	protected $categories;
	protected $brands;
	protected $products;
	protected $countries;
	
	// store data
	protected $data;
	
	// temporary folder
	const TMP_FOLDER = '/tmp/';


	/**
	 * Factory function is used to create full report of a supplier which means this report will pull up to 4 calls.
	 *
	 * @param int $tnid
	 * @param string $startDate1
	 * @param string $endDate1
	 * @param string $startDate2
	 * @param string $endDate2
	 * @param array $ports
	 * @param array $categories
	 * @param array $brands
	 * @param array $products
	 * @return object $object of this class
	 */
	public static function createFullSupplierValueReport($tnid, $startDate1 = "", $endDate1 = "", $startDate2 = "", $endDate2 = "", $ports = array(), $categories = array(), $brands = array(), $products = array() )
	{
		$data = null;
		$object = new self();
		
		$object->tnid = $tnid;
		
		$object->startDate1 = $startDate1;
		$object->endDate1 = $endDate1;
		$object->startDate2 = $startDate2;
		$object->endDate2 = $endDate2;
		
		$object->ports = $ports;
		$object->categories = $categories;
		$object->brands = $brands;
		$object->products = $products;
		
		// generate the supplier value report
		$reportForSupplier = self::getSupplierValueReport($tnid, $startDate1, $endDate1, $startDate2, $endDate2);

		// convert to array
		$data = $reportForSupplier->toArray();
		
		// pull data for the first period
    	$object->data["supplier"] = $data['supplier'];

    	// generate global average report for all suppliers that are matched by the filters
		$reportForAllSupplier = self::getGlobalValueReport($startDate1, $endDate1, $startDate2, $endDate2, $ports, $categories, $brands, $products);

		// convert to array
		$data = $reportForAllSupplier->toArray();
		
		// pull data for the first period
    	$object->data["general"] = $data['general'];
	    	

		return $object;
	}
	
	/**
	 * Get global report
	 * @param string $startDate1
	 * @param string $endDate1
	 * @param string $startDate2
	 * @param string $endDate2
	 * @param array $ports
	 * @param array $categories
	 * @param array $brands
	 * @param array $products
	 * @return object $object of this class
	 */
	public static function getGlobalValueReport( $startDate1 = "", $endDate1 = "", $startDate2 = "", $endDate2 = "", $ports = array(), $categories = array(), $brands = array(), $products = array(), $countries = array() )
	{
		$object = new self();
		
		$object->startDate1 = $startDate1;
		$object->endDate1 = $endDate1;
		$object->startDate2 = $startDate2;
		$object->endDate2 = $endDate2;
		
		$object->ports = $ports;
		$object->categories = $categories;
		$object->brands = $brands;
		$object->products = $products;    	
		$object->countries = $countries;

		if( $startDate1 == "" )
		{
			$object->data["general"] = $object->getDataFromAdapter( 'general', '', $startDate1, $endDate1, $ports, $categories, $brands, $products, $countries);
		}
		
		// if startdate and enddate are supplied
		if( $startDate1 != "" && $endDate1 != "" )
		{
			// pull data for the first period
	    	$object->data["general"] = $object->getDataFromAdapter( 'general', '', $startDate1, $endDate1, $ports, $categories, $brands, $products, $countries);
			
	    	// banner isn't needed on global report
	    	//$banner = $object->getDataFromAdapter( "banner-for-global", '', $startDate1, $endDate1);
    		//$object->data["general"]["banner-summary"] = ($banner==false)?null:$banner;
	    	
		}
		
    	// if user wants to compare it with the 2nd period
    	if( $startDate2 != "" && $endDate2 != "" )
    	{
    		$tmp[$startDate1. " " . $endDate1] = $object->data["general"];
    		$tmp[$startDate2. " " . $endDate2] = $object->getDataFromAdapter( 'general', '', $startDate2, $endDate2, $ports, $categories, $brands, $products, $countries);
    		$object->data["general"] = $tmp;
    	}		
    	    	
    	return $object;
	}
	
	/**
	 * Factory method to pull report containing the report of given supplier (supplied by tnid)
	 * 
	 * @param int $tnid
	 * @param string $startDate YYYYMMDD
	 * @param string $endDate YYYYMMDD
	 * @return object $object of this class
	 */
	public static function getSupplierValueReport($tnid, $startDate1 = "", $endDate1 = "", $startDate2 = "", $endDate2 = "" )
	{
		$object = new self();
		$object->tnid = $tnid;
		
		$object->startDate1 = $startDate1;
		$object->endDate1 = $endDate1;
		$object->startDate2 = $startDate2;
		$object->endDate2 = $endDate2;
		
		$adapter = new Shipserv_Oracle_Suppliers(parent::getDb());
		$isPublishedInSSO2014 = $adapter->isPublishedInShipServOnBoard($tnid, 2014);
		
		
		if( $startDate1 == "" )
		{
			$object->data["supplier"] = $object->getDataFromAdapter( 'supplier', $tnid );
		}
		
		if( $startDate1 != "" && $endDate1 != "" )
		{
			$unactionedRfqData = $object->getUnactionedRfqReport($tnid, $startDate1, $endDate1);
			// pull data for the first period
	    	$object->data["supplier"] = $object->getDataFromAdapter( 'supplier', $tnid, $startDate1, $endDate1 );
	    	$object->data["supplier"]["enquiry-summary"]["unactioned-rfq"] = $unactionedRfqData->data['supplier']['unactioned-rfq'];

	    	// pull data for banner impression
	    	$banner = $object->getDataFromAdapter( "banner-for-supplier", $tnid, $startDate1, $endDate1);
	    	$object->data["supplier"]["banner-summary"] = ($banner==false)?array("impression" => array( "count" => 0, "days" => array() ), "click" => array( "count" => 0, "days" => array() ) ):$banner;
	    	
	    	$object->data["supplier"]['tradenet-summary']["total-buyer-in-po"] = $object->getUniqueTransactingBuyer($tnid, $startDate1, $endDate1);
	    	$object->data["supplier"]['listed-in-sso-2014'] = ($isPublishedInSSO2014==true)?1:0;
	    	
		}
		
    	// if user wants to compare it with the 2nd period
    	// change the structure a little bit to conform with the export to xlsx feature
    	if( $startDate2 != "" && $endDate2 != "" )
    	{
    		
    		$tmp[$startDate1. " " . $endDate1] = $object->data["supplier"];
    		$tmp[$startDate2. " " . $endDate2] = $object->getDataFromAdapter( 'supplier', $tnid, $startDate2, $endDate2 );
			
    		// pull data for banner impression
    		$banner = $object->getDataFromAdapter( "banner-for-supplier", $tnid, $startDate2, $endDate2);
    		$tmp[$startDate1. " " . $endDate1]["banner-summary"] = $object->data["supplier"]["banner-summary"];//($banner==false)?null:$banner;
    		$tmp[$startDate2. " " . $endDate2]["banner-summary"] = ($banner==false)?null:$banner;
    		
    		$object->data["supplier"] = $tmp;
    	}

    	return $object;
    }
    

    public function getUniqueTransactingBuyer($tnid, $startDate, $endDate)
    {
    	$start = new DateTime();
    	$start->setDate(substr($startDate, 0, 4), substr($startDate, 4, 2), substr($startDate, 6, 2));
    	
    	$end = new DateTime();
    	$end->setDate(substr($endDate, 0, 4), substr($endDate, 4, 2), substr($endDate, 6, 2));
    	$sql = "
		  SELECT
		    COUNT(DISTINCT ord.byb_branch_code) TOTAL
		  FROM
		    ord
		    , buyer
		  WHERE
		    ord.spb_branch_code=:tnid
		    AND ord.ord_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
		    AND ord.byb_branch_code=buyer.byb_branch_code
		    AND buyer.byb_is_test_account=0
		";
    	 
    	$db = $this->getDbByType(Shipserv_Oracle::SSREPORT2);
    	$params = array('tnid' => $tnid, 'startDate' => $start->format("d-M-Y"), 'endDate' => $end->format("d-M-Y"));
    	
    	return $db->fetchOne($sql, $params);
    }
    
    
    public static function getUnactionedRfqReport($tnid, $startDate, $endDate)
    {
    	$object = new self();
    	$object->tnid = $tnid;
    	
    	$object->startDate1 = $startDate1;
    	$object->endDate1 = $endDate1;
    	
    	if( $startDate1 == "" )
    	{
    		$object->data["supplier"] = $object->getDataFromAdapter( 'unactioned-rfq', $tnid );
    	}
    	
    	if( $startDate1 != "" && $endDate1 != "" )
    	{
    		// pull data for the first period
    		$object->data["supplier"] = $object->getDataFromAdapter( 'unactioned-rfq', $tnid, $startDate1, $endDate1 );
    	}
    	return $object;
    	 
    }
	
    /**
     * Interface for the adapters (backend)
     * 
     * @param string $type
     * @param int $tnid
     * @param string $startDate
     * @param string $endDate
     * @param array $ports
     * @param array $categories
     * @param array $brands
     * @param array $products
     * @param array $countries
     * @return object $report
     */
	public function getDataFromAdapter( $type, $tnid = "", $startDate = "", $endDate = "", $ports = array(), $categories = array(), $brands = array(), $products = array(), $countries = array() )
	{
    	if( $type == "general" )
    	{
	    	$adapter = new Shipserv_Adapters_Report();
	    	return $adapter->getGeneralImpression($startDate, $endDate, $ports, $categories, $brands, $products, $countries);
    	}
    	else if( $type == "supplier")
    	{
    		$adapter = new Shipserv_Adapters_Report();
    		return $adapter->getSupplierImpression($tnid, $startDate, $endDate, $ports, $categories, $brands, $products);
    	}
		else if( $type == "banner-for-supplier" )
    	{
    		$adapter = new Shipserv_Adapters_Report_GoogleDFP();
    		return $adapter->getBannerDataForSupplier( $tnid, $startDate, $endDate );
    	}
    	else if( $type == "unactioned-rfq" )
    	{
    		$adapter = new Shipserv_Adapters_Report();
    		return $adapter->getSupplierUnactionedRFQ( $tnid, $startDate, $endDate );
    	}
		else if( $type == "gmv-p1si" )
    	{
    		$adapter = new Shipserv_Adapters_Report();
    		return $adapter->getGmvPageOneSearchImpression( $tnid, $startDate, $endDate );
    	}
	    else if( $type == "ucv-urfq" )
    	{
    		$adapter = new Shipserv_Adapters_Report();
    		return $adapter->getUniqueContactViewAndUnactionedRfq( $tnid, $startDate, $endDate );
    	}
		else if( $type == "gmv-detail" )
    	{
    		$adapter = new Shipserv_Adapters_Report();
    		return $adapter->getGmvDetail( $tnid, $startDate, $endDate );
    	}
    	/*
		else if( $type == "banner-for-global" )
    	{
    		$adapter = new Shipserv_Adapters_Report_GoogleDFP();
    		return $adapter->getGlobalDataForBanner( '', $startDate, $endDate );
    	}
    	*/
	}

	/**
	 * Convert this report to array
	 * @return array
	 */
	public function toArray()
	{
		return $this->data;
	}
	
	/**
	 * Convert this report to excel (xlsx file)
	 */
	public function toExcel( )
	{
		$report = $this;
		return Shipserv_Report_ReportConverter::convert( $report, "xlsx" );
	}
		
	/**
	 * Get detail of the company for this report
	 * @return Shipserv_Supplier @company
	 */
	public function getCompany($skipCheck = false)
	{
		$this->company = Shipserv_Supplier::fetch( $this->tnid, $this->getDb(), $skipCheck );
		return $this->company;
	}


	/**
	 * @param String $name
	 * @param String $country
	 * @param Array|String $nameSearch
	 * @return Array
	 */
	public function getVesseles($name, $country = null, $type = array('manager', 'owner'))
	{
	    //Searching for only 1 char long string would lead to mem leak
	    if (strlen($name) < 2) {
	        $name = '';
	    }
		$type = (array) $type;
		
		$db = Shipserv_Helper_Database::getSsreport2Db();
		$select = new Zend_Db_Select($db);
		$select
			->from('ihs_ship', array('lrimoshipno', 'shipname', 'deadweight', 'yearofbuild', 'shiptypelevel5', 'beneficialowner', 'technicalmanager', 'shipmanager'))
			->where('shiptypelevel5 <> ?', 'tug')
			->where('shiptypelevel5 NOT LIKE ?', 'fish%');

		if ($country) {
			$select->where('registeredownercountry = ?', $country);
		}
		
		$whereNameConditions = array();
		if (in_array('owner', $type)) {
			$whereNameConditions[] = "REGEXP_LIKE(beneficialowner, :name, 'i')";
		}
		if (in_array('manager', $type)) {
			$whereNameConditions[] = "REGEXP_LIKE(technicalmanager, :name, 'i') OR REGEXP_LIKE(shipmanager, :name, 'i')"; 
		}
		$select
    		->where(implode(' OR ', $whereNameConditions))
    		->columns(new Zend_Db_Expr("(CASE WHEN REGEXP_LIKE(beneficialowner, :name, 'i') THEN 1 ELSE 0 END) AS OWNER_IS_MATCHING"))
    		->columns(new Zend_Db_Expr("(CASE WHEN REGEXP_LIKE(technicalmanager, :name, 'i') OR REGEXP_LIKE(shipmanager, :name, 'i') THEN 1 ELSE 0 END) AS MANAGER_IS_MATCHING"));
		
		if (!$country && !$name) {
		    return array();
		} elseif ($country && !$name) {
		    $name = '*';
		}
		
		return $db->fetchAll($select, array('name' => $name));
	}


	public function getVesselesCountries()
	{
	    $db = Shipserv_Helper_Database::getSsreport2Db();
	    $select = new Zend_Db_Select($db);
	    $select
    	    ->from('ihs_ship', array())
    	    ->where('shiptypelevel5 <> ?', 'tug')
    	    ->where('shiptypelevel5 NOT LIKE ?', 'fish%')
    	    ->where('registeredownercountry IS NOT NULL')
    	    ->order('registeredownercountry ASC')
    	    ->columns(new Zend_Db_Expr('DISTINCT(registeredownercountry) AS country'));	    

	    return $db->fetchCol($select);

	}
}


