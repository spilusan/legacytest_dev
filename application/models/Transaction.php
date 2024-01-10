<?php

/**
 * Transaction monitor data model
 *
 * Class Application_Model_Transaction
 */
class Application_Model_Transaction
{
    public static $memcacheExpiration = 86400; //300

    /**
     * Set memcache expiration
     *
     * @param integer $exp
     */
    public static function setMemcacheExpiration($exp)
    {
        self::$memcacheExpiration = $exp;
    }

    /**
     * @param integer $buyerBranch
     * @param string $fromDate
     * @param string $toDate
     * @param string $qotdeadline
     * @param array $options
     * @param Shipserv_User $user
     * @param integer $pageStart
     * @param integer $pageEnd
     * @return array
     */
    public static function processParams($buyerBranch, $fromDate, $toDate, $qotdeadline, $options, $user, $pageStart, $pageEnd)
    {
        
        $buyerbranch = filter_var($buyerBranch, FILTER_SANITIZE_STRING);
        if (isset($_GET['allBuyerBranches']) && $_GET['allBuyerBranches'] != "") {
            $buyerbranch = filter_var($_GET['allBuyerBranches'], FILTER_SANITIZE_STRING);
        }
        /* Make sure, that they all are numbers */
        $values = explode(',', $buyerbranch);
        for ($i=0;$i<count($values);$i++) {
            $values[$i] = (int)$values[$i];
        }
        $buyerbranch = implode(',', $values);

        $params = array(
            ':fromdate'         => strtoupper($fromDate->format('d-M-Y H:i:s')),
            ':todate'           => strtoupper($toDate->format('d-M-Y H:i:s')),
            ':supplierbranch'   => (int) $options['supplierbranch'],
            ':suppliertype'     => $options['suppliertype'],
            ':status'           => $options['status'],
            ':mtmlbuyer'        => $user->mtmlbuyer,
            ':vessel'           => $options["vessel"],
            ':reference'        => $options["buyerreference"],
            ':pagestart'        => $pageStart,
            ':pageend'          => $pageEnd,
            ':buyercontact'     => $options['buyercontact'],
            ':variance'         => isset($options['variance']) ? $options['variance'] : null,
            ':urgent'           => isset($options['urgent']) ? $options['urgent'] : null,
            ':attachment'        => isset($options['attachment']) ? $options['attachment'] : null,
            ':novariance'         => isset($options['novariance']) ? $options['novariance'] : null,
            ':noturgent'           => isset($options['noturgent']) ? $options['noturgent'] : null,
            ':noattachment'        => isset($options['noattachment']) ? $options['noattachment'] : null
        );

        if ($qotdeadline) {
            $params[':qotdeadline'] = strtoupper($qotdeadline->format('d-M-Y H:i:s'));
        } else {
            $params[':qotdeadline'] = null;
        }

        $documentType = $options['documenttype'];
        $sortdirection = $options['sortdirection'];

        //Sanitize submitted_date as we'll be injecting it directly into SQL
        if (!in_array($options['sortfield'], array('submitted_date', 'doc_type', 'buyer_reference', 'supplier_reference', 'spb_name', 'spb_branch_code', 'vessel_name', 'status', /* S16126 */ 'rfq_advice_before_date'))) {
            $sortfield = 'submitted_date';
        } else {
            $sortfield = $options['sortfield'];
        }

        //Additional transformation of reference variable depending on search type
        if ($params[':reference'] != '') {
            if ($options['searchtype'] == '1') {
                $params[':reference'] .= '%';
            } elseif ($options['searchtype'] == '50') {
                $params[':reference'] = '%' . $params[':reference'] . '%';
            }
        }

        return array(
            'buyerbranch' => $buyerbranch,
            'params' => $params,
            'documentType' => $documentType,
            'sortdirection' => $sortdirection,
            'sortfield' => $sortfield
        );

    }

