<?php
/**
 * Class to handle communication between SF and Pages in relation to
 * Uploading Monthly VBP Billing report so Finance can bill suppliers
 * from Salesforce.
 */
// commented out by Yuriy Akopov on 2016-06-08, S16162 as Salesforce library is moved to composer
// require_once ('/var/www/libraries/SalesForce/SforceEnterpriseClient.php');

class Myshipserv_Salesforce_Report_Billing_HealthCheck extends Myshipserv_Salesforce_Base
{
	const MEMCACHE_TTL = 86400;
	
	protected $data = array();
	protected $billingReport = array();
	protected $sf = array();
	protected $period = array();
	
	public function __construct()
	{
		$this->db 			= Shipserv_Helper_Database::getDb();
		$this->logger 		= new Myshipserv_Logger_File('salesforce-monthly-billing-report-supplier');
		
		if ($_SERVER['APPLICATION_ENV'] != 'production' && $_SERVER['APPLICATION_ENV'] != 'development-production-data')
		{
			$this->runInSandbox();
			$this->runAsTestTransaction();
		}
		
		$this->initialiseConnection();
	}
	
	public function getData()
	{
		$data = array();
		
		foreach( (array) $this->getAllVbpAccount() as $row)
		{
			$x['rates'] = $this->getRateSets($row['VST_SF_ACCOUNT_ID']);
			$x['transitionDate'] = $row['VST_TRANSITION_DATE'];
			$x['valueEvents'] = $this->getValueEventByRateSet($row['VST_SF_ACCOUNT_ID'], $x['rates']);
			$data[(int)$row['VST_SPB_BRANCH_CODE']] = $x;
		}

		return $data;
	}
	
	public function getValueEventByRateSet($accountId, $rates)
	{
		foreach( $rates as $rate )
		{
			if( $rate['SFR_START_DATE'] != "" )
			{
				$tmp = explode("-", $rate['SFR_START_DATE']);
				$date = new DateTime;
				$date->setDate($tmp[0], $tmp[1], $tmp[2]);
				
				$dates[$date->format('U')] = $date;
			}
			if($rate['SFR_END_DATE'] != "" )
			{
				$tmp = explode("-", $rate['SFR_END_DATE']);
				$date = new DateTime;
				$date->setDate($tmp[0], $tmp[1], $tmp[2]);
					
				$dates[$date->format('U')] = $date;
			}	
		}
		
		if( count($dates) == 0 )
		{
			return null;
		}
		ksort($dates);
		
		$startDate = array_slice($dates, 0, 1);
		$startDate = $startDate[0];
		$endDate = array_slice($dates, count($dates)-1, 1);
		$endDate = $endDate[0];

		$sql = "
			SELECT 
				SFV_ID
				, TO_CHAR(SFV_START_DATE, 'YYYY-MM') SFV_START_DATE
			FROM
				salesforce_value_event
			WHERE
				sfv_sfa_id='" . $accountId . "'
				AND sfv_start_date >= TO_DATE('" . $startDate->format("d-M-Y") . "')	
		";
		foreach( (array) $this->db->fetchAll($sql) as  $row )
		{
			$valueEvent[$row['SFV_START_DATE']] = $row;
				
		}
		
		return $valueEvent;
		
	}
	
	public function getMonthsForVbp()
	{
		
		$sql = "
  		    select
				to_char( add_months( start_date, level-1 ), 'YYYY-MM' ) mo
		    from
			(
				SELECT MIN(vst_transition_date) start_date, MAX(vst_transition_date) end_date FROM vbp_transition_date
			)
		    connect by
		    	level <= months_between(trunc(end_date,'MM'), trunc(start_date,'MM') ) * + 1
		";
		
		foreach( (array) $this->db->fetchAll($sql) as  $row )
		{
			$data[] = $row['MO'];
		}
		
		return $data;
		
	}
	
	public function getAllVbpAccount()
	{
		$sql = "
			SELECT * 
			FROM 
				vbp_transition_date
				";
		$rows = $this->getDb()->fetchAll($sql);
		return $rows; 
	}
	
	public function getRateSets( $accountId )
	{
		$sql = "SELECT 
					sfr_id,
					sfr_sfa_id,
					sfr_sfc_id,
					TO_CHAR(sfr_start_date, 'YYYY-MM-DD') sfr_start_date,
					TO_CHAR(sfr_end_date, 'YYYY-MM-DD') sfr_end_date
					
				FROM salesforce_rateset WHERE sfr_sfa_id=:accountId";
		return $this->getDb()->fetchAll($sql, array('accountId' => $accountId));
	}
	
	/**
	 * Function to query SF, it'll keep trying it if throws error
	 * @param unknown $soql
	 * @return unknown
	 */
	public function querySf($soql)
	{
		$this->sfConnection->setQueryOptions(new QueryOptions(self::QUERY_ROWS_AT_ONCE));
	
		$ok = false;
		while($ok == false)
		{
			try
			{
				$response = $this->sfConnection->query($soql);
				$ok = true;
			}
			catch (Exception $e)
			{
				$this->logger->log(
				    "\tSF's not responding when querying; reason: " . $e->getMessage() .
                    " - pausing for " . Myshipserv_Salesforce_Base::REQUEST_PAUSE . "s and retry"
                );
				if( $e->getMessage() == "Could not connect to host" )
				{
					sleep(Myshipserv_Salesforce_Base::REQUEST_PAUSE);
					$ok = false;
				}
				else 
				{
					throw $e;
				}
			}
		}
	
		return $response;
	}	
}