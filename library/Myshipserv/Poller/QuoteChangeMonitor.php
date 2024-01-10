<?php
/**
 * Monitoring testorder@shipserv.com/order@shipserv.com if buyer want to change the quote from the supplier
 * 
 * @author elvirleonard
 *
 */
class Myshipserv_Poller_QuoteChangeMonitor extends Shipserv_Object 
{
	/**
	 * Constructor - initialising logger
	 */
    public function __construct() 
    {
        $this->logger = new Myshipserv_Logger_File('buyers-reply-to-quote-pages');
    }

    /**
     * Polling the imap
     * and logging it to the log
     */
    public function poll() 
    {
    	$config = Zend_Registry::get('config');

        $mailConfig = array(
            'host' => $config->shipserv->pages->quoteToPo->emailPoller->server->url,
            'user' => $config->shipserv->pages->quoteToPo->emailPoller->username,
            'password' => $config->shipserv->pages->quoteToPo->emailPoller->password
        );

        $storage = new Zend_Mail_Storage_Imap($mailConfig);
		$this->logger->log("Contacting IMAP");
        $messageIds = $storage->getUniqueId();
        
        // getting the folders
        $folders = $storage->getFolders();
         
        // go through each mails and checking if it's seen/read or not, if not, then process
        // and mark it as read
        foreach ($storage as  $idx => $mail) 
        {
        	$mailCount++;

        	// getting meta data of the email

            // changed by Yuriy Akopov on 2015-12-04, DE6524 (parsing fails for .co.uk addresses resulting in $match[0] being an array)
            // $from = $mail->from;
        	// preg_match_all("/[A-Za-z0-9_-]+@[A-Za-z0-9_-]+\.([A-Za-z0-9_-][A-Za-z0-9_]+)/", $from, $matches);
        	// $from = $matches[0];
            $from = Shipserv_Helper_String::findEmails($mail->from);
            // changes by Yuriy Akopov end - the rest of the workflow is also less than ideal, but apparently it works

        	$subject = utf8_encode($mail->subject);
        	$messageId = $storage->getNumberByUniqueId($storage->getUniqueId ( $idx ));
        	$uniqueId = $storage->getUniqueId ( $idx );
        	
        	// checking if each email is read or not
        	if( $mail->hasFlag(Zend_Mail_Storage::FLAG_SEEN) == false )
        	{
        		$mailProcessed++;
        		$this->logger->log("------------------");
        		$this->logger->log("Processing email from: " . $from);
        		$this->logger->log("subject: " . $subject);
        		
        		$html = "";
        		$text = "";
        		
        		// parsing the parts
        		foreach (new RecursiveIteratorIterator($mail) as $part)
        		{
        			try
        			{
        				if (strtok($part->contentType, ';') == 'text/plain')
        				{
        					$data = $part->getContent();
        					$text .= trim(utf8_encode(quoted_printable_decode(($data))));
        				}
        				else if (strtok($part->contentType, ';') == 'text/html')
        				{
        					$data = $part->getContent();
        					$html .= trim(utf8_encode(quoted_printable_decode(($data))));
        				}
        			}
        			catch (Zend_Mail_Exception $e)
        			{
        				$this->logger->log("ERROR: Content cannot be extracted: " . $e->getMessage());
        				echo "Error: " . $e->getMessage();
        			}
        		}
        		        		
        		$this->logger->log("Content extracted for email from: " . $from);
        	
        		// forward email to supplier
        		if( base64_decode($html, true) !== false )
        		{	
        			$html = base64_decode($html);
        		}
        		
        		$this->sendEmailToSupplier($from, $subject, $html, $text);
        		
        		try
        		{
        			//$storage->copyMessage($messageId, 'INBOX.processed');
        			//$storage->removeMessage($uniqueId);
        			//$storage->moveMessage($uid, $folders->__get('INBOX')->__get("processed"));
        			
        			$storage->setFlags($uniqueId, array(Zend_Mail_Storage::FLAG_SEEN));
        			 
        			$this->logger->log("Marking message: " . $uniqueId . "-" . $messageId . " as read - (uniqueId-messageId)");
        		}
        		catch(Exception $e)
        		{
        			//$this->logger->log("Error: system cannot move message: " . $uniqueId . "-" . $messageId . " to processed folder: " . $e->getMessage());
        		}
        	}
        	else
        	{
        		if( $_SERVER['APPLICATION_ENV'] == 'development' || $_SERVER['APPLICATION_ENV'] == 'testing')
        		{
        			$this->logger->log("Skipping message: " . $uniqueId . "-" . $messageId . " because it has been processed");
        		}
        	}
        }
        
        if( $mailCount == 0 || $mailProcessed == 0 )
        {
        	$this->logger->log("No new mail found");
        }
        
        $this->logger->log("---------------------------------------");
    }
    
