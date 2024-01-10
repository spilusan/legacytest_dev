<?php

class Myshipserv_NotificationManager_Email_ContactUs extends Myshipserv_NotificationManager_Email_Abstract
{
	
    /**
     * Constructor 
     * 
     * @param ShipServ_DB $db
     * @param String $recipient
     * @param String $subject
     * @param String $content
     */
	public function __construct($db, $recipient, $subject, $content)
	{
		parent::__construct($db);
		$this->recipient = $recipient;
		$this->subject = $subject;
		$this->content = $content;
	}
	
	
	/**
	 * Extend the required abstract function to get the email recipient
	 * 
	 * @return array(array('email' => $this->recipient, 'name' => $this->recipient))
	 */
	public function getRecipients()
	{
		return array(array('email' => $this->recipient, 'name' => $this->recipient));
	}
	

	/**
	 * Extend the required abstract function to get the email subject
	 *
	 * @return String
	 */	
	public function getSubject()
	{
		return $this->subject;
	}
	

	/**
	 * Extend the required abstract function to get the email content
	 *
	 * @return Array
	 */	
	public function getBody()
	{
		return array($this->recipient => $this->content);
	}
	
}
