<?php

class Myshipserv_NotificationManager_Email_MatchSupplierIntroduction extends Myshipserv_NotificationManager_Email_Abstract {

    protected $subject = "";
    protected $isSalesforce = false;
    protected $supplier;
    protected $rfqid;
    protected $rfq;
    protected $db;
    private $buyer;

    public function __construct($supplierID, $rfqid) {
        $this->db = $this->getDb();
        if (!empty($supplierID)) {
            $supplierSQL = "Select * from Supplier_Branch where spb_branch_code = :tnid";

            $params = array('tnid' => (int) $supplierID);
            $supplierObj = $this->db->fetchAll($supplierSQL, $params);
            $supplier = $supplierObj[0];
        }

        if (!empty($rfqid)) {
            $rfqSQL = "SELECT *
                        FROM request_for_quote
                        WHERE rfq_internal_ref_no =
                          (SELECT rfq_sourcerfq_internal_no
                          FROM request_for_quote
                        WHERE rfq_internal_ref_no = :rfqid
                                      )";
            $params = array('rfqid' => $rfqid);
            $rfq = $this->db->fetchRow($rfqSQL, $params);

            $buyerSQL = "Select * from Buyer_Branch where byb_branch_code = :buyertnid";
            $bparams = array('buyertnid' => $rfq['RFQ_BYB_BRANCH_CODE']);

            $buyer = $this->db->fetchRow($buyerSQL, $bparams);
        }

        $this->supplier = $supplier;
        $this->rfqid = $rfqid;
        $this->rfq = $rfq;
        $this->buyer = $buyer;
    }

    public function getRecipients() {
        $row = array();
        $row[]['email'] = $this->supplier['SPB_EMAIL'];
        if ($_SERVER['APPLICATION_ENV'] == "production" || $_SERVER['APPLICATION_ENV'] == "development") {
            //$row[] = array("name" => "Match Monitor", "email" => "match.monitor.quote.email@shipserv.com");
        }
        return $row;
    }

    public function getSubject() {
        $subject = "New RFQ from {$this->buyer['BYB_NAME']} via ShipServ Match";
        return $subject;
    }

    public function getBody() {
        $view = $this->getView();
        $recipients = $this->getRecipients();
        $db = $this->getDb();
        $rfq = $this->rfq;


        //get supplier's privacy policy
        //$dbPriv = new Shipserv_Oracle_EndorsementPrivacy($db);
        //$sPrivacy = $dbPriv->getSupplierPrivacy($this->quote->getSupplier()->tnid);
        // give the view the supplier data
        $view->rfq = $rfq;
        $view->rfqid = $this->rfqid;
        $view->hostname = $_SERVER["HTTP_HOST"];

        $view->startSupplierRootURL = $this->getRootUrl();

        $view->supplier = $this->supplier;
        $view->buyer = $this->buyer;
        $view->buyerName = $this->buyer['BYB_NAME'];
        $view->smartSupplier = ($this->supplier['SPB_SMART_PRODUCT_NAME'] == 'SmartSupplier');


        $body = $view->render('email/match-supplier-introduction.phtml');

        foreach ($recipients as $recipient) {
            $emails[$recipient['email']] = $body;
        }
        return $emails;
    }

}

?>
