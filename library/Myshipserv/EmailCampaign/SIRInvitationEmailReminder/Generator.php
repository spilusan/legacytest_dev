<?php
/**
 * 
 * @author elvir <eleonard@shipserv.com>
 *
 */
class Myshipserv_EmailCampaign_SIRInvitationEmailReminder_Generator {

	private $requests = array();

	private $db;
	
	public $debug = false;
	public $mode = 'both';
	public $retryFailedMessage = false;
	
	const NO_EMAIL_PER_BATCH_LIVE_MODE = 100;
	const NO_EMAIL_PER_BATCH_DEBUG_MODE = 25;
	
	const CAMPAIGN_ID = 2;
	
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
		
		if( isset( $_GET['overrideNumOfEmailSent'] ) && isset( $_GET['auth2'] ) && $_GET['overrideNumOfEmailSent'] != "" && ctype_digit( $_GET['overrideNumOfEmailSent'] ) && md5('override' . $_GET['overrideNumOfEmailSent']) == $_GET['auth2'] )
		{
			$numberOfEmailPerBatch = intval($_GET['overrideNumOfEmailSent']);
		}
		
		$totalEmailNotSent = $totalEmailSent = $job = 0;
		$adapter = new Shipserv_Oracle_Mailer( $this->getDb() );
		
		echo "Start sending SIR invite reminder to all basic listers that have NOT opened the email on the previous campaign\n-------------------------------------------------------------------------------------------------------------------------------------\n\n";
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
			
			echo "-- ". $job . ". sending an email to: " . $email . " (" . $userType . " user)\n";
	
			$yearAgo = strtotime('-1 year');
			$yearAgoAsDate = new DateTime;
			$yearAgoAsDate->setDate( date("Y",$yearAgo), date("m",$yearAgo), date("d",$yearAgo) );
			
			try 
			{
				// create report based on the TNID
				$time = microtime(true);
				$report = Shipserv_Report::getSupplierValueReport($row['PMR_TNID'], $yearAgoAsDate->format("Ymd"), date("Ymd"));
				$supplier = Shipserv_Supplier::fetch( $tnid);
				echo "---- pulling SIR stats for: " . $row['PMR_TNID'] . ' in ' . round( microtime(true) - $time, 2 ). "s\n";
			}
			catch(Exception $e )
			{
				$adapter->setError($e->getMessage(), $email, $campaignId);
				$adapter->setTimestamp( "sent", $email, self::CAMPAIGN_ID );
				$error = true;	
			}
			
			if( $error == false )
			{
				try
				{
					$supplierAdapter = new Shipserv_Oracle_Suppliers( $this->getDb() );
					$userAdapter = new Shipserv_Oracle_User($this->db);
					$userId = $userAdapter->getUserIdByEmail( $email );
					$userId = $userId[0]['PSU_ID'];
				}
				catch( Exception $e )
				{
					echo "---- ---- cannot pull user detail for " . $email . " due to system error.\n";
				}
				
				// Requested by IT Support (Robin) to wrap this on exception to handle the error thrown gracefully
				try
				{
					echo "---- sending email to: " . $email . " via jangoSMTP";
					$time = microtime(true);
					
					$notificationManager->sendSIRInviteReminderForBasicLister( $email, $subject, $supplier, $report, $yearAgoAsDate->format("d F Y"), $userType, $userId, $this->getDb(), $this->mode);
					$adapter->setTimestamp( "error", $email, self::CAMPAIGN_ID );
					
					echo " ... done in " . round( microtime(true) - $time, 2 ). "s\n";
					$totalEmailSent++;
				}
				catch( Exception $e )
				{
					echo "\n---- ---- cannot send email " . $email . " due to \"" . $e->getMessage() . "\". [" . date('d M y h:i:s') . "]\n";
					$adapter->setError($e->getMessage(), $email, $campaignId);	
					$error = true;
					$totalEmailNotSent++;
					
				}
				
				$adapter->setTimestamp( "sent", $email, self::CAMPAIGN_ID );
	
				if( $error == false )
				{
					try
					{
						echo "---- ---- enabling access for TNID: " . $tnid;
						$supplierAdapter->enableSVRAccess( $tnid );
						echo "... done\n\n";
					}
					catch( Exception $e )
					{
						echo "---- ---- cannot enable access for " . $email . " due to system error.\"" . $e->getMessage() . "\". [" . date('d M y h:i:s') . "]\n";
					}
				}
				else
				{
					echo "\n\n";
				}
				$supplier->purgeMemcache();
			}
			
		}
		echo "-------------------------------------------------------------------------------------------------------------------------------------";	
		echo "\n" . $totalEmailSent . " email(s) sent and " . $totalEmailNotSent . " email(s) NOT sent";
		echo " in  " . round( microtime(true) - $timeAll, 2 ). "s\n";
		
		
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
