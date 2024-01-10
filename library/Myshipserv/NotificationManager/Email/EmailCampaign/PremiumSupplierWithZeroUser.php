<?php

class Myshipserv_NotificationManager_Email_EmailCampaign_PremiumSupplierWithZeroUser extends Myshipserv_NotificationManager_Email_Abstract
{
	protected $subject = "";
	protected $isSalesforce = false;
	protected $user = false;
	
	public function __construct ($email, $subject, $supplier, $report, $periodAsString, $userId, $db, $textMode, $data)
	{	
		if ($_SERVER['APPLICATION_ENV'] == 'production')
		{
			$this->enableSMTPRelay = true;
		}
		
		$this->email = $email;
		$this->userId = $userId;
		
		$this->supplier = $supplier;
		$this->supplierName = $supplier->name;
		
		$this->statistic = $report;
		$this->statistic['pcs'] = $supplier->getProfileCompletionScore();
		
		$this->mode = $textMode;
		$this->hash = md5("SIR_INVITE_" . $email );
		$this->db = $db;
		$this->data = $data;
		
		if( $data['PMR_DATA_SOURCE'] == 'ENQUIRY' )
		{
			$this->emailType = 'enquiry';
			
			if( $this->userId !== null )
			{
				try{
					$this->user = Shipserv_User::getInstanceById( $this->userId );
				}catch( Exception $e){}
			}
			
			$this->url = ( $this->user !== false ) ? $this->getSIRUrlForPagesUser() : $this->getAutoRegistrationPageUrl( $this->getSIRUrlForPagesUser() );
		}
	}
	
	public function getRecipients ()
	{
		$row = array();
		$row['email'] = $this->email;
		return array($row);
	}

	public function getOpenRate()
	{
		$statistic = $this->statistic;
		
		if( $statistic['read'] == 0 || $statistic['sent'] == 0 )
		{
			$openRate = 0;
		}
		else
		{
			$openRate = round($statistic['read'] / $statistic['sent'] * 100);
		}
		return $openRate;
	}
	
	public function getSubject ()
	{
		
		if( $this->statistic['sent'] > 5 )
		{
			$subject = "You've received " . $this->statistic['sent'] . " RFQs through ShipServ Pages";
		}
		else 
		{
			$subject = "Your profile completion score with ShipServ is " . $this->statistic['pcs'] . '%';
		}
				
		// group name on JANGOSMTP
		if ($this->enableSMTPRelay)
		{
			$subject .= "{Zero user - Premium lister}";
		}
		
		return $subject;
	}
	
	public function getBody ()
	{
		$view = $this->renderView();
		$recipients = $this->getRecipients();
		
		return array($recipients[0]["email"] => $view->render('email/campaign/premium-with-zero-user.phtml'));	
	}
	
	private function getSIRUrlForPagesUser()
	{
		$url = 'https://' . $_SERVER['HTTP_HOST'] . '/reports?tnid=' . $this->supplier->tnid;
		return $url;
	}
	
	private function getAutoRegistrationPageUrl($redirect)
	{
		$uri = new Myshipserv_View_Helper_Uri();
		$url = 'https://' . $_SERVER['HTTP_HOST'] . '/user/auto-registration?tnid=' . urlencode($this->data['NEW_USER_FROM_ENQUIRY_EMAIL_TO_JOIN_TNID']) .
		'&email=' . strtolower($this->email) .
		// lower case the email -- otherwise the md5 wont match
		'&a=' . md5('AUTOREG' . $this->data['NEW_USER_FROM_ENQUIRY_EMAIL_TO_JOIN_TNID'] . strtolower($this->email)) .
		'&r=' . $uri->obfuscate($redirect);
		
		$token = new Myshipserv_AutoLoginToken($this->db);
		$tokenId = $token->generateToken($this->userId, $url, '1 click');
		$url = $token->generateUrlToVerify();
		
		return $url;
	}
	
	private function renderView()
	{
		$view = $this->getView();
		$view->url = $this->url;
		$view->email = $this->email;
		$view->emailType = $this->emailType;
		$view->hostname = $_SERVER['HTTP_HOST'];
		$view->statistic = $this->statistic;
		$view->isSalesforce = $this->isSalesforce;
		$view->isTrusted = $this->isTrusted;
		$view->supplier = $this->supplier;
		$view->user = $this->user;
		$view->supplierName = $this->supplierName;
		$view->companyUsers = $this->companyUsers;
		$view->companyAdmins = $this->companyAdmins;
		return $view;
	}
}


?>
