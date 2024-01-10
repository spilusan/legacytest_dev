<?php

class Myshipserv_NotificationManager_Exception extends Exception
{
	// Periodic review request notification: no requests pending for user
	const RR_NONE_PENDING = 100;
}

class Myshipserv_NotificationManager extends Shipserv_Memcache
{
	protected $from;

	private static $inst;

    /**
     * @var Zend_Db_Adapter_Oracle
     */
    private $db;
	private $uDao;

	private $returnAsHtml = false;

	private $stopSending = false;

	const NUM_OF_RETRY_BEFORE_SKIPPING_JANGO_SMTP = 3;

	public function __construct ($db)
	{
		$this->db = $db;
		$this->uDao = new Shipserv_Oracle_User($this->db);
		$this->logger = new Myshipserv_Logger_File('mail-out');

	}

	public static function getInstance ()
	{
		if (!self::$inst)
		{
			self::$inst = new self($GLOBALS['application']->getBootstrap()->getResource('db'));
		}
		return self::$inst;
	}

	public function stopSending()
	{
		$this->stopSending = true;
	}

	public function startSending()
	{
		$this->stopSending = false;
	}


	/**
	 * Sends notification that user has requested to join company. E-mail is sent
	 * to company administrators.
	 *
	 * Note: this functionality uses the currently logged-in user to determine
	 * who the requester is.
	 *
	 * Added ny Ulad
	 * Note: Added non required user Id to pass who has requested request join
	 *
	 * @return void
	 */
	public function requestCompanyMembership ($joinReqId, $userId = null)
	{
		$email = new Myshipserv_NotificationManager_Email_RequestCompany($this->db, $joinReqId, $userId);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to new company member following membership approval.
	 *
	 * @return void
	 */
	public function grantCompanyMembership ($recipientUserId, $companyType, $companyId)
	{
		$email = new Myshipserv_NotificationManager_Email_GrantCompany($this->db, $recipientUserId, $companyType, $companyId);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to user who requested company membership that request was declined.
	 *
	 * @return void
	 */
	public function declineCompanyMembership ($recipientUserId, $companyType, $companyId)
	{
		$email = new Myshipserv_NotificationManager_Email_DeclineCompany($this->db, $recipientUserId, $companyType, $companyId);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to user when review request is submitted
	 *
	 * @param <type> $recipient
	 * @param ShipServ_ReviewRequest $reviewRequest
	 */
	public function requestReview ($recipient, $reviewRequest)
	{
		// Default behaviour: send e-mail
		$doSend = true;

		// Retrieve user if exists
		$rUsers = $this->uDao->fetchUsersByEmails(array($recipient["email"]))->makeShipservUsers();

		if ($rUsers)
		{
			// If user does not wish to receive immediate alerts ...
			if ($rUsers[0]->alertStatus != Shipserv_User::ALERTS_IMMEDIATELY)
			{
				$doSend = false;
			}
		}

		if ($doSend)
		{
			$email = new Myshipserv_NotificationManager_Email_RequestReview($this->db, $recipient, $reviewRequest);
			$this->sendMail($email);
		}
	}

	/**
	 * Sends notification to all admins of supplier company when company's review is submitted
	 *
	 * @param Shipserv_Review $review
	 */
	public function reviewAdded ($review)
	{
		$email = new Myshipserv_NotificationManager_Email_ReviewAdded($this->db, $review);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to all admins of supplier company when company's review is edited
	 *
	 * @param Shipserv_Review $review
	 */
	public function reviewEdited ($review)
	{
		$email = new Myshipserv_NotificationManager_Email_ReviewEdited($this->db, $review);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to all admins of buyer company when reply to review is submitted
	 *
	 * @param Shipserv_Review $review
	 */
	public function reviewReplyPosted ($review)
	{
		$email = new Myshipserv_NotificationManager_Email_ReviewReplyPosted($this->db, $review);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to all admins of brand owner company when authorisation is requested by supplier
	 *
     * @deprecated  by brandAuthorisationRequestInSingleNotification() consolidated email
     *
	 * @param Shipserv_BrandAuthorisation $auth
	 */
	public function brandAuthorisationRequested($auth)
	{
		$email = new Myshipserv_NotificationManager_Email_BrandAuthRequested($this->db, $auth);
		$this->sendMail($email);
	}

    /**
     * Sends out confirmation request for new brand authorisation levels
     *
     * Refactored by Yuriy Akopov on 2014-09-11, DE5017
     *
     * @param   Shipserv_BrandAuthorisation|Shipserv_BrandAuthorisation[]   $authorisations
     *
     * @return  string
     */
    public function brandAuthorisationRequestInSingleNotification($authorisations) {
		$email = new Myshipserv_NotificationManager_Email_BrandAuthRequestConsolidated($this->db, $authorisations);
		return $this->sendMail($email);
	}

	/**
	 * Sends notification to all admins of supplier when authorisation is rejected by brand owner
	 *
	 * @param array $auth
	 */
	public function brandAuthorisationRejected($requests)
	{
		$email = new Myshipserv_NotificationManager_Email_BrandAuthRejected($this->db, $requests);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to all admins of supplier when authorisations are granted by brand owner
	 *
	 * @param array $auths
	 */
	public function brandAuthorisationApproved($auths)
	{
		$email = new Myshipserv_NotificationManager_Email_BrandAuthApproved($this->db, $auths);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to all supplier admins that brand authorisations are being reviewed by brand owner
	 *
	 * @param Shipserv_BrandAuthorisation $auth
	 * @param integer $brandOwnerId
	 */
	public function brandAuthorisationPendingApproval($auth, $brandOwnerId)
	{
		$email = new Myshipserv_NotificationManager_Email_BrandAuthPendingApproval($this->db, $auth, $brandOwnerId);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to all supplier admins that brand authorisations are being granted because of luck of brand onwer
	 *
	 * @param Shipserv_BrandAuthorisation $auth
	 */
	public function brandAuthorisationRestored($auth)
	{
		$email = new Myshipserv_NotificationManager_Email_BrandAuthRestored($this->db, $auth);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to support
	 *
	 * @param array $params
	 */
	public function brandOwnershipRequested($params)
	{
		$email = new Myshipserv_NotificationManager_Email_BrandOwnershipRequested($this->db, $params);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to all admins of supplier when authorisation is rejected by membership owner
	 *
	 * @param array $auth
	 */
	public function membershipAuthorisationRejected($requests)
	{
		$email = new Myshipserv_NotificationManager_Email_MembershipAuthRejected($this->db, $requests);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to all admins of supplier when authorisations are granted by membership owner
	 *
	 * @param array $auths
	 */
	public function membershipAuthorisationApproved($auths)
	{
		$email = new Myshipserv_NotificationManager_Email_MembershipAuthApproved($this->db, $auths);
		$this->sendMail($email);
	}


	/**
	 * Sends notification to all admins of supplier when authorisation is rejected by category editor
	 *
	 * @param array $auth
	 */
	public function categoryAuthorisationRejected($requests)
	{
		$email = new Myshipserv_NotificationManager_Email_CategoryAuthRejected($this->db, $requests);
		$this->sendMail($email);
	}

	/**
	 * Sends notification to all admins of supplier when authorisations are granted by brand owner
	 *
	 * @param array $auths
	 */
	public function categoryAuthorisationApproved($auths)
	{
		$email = new Myshipserv_NotificationManager_Email_CategoryAuthApproved($this->db, $auths);
		$this->sendMail($email);
	}

	/**
	 * Sends invitation to external supplier to join pages
	 *
	 * @param array $recipient
	 * @param string $text
	 */
	public function brandInviteSupplier ($recipient, $text,$brandId, $companyId)
	{

		$email = new Myshipserv_NotificationManager_Email_BrandInviteSupplier($this->db, $recipient, $text, $brandId, $companyId);
		$this->sendMail($email);

	}

	/**
	 * Sends invitation to external supplier to join pages
	 *
	 * @param array $recipient
	 * @param string $text
	 */
	public function membershipInviteSupplier ($recipient, $text, $membershipId, $companyId)
	{

		$email = new Myshipserv_NotificationManager_Email_MembershipInviteSupplier($this->db, $recipient, $text, $membershipId, $companyId);
		$this->sendMail($email);

	}

	/**
	 * Sends an email to brand owner to authorise suppliers
	 *
	 * @param Shipserv_BrandAuthorisation $auth
	 * @param String $emailAddress
	 * @param String $message
	 * @author Elvir <eleonard@shipserv.com>
	 */
	public function inviteBrandOwnerToAuthoriseSupplier($auth, $emailAddress, $message)
	{
		$email = new Myshipserv_NotificationManager_Email_BrandAuthInviteBrandOwnerToAuthoriseSupplier($this->db, $auth, $emailAddress, $message);
		if( $this->returnAsHtml )
			return $this->sendMail($email);
		else
			$this->sendMail($email);
	}
	/**
	 * Send email to specified brand owner on auth variable to join Pages
	 *
	 * @param Shipserv_BrandAuthorisation $auth
	 * @author Elvir <eleonard@shipserv.com>
	 */
	public function invitePassiveBrandOwnerToClaim($auth)
	{
		$email = new Myshipserv_NotificationManager_Email_InviteBrandOwnerToClaim($this->db, $auth);
		$this->sendMail($email);
	}

	/**
	 * Send email to copmany approved supplier list email addresses
	 * 
	 * @param integer $branchCode Branch Code, which was not on the approved list
	 * @param integer $orgCode, Ord code,  who we are sending the email to
	 * @author Attila O
	 */
	public function companyApprovedSuppliers($branchCodes, $orgCode)
	{
		$email = new Myshipserv_NotificationManager_Email_CompanyApprovedSuppliers($this->db, $branchCodes, $orgCode);
		if (count($email->recepientsList) > 0)
		{
			$this->sendMail($email);
		}
	}
	
	/**
	 * Send an invitation to unverified supplier why they should join pages.
	 * Type of environment: CLI
	 *
	 * @param unknown_type $recipient
	 * @param unknown_type $data
	 */
	public function inviteUnverifiedSupplier($user, $data)
	{
		// Default behaviour: send e-mail
		$doSend = true;

		if ($user)
		{
			// If user does not wish to receive immediate alerts ...
			if ($user->alertStatus != Shipserv_User::ALERTS_IMMEDIATELY)
			{
				$doSend = false;
			}
		}

		if ($doSend)
		{
			$email = new Myshipserv_NotificationManager_Email_InviteUnverifiedSupplier($this->db, $user, $data);
			$this->sendMail($email);
		}
	}

	/**
	 * Sends user enquiry email sent from /info/contact-us to shipserv
	 *
	 * @return void
	 */
	public function contactUsEmail($receipient, $subject, $message)
	{
	    $email = new Myshipserv_NotificationManager_Email_ContactUs($this->db, $receipient, $subject, $message);
	    $this->sendMail($email);
	}	

	/**
	 * Sends e-mail to user in order to confirm user has access to e-mail address provided.
	 *
	 * @return void
	 */
	public function confirmEmail($userId)
	{
		$email = new Myshipserv_NotificationManager_Email_ConfirmEmail($this->db, $userId);
		$this->sendMail($email);
	}


	/**
	 * Sends e-mail to user in order to confirm user has access to e-mail address provided.
	 *
	 * @return void
	 */	
	public function joinCompanyConfirmEmail($userId, $orgName)
	{
	    $email = new Myshipserv_NotificationManager_Email_JoinCompanyConfirmEmail($this->db, $userId, $orgName);
	    $this->sendMail($email);
	}
	
	/**
	 * Sends e-mail to user indicating count of outstanding review requests.
	 *
	 * @return void
	 * @throws Myshipserv_NotificationManager_Exception if user has no pending review requests.
	 */
	public function pendingReviewRequests ($recipientId)
	{
		$email = new Myshipserv_NotificationManager_Email_PeriodicReviewReq($recipientId);
		$this->sendMail($email);
	}

	/**
	 * Sends e-mail to user indicating count of outstanding review requests.
	 *
	 * @return void
	 * @throws Myshipserv_NotificationManager_Exception if user has no pending review requests.
	 */
	public function sendCustomerSatisfactionSurveyInvitation ($email)
	{
		$this->from = array("name" => "ShipServ","email" => "info@shipserv.com");
		$email = new Myshipserv_NotificationManager_Email_CustomerSatisfactionSurvey($email);
		$this->sendMail($email);
	}

	public function sendSIRInviteForBasicLister($email, $subject, $supplier, $statistic, $dateAsString, $userType, $userId, $db)
	{
		$this->from = array("name" => "ShipServ","email" => "info@shipserv.com");
		$email = new Myshipserv_NotificationManager_Email_EmailCampaign_SIRInvitationEmail($email, $subject, $supplier, $statistic, $dateAsString, $userId, $db);
		if( $userType == 'PAGES' )
		{
			$email->setAsSalesforceEmail(false);
		}
		else
		{
			$email->setAsSalesforceEmail();
		}
		$this->sendMail($email);
	}


	public function sendSIRSummaryToCustomer($email, $subject, $supplier, $statistic, $dateAsString, $db, $htmlMode = true, $message, $salutation)
	{
		$this->from = array("name" => "ShipServ","email" => "info@shipserv.com");
		$email = new Myshipserv_NotificationManager_Email_SIRSendSummaryToCustomer($email, $subject, $supplier, $statistic, $dateAsString, $db, $htmlMode, $message, $salutation);
		$this->sendMail($email);
	}

	public function sendSIRInviteReminderForBasicLister($email, $subject, $supplier, $statistic, $dateAsString, $userType, $userId, $db, $htmlMode = true)
	{
		$this->from = array("name" => "ShipServ","email" => "info@shipserv.com");
		$email = new Myshipserv_NotificationManager_Email_EmailCampaign_SIRInvitationEmailReminder($email, $subject, $supplier, $statistic, $dateAsString, $userId, $db, $htmlMode);
		if( $userType == 'PAGES' )
		{
			$email->setAsSalesforceEmail(false);
		}
		else
		{
			$email->setAsSalesforceEmail();
		}
		$this->sendMail($email);
	}

	public function sendSIRInvitePremiumListing($email, $subject, $supplier, $statistic, $dateAsString, $userType, $userId, $db, $htmlMode = true, $data = null)
	{
		$this->from = array("name" => "ShipServ","email" => "info@shipserv.com");
		$email = new Myshipserv_NotificationManager_Email_EmailCampaign_SIRInvitationPremiumListing($email, $subject, $supplier, $statistic, $dateAsString, $userId, $db, $htmlMode, $data);

		$this->sendMail($email);

	}

	public function sendEnquiriesStatistic($email, $subject, $supplier, $statistic, $dateAsString, $userType, $userId, $db, $htmlMode = true, $data = null)
	{
		$this->from = array("name" => "ShipServ","email" => "info@shipserv.com");
		$email = new Myshipserv_NotificationManager_Email_EmailCampaign_EnquiriesStatistic($email, $subject, $supplier, $statistic, $dateAsString, $userId, $db, $htmlMode, $data);

		$this->sendMail($email);

	}

	public function sendEmailToPremiumSupplierWithZeroUser($email, $subject, $supplier, $statistic, $dateAsString, $userType, $userId, $db, $htmlMode = true, $data = null)
	{
		$this->from = array("name" => "ShipServ","email" => "info@shipserv.com");
		$email = new Myshipserv_NotificationManager_Email_EmailCampaign_PremiumSupplierWithZeroUser($email, $subject, $supplier, $statistic, $dateAsString, $userId, $db, $htmlMode, $data);

		$this->sendMail($email);
	}

	public function sendEmailToUpsellNonPayingSupplier($email, $supplier, $statistic, $data = null)
	{
		$this->from = array("name" => "ShipServ","email" => "info@shipserv.com");
		$email = new Myshipserv_NotificationManager_Email_EmailCampaign_UpsellNonPayingSupplier($email, $supplier, $statistic, $data);
		$this->sendMail($email);
	}

	public function sendBuyerFeedbackWhenSupplierDeclineRFQ ($email, $response, $supplierId)
	{
		$email = new Myshipserv_NotificationManager_Email_RFQDeclineSurveyBySupplierToBuyer($email, $response, $supplierId);
		$this->sendMail($email);
	}

	/**
	 * Sends e-mail to user newly added to company.
	 *
	 * @return void
	 */
	public function addCompanyUser($newMemberUserId, $companyType, $companyId, $addedByUsername)
	{
	    if ($companyType != 'BYB') {
	        $email = new Myshipserv_NotificationManager_Email_AddCompanyUser($this->db, $newMemberUserId, $companyType, $companyId, $addedByUsername);
	        $this->sendMail($email);	        
	    }
	}

	/**
	 * Sends e-mail to newly created user.
	 *
	 * @return void
	 */
	public function createUser ($newUserId, $password)
	{
		$email = new Myshipserv_NotificationManager_Email_CreateUser($this->db, $newUserId, $password);
		$this->sendMail($email);
	}

	/**
	 * Fetch logged-in user, or throw Exception if none.
	 *
	 * @return Shipserv_User
	 */
	private function getSender ()
	{
		$user = Shipserv_User::isLoggedIn();
		if (!$user)
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}
		return $user;
	}

	/**
	 * Fetch safe e-mail recipient for DEV / UAT from config.
	 * New feature requested by David/Mark: They want to override the email on the UAT/Dev before sending the campaign out
	 * @return string Email
	 */
	private function getTestEmailRecipient ()
	{
		// requested by Mark and David
		if( isset( $_GET['overrideEmail'] ) && isset( $_GET['auth'] ) && $_GET['overrideEmail'] != "" && md5('override' . $_GET['overrideEmail']) == $_GET['auth'] )
		{
			return trim($_GET['overrideEmail']);
		}
		else
		{
			$config = Zend_Registry::get('config');
			return $config->notifications->test->email;
		}
	}

	/**
	 * Tests for an e-mail ending '@shipserv.com' (case insensitive, ignoring whitespace)
	 *
	 * @return bool True if $strEmail is a ShipServ e-mail address.
	 */
	private function isShipServEmail ($strEmail)
	{
		static $testSuffix = '@shipserv.com';

		$spRes = strpos(strtolower(trim($strEmail)), $testSuffix);
		return $spRes !== false && ($spRes + strlen($testSuffix)) == strlen($strEmail);
	}

	/**
	 * createZendMailFromRecipientList
	 *
	 *
	 * @param unknown_type $email
	 * @param unknown_type $recipientList
	 */
	public function createZendMailFromRecipientList($email, $recipientList, &$contents)
	{
		$zm 				= new Zend_Mail('UTF-8');
		$bodyByRecipient 	= $email->getBody();
		$plainText 			= $textOnlyBodyByRecipient[$recipientList->getHash()] . $debugMessage;
		$html 				= $bodyByRecipient[$recipientList->getHash()] . nl2br($debugMessage);

		if( $this->from["name"] != "" )
		{
			$zm->setFrom($this->from["email"], $this->from["name"]);
		}
		else
		{
			$zm->setFrom('info@shipserv.com', 'ShipServ');
		}

		if( $_SERVER['APPLICATION_ENV'] != 'production' )
		{
			$zm->setSubject('[' . $_SERVER['APPLICATION_ENV'] . '] ' . $email->getSubject());
		}
		else
		{
			$zm->setSubject($email->getSubject());
		}


		// check email format
		if( isset( $email->mode ) && $email->mode == 'text' )
		{
			$zm->setBodyText($plainText);
		}
		else if( isset( $email->mode ) && $email->mode == 'both' )
		{
			$zm->setBodyText($plainText);
			$zm->setBodyHtml($html);
		}
		else
		{
			$zm->setBodyHtml($html);
		}

		$contents[] = $html;

		//Attachments
		if($attachments){
			foreach($attachments as $attachment){
				if(file_exists($attachment['file'])){
					$at = new Zend_Mime_Part(file_get_contents($attachment['file']));
					$at->filename = basename($attachment['file']);
					$at->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
					$at->encoding = Zend_Mime::ENCODING_BASE64;
					$zm->addAttachment($at);
					unset($at);
				}
			}
		}

		// If in production, use the real recipient
		if ($_SERVER['APPLICATION_ENV'] == 'production')
		{
			$zm = $recipientList->processZendMail($zm);
		}

		// If not in production, ensure a safe recipient is used
		else
		{
			$rEmail = $this->getTestEmailRecipient();
			if ($rEmail != '')
			{
				$zm->addTo($rEmail, $rEmail);

				$debugMessage 	= $recipientList->getDebugMessageForNonProductionEnvironment();

				if( isset( $email->mode ) && $email->mode == 'text' )
				{
					$zm->setBodyText($plainText);
				}
				else if( isset( $email->mode ) && $email->mode == 'both' )
				{
					$zm->setBodyText($plainText);
					$zm->setBodyHtml($html . $debugMessage);
				}
				else
				{
					$zm->setBodyHtml($html . $debugMessage);
				}
			}
		}

		return $zm;
	}
	/**
	 * Dispatches provided e-mail object (this may have multiple recipients).
	 *
	 * Also ensures that e-mails are not sent to real users in DEV / UAT. In these
	 * environments, e-mails are re-routed to the address returned by getTestEmailRecipient().
     *
     * @param   Myshipserv_NotificationManager_Email_Abstract $email
	 *
	 * @return void|string
	 */
	private function sendMail (Myshipserv_NotificationManager_Email_Abstract $email)
	{
		$zmArr = array();
		$sendEmailOut = true;

		if( $this->skipJangoSmtp() === true )
		{
			$email->enableSMTPRelay = false;
		}

		// determining the recipient of the email/notification
		// if the recepient wrapped in an object below, then it'll be handled differently
		if( gettype($email->getRecipients()) == "object" && get_class($email->getRecipients()) == "Myshipserv_NotificationManager_Recipient" )
		{
			$zmArr[] = $this->createZendMailFromRecipientList($email, $email->getRecipients(), $contents);
		}
		// if receipients wrapped in an array
		else
		{
			foreach ((array)$email->getRecipients() as $r)
			{
				$x = $this->createZendMailFromArray($email, $r, $contents);
				$zmArr[] = $x;
			}
		}

		// forcing the notification manager to use jango
		// $email->enableSMTPRelay = true;

		// Send e-mails - this can be override
		// by doing $nm->stopSending() when initialise the notification manager
		if( $this->stopSending === false )
		{
			foreach ($zmArr as $zm)
			{
				$errorMessage = "";
				$this->logger->log(str_repeat("-", 100));
				$this->logger->log("Sending email " . $email->getHash());
				$this->logger->log("- to: " . json_encode($email->getRecipients()));
				$this->logger->log("- subject: " . json_encode($email->getSubject()));
				$this->logger->log("- " . $this->totalNumberOfTimeSent($email->getHash()) . ' emails has been sent previously');
				//$this->logger->log(end(array_values($email->getBody()))); //debug body with logs

				// check if email gets sent thru jango
				if( $email->getTransport() !== null && $this->skipJangoSmtp() === false )
				{
					$this->logger->log("- using JangoSMTP relay");
					try
					{
						if( $sendEmailOut === true )
						{
							$zm->send($email->getTransport());
						}

						$this->emailIsSent($email->getHash());
						$this->logger->log("-- sent");
					}
					catch(Exception $e)
					{
						// storing the state of jango
						$this->reportErrorOnJango();
						$this->logger->log("- problem detected: " . $e->getMessage());
						$this->logger->log("- trying shipserv relay instead ");

						if( $sendEmailOut === true )
						{
							// removing jango curly bracket
							$x = $zm->send(null);
						}

						$this->emailIsSent($email->getHash());
						$this->logger->log("-- sent");

					}
				}
				else
				{
					if( $email->getTransport() !== null && $this->skipJangoSmtp() === true )
					{
						$this->logger->log("- using Shipserv relay because jango is having problem since last hour");
					}
					else
					{
						$this->logger->log("- using Shipserv relay");
					}

					if( $sendEmailOut === true )
					{
						$x = $zm->send(null);
					}

					$this->emailIsSent($email->getHash());

					$this->logger->log("-- sent");
				}
			}
		}

		if( $this->returnAsHtml == true )
		{
			return $contents;
		}
	}

	/**
	 * Keeping track the state of JangoSMTP.
	 * storing number of times that the atemp been made
	 * if system has been trying to send email thru jango for more than 10 times an hour
	 * then skip jango - and use shipserv smtp
	 */
	public function reportErrorOnJango()
	{
		$totalError = $this->memcacheGet(__CLASS__, "", "jangoThrowError");

		if( $totalError == null || $totalError == "" )
		{
			$this->setMemcacheTTL(3600);
			$this->memcacheSet(__CLASS__, "", "jangoThrowError", 1);
		}
		else
		{
			$totalError += 1;
			$this->memcacheSet(__CLASS__, "", "jangoThrowError", $totalError);
		}
	}

	/**
	 * Function to check if Jango is not working or not
	 * This will return true if Jango isnt working
	 * @return boolean
	 */
	public function skipJangoSmtp()
	{
		$totalError = $this->memcacheGet(__CLASS__, "", "jangoThrowError");
		return ( $totalError >= self::NUM_OF_RETRY_BEFORE_SKIPPING_JANGO_SMTP ) ? true: false;
	}

	public function isJangoWorking()
	{
		$totalError = $this->memcacheGet(__CLASS__, "", "jangoThrowError");
		return ( $totalError == 0 ) ? true: false;
	}
	public function totalNumberOfTimeSent($messageId)
	{
		$data = @unserialize($this->memcacheGet(__CLASS__, "", "previouslySentItems"));
		return ($data[$messageId]===null)?0:$data[$messageId];
	}

	public function emailIsSent($messageId)
	{
		$data = @unserialize($this->memcacheGet(__CLASS__, "", "previouslySentItems"));
		$data[$messageId] += 1;
		$this->memcacheSet(__CLASS__, "", "previouslySentItems", serialize($data));

	}

	/**
	 * Current implementation
	 *
	 * @param unknown_type $email
	 * @param unknown_type $r
	 */
	public function createZendMailFromArray($email, $r, &$contents)
	{
		$zm = new Zend_Mail('UTF-8');

		$bodyByRecipient = $email->getBody();
		$textOnlyBodyByRecipient = "";

		// if sender is provided/overridden
		if( $this->from["name"] != "" )
		{
			$zm->setFrom($this->from["email"], $this->from["name"]);
		}
        else
        {
            if ($email instanceof Myshipserv_NotificationManager_Email_AutoMatchQuoteNotSent) {
                $zm->setFrom('info@shipserv.com', 'ShipServ');
            } else {
                $zm->setFrom('info@shipserv.com', 'ShipServ Pages');
            }
		}

		// setting the subject
		if( $_SERVER['APPLICATION_ENV'] != 'production' )
		{
			$zm->setSubject('[' . $_SERVER['APPLICATION_ENV'] . '] ' . $email->getSubject());
		}
		else
		{
			$zm->setSubject($email->getSubject());
		}

		//Attachments
		if($attachments){
			foreach($attachments as $attachment){
				if(file_exists($attachment['file'])){
					$at = new Zend_Mime_Part(file_get_contents($attachment['file']));
					$at->filename = basename($attachment['file']);
					$at->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
					$at->encoding = Zend_Mime::ENCODING_BASE64;
					$zm->addAttachment($at);
					unset($at);
				}
			}
		}

		// If in production, use the real recipient
		if ($_SERVER['APPLICATION_ENV'] == 'production')
		{
			$zm->addTo($r['email'], $r['name']);
		}

		// If not in production, ensure a safe recipient is used
		else
		{
			$rEmail = $this->getTestEmailRecipient();
			if ($rEmail != '')
			{
				$zm->addTo($rEmail, $rEmail);
			}
		}

		switch ( $email->mode )
		{
			case "text":	$zm->setBodyText(
								$textOnlyBodyByRecipient[$r['email']]
								. ( ( $_SERVER['APPLICATION_ENV'] != 'production' ) ? "\nDEBUG - this e-mail was re-routed here from intended recipient: {$r['email']} ({$r['name']})" : "" )
							);
							break;

			case "both":	$zm->setBodyText(
								$textOnlyBodyByRecipient[$r['email']]
								. ( ( $_SERVER['APPLICATION_ENV'] != 'production' ) ? "\nDEBUG - this e-mail was re-routed here from intended recipient: {$r['email']} ({$r['name']})" : "" )
							);
							$zm->setBodyHtml(
								$bodyByRecipient[$r['email']]
								. ( ( $_SERVER['APPLICATION_ENV'] != 'production' ) ? "<br /><br />DEBUG - this e-mail was re-routed here from intended recipient: {$r['email']} ({$r['name']})" : "" )
							);
							break;

			default:		$zm->setBodyHtml(
								$bodyByRecipient[$r['email']]
								. ( ( $_SERVER['APPLICATION_ENV'] != 'production' ) ? "<br /><br />DEBUG - this e-mail was re-routed here from intended recipient: {$r['email']} ({$r['name']})" : "" )
							);
							break;
		}

		$contents[] = $bodyByRecipient[$r['email']];

		return $zm;
	}

	/**
	 * Requested by Kevin to test email content on different mail reader
	 *
	 * @author Elvir <eleonard@shipserv.com>
	 */
	public function returnAsHtml()
	{
		$this->returnAsHtml = true;
	}

	/**
	* Sending notification to buyer
	*/
	public function sendErroneousTransactionNotificationToBuyer($orderId, $data, $sendToGsd = false)
	{
		return $this->sendErroneousTransactionNotificationTo($orderId, $data, Myshipserv_NotificationManager_Email_ErroneousTransaction::RECIPIENT_BUYER, $sendToGsd, false);
	}

	public function sendErroneousTransactionNotificationToSupplier($orderId, $data, $sendToGsd = false, $second = false)
	{
		return $this->sendErroneousTransactionNotificationTo($orderId, $data, Myshipserv_NotificationManager_Email_ErroneousTransaction::RECIPIENT_SUPPLIER, $sendToGsd, $second);
	}

	public function sendErroneousTransactionNotificationTo($orderId, $data, $recepientType, $sendToGsd, $second)
	{
		if (!Myshipserv_Config::isErroneousTransactionNotificationEnabled()) {
			// do not send anything if disabled in config
			return false;
		}

		try {
			$email = new Myshipserv_NotificationManager_Email_ErroneousTransaction($orderId, $data, $recepientType, $sendToGsd, $second);
			$this->from = array(
					// @todo: shouldn't this be in INI config?
					'name'  => 'ShipServ',
					'email' => 'info@shipserv.com'
			);

			$content = $this->sendMail($email);

		} catch (Myshipserv_NotificationManager_Email_Exception_SilentImportError $e) {
			// no user-visible errors were generated
			//echo var_export($e, true);
			return false;

		} catch (Myshipserv_NotificationManager_Exception $e) {
			// all other erros
			//echo var_export($e, true);
			return false;
		}

		return true;

	}



    /**
     * Notifies a buyer that there was a problem with importing the given match quote
     *
     * @author  Yuriy Akopov
     * @date    2014-08-07
     * @story   S10774
     *
     * @param   int     $rfqId
     * @param   int     $supplierId
     * @param   string  $rawIxpMessage
     *
     * @return  bool
     */
    public function sendMatchQuoteImportFailedToBuyer($rfqId, $supplierId, $rawIxpMessage)
    {
        if (!Myshipserv_Config::isMatchQuoteImportNotificationEnabled()) {
            // do not send anything if disabled in config
            return false;
        }

        try {
            $email = new Myshipserv_NotificationManager_Email_MatchQuoteImportFailed($rfqId, $supplierId, $rawIxpMessage);
            $this->from = array(
                // @todo: shouldn't this be in INI config?
                'name'  => 'ShipServ Match',
                'email' => 'info@shipserv.com'
            );

            $content = $this->sendMail($email);

        } catch (Myshipserv_NotificationManager_Email_Exception_SilentImportError $e) {
            // no user-visible errors were generated
            return false;

        } catch (Myshipserv_NotificationManager_Exception $e) {
            // all other erros
            return false;
        }

        return true;
    }

	public function sendJangoTestEmail()
	{
		$email = new Myshipserv_NotificationManager_Email_Jango();
		$this->from = array(
			'name'  => "ShipServ Pages",
			'email' => "info@shipserv.com"
		);
		$emailContent = $this->sendMail($email);

		return $emailContent;
	}

	public function sendMatchQuoteToBuyer( $quoteId )
	{
        try {
            $email = new Myshipserv_NotificationManager_Email_QuoteToBuyer($quoteId);

            $this->from = array(
                'name'  => "ShipServ " . $email->getQuoteTypeLabel(),
                'email' => "info@shipserv.com"
            );

            $emailContent = $this->sendMail($email);
            Shipserv_Match_BuyerAlert::saveAlert($quoteId, false);

        } catch (Myshipserv_NotificationManager_Email_Exception_TooExpensive $e) {
            // notification email has not been sent because this auto match quote was not cheap enough
            // notify supplier
	        $fromCredentials = Myshipserv_Config::getDefaultFromAddressName();
	        
            $this->from = array(
	            'email' => $fromCredentials[0],
                'name'  => $fromCredentials[1]
            );

	        // S16396: email to supplier silenced on Stuart's request by Yuriy Akopov on 2016-04-22
            // $email = new Myshipserv_NotificationManager_Email_AutoMatchQuoteNotSent($quoteId);
            // $emailContent = $this->sendMail($email);
	        
	        $emailContent = "Silenced automatic decline message to supplier";
	        
            Shipserv_Match_BuyerAlert::saveAlert($quoteId, true);

        } catch (Myshipserv_NotificationManager_Email_Exception $e) {
            // support for the email cancellation added by Yuriy Akopov on 2014-04-07
            // email hasn't been sent for one or another reason
            // pass the exception to controller to deal with the output
            throw $e;
        }

        return $emailContent;
	}

	public function sendPagesQuoteToBuyer( $quoteId )
	{
		$sql = "SELECT count(*) FROM pages_rfq_buyer_alerted where PBA_QUOTE_ID = :quoteid";
		$params = array('quoteid' => $quoteId);
		$result = $this->db->fetchAll($sql, $params);
		if($result[0][0] == 0)
		{
			$this->from = array("name" => "ShipServ","email" => (($_SERVER['APPLICATION_ENV'] == "production")?"order":"testorder") . "@shipserv.com");
            $email = new Myshipserv_NotificationManager_Email_QuoteToBuyerForPagesRFQ($quoteId);
            $emailContent = $this->sendMail($email);
            $sql = "Insert into PAGES_RFQ_BUYER_ALERTED (pba_quote_id) values (:quoteid)";
            $params = array('quoteid' => $quoteId);
            $this->db->query($sql, $params);
            return $emailContent;
		}
	}


	public function sendMatchSupplierIntroduction( $supplierID, $rfqid )
	{
		$email = new Myshipserv_NotificationManager_Email_MatchSupplierIntroduction($supplierID, $rfqid);
        try{
        	$this->from = array("name" => "ShipServ Match","email" => "info@shipserv.com");
            // disabled as per Stuart's request by Elvir
            //$results = $this->sendMail ($email);
            return $results;
        }catch (Myshipserv_NotificationManager_Exception $ex){
        	$message = $ex->getMessage();
        }
	}

	public function sendEmailNotificationToPagesTNRFQRecipient($email, $subject, $supplier, $supplierType, $db, $htmlMode = true, $data = null)
	{
		$this->from = array("name" => "ShipServ","email" => "info@shipserv.com");
		$email = new Myshipserv_NotificationManager_Email_EmailCampaign_PagesTNRFQMigrationNotification($email, $supplier, $supplierType, $db, $htmlMode, $data);
		$this->sendMail($email);
	}

	/**
	 * Sends e-mail to user if buyer excluded
	 *
	 * @return void
	 */
	public function targetBuyerExcluded ( $id )
	{
		$targetInfo = Shipserv_Oracle_Targetcustomers_Targetinfo::getInstance();
		$data = $targetInfo->getTargetingDetails($id);
		if (count($data) > 0) {
			if ($targetInfo->hasEmail($data[0]['BSR_SPB_BRANCH_CODE']) > 0) {
				$email = new Myshipserv_NotificationManager_Email_Targetcustomers_Excluded($this->db, $data[0]);
				$this->sendMail($email);
			}
		}
	}

	/**
	 * Sends e-mail to user if buyer targeted
	 *
	 * @return void
	 */
	public function targetBuyerTargeted ( $id )
	{
		$targetInfo = Shipserv_Oracle_Targetcustomers_Targetinfo::getInstance();
		$data = $targetInfo->getTargetingDetails($id);
		if (count($data) > 0) {
			if ($targetInfo->hasEmail($data[0]['BSR_SPB_BRANCH_CODE']) > 0) {
				$email = new Myshipserv_NotificationManager_Email_Targetcustomers_Targeted($this->db, $data[0]);
				$this->sendMail($email);
			}
		}
	}

	/**
	 * Sends e-mail to user if buyer locked
	 *
	 * @return void
	 */
	public function targetBuyerLocked ( $id )
	{
		$targetInfo = Shipserv_Oracle_Targetcustomers_Targetinfo::getInstance();
		$data = $targetInfo->getTargetingDetails($id);
		if (count($data) > 0) {
			if ($targetInfo->hasEmail($data[0]['BSR_SPB_BRANCH_CODE']) > 0) {
				$email = new Myshipserv_NotificationManager_Email_Targetcustomers_Locked($this->db,  $data[0]);
				$this->sendMail($email);
			}
		}
	}

	/**
	 * Sends e-mail to user if buyer Unlocked
	 *
	 * @return void
	 */
	public function targetBuyerUnlocked ( $id )
	{
		$targetInfo = Shipserv_Oracle_Targetcustomers_Targetinfo::getInstance();
		$data = $targetInfo->getTargetingDetails($id);
		if (count($data) > 0) {
			if ($targetInfo->hasEmail($data[0]['BSR_SPB_BRANCH_CODE']) > 0) {
				$email = new Myshipserv_NotificationManager_Email_Targetcustomers_Unlocked($this->db, $data[0]);
				$this->sendMail($email);
			}
		}
	}
	
	/**
	 * Sending out email for Supplier Reccomnendation alert
	 * @param integer $buyerBranchCode
	 */
	public function supplierRecommendations($buyerBranchCode)
	{
		$email = new Myshipserv_NotificationManager_Email_SupplierRecommendations_Alert($this->db, $buyerBranchCode);
		
		if ($email->getReport()) {
			$this->sendMail($email);
		}
	}
	

}
