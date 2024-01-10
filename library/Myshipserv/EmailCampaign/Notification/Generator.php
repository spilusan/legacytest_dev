<?php
/**
 * @author elvir <eleonard@shipserv.com>
 */
class Myshipserv_EmailCampaign_Notification_Generator {

	private $requests = array();
	private $db;
	private $logger;
	
	public $debug = false;
	public $mode = 'html';
	public $retryFailedMessage = false;
	
	const NO_EMAIL_PER_BATCH_LIVE_MODE = 50000;
	const NO_EMAIL_PER_BATCH_DEBUG_MODE = 10;
	
	public function  __construct()
	{
		$d = getopt('d:');
		$m = getopt('m:');
		$e = getopt('e:');
		if( isset( $d['d'] ) && $d['d'] == 'true' ) $this->debug = true;
		if( isset( $m['m'] ) ) $this->mode = $m['m'];
		if( isset( $e['e'] ) && $e['e'] == 'true' ) $this->retryFailedMessage = true;
		
		$this->logger = new Myshipserv_Logger(false);
	}
	
	public function createRecipient( $campaignId )
	{
		if( $campaignId == 6 )
		{
			$this->logger->log("Creating recipient list");
			
			$this->logger->log("Reseting data");
			$sql = "
				MERGE INTO pages_mailer_campaign USING DUAL ON (PMC_NAME = 'Premium with 0 user campaign')
				  WHEN NOT MATCHED THEN
				    INSERT (PMC_ID, PMC_NAME) VALUES($campaignId, 'Premium with 0 user campaign')
			";
			$this->getDb()->query( $sql );
						
			$sql = "DELETE pages_mailer_recipient WHERE pmr_pmc_id=" . $campaignId;
			$this->getDb()->query( $sql );
			$this->getDb()->commit();
			
			$this->logger->log("Selecting all Premium Profile that has 0 users");
			$sql = "
				SELECT 
				  spb_branch_code tnid,
				  spb_email email,
				  public_contact_email email2
				FROM
				  supplier_branch
				WHERE
				  -- ONLY PULL PREMIUM LISTER, PUBLISHED
				  directory_listing_level_id=4
				  AND directory_entry_status = 'PUBLISHED'
				  AND spb_account_deleted = 'N'
				  AND spb_test_account = 'N'
				  AND spb_branch_code <= 999999
				  AND spb_branch_code NOT IN (
				    SELECT DISTINCT puc_company_id FROM pages_user_company WHERE puc_company_type='SPB' AND puc_status = 'ACT' 
				  )	
				  -- CHECK OPTOUT ON SALESFORCE
				  AND spb_email NOT IN (SELECT seo_email FROM salesforce_email_optout)
				  AND public_contact_email NOT IN (SELECT seo_email FROM salesforce_email_optout)
			";
			$twoEmails = $oneEmails = 0;
			foreach( $this->getDb()->fetchAll( $sql ) as $row )
			{
				$sql = "
					INSERT INTO 
						pages_mailer_recipient (PMR_TNID, PMR_EMAIL, PMR_PMC_ID, PMR_DATA_SOURCE)
										VALUES (:tnid, :email, :campaignId, :dataSource)
				";
				$this->getDb()->query( $sql, array("tnid" => $row['TNID'], "email" => $row['EMAIL'], "campaignId" => $campaignId, "dataSource" => 'ENQUIRY') );
				if( $row['EMAIL'] != $row['EMAIL2'])
				{
					$sql = "
						INSERT INTO 
							pages_mailer_recipient (PMR_TNID, PMR_EMAIL, PMR_PMC_ID, PMR_DATA_SOURCE)
											VALUES (:tnid, :email, :campaignId, :dataSource)
					";
					$this->getDb()->query( $sql, array("tnid" => $row['TNID'], "email" => $row['EMAIL2'], "campaignId" => $campaignId, "dataSource" => 'ENQUIRY') );
					$twoEmails++;
				}
				else 
				{
					$oneEmails++;
				}
			}	
			$this->logger->log("" . ($oneEmails + $twoEmails) . " invites inserted.");	
		}
	}
	
