<?php
/**
 * Email sent to buyer when they put wrong currency
 *
 * @author  Elvir
 * @date    2015-01-22
 * @story   S12486
 */
class Myshipserv_NotificationManager_Email_ErroneousTransaction extends Myshipserv_NotificationManager_Email_Abstract
{

    CONST
          RECIPIENT_BUYER = 0
        , RECIPIENT_SUPPLIER = 1
        ;
	/**
     * @var null|Shipserv_Order
     */
    protected $ord = null;

    /**
     * @var null|Shipserv_Supplier
     */
    protected $supplier = null;

    /**
     * @var null|string
     */
    protected $message = null;

    /**
    * @var false|boolean
    */
    protected $isSecond = false;

    /**
     * @param   int     $ordId
     * @param   int     $supplierId
     * @param   string  $rawIxpMessage
     */
    public function __construct($orderId, $data, $recepientType, $sendToGsd = false, $second = false)
    {
        $this->order = Shipserv_Order::getInstanceById($orderId);
        $this->quote = Shipserv_Quote::getInstanceById($this->order->ordQotInternalRefNo);

        $this->supplier = Shipserv_Supplier::getInstanceById($this->order->ordSpbBranchCode, '', true);
        $this->buyer = Shipserv_Buyer::getBuyerBranchInstanceById($this->order->ordBybBuyerBranchCode, '', true);

        $this->data = $data;
        $this->sendToGsd = $sendToGsd;
        $this->isSecond = $second;
        $this->recepientType = $recepientType;
    }

    /**
     * @return string
     */
    public function getSubject() {
        $ordStr = $this->order->ordRefNo;
        if (strlen($this->order->ordVesselName)) {
            $ordStr .= " - Vessel: " . $this->order->ordVesselName;
        }
         $subject = ($this->isSecond === true) ? "Reminder: Potential Error in PO – Purchase Order: " . $ordStr : "Potential Error in PO – Purchase Order: " . $ordStr;

        return $subject;
    }

    /**
     * @return  array
     * @throws  Myshipserv_NotificationManager_Email_Exception_SilentImportError
     */
    public function getBody()
    {
        $view = $this->getView();
        $view->order = $this->order;
        $view->quote = $this->quote;
        $view->supplier = $this->supplier;
        $view->buyer = $this->buyer;
        $view->data = $this->data;
        $view->sendToGsd = $this->sendToGsd;
        $view->isSecond = $this->isSecond;
        $view->recepientType = $this->recepientType;
        $view->hostname = Myshipserv_Config::getApplicationHostName();
        $view->startSupplierURL = $this->getStartSupplierUrl($this->order->ordSpbBranchCode, $this->order->ordInternalRefNo);
        $view->buyerMessage = $this->getBuyerMessage($this->order->ordInternalRefNo);

        $body = $view->render('email/erroneous-transaction-notification.phtml');

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
    	// if this is a notification to GSD, then bypass all the check
    	if( $this->sendToGsd === true )
    	{
    		$recipientList = array(
    	        Myshipserv_NotificationManager_Recipient::RECIPIENT_NAME  => "ShipServ Support",
	            Myshipserv_NotificationManager_Recipient::RECIPIENT_EMAIL => "support@shipserv.com"
	        );
    	}

    	else
    	{
    		if( Myshipserv_Config::isErroneousTransactionNotificationSentToBuyer() ) {
                switch ($this->recepientType) {
                    case self::RECIPIENT_BUYER:

                        // get email address of buyer in order (ordEmail); if not found, then try buyerBranchEmail
                        if( $this->order->ordEmail != "" && self::isValidEmail($this->order->ordEmail)) {
                            $to = array(
                                Myshipserv_NotificationManager_Recipient::RECIPIENT_NAME  => $this->order->ordContact,
                                Myshipserv_NotificationManager_Recipient::RECIPIENT_EMAIL => $this->order->ordEmail
                            );
                        }else{
                            $to = self::getBuyerBranchRecipients($this->order->ordBybBuyerBranchCode);
                        }

                        $recipientList = array(
                            Myshipserv_NotificationManager_Recipient::RECIPIENTS_TO  => array($to),
                            Myshipserv_NotificationManager_Recipient::RECIPIENTS_BCC => array()
                        );
                        break;

                    case self::RECIPIENT_SUPPLIER:

                        $email = $this->getRecepientByOrd($this->order->ordInternalRefNo, $this->order->ordSpbBranchCode, $this->order->ordBybBuyerBranchCode);

                        if( $email['EMAIL'] != "" && self::isValidEmail($email['EMAIL'])) {
                                $to = array(
                                    Myshipserv_NotificationManager_Recipient::RECIPIENT_NAME  => $email['NAME'],
                                    Myshipserv_NotificationManager_Recipient::RECIPIENT_EMAIL => $email['EMAIL']
                                );
                            } else {
                                //@todo implement default addr
                                $to = array();
                            }
                        $recipientList = array(
                            Myshipserv_NotificationManager_Recipient::RECIPIENTS_TO  => array($to),
                            Myshipserv_NotificationManager_Recipient::RECIPIENTS_BCC => array()
                        );
                            break;
                    
                    default:
                        // Invalid recepient, exception could be raised here?
                        break;
                }

    		}
    	}

    	$bcc = Myshipserv_Config::getErroneousTransactionBccRecipients();
    	if (!empty($bcc)) {
    		foreach ($bcc as $bccEmail) {
                $destinationEmail = array(
					Myshipserv_NotificationManager_Recipient::RECIPIENT_NAME  => "ShipServ Staff",
					Myshipserv_NotificationManager_Recipient::RECIPIENT_EMAIL => $bccEmail
    			);

                if( count($recipientList[Myshipserv_NotificationManager_Recipient::RECIPIENTS_TO]) == 0 ){
                    $recipientList[Myshipserv_NotificationManager_Recipient::RECIPIENTS_TO][] = $destinationEmail;
                }else{
                    $recipientList[Myshipserv_NotificationManager_Recipient::RECIPIENTS_BCC][] = $destinationEmail;
                }
    		}
    	}

        //echo var_export($recipientList, true);

    	$recipient = new Myshipserv_NotificationManager_Recipient();
    	$recipient->list = $recipientList;

    	return $recipient;

    }

