<?php

class Myshipserv_NotificationManager_Email_QuoteToBuyer extends Myshipserv_NotificationManager_Email_Abstract
{

    protected $subject = "";
    protected $isSalesforce = false;

    /**
     * @var null|Shipserv_Quote
     */
    protected $quote = null;

    /**
     * @var null|Shipserv_Rfq
     */
    protected $rfq = null;

    /**
     * @var null|Shipserv_Buyer
     */
    protected $buyer = null;

	/**
	 * @author  Yuriy Akopov
	 * @date    2016-09-07
	 * @story   S17912
	 *
	 * @var bool
	 */
	protected $isAutomatch = null;

    /**
     * @param   int     $quoteId
     * @param   string  $buyerId
     *
     * @throws  Myshipserv_NotificationManager_Email_Exception_AlreadySent
     * @throws  Myshipserv_NotificationManager_Email_Exception_TooExpensive
     */
    public function __construct($quoteId, $buyerId = '')
    {
        $this->db = $this->getDb();

        // enable SMTP relay through jangoSMTP (or any other)
        $this->enableSMTPRelay = true;

        if (empty($buyerId) || $buyerId = '') {
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

        $this->quote = Shipserv_Quote::getInstanceById($quoteId);

        // added by Yuriy Akopov on 2014-06-13 as an additional safety measure
        if (!isset($_GET['send']) && $this->quote->wasEmailedAsMatch()) {
        	throw new Myshipserv_NotificationManager_Email_Exception_AlreadySent("Quote " . $this->quote->qotInternalRefNo . " notification email has already been set to buyer");
        }
        // changes by Yuriy Akopov end

        $this->rfq = $this->quote->getRfq();
        if (!empty($buyerId)) {
            $this->buyer = Shipserv_Buyer::getBuyerBranchInstanceById($buyerId);
        }

        // added by Yuriy Akopov on 2014-06-12
        if ($this->quote->isAutoMatchQuote(true)) { // boolean parameter added on 2015-08-11 to apply the check only to 'pure Automatch' events
            // check if the buyer has requested cheap autoquotes only
            $sender = $this->rfq->getOriginalSender();
            $originalRfq = $this->rfq->resolveMatchForward();
            $settings = new Shipserv_Match_Buyer_Settings($sender, $originalRfq->rfqBybBranchCode);

            if ($settings->isAutomatchCheapQuotesMode()) {
                // if cheap quotes requested, check if this one if cheap enough
                if (!Shipserv_Match_Auto_Manager::checkAutoMatchQuoteCost($this->quote)) {
                    // auto match quote is not cheap enough to trigger a notification email
                    throw new Myshipserv_NotificationManager_Email_Exception_TooExpensive("Auto match quote " . $this->quote->qotInternalRefNo . " is not cheap enough for an email to be sent");
                }
            }
        }
        // changes by Yuriy Akopov end
    }

    /**
     * Returns email alert config records for match quotes of the buyer branch.
     *
     * Moved to a separated function from getRecipients()
     *
     * @author  Yuriy Akopov
     * @data    2017-01-17
     * @story   S18892
     *
     * @return array
     */
    protected function getEmailAlertConfigForBuyer()
    {
        $emailAlertSql = "
          SELECT
            *
          FROM
            email_alert_config
          WHERE
            eac_branch_code = :buyerTnid
            AND eac_alert_type = 'QOT_MCH'
        ";
        $configRow = $this->getDb()->fetchRow($emailAlertSql, array('buyerTnid' => $this->buyer->bybBranchCode));

        return $configRow;
    }

    /**
     * Returns recipient users with Spend Management privilege in their profile
     *
     * Placing it here and not in Shipserv_Oracle_PagesUserCompany because it's for one use case only
     * and this class contains a lot of SQL already so it's already mixing concepts
     *
     * @author  Yuriy Akopov
     * @story   S18892
     * @date    2017-01-17
     */
    protected function getSpendManagementRecipients()
    {
        $select = new Zend_Db_Select($this->getDb());
        $select
            ->from(
                array('psu' => Shipserv_User::TABLE_NAME),
                array(
                    'EMAIL'      => 'psu.' . Shipserv_User::COL_EMAIL,
                    'FIRST_NAME' => 'psu.' . Shipserv_User::COL_NAME_FIRST,
                    'LAST_NAME'  => 'psu.' . Shipserv_User::COL_NAME_LAST
                )
            )
            ->join(
                array('puc' => 'pages_user_company'),
                'psu.' . Shipserv_User::COL_ID . ' = puc.puc_psu_id',
                array()
            )
            ->where('puc.puc_company_type = ?', 'BYB')
            ->where('puc.puc_company_id = ?', $this->buyer->bybBranchCode)
            ->where('puc.puc_match_notify = ?', 1)      // has the privilege of Spend Benchmarking notifications
            ->where('puc.puc_status = ?', Shipserv_Oracle_PagesUserCompany::STATUS_ACTIVE)
            ->distinct();

        $rows = $select->getAdapter()->fetchAll($select);
        $recipients = array();

        foreach ($rows as $row) {
            $nameValues = array();
            $nameFields = array(
                'FIRST_NAME',
                'LAST_NAME'
            );

            foreach ($nameFields as $field) {
                $nameValue = trim($row[$field]);

                if (strlen($nameValue) > 0) {
                    $nameValues[] = $nameValue;
                }
            }

            if (empty($nameValues)) {
                $toName = $this->buyer->bybName;
            } else {
                $toName = implode(" ", $nameValues);
            }

            $recipients[] = array(
                'name'  => $toName,
                'email' => $row['EMAIL']
            );
        }

        if (empty($recipients)) {
			
			//BUY-327 Adding Buyer Email address, skip RFQ header email address        	

        	$bybEmailAddress = trim($this->buyer->bybEmailAddress);
        	
        	if (filter_var($bybEmailAddress, FILTER_VALIDATE_EMAIL) !== false) {
        		$recipients[] = array(
        				'name'  => "",
        				'email' => $bybEmailAddress
        		);
        	}
        	
        	if (empty($recipients)) {
        		return array();
        	}
 
        }

        return array(
            'TO' => $recipients
        );
    }

    /**
     * The only workflow for recipients that existed before S18892 directly in getRecipients()
     *
     * @author  Yuriy Akopov
     * @story   S18892
     * @date    2017-01-17
     *
     * @return  array
     */
    protected function getSpotSourceRecipients()
    {
        $configRow = $this->getEmailAlertConfigForBuyer();

        if (empty($configRow)) {
            try {
                $this->getDb()->insert(
                    'email_alert_config',
                    array(
                        'EAC_BRANCH_CODE'       => $this->buyer->bybBranchCode,
                        'EAC_ALERT_TYPE'        => 'QOT_MCH',
                        'EAC_BRANCH_RCP_TYPE'   => 'TO',
                        'EAC_CONTACT_RCP_TYPE'  => 'TO',
                        'EAC_EMAIL_FORMAT'      => 'STD'
                    )
                );

            } catch(Exception $e) {
                // Not sure why this is here - we cannot violate a constraint because we have just checked there is no
                // record. Perhaps this block should be removed. (Yuriy)
            }

            $configRow = $this->getEmailAlertConfigForBuyer();
        }

        $recipientStorage = array();

        if ($configRow['EAC_EMAIL_FORMAT'] != 'NIL') {
            // getting the email address for the rfq sender
            if (
                ($configRow['EAC_CONTACT_RCP_TYPE'] != 'NIL') and
                ($configRow['EAC_CONTACT_RCP_TYPE'] != "")
            ) {
                $rfqEmailAddress = trim($this->rfq->rfqEmailAddress);

                if (filter_var($rfqEmailAddress, FILTER_VALIDATE_EMAIL) !== false) {
                    $recipientStorage[$configRow['EAC_CONTACT_RCP_TYPE']][] = array(
                        'name'  => "",
                        'email' => $rfqEmailAddress
                    );
                }
            }

            // getting the email address from the buyer branch table
            if (
                ($configRow['EAC_BRANCH_RCP_TYPE'] != 'NIL') and
                ($configRow['EAC_BRANCH_RCP_TYPE'] != "")
            ) {
                $bybEmailAddress = trim($this->buyer->bybEmailAddress);

                if (filter_var($bybEmailAddress, FILTER_VALIDATE_EMAIL) !== false) {
                    $recipientStorage[$configRow['EAC_BRANCH_RCP_TYPE']][] = array(
                        'name'  => "",
                        'email' => $bybEmailAddress
                    );
                }
            }

            // checking if any additional emails are required
            if (
                ($configRow['EAC_TO'] != 'NIL') and
                (filter_var($configRow['EAC_TO'], FILTER_VALIDATE_EMAIL) !== false)
            ) {
                $recipientStorage['TO'][] = array(
                    'name'  => "",
                    'email' => $configRow['EAC_TO']
                );
            }

            // CC
            if (
                ($configRow['EAC_CC'] != 'NIL') and
                (filter_var($configRow['EAC_CC'], FILTER_VALIDATE_EMAIL) !== false)
            ) {
                $recipientStorage['CC'][] = array(
                    'name'  => "",
                    'email' =>$configRow['EAC_CC']
                );
            }

            // BCC
            if (
                ($configRow['EAC_BCC'] != 'NIL') and
                (filter_var($configRow['EAC_BCC'], FILTER_VALIDATE_EMAIL) !== false)
            ) {
                $recipientStorage['BCC'][] = array(
                    'name'  => "",
                    'email' => $configRow['EAC_BCC']
                );
            }
        }

        if (Myshipserv_Config::isInProduction()) {
            $recipientStorage['BCC'][] = array(
                'name'  => "",
                'email' => "match.monitor.quote.email@shipserv.com"
            );
        }

        return $recipientStorage;
    }

    /**
     * Returns recipients of Match quote notification email
     *
     * @return Myshipserv_NotificationManager_Recipient
     */
    public function getRecipients()
    {
        $recipients = array();

        if (($this->buyer->bybBranchCode != Myshipserv_Config::getProxyPagesBuyer()) and $this->isAutomatchQuote()) {
            $recipients = $this->getSpendManagementRecipients();
        }

        if (empty($recipients)) {
            $recipients = $this->getSpotSourceRecipients();
        }

        $recipient = new Myshipserv_NotificationManager_Recipient;
        $recipient->list = $recipients;

        return $recipient;
    }

	/**
	 * Returns true if the quote email is about is an Automatch quote
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-09-07
	 * @story   S17912
	 *
	 * @return  bool
	 */
    protected function isAutomatchQuote()
    {
    	if (is_null($this->isAutomatch)) {
		    $this->isAutomatch = $this->quote->getRfq()->isAutoMatchEvent();
	    }

	    return $this->isAutomatch;
    }

	/**
	 * Returns true if the quote email is about is an Automatch quote
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-09-07
	 * @story   S17912
	 *
	 * @return string
	 */
    public function getQuoteTypeLabel()
    {
        if ($this->isAutomatchQuote()) {
	        return "AutoSource";
        }

        return "SpotSource";
    }

    /**
     * @return string
     */
    public function getSubject()
    {
        $subject =
	        "ShipServ " . $this->getQuoteTypeLabel() .
	        " - Quote from " . $this->quote->getSupplier()->name .
	        " for RFQ: " . $this->rfq->rfqRefNo .
	        (($this->quote->getRfq()->rfqVesselName!="")?" - Vessel: " . $this->quote->getRfq()->rfqVesselName . "":"")
        ;

        if ($this->enableSMTPRelay) {
            // group name on JANGOSMTP
            $subject .= "{Match Quote Buyer Notification}";
        }

        return $subject;
    }

    /**
     * @return array
     * @throws Myshipserv_NotificationManager_Email_Exception
     */
    public function getBody()
    {
        $view = $this->getView();
        $recipients = $this->getRecipients();
        $db = $this->getDb();

        // query replaced with a simpler event-hash based version by Yuriy Akopov on 2014-11-21, DE5483
        $sqlEventQuotes = "
        SELECT
            tbl.*
            , row_number() OVER (ORDER BY tbl.Completeness DESC, tbl.NORMALISEDTOTAL ASC NULLS LAST) AS cheapest_rank
            , (
                CASE
                  WHEN
                    qot_byb_branch_code != 11107
                    AND NOT EXISTS (SELECT NULL FROM MATCH_IMPORTED_RFQ_PROC_LIST WHERE MIR_RFQ_INTERNAL_REF_NO=tbl.qot_rfq_internal_ref_no AND MIR_SPB_BRANCH_CODE=tbl.qot_spb_branch_code)
                  THEN
                    row_number() OVER (ORDER BY tbl.Completeness DESC, tbl.NORMALISEDTOTAL ASC NULLS LAST)
                ELSE
                  null
                END
            ) AS most_complete_non_match_rank
        FROM
        (
            SELECT
                qot.*,
                TO_CHAR(qot.qot_submitted_date, 'YYYY-MM-DD HH24:MI:SS') AS QUOTEDATE,
                bsb.bsb_id AS WHITELISTED,
                bbs.bbs_id AS WHITELISTDISABLED,
                ROUND(qot.qot_total_cost / cur.curr_exchange_rate, 2) AS NORMALISEDTOTAL,
                spb.spb_name,
                rfq.rfq_ref_no,
                rfq.rfq_byb_branch_code,

                CASE
                  WHEN rfq.rfq_sourcerfq_internal_no IS NOT NULL THEN (
                    SELECT
                      rfq_orig.rfq_byb_branch_code
                    FROM
                      request_for_quote rfq_orig
                    WHERE
                      rfq_orig.rfq_internal_ref_no = rfq.rfq_sourcerfq_internal_no
                  )
                ELSE rfq.rfq_byb_branch_code
                END AS originalbuyer,

                LEAST(100, (
                  SELECT
                    ROUND((qot.qot_line_item_count - (
                      SELECT
                        COUNT(*)
                      FROM
                        rfq_quote_line_item_change rql,
                        quote_line_item ql
                      WHERE
                        rqlc_qot_internal_ref_no = qot.qot_internal_ref_no
                        AND rql.rqlc_line_item_no = ql.qli_line_item_number
                        AND ql.qli_qot_internal_ref_no = qot.qot_internal_ref_no
                        AND (
                          rqlc_line_item_status = 'DEC'
                          OR ql.qli_total_line_item_cost = 0
                        )
                    )) / qot.qot_line_item_count, 2)
                  FROM
                    DUAL
                ) * 100) AS Completeness,

                row_number() OVER (PARTITION BY qot.qot_spb_branch_code ORDER BY qot.qot_internal_ref_no DESC NULLS LAST) AS quote_row_no
            FROM
              quote qot
              JOIN request_for_quote rfq ON
                 qot.qot_rfq_internal_ref_no = rfq.rfq_internal_ref_no
              JOIN request_for_quote rfq_src ON
                rfq_src.rfq_event_hash = rfq.rfq_event_hash
              JOIN supplier_branch spb ON
                spb.spb_branch_code = qot.qot_spb_branch_code
              JOIN currency cur ON
                cur.curr_code = qot.qot_currency
              LEFT JOIN match_imported_rfq_proc_list mir ON
                mir.mir_qot_internal_ref_no = qot.qot_internal_ref_no
                AND mir.mir_qot_imported = 'Y'
                AND mir.mir_processed = 'Y'
              LEFT JOIN buyer_supplier_blacklist bsb ON
                bsb.bsb_byo_org_code = :buyerOrgId
                AND bsb.bsb_spb_branch_code = qot.qot_spb_branch_code
                AND bsb.bsb_type = :whitelistType
              LEFT JOIN buyer_blacklist_status bbs ON
                bbs.bbs_byo_org_code = bsb.bsb_byo_org_code
                AND bbs.bbs_type = bsb.bsb_type
                AND bbs.bbs_enabled <> 1
            WHERE
              rfq_src.rfq_internal_ref_no = :rfqId
              AND mir.mir_id IS NULL
              AND qot.qot_quote_sts = 'SUB'
            ORDER BY
              NormalisedTotal DESC
        ) tbl
        ";

        $rfq = $this->quote->getRfq()->resolveMatchForward();
        $buyerOrg = $rfq->getBuyer();

        $timeStart = microtime(true);
        $params = array(
            'rfqId'         => $rfq->rfqInternalRefNo,
            'buyerOrgId'    => $buyerOrg->id,
            'whitelistType' => Shipserv_Buyer_SupplierList::TYPE_WHITELIST
        );
        $quotes = $db->fetchAll($sqlEventQuotes, $params);

        $sqlForPotentialSaving = "
            WITH
            all_quotes_base AS (
                $sqlEventQuotes
            )
            ,
            all_quotes AS (
              SELECT
                (
                  SELECT a.normalisedtotal
                  FROM all_quotes_base a
                  WHERE
                      (
                        A.most_complete_non_match_rank=(
                          SELECT MIN( b.most_complete_non_match_rank )
                          FROM all_quotes_base b
                        )

                        OR A.most_complete_non_match_rank=(
                          SELECT MIN( b.most_complete_non_match_rank )
                          FROM all_quotes_base b
                        ) + 1
                      )
                      AND rownum=1

                ) cheapest_qot_to_compare
                ,aq.*
              FROM
                all_quotes_base aq
            )
            SELECT
              tbl.*
              ,
              (
                  CASE WHEN completeness = 100 THEN
                    (SELECT MIN(aq2.cheapest_qot_to_compare) FROM all_quotes aq2) - tbl.normalisedtotal
                  ELSE
                    null
                  END
              ) potential_saving
            FROM
            (
                SELECT
                  qot_spb_branch_code || '-' || qot_internal_ref_no AS PK
                  , normalisedtotal
                  , completeness
                FROM
                  all_quotes
                WHERE
                  rfq_byb_branch_code = 11107
            ) tbl
        ";

        if ($_GET['terminated'] == 1) {
            echo $sqlForPotentialSaving;
            print_r($params);
            die();
        }

        $matchQuotePotentialSaving = array();
        foreach ($db->fetchAll($sqlForPotentialSaving, $params) as $row) {
            $matchQuotePotentialSaving[$row['PK']] = $row['POTENTIAL_SAVING'];
        }

        $view->matchQuotePotentialSaving = $matchQuotePotentialSaving;

        $timeElapsed = microtime(true) - $timeStart;

        //var_dump($timeElapsed);
        $timeElapsed = 0;

        // added by Yuriy Akopov on 2013-09-12 - analysing the loaded quotes for possible duplicates (see the comment above the query)
        $matchQuoteIds = array();
        foreach ($quotes as $key => $qot) {
            // added by Yuriy Akopov on 2014-06-12 to exclude auto match quotes that were not emailed (were not cheap enough)
            $quote = Shipserv_Quote::getInstanceById($qot[Shipserv_Quote::COL_ID]);

            if ($quote->isAutoMatchQuote() and (!$quote->wasEmailedAsMatch()) and ($quote->qotInternalRefNo != $this->quote->qotInternalRefNo)) {
                // for auto match events only include match quotes which have been already emailed about
                unset($quotes[$key]);
                continue;
            }
            // changes by Yuriy Akopov end

            // collecting supplier IDs that have match quotes in the loaded set
            //if ($qot[Shipserv_Quote::COL_BUYER_ID] == $matchBuyerId) {
            if ($quote->isMatchQuote()) {
                $matchQuoteIds[] = $quote->qotInternalRefNo;
            }

            if ($qot['QUOTE_ROW_NO'] != '1') {
                if ($qot[Shipserv_Quote::COL_ID] == $this->quote->qotInternalRefNo) {
                    // added by Yuriy Akopov on 2014-04-07
                    // this use case mean that the quote we're preparing email for is a duplicate one
                    // here we assume that we should not send an email out because one has been already sent out for the original quote
                    throw new Myshipserv_NotificationManager_Email_Exception("Duplicate quote detected, email probably already sent out");
                }

                unset($quotes[$key]);  // don't need to display quotes from the same supplier other than most recent one
                continue;
            }

            $supplier = $quote->getSupplier();
            if ($supplier->tnid) {
                $timeStart = microtime(true);
                $quotes[$key]['LASTORDERDATE'] = $supplier->getLastOrderDate($rfq->rfqBybBranchCode);
                $timeElapsed += microtime(true) - $timeStart;
            }

            $quotes[$key]['QUOTEDATE'] = new DateTime($qot['QUOTEDATE']);
            $quotes[$key]['QUOTE_URL'] = $quote->getUrl();
            // added by Yuriy Akopov on 2015-10-01, story 14449
            $quotes[$key]['QUOTE_GENUINE_MESSAGE'] = $quote->getGenuineInfo();
        }
        // changes by Yuriy Akopov end

        // var_dump($timeElapsed);

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

        $userEndorsementPrivacy = $endorsementsAdapter->showBuyers($this->quote->getSupplier()->tnid, $endorseeIdsArray, true);

        //get supplier's privacy policy
        $dbPriv = new Shipserv_Oracle_EndorsementPrivacy($db);
        $sPrivacy = $dbPriv->getSupplierPrivacy($this->quote->getSupplier()->tnid);

        // give the view the supplier data
        $view->quote = $this->quote;

        // added by Yuriy Akopov on 2013-12-06, S8971
        $view->attachments = array();

        // quote attachments
        $attachments = Application_Model_Transaction::getTransactionAttachments($this->quote);
        if (!empty($attachments)) {
            $view->attachments['QOT'] = $attachments;
        }

        // RFQ attachments
        $rfq = $this->quote->getRfq();
        $attachments = Application_Model_Transaction::getTransactionAttachments($rfq);
        if (!empty($attachments)) {
            $view->attachments['RFQ'] = $attachments;
        }

        // enquiry attachments
        if (($enquiry = $rfq->getPagesEnquiry()) !== false) {
            $attachments = Application_Model_Transaction::getTransactionAttachments($enquiry);
            $view->attachments['PIN'] = $attachments;
        }
        // S8971 changes by Yuriy Akopov end

        $view->quotes = $quotes;
        $view->matchQuoteIds = $matchQuoteIds;
        $view->hostname = $_SERVER["HTTP_HOST"];
        $view->endorseeInfo = $endorseeInfo;
        // $view->supplier = $supplier;
        $view->endorsements = $endorsements;
        $view->reviews = Shipserv_Review::fetchSummary($this->quote->getSupplier()->tnid);
        $view->userEndorsementsPrivacy = $userEndorsementPrivacy;
        $view->supplierPrivacy = $sPrivacy->getGlobalAnonPolicy();
        $view->sourceRfqId = $rfq->rfqInternalRefNo;

	    // S17912
	    $view->isAutomatch = $this->isAutomatchQuote();
	    $view->quoteTypeLabel = $this->getQuoteTypeLabel();

        $body = $view->render('email/ss-match-quote-to-buyer.phtml');

        $emails = array(
            $recipients->getHash() => $body
        );

        return $emails;
    }
}