	private function runNotification2()
	{
		
		
		// initialising
		$campaignId = 6;
		
		// create pull all relevant recipients
		$sql = "SELECT COUNT(*) AS TOTAL FROM pages_mailer_recipient WHERE pmr_pmc_id=" . $campaignId;
		$row = $this->getDb()->fetchAll( $sql );
		if( $row[0]['TOTAL'] == 0 )
		{
			$this->createRecipient( $campaignId );	
		}
		
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
		
		// check if something is being overriden by _GET var
		if( isset( $_GET['overrideNumOfEmailSent'] ) && isset( $_GET['auth2'] ) && $_GET['overrideNumOfEmailSent'] != "" && ctype_digit( $_GET['overrideNumOfEmailSent'] ) && md5('override' . $_GET['overrideNumOfEmailSent']) == $_GET['auth2'] )
		{
			$numberOfEmailPerBatch = intval($_GET['overrideNumOfEmailSent']);
		}
		
		// initialise
		$totalEmailNotSent = $totalEmailSent = $job = 0;
		$adapter = new Shipserv_Oracle_Mailer( $this->getDb() );
		
		$this->logger->logSimple("Mailer: Premium with Zero users\n");
		$this->logger->logSimple("-------------------------------------------------------------------------------------------------------------------------------------\n\n");
		
		// start the counter
		$timeAll = microtime(true);
		
		// get the next recipient
		$recipients = $adapter->getNextRecipient( $numberOfEmailPerBatch, $campaignId, $this->retryFailedMessage );

		foreach( $recipients as $row )
		{
			$job++;
			$error = false;
			
			$email 		= $row["PMR_EMAIL"];
			$tnid 		= $row["PMR_TNID"];
			$userType 	= $row["PMR_DATA_SOURCE"];
			$campaignId = $row['PMR_PMC_ID'];
			
			// check if user is available with that email
			$user = false;
			$this->logger->logSimple("[" . date('Y-m-d H:i:s') . "]\n");
			$this->logger->logSimple("-- ". $job . ". sending an email to: " . $email . " " . $userType . " user\n");
	
			// 1 year ago
			$yearAgo = strtotime('-1 year');
			$yearAgoAsDate = new DateTime;
			$yearAgoAsDate->setDate( date("Y",$yearAgo), date("m",$yearAgo), date("d",$yearAgo) );
			
			// set the default date
			$now = new DateTime();
			$lastYear = new DateTime();
			$now->setDate(date('Y'), date('m'), date('d')+1);
			$lastYear->setDate(date('Y')-1, date('m'), date('d'));
			
			// store it to a data structure to be passed around
			$period = array('start' => $lastYear, 'end' => $now );
       		try 
			{
				// create report/stats based on the TNID
				$time = microtime(true);
				$supplier = Shipserv_Supplier::fetch($tnid);
				$report = $supplier->getEnquiriesStatistic($period);

				if( $supplier->tnid == null )
				{
					$this->logger->logSimple("---- error, this supplier is not published: " . $row['PMR_TNID'] . "\n");
					throw new Exception("Supplier is not published", 500);
				}
				
				if( $report['sent'] < 3)
				{
					throw new Exception("Supplier has < 3 enquiries last year");
				}
				
				// get all tnid related to this email (only for Enquiry data source)
				if( $row['PMR_DATA_SOURCE'] == 'ENQUIRY' )
				{
					$tnids = $this->getAllTnidByEmail( $email, $campaignId, 'ENQUIRY');
					$row['NEW_USER_FROM_ENQUIRY_EMAIL_TO_JOIN_TNID'] = implode($tnids, ",");
				}
				$this->logger->logSimple("---- pulling Enquiry stats for: " . $row['PMR_TNID'] . ' in ' . round( microtime(true) - $time, 2 ). "s\n");
			}
			catch(Exception $e )
			{
				$this->logger->logSimple("---- NOT SENT " . $e->getMessage() . "\n\n");
				
				$adapter->setError($e->getMessage(), $email, $campaignId);
				$adapter->setTimestamp( "sent", $email, $campaignId );
				$error = true;	
				$totalEmailNotSent++;
			}
			
			if( $error == false )
			{
				try
				{
					// only send this email out to all TNID that has 5 enq last year
					if( ( $supplier->isPremium() && $report['sent'] > 3 ) )
					{
						$this->logger->logSimple("---- sending email to: " . $email . " - " . (($supplier->isPremium())?"Premium lister":"Basic lister") . " has " . $report['sent']. " enquiry (last year) ");
						$time = microtime(true);
						
						// we need stop sending for load test
						//$notificationManager->stopSending();
						$notificationManager->sendEmailToPremiumSupplierWithZeroUser( $email, $subject, $supplier, $report, $yearAgoAsDate->format("d F Y"), $userType, $userId, $this->getDb(), $this->mode, $row);
						$adapter->setTimestamp( "error", $email, $campaignId );
						
						$this->logger->logSimple(" ... done in " . round( microtime(true) - $time, 2 ). "s\n");
						$totalEmailSent++;
						$emailIsSent = true;
					}
					else
					{
						$this->logger->logSimple("------- Skipping: " . (($supplier->isPremium())?"Premium lister":"Basic lister") . " only has " . $report['sent']. " enquiry (last year)" . "\n");
						$adapter->setError("Skipped " . (($supplier->isPremium())?"Premium lister":"Basic lister") . " with " . $report['sent']. " enquiry", $email, $campaignId);	
						
						$totalEmailNotSent++;
						$emailIsSent = false;
					}
				}
				catch( Exception $e )
				{
					$error = true;
					$this->logger->logSimple("\n---- ---- cannot send email " . $email . " due to \"" . $e->getMessage() . "\". [" . date('d M y h:i:s') . "]\n");
					$adapter->setError($e->getMessage(), $email, $campaignId);	
					$totalEmailNotSent++;
					$emailIsSent = false;
				}
				
				// mark all tnid related to this email (only for Enquiry data source) as SENT
				if( $row['PMR_DATA_SOURCE'] == 'ENQUIRY' )
				{
					// only mark all TNIDs that relates to a single email sent when one email is 
					// actually sent, otherwise keep trying with the next TNID
					if( $emailIsSent == true )
					{
						$this->markAllRecipientAsSent( $email, $campaignId, 'ENQUIRY');
						$this->logger->logSimple("---- ---- Marking other TNID as sent (Enquiry)\n");
					}
					else 
					{
						$adapter->setTimestamp( "sent", $email, $campaignId, $tnid );
					}
				}
				else
				{
					$adapter->setTimestamp( "sent", $email, $campaignId );
				}
				
				$this->logger->logSimple( "\n\n" );
				$supplier->purgeMemcache();
			}
			
		}
		$this->logger->logSimple( "-------------------------------------------------------------------------------------------------------------------------------------" );	
		$this->logger->logSimple( "\n" . $totalEmailSent . " email(s) sent and " . $totalEmailNotSent . " email(s) NOT sent" );
		$this->logger->logSimple( " in  " . round( microtime(true) - $timeAll, 2 ). "s\n" );
		
		//$this->logger->sendEmail("eleonard@shipserv.com", "Notification v1");
	}
	
