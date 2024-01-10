<?php
/**
 * @author elvir <eleonard@shipserv.com>
 */
class Myshipserv_EmailCampaign_UpsellNonPayingSupplier_Generator {

	private $requests = array();
	private $db;
	
	public $debug = false;
	public $mode = 'both';
	public $retryFailedMessage = false;
	
	const NO_EMAIL_PER_BATCH_LIVE_MODE = 200;
	const NO_EMAIL_PER_BATCH_DEBUG_MODE = 5;
	const CAMPAIGN_ID = 8;
	
	public function  __construct()
	{
		$d = getopt('d:');
		$m = getopt('m:');
		$e = getopt('e:');
		if( isset( $d['d'] ) && $d['d'] == 'true' ) $this->debug = true;
		if( isset( $m['m'] ) ) $this->mode = $m['m'];
		if( isset( $e['e'] ) && $e['e'] == 'true' ) $this->retryFailedMessage = true;
	}
	
	public function generate()
	{
		// initialising
		$adapter = new Shipserv_Oracle_Mailer( $this->db );
		$notificationManager = new Myshipserv_NotificationManager( $this->getDb());
		
		// setting up total email sent per batch
		if( $this->debug == false )
		{
			$numberOfEmailPerBatch = self::NO_EMAIL_PER_BATCH_LIVE_MODE;
		}
		else
		{
			$numberOfEmailPerBatch = self::NO_EMAIL_PER_BATCH_DEBUG_MODE;
		}
		
		if (isset($_GET['override']) && $_GET['override'] == 1) {
			if (isset($_GET['totalEmail']) && $_GET['totalEmail'] != "") {
				$numberOfEmailPerBatch = (Integer) $_GET['totalEmail']; //cast should do also sanitization 
			}
		}
		
		$totalEmailNotSent = $totalEmailSent = $job = 0;
		$adapter = new Shipserv_Oracle_Mailer( $this->getDb() );
		
		$yearAgo = strtotime('now');
		$twoYearAgo = strtotime('-1 year');
			
		$startDate = new DateTime;
		$startDate->setDate( date("Y",$twoYearAgo), date("m",$twoYearAgo), date("d",$twoYearAgo) );
			
		$endDate = new DateTime;
		$endDate->setDate( date("Y",$yearAgo), date("m",$yearAgo), date("d",$yearAgo) );
		
		print "Start sending email to NON-PAYING suppliers where they're not OPTING OUT, DNC (Do not call) List and do NOT have opportunity" . PHP_EOL;
		print "-------------------------------------------------------------------------------------------------------------------------------------" . PHP_EOL;
		print "- Period of data is: " . $startDate->format("d-m-Y") . " to " . $endDate->format("d-m-Y") . PHP_EOL;
		print "-------------------------------------------------------------------------------------------------------------------------------------" . PHP_EOL . PHP_EOL;
		$timeAll = microtime(true);
		
		$rows = $adapter->getNextRecipient( $numberOfEmailPerBatch, self::CAMPAIGN_ID, $this->retryFailedMessage );
		
		foreach( $rows  as $row )
		{
			$job++;
			$error = false;
			$email = $row["PMR_EMAIL"];
			$tnid = $row["PMR_TNID"];
			$userType = $row["PMR_DATA_SOURCE"];
			$campaignId = $row['PMR_PMC_ID'];
			$userId = $row['PMR_PSU_ID'];
			$isTrusted = $row['PMR_IS_TRUSTED'];
			
			print "-- ". $job . ". sending an email to: " . $email . " (" . $userType . " user)" . PHP_EOL;
	
			try 
			{
				// create report based on the TNID
				$time = microtime(true);
				$report = Shipserv_Report::getSupplierValueReport($row['PMR_TNID'], $startDate->format("Ymd"), $endDate->format("Ymd"));
				$supplier = Shipserv_Supplier::getInstanceById($tnid, "", true);
								
				$totalRfqReceived = $report->data['supplier']['tradenet-summary']['RFQ']['count'];
				$totalProfileView = $report->data['supplier']['impression-summary']['impression']['count'];
								
				print "---- pulling SIR stats for: " . $row['PMR_TNID'] . ' in ' . round( microtime(true) - $time, 2 ). "s" . PHP_EOL;
			}
			catch(Exception $e )
			{
				$adapter->setError($e->getMessage(), $email, $campaignId);
				$adapter->setTimestamp( "sent", $email, self::CAMPAIGN_ID );
				$error = true;	
				$totalEmailNotSent++;	
			}
			
			// determining if we should send email out or not based on total RFQ received and total profile view
			// do not send to supplier that has more than 1300 RFQs in last 12 months (about 100 TNIDs)
			// do not send to supplier that has 0 RFQ and 0 profile view
			if( $totalRfqReceived == 0 && $totalProfileView < 10 || $totalRfqReceived > 1300 ) 
			{
				// do not send
				print "---- skipped: " . $email . " [RFQ: " . $totalRfqReceived . " PROFILE: " . $totalProfileView . "]" . PHP_EOL;
				
				// mark it as sent so script will not pick this up again later
				$adapter->setTimestamp( "sent", $email, self::CAMPAIGN_ID );	
			}
			else
			{
				if( $error == false )
				{
					try
					{
						print "---- sending email to: " . $email . " via jangoSMTP [RFQ: " . $totalRfqReceived . " PROFILE: " . $totalProfileView . "]" . PHP_EOL;
						$time = microtime(true);
						
						// ($email, $supplier, $statistic, $data = null)
						$notificationManager->sendEmailToUpsellNonPayingSupplier( $email, $supplier, $report, $row);
						//$adapter->setTimestamp( "error", $email, self::CAMPAIGN_ID );
						$adapter->setTimestamp( "sent", $email, self::CAMPAIGN_ID );
						
						print "     ... done in " . round( microtime(true) - $time, 2 ). "s" . PHP_EOL;
						$totalEmailSent++;
					}
					catch( Exception $e )
					{
						print PHP_EOL;
						print "---- ---- cannot send email " . $email . " due to \"" . $e->getMessage() . "\". [" . date('d M y h:i:s') . "]" . PHP_EOL;
						$adapter->setError($e->getMessage(), $email, $campaignId);
						$error = true;
						$totalEmailNotSent++;
					}
				
					$supplier->purgeMemcache();
				}				
			}
			echo PHP_EOL;
		}
		print "-------------------------------------------------------------------------------------------------------------------------------------" . PHP_EOL;	
		print $totalEmailSent . " email(s) sent and " . $totalEmailNotSent . " email(s) NOT sent";
		print " in  " . round( microtime(true) - $timeAll, 2 ). "s" . PHP_EOL;
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
