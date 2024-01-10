<?php

class Myshipserv_NotificationManager_Email_CustomerSatisfactionSurvey extends Myshipserv_NotificationManager_Email_Abstract
{

	public function __construct ($email)
	{	
		
		$this->enableSMTPRelay = true; // enable SMTP relay through jangoSMTP (or any other)
		$this->email = $email;
		$this->hash = md5("CSS" . $email );
	}

	public function getRecipients ()
	{
		$row = array();
		$row['email'] = $this->email;
		
		return array($row);
	}

	public function getSubject ()
	{
		$subject = 'Your view on ShipServ';
		
		if ($this->enableSMTPRelay)
		{
			// group name on JANGOSMTP
			$subject .= "{Customer Satisfaction Survey}";
		}
		
		return $subject;
	}

	public function getBody ()
	{

		// Fetch e-mail template
		$view = $this->getView();

		$url = 'https://' . $_SERVER['HTTP_HOST'] . '/survey?';
		$url .= '&surveyId=1';
		$url .= '&email=' . $this->email;
		$url .= '&c=' . $this->hash;
		
		$view->url = $url;
		$view->hostname = $_SERVER['HTTP_HOST'];
		
		$recipients = $this->getRecipients ();
	
		return array($recipients[0]["email"] => $view->render('email/customer-satisfaction-survey.phtml'));
	}

}


?>
