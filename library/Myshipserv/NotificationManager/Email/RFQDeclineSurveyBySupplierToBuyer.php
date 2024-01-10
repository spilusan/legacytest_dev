<?php

class Myshipserv_NotificationManager_Email_RFQDeclineSurveyBySupplierToBuyer extends Myshipserv_NotificationManager_Email_Abstract
{

	public function __construct ($inquiry, $response, $supplierId)
	{	
		$this->inquiry = $inquiry;
		$this->email = $inquiry->senderEmail;
		$this->response = $response;
		$this->supplier = Shipserv_Supplier::fetch($supplierId);
		
	}

	public function getRecipients ()
	{
		$row = array();
		$row['email'] = $this->email;
		
		return array($row);
	}

	public function getSubject ()
	{
		$subject = 'Supplier feedback about your recent RFQ on ShipServ';
		
		return $subject;
	}

	public function getBody ()
	{

		// Fetch e-mail template
		$view = $this->getView();
		$view->response = $this->response;
		$view->hostname = $_SERVER['HTTP_HOST'];
		$view->supplier = $this->supplier;
		$view->inquiry = $this->inquiry;
		
		$recipients = $this->getRecipients ();
	
		return array($recipients[0]["email"] => $view->render('email/RFQ-decline-survey-by-supplier-to-buyer.phtml'));
	}

}


?>