    /**
     * Search by params
     *
     * @param integer $buyerBranch
     * @param string $fromDate
     * @param string $toDate
     * @param string $qotdeadline
     * @param intger $pageStart
     * @param integer $pageEnd
     * @param array $options
     * @param Shipserv_User $user
     * @param bool $includeChildren
     * @param bool $allowUnlock
     * @param int $timeZoneOffset
     * @return array|string
     * @throws Zend_Exception
     */
    public static function search($buyerBranch, $fromDate, $toDate, $qotdeadline, $pageStart, $pageEnd, $options, $user, $includeChildren = false, $allowUnlock = false, $timeZoneOffset = 0)
    {

        $sortfield = null;
        $sortdirection = null;
        $buyerbranch = null;

        $params = array();
        $resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
        $db = $resource->getDb('ssreport2');

        extract(self::processParams($buyerBranch, $fromDate, $toDate, $qotdeadline, $options, $user, $pageStart, $pageEnd), EXTR_OVERWRITE);

        if (!$buyerbranch || !isset($options['documenttype']) || !$options['documenttype']) {
            return array();
        }

        $embeddedQueryParams = array(
            'includeChildren' => $includeChildren,
            'buyerbranch' => $buyerbranch,
            'sortfield' => $sortfield,
            'sortdirection' => $sortdirection
        );

        $transactionQueries = new Application_Model_TransactionQueries($options['documenttype'], $embeddedQueryParams);

        $requiredParams = $transactionQueries->getRequiredParams();
        $query =$transactionQueries->getQuery();



        //Connect to memcache
        $config = Zend_Registry::get('config');
        $memcache = new Memcache();
        $memcache->connect($config->memcache->server->host, $config->memcache->server->port);

        $preparedParams = array_intersect_key($params, array_flip($requiredParams));
        //echo($query); var_dump($preparedParams); die();

        //Dump the query
        if (isset($_GET['terminated']) && $_GET['terminated'] == 1) {
            echo ($query);
            var_dump($preparedParams);
            die;
        }

        $memcacheKey = 'txn_' . __FUNCTION__ . ':' . md5($query . '_' . serialize($preparedParams));
        if (!$result = $memcache->get($memcacheKey)) {
            //Fetch results using parameters and whatever query was generated
            $result = $db->fetchAll($query, $preparedParams);

            if (count($result) > 0) $result[0]['mc'] = $memcacheKey;

            //Add additional column with human-friendly status
            $TransactionModel = new Application_Model_Transaction();
            foreach ($result as &$r) {

                /* Auto unlock, added by Attila O 13 apr 2016 */
                if ((bool)$allowUnlock) {
                    if ($r["DOC_TYPE"] == "RFQ" && $r['RFQ_DEADLINE_MGR_UNLOCKED_DATE'] == null) {
                        $overDeadline = $TransactionModel->getAutoUnlockStatus($r['INTERNAL_REF_NO'], $timeZoneOffset);
                        if ($overDeadline > 0 && $r['RFQ_DEADLINE_ALLOW_MGR_UNLOCK'] == 0) {
                           //The RFQ must be unlocked
                            $unlockDate = $TransactionModel->unlockTransaction($r['INTERNAL_REF_NO'], false, true, $timeZoneOffset);
                            $r['RFQ_DEADLINE_MGR_UNLOCKED_DATE'] = $unlockDate ;
                        }
                    }
                }

                switch($r["STATUS"]) {
                    case 'ACC':
                        if ($r["DOC_TYPE"] == "RFQ") {
                            $r["STATUS_READABLE"] = "Quoted";
                        } else {
                            $r["STATUS_READABLE"] = "Accepted";
                        }
                        break;

                    case 'NEW':
                        if (($r["DOC_TYPE"] == "RFQ" || $r["DOC_TYPE"] == "PO") && $r["SPB_CONNECT_TYPE"] == "STARTSUPPLIER") {
                            $r["STATUS_READABLE"] = "Unopened";
                        } else {
                            $r["STATUS_READABLE"] = "Sent";
                        }
                        break;

                    case 'OPN':
                        if (($r["DOC_TYPE"] == "RFQ" || $r["DOC_TYPE"] == "PO") && $r["SPB_CONNECT_TYPE"] == "STARTSUPPLIER") {
                            $r["STATUS_READABLE"] = "Opened";
                        } else {
                            $r["STATUS_READABLE"] = "Sent";
                        }
                        break;

                    case 'DEC':
                        $r["STATUS_READABLE"] = "Declined";
                        break;

                    case 'CON':
                        $r["STATUS_READABLE"] = "Confirmed";
                        break;

                    default:
                        $r["STATUS_READABLE"] = $r["STATUS"];
                        break;
                }
            }

            $memcache->set($memcacheKey, $result, 0, self::$memcacheExpiration);
        }

        return $result;
    }

