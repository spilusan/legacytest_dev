<?php
class Myshipserv_Security_Spam extends Shipserv_Object
{
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->db = $this->getDb();
		$this->config = $this->getConfig();
		$this->maxSelectedSuppliers = $this->config->shipserv->enquiryBasket->maximum;
	}
	
	/**
	 * Getting the captcha from the memcache
	 * @return Zend_Captcha_Image
	 */
	public function getCaptchaFromMemcache()
	{
		$config = $this->config;
		// connect to memcache and fetch the enquiry
		$memcache = new Memcache();
        $memcache->connect($config->memcache->server->host, $config->memcache->server->port);

        // generate unique key based on the session_id
        $key = "Shipserv_Captcha_" . session_id();
		$captcha = $memcache->get($key);
		
		return $captcha;
	}
	
	/**
	 * Creating new captcha and store it to the memcached by session_id
	 * @return Zend_Captcha_Image
	 */
	public function createCaptcha()
	{
		if( is_dir("/tmp/captcha") == false )
		{
			mkdir( "/tmp/captcha", 0777 );
		}
		
		chmod("/tmp/captcha", 0777);
		
		$config = $this->config;
		// connect to memcache and fetch the enquiry
		$memcache = new Memcache();
		$memcache->connect($config->memcache->server->host, $config->memcache->server->port);
		
		
		$captcha = new Zend_Captcha_Image(
			array(
				'font' => '/usr/share/fonts/liberation/LiberationMono-Regular.ttf',
				'imgDir' => '/tmp/captcha/',
				'imgUrl' => '/images/captcha/',
				'wordLen' => 5,
				'width' => 150,
				'height' => 55,
				'dotNoiseLevel' => 20,
				'lineNoiseLevel' => 10
			)
		);
		
		$captcha->generate();
		
		$key = "Shipserv_Captcha_" . session_id();
		$memcache->set($key, $captcha);
		
		return $captcha;
	}
	
	/**
	 * Alias for function createCaptcha
	 * @return Zend_Captcha_Image
	 */
	public function getCaptcha()
	{
        $captcha = $this->createCaptcha();
		return $captcha;
	}
	
	public function getRFQSentByPeriodWeeks($user, $formValues, $numOfWeek = 4, $filesUploaded = array())
	{
		$db = $this->db;
		$numOfDays = (int) $numOfWeek * 7;
		
		// requested by Crystal;
		//if($_SERVER['APPLICATION_ENV'] == "testing")
		//{
		//	$numOfDays = 40;
		//}
		
		/*
		$sql = "
			SELECT
				pin_id,
				spb_name,
				spb_branch_code
			FROM
				pages_inquiry JOIN pages_inquiry_recipient ON (pin_id = pir_pin_id)
				JOIN supplier_branch ON (pir_spb_branch_code = spb_branch_code)
			WHERE
				TRIM(pin_inquiry_text)=:description
				AND pin_usr_user_code=:userId
				AND pin_creation_date BETWEEN SYSDATE-" . $numOfDays . " AND SYSDATE
		";
		*/
		$sql = "
      		SELECT
				pin_id,
				spb_name,
				spb_branch_code
			FROM
				pages_inquiry JOIN pages_inquiry_recipient ON (pin_id = pir_pin_id)
				JOIN supplier_branch ON (pir_spb_branch_code = spb_branch_code)
			WHERE
       			-- pull only inquiry that's sent by a given userId
				pin_usr_user_code=:userId
				
				-- restrict x days of PIN
				AND pin_creation_date BETWEEN SYSDATE-" . $numOfDays . " AND SYSDATE
				
				-- checking description OR file attachments
		        AND 
		        (
		          -- check the content of the enquiry if it's 100% the same
		          TRIM(pin_inquiry_text)=:description
		";
		if( count($filesUploaded) > 0 )
		{
			$sql .= "
			";
			foreach( $filesUploaded as $file )
			{
				$files[] = "'" . strtolower(basename($file)) . "'";
			}
			$sql .= "
		          -- check the attachment of the enquiry if he already tried to send this before
		          OR
		          (
						PIN_ID IN 
						(
							SELECT
							  PIN_ID
							FROM
							  pages_inquiry 
							      RIGHT JOIN attachment_txn ON (pin_id=ATT_TRANSACTION_ID AND ATT_TRANSACTION_TYPE='PIN')
							        JOIN attachment_file ON (ATF_ID=ATT_ATF_ID)
							WHERE
							  pin_usr_user_code=:userId
							  AND pin_creation_date BETWEEN SYSDATE-" . $numOfDays . " AND SYSDATE
							  AND LOWER(ATF_ORIG_FILENAME) IN (" . implode(",", $files) . ")			
		      	   		)
		      	   )
			";
		}
		$sql .= "
		      )
		";

		$result = $db->fetchAll( $sql, array('userId' => $user->userId, 'description' => trim($formValues['enquiry-text']) ) );
		return $result;
	}
	
	public function checkTotalRFQSentPerDay($user, $formValues)
	{
		$db = $this->db;
		$sql = "
			SELECT 
				COUNT(*) TOTAL 
			FROM 
				pages_inquiry 
			WHERE 
				PIN_USR_USER_CODE=:userId 
				AND PIN_CREATION_DATE>=SYSDATE-1
		";
			
		$result = $db->fetchAll( $sql, array('userId' => $user->userId) );
		if( isset($result[0]) ) return $result[0]['TOTAL'];
		return 0;
	}
	
	public function getAttachment($files, $formValues)
	{
		$db = $this->db;
		$sql = "
		SELECT
			pin_id
    	  , RTRIM( xmlagg( xmlelement( c, lower( ATF_FILENAME ) || ',,,' ) order by  lower( ATF_FILENAME ) ).extract ( '//text()' ), ',,,' )
		FROM
			pages_inquiry 
				RIGHT JOIN attachment_txn ON (pin_id=ATT_TRANSACTION_ID AND ATT_TRANSACTION_TYPE='PIN')
					JOIN attachment_file ON (ATF_ID=ATT_ATF_ID)
		WHERE
			TRIM(pin_inquiry_text)=:description
			AND pin_usr_user_code=:userId
			AND pin_creation_date BETWEEN SYSDATE-7 AND SYSDATE

	    GROUP BY pin_id
		";
			
		$result = $db->fetchAll( $sql, array('userId' => $user->userId, 'description' => trim($formValues['enquiry-text']) ) );
		
		return $result;
		
	}
	
	/**
	 * Function to notify SS if any user is trying to send more than 15 suppliers per week with the same RFQ content (line items/buyer's comments)
	 * new automated vetting process that automatically mark a user as NOT TRUSTED/QUARANTINED
	 * @param $user Shipserv_User user that we want to check against
	 * @param $formValues parameters, at the moment we only check $formValues['enquiry-text'] can be extended later
	 * @param $enquiryBasket 
	 * @param $cookieManager 
	 * @param $files all uploaded files
	 **/
	public function checkRFQForSpam($user, $formValues, $enquiryBasket, $cookieManager, $files)
	{
		$db = $this->db;
		$userId = $user->userId;

		// checking/pulling all RFQ sent by X weeks by this user that has 100% description or same attachment (files)
		$result = $this->getRFQSentByPeriodWeeks($user, $formValues, 1, $files);

		$totalSentSoFar = count( $result );
		$totalSupplierSelected = count((array) $enquiryBasket['suppliers']);
		$totalAttemptedToSend = $totalSentSoFar + $totalSupplierSelected;
		
		// count total sent so far and add them up with total that this user is going to sent, and check it against
		// the threshold set on the application.ini
		if( $totalAttemptedToSend > $this->maxSelectedSuppliers )
		{
	
			// log this as spammer
			$user->logActivity(Shipserv_User_Activity::ENQUIRY_SENDER_MARKED_AS_SPAMMER, 'PAGES_USER', $user->userId, $user->email);
			
			// getting the list of the supplier that has been sent previously
			foreach( $result as $r ) $oldSupplier .= "-" . $r['SPB_NAME'] . " (" . $r['SPB_BRANCH_CODE'] . ")\n";
			
			// getting the list of supplier that are about to be spammed
			foreach( $enquiryBasket['suppliers'] as $x )
			{
				$sup = Shipserv_Supplier::fetch( $x );
				$newSupplier .= "-" . $sup->name . " (" . $sup->tnid . ")\n";
			}
			
			$message = <<<EOT
This buyer  has just sent a number of repeat Pages RFQs to more than {$totalAttemptedToSend} suppliers in total.
	
THERE IS A HIGH CHANCE THIS IS A SPAM RFQ.
	
	
THIS RFQ IS NOW IN QUARANTINE.
	
	
This is at least their second (or more) RFQ which is very similar or the same as previous RFQs recently sent. This RFQ has been automatically placed in Quarantine.
	
	
Please refer to the internal policy for rules of use of Pages. (https://www.shipserv.com/help#8)
	
	
Generally, buyers are not allowed to send more than {$this->maxSelectedSuppliers} suppliers the same or similar RFQ in more than one transactions.  There are only very rare exceptions.
	
	
Also, buyers sending generic emails where they are not requesting prices on a specific items/s are not allowed.
	
------------------------------------------------------------------------------------------------
Sender Name: {$formValues['sender-name']}
Company Name: {$formValues['company-name']}
Email: {$formValues['sender-email']}
Phone: {$formValues['sender-phone']}
Country: {$formValues['sender-country']}
	
------------------------------------------------
	
Vessel name: {$formValues['vessel-name']}
IMO: {$formValues['imo']}
Delivery location: {$formValues['delivery-location']}
Delivery date: {$formValues['delivery-date']}
Search ID: {$searchRecId}
	
------------------------------------------------
	
Subject: {$formValues['enquiry-subject']}
RFQ Detail:
{$formValues['enquiry-text']}
	
------------------------------------------------
	
	
This user has sent same RFQ to {$totalSentSoFar} suppliers:
{$oldSupplier}
	
This user is trying to send same RFQ to {$totalSupplierSelected} suppliers:
{$newSupplier}
EOT;

			// send us a notification
			$zm = new Zend_Mail('UTF-8');
			$zm->setFrom('support@shipserv.com', 'ShipServ Pages');
			$zm->setSubject('Spammer was detected when he/she trying to sending RFQ from Pages');
			
			// determine the email destination
			if( $_SERVER['APPLICATION_ENV'] == "production" )
			{
				$zm->addTo('pages.rfq.spam.report@shipserv.com', '');			
			}
			else if($_SERVER['APPLICATION_ENV'] == "testing")
			{
				$zm->addTo('cgonzalez@shipserv.com', 'Crystal Gonzalez');
				$zm->addTo('jgo@shipserv.com', 'Johanna Go');
			}
			else
			{
				$zm->addTo('eleonard@shipserv.com', 'Elvir Leonard');
			}
				
			// set the message
			$zm->setBodyText($message);
			$zm->send();
				
			$cookieManager->clearCookie('enquiryStorage');
			
			// mark this user as QUARANTINED
			$sql = "UPDATE users SET USR_PAGES_ENQUIRY_STATUS = null WHERE USR_USER_CODE=:userId AND USR_PAGES_ENQUIRY_STATUS='TRUSTED'";
			$db->query( $sql, array( "userId" => $userId) );
			
			return true;
		}
		else
		{
			return false;
		}
	}	
}
