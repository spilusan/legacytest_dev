<?php
/**
 * Class implementing access to RFQ events and algorithms of their binding out of separate RFQs
 *
 * @author  Yuriy Akopov
 * @date    2014-01-23
 * @story   S9304
 */
class Shipserv_Rfq_EventManager {
    // RFQ events table
    const
        COL_EVENT_HASH  = 'EVENT_HASH'
    ;

    const
        OUTBOX_DEPTH_DAYS = 365 // default max length of the RFQ outbox - no access allowed to RFQs beyond that point
    ;

    /**
     * Time elapsed on the last temporary table population operation
     *
     * @var float
     */
    protected static $_elapsed = 0;

    /**
     * @return Zend_Db_Adapter_Oracle
     */
    protected static function _getDb() {
        return Shipserv_Helper_Database::getDb();
    }

    /**
     * A simplified and quicker version of the query to collect all the RFQs in the same event with the given one
     * Re-introduced from RC-4.0 and simplified to remove use cases never hit in the current situation
     * 
     * Refactored by Yuriy Akopov on 2016-05-10, DE6555 to remove legacy snippets no longer in use
     *
     * @author  Yuriy Akopov
     * @date    2014-04-15
     * @story   DE4712
     *
     * @param   Shipserv_Rfq     $rfq
     *
     * @return Zend_Db_Select
     */
    public static function getRfqEventSelect(Shipserv_Rfq $rfq) {
        $db = Shipserv_Helper_Database::getDb();

        $rfqEvent = new Zend_Db_Select($db);
        $rfqEvent
            ->from(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                array(
                    Shipserv_Rfq::SELECT_RFQ_ID => 'rfq.' . Shipserv_Rfq::COL_ID,
                    Shipserv_Rfq::SELECT_BUYER_ID => 'rfq.' . Shipserv_Rfq::COL_BUYER_ID
                )
            )
            ->where('rfq.' . Shipserv_Rfq::COL_STATUS . ' <> ?', Shipserv_Rfq::STATUS_DRAFT)
            ->where('rfq.' . Shipserv_Rfq::COL_EVENT_HASH . ' = HEXTORAW(?)', $rfq->rfqEventHash)
            ->order(Shipserv_Rfq::COL_ID . ' DESC')
        ;

        return $rfqEvent;
    }

    /**
     * Returns a query to get all the hashes of the buyer's RFQs sent from integrated apps
     *
     * @param   array   $buyerBranchIds
     * @param   array   $fields
     * @param   int     $daysDepth
     * @param   bool    $distinct
     * @param   string  $rfqTableAlias
     *
     * @return  Zend_Db_Select
     */
    public static function getIntegratedRfqSelect($buyerBranchIds, array $fields, $daysDepth = self::OUTBOX_DEPTH_DAYS, $distinct = true, $rfqTableAlias = 'rfq_buyer') {
        if (!is_array($buyerBranchIds)) {
            $buyerBranchIds = array($buyerBranchIds);
        }

        $db = self::_getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array($rfqTableAlias => Shipserv_Rfq::TABLE_NAME),
                $fields
            )
            ->where($rfqTableAlias . '.' . Shipserv_Rfq::COL_DATE . ' >= (SYSDATE - ?)', $daysDepth)
            ->where($rfqTableAlias . '.' . Shipserv_Rfq::COL_BUYER_ID . ' IN (?)', $buyerBranchIds)
        ;

        if ($distinct) {
            $select->distinct();
        }

