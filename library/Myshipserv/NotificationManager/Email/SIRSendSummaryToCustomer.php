<?php

class Myshipserv_NotificationManager_Email_SIRSendSummaryToCustomer extends Myshipserv_NotificationManager_Email_Abstract
{
	protected $subject = "";
	protected $isSalesforce = false;
	
	public function __construct ($email, $subject, $supplier, $report, $period, $db, $textMode, $message, $salutation)
	{	
		
		$statistic = new stdClass;
		$statistic->targetedSearch = $report->data['supplier']['search-summary']['brand-and-category-searches']['count'];
		$statistic->profileView = $report->data['supplier']['impression-summary']['impression']['count'];
		$statistic->contactView = $report->data['supplier']['impression-summary']['contact-view']['count'];
		$statistic->rfq = $report->data['supplier']['enquiry-summary']['enquiry-sent']['count'] + $report->data['supplier']['tradenet-summary']['RFQ']['count'];
		$this->period = $period;
		
		if ($_SERVER['APPLICATION_ENV'] == 'production')
		{
			$this->enableSMTPRelay = true;
		}
		
		$this->email = $email;
		
		$this->userId = $userId;
		
		$this->supplier = $supplier;
		$this->supplierName = $supplier->name;
		
		$this->statistic = $statistic;
		$this->mode = $textMode;
		$this->hash = md5("SIR_INVITE_" . $email );
		$this->db = $db;
		
		$this->subject = $subject;
		$this->message = $message;
		$this->salutation = $salutation;
	}
	
	public function getRecipients ()
	{
		$row = array();
		$row['email'] = $this->email;
		return array($row);
	}

	public function getSubject ()
	{
		$subject = ($this->subject == "") ? "Your Summary Supplier Insight Report" : $this->subject;
		
		// group name on JANGOSMTP
		if ($this->enableSMTPRelay)
		{
			$subject .= "{SIR Forwarded Report}";
		}
		
		return $subject;
	}

	public function getBodyPlainText()
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
		$view->supplierName = $this->supplierName;
		
		$view->message = $this->message;
		$view->salutation = $this->salutation;
		
		$recipients = $this->getRecipients();
	
		return array($recipients[0]["email"] => $view->render('email/sir-summary-to-customer-text-only.phtml'));	
		
	}
	
	public function getBody ()
	{
		$view = $this->getView();

		$url = 'https://' . $_SERVER['HTTP_HOST'] . '/reports?tnid=' . $this->supplier->tnid;
		
		$view->url = $url;
		$view->hostname = $_SERVER['HTTP_HOST'];
		$view->statistic = $this->statistic;
		$view->period = $this->period;
		$view->message = $this->message;
		$view->salutation = $this->salutation;
		
		
		$view->supplierName = $this->supplierName;
		$recipients = $this->getRecipients();
	
		return array($recipients[0]["email"] => $view->render('email/sir-summary-to-customer.phtml'));	
	}
}


?>