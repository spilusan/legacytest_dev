<?php

class Myshipserv_NotificationManager_Email_JoinCompanyConfirmEmail extends Myshipserv_NotificationManager_Email_ConfirmEmail
{

    /**
    * Constructor
    * @param resouurce $db               database Handler
    * @param integer   $recipientUserId  id of the recepient user
    * @param string    $addToCompanyName the name of the company
    */
    public function __construct($db, $recipientUserId, $addToCompanyName)
    {
        $this->addToCompanyName = $addToCompanyName;
        parent::__construct($db, $recipientUserId);
        
    }
    
    /**
    * Returns the body text of the email
    * @return array
    */
	public function getBody()
	{
        $recipientUser = $this->getUser($this->recipientUserId);       
        $data = array();
        $data['links'] = array();
        $data['links']['confirm'] = $this->makeConfirmLink();
        $data['companyname'] = $this->addToCompanyName;
        $data['username'] = $recipientUser->username;
        
        $view = $this->getView();
        $view->data = $data;
        $res = $view->render('email/join-company-confirm-email.phtml');
        
		return array($recipientUser->email => $res);
	}

}
