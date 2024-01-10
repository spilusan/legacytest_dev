<?php
/**
 * An interface to access keywords and phrases sets for preemptive matching
 * Provides read only access because these sets are not going to change too often an so far they'll be updated manually if needed
 *
 * This is a lazy implementation because at the moment we don't have to edit keywords lists much (it's a one-time operation)
 * So there are no separate ActiveRecord classes for every table here unlike say for stored searches model
 *
 * ActiveRecord implementations, should they be needed later should be implemented by extending stub classes Shipserv_Match_Auto_Component_*
 *
 * @author  Yuriy Akopov
 * @date    2014-05-30
 * @story   S10311
 */
class Shipserv_Match_Auto_Manager
{
    const
        TABLE_SUPPLIER_MATCH = 'SUPPLIER_BRANCH_MATCH'
    ;

    /**
     * Returns select for supplier organisations and branches participating in pre emptive match
     *
     * @return Zend_Db_Select
     */
    protected static function _getSupplierSelect()
    {
        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);

        $select
            ->from(
                array('mso' => Shipserv_Match_Auto_Component_Owner::TABLE_NAME),
                'mso.' . Shipserv_Match_Auto_Component_Owner::COL_OWNER_ID
            )
            ->join(
                array('mss' => Shipserv_Match_Auto_Component_Set::TABLE_NAME),
                'mss.' . Shipserv_Match_Auto_Component_Set::COL_ID . ' = mso.' . Shipserv_Match_Auto_Component_Owner::COL_SET_ID,
                array()
            )
            ->where('mss.' . Shipserv_Match_Auto_Component_Set::COL_ENABLED . ' = ?', 1);