    /**
     * Get search query count
     *
     * @param int $buyerBranch
     * @param string $fromDate
     * @param string $toDate
     * @param string $qotdeadline
     * @param array $options
     * @param Shipserv_User $user
     * @param bool $includeChildren
     * @return int
     * @throws Zend_Exception
     */
    public function getSearchCount($buyerBranch, $fromDate, $toDate, $qotdeadline, $options, $user, $includeChildren = false)
    {

        $buyerbranch = null;
        $params = array();
        $pageStart = null;
        $pageEnd = null;

        $resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
        $db = $resource->getDb('ssreport2');

        extract(self::processParams($buyerBranch, $fromDate, $toDate, $qotdeadline, $options, $user, $pageStart, $pageEnd), EXTR_OVERWRITE);

    	if (!$buyerbranch || !isset($options['documenttype']) || !$options['documenttype']) {
            return 0;
    	}

        $embeddedQueryParams = array(
            'includeChildren' => $includeChildren,
            'buyerbranch' => $buyerbranch,
        );

        $transactionQueries = new Application_Model_TransactionQueries($options['documenttype'], $embeddedQueryParams);

        $requiredParams = $transactionQueries->getRequiredCountParams();
        $query =$transactionQueries->getCountQuery();

        if (isset($_GET['terminated']) && $_GET['terminated'] == 1) {
            echo $query;
            print_r(array_intersect_key($params, array_flip($requiredParams))); die();
        }
        //Connect to memcache
        $config = Zend_Registry::get('config');
        $memcache = new Memcache();
        $memcache->connect($config->memcache->server->host, $config->memcache->server->port);
        $preparedParams = array_intersect_key($params, array_flip($requiredParams));

        $memcacheKey = 'txn_' . __FUNCTION__ . ':' . md5($query . '_' . serialize($preparedParams));
        //print_r($preparedParams); print($query); die();

        if (!$result = $memcache->get($memcacheKey)) {
            //Fetch results using parameters and whatever query was generated
            $result = $db->fetchOne($query, $preparedParams);
            $memcache->set($memcacheKey, $result, 0, self::$memcacheExpiration);
        }

        return (int) $result;
    }

    /**
     * Get attacment by Txn ID
     *
     * @param integer $txnId
     * @param string $docType
     * @return mixed
     */
    public static function getAttachmentByTxnId($txnId, $docType)
    {
        $resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
        $db = $resource->getDb('ssreport2');
        $sql = "SELECT * FROM att_txn JOIN att_file ON ATT_ATF_ID=ATF_ID WHERE att_txn_id=:txnId AND att_txn_type=:docType";
        return $db->fetchAll($sql, array('txnId' => $txnId, 'docType' => $docType));
    }

    /**
     * Resend to email address
     *
     * @param string $docType
     * @param integer $internalRefNo
     * @param integer $spbBranchCode
     * @param intger $bybBranchCode
     * @return mixed
     */
    public function getResendEmailAddress($docType, $internalRefNo, $spbBranchCode, $bybBranchCode)
    {

        $resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
        $db = $resource->getDb('ssreport2');

        if ($docType == "RFQ") {
            $query =  "select spb.spb_email from supplier spb, rfq ";
            $query .= " where rfq.rfq_internal_ref_no=:internalrefno ";
            $query .= " and rfq.spb_branch_code=:spbbranchcode ";
            if (false/*isAdminUser != null && isAdminUser.equalsIgnoreCase("Y") @FIXME*/) {
                $query .= " and rfq.byb_branch_code=:orgcode ";
            } else {
                $query .= " and rfq.byb_branch_code=:bybbranchcode ";
            }
            $query .= " and spb.spb_branch_code=:spbbranchcode ";
        } elseif ($docType == "PO") {
            $query  = "select spb.spb_email from supplier spb, ord ";
            $query .= "where ord.ord_internal_ref_no=:internalrefno ";
            $query .= " and ord.spb_branch_code=:spbbranchcode ";
            if (false/*isAdminUser != null && isAdminUser.equalsIgnoreCase("Y") @FIXME*/) {
                $query .= " and org.byb_branch_code=:orgcode ";
            } else {
                $query .= " and ord.byb_branch_code=:bybbranchcode ";
            }
            $query .= " and spb.spb_branch_code=ord.spb_branch_code ";
        }
        
        $params = array(
            ':internalrefno' => $internalRefNo,
            ':spbbranchcode' => $spbBranchCode,
            ':bybbranchcode' => $bybBranchCode
        );
        $result = $db->fetchOne($query, $params);

        return $result;
    }