    public function sendEmailToSupplier($buyerEmail, $subject, $html, $text)
    {
    	// pulling the meta data
    	$data = $this->getEmailFromRfqInternalRefNo($html, $buyerEmail, $subject);
    	
    	// if meta data is specified
    	if( $data !== false )
    	{
	    	$supplier = Shipserv_Supplier::getInstanceById($data['supplierId'], '', true);
	    	
	    	$newmail = new Zend_Mail();
	    	$config = Zend_Registry::get('config');
	    	
	    	if( $_SERVER['APPLICATION_ENV'] == "production" )
	    	{
	    		$newmail->setFrom('order@shipserv.com');
	    		$destination = $supplier->getEnquiryEmail();
	    	}
	    	else
	    	{
	    		$newmail->setFrom('testorder@shipserv.com');
	    		$destination = $config->notifications->test->email;
	    		$html .= "<br /><br />Intended recipient: " . $supplier->getEnquiryEmail() . "<br /><br />";
	    	}

	    	if( $data['quoteId'] != "" )
	    	{
	    		$quote = Shipserv_Quote::getInstanceById( $data['quoteId'] );
	    		$qotRef = "(QOT: " . $quote->qotRefNo . ")";
	    	}
	    	
	    	$template = "
				<br />
				*** PLEASE DO NOT REPLY TO THIS MESSAGE : ANY REPLY WILL BE IGNORED ***<br />
				<br />
				<br />
				
				To: " . $supplier->name . "<br />
				<br />
				You have received a message in response to your quote " . $qotRef . " from a registered user (" . $data['fullName'] . ") on ShipServ Pages, the leading
				marine and offshore e-marketplace.<br />
				<br />
	    	";
	    	
	    	if( $supplier->hasSmartSupplier() || $supplier->hasExpertSupplier() )
	    	{
	    		$template .= "Please go to your SmartSupplier to re-quote if necessary.<br />"	;
	    	}
	    	else
	    	{
	    		 
	    		$linkToRfq = Myshipserv_Config::getApplicationProtocol() . '://' . Myshipserv_Config::getApplicationHostName() . "/viewrfq?login=" . $supplier->tnid . "&rfqrefno=" . $data['rfqId']; 
	    	
		    	$template .= "
		    		<br />
		    		<br />
		    		Please click on the link below to review this RFQ. You can re-quote by using 'Create Quote' button at the bottom of the RFQ screen.<br />
		    		<br />
		    		View your RFQ now:
		    		<a href=\"" . $linkToRfq . "\">" . $linkToRfq . "</a>
		    	";
	    	}	    	
	    	
	    	$posMarker['start'] = strpos($html, '<div id="marker"></div>');
	    	$posMarker['end'] 	= strpos($html, '<div id="eofMarker"></div>');
	    	$strToBeRemoved = substr($html, $posMarker['start'], ($posMarker['end'] - $posMarker['start']));
	    	
	    	$html = str_replace($strToBeRemoved, "", $html);
	    	
	    	$template .= "<br /><br />Buyer comments:<br /><hr />";
	    	$template .= "<hr />";
	    	$template .= $html;
	    	$template .= "<hr />";
	    	$template .= "<hr />";
	    	
	    	$template .= "<br /><br /><br />Best regards,<br /><br />ShipServ";
	    	
	    	$newmail->addTo($destination);
	    	$newmail->setSubject($subject);
	    	$newmail->setBodyHtml($template);
	    	$newmail->send();
	    	
	    	unset($newmail);
	    	$this->logger->log("Email has been forwarded to " . $destination . " (intended for: " . $supplier->getEnquiryEmail() . ")" );
    	}
    }
    
