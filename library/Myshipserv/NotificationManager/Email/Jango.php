<?php

class Myshipserv_NotificationManager_Email_Jango extends Myshipserv_NotificationManager_Email_Abstract
{

    protected $subject = "";

    public function __construct() {
        $this->db = $this->getDb();

        // enable SMTP relay through jangoSMTP (or any other)
        $this->enableSMTPRelay = true;
    }

    public function getRecipients()
    {
        $row = array();
		$recipient = new Myshipserv_NotificationManager_Recipient;
		$recipientStorage = array();
		$recipientStorage['TO'][] = array("name" => "Elvir", "email" => "elvir.leonard@icloud.com");
        $recipientStorage['TO'][] = array("name" => "Elvir", "email" => "elvirleonard@hotmail.co.uk");
        $recipientStorage['BCC'][] = array("name" => "Elvir", "email" => "elvir.leonard@gmail.com");
        $recipient->list = $recipientStorage;

        return $recipient;
    }

    public function getSubject() {
        $subject = "JangoSMTP Test email";


        if ($this->enableSMTPRelay)
        {
            // group name on JANGOSMTP
            $subject .= "{test group}";
        }

        return $subject;
    }

    public function getBody() {
        $view = $this->getView();
        $recipients = $this->getRecipients();
        $body = "$view->render('email/jango-test.phtml')";
        $emails[$recipients->getHash()] = $body;
        return $emails;
    }
}
