<?php

class Shipserv_Match_Stats extends Shipserv_Object {

    private $matchCache;
    private $buyerId;
    private $matchId;
    private $db;
    private $local;
    private $reporting;
    private $dbRaw;

    const REPORT_PERIOD_WEEK = 10;
    const REPORT_PERIOD_MONTH = 20;
    const REPORT_PERIOD_2MONTH = 30;
    const REPORT_PERIOD_6MONTH = 40;
    const REPORT_PERIOD_ALL = 50;

    //CONST METHODS
    public static function getReportPeriodWeek() {
        return self::REPORT_PERIOD_WEEK;
    }

    public static function getReportPeriodMonth() {
        return self::REPORT_PERIOD_MONTH;
    }

    public static function getReportPeriod2Month() {
        return self::REPORT_PERIOD_2MONTH;
    }

    public static function getReportPeriod6Month() {
        return self::REPORT_PERIOD_6MONTH;
    }

    public static function getReportPeriodAll() {
        return self::REPORT_PERIOD_ALL;
    }

    /**
     * Function to help with SQL building, will return the SQL nescessary to compare time periods/
     * @param int $period - The numerical reference to the time period referred to by the Consts declared at class scope
     * @return string
     */
    private function getSQLPeriodCode($period) {
        switch ($period) {
            case self::REPORT_PERIOD_WEEK:
                return " (sysdate - 7)";
                break;
            case self::REPORT_PERIOD_MONTH:
                return " (sysdate - INTERVAL '1' MONTH) ";
                break;
            case self::REPORT_PERIOD_2MONTH:
                return " (sysdate - 60)  ";
                break;
            case self::REPORT_PERIOD_6MONTH:
                return " (sysdate - 180)  ";
                break;
            default:
                //Defaulting to the start date of Match
                return " '01-JAN-2012' ";
                break;
        }
    }

