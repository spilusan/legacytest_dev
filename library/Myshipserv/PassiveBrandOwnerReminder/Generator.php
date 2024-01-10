<?php
/**
 * This class handles the email reminder to unverified supplier
 * 
 * @author elvir <eleonard@shipserv.com>
 *
 */
class Myshipserv_PassiveBrandOwnerReminder_Generator {

	private $userDao;

	private $requests = array();

	private $analyticsSupplierCategorySearch;
	
	private $analyticsSupplierTotalVisitor;

	private $analyticsSupplierTotalRFQ;
	
	private $analyticsSupplierTotalVerifiedCompetitors;
	
	private $analyticsSupplierTotalTransactionForAllCategory;
	
	private static $sharedAnalyticsTotalVisitorOnPages = 0;

	private static $sharedAnalyticsRFQSentWithinCategory = array();
	
	private static $sharedAnalyticsTotalSearchesWithinCategory = array();
	
	private static $sharedAnalyticsVerifiedSupplierWithinCategory = array();
	
	private static $sharedAnalyticsTotalTransactionWithinCategory = array();
	
	private $db;
	
	public $suppliers = array();
	
	public $debug = false;

	public function  __construct()
	{
		
		$arguments = getopt('d:');
		
		if( isset( $arguments['d'] ) && $arguments['d'] == 'true' ) $this->debug = true;
		
		// use 1 day old database
		$this->db = self::getStandByDb();
		$this->userDao = new Myshipserv_UserCompany_Domain( $this->db );
		
		$analyticsAdapter = new Shipserv_Adapters_Analytics( $this->db );
		$gApi = new Myshipserv_GAnalytics();
		
		// put everything to variable
		$this->sharedAnalyticsTotalVisitorOnPages = $gApi->getSiteVisits();
		$this->sharedAnalyticsRFQSentWithinCategory = $analyticsAdapter->getTotalInquiryForAllCategory( $this->db);//, "3 months" );	
		$this->sharedAnalyticsTotalSearchesWithinCategory = $analyticsAdapter->getTotalSearchForAllCategory( $this->db);//, "3 months" );
			
	}
	
	
	/**
	 * Sending an invitation email to all users of unverified supplier
	 */
	public function generate()
	{
		$totalSent = 0; 
		$totalSupplier = 0;

		// getting the db instance
		$db = $this->db;
		
		if ($this->debug) echo "Debug mode is enabled \n";
				
		// prepare notification manager
		$notificationManager = new Myshipserv_NotificationManager( $db );
		
		if ($auths = Shipserv_BrandAuthorisation::search(array(
			"PCB_AUTH_LEVEL" => Shipserv_BrandAuthorisation::AUTH_LEVEL_OWNER,
			"PCB_IS_AUTHORISED" => 'N'
		)))
		{
			foreach( $auths as $auth )
			{
				// pass it to notification manager to pull all users
				$notificationManager->invitePassiveBrandOwnerToClaim( $auth );
			}
		}
		else 
		{
			if ($this->debug) echo "There is no inactive brand owner(s)\n";

		}
		
		if ($this->debug) echo "Sending report to CRC\n";
		
		$this->sendReportOfMissingUserOnBrandOwner();
	}
	
