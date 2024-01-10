<?php
/**
 * Email sent to buyer for supplier recommendations report
 *
 * @author  Attila O
 * @date    2017-04
 * @story   S19510
 */
class Myshipserv_NotificationManager_Email_SupplierRecommendations_Alert extends Myshipserv_NotificationManager_Email_Abstract
{

	protected $buyerBranchCode;
    /**
     * @param   object  $db
     * @param   int     $buyerBranchCode
     */
    public function __construct($db, $buyerBranchCode)
    {
    	$this->db = $db;
    	$this->buyerBranchCode = $buyerBranchCode;
	}

    /**
     * @return string
     */
    public function getSubject()
    {
        return 'Top 5 Supplier Recommendations';
    }

    /**
     * @return  array
     * @throws  Myshipserv_NotificationManager_Email_Exception_SilentImportError
     */
    public function getBody()
    {
        $view = $this->getView();
        $view->data = $this->reportData;
        $view->buyerBranchCode = $this->buyerBranchCode;
        $view->hostname =  Myshipserv_Config::getApplicationProtocol() . '://' . Myshipserv_Config::getApplicationHostName();
        $body = $view->render('email/supplier-recomendations.phtml');
        $recipients = $this->getRecipients();
        $emails = array(
            $recipients->getHash() => $body
        );

        return $emails;
    }

    /**
     * @return Myshipserv_NotificationManager_Recipient
     */
    public function getRecipients()
    {
    	if (Myshipserv_Config::isSupplierRecommendationsNotificationSentToBuyer()) {
	    	$buyer = Shipserv_Buyer::getBuyerBranchInstanceById($this->buyerBranchCode);
	    	$emailAddress = $buyer->bybRegistrantEmailAddress;
	    	
	    	// get email address of buyer in order (ordEmail); if not found, then try buyerBranchEmail
	    	if ($emailAddress !== "" && self::isValidEmail($emailAddress)) {
	    		$to = array(
	    				Myshipserv_NotificationManager_Recipient::RECIPIENT_NAME  => $buyer->bybRegistrantFirstName . ' ' . $buyer->bybRegistrantLastName,
	    				Myshipserv_NotificationManager_Recipient::RECIPIENT_EMAIL => $emailAddress
	    		);
	    	} 
	    		    	
	    	$recipientList = array(
	    			Myshipserv_NotificationManager_Recipient::RECIPIENTS_TO  => array($to),
	    			Myshipserv_NotificationManager_Recipient::RECIPIENTS_BCC => array()
	    	);
    	}
    	
    	$bcc = Myshipserv_Config::getSupplierRecommendationsBccRecipients();
    	if (!empty($bcc)) {
    		foreach ($bcc as $bccEmail) {
    			$destinationEmail = array(
    					Myshipserv_NotificationManager_Recipient::RECIPIENT_NAME  => "ShipServ Staff",
    					Myshipserv_NotificationManager_Recipient::RECIPIENT_EMAIL => $bccEmail
    			);
    			
    			if (count($recipientList[Myshipserv_NotificationManager_Recipient::RECIPIENTS_TO]) == 0) {
    				$recipientList[Myshipserv_NotificationManager_Recipient::RECIPIENTS_TO][] = $destinationEmail;
    			} else {
    				$recipientList[Myshipserv_NotificationManager_Recipient::RECIPIENTS_BCC][] = $destinationEmail;
    			}
    		}
    	}
    	
    	//echo var_export($recipientList, true);
    	
    	$recipient = new Myshipserv_NotificationManager_Recipient();
    	$recipient->list = $recipientList;
    	
    	return $recipient;
    }
    
    /**
     * Get the actual mail body from the report
     */
    public function getReport()
    {
    	$result = array();
    	$report = new Shipserv_Report_Supplier_Match();

    	$result = $report->getSupplierList($this->buyerBranchCode, 'All', null, null, null, false, 0, 1, 5, true, true, null, true);
    	$this->reportData = $result['data'];
    	return (count($this->reportData) > 0);
    }


    
}

