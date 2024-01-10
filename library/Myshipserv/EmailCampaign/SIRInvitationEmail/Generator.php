<?php
/**
 * This class handles the email reminder to unverified supplier
 * 
 * @author elvir <eleonard@shipserv.com>
 *
 */
class Myshipserv_EmailCampaign_SIRInvitationEmail_Generator {

	private $userDao;

	private $requests = array();

	private $db;
	
	public $debug = false;
	
	const NO_EMAIL_PER_BATCH_LIVE_MODE = 100;
	const NO_EMAIL_PER_BATCH_DEBUG_MODE = 25;
	
	public function  __construct()
	{
		$arguments = getopt('d:');
		
		if( isset( $arguments['d'] ) && $arguments['d'] == 'true' ) $this->debug = true;
		
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
		
		$totalEmailSent = 0;
		$adapter = new Shipserv_Oracle_Mailer( $this->getDb() );
		
		echo "Start sending SIR invite to all basic listers\n";
		$timeAll = microtime(true);
		
		$rows = $adapter->getNextRecipient( $numberOfEmailPerBatch );
		foreach( $rows  as $row )
		{
			$totalEmailSent++;
			
			$email = $row["PMR_EMAIL"];
			$tnid = $row["PMR_TNID"];
			$userType = $row["PMR_DATA_SOURCE"];
			$error = false;

			echo "-- ". $totalEmailSent . ". sending an email to: " . $email . " (" . $userType . " user)\n";
	
			$yearAgo = strtotime('-1 year');
			$yearAgoAsDate = new DateTime;
			$yearAgoAsDate->setDate( date("Y",$yearAgo), date("m",$yearAgo), date("d",$yearAgo) );
			
			try{
				// create report based on the TNID
				$time = microtime(true);
				$report = Shipserv_Report::getSupplierValueReport($row['PMR_TNID'], $yearAgoAsDate->format("Ymd"), date("Ymd"));
				$supplier = Shipserv_Supplier::fetch( $tnid);
				echo "---- pulling SIR stats for: " . $row['PMR_TNID'] . ' in ' . round( microtime(true) - $time, 2 ). "s\n";
			}
			catch(Exception $e)
			{
				echo "--- ---- cannot pull SIR for " . $row['PMR_TNID'] . " due to system error.\n";
				$error = true;
			}

			if( $error == false )
			{
				// enable access for this particular user
				try
				{
					$supplierAdapter = new Shipserv_Oracle_Suppliers( $this->getDb() );
					$userAdapter = new Shipserv_Oracle_User($this->db);
					$userId = $userAdapter->getUserIdByEmail( $email );
					$userId = $userId[0]['PSU_ID'];
				}
				catch( Exception $e )
				{
					$error = true;
					echo "---- ---- cannot pull user detail for " . $email . " due to system error.\n";
				}

				if( $error == false )
				{
					try{
						$notificationManager->sendSIRInviteForBasicLister( $email, $subject, $supplier, $report, $yearAgoAsDate->format("d F Y"), $userType, $userId, $this->getDb());
						$adapter->setTimestamp( "sent", $email );
					}
					catch( Exception $e )
					{
						$error = true;
						echo "\n---- ---- cannot send email " . $email . " due to \"" . $e->getMessage() . "\". [" . date('d M y h:i:s') . "]\n";
					}
					
					if( $error == false )
					{
						try
						{
							echo "---- ---- enabling access for TNID: " . $tnid;
							//$userAdapter->enableAccessToSIR( $userId );
							$supplierAdapter->enableSVRAccess( $tnid );
							echo "... done\n\n";
							
						}
						catch( Exception $e )
						{
							$error = true;
							echo "---- ---- cannot enable access for " . $email . " due to system error.\n";
						}
						
						$supplier->purgeMemcache();
					}
				}
			}
		}
				
		echo "\n" . $totalEmailSent . " email(s) sent ";
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
