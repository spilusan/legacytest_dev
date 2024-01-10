<?php
/**
 * This class handles the email reminder to unverified supplier
 * 
 * @author elvir <eleonard@shipserv.com>
 *
 */
class Myshipserv_UnverifiedSupplierReminder_Generator {

	private $userDao;

	private $requests = array();

	private $duration = "3 months";
	
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
	
		// calculate all shared information
		$analyticsAdapter = new Shipserv_Adapters_Analytics( $this->db );
		
		echo "Preparing shared analytics...\n";
		
		$time = microtime(true);
		// talk to google analytics API
		$gApi = new Myshipserv_GAnalytics();
		$this->sharedAnalyticsTotalVisitorOnPages = $gApi->getSiteVisits( $this->duration );
		echo "- google analytics in " . round( microtime(true) - $time, 2 ). "s\n";

	}
	
    private function array_chunk_vertical($data, $columns) {
        $n = count($data) ;
        $per_column = floor($n / $columns) ;
        $rest = $n % $columns ;

        // The map
        $per_columns = array( ) ;
        for ( $i = 0 ; $i < $columns ; $i++ ) {
            $per_columns[$i] = $per_column + ($i < $rest ? 1 : 0) ;
        }

        $tabular = array( ) ;
        foreach ( $per_columns as $rows ) {
            for ( $i = 0 ; $i < $rows ; $i++ ) {
                $tabular[$i][ ] = array_shift($data) ;
            }
        }

        return $tabular ;
    }	
	/**
	 * Sending an invitation email to all users of unverified supplier
	 */
	public function generate()
	{
		$totalSent = $totalSupplier = 0;
		$batches = array();
		
		// getting the db instance
		$db = $this->db;

		if( $this->debug ) echo "\nDebug mode is enabled \n";
				
		if( $this->debug ) echo "\nToday is " . date('l d/m/Y') . "\n\nThis process should only be sending email to unverified supplier on the following day every week: Tue, Wed, Thu for 12 times per month";

		// prepare notification manager
		$notificationManager = new Myshipserv_NotificationManager( $db );
		
		$supplierIds = Shipserv_Supplier::fetchUnverified( $db, false ); 

		$totalUnverifiedSuppliers = count( $supplierIds  );
		
		$currentDate = date('d');
		$currentMonth = date("F");
		$currentYear = date("Y");
		$lastDay = date('t');
		$nth = array("first", "second", "third", "fourth", "fifth");
		$days = array("tuesday", "wednesday", "thursday");
		$x = 0;
		
		for( $i=1; $i<=$lastDay; $i++ )
		{
			$timestamp = mktime(0, 0, 0, date("m"), $i, date("Y") );
			$day = date("l", $timestamp);
			if( $day == 'Tuesday' || $day == 'Wednesday' || $day == 'Thursday' )
			{
				$data[$x] = date("d/m/Y", $timestamp);
				$x++;
			}
		}

		// current date
		$string = date('d/m/Y');
		
		// get the remainder from the date rule
		$remainder = array_search($string, $data);

		// check if there's something to send
		if( $remainder !== false )
		{
			foreach( $supplierIds as $id )
			{
				if( $id % 12 == $remainder )
				{
					$batches[] = $id;
				}
			}
		}	
						
		if( $this->debug )
		{
			if( count( $batches ) > 0 )
			{
			 	echo "\n\nPretending to send emails to " . count( $batches ) . " suppliers  (only 5 suppliers that have email get sent in UAT/QAT).\n\n";
			}
			else
			{
				if( $remainder === false )
				{
					echo "\n\nThere's no email broadcast today\n";
				}
				else 
				{
					echo "\n\nThere's no email need to be sent today\n";
				}
			}
		}
		
		if( $this->debug ) $batches = array_slice( $batches, 0, 5);
		
		// get all analytics for each supplier
		foreach( $batches as $supplierId )
		{
			$totalSupplier++;
			echo "\n\n\n\n";
			echo "*********************************************************************";
			echo "\n$totalSupplier. Processing supplier: " . $supplierId . "....\n";
			
			// get all users
			$users = $this->userDao->fetchUsersForCompany('SPB', $supplierId)->getActiveUsers() ;
			
			if( count( $users ) > 0 )
			{	
				echo "- generating analytics for this supplier: ... ";
				// get all anaytics data for each supplier
				$time = microtime(true);
				$analytics = $this->getAnalytics( $supplierId );
				echo " = " . round( microtime(true) - $time, 2 ). "s in total\n";
				
				// creating instance of supplier
				$supplier = Shipserv_Supplier::fetch( $supplierId, $db );
				
				// get supplier's competitors (as an object)
				$supplierCompetitors = $supplier->fetchCompetitors( 10, true );
				
				//  init variables
				$competitors = array();
				
				// put it on a variable then pass it to the notification manager
				foreach( $supplierCompetitors as $competitor){
					$competitors[] = array(  "name" => $competitor->name
											,"url" => 'https://' . $_SERVER["HTTP_HOST"] . '/supplier/profile/s/' . preg_replace('/(\W){1,}/', '-', $competitor->name) . '-' . $competitor->tnid);
				}
					
				$time = microtime(true);
				// sending email to all users
				foreach( $users as $user )
				{
					echo "- Sending an email to: ". $user->email .', company: '. $user->companyName . ": ";
					
					//  if it's ok for us to send this user immediately
					if ($user->alertStatus == Shipserv_User::ALERTS_IMMEDIATELY)
					{
						// prepare the data to be passed to the notification manager
						$data = array("supplier" => $supplier, "analytics" => $analytics, "competitors" => $competitors );

						$timeForEmail = microtime(true);
						// pass data to notification manager
						$notificationManager->inviteUnverifiedSupplier( $user, $data );
						
						// increase totalSent
						$totalSent++;
						
						echo "... sent in " . round( microtime(true) - $timeForEmail, 2 ) . "s.\n";
					}
					else
					{
						echo "- User has selected not to receive immediate notifications\n";
					}
				}
				
				// update date email sent field here
				$supplier->updateEmailReminderSentDate();
				
				echo "---- all email sent in " . round( microtime(true) - $time, 2 ) . "s for this supplier\n";
			}
			
			// when company doesn't have user
			else
			{
				echo "- No email address found...\n";
			}
			echo "---------------------------------------------------------------------\n                      " . $totalSent . " emails sent in total\n---------------------------------------------------------------------";
			echo "\n*********************************************************************\n";
			
		}
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
		
		// pass duration to view
		$analytics["duration"] = $this->duration;
		
		$time = microtime(true);
		// store total visitor to supplier
		$analytics["totalVisitorSupplier"] = $adapter->getTotalVisitorOnSupplier( $db, $supplierId, $this->duration );
		echo "stat1: " . round( microtime(true) - $time, 2 ). "s.";
		
		$time = microtime(true);
		// store total competitor that are verified
		$analytics["totalVerifiedSupplier"] = count( $supplierAdapter->fetchCategoryWorldwideMatches( $supplierId, array($supplierId), true) );
		echo " | stat2: " . round( microtime(true) - $time, 2 ). "s.";
		
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