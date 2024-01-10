<?php
/**
 * Email which is sent to supplier when an automatch quote was not sent to buyer because it wasn't cheap enough
 *
 * @author  Yuriy Akopov
 * @date    2014-06-19
 * @story   S10311
 */
class Myshipserv_NotificationManager_Email_AutoMatchQuoteNotSent extends Myshipserv_NotificationManager_Email_Abstract
{
    protected $subject = "";
    protected $isSalesforce = false;

    /**
     * @var null|Shipserv_Quote
     */
    protected $quote = null;

    /**
     * @param   Shipserv_Quote|int  $quote
     *
     * @throws  Myshipserv_NotificationManager_Exception
     */
    public function __construct($quote) {
        if (!($quote instanceof Shipserv_Quote)) {
            $this->quote = Shipserv_Quote::getInstanceById($quote);
        } else {
            $this->quote = $quote;
        }

        if (!$this->quote->isAutoMatchQuote()) {
            throw new Myshipserv_NotificationManager_Exception("Not an auto match quote, invalid use case for supplier notification");
        }

        if ($this->quote->wasEmailedAsMatch()) {
            throw new Myshipserv_NotificationManager_Exception("Unable to tell supplier automatch quote was not sent as there is a record it was sent");
        }
    }

    /**
     * @return array|Myshipserv_NotificationManager_Recipient
     */
    public function getRecipients()
    {
        $recipient  = new Myshipserv_NotificationManager_Recipient();
        $recipient->list = array(
            'TO'  => array(),
            'CC'  => array(),
            'BCC' => array()
        );

        $supplier = $this->quote->getSupplier();

        // DE6438 by Yuriy Akopov on 2016-03-22, checking if there is an email in the quote itself to use
        // before reverting to supplier's email
        // @todo: this email should be one day moved to email_alert_queue to be sent out by email notificaiton service
        // @todo: as it would employ proper logic for client's email settings
        $toEmail = $this->quote->qotEmailAddress;
        if (!filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            // fall back to supplier's email
            $toEmail = $supplier->email;
        }

        $toName = $this->quote->qotContact;
        if (strlen($toName) < 4) {
            // revert to supplier's name
            $toName = $supplier->name;
        }

        $recipient->list['TO'][] = array(
            'name'  => $toName,
            'email' => $toEmail
        );

        switch (Myshipserv_Config::getEnv()) {
            case Myshipserv_Config::ENV_LIVE:
                $recipient->list['BCC'][] = array(
                    'name'  => "",
                    'email' => "match.monitor.quote.email@shipserv.com"
                );
                break;

            case Myshipserv_Config::ENV_DEV:
                $recipient->list['BCC'][] = array(
                    "name"  => "Elvir Leonard",
                    "email" => "eleonard@shipserv.com"
                );
                $recipient->list['BCC'][] = array(
                    "name"  => "Yuriy Akopov",
                    "email" => "yakopov@shipserv.com"
                );
                break;
        }

        return $recipient;
    }

    /**
     * @return string
     */
    public function getSubject() {
        $subject = "Your quote " . $this->quote->qotInternalRefNo . " has been automatically declined";
        return $subject;
    }

    /**
     * @return array
     */
    public function getBody() {
        $view = $this->getView();

        $rfq = $this->quote->getRfq();
        $buyerBranch = Shipserv_Buyer_Branch::getInstanceById($rfq->rfqBybBranchCode);
        $sentDate = new DateTime('@' . $this->quote->qotSubmittedDate->getTimestamp());

        $view->quote    = $this->quote;
        $view->supplier = $this->quote->getSupplier();
        $view->rfq      = $rfq;
        $view->buyer    = $buyerBranch;
        $view->date     = $sentDate;

        $view->hostname = $_SERVER["HTTP_HOST"];

        $body = $view->render('email/ss-match-quote-too-expensive.phtml');

        $recipients = $this->getRecipients();

        $emails = array(
            $recipients->getHash() => $body
        );

        return $emails;
    }
}