    /**
     * Get CAncel RFQ Address
     *
     * @param integer $internalRefNo
     * @param integer $spbBranchCode
     * @param integer $bybBranchCode
     * @return mixed
     */
    public function getCancelRfqAddress($internalRefNo, $spbBranchCode, $bybBranchCode)
    {

        $resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
        $db = $resource->getDb('ssreport2');

        $query =  "select spb.spb_email from supplier spb, rfq ";
        $query .= " where rfq.rfq_internal_ref_no=:internalrefno ";
        $query .= " and rfq.spb_branch_code=:spbbranchcode ";

        if (false/*isAdminUser != null && isAdminUser.equalsIgnoreCase("Y") @FIXME*/) {
            $query .= " and rfq.byb_branch_code=:orgcode ";
        } else {
            $query .= " and rfq.byb_branch_code=:bybBranchCode ";
        }
        $query .= " and spb.spb_branch_code=:spbbranchcode ";

        $params = array(
                ':internalrefno' => $internalRefNo,
                ':spbbranchcode' => $spbBranchCode,
                ':bybbranchcode' => $bybBranchCode
            );
        $result = $db->fetchOne($query, $params);

        return $result;
    }

    /**
     * Modified by Yuriy Akopov on 2014-10-15 to support RFQ Outbox reminded function
     *
     * @param   int     $internalRefNo
     * @param   int     $supplierBranch
     * @param   string  $alertType
     * @param   bool    $htmlMode
     * @param   string  $emailTo
     * @param   string  $emailCc
     *
     * @throws Zend_Exception
     */
    public function resendEmail($internalRefNo, $supplierBranch, $alertType, $htmlMode = true, $emailTo = null, $emailCc = null)
    {
        $db = Shipserv_Helper_Database::getDb();

        $record = array(
            'eaq_id'              => new Zend_Db_Expr('sq_email_alert_queue.nextval'),
            'eaq_internal_ref_no' => $internalRefNo,
            'eaq_spb_branch_code' => $supplierBranch,
            'eaq_alert_type'      => ( ( $alertType == 'RFQ_SUB' ) ? 'RFQ_RMD': 'ORD_RMD' ),
            'eaq_reminder'        => ($htmlMode ? 'HTML' : 'TEXT')
        );

        if (!is_null($emailTo)) {
            $record['eaq_to'] = $emailTo;
        }

        if (!is_null($emailCc)) {
            $record['eaq_cc'] = $emailCc;
        }

        $db->insert('email_alert_queue', $record);

        $db->commit();

        $dbSSReport2 = Shipserv_Helper_Database::getSsreport2Db();

        if ($alertType === 'ORD_SUB') {
            $dbSSReport2->update(
                'ord',
                array(
                    'ord_alert_last_send_date' => new Zend_Db_Expr('SYSDATE')
                ),
                implode(
                    ' AND ',
                    array(
                        $dbSSReport2->quoteInto('ord_internal_ref_no = ?', $internalRefNo),
                        $dbSSReport2->quoteInto('spb_branch_code = ?', $supplierBranch),
                    )
                )
            );
            // $query = "UPDATE ord SET ord_alert_last_send_date=SYSDATE WHERE ord_internal_ref_no=:docId AND spb_branch_code=:tnid";
        } else {
            $dbSSReport2->update(
                'rfq',
                array(
                    'rfq_alert_last_send_date' => new Zend_Db_Expr('SYSDATE')
                ),
                implode(
                    ' AND ',
                    array(
                        $dbSSReport2->quoteInto('rfq_internal_ref_no = ?', $internalRefNo),
                        $dbSSReport2->quoteInto('spb_branch_code = ?', $supplierBranch),
                    )
                )
            );
        }

        $dbSSReport2->commit();

        // purging memcache
        $mckey = _safestring($_REQUEST['mckey']);
        if (strlen($mckey)) {
            $memcache = new Memcache();
            $config = Zend_Registry::get('config');

            $memcache->connect($config->memcache->server->host, $config->memcache->server->port);
            $memcache->replace($mckey, '', 0, 1);
        }
    }