	public function sendReportOfMissingUserOnBrandOwner()
	{
		$sql = "
SELECT
  brand.id AS brandId,
  pcb_company_id AS tnid,
  brand.name AS brandName,
  spb_name AS companyName,
  spb_registrant_email_address || ',' ||
  spb_email || ',' ||
  public_contact_email
  AS email
FROM
  brand,
  supplier_branch,
  (
    SELECT pcb_company_id, pcb_brand_id FROM pages_company_brands WHERE 
       pcb_auth_level='OWN' 
       AND pcb_is_authorised='N'
       AND pcb_company_id not in (
         SELECT distinct(puc_company_id) FROM pages_user_company
         WHERE
           puc_level = 'ADM'
           AND puc_status='ACT'        
       )
   ) tbl
WHERE
  tbl.pcb_brand_id=brand.id
  AND tbl.pcb_company_id = supplier_branch.spb_branch_code
		";
		
		$results = $this->getStandByDb()->fetchAll( $sql );

		if( count($results ) > 0 )
		{
			$data[] = "Hello,";
			$data[] = "Pages has found there are " . count( $results ) . " brand owners that don't have admin\n";
				
		}
		foreach( $results as $row )
		{
			$emails = array();
			$tmp = explode(",", $row["EMAIL"]);
			foreach( $tmp as $email )
			{
				if( $email != '' && $email != "None" && $email != "," && in_array($email, $emails) === false )
				{
					$emails[] = $email;
				}
			}
			$data[] =   "TNID: " . $row["TNID"] . "\n"
					  . "Company: " . $row["COMPANYNAME"] . "\n"
					  . "Brand id: " . $row["BRANDID"] . "\n"
					  . "Brand: " . $row["BRANDNAME"] . "\n"
					  . "Email: " . implode(", ", $emails) . "\n"
					  . "------------------------------------\n";
		}
		
		if ($_SERVER['APPLICATION_ENV'] == 'production')
		{
			$email = "support@shipserv.com";
		}
		else
		{
			$config = Zend_Registry::get('config');
			$email = $config->notifications->test->email;		
		}
		
		$mail = new Zend_Mail();
		$mail->setBodyText(implode("\n", $data))
				->setFrom('support@shipserv.com')
				->addTo($email, $email)
				->setSubject('Missing administrator on supplier that claims to own a brand')
				->send();

	}
	
	/**
	 * Get analytics for each supplier
	 * 
	 * @param object $supplierId
	 * @return array of statistics
	 */
	public function getAnalytics( $supplierId )
	{
		$db = $this->db;
		$analytics = array();
		
		// initialise all adapters
		$adapter = new Shipserv_Adapters_Analytics( $db );
		$supplierAdapter = new Shipserv_Oracle_Suppliers( $db );
		$profileAdapter = new Shipserv_Oracle_Profile( $db );
		
		// store total visitor to pages
		$analytics["totalVisitorPages"] = $this->sharedAnalyticsTotalVisitorOnPages;
		
		$time = microtime(true);
		// store total visitor to supplier
		$analytics["totalVisitorSupplier"] = $adapter->getTotalVisitorOnSupplier( $db, $supplierId);//, "3 months" );
		echo "stat1: " . round( microtime(true) - $time, 2 ). "s.";
		
		$time = microtime(true);
		// store total competitor that are verified
		$analytics["totalVerifiedSupplier"] = count( $supplierAdapter->fetchCategoryWorldwideMatches( $supplierId, array($supplierId), true) );
		echo " | stat2: " . round( microtime(true) - $time, 2 ). "s.";
		
		
		$time = microtime(true);
		
		// get total transaction
		$rpStats = new Myshipserv_SupplierTransactionStats( $db );
		$statisticsTxn = $rpStats->supplierProfileStats($supplierId);
		$totalArr = Myshipserv_View_Helper_Stats::formatNumber($statisticsTxn->getMonthResult()->getTotal());
		
		if (! $statisticsTxn->isTopLevel())
		{
			$analytics["totalTransactionDescription"] = "ordered from suppliers in these categories (3 mths)";
		}
		else
		{
			$analytics["totalTransactionDescription"] = "ordered from suppliers on TradeNet (3 mths)";
		}
		$analytics["totalTransaction"] = $totalArr['v']. " " . $totalArr['scale']; 

		// pull all categories that belongs to given supplier
		$supplierProfile = $profileAdapter->fetchMainProfile ($supplierId);
		$categories = $profileAdapter->getCategories ($supplierId, $supplierProfile["ORGCODE"], true);
		echo " | stat3: " . round( microtime(true) - $time, 2 ). "s.";
		
		$time = microtime(true);
		// get all category related data
		foreach( $categories as $cat )
		{
			// get total rfq sent
			$analytics["rfqSent"] += $this->sharedAnalyticsRFQSentWithinCategory[ $cat["ID"] ];
			
			// get total searches 
			$analytics["totalSearch"] += $this->sharedAnalyticsTotalSearchesWithinCategory[ $cat["ID"] ];
		}
		echo " | stat4: " . round( microtime(true) - $time, 2 ). "s.";
		
		return $analytics;
	}
	

	private static function getStandByDb()
	{
		$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
		return $resource->getDb('standbydb');
	}

	private static function getDb()
	{
		return $GLOBALS["application"]->getBootstrap()->getResource('db');
	}
	
}
?>