	private function runNotification1()
	{
		// initialising
		$campaignId = 5;
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
		
		// check if something is being overriden by _GET var
		if( isset( $_GET['overrideNumOfEmailSent'] ) && isset( $_GET['auth2'] ) && $_GET['overrideNumOfEmailSent'] != "" && ctype_digit( $_GET['overrideNumOfEmailSent'] ) && md5('override' . $_GET['overrideNumOfEmailSent']) == $_GET['auth2'] )
		{
			$numberOfEmailPerBatch = intval($_GET['overrideNumOfEmailSent']);
		}
		
		// initialise
		$totalEmailNotSent = $totalEmailSent = $job = 0;
		$adapter = new Shipserv_Oracle_Mailer( $this->getDb() );
		
		$this->logger->logSimple("Mailer: Open rate awareness campaign\n");
		$this->logger->logSimple("-------------------------------------------------------------------------------------------------------------------------------------\n\n");
		
		// start the counter
		$timeAll = microtime(true);
		
		// get the next recipient
		$recipients = $adapter->getNextRecipient( $numberOfEmailPerBatch, $campaignId, $this->retryFailedMessage );

		foreach( $recipients as $row )
		{
			$job++;
			$error = false;
			
			$email 		= $row["PMR_EMAIL"];
			$tnid 		= $row["PMR_TNID"];
			$userType 	= $row["PMR_DATA_SOURCE"];
			$campaignId = $row['PMR_PMC_ID'];
			
			// check if user is available with that email
			$user = false;
			try{
				$user = Shipserv_User::getInstanceByEmail( $email );
			}catch(Exception $e){}
			
			$userId 	= ($user === false)?null:$user->userId;
			$isTrusted 	= $row['PMR_IS_TRUSTED'];
			
			$this->logger->logSimple("[" . date('Y-m-d H:i:s') . "]\n");
			$this->logger->logSimple("-- ". $job . ". sending an email to: " . $email . " " . $userType . " user\n");
	
			// 1 year ago
			$yearAgo = strtotime('-1 year');
			$yearAgoAsDate = new DateTime;
			$yearAgoAsDate->setDate( date("Y",$yearAgo), date("m",$yearAgo), date("d",$yearAgo) );
			
			// set the default date
			$now = new DateTime();
			$lastYear = new DateTime();
			$now->setDate(date('Y'), date('m'), date('d')+1);
			$lastYear->setDate(date('Y')-1, date('m'), date('d'));
			
			// store it to a data structure to be passed around
			$period = array('start' => $lastYear, 'end' => $now );
       				
			try 
			{
				// create report/stats based on the TNID
				$time = microtime(true);
				$supplier = Shipserv_Supplier::fetch($tnid);
				$report = $supplier->getEnquiriesStatistic($period);

				if( $supplier->tnid == null )
				{
					$this->logger->logSimple("---- error, this supplier is not published: " . $row['PMR_TNID'] . "\n");
					throw new Exception("Supplier is not published", 500);
				}
				
				// get all tnid related to this email (only for Enquiry data source)
				if( $row['PMR_DATA_SOURCE'] == 'ENQUIRY' )
				{
					$tnids = $this->getAllTnidByEmail( $email, $campaignId, 'ENQUIRY');
					$row['NEW_USER_FROM_ENQUIRY_EMAIL_TO_JOIN_TNID'] = implode($tnids, ",");
				}
				$this->logger->logSimple("---- pulling Enquiry stats for: " . $row['PMR_TNID'] . ' in ' . round( microtime(true) - $time, 2 ). "s\n");
			}
			catch(Exception $e )
			{
				$adapter->setError($e->getMessage(), $email, $campaignId);
				$adapter->setTimestamp( "sent", $email, $campaignId );
				$error = true;	
				$totalEmailNotSent++;
			}
			
			if( $error == false )
			{
				try
				{
					// only send this email out to all TNID that has 5 enq last year
					if( ( $supplier->isPremium() && $supplier->canAccessSVR() && $report['sent'] >= 5 ) || ( $supplier->isPremium() === false && $report['sent'] >= 8 ) )
					{
						$this->logger->logSimple("---- sending email to: " . $email . " - " . (($supplier->isPremium())?"Premium lister":"Basic lister") . " has " . $report['sent']. " enquiry (last year) ");
						$time = microtime(true);
						
						// we need stop sending for load test
						//$notificationManager->stopSending();
						$notificationManager->sendEnquiriesStatistic( $email, $subject, $supplier, $report, $yearAgoAsDate->format("d F Y"), $userType, $userId, $this->getDb(), $this->mode, $row);
						$adapter->setTimestamp( "error", $email, $campaignId );
						
						$this->logger->logSimple(" ... done in " . round( microtime(true) - $time, 2 ). "s\n");
						$totalEmailSent++;
						$emailIsSent = true;
					}
					else
					{
						$this->logger->logSimple("------- Skipping: " . (($supplier->isPremium())?"Premium lister":"Basic lister") . " only has " . $report['sent']. " enquiry (last year)" . "\n");
						$adapter->setError("Skipped " . (($supplier->isPremium())?"Premium lister":"Basic lister") . " with " . $report['sent']. " enquiry", $email, $campaignId);	
						
						$totalEmailNotSent++;
						$emailIsSent = false;
					}
				}
				catch( Exception $e )
				{
					$error = true;
					$this->logger->logSimple("\n---- ---- cannot send email " . $email . " due to \"" . $e->getMessage() . "\". [" . date('d M y h:i:s') . "]\n");
					$adapter->setError($e->getMessage(), $email, $campaignId);	
					$totalEmailNotSent++;
					$emailIsSent = false;
				}
				
				// mark all tnid related to this email (only for Enquiry data source) as SENT
				if( $row['PMR_DATA_SOURCE'] == 'ENQUIRY' )
				{
					// only mark all TNIDs that relates to a single email sent when one email is 
					// actually sent, otherwise keep trying with the next TNID
					if( $emailIsSent == true )
					{
						$this->markAllRecipientAsSent( $email, $campaignId, 'ENQUIRY');
						$this->logger->logSimple("---- ---- Marking other TNID as sent (Enquiry)\n");
					}
					else 
					{
						$adapter->setTimestamp( "sent", $email, $campaignId, $tnid );
					}
				}
				else
				{
					$adapter->setTimestamp( "sent", $email, $campaignId );
				}
				
				$this->logger->logSimple( "\n\n" );
				$supplier->purgeMemcache();
			}
			
		}
		$this->logger->logSimple( "-------------------------------------------------------------------------------------------------------------------------------------" );	
		$this->logger->logSimple( "\n" . $totalEmailSent . " email(s) sent and " . $totalEmailNotSent . " email(s) NOT sent" );
		$this->logger->logSimple( " in  " . round( microtime(true) - $timeAll, 2 ). "s\n" );
		
		//$this->logger->sendEmail("eleonard@shipserv.com", "Notification v1");
	}
	
	
	private function runNotification7()
	{
	
	
		// initialising
		$campaignId = 7;
	
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
	
		// check if something is being overriden by _GET var
		if( isset( $_GET['overrideNumOfEmailSent'] ) && isset( $_GET['auth2'] ) && $_GET['overrideNumOfEmailSent'] != "" && ctype_digit( $_GET['overrideNumOfEmailSent'] ) && md5('override' . $_GET['overrideNumOfEmailSent']) == $_GET['auth2'] )
		{
			$numberOfEmailPerBatch = intval($_GET['overrideNumOfEmailSent']);
		}
	
		// initialise
		$totalEmailNotSent = $totalEmailSent = $job = 0;
		$adapter = new Shipserv_Oracle_Mailer( $this->getDb() );
	
		$this->logger->logSimple("Mailer: Pages RFQ migration notification\n");
		$this->logger->logSimple("-------------------------------------------------------------------------------------------------------------------------------------\n\n");
	
		// start the counter
		$timeAll = microtime(true);

		// get the next recipient
		$recipients = $adapter->getNextRecipient( $numberOfEmailPerBatch, $campaignId, $this->retryFailedMessage, false );
		
		foreach( $recipients as $row )
		{
			$job++;
			$error = false;
			
			$email 		= $row["PMR_EMAIL"];
			$tnid 		= $row["PMR_TNID"];
			$supplierType 	= $row["PMR_DATA_SOURCE"];
			$campaignId = $row['PMR_PMC_ID'];
			try
			{
				$t = $this->getProductByTnid($tnid);
			}
			catch(Exception $e )
			{
				$this->logger->logSimple("---- NOT SENT " . $e->getMessage() . "\n\n");
	
				$adapter->setError($e->getMessage(), $email, $campaignId);
				$adapter->setTimestamp( "sent", $email, $campaignId );
				$error = true;
				$totalEmailNotSent++;
			}
						
			$row['ENABLED_PRODUCT'] = $t['PMR_DATA_SOURCE'];
			$row['NEW_EMAIL_RECIPIENT'] = $t['PMR_EMAIL'];
			$email = trim(strtolower($email));

			// check if user is available with that email
			$user = false;
			$this->logger->logSimple("[" . date('Y-m-d H:i:s') . "]\n");
			
			$this->logger->logSimple("-- ". $job . ". sending an email to: " . $tnid . " " . $supplierType . " supplier\n");
			
			// checking if supplier is integrated - if it is, then skip it
			if( $supplierType == "INTEGRATED" )
			{
				$this->logger->logSimple("---- This supplier is integrated - skipping\n\n");
				
				$adapter->setError('Skipped - Integrated supplier', $email, $campaignId);
				$adapter->setTimestamp( "sent", $email, $campaignId );
				$error = true;
				$totalEmailNotSent++;
				continue;
			}
			
			// trying to pull supplier based on the TNID stored on db, and skip the check where supplier isn't published
			try
			{
				// create report/stats based on the TNID
				$time = microtime(true);
				$supplier = Shipserv_Supplier::fetch($tnid, "", true);
	
				$this->logger->logSimple("---- pulling supplier tnid  " . $row['PMR_TNID'] . ' in ' . round( microtime(true) - $time, 2 ). "s\n");
			}
			catch(Exception $e )
			{
				$this->logger->logSimple("---- NOT SENT " . $e->getMessage() . "\n\n");
	
				$adapter->setError($e->getMessage(), $email, $campaignId);
				$adapter->setTimestamp( "sent", $email, $campaignId );
				$error = true;
				$totalEmailNotSent++;
			}
			
			// if supplier not found
			if( $supplier->tnid == null )
			{
				$this->logger->logSimple("---- supplier not found\n\n");
				$adapter->setError('Skipped - supplier not found', $email, $campaignId);
				$adapter->setTimestamp( "sent", $email, $campaignId );
				$error = true;
				$totalEmailNotSent++;
				continue;
			}
				
			if( $error == false )
			{
				try
				{
					// sending the start/smart recipient
					//$notificationManager->stopSending();
					$notificationManager->sendEmailNotificationToPagesTNRFQRecipient($email, $subject, $supplier, $supplierType, $this->getDb(), $this->mode, $row);
					$adapter->setTimestamp( "sent", $email, $campaignId );
					$totalEmailSent++;
					$this->logger->logSimple("---- sending email to TN RFQ recipient: " . $email . "\n" );
				}
				catch( Exception $e )
				{
					$error = true;
					$this->logger->logSimple("\n---- ---- cannot send email " . $email . " due to \"" . $e->getMessage() . "\". [" . date('d M y h:i:s') . "]\n");
					$adapter->setError($e->getMessage(), $email, $campaignId);
					$totalEmailNotSent++;
					$emailIsSent = false;
				}
	
				$this->logger->logSimple( "\n\n" );
			}
		}
		$this->logger->logSimple( "-------------------------------------------------------------------------------------------------------------------------------------" );
		$this->logger->logSimple( "\n" . $totalEmailSent . " email(s) sent and " . $totalEmailNotSent . " email(s) NOT sent" );
		$this->logger->logSimple( " in  " . round( microtime(true) - $timeAll, 2 ). "s\n" );
	}	
	