    /**
     * RFQ Cancellation Email to
     * @param   int     $internalRefNo
     * @param   int     $supplierBranch
     * @param   bool    $htmlMode
     * @param   string  $emailTo
     * @param   string  $emailCc
     *
     * @throws Zend_Exception
     */
    public function cancelEmail($internalRefNo, $supplierBranch, $htmlMode = true, $emailTo = null, $emailCc = null)
    {
        $db = Shipserv_Helper_Database::getDb();

        $record = array(
            'eaq_id'              => new Zend_Db_Expr('sq_email_alert_queue.nextval'),
            'eaq_internal_ref_no' => $internalRefNo,
            'eaq_spb_branch_code' => $supplierBranch,
            'eaq_alert_type'      => 'RFQ_CAN',
            'eaq_reminder'        => ($htmlMode ? 'HTML' : 'TEXT')
        );

        if (!is_null($emailTo)) {
            $record['eaq_to'] = $emailTo;
        }

        if (!is_null($emailCc)) {
            $record['eaq_cc'] = $emailCc;
        }

        $db->insert('email_alert_queue', $record);

        $db->commit();

        $dbSSReport2 = Shipserv_Helper_Database::getSsreport2Db();

        $dbSSReport2->update(
            'rfq',
            array(
                'rfq_cancellation_last_sent' => new Zend_Db_Expr('SYSDATE')
            ),
            implode(
                ' AND ',
                array(
                    $dbSSReport2->quoteInto('rfq_internal_ref_no = ?', $internalRefNo),
                    $dbSSReport2->quoteInto('spb_branch_code = ?', $supplierBranch),
                )
            )
        );


        $dbSSReport2->commit();

        // purging memcache
        $mckey = _safestring($_REQUEST['mckey']);
        if (strlen($mckey)) {
            $memcache = new Memcache();
            $config = Zend_Registry::get('config');

            $memcache->connect($config->memcache->server->host, $config->memcache->server->port);
            $memcache->replace($mckey, '', 0, 1);
        }
    }


