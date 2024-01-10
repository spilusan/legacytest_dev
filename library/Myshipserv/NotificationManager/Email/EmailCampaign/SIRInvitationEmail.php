<?php

class Myshipserv_NotificationManager_Email_EmailCampaign_SIRInvitationEmail extends Myshipserv_NotificationManager_Email_Abstract
{
	protected $subject = "";
	protected $isSalesforce = false;
	
	public function __construct ($email, $subject, $supplier, $report, $periodAsString, $userId, $db)
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
		
		$this->statistic = $statistic;
		$this->hash = md5("SIR_INVITE_" . $email );
		$this->db = $db;
	}
	
	public function setAsSalesforceEmail( $value = true )
	{
		$this->isSalesforce = $value;
	}

	public function getRecipients ()
	{
		$row = array();
		$row['email'] = $this->email;
		return array($row);
	}

	public function getSubject ()
	{
		$subject = "Access your ShipServ Enquiries below and take a 3 month Premium Profile";
		
		// group name on JANGOSMTP
		if ($this->enableSMTPRelay)
		{
			$subject .= "{SIR Invitation Email v2}";
		}
		
		return $subject;
	}

	public function getBody ()
	{
		$view = $this->getView();

		$url = 'https://' . $_SERVER['HTTP_HOST'] . '/reports?tnid=' . $this->supplier->tnid;

		// enable autologin for pages user
		if( $this->isSalesforce == false && $this->userId != null )
		{
			$token = new Myshipserv_AutoLoginToken($this->db);
			$tokenId = $token->generateToken($this->userId, $url, '1 click');
			$url = $token->generateUrlToVerify();
		}
		
		$view->url = $url;
		$view->hostname = $_SERVER['HTTP_HOST'];
		$view->statistic = $this->statistic;
		$view->isSalesforce = $this->isSalesforce;
		
		// logic to switch text
		if( $this->isSalesforce )
		{
			if( $this->statistic->pagesEnquiry > 0 )
			{
				$view->mode = "salesforce_user_accepting_enquiries";
			}
			else 
			{
				$view->mode = "salesforce_user_zero_enquiries";
			}
		}
		else
		{
			if( $this->statistic->pagesEnquiry > 0 )
			{
				$view->mode = "pages_user_accepting_enquiries";
			}
			else 
			{
				$view->mode = "pages_user_zero_enquiries";
			}
		}
		
		$view->supplierName = $this->supplierName;
		$recipients = $this->getRecipients ();
	
		return array($recipients[0]["email"] => $view->render('email/sir-invitation-email.phtml'));
	}
}


?>
