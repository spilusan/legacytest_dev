<?php
/**
 * Email sent to buyer when import of their match quote fails
 *
 * @author  Yuriy Akopov
 * @date    2014-08-06
 * @story   S10774
 */
class Myshipserv_NotificationManager_Email_MatchQuoteImportFailed extends Myshipserv_NotificationManager_Email_Abstract {
    /**
     * @var null|Shipserv_Rfq
     */
    protected $rfq = null;

    /**
     * @var null|Shipserv_Supplier
     */
    protected $supplier = null;

    /**
     * @var null|string
     */
    protected $message = null;

    /**
     * Raw log message from IXP service which needs to be parsed to recover user readable message
     *
     * @var null|string
     */
    protected $rawIxpMessage = null;

    /**
     * @param   int     $rfqId
     * @param   int     $supplierId
     * @param   string  $rawIxpMessage
     */
    public function __construct($rfqId, $supplierId, $rawIxpMessage) {
        $this->rawIxpMessage = $rawIxpMessage;

        $this->rfq      = Shipserv_Rfq::getInstanceById($rfqId);
        $this->supplier = Shipserv_Supplier::getInstanceById($supplierId, '', true);
        // $this->buyer = Shipserv_Buyer::getBuyerBranchInstanceById($this->rfq->rfqBybBranchCode);
    }

    /**
     * @return string
     */
    public function getSubject() {
        $rfqStr = $this->rfq->rfqRefNo;
        if (strlen($this->rfq->rfqVesselName)) {
            $rfqStr .= " - Vessel: " . $this->rfq->rfqVesselName;
        }

        $subject = "ShipServ Match - Quote import failed for RFQ: " . $rfqStr;

        return $subject;
    }

    /**
     * Parses IXP log for error codes and returns user readable messages
     *
     * @return  array
     */
    protected function getUserMessages() {
        /**
         * Sample value of $this->rawIxpMessage
         * 2014-08-11 02:21:02:737 DEBUG Constructing Line Items
         * 2014-08-11 02:21:02:739 ERROR [ActualLineItemCountMismatchEx] Doc Changed on Actual Line Item Count
         * 2014-08-11 02:21:02:739 ERROR [ActualLineItemCountMismatchEx] 7 not equal to 2
         * 2014-08-11 02:21:02:739 DEBUG Committing database transaction
         * 2014-08-11 02:21:02:739 DEBUG Closing connection to the database
         */
        $lines = explode("\n", $this->rawIxpMessage);
        $userMessages = array();

        $noMatchStr =
            "There is no RFQ with a Reference No.: " . $this->rfq->rfqRefNo .
            " found within the last days that was sent by Buyer " . $this->rfq->rfqBybBranchCode .
            " to Match Supplier " . Myshipserv_Config::getProxyMatchSupplier() .
            " that was forwarded by Match Buyer " . Myshipserv_Config::getProxyMatchBuyer() .
            " to Supplier " . $this->supplier->tnid
        ;

        $errors = array(
            // errors commented out may appear in IXP output but should not result in a buyer notification email
            //
            // 'RFQReferenceNumberMismatchEx'   => "Document Header Reference Number Value Mismatch",
            //'MatchFieldListNotFoundEx'        => "The list of fields that will be used for matching is not set in the database",

            // and these errors need to be sent out
            'LineItemDescriptionMismatchEx'     => "Line Item Description Mismatch",
            'ActualLineItemCountMismatchEx'     => "Actual Line Item Count Mismatch",
            'LineItemCountMismatchEx'           => "Document Header Line Item Count Attribute Value Mismatch",
            'NoMatchingRFQWithRefenceFoundEx'   => $noMatchStr
        );

        foreach ($lines as $line) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}:\d{3} ERROR \[(\w+)\] (.*)$/', $line, $matches)) {
                continue;
            }

            $errorCode    = $matches[1];
            $errorDetails = $matches[2];

            if (!array_key_exists($errorCode, $errors)) {
                // IXP error is not in the list of ones to be sent to buyer
                continue;
            }

            if (array_key_exists($errorCode, $userMessages)) {
                $userMessages[$errorCode]['details'][] = $errorDetails;
            } else {
                $userMessages[$errorCode] = array(
                    'error'   => $errors[$errorCode],
                    'details' => array(
                        $errorDetails
                    )
                );
            }
        }

        return $userMessages;
    }

    /**
     * @return  array
     * @throws  Myshipserv_NotificationManager_Email_Exception_SilentImportError
     */
    public function getBody() {
        $view = $this->getView();

        $view->rfq      = $this->rfq;
        $view->supplier = $this->supplier;

        $view->userMessages = $this->getUserMessages();
        if (empty($view->userMessages)) {
            // no errors recognised, nothing to sent, abort email notification
            throw new Myshipserv_NotificationManager_Email_Exception_SilentImportError();
        }

        $body = $view->render('email/ss-match-quote-import-failed.phtml');

        $recipients = $this->getRecipients();
        $emails = array(
            $recipients->getHash() => $body
        );

        return $emails;
    }

    /**
     * @return Myshipserv_NotificationManager_Recipient
     */
    public function getRecipients() {
        $to = self::getBuyerBranchRecipients($this->rfq->rfqBybBranchCode);
        $recipientList = array(
            Myshipserv_NotificationManager_Recipient::RECIPIENTS_TO  => array($to),
            Myshipserv_NotificationManager_Recipient::RECIPIENTS_BCC => array()
        );

        $bcc = Myshipserv_Config::getMatchQuoteImportBccRecipients();
        if (!empty($bcc)) {
            foreach ($bcc as $bccEmail) {
                $recipientList[Myshipserv_NotificationManager_Recipient::RECIPIENTS_BCC][] = array(
                    Myshipserv_NotificationManager_Recipient::RECIPIENT_NAME  => "ShipServ Staff",
                    Myshipserv_NotificationManager_Recipient::RECIPIENT_EMAIL => $bccEmail
                );
            }
        }

        $recipient = new Myshipserv_NotificationManager_Recipient();
        $recipient->list = $recipientList;

        return $recipient;
    }
}