    public function __construct($buyerId) {
        if ($buyerId > 0) {


            //$this->matchCache = new Shipserv_Match_MatchCache();
            //Change this to use Reporting -> Standby rather than Live and Standby
            $this->db = Shipserv_Helper_Database::getStandByDb(true);
            $this->local = Shipserv_Helper_Database::getDb();
            $this->reporting = Shipserv_Helper_Database::getSsreport2Db();

            //Configure local buyer IDS, these differ between UAT and Prod
            $this->buyerId = $buyerId;
            $config = Zend_Registry::get('config');
            $this->matchId = $config->shipserv->match->buyerId;

            if ($this->getCountRFQSToMatch(self::REPORT_PERIOD_ALL) == 0) {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Count of RFQs sent to Match
     */
    public function getCountRFQSToMatch($period) {
        $sql = "SELECT COUNT(*) AS r
                FROM rfq r
                WHERE r.spb_branch_code  = 999999
                AND r.byb_branch_code    = :buyerId
                AND r.rfq_submitted_date >
 " . $this->getSQLPeriodCode($period);
        $params = array('buyerId' => $this->buyerId);

        $results = $this->reporting->fetchOne($sql, $params);
        return (int) $results;
    }

    /**
     * Ratio in % of RFQs sent to Match vs ALL MatchRFQs
     */
    public function getPercentageRFQSToMatch($period) {
        $sql = "Select
                (Select count(*) from
                rfq r WHERE r.spb_branch_code = 999999 AND r.byb_branch_code = :buyerId AND r.rfq_submitted_date > " . $this->getSQLPeriodCode($period) . ")
                /
                (SELECT COUNT(DISTINCT r.rfq_ref_no)
                FROM rfq r
                WHERE r.byb_branch_code = :buyerId
                AND r.rfq_submitted_date  > " . $this->getSQLPeriodCode($period) . ") * 100 as Percentage From Dual";
        $params = array('buyerId' => $this->buyerId);

        $results = $this->reporting->fetchOne($sql, $params);
        return round($results, 0);
    }

    /**
     * Count of quotes in response to forwarded RFQs from Match
     */
    public function getCountQuotesFromMatch($period) {
        $sql = "SELECT COUNT(*) AS r
                FROM qot
                WHERE rfq_internal_ref_no IN
                  (SELECT rfq_internal_ref_no
                  FROM rfq
                  WHERE byb_branch_code      = :matchId
                  AND rfq_pom_source IN
                    (SELECT rfq_internal_ref_no
                    FROM rfq
                    WHERE byb_branch_code = :buyerId
                    and rfq_submitted_date > (TO_DATE(" . $this->getSQLPeriodCode($period) . ") - 30 )
                    )
                  )
                AND qot_total_cost     > 0
                AND qot_submitted_date > " . $this->getSQLPeriodCode($period);

        $params = array('buyerId' => $this->buyerId, 'matchId' => $this->matchId);

        $results = $this->reporting->fetchAll($sql, $params);
        return (int) $results[0]['R'];
    }

    /**
     * Count of POs attributed to Match Introductions (Direct or otherwise for any RFQ that was initially sent to Match chosen suppliers)
     */
    public function getCountPOsMatchSuppliers($period) {
        $sql = "
       SELECT COUNT(*)
            FROM ORD p,
              rfq r,
              qot q,
              (SELECT source_r.rfq_internal_ref_no AS SourceRFQ,
                int_r.rfq_internal_ref_no          AS MatchRFQ,
                source_r.byb_branch_code           AS Buyer,
                int_r.spb_branch_code              AS Supplier,
                source_r.rfq_ref_no                AS RefNo,
                int_r.rfq_submitted_date           AS MatchSent,
                int_q.qot_internal_ref_no          AS MatchQuote
              FROM rfq int_r,
                rfq source_r,
                qot int_q
              WHERE int_q.rfq_internal_ref_no = int_r.rfq_internal_ref_no
              AND int_r.rfq_pom_source        = source_r.rfq_internal_ref_no
              AND int_r.byb_branch_code       =  {$this->matchId}
              AND source_r.spb_branch_code    = 999999
              AND source_r.byb_branch_code    = {$this->buyerId}
              AND source_r.rfq_submitted_date > {$this->getSQLPeriodCode($period)}
              ) MatchEvents
            WHERE p.rfq_internal_ref_no                             = r.rfq_internal_ref_no (+)
            AND p.qot_internal_ref_no                               = q.qot_internal_ref_no (+)
            AND (COALESCE(r.rfq_ref_no, q.qot_ref_no, p.ord_ref_no) = MatchEvents.RefNo)
            AND p.spb_branch_code                                   = MatchEvents.Supplier
            AND p.byb_branch_code                                   = MatchEvents.Buyer
            AND p.ord_internal_ref_no                               > 3214421
            AND p.ord_submitted_date                                > MatchEvents.MatchSent

";


        $poCount = $this->reporting->fetchOne($sql);
        return $poCount;
    }

    /**
     * Count of RFQs that were not sent to Match
     * @param string $period
     * @return Integer
     */
    public function getCountRFQsNotSentToMatch($period) {
        $sql = "SELECT COUNT( DISTINCT rfq.rfq_ref_no )
                FROM rfq
                WHERE rfq.byb_branch_code   = :buyerId
                AND rfq.spb_branch_code  != 999999
                AND rfq.rfq_submitted_date    > " . $this->getSQLPeriodCode($period);
        $params = array('buyerId' => $this->buyerId);

        $rfqCount = $this->reporting->fetchOne($sql, $params);

        return $rfqCount;
    }

    /**
     * Get RFQ headers for all RFQs sent in set period
     * @param string $period
     * @param integer $page Current page
     * @param integer $count Number of records
     * @param integer $sortMethod
     * @return array
     */
    public function getRFQsSentToMatch($period, $page = 1, $count = 5, $sortMethod = 1) {

        switch ($sortMethod) {
            case 1:
                $sort = " RQR_SUBMITTED_DATE desc ";
                break;
            case 2:
                $sort = " RFQ_VESSEL_NAME asc  ";
                break;
            default:
                $sort = " RQR_SUBMITTED_DATE desc ";
                break;
        }


        $sql = "Select *
                from (select x.*, rownum    as r from (Select r.*, RQR_SUBMITTED_DATE
                from request_for_quote r, rfq_quote_relation rqr, port p
                where
                    r.rfq_internal_ref_no = rqr.rqr_rfq_internal_ref_no
                    and  rqr.rqr_spb_branch_code = 999999
                    and r.rfq_delivery_port = p.prt_port_code(+)
                    and rqr.rqr_byb_branch_code = :buyerId and  rqr.rqr_submitted_date >  " . $this->getSQLPeriodCode($period) . " order by " . $sort . ") x ) where r between " . ((($page * $count) - $count) + 1) . " and " . ($page * $count);



        $params = array('buyerId' => $this->buyerId);

        $results = $this->db->fetchAll($sql, $params);
        return $results;
    }

    /**
     * Fetch quotes for a given RFQ for comparison
     * @param integer $rfqId
     * @return array
     */
    public function getQuotesForRFQ($rfqId) {
        $sql = "Select  q.*, round(q.qot_total_cost/c.curr_exchange_rate, 2) as NormalisedTotal, s.spb_name, s.spb_branch_code, r.rfq_ref_no, r.rfq_byb_branch_code,
                case when r.rfq_sourcerfq_internal_no is not null then
                (select rfq_byb_branch_code from request_for_quote where rfq_internal_ref_no = r.rfq_sourcerfq_internal_no)
                else
                r.rfq_byb_branch_code
                end as originalbuyer,  Least(100,(SELECT ROUND((q.qot_line_item_count -
                (SELECT COUNT(*)
                FROM rfq_quote_line_item_change
                WHERE rqlc_qot_internal_ref_no = q.qot_internal_ref_no and rqlc_line_item_status = 'DEC'
                ))/ q.qot_line_item_count, 2)
              From DUAL
              ) * 100) AS Completeness,(Select count(*) from Purchase_order po where po.ord_qot_internal_ref_no = q.qot_internal_ref_no ) as POsAwarded

                from quote q, currency c, supplier_branch s, request_for_quote r
                          where
                          r.rfq_internal_ref_no = :rfqId
                          and q.qot_spb_branch_code = s.spb_branch_code
                          and  q.qot_currency = c.curr_code
                          and  qot_rfq_internal_ref_no in (Select rfq_internal_ref_no from request_for_quote where rfq_ref_no = (select rfq_ref_no from request_for_quote where rfq_internal_ref_no = :rfqId))
                          and ((qot_byb_branch_code = 11107) or (qot_byb_branch_code = r.rfq_byb_branch_code))
                          and qot_quote_sts = 'SUB'
                          and QOT_TOTAL_COST > 0
                          ";

        $params = array('rfqId' => $rfqId);

        $results = $this->db->fetchAll($sql, $params);



        $lowOriginal = 0;
        $lowMatch = 0;



        foreach ($results as $result) {
            if (($lowOriginal == 0 && $result['QOT_BYB_BRANCH_CODE'] != $this->matchId && $result['COMPLETENESS'] == 100) || ($lowOriginal > $result['NORMALISEDTOTAL'] && $this->matchId != $result['QOT_BYB_BRANCH_CODE'] && $result['COMPLETENESS'] == 100 )) {

                $lowOriginal = $result['NORMALISEDTOTAL'];
                $lowOriginalQuoteId = $result['QOT_INTERNAL_REF_NO'];
            }

            if (($lowMatch == 0 && $result['QOT_BYB_BRANCH_CODE'] == $this->matchId && $result['COMPLETENESS'] == 100) || ($lowMatch > $result['NORMALISEDTOTAL'] && $this->matchId == $result['QOT_BYB_BRANCH_CODE'] && $result['COMPLETENESS'] == 100 )) {
                $lowMatch = $result['NORMALISEDTOTAL'];
                $lowMatchQuoteId = $result['QOT_INTERNAL_REF_NO'];
            }
        }

        $lowStats = array('lowOriginal' => array('value' => $lowOriginal, 'id' => $lowOriginalQuoteId),
            'lowMatch' => array('value' => $lowMatch, 'id' => $lowMatchQuoteId));
        return array('quotes' => $results, 'stats' => $lowStats);
    }

    /**
     *
     * @param integer $quoteId
     * @return array
     */
    public function getPOsforQuote($quoteId) {
        $sql = "SELECT DISTINCT p.ord_internal_ref_no,
                q.qot_ref_no,
                q.qot_internal_ref_no,
                MatchEvents.*
              FROM ord p,
                rfq r,
                qot q,
                (SELECT source_r.rfq_internal_ref_no AS SourceRFQ,
                  int_r.rfq_internal_ref_no          AS MatchRFQ,
                  source_r.byb_branch_code           AS Buyer,
                  int_r.spb_branch_code              AS Supplier,
                  source_r.rfq_ref_no                AS RefNo,
                  int_r.rfq_submitted_date           AS MatchSent,
                  int_q.qot_internal_ref_no          AS MatchQuote
                FROM rfq int_r,
                  rfq source_r,
                  qot int_q
                WHERE int_q.rfq_internal_ref_no = int_r.rfq_internal_ref_no
                AND int_r.rfq_pom_source        = source_r.rfq_internal_ref_no
                AND int_r.byb_branch_code       = 11107
                AND source_r.spb_branch_code    = 999999
                AND source_r.byb_branch_code    = :buyerId
                AND int_q.qot_internal_ref_no   = :quoteId
                ) MatchEvents
              WHERE p.rfq_internal_ref_no                             = r.rfq_internal_ref_no (+)
              AND p.qot_internal_ref_no                               = q.qot_internal_ref_no (+)
              AND (COALESCE(r.rfq_ref_no, q.qot_ref_no, p.ord_ref_no) = MatchEvents.RefNo)
              AND p.spb_branch_code                                   = MatchEvents.Supplier
              AND p.byb_branch_code                                   = MatchEvents.Buyer
              AND p.ord_submitted_date                                > MatchEvents.MATCHSENT
              AND p.ord_submitted_date                                > '01-JAN-2012'";

        $params = array('buyerId' => $this->buyerId, 'quoteId' => $quoteId);

        try {
            $results = $this->reporting->fetchRow($sql, $params);
            if (empty($results)) {
                return false;
            }
        } catch (Exception $ex) {
            return false;
        }
        return $results;
    }
}
