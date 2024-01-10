<?php

/**
 * Class to call the RFQ forwarding service and return the new RFQ id. Sends emails to notify supplier of receipt also.
 */
class Shipserv_Match_Forwarder extends Shipserv_Object {
    /**
     * Function that makes the call to the forwarding service
     *
     * @param   int $rfqId
     * @param   int $supplierId
     *
     * @return  int
     * @throws  Shipserv_Match_Forwarder_Exception
     */
    public function forwardRFQ($rfqId, $supplierId) {
        $client = $this->makeHttpClient();
        $client->setParameterGet(array(
            'supplierTnid'  => (int) $supplierId,
            'rfqId'         => (int) $rfqId
        ));

        $response = $client->request();
        if ($response->getStatus() != 200) {
            throw new Shipserv_Match_Forwarder_Exception("RFQ forwarding service returned an error", $rfqId);
        }

        $responseBody = $response->getBody();
        $fwdRfqId = json_decode($responseBody);
        if (!filter_var($fwdRfqId, FILTER_VALIDATE_INT)) {
            throw new Shipserv_Match_Forwarder_Exception("RFQ forwarding service to return forwarded RFQ ID", $rfqId);
        }

        return (int) $fwdRfqId;
    }

    /**
     * @param   int $tnid
     * @param   int $rfqid
     */
    public function sendEmailNotifications($tnid, $rfqid) {
        $nm = new Myshipserv_NotificationManager($this->getDb());
        $nm->sendMatchSupplierIntroduction($tnid, $rfqid);
    }

    /**
     * Creates connection client to the forwarding essm webservice
     *
     * @return  Zend_Http_Client
     */
    private function makeHttpClient() {
        $client = new Zend_Http_Client();
        $client
            ->setUri(Myshipserv_Config::getRfqForwarderUrl())
            ->setConfig(array(
                'maxredirects'  => 0,
                'timeout'       => Myshipserv_Config::getRfqForwarderTimeout()
            ))
            ->setMethod(Zend_Http_Client::GET)
        ;

        return $client;
    }

    /**
     * Normalises and validates given supplier information
     *
     * @author  Yuriy Akopov
     * @date    2014-05-27
     * @story   S10313
     *
     * @param   Shipserv_Buyer              $buyerOrg
     * @param   Shipserv_Supplier|int|array $suppliers
     * @param   bool                        $abortWithException
     *
     * @return  Shipserv_Supplier[]
     * @throws Shipserv_Match_Forwarder_Exception
     */
    protected function validateSuppliers(Shipserv_Buyer $buyerOrg, $suppliers, $abortWithException = true) {
        if (!is_array($suppliers)) {
            $suppliers = array($suppliers);
        }

        $validatedSuppliers = array();
        $supplierList = new Shipserv_Buyer_SupplierList($buyerOrg);

        foreach ($suppliers as $supplierInfo) {
            // instantiate a supplier if it hasn't been yet
            if (filter_var($supplierInfo, FILTER_VALIDATE_INT)) {
                $supplier = Shipserv_Supplier::getInstanceById($supplierInfo);

            } else if ($supplierInfo instanceof Shipserv_Supplier) {
                $supplier = $supplierInfo;

            } else {
                if ($abortWithException) {
                    throw new Shipserv_Match_Forwarder_Exception("Invalid supplier provided to forward an RFQ to");
                }

                continue;
            }

            if (strlen($supplier->tnid) === 0) {
                if ($abortWithException) {
                    throw new Shipserv_Match_Forwarder_Exception("Supplier provided to forward RFQ to is not active or doesn't exist");
                }

                continue;
            }

            if (!$supplierList->validateSupplier($supplier)) {
                if ($abortWithException) {
                    throw new Shipserv_Match_Forwarder_Exception("Supplier " . $supplier->tnid . " is rejected by buyer blacklist settings");
                }
                
                continue;
            }

            $validatedSuppliers[] = $supplier;
        }

        return $validatedSuppliers;
    }