    protected function getRecepientByOrd( $ordInternalRefNo, $spbBranchCode, $bybBranchCode )
    {
        $this->db = $this->getDb();
        $sql = "WITH base_contact AS
                (
                SELECT 
                  (
                    SELECT
                         party_contact_email email
                    FROM
                        party_contact
                    WHERE
                        party_doc_type='ORD'
                        and party_qualifier='VN'
                        and party_doc_internal_ref_no= :ordInternalRefNo
                        and party_spb_branch_code= :spbBranchCode
                        and party_byb_branch_code= :bybBranchCode
                        and rownum = 1
                  ) as party_email
                  ,(
                    SELECT
                        party_contact_name name
                    FROM
                        party_contact
                    WHERE
                        party_doc_type='ORD'
                        and party_qualifier='VN'
                        and party_doc_internal_ref_no= :ordInternalRefNo
                        and party_spb_branch_code= :spbBranchCode
                        and party_byb_branch_code= :bybBranchCode
                        and rownum = 1
                  ) as party_name
                  ,(
                    SELECT
                         spb_email email
                    FROM
                        supplier_branch
                    WHERE
                        spb_branch_code= :spbBranchCode
                        and rownum = 1
                  ) as spb_email

                  ,(
                    SELECT
                        spb_contact_name name
                    FROM
                        supplier_branch
                    WHERE
                        spb_branch_code= :spbBranchCode
                        and rownum = 1
                  ) as spb_name
                FROM
                  dual
                )

                SELECT 
                     CASE WHEN party_email is null THEN spb_email ELSE party_email END email
                    ,CASE WHEN party_email is null THEN spb_name ELSE party_name END name
                FROM
                    base_contact";

        $params = array(
            'ordInternalRefNo' => (int)$ordInternalRefNo,
            'spbBranchCode' => (int)$spbBranchCode,
            'bybBranchCode' => (int)$bybBranchCode,
        );

        $result = $this->db->fetchAll($sql, $params);
        return (count($result) > 0) ? $result[0] : array('EMAIL' => '', 'NAME' => '');
    }

    protected function getStartSupplierUrl($ordSpbBranchCode, $ordInternalRefNo)
    {
        return Myshipserv_Config::getApplicationProtocol() . '://' . Myshipserv_Config::getApplicationHostName() . "/viewpo?login=" . $ordSpbBranchCode . '&porefno=' . $ordInternalRefNo;
     
    }

    protected function getBuyerMessage($ordInternalRefNo)
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $sql = "
        SELECT
            etn_buy_notification_message
        FROM
            erroneous_txn_notification
        WHERE
            etn_ord_internal_ref_no = :etnOrdRefNo
            and etn_doc_type = 'ORD'";

        $blob = $db->fetchOne($sql, array('etnOrdRefNo' => $ordInternalRefNo));
        return ($blob) ? $blob->load() : '';
    }
    
}

