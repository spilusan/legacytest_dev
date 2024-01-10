<?php
class Myshipserv_NotificationManager_Email_QuoteToBuyerForPagesRFQ extends Myshipserv_NotificationManager_Email_Abstract {

    protected $subject = "";
    protected $isSalesforce = false;

    public function __construct($quoteId, $buyerId = '') {
        $this->db = $this->getDb();
        
        if ($_SERVER['APPLICATION_ENV'] == "production" || $_SERVER['APPLICATION_ENV'] == "testing") 
        {
        	$this->enableSMTPRelay = true;
        }
        else
        {
        	$this->enableSMTPRelay = false;
        }
        
        if (empty($buyerId)) {
            $buyerSQL = "Select rfq_byb_branch_code from 
							request_for_quote 
								where rfq_internal_ref_no = 
									(select rfq_sourcerfq_internal_no 
										from request_for_quote 
										where rfq_internal_ref_no = (Select qot_rfq_internal_ref_no 
																		from quote 
																		where qot_internal_ref_no = :qid))";

            $params = array('qid' => (int) $quoteId);
            $buyerObj = $this->db->fetchAll($buyerSQL, $params);

            if (count($buyerObj) > 0) {
                $buyerId = $buyerObj[0]['RFQ_BYB_BRANCH_CODE'];
            }
        }
        //Search for Attachments and add them to the list
        //Lets check to see if there is an attachment
        $attachmentSQL = "Select * from attachment_txn t, attachment_file f where t.att_atf_id = f.atf_id and  att_transaction_type = 'QOT' and att_transaction_id = :qot";
        $params = array('qot' => $quoteId);
        $attachments = $this->db->fetchAll($attachmentSQL, $params);
        if (count($attachments) > 0) {
        	$attachLoc = $this->db->fetchOne("Select GCF_ATTACHMENT_PATH from Global_Config");
            foreach ($attachments as $attachment) {
            	$qs = array(
            			'action' => 'view'
            			, 'docType' => "QOT"
            			, 'bCode' => $attachment['ATF_CREATED_BY']
            			, 'atfID' => $attachment['ATT_ATF_ID']
            			, 'cby' => $attachment['ATF_CREATED_BY']
            	);
                $formattedAttch[] = array(
                	'name' => $attachment['ATF_ORIG_FILENAME'], 
                	"size" => $attachment['ATF_FILESIZE'],
                	"url" => $_SERVER["HTTP_HOST"] . '/FileAttachment?' . http_build_query($qs)
               	);
            }
            $this->attachments = $formattedAttch;
        }
        
        $this->quote = Shipserv_Quote::getInstanceById($quoteId);
        $this->rfq = $this->quote->getRfq();
        if (!empty($buyerId)) {
            $this->buyer = Shipserv_Buyer::getBuyerBranchInstanceById($buyerId);
        }
    }

    public function getRecipients() {
        $row = array();
        $row[]['email'] = $this->rfq->rfqEmailAddress;

        if ($_SERVER['APPLICATION_ENV'] == "production") 
        {
            $row[] = array("name" => "", "email" => "pages.monitor.quote.email@shipserv.com");
        }
        else if ( $_SERVER['APPLICATION_ENV'] == "development" )
        {
        	$row[] = array("name" => "", "email" => "eleonard@shipserv.com");
        }
        return $row;
    }

    public function getSubject() {
        $subject = "Quote from " . $this->quote->getSupplier()->name . " for Pages RFQ: " . $this->quote->qotRfqInternalRefNo . " - Vessel: " . $this->rfq->rfqVesselName . "";
        if ($this->enableSMTPRelay)
        {
        	if ($_SERVER['APPLICATION_ENV'] == "production") 
        	{
        		//$subject .= "{Quote email to buyer - Live}";
        	}
        	else
        	{
        		//$subject .= "{Quote email to buyer - UAT}";
        	}
        }
        
        return $subject;
    }

    public function getBody() {
        $view = $this->getView();
        $recipients = $this->getRecipients();
        $db = $this->getDb();
        //$rfq = $this->rfq;

         // get endorsement info
        $profileDao = new Shipserv_Oracle_Profile($db);
        $endorsee = $profileDao->getSuppliersByIds(array($this->quote->getSupplier()->tnid));
        $endorseeInfo = $endorsee[0];

        //retrieve list of endorsements for given supplier
        $endorsementsAdapter = new Shipserv_Oracle_Endorsements($db);
        $endorsements = $endorsementsAdapter->fetchEndorsementsByEndorsee($this->quote->getSupplier()->tnid, false);
        $endorseeIdsArray = array();
        foreach ($endorsements as $endorsement) {
            $endorseeIdsArray[] = $endorsement["PE_ENDORSER_ID"];
        }

        $userEndorsementPrivacy = $endorsementsAdapter->showBuyers($this->quote->getSupplier()->tnid, $endorseeIdsArray);

        //get supplier's privacy policy
        $dbPriv = new Shipserv_Oracle_EndorsementPrivacy($db);
        $sPrivacy = $dbPriv->getSupplierPrivacy($this->quote->getSupplier()->tnid);

        // give the view the supplier data
        $view->quote = $this->quote;
        //$view->quotes = $quotes;
        $view->hostname = $_SERVER["HTTP_HOST"];
        $view->endorseeInfo = $endorseeInfo;
        //$view->supplier = $supplier;
        $view->endorsements = $endorsements;
        $view->attachments = $this->attachments;
        $view->reviews = Shipserv_Review::fetchSummary($this->quote->getSupplier()->tnid);
        $view->userEndorsementsPrivacy = $userEndorsementPrivacy;
        $view->supplierPrivacy = $sPrivacy->getGlobalAnonPolicy();

        $view->urlToPlacePurchaseOrder = $this->quote->getUrlToPlacePurchaseOrder();
        $body = $view->render('email/quote-to-buyer-pages-rfq.phtml');
        
        foreach ($recipients as $recipient) {
            $emails[$recipient['email']] = $body;
        }
        return $emails;
    }

}

?>
