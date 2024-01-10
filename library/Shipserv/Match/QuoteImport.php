<?php

class Shipserv_Match_QuoteImport extends Shipserv_Object {

    private $db;

    public function __construct()
    {
        $this->logger = new Myshipserv_Logger_File('match-quote-import');
    }

    /**
     * Function to poll a table (match_imported_rfq_proc_list) and send the
     * RFQ Ids off to the IXP service for quote import. Will do a number of
     * checks on the RFQ to ensure that its a match buyer, that the supplier
     * has already received the same rfq from match etc.
     */
    public function poll()
    {
        $this->db = $this->getDb();
        $sql = "SELECT * FROM match_imported_rfq_proc_list WHERE mir_processed != 'Y' OR mir_processed IS null";
        $proclist = $this->db->fetchAll($sql);

        foreach ($proclist as $rfq)
        {
            $rfqId = $rfq['MIR_RFQ_INTERNAL_REF_NO'];
            $supplier = $rfq['MIR_SPB_BRANCH_CODE'];

            $this->sendToIxp($rfqId, $supplier, $rfq);

            $sql = "UPDATE match_imported_rfq_proc_list 
            		SET mir_processed = 'Y' 
            		WHERE mir_rfq_internal_ref_no = :rfqId";
            $params = array('rfqId' => $rfqId);
            $this->db->query($sql, $params);
        }
    }

    /**
     * Function that sends the RFQ id to IXP service
     * @param type $rfqId
     * @param type $supplier
     * @return boolean
     * @throws MessagedException
     */
    private function sendToIxp($rfqId, $supplier, $row)
    {
        if (preg_match('/[^0-9]/', $rfqId))
        {
            throw new Myshipserv_Exception_MessagedException("Invalid rfqId passed");
        }

        $client = $this->makeHttpClient();
        $client->setMethod(Zend_Http_Client::GET);
        $client->setParameterGet(
            array(
                'rfqId' => (int) $rfqId,
                'spbTnid' => (int) $supplier
            )
        );

        $response = $client->request();
        if ($response->getStatus() == 200)
        {
        	$quoteId = json_decode($response->getBody());
        }
        else
        {
            $message = $response->getMessage();
        }

        if( $quoteId == 0 || $message != "" )
        {
            $this->notifyError($rfqId, $supplier, $message . " <br />" . print_r($row, true));
            return false;
        }
        else
        {
            return true;
        }

    }

    public function notifyError($rfqId, $supplier, $additionalResponseMessage)
    {
        $logMessage = $this->getLastIxpError($rfqId);
        if (is_null($logMessage)) {
            $logMessage = 'N/A';
        }

        // notify buyer of failed import - added by Yuriy Akopov on 2014-08-07, story S10774

        $legacyEmailErrorMessage = "";

        $notificationManager = new Myshipserv_NotificationManager(Shipserv_Helper_Database::getDb());
        try {
            $notificationManager->sendMatchQuoteImportFailedToBuyer($rfqId, $supplier, $logMessage);
        } catch (Myshipserv_NotificationManager_Email_Exception $e) {
            // most likely this is because the buyer branch doesn't have a valid email specified for it
            $legacyEmailErrorMessage = "Failed to send a notification message to buyer because of " . get_class($e) . ": " . $e->getMessage();
        }

        // carry on with legacy message

        $message = $legacyEmailErrorMessage . "

The following error occurred:

=======================================================================
Error in Match Quote Import. (IXP did not return Quote ID.)
=======================================================================

rfqId: {$rfqId}
supplierId: {$supplier}

Exception message:
{$additionalResponseMessage}

IXP log message:
{$logMessage}

Best Regards,

ShipServ
support@shipserv.com

";

		if( $_SERVER['APPLICATION_ENV'] == "development" )
		{
			$destination = "yakopov@shipserv.com";
		}
		else
		{
			$destination = 'match.engineers@shipserv.com';
		}

		$newmail = new Zend_Mail();
    	$newmail->setFrom('support@shipserv.com');
    	$newmail->addTo($destination);
    	$newmail->setSubject("[" . $_SERVER['APPLICATION_ENV']  ."] Error on match quote import " . date("d-m-Y"));
    	$newmail->setBodyHtml(nl2br($message));
    	$newmail->send();

    }

    /**
     * Notify the suppliers
     * @param type $tnid
     * @param type $rfqid
     * @return boolean
     */
    public function sendEmailNotifications($tnid, $rfqid) {
        $enableSend = true;
        $db = $this->getDb();
        if ($enableSend) {
            $nm = new Myshipserv_NotificationManager($db);

            $nm->sendMatchSupplierIntroduction($tnid, $rfqid);
        }
        return true;
    }

    private function makeHttpClient() {
        $config = Zend_Registry::get('config');

        $baseUrl = $config->shipserv->services->tradenet->quoteimport->url;

        $client = new Zend_Http_Client();
        $client->setUri($baseUrl);
        $client->setConfig(array(
            'maxredirects' => 0,
            'timeout' => 5));

        return $client;
    }


    /**
     * Returns the last IXP error message for the given RFQ
     *
     * @author  Yuriy Akopov
     * @date    2013-09-12
     * @story   S8093
     *
     * @param   int $rfqId
     *
     * @return  string|null
     */
    protected function getLastIxpError($rfqId) {
        $db = $this->getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mir' => 'match_imported_rfq_proc_list'),
                // the column we need is CLOB, not string, so in order to get a string back we need to convert
                array('error' => new Zend_Db_Expr('DBMS_LOB.substr(mir_error_log, 10000)'))
            )
            ->where('mir.mir_rfq_internal_ref_no = :rfqid')
            ->where('ROWNUM = 1')
            ->order('mir.mir_qot_imported_date DESC')
            ->order('mir.mir_id DESC')
        ;

        try {
            $errorMessage = $db->fetchOne($select, array('rfqid' => $rfqId));
        } catch (Exception $e) {
            // column might not exist in the live database yet
            $errorMessage = null;
        }

        return $errorMessage;
    }
}