    /**
     * Returns attachment information for the given transaction
     *
     * @author  Yuriy Akopov
     * @date    2013-12-05
     * @story   S8971
     *
     * @param   Shipserv_Rfq|Shipserv_Quote|Shipserv_PurchaseOrder|Shipserv_Enquiry|Shipserv_PurchaseOrderConfirmation  $transaction
     *
     * @return  array
     * @throws  Exception
     */
    public static function getTransactionAttachments($transaction)
    {

        if ($transaction instanceof Shipserv_Rfq) {
            $transactionType = 'RFQ';
            $transactionId = $transaction->rfqInternalRefNo;

            // $buyerId    = $transaction->rfqBybBranchCode;
            // $supplierId = $transaction->rfqSpbBranchCode;

        } else if ($transaction instanceof Shipserv_Quote) {
            $transactionType = 'QOT';
            $transactionId = $transaction->qotInternalRefNo;

            // $buyerId    = $transaction->getOriginalBuyerId(true);
            // $supplierId = $transaction->qotSpbBranchCode;

        } else if ($transaction instanceof Shipserv_PurchaseOrder) {
            $transactionType = 'PO';
            $transactionId = $transaction->ordInternalRefNo;

            // $buyerId    = $transaction->ordBybBuyerBranchCode;
            // $supplierId = $transaction->ordSpbBranchCode;

        } else if ($transaction instanceof Shipserv_PurchaseOrderConfirmation) {
            $transactionType = 'POC';
            $transactionId = $transaction->pocOrdInternalRefNo;

            // $buyerId    = $transaction->pocBybBranchCode;
            // $supplierId = $transaction->pocSpbBranchCode;

        } else if (($transaction instanceof Myshipserv_Enquiry) or ($transaction instanceof Shipserv_Enquiry)) {
            // Enquiries are legacy structure, so re-using their legacy mechanism here

            if ($transaction instanceof Myshipserv_Enquiry) {
                $enqAttachments = $transaction->attachments;
            } else {
                /** @var Shipserv_Enquiry $transaction */
                $enqAttachments = $transaction->getAttachments();
            }

            $attachments = array();
            foreach ($enqAttachments as $eqAtt) {
                $attachments[] = array(
                    'filename'      => $eqAtt['urlFilename'],
                    'filename_orig' => $eqAtt['filename'],
                    'type'          => $eqAtt['type'],
                    'url_absolute'  => true,
                    'url'           => $eqAtt['url'],
                    'size_orig'     => $eqAtt['size'],
                    'size'          => self::getReadableFileSize($eqAtt['size'])
                );
            }

            return $attachments;

        } else {
            throw new Exception("Unknown or not yet supported transaction type, cannot retrieve attachments");
        }

        // retrieve transaction rows
        $select = new Zend_Db_Select(Shipserv_Helper_Database::getSsreport2Db());
        $select
            ->from(
                array('t' => 'att_txn'),
                't.*'
            )
            ->join(
                array('f' => 'att_file'),
                't.att_atf_id = f.atf_id',
                'f.*'
            )
            ->where('t.att_txn_id = :transactionId')
            ->where('t.att_txn_type = :transactionType')
            ->distinct();

        $attachmentData = $select->getAdapter()->fetchAll(
            $select,
            array(
                'transactionId'     => $transactionId,
                'transactionType'   => $transactionType
                )
        );

        if (empty($attachmentData)) {
            return array(); // so we don't get that stupidest warning on foreach'ing an empty array
        }

        $attachments = array();
        foreach ($attachmentData as $attRow) {
            // build URL to download an attachment
            $url = '/FileAttachment?' . http_build_query(
                array(
                    'action'    => 'view',
                    'docType'   => $transactionType,
                    'bCode'     => $attRow['ATT_TXN_BY'], //$buyerId,
                    'atfID'     => $attRow['ATF_ID'],
                    'cby'       => $attRow['ATT_TXN_BY'] // ((in_array($transactionType, array('POC', 'QOT'))) ? $buyerId : $supplierId)
                )
            );

            $attachments[] = array(
                'filename'      => $attRow['ATF_FILE_NAME'],
                'filename_orig' => $attRow['ATF_FILE_NAME_ORIG'],
                'type'          => $attRow['ATF_FILE_TYPE'],
                'url_absolute'  => false,
                'url'           => $url,
                'size_orig'     => $attRow['ATF_FILE_SIZE'],
                'size'          => self::getReadableFileSize($attRow['ATF_FILE_SIZE']),
            );
        }

        return $attachments;
    }

    /**
     * Returns human readable file size label when given size in bytes
     *
     * @author  Yuriy Akopov
     * @date    2013-12-06
     * @story   S8971
     *
     * @param   int $size
     *
     * @return string
     */
    public static function getReadableFileSize($size)
    {
        if ($size >= 1<<30) {
            return number_format($size / (1<<30), 2) . " GB";
        } else if ($size >= 1<<20) {
            return number_format($size / (1<<20), 2) . " MB";
        } else if ($size >= 1<<10) {
            return number_format($size / (1<<10), 2) . " KB";
        }

        return number_format($size) . " bytes";
    }