    /**
     * Receives RFQ and returns match proxy RFQ from the same event, or turns one of the event RFQs into a match one if none found
     *
     * @param   Shipserv_Rfq    $rfq
     * @param   Shipserv_User   $user
     * @param   bool            $createIfNotFound
     *
     * @return  Shipserv_Rfq
     * @throws  Shipserv_Match_Forwarder_Exception
     */
    public static function getMatchProxyRfq(Shipserv_Rfq $rfq, Shipserv_User $user, $createIfNotFound = true) {
        // now we need to check if any of RFQs in the same event with the current one has been already sent to the match proxy
        $matchSupplierId = Myshipserv_Config::getProxyMatchSupplier();
        $eventSuppliers = $rfq->getSuppliers(false);

        $matchRfqIds = array();
        foreach ($eventSuppliers as $supplierInfo) {
            if ($supplierInfo[Shipserv_Rfq::RFQ_SUPPLIERS_BRANCH_ID] == $matchSupplierId) {
                $matchRfqIds[] = $supplierInfo[Shipserv_Rfq::RFQ_SUPPLIERS_RFQ_ID];
            }
        }

        $matchRfqId = null;
        if (!empty($matchRfqIds)) {
            if (in_array($rfq->rfqInternalRefNo, $matchRfqIds)) {
                // if the current RFQ itself is a match RFQ, use it
                $matchRfqId = $rfq->rfqInternalRefNo;
            } else {
                // otherwise use the most recent match RFQ
                $matchRfqId = max($matchRfqIds);
            }
        }

        if (!is_null($matchRfqId)) {
            // if an RFQ sent to a match proxy supplier is found in the event, instantiate and return it
            $matchRfq = Shipserv_Rfq::getInstanceById($matchRfqId);
            return $matchRfq;
        }

        // if we are here, match RFQ wasn't found in the event
        if (!$createIfNotFound) {
            throw new Shipserv_Match_Forwarder_Exception("No match RFQ found in the given RFQ event", $rfq->rfqInternalRefNo);
        }

        // none of the RFQs in the event has been sent to a proxy supplier, so before we forward an RFQ we need to
        // add that proxy supplier to the list of buyer selected ones for the given RFQ
        // (which is a temporary solution - if done properly, we would probably need to create a new RFQ in the event
        // sent to match proxy only and forward that new RFQ; our API doesn't allow that right now though)

        // make sure we are not adding a new supplier to a already forwarded one
        $rfq = $rfq->resolveMatchForward();

        $supplier = Shipserv_Supplier::getInstanceById(Myshipserv_Config::getProxyMatchSupplier(), '', true);
        $rfq->addRecipientSupplier($supplier, $user);
        /*
        $db = Shipserv_Helper_Database::getDb();
        $db->beginTransaction();

        $seqNo = $db->fetchOne('SELECT rqr_id.nextval FROM dual');

        $db->insert('RFQ_QUOTE_RELATION', array(
            'RQR_SEQ_NO'                => $seqNo,
            'RQR_RFQ_INTERNAL_REF_NO'   => $rfq->rfqInternalRefNo,
            'RQR_SS_RFQ_TRACKING_NO'    => $rfq->rfqSsTrackingNo,
            'RQR_BYB_BRANCH_CODE'       => $rfq->rfqBybBranchCode,
            'RQR_BYB_BYO_ORG_CODE'      => $rfq->rfqBybByoOrgCode,
            'RQR_RFQ_STS'               => 'NEW',
            'RQR_SPB_BRANCH_CODE'       => Shipserv_Match_Settings::get(Shipserv_Match_Settings::SUPPLIER_PROXY_ID),
            'RQR_SPB_SUP_ORG_CODE'      => Shipserv_Match_Settings::get(Shipserv_Match_Settings::SUPPLIER_PROXY_ORG_ID),
            'RQR_CREATED_BY'            => $user->userId,
            'RQR_UPDATED_BY'            => $user->userId,
            'RQR_CREATED_DATE'          => new Zend_Db_Expr('SYSDATE'),
            'RQR_UPDATED_DATE'          => new Zend_Db_Expr('SYSDATE'),
            'RQR_SUBMITTED_DATE'        => new Zend_Db_Expr('SYSDATE'),
            'RQR_MTML_ACKNOWLEDGED'     => 0
        ));

        $db->update(
            Shipserv_Rfq::TABLE_NAME,
            array(
                'rfq_vendor_count' => new Zend_Db_Expr('(rfq_vendor_count + 1)')    // since we have added a new supplier
            ),
            $db->quoteInto(Shipserv_Rfq::COL_ID . ' = ?', $rfq->rfqInternalRefNo)
        );

        $db->commit();
        */

        // now this RFQ is a match one and it can be forwarded
        return $rfq;
    }