        return $select;
    }

    /**
     * Returns a query to get all the hashes of the buyer's RFQs sent from Pages
     *
     * @param   Shipserv_Buyer[]|Shipserv_Buyer $buyerOrgs
     * @param   array           $fields
     * @param   int             $daysDepth
     * @param   bool            $distinct
     * @param   string          $rfqTableAlias
     *
     * @return  Zend_Db_Select
     * @throws  Exception
     */
    public static function getPagesRfqSelect($buyerOrgs, array $fields, $daysDepth = self::OUTBOX_DEPTH_DAYS,  $distinct = true, $rfqTableAlias = 'rfq_buyer') {
        if (!is_array($buyerOrgs)) {
            $buyerOrgs = array($buyerOrgs);
        }

        $buyerOrgIds = array();
        foreach ($buyerOrgs as $buyerOrg) {
            if ($buyerOrg instanceof Shipserv_Buyer) {
                $buyerOrgIds[] = $buyerOrg->id;
            } else {
                throw new Exception("Impossible to retrieve pages RFQs, invalid buyer organisation supplied");
            }
        }

        $pagesBuyerId = Myshipserv_Config::getProxyPagesBuyer();

        $select = self::getIntegratedRfqSelect(array($pagesBuyerId), $fields, $daysDepth, $distinct, $rfqTableAlias);

        $select
            ->join(
                array('pir' => 'pages_inquiry_recipient'),
                'pir.pir_rfq_internal_ref_no = ' . $rfqTableAlias . '.rfq_internal_ref_no',
                array()
            )
            ->join(
                array('pin' => 'pages_inquiry'),
                implode(' AND ', array(
                    'pin.pin_id = pir.pir_pin_id',
                    $select->getAdapter()->quoteInto('pin.pin_puc_company_type = ?', 'BYO'),
                    $select->getAdapter()->quoteInto('pin.pin_puc_company_id IN (?)', $buyerOrgIds)
                )),
                array()
            )
        ;

        return $select;
    }

    /**
     * Returns select to grab direct (i.e. not forwarded) RFQs from the given buyers, supports Pages RFQs as well
     *
     * @param   Shipserv_Buyer[]|Shipserv_Buyer $buyerOrgs
     * @param   array|null   $fields
     * @param   array|null   $buyerBranchIds
     * @param   int $daysDepth
     *
     * @return Zend_Db_Select
     */
    public static function getBuyerRfqsNoFwdSelect($buyerOrgs, array $fields = null, $buyerBranchIds = null, $daysDepth = self::OUTBOX_DEPTH_DAYS) {
        $pagesAndIntegratedTogether = false;

        if (!is_array($buyerOrgs)) {
            $buyerOrgs = array($buyerOrgs);
        }

        /** @var Shipserv_Buyer[] $buyerOrgs */

        if (is_null($buyerBranchIds)) {
            $pagesAndIntegratedTogether = true;

            $buyerBranchIds = array();
            foreach ($buyerOrgs as $buyerOrg) {
                $buyerBranchIds = array_merge($buyerBranchIds, $buyerOrg->getBranchesTnid());
            }
        } else if (!is_array($buyerBranchIds)) {
            $buyerBranchIds = array($buyerBranchIds);
        }

        $pagesBuyerId = Myshipserv_Config::getProxyPagesBuyer();

        // first select all the unique hashes for the given buyer
        $db = self::_getDb();

        if (is_null($fields)) {
            $fields = array(Shipserv_Rfq::COL_EVENT_HASH);
        }

        if ($buyerBranchIds == array($pagesBuyerId)) {
            $selectRfqs = self::getPagesRfqSelect($buyerOrgs, $fields, $daysDepth);

        } else if (!$pagesAndIntegratedTogether) {
            $selectRfqs = self::getIntegratedRfqSelect($buyerBranchIds, $fields, $daysDepth);

        } else {
            $selectRfqs = new Zend_Db_Select($db);
            $selectRfqs->union(array(
                self::getIntegratedRfqSelect($buyerBranchIds, $fields, $daysDepth),
                self::getPagesRfqSelect($buyerOrgs, $fields, $daysDepth)
            ), Zend_Db_Select::SQL_UNION);
        }

        return $selectRfqs;
    }

    /**
     * Returns basic RFQ events list query to be constrained depending on the use case
     *
     * Applies basic constraints critical for performance, e.g. date and buyer ones.
     *
     * @date    2014-04-16
     * @story   S10029
     *
     * @param   Shipserv_Buyer[]|Shipserv_Buyer $buyerOrgs
     * @param   array           $fields
     * @param   int             $buyerBranchIds
     * @param   int             $daysDepth
     * @param   string          $rfqTableAlias
     *
     * @return Zend_Db_Select
     */
    public static function getEventListSelect($buyerOrgs, array $fields = null, $buyerBranchIds = null, $daysDepth = self::OUTBOX_DEPTH_DAYS, $rfqTableAlias = 'rfq') {
        $pagesAndIntegratedTogether = false;

        if (!is_array($buyerOrgs)) {
            $buyerOrgs = array($buyerOrgs);
        }

        /** @var Shipserv_Buyer[] $buyerOrgs */

        if (is_null($buyerBranchIds)) {
            $pagesAndIntegratedTogether = true;

            $buyerBranchIds = array();
            foreach ($buyerOrgs as $buyerOrg) {
                $buyerBranchIds = array_merge($buyerBranchIds, $buyerOrg->getBranchesTnid());
            }
        } else if (!is_array($buyerBranchIds)) {
            $buyerBranchIds = array($buyerBranchIds);
        }

        $pagesBuyerId = Myshipserv_Config::getProxyPagesBuyer();

        // first select all the unique hashes for the given buyer
        $db = self::_getDb();

        $subqueryFields = array(Shipserv_Rfq::COL_EVENT_HASH);

        if ($buyerBranchIds == array($pagesBuyerId)) {
            $selectHashes = self::getPagesRfqSelect($buyerOrgs, $subqueryFields, $daysDepth);

        } else if (!$pagesAndIntegratedTogether) {
            $selectHashes = self::getIntegratedRfqSelect($buyerBranchIds, $subqueryFields, $daysDepth);

        } else {
            $selectHashes = new Zend_Db_Select($db);
            $selectHashes->union(array(
                self::getIntegratedRfqSelect($buyerBranchIds, $subqueryFields, $daysDepth),
                self::getPagesRfqSelect($buyerOrgs, $subqueryFields, $daysDepth)
            ), Zend_Db_Select::SQL_UNION);
        }

        if (is_null($fields)) {
            $fields = array();
        }

        $fields = array_merge($fields, array(
            self::COL_EVENT_HASH => new Zend_Db_Expr('RAWTOHEX(' . $rfqTableAlias . '.' . Shipserv_Rfq::COL_EVENT_HASH . ')')
        ));

        // then select all the RFQs for these hashes - that would include forwarded RFQs as well (they have hashes
        // identical to their source ones)
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array($rfqTableAlias => Shipserv_Rfq::TABLE_NAME),
                $fields
            )
            ->join(
                array('rfq_hash' => $selectHashes),
                $rfqTableAlias . '.' . Shipserv_Rfq::COL_EVENT_HASH . ' = rfq_hash.' . Shipserv_Rfq::COL_EVENT_HASH,
                array()
            )
            ->where($rfqTableAlias . '.' . Shipserv_Rfq::COL_STATUS . ' <> ?', Shipserv_Rfq::STATUS_DRAFT)
            ->group($rfqTableAlias . '.' . Shipserv_Rfq::COL_EVENT_HASH)
        ;

        return $select;
    }

    /**
     * Returns all or first only RFQ from the given event
     *
     * @date    2014-06-22
     *
     * @param   string  $eventHash
     * @param   bool    $firstOnly
     * @param   bool    $idsOnly
     *
     * @return  Shipserv_Rfq[]|Shipserv_Rfq|int|array    $rfq
     *
     */
    public static function getRfqsForEvent($eventHash, $firstOnly = false, $idsOnly = false) {
        if ($firstOnly) {
            $fields = array(
                Shipserv_Rfq::COL_ID => new Zend_Db_Expr('MIN(' . Shipserv_Rfq::COL_ID . ')')
            );
        } else {
            $fields = array(
                Shipserv_Rfq::COL_ID
            );
        }

        $db = self::_getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                $fields
            )
            ->where(Shipserv_Rfq::COL_EVENT_HASH . ' = HEXTORAW(?)', $eventHash)
            ->where(Shipserv_Rfq::COL_STATUS . ' <> ?', Shipserv_Rfq::STATUS_DRAFT)
        ;

        if ($firstOnly) {
            $rfqIds = $db->fetchOne($select);
        } else {
            $rfqIds = $db->fetchCol($select);
        }

        if ($idsOnly) {
            return $rfqIds;
        }

        if ($firstOnly) {
            $rfqs = Shipserv_Rfq::getInstanceById($rfqIds);
        } else {
            $rfqs = array();
            foreach($rfqIds as $id) {
                $rfqs[] = Shipserv_Rfq::getInstanceById($id);
            }
        }

        return $rfqs;
    }
}