        return $select;
    }

    /**
     * Returns all supplier organisations that have active keyword sets defined for them
     *
     * @param   array|int|null $setId
     *
     * @return  Shipserv_Supplier_Organisation[]
     */
    public static function getSupplierOrgs($setId = null)
    {
        $select = self::_getSupplierSelect();
        $select->where('mso.' . Shipserv_Match_Auto_Component_Owner::COL_OWNER_TYPE . ' = ?', Shipserv_Match_Auto_Component_Owner::OWNER_ORG);

        if (!is_null($setId)) {
            $select->where('mss.' . Shipserv_Match_Auto_Component_Set::COL_ID . ' IN (?)', $setId);
        }

        $rows = $select->getAdapter()->fetchAll($select);

        $supplierOrgs = array();
        foreach ($rows as $row) {
            $supplierOrgs[] = Shipserv_Supplier_Organisation::getInstanceById($row[Shipserv_Match_Auto_Component_Owner::COL_OWNER_ID]);
        }

        return $supplierOrgs;
    }

    /**
     * Returns all supplier branches that have active keyword sets defined for them
     *
     * @param   array|int|null $setId
     *
     * @return Shipserv_Supplier[]
     */
    public static function getSupplierBranches($setId = null)
    {
        $select = self::_getSupplierSelect();
        $select->where('mso.' . Shipserv_Match_Auto_Component_Owner::COL_OWNER_TYPE . ' = ?', Shipserv_Match_Auto_Component_Owner::OWNER_BRANCH);

        if (!is_null($setId)) {
            $select->where('mss.' . Shipserv_Match_Auto_Component_Set::COL_ID . ' IN (?)', $setId);
        }
        $rows = $select->getAdapter()->fetchAll($select);

        $supplierBranches = array();
        foreach ($rows as $row) {
            $supplierBranches[] = Shipserv_Supplier::getInstanceById($row[Shipserv_Match_Auto_Component_Owner::COL_OWNER_ID], '', true);
        }

        return $supplierBranches;
    }

    /**
     * Returns IDs and names of available and enable sets, all of them, or for the provided owner
     *
     * @param   Shipserv_Supplier|Shipserv_Supplier_Organisation $owner
     *
     * @return  array
     * @throws  Exception
     */
    public static function getKeywordSets($owner = null)
    {
        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mss' => Shipserv_Match_Auto_Component_Set::TABLE_NAME),
                array(
                    'mss.' . Shipserv_Match_Auto_Component_Set::COL_ID,
                    'mss.' . Shipserv_Match_Auto_Component_Set::COL_NAME
                )
            )
            ->where('mss.' . Shipserv_Match_Auto_Component_Set::COL_ENABLED . ' = ?', 1);

        if (!is_null($owner)) {
            $select->join(
                array('mso' => Shipserv_Match_Auto_Component_Owner::TABLE_NAME),
                'mso.' . Shipserv_Match_Auto_Component_Owner::COL_SET_ID . ' = mss.' . Shipserv_Match_Auto_Component_Set::COL_ID,
                array()
            );

            if ($owner instanceof Shipserv_Supplier) {
                $select
                    ->where('mso.' . Shipserv_Match_Auto_Component_Owner::COL_OWNER_TYPE . ' = ?', Shipserv_Match_Auto_Component_Owner::OWNER_BRANCH)
                    ->where('mso.' . Shipserv_Match_Auto_Component_Owner::COL_OWNER_ID . ' = ?', $owner->tnid);
            } else if ($owner instanceof Shipserv_Supplier_Organisation) {
                $select
                    ->where('mso.' . Shipserv_Match_Auto_Component_Owner::COL_OWNER_TYPE . ' = ?', Shipserv_Match_Auto_Component_Owner::OWNER_ORG)
                    ->where('mso.' . Shipserv_Match_Auto_Component_Owner::COL_OWNER_ID . ' = ?', $owner->getId());
            } else {
                throw new Exception("Invalid owner supplier, unable to retrieve keyword sets");
            }
        }

        $rows = $db->fetchAll($select);

        $setIds = array();
        foreach ($rows as $row) {
            $setIds[$row[Shipserv_Match_Auto_Component_Set::COL_ID]] = $row[Shipserv_Match_Auto_Component_Set::COL_NAME];
        }

        return $setIds;
    }

    /**
     * Returns a query to get all the keywords for the given set
     *
     * @param   int|array $setId
     *
     * @return  Zend_Db_Select
     */
    public static function getKeywordsSelectForSet($setId)
    {
        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);

        $select
            ->from(
                array('msk' => Shipserv_Match_Auto_Component_Keyword::TABLE_NAME),
                'msk.' . Shipserv_Match_Auto_Component_Keyword::COL_KEYWORD
            )
            ->where('msk.' . Shipserv_Match_Auto_Component_Keyword::COL_SET_ID . ' IN (?)', $setId);

        return $select;
    }

    /**
     * Marks given RFQ as matching the set given
     *
     * @param   Shipserv_Rfq $rfq
     * @param   int $setId
     *
     * @return  bool
     */
    public static function markAutoMatchedRfq(Shipserv_Rfq $rfq, $setId)
    {
        $rfq = $rfq->resolveMatchForward();
        $autoMatchBuyers = Shipserv_Match_Buyer_Settings::getAutoMatchParticipants();

        // first let's perform a quick check against RFQ raw fields (not deriving the original sender which is an expensive operation)
        if (!in_array($rfq->rfqBybByoOrgCode, $autoMatchBuyers)) {
            // quick check didn't succeed, let's request the original sender and compare again
            try {
                $sender = $rfq->getOriginalSender();
            } catch (Exception $e) {
                // @todo: some RFQs are failed by our signature function but it is okay to ignore such RFQs here
                return false;
            }

            if (!($sender instanceof Shipserv_Buyer)) {
                // only buyer can take part in automatch so far
                return false;
            }

            if (!array_key_exists($sender->id, $autoMatchBuyers)) {
                // buyer organisation does not take part in automatch
                return false;
            }

            $rfqBuyerOrgId = (int)$sender->id;
        } else {
            $rfqBuyerOrgId = $rfq->rfqBybByoOrgCode;
        }

        // check the branch as well
        if (!in_array($rfq->rfqBybBranchCode, $autoMatchBuyers[$rfqBuyerOrgId])) {
            return false;
        }

        // if we are here, RFQ supplied is eligible for automatch (its buyer has agreed to participate)

        $db = Shipserv_Helper_Database::getDb();

        try {
            $db->insert(
                Shipserv_Match_Auto_Component_Event::TABLE_NAME,
                array(
                    Shipserv_Match_Auto_Component_Event::COL_RFQ_EVENT => new Zend_Db_Expr($db->quoteInto('HEXTORAW(?)', $rfq->rfqEventHash)),
                    Shipserv_Match_Auto_Component_Event::COL_SET_ID => $setId
                )
            );

            Shipserv_Match_Component_Search::eraseOlderSearchesEvent($rfq);

        } catch (Exception $e) {
            // this event is already matched to the given set
            return false;
        }

        return true;
    }

    /**
     * A helper function to convert database rows with keywords into a plain array of values
     *
     * @param   array $keywordRows
     *
     * @return  array
     */
    public static function flattenKeywordRows($keywordRows)
    {
        $keywords = array();
        foreach ($keywordRows as $row) {
            $keywords[] = $row[Shipserv_Match_Auto_Component_Keyword::COL_KEYWORD];
        }

        return $keywords;
    }

    /**
     * Returns the list of supplier branches the given set belongs to
     * (Use case - and RFQ matched a set, which suppliers should be displayed then?)
     *
     * @param   int $setId
     * @param   bool $idsOnly
     *
     * @return  Shipserv_Supplier[]|array
     */
    public static function getSetOwnerBranches($setId, $idsOnly = false)
    {
        $supplierIds = array();

        $supplierOrgs = self::getSupplierOrgs($setId);
        foreach ($supplierOrgs as $org) {
            $supplierIds = array_merge($supplierIds, $org->getBranches(true));
        }

        $supplierBranches = self::getSupplierBranches($setId);
        foreach ($supplierBranches as $branch) {
            $supplierIds[] = $branch->tnid;
        }

        $supplierIds = array_unique($supplierIds);

        if ($idsOnly) {
            return $supplierIds;
        }

        $suppliers = array();
        foreach ($supplierIds as $tnid) {
            $suppliers[] = Shipserv_Supplier::getInstanceById($tnid, '', true);
        }

        return $suppliers;
    }

    /**
     * Returns the date the given RFQ event has first automatched on, if ever
     *
     * @author  Yuriy Akopov
     * @date    2015-08-11
     *
     * @param   string $eventHash
     * @param   bool $enabledOnly
     *
     * @return  DateTime
     */
    public static function getAutomatchedDate($eventHash, $enabledOnly = false)
    {
        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('msr' => Shipserv_Match_Auto_Component_Event::TABLE_NAME),
                new Zend_Db_Expr('TO_CHAR(MIN(msr.' . Shipserv_Match_Auto_Component_Event::COL_DATE . "), 'YYYY-MM-DD HH24:MI:SS')")
            )
            ->where('msr.' . Shipserv_Match_Auto_Component_Event::COL_RFQ_EVENT . ' = HEXTORAW(?)', $eventHash);

        if ($enabledOnly) {
            $select
                ->join(
                    array('mss' => Shipserv_Match_Auto_Component_Set::TABLE_NAME),
                    'mss.' . Shipserv_Match_Auto_Component_Set::COL_ID . ' = msr.' . Shipserv_Match_Auto_Component_Event::COL_SET_ID,
                    array()
                )
                ->where('mss.' . Shipserv_Match_Auto_Component_Set::COL_ENABLED . ' = ?', 1);
        }

        $dateStr = $db->fetchOne($select);

        if (strlen($dateStr) === 0) {
            return null;
        }

        return new DateTime($dateStr);
    }

    /**
     * Returns keyword sets an RFQ event given has matched
     *
     * @param   string $eventHash
     * @param   bool $enabledOnly
     *
     * @return  array
     */
    public static function getMatchedKeywordSets($eventHash, $enabledOnly = false)
    {
        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('msr' => Shipserv_Match_Auto_Component_Event::TABLE_NAME),
                'msr.' . Shipserv_Match_Auto_Component_Event::COL_SET_ID
            )
            ->where('msr.' . Shipserv_Match_Auto_Component_Event::COL_RFQ_EVENT . ' = HEXTORAW(?)', $eventHash);

        if ($enabledOnly) {
            $select
                ->join(
                    array('mss' => Shipserv_Match_Auto_Component_Set::TABLE_NAME),
                    'mss.' . Shipserv_Match_Auto_Component_Set::COL_ID . ' = msr.' . Shipserv_Match_Auto_Component_Event::COL_SET_ID,
                    array()
                )
                ->where('mss.' . Shipserv_Match_Auto_Component_Set::COL_ENABLED . ' = ?', 1);
        }

        $sets = $db->fetchCol($select);

        return $sets;
    }

    /**
     * Returns true if the quote given is an automatch one and is cheaper than buyer selected quotes
     * Returns false if it is not the cheapest one
     *
     * @author  Yuriy Akopov
     * @date    2014-06-11
     * @story   S10311
     *
     * @param   Shipserv_Quote|int $quote
     *
     * @return  bool|null
     * @throws  Shipserv_Match_Auto_Exception
     */
    public static function checkAutoMatchQuoteCost($quote)
    {
        if (!($quote instanceof Shipserv_Quote)) {
            $quote = Shipserv_Quote::getInstanceById($quote);
        }

        if ($quote->qotQuoteSts <> Shipserv_Quote::STATUS_SUBMITTED) {
            throw new Shipserv_Match_Auto_Exception("Auto match quote " . $quote->qotInternalRefNo . " cannot be compared as it isn't submitted");
        }

        if (!$quote->isAutoMatchQuote()) {
            throw new Shipserv_Match_Auto_Exception("RFQ event was not selected by automatch (quote " . $quote->qotInternalRefNo . ")");
        }

        if ($quote->qotTotalCost == 0) {
            // DE6362: this is a decline, consider it as not cheap enough so that buyer won't receive a notification
            return false;
        }

        $automatchQuoteCost = Shipserv_Oracle_Currency::convertTransactionCost($quote);
        if (is_null($automatchQuoteCost)) {
            // DE6362: unable to convert to USD - skip
            return false;
        }

        $rfq = $quote->getRfq();

        // retrieve all the quotes posted in response to the same RFQ event
        // DE6362: we are interested in buyer quotes for 100% of line items
        $db = Shipserv_Helper_Database::getDb();
        $selectBuyerQuotes = new Zend_Db_Select($db);
        $selectBuyerQuotes
            ->from(
                array('qot' => Shipserv_Quote::TABLE_NAME),
                array('qot.' . Shipserv_Quote::COL_ID)
            )
            ->join(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                'rfq.' . Shipserv_Rfq::COL_ID . ' = qot.' . Shipserv_Quote::COL_RFQ_ID,
                array()
            )
            ->joinLeft(
                array('mir' => 'match_imported_rfq_proc_list'),
                'mir.mir_qot_internal_ref_no = qot.' . Shipserv_Quote::COL_ID,
                array()
            )
            // all quotes for the same event
            ->where('rfq.' . Shipserv_Rfq::COL_EVENT_HASH . ' = HEXTORAW(?)', $rfq->rfqEventHash)
            // only buyer quotes
            ->where('qot.' . Shipserv_Quote::COL_BUYER_ID . ' <> ?', Myshipserv_Config::getProxyMatchBuyer())
            // only submitted quotes
            ->where('qot.' . Shipserv_Quote::COL_STATUS . ' = ?', Shipserv_Quote::STATUS_SUBMITTED)
            // added on 2015-07-30 by Yuriy Akopov to fix the problem with 0 cost quotes
            ->where('qot.' . Shipserv_Quote::COL_TOTAL_COST . ' > 0')
            // DE6362 changes of 2016-01-26
            // only non-imported quotes (i.e. original buyer quotes only) for 100% of line items
            // some of them might be priced at 0 - further in the code there is a check for that
            ->where('mir.mir_id IS NULL')
            ->where('rfq.' . Shipserv_Rfq::COL_LINE_ITEM_COUNT . ' <= qot.' . Shipserv_Quote::COL_LINE_ITEM_COUNT);

        $quoteIds = $db->fetchCol($selectBuyerQuotes);
        $buyerQuoteCosts = array();
        foreach ($quoteIds as $quoteId) {
            $buyerQuote = Shipserv_Quote::getInstanceById($quoteId);
            if ($buyerQuote->getCompleteness() < 1) {
                // incomplete buyer quotes are excluded
                continue;
            }

            $buyerQuoteCost = Shipserv_Oracle_Currency::convertTransactionCost($buyerQuote);

            if (is_null($buyerQuoteCost)) {
                continue; // not able to convert to USD for comparison, skip this quote
            }

            $buyerQuoteCosts[] = $buyerQuoteCost;
        }

        if (empty($buyerQuoteCosts)) {
            return true;    // no buyer quotes with valid prices found, nothing to compare to, green light granted
        }

        // return true if the quote is cheaper than buyer selected alternatives
        return ($automatchQuoteCost <= min($buyerQuoteCosts));
    }

    /**
     * Returns true if the supplier provided is a participant in AutoSource
     * @todo: Could be using Match HTTP API instead (but that's more complex as CAS needs to be authenticated etc.
     *
     * @author  Yuriy Akopov
     * @date    2017-10-23
     * @story   BUY-1231
     *
     * @param   Shipserv_Supplier|int $supplier
     *
     * @return  bool
     */
    public static function isSupplierParticipant($supplier)
    {
        if ($supplier instanceof Shipserv_Supplier) {
            $supplierId = $supplier->tnid;
        } else {
            $supplierId = $supplier;
        }

        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('sbm' => self::TABLE_SUPPLIER_MATCH),
                array(
                    'ID' => 'sbm.sbm_id'
                )
            )
            ->where('sbm.sbm_spb_branch_code = ?', $supplierId)
            ->where('sbm.sbm_enabled_till IS NULL')
            // this is not strictly necessary because elsewhere it's expected records beginning in the future cannot exist
            // ->where('sbm.sbm_enabled_from < SYSDATE')
        ;

        $autoSourceStatus = $select->getAdapter()->fetchOne($select);

        return (strlen($autoSourceStatus) > 0);
    }

    /**
     * Changes AutoSource participation status for the given supplier, returns true if the previous status was different
     * @todo: Could be using Match HTTP API instead (but that's more complex as CAS needs to be authenticated etc.
     *
     * @author  Yuriy Akopov
     * @date    2017-10-23
     * @story   BUY-1231
     *
     * @param   Shipserv_Supplier|int   $supplier
     * @param   bool                    $status
     *
     * @return  bool
     * @throws  Exception
     */
    public static function setSupplierParticipant($supplier, $status)
    {
        $existingStatus = self::isSupplierParticipant($supplier);
        if ($status == $existingStatus) {
            return false;
        }

        if ($supplier instanceof Shipserv_Supplier) {
            $supplierId = $supplier->tnid;
        } else {
            $supplierId = $supplier;
        }

        $db = Shipserv_Helper_Database::getDb();

        if ($status) {
            // this means there is either no record, or only terminated historical records
            // in both cases we need to insert a new one
            $db->insert(
                self::TABLE_SUPPLIER_MATCH,
                array(
                    'sbm_spb_branch_code' => $supplierId
                    // all other fields will receive correct default values;
                )
            );
        } else {
            // this means a record with an open date exists, so we need to close it
            $db->beginTransaction();
            try {
                $updateCount = $db->update(
                    self::TABLE_SUPPLIER_MATCH,
                    array(
                        'sbm_enabled_till' => new Zend_Db_Expr('SYSDATE')
                    ),
                    implode(
                        ' AND ',
                        array(
                            $db->quoteInto('sbm_spb_branch_code = ?', $supplierId),
                            'sbm_enabled_till IS NULL'
                        )
                    )
                );

                if ($updateCount !== 1) {
                    throw new Exception("Supplier AutoSource status integrity broken for TNID " . $supplierId);
                }

            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
        }

        return true;
    }
}