    /**
     * Extracting meta data info from email
     * 
     * @param unknown_type $html
     * @param unknown_type $buyerEmail
     * @param unknown_type $subject
     * @return boolean|unknown
     */
    public function getEmailFromRfqInternalRefNo($html, $buyerEmail, $subject)
    {    	
    	$this->logger->log("Getting RFQ, Quote, Supplier IDs and Email address");
    	
		$totalMatched = preg_match_all('/rfqId:([0-9]+)/i', $html, $matches);
		$data['rfqId'] = $matches[1][0];
		
		$totalMatched = preg_match_all('/quoteId:([0-9]+)/i', $html, $matches);
		$data['quoteId'] = $matches[1][0];
		
		$totalMatched = preg_match_all('/supplierId:([0-9]+)/i', $html, $matches);
		$data['supplierId'] = $matches[1][0];
		
		$totalMatched = preg_match_all('/emailAddress:([A-Z0-9._%+-]+\[at\][A-Z0-9.-]+\.[A-Z]{2,4})/i', $html, $matches);
		$data['email'] = $matches[1][0];
		$data['email'] = str_replace("[at]", "@", $data['email']);
		
		$totalMatched = preg_match_all('/fullName\:([A-Za-z0-9 ]+):fullName/i', $html, $matches);
		$data['fullName'] = $matches[1][0];
		
		if( $data['rfqId'] == "" || $data['quoteId'] == "" || $data['supplierId'] == "" || $data['email'] == "" )
		{
			$this->sendMessageToBuyerToReplyToTheQuote($buyerEmail, $subject);
			return false;
		}
		
		return $data;
    }
    
    /**
     * Sending message to buyer, telling that they need to include the detail of the Quote
     * @param unknown_type $buyerEmail
     * @param unknown_type $subject
     */
    public function sendMessageToBuyerToReplyToTheQuote($buyerEmail, $subject)
    {
    	$this->logger->log("Warning: buyer did not include quote on the email. Pages is sending email reminder to them on: " . $buyerEmail);
    	 
    	$text = "
    	
Dear Customer,

Thank you for your reply. 

We are trying to forward your message to the supplier but we cannot see the quote that should have been included on your reply.

Please make sure that you reply the quote email without losing any content or quote data on it.

Should you have any questions or require immediate service, please contact ShipServ through https://www.shipserv.com/info/support/ or send an email to support@shipserv.com.


Best Regards,

ShipServ
support@shipserv.com
    	";
    	$config = Zend_Registry::get('config');
    	$newmail = new Zend_Mail();
    	
    	if( $_SERVER['APPLICATION_ENV'] == "production" )
    	{
    		$newmail->setFrom('order@shipserv.com');
    		$destination = $buyerEmail;
    	}
    	else
    	{
    		$newmail->setFrom('testorder@shipserv.com');
    		$destination = $config->notifications->test->email;
    		$html .= "\n\nIntended recipient: " . $buyerEmail . "\n\n";    			
    	}
    	
    	$newmail->addTo($destination);
    	$newmail->setSubject($subject);
    	$newmail->setBodyText($text);
    	
    	if( $destination != "" )
    	{
    		$newmail->send();
    	}
    	
    	unset($newmail);
    }
}
