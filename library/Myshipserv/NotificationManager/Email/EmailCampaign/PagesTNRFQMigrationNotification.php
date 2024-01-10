<?php

class Myshipserv_NotificationManager_Email_EmailCampaign_PagesTNRFQMigrationNotification extends Myshipserv_NotificationManager_Email_Abstract
{	
	public function __construct ($email, $supplier, $supplierType, $db, $textMode, $data)
	{	
		if ($_SERVER['APPLICATION_ENV'] == 'production')
		{
			$this->enableSMTPRelay = true;
		}
		
		$this->email = $email;
		$this->userId = $userId;
		$this->supplier = $supplier;
		$this->supplierName = $supplier->name;
		$this->supplierType = $supplierType;
		$this->mode = $textMode;
		$this->db = $db;
		$this->data = $data;
	}
	
	public function getRecipients ()
	{
		$row = array();
		$row['email'] = $this->email;
		return array($row);
	}

	public function getSubject ()
	{		
		if( $this->supplierType == "STARTSUPPLIER" )
		{
			$subject = "IMPORTANT NOTICE: Changes to your RFQ notification emails";
		}
		else if( $this->supplierType == "SMARTSUPPLIER" )
		{
			$subject = "IMPORTANT NOTICE: Pages RFQs in your SmartSupplier Inbox";	
		}
		else if( $this->supplierType == "PAGES")
		{
			$subject = "IMPORTANT NOTICE: You will no longer receive Pages RFQs to this email address";
		}
		
		// group name on JANGOSMTP
		if ($this->enableSMTPRelay)
		{
			$subject .= "{Pages RFQ Integration with TN Notification}";
		}
		
		return $subject;
	}
	
	public function getBody ()
	{
		$view = $this->renderView();
		$recipients = $this->getRecipients();
		
		return array($recipients[0]["email"] => $view->render('email/campaign/pages-rfq-with-tn-integration.phtml'));	
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
		$view->supplierType = $this->supplierType;
		$view->user = $this->user;
		$view->supplierName = $this->supplierName;
		$view->companyUsers = $this->companyUsers;
		$view->data = $this->data;
		$view->companyAdmins = $this->companyAdmins;
		return $view;
	}
}


?>
