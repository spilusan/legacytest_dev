<?php
/**
 * A helper class to juggle quote-related queries which don't look well in Shipserv_Quote
 *
 * @author  Yuriy Akopov
 * @date    2014-01-24
 * @story   S9231
 */
class Shipserv_Quote_ListManager {
    const
        INBOX_DEPTH_DAYS = 365 // default max length of the quote inbox - no access allowed to RFQs beyond that point
    ;

    /**
     * Returns a query for a list of quotes buyer has access to
     *
     * There is a flaw here - for forwarded RFQ, the same date limit is applied to speed the query up
     * That means that is a match quote is issue within the requested period in response to an RFQ outside that period it won't appear in the list
     *
     * @param   Shipserv_Buyer  $buyerOrg
     * @param   array           $fields         fields for the select
     * @param   int             $buyerBranchId
     * @param   int             $daysDepth      how many days back
     *
     * @return  Zend_Db_Select
     */
    public static function getQuoteListSelect(Shipserv_Buyer $buyerOrg, array $fields = null, $buyerBranchId = null, $daysDepth = self::INBOX_DEPTH_DAYS) {
        $pagesBuyerId = Myshipserv_Config::getProxyPagesBuyer();
        $matchBuyerId = Myshipserv_Config::getProxyMatchBuyer();

        $db = Shipserv_Helper_Database::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('qot' => Shipserv_Quote::TABLE_NAME),
                $fields
            )
            ->where('qot.qot_submitted_date >= (SYSDATE - ?)', $daysDepth)
            ->where('qot.' . Shipserv_Quote::COL_STATUS . ' = ?', Shipserv_Quote::STATUS_SUBMITTED)
        ;

        $subqueryFields = array(Shipserv_Rfq::COL_ID);

        if ($buyerBranchId == $pagesBuyerId) {
            $select
                ->join(
                    array('rfq_fwd' => Shipserv_Rfq::TABLE_NAME),
                    implode(' AND ', array(
                        'qot.' . Shipserv_Quote::COL_RFQ_ID . ' = rfq_fwd.' . Shipserv_Rfq::COL_ID,
                        'qot.qot_submitted_date >= rfq_fwd.' . Shipserv_Rfq::COL_DATE
                    )),
                    array()
                )
                ->join(
                    array('rfq_pages' => Shipserv_Rfq_EventManager::getPagesRfqSelect($buyerOrg, $subqueryFields, $daysDepth, false)),
                    implode(' OR ', array(
                        'rfq_fwd.' . Shipserv_Rfq::COL_ID . ' = rfq_pages.' . Shipserv_Rfq::COL_ID,
                        'rfq_fwd.' . Shipserv_Rfq::COL_SOURCE_ID . ' = rfq_pages.' . Shipserv_Rfq::COL_ID
                    )),
                    array()
                )
                ->where('rfq_pages.' . Shipserv_Rfq::COL_ID . ' <= rfq_fwd.' . Shipserv_Rfq::COL_ID)
                ->where('qot.' . Shipserv_Quote::COL_BUYER_ID . ' IN (?)', array($matchBuyerId, $pagesBuyerId))
            ;
        } else {
            $selectRfqs = new Zend_Db_Select($db);
            $selectRfqs
                ->from(
                    array('rfq_fwd' => Shipserv_Rfq::TABLE_NAME),
                    array(
                        'rfq_fwd.' . Shipserv_Rfq::COL_ID,
                        'rfq_fwd.' . Shipserv_Rfq::COL_SOURCE_ID,
                        'rfq_fwd.' . Shipserv_Rfq::COL_DATE
                    )
                )
                ->join(
                    array('rfq_src' => Shipserv_Rfq_EventManager::getIntegratedRfqSelect($buyerBranchId, $subqueryFields, $daysDepth, false)),
                    'rfq_fwd.' . Shipserv_Rfq::COL_SOURCE_ID . ' = rfq_src.' . Shipserv_Rfq::COL_ID,
                    array()
                )
                ->where('rfq_fwd.' . Shipserv_Rfq::COL_BUYER_ID . ' = ?', Myshipserv_Config::getProxyMatchBuyer())
            ;

            $select
                ->joinLeft(
                    array('rfq_fwd' => $selectRfqs),
                    implode(' AND ', array(
                        'qot.' . Shipserv_Quote::COL_RFQ_ID . ' = rfq_fwd.' . Shipserv_Rfq::COL_ID,
                        $db->quoteInto('qot.' . Shipserv_Quote::COL_BUYER_ID . ' = ?', $matchBuyerId),
                        'qot.qot_submitted_date >= rfq_fwd.' . Shipserv_Rfq::COL_DATE
                    )),
                    array()
                )
                ->where(implode(' OR ', array(
                    $db->quoteInto('qot.' . Shipserv_Quote::COL_BUYER_ID . ' IN (?)', $buyerBranchId),
                    'rfq_fwd.' . SHipserv_Rfq::COL_ID . ' IS NOT NULL'
                )))
            ;
        }

        return $select;

    }

    /**
     * Returns a subquery to tell if the quote was declined
     *
     * @return Zend_Db_Select
     */
    public static function getDeclineSelect() {
        $db = Shipserv_Helper_Database::getDb();

        // subquery to get the date of the most recent quote response (only most recent one matters)
        $lastReplyDateSelect = new Zend_Db_Select($db);
        $lastReplyDateSelect
            ->from(
                array('last_reply' => 'quote_response'),
                array(
                    'QUOTE_ID'   => 'last_reply.qrp_qot_internal_ref_no',
                    'REPLY_DATE' => new Zend_Db_Expr('MAX(last_reply.qrp_updated_date)')
                )
            )
            ->group('last_reply.qrp_qot_internal_ref_no')
        ;

        $declineSelect = new Zend_Db_Select($db);
        $declineSelect
            ->from(
                array('decline' => 'quote_response'),
                'decline.qrp_qot_internal_ref_no'
            )
            ->join(
                array('reply' => $lastReplyDateSelect),
                implode(' AND ', array(
                    'decline.qrp_qot_internal_ref_no = reply.quote_id',
                    'decline.qrp_updated_date = reply.reply_date'
                )),
                array()
            )
            ->where('decline.qrp_sts = ?', 'DEC')
        ;

        return $declineSelect;
    }
}