    /**
     *  Unlock transaction by it's internal_ref_no
     *
     * @param integer $id
     * @param bool $isQot
     * @param bool $auto
     * @param int $timeZoneOffset
     * @return bool|string
     * @throws Zend_Exception
     */
    public function unlockTransaction($id, $isQot = false, $auto = false, $timeZoneOffset = 0)
    {
    
        $ssReportDb = Shipserv_Helper_Database::getSsreport2Db();
        $db = Shipserv_Helper_Database::getDb();
         
        if ($isQot) {
            //If it is qot, we need to look for rfq_internal_ref_no by ord_internal_ref_no
            $sql = "
                SELECT
                  rfq_internal_ref_no
                FROM
                  qot
                WHERE
                  qot_internal_ref_no = :qotInternalRefNo
            ";
            $transactionId = $ssReportDb->fetchOne(
                $sql,
                array(
                    'qotInternalRefNo' => $id
                )
            );
            if (!$transactionId) {
                return false;
            }
    
        } else {
            $transactionId = $id;
        }
    
        if ($auto == false) {
            $user = Shipserv_User::isLoggedIn();
            if ($user) {
                $userId = ($user) ? (int)$user->userId : null;
            } else {
                $tnUser = new Shipserv_Oracle_User_Tradenet();
                $config = Zend_Registry::get('config');
                $userTradenet = $tnUser->fetchUserByName(Myshipserv_CAS_CasRest::getInstance()->getUserName());
                $userId = ($userTradenet) ? (int)$userTradenet->usercode : null;
            }
            // Getting the current date, once so the rfq, and request_for_quote will contain the same, and I also can return it to be displayed
    
            $sql = "
                SELECT
                    TO_CHAR(SYSDATE + ((1/24)*:timeZoneOffset), 'DD-MON-YYYY HH24:MI') curr_date
                FROM
                    dual
            ";
    
            $currentDate = $ssReportDb->fetchOne(
                $sql,
                array(
                    'timeZoneOffset' => $timeZoneOffset
                )
            );
    
        } else {
            $userId = 1;
            $sql = "
                SELECT
                    TO_CHAR(RFQ_ADVICE_BEFORE_DATE, 'DD-MON-YYYY HH24:MI') curr_date
                FROM
                    rfq
                WHERE
                    rfq_internal_ref_no = :rfqInternalRefNo
            ";
    
            $currentDate = $ssReportDb->fetchOne(
                $sql,
                array(
                    'rfqInternalRefNo' => $transactionId
                )
            );
        }
    
        $result = $currentDate;
        $sql = "
            UPDATE
                request_for_quote rfq
            SET
                rfq_deadline_mgr_unlocked_date = TO_DATE(:currDate, 'DD-MON-YYYY HH24:MI')
                ,rfq_deadline_mgr_unlocked_by = :userId
            WHERE
                rfq.rfq_internal_ref_no = :rfqInternalRefNo
            ";
    
        try {
            $db->query(
                $sql,
                array(
                    'rfqInternalRefNo' => $transactionId
                    ,'userId' => $userId
                    ,'currDate' => $currentDate
                )
            );
        } catch (Exception $e) {
            $result = false;
        }
    
        //Replace in SSREPORT too
    
    
        $sql = "
            UPDATE
                rfq
            SET
                 rfq_deadline_mgr_unlocked_date = TO_DATE(:currDate, 'DD-MON-YYYY HH24:MI')
                ,rfq_deadline_mgr_unlocked_by = :userId
            WHERE
                rfq.rfq_internal_ref_no = :rfqInternalRefNo
            ";
    
        try {
            $ssReportDb->query(
                $sql,
                array(
                    'rfqInternalRefNo' => $transactionId
                    ,'userId' => $userId
                    ,'currDate' => $currentDate
                )
            );
        } catch (Exception $e) {
            $result = false;
        }
    
        //We need to flush the memcache after updating the data
        $config = Zend_Registry::get('config');
        $memcache = new Memcache();
        $memcache->connect($config->memcache->server->host, $config->memcache->server->port);
        $memcache->flush();
    
        return $result;
    }

    /**
     * Getting the auto-unlock status
     *
     * @param int $rfqInternalRefNo
     * @param int $timeZoneOffset
     * @return bool
     */
    public function getAutoUnlockStatus($rfqInternalRefNo, $timeZoneOffset = 0)
    {
        $sql = "
            SELECT
                  rfq_internal_ref_no
                , ( SYSDATE + ((1/24)*:timeZoneOffset)) - ( rfq_advice_before_date ) as rfq_past_deadline_days
            FROM
                rfq
            WHERE
                rfq_internal_ref_no = :rfqInternalRefNo
                and spb_branch_code != 999999
            GROUP BY
                  rfq_internal_ref_no
                , ( SYSDATE + ((1/24)*:timeZoneOffset)) - ( rfq_advice_before_date )
            ";

            $ssReportDb = Shipserv_Helper_Database::getSsreport2Db();
            $result = $ssReportDb->fetchAll(
                $sql,
                array(
                    'rfqInternalRefNo' => (int)$rfqInternalRefNo
                    ,'timeZoneOffset' => $timeZoneOffset
                )
            );

            if (count($result) > 0) {
                return  $result[0]['RFQ_PAST_DEADLINE_DAYS'];
            } else {
                return false;
            }
    }
}