	public function getProductByTnid($tnid, $campaignId = 7)
	{
		$sql = "SELECT pmr_data_source, pmr_email FROM pages_mailer_recipient WHERE pmr_tnid=:tnid AND pmr_data_source !='PAGES' AND pmr_pmc_id=:campaignId";
		$result = $this->getDb()->fetchAll($sql, array('tnid' => $tnid, 'campaignId' => $campaignId));
		
		// check the public TNID
		if( $result[0]['PMR_DATA_SOURCE'] == "" && $result[0]['PMR_EMAIL'] == "" )
		{
			$sql = "SELECT pmr_data_source, pmr_email FROM pages_mailer_recipient WHERE pmr_tnid=(SELECT spb_public_branch_code FROM supplier_branch WHERE spb_branch_code=:tnid) AND pmr_data_source !='PAGES' AND pmr_pmc_id=:campaignId";
			$result = $this->getDb()->fetchAll($sql, array('tnid' => $tnid, 'campaignId' => $campaignId));
		}
		
		// if it's still empty, then skip this
		if( $result[0]['PMR_DATA_SOURCE'] == "" && $result[0]['PMR_EMAIL'] == "" )
		{
			throw new Exception("Cannot find product and email address");
		}
		
		return $result[0];
	}
	
	/**
	 * Get all TNID
	 * @param string $email
	 * @param id $campaignId
	 * @param string $dataSource
	 */
	private function getAllTnidByEmail( $email, $campaignId, $dataSource = 'ENQUIRY')
	{
		$sql = "SELECT pmr_tnid FROM pages_mailer_recipient WHERE pmr_pmc_id=:campaignId AND pmr_data_source=:dataSource AND pmr_email=:email";
		$rows = $this->getDb()->fetchAll( $sql, compact('email','campaignId','dataSource') );
		foreach( $rows as $key => $row )
		{
			$data[] = $row['PMR_TNID'];
		}		
		return $data;
	}
	
	private function markAllRecipientAsSent($email, $campaignId, $dataSource = 'ENQUIRY')
	{
		$sql = "UPDATE pages_mailer_recipient SET pmr_date_sent=SYSDATE WHERE pmr_pmc_id=:campaignId AND pmr_data_source=:dataSource AND pmr_email=:email";
		return $this->getDb()->query( $sql, compact('email','campaignId','dataSource') );
	}
	
	public function generate( $notificationId )
	{
		if( $notificationId == 1 )
		{
			$this->runNotification1();
		}
		else if( $notificationId == 2 )
		{
			$this->runNotification2();
		}
		else if( $notificationId == 7 )
		{
			$this->runNotification7();
		}
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