    /**
     * Unlike legacy forwardRfq which assumes that RFQ being forwarded has been sent to match proxy, this one can be used
     * with any RFQ - match proxy would be created on the fly if it doesn't exists yet.
     *
     * @param   Shipserv_Buyer                  $buyerOrg
     * @param   Shipserv_User                   $user
     * @param   Shipserv_Rfq                    $rfq
     * @param   Shipserv_Supplier[]|array|int   $suppliers
     * @param   Myshipserv_Logger_File|null     $logger
     * @param   array|null                      $scores
     * @param   array|null                      $comments
     *
     * @return  array
     * @throws  Shipserv_Match_Forwarder_Exception
     */
    public function forwardRfqEvent(Shipserv_Buyer $buyerOrg, Shipserv_User $user, Shipserv_Rfq $rfq, $suppliers, Myshipserv_Logger_File $logger = null, array $scores = null, array $comments = null) {
        $db = $this->getDb();

        // validate and normalise suppliers to forward the given RFQ to
        $suppliers = $this->validateSuppliers($buyerOrg, $suppliers);
        if (empty($suppliers)) {
            throw new Shipserv_Match_Forwarder_Exception("No suppliers provided to forward to");
        }

        // normalise unnecessary forwarding meta data
        if (count($comments)) {
            foreach ($comments as $index => $value) {
                $comments[$index] = Shipserv_Object::truncateStringDbValue($value, 'match_rfq_processed_list', 'mrp_comments', $db);
            }
        }

        if (count($scores)) {
            foreach ($scores as $index => $score) {
                $scores[$index] = (int) $score;
            }
        }

        // find or create a match forward RFQ in the same event with the given one
        $rfq = self::getMatchProxyRfq($rfq, $user);

        // check if the buyer has requested no more than N suppliers allowed for match
        // disabled for now as the process is human operated and we trust the operators (and sometimes they might need to override buyer settings)
        /*
        $settings = new Shipserv_Match_Buyer_Settings($rfq);
        $maxMatchSuppliers = $settings->getMaxMatchSupplierCount();
        if (!is_null($maxMatchSuppliers)) {
            $suppliers = $rfq->getSuppliers();
            $matchSuppliers = 0;
            foreach ($suppliers as $supplierInfo) {
                if ($supplierInfo[Shipserv_Rfq::RFQ_SUPPLIERS_FROM_MATCH]) {
                    $matchSuppliers++;
                }
            }

            if ((count($suppliers) + $matchSuppliers) > $maxMatchSuppliers) {
                throw new Shipserv_Match_Forwarder_Exception("Max number of match suppliers allowed by buyer is " . $maxMatchSuppliers . ", forwardign aborted");
            }
        }
        */

        $forwardedIds = array();
        foreach ($suppliers as $index => $supplier) {
            $supplierId = (int) $supplier->tnid;

            $db->beginTransaction();

            // marking the match transaction RFQ as processed (so it won't appear in /match/inbox queue to be processed by ShipServ staff)
            $db->insert('match_rfq_processed_list', array(
                'mrp_processed_date'        => new Zend_Db_Expr('SYSDATE'),
                'mrp_rfq_internal_ref_no'   => $rfq->rfqInternalRefNo,
                'mrp_tnid'                  => $supplierId,
                'mrp_score'                 => (count($scores) ? $scores[$index] : 0),
                'mrp_comments'              => (count($comments) ? $comments[$index] : "Forwarded by user")
            ));

            // call remote webservice to send the RFQ out
            try {
                $forwardRfqId = $this->forwardRFQ($rfq->rfqInternalRefNo, $supplierId);

                if ($logger) {
                    $logger->log("Forwarded RFQ " . $rfq->rfqInternalRefNo . " to supplier " . $supplierId . " response was " . $forwardRfqId);
                }
                $forwardedIds[$supplierId] = $forwardRfqId;

                if ($logger) {
                    $logger->log("Sending match email notification: RFQ " . $rfq->rfqInternalRefNo . ", supplier " . $supplierId);
                }
                $this->sendEmailNotifications($supplierId, $forwardRfqId);
                if ($logger) {
                    $logger->log("Sent match email notification: RFQ " . $rfq->rfqInternalRefNo . ", supplier " . $supplierId);
                }

                $db->commit();

            } catch (Shipserv_Match_Forwarder_Exception $e) {
                if ($logger) {
                    $logger->log("Failed to forward RFQ " . $rfq->rfqInternalRefNo . " to supplier " . $supplierId);
                }
                $forwardedIds[$supplierId] = false;
                $db->rollBack();
            }
        }

        return $forwardedIds;
    }
}

