<?php

class Myshipserv_NotificationManager_Email_EmailCampaign_SIRInvitationPremiumListing extends Myshipserv_NotificationManager_Email_Abstract
{
	protected $subject = "";
	protected $isSalesforce = false;
	
	public function __construct ($email, $subject, $supplier, $report, $periodAsString, $userId, $db, $textMode, $data)
	{	
		$statistic->targetedSearch = $report->data['supplier']['search-summary']['brand-and-category-searches']['count'];
		$statistic->profileView = $report->data['supplier']['impression-summary']['impression']['count'];
		$statistic->contactView = $report->data['supplier']['impression-summary']['contact-view']['count'];
		$statistic->pagesEnquiry = $report->data['supplier']['enquiry-summary']['enquiry-sent']['count'];
		$statistic->endDate = $periodAsString;
		
		if ($_SERVER['APPLICATION_ENV'] == 'production')
		{
			$this->enableSMTPRelay = true;
		}
		
		$this->email = $email;
		$this->userId = $userId;
		
		$this->supplier = $supplier;
		$this->supplierName = $supplier->name;
		
		$adapter = new Myshipserv_UserCompany_Domain($db);
		$collections = $adapter->fetchUsersForCompany( Myshipserv_UserCompany_Domain::COMP_TYPE_SPB, $supplier->tnid);
		
		$this->companyAdmins = $collections->getAdminUsers();
		$this->companyUsers = $collections->getUsers();
		
		$this->statistic = $statistic;
		$this->mode = $textMode;
		$this->hash = md5("SIR_INVITE_" . $email );
		$this->db = $db;
		$this->data = $data;
		
		// for pages
		if( $data['PMR_DATA_SOURCE'] == 'PAGES' )
		{
			$this->emailType = 'pages';
			$this->url = $this->getSIRUrlForPagesUser();
		}
		else if( $data['PMR_DATA_SOURCE'] == 'ENQUIRY' )
		{
			$this->emailType = 'enquiry';
			$this->url = $this->getAutoRegistrationPageUrl();
		}
		else
		{
			// for trusted user/emails
			if( $this->data['PMR_IS_TRUSTED'] == 1 )
			{
				$this->emailType = 'salesforce-trusted';
				$this->url = $this->getAutoRegistrationPageUrl();
			}
			else
			{
				$this->emailType = 'salesforce-not-trusted';
			}
		}
		
	}
	
	public function getRecipients ()
	{
		$row = array();
		$row['email'] = $this->email;
		return array($row);
	}

	public function getSubject ()
	{
		
		if( $this->data['PMR_DATA_SOURCE'] == 'PAGES' || $this->data['PMR_DATA_SOURCE'] == 'ENQUIRY' || ( $this->data['PMR_DATA_SOURCE'] == 'SALESFORCE' && $this->data['PMR_IS_TRUSTED'] == 1 ) )
		{
			if( $this->statistic->pagesEnquiry > 0 )
			{
				$subject = "You received " . $this->statistic->pagesEnquiry . " Enquir" . ( ( $this->statistic->pagesEnquiry > 1 ) ? "ies":"y" ) .  " from your ShipServ Premium Profile";
			}
			else
			{
				$subject = "You received " . $this->statistic->profileView . " Profile View" . ( ( $this->statistic->profileView > 1 ) ? "s":"" )  . " from your ShipServ Premium Profile";
			}
		}
		else if( $this->data['PMR_DATA_SOURCE'] == 'SALESFORCE' && $this->data['PMR_IS_TRUSTED'] != 1 )
		{
			$subject = "You received Enquiries from your ShipServ Premium Profile - find out more below";
		}
				
		// group name on JANGOSMTP
		if ($this->enableSMTPRelay)
		{
			if( $this->emailType == "pages" )
				$subject .= "{SIR Premium 1 - Pages}";
			else if( $this->emailType == "enquiry" )
				$subject .= "{SIR Premium 1 - Enquiry}";
			else if( $this->emailType == "salesforce-not-trusted" )
				$subject .= "{SIR Premium 1 - Salesforce - NOT Trusted}";
			else if( $this->emailType == "salesforce-trusted" )
				$subject .= "{SIR Premium 1 - Salesforce - Trusted}";
		}
		
		return $subject;
	}

	public function getBodyPlainText()
	{
		$view = $this->renderView();	
		$recipients = $this->getRecipients();

		return array($recipients[0]["email"] => $view->render('email/sir-invitation-email-premium-listing-text-only.phtml'));	
	}
	
	public function getBody ()
	{
		$view = $this->renderView();
		$recipients = $this->getRecipients();
		
		return array($recipients[0]["email"] => $view->render('email/sir-invitation-email-premium-listing.phtml'));	
	}
	
	private function getSIRUrlForPagesUser()
	{
		$url = 'https://' . $_SERVER['HTTP_HOST'] . '/reports?tnid=' . $this->supplier->tnid;

		// enable autologin for pages user
		if( $this->data['PMR_DATA_SOURCE'] == 'PAGES' && $this->userId != null )
		{
			$token = new Myshipserv_AutoLoginToken($this->db);
			$tokenId = $token->generateToken($this->userId, $url, '1 click');
			$url = $token->generateUrlToVerify();
		}
		
		return $url;
	}
	
	private function getAutoRegistrationPageUrl()
	{
		$url = 'https://' . $_SERVER['HTTP_HOST'] . '/user/auto-registration?tnid=' . $this->supplier->tnid .
		'&email=' . strtolower($this->email) .
		'&a=' . md5('AUTOREG' . $this->supplier->tnid . $this->email);

		return $url;
	}
	
	private function renderView()
	{
		$view = $this->getView();
		$view->url = $this->url;
		$view->emailType = $this->emailType;
		$view->hostname = $_SERVER['HTTP_HOST'];
		$view->statistic = $this->statistic;
		$view->isSalesforce = $this->isSalesforce;
		$view->isTrusted = $this->isTrusted;
		$view->supplier = $this->supplier;
		$view->supplierName = $this->supplierName;
		$view->companyUsers = $this->companyUsers;
		$view->companyAdmins = $this->companyAdmins;
		return $view;
	}
}


?>
