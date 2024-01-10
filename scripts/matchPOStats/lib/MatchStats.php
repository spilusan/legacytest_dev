<?php

/**
 * Class to handle data pump for MarketSegmentation
 * This class will classify
 */
class MatchStats {

    function __construct() {
        $dbReport = Shipserv_Helper_Database::getSsreport2Db();
        $this->reporting = $dbReport;
        $this->config = Zend_Registry::get('config');
    }

    /**
     * Func to aggregate the data for RFQ and POs into daily stats.
     * @param type $startDate Date for which processing should happen.
     * @param type $continuous Booledan which determinse if we should incrementally process days following $startDate, or just the startDate.
     */
    public function getMatchStats($startDate, $continuous = true) {

        $config = Zend_Registry::get('config');
        $buyerId = $config->shipserv->pagesrfq->buyerId;
        //TODO: change
        //$sql = "Select ID from Product_Category order by ID";

        if ($continuous) {
            Logger::log("Continuous mode on.\n\tStart date set to $startDate");
            $dt = new MyDateTime();
            //Enddate will be yesterday.
            $dates = $dt->getDaysToArray($startDate, date('d-M-Y', time() - (60 * 60 * 24)));
            Logger::log(count($dates) . " days will be processed.");
        } else {
            Logger::log("Getting Match stats for $startDate ONLY.");
            $dates = array($startDate);
        }

        $insertSQL = $this->getMDAInsertionSQL();

        foreach ($dates as $day) {
            $startDate = "$day 00:00:00";
            $endDate = "$day 23:59:59";
            Logger::log("\tProcessing date $day;");
            $basicResultsParameters = $this->getBasicMatchStatsSQL($startDate, $endDate);
			$basicResults = $this->reporting->fetchAll($basicResultsParameters['sql'], $basicResultsParameters['params']);

            foreach ($basicResults as $key => &$result) {
                //We need to get the ID and add in the PO values and savings.
                $poParams = $this->getMatchPoCountSQL($startDate, $endDate, $result['BYB_BRANCH_CODE']);
                $poCount = $this->reporting->fetchOne($poParams['sql'], $poParams['params']);
                $result['TOTALPOCOUNT'] = $poCount;

                //Savings
                $savingsParams = $this->getPotentialPOSavingsSQL($startDate, $endDate, $result['BYB_BRANCH_CODE']);
                $savings = $this->reporting->fetchOne($savingsParams['sql'], $savingsParams['params']);
                $result['POTENTIALSAVING'] = $savings;

                //Add the results to the DB>
                $params = array(
                	'dateOfStat' => $day,
                    'potentialSavings' => $savings,
                    'totalRFQ' => $result['TOTAL_RFQ_COUNT'],
                    'buyerId' => $result['BYB_BRANCH_CODE'],
                    'matchPOS' => $poCount,
                    'matchRFQs' => $result['RFQS_SENT_FROM_MATCH_COUNT'],
                    'rfqsToMatch' => $result['TOTALRFQSTOMATCHCOUNT'],
                    'quotesIssued' => $result['MATCH_QUOTES_COUNT'],
                    'rfqsWithQuote' => $result['RFQS_WITH_QUOTES_COUNT']
                );
                $this->reporting->query($insertSQL, $params);
            }

            Logger::log("\tCompleted stats for $day");
        }
    }

    public function errorHandler($error_level, $error_message, $error_file, $error_line, $error_context) {
        Logger::log("\t===>Error: [$error_context] $error_message errors.");
        $this->totalError++;
    }

    //Helper method to handle the SQL formation, tidying up the main worker method.
    /**
     *
     */
    private function getBasicMatchStatsSQL($startDate, $endDate) {
        $sql = "
        	  SELECT b.byb_name,
                B.Byb_Country_Code,
                DrvTable.Byb_Branch_Code,
                Totalrfqstomatchcount,
                Rfqs_Sent_From_Match_Count,
                Match_Quotes_Count,
                Rfqs_With_Quotes_Count,
                Total_Rfq_Count
              FROM
                ( SELECT DISTINCT Primary_R.Byb_Branch_Code,
                  --Total RFQs Sent To Match--
                  (
	                  SELECT COUNT(*)
	                  FROM rfq
	                  WHERE Spb_Branch_Code = 999999
	                  AND Rfq_Submitted_Date BETWEEN to_date(:startDate, 'DD-MON-YYYY HH24:MI:SS') AND to_date(:endDate, 'DD-MON-YYYY HH24:MI:SS') + 1
	                  AND Byb_Branch_Code = Primary_R.Byb_Branch_Code
                  ) AS TotalRfqsToMatchCount,
                  
                  --Total RFDs sent from Match--
                  (
	                  SELECT COUNT(*)
	                  FROM rfq r,
	                    rfq r2
	                  WHERE r.byb_branch_code = :matchBuyer
	                  AND R.rfq_pom_source    = R2.rfq_internal_ref_no
	                  AND R.Rfq_Pom_Source   IS NOT NULL
	                  AND R2.rfq_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY HH24:MI:SS') AND to_date(:endDate, 'DD-MON-YYYY HH24:MI:SS') + 1
	                  AND R2.Byb_Branch_Code = Primary_R.Byb_Branch_Code
                  ) AS Rfqs_Sent_From_Match_Count,
                  
                  --Total Quotes Back--
                  (
	                  SELECT COUNT(*)
	                  FROM QOT q,
	                    RFQ r,
	                    RFQ r2
	                  WHERE q.rfq_internal_ref_no = r.rfq_internal_ref_no
	                  AND r.rfq_pom_source        = r2.rfq_internal_ref_no
	                  AND q.qot_submitted_date   IS NOT NULL
	                  AND r.byb_branch_code       = :matchBuyer
	                  AND Q.Qot_Total_Cost        > 0
	                  AND r.rfq_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY HH24:MI:SS') AND to_date(:endDate, 'DD-MON-YYYY HH24:MI:SS') + 1
	                  AND R2.Byb_Branch_Code = Primary_R.Byb_Branch_Code
                  ) AS Match_Quotes_Count,
                  
                  --Number of RFQs with at least one quote--
                  (
	                  SELECT COUNT(DISTINCT r.rfq_ref_no)
	                  FROM RFQ r
	                  WHERE r.spb_branch_code    = 999999
	                  AND r.rfq_internal_ref_no IN
	                    (SELECT rfq_pom_source
	                    FROM rfq r2,
	                      qot q
	                    WHERE r2.rfq_internal_ref_no = q.rfq_internal_ref_no
	                    AND r2.byb_branch_code       = :matchBuyer
	                    AND q.qot_total_cost         > 0
	                    AND q.qot_submitted_date    IS NOT NULL
	                    )
	                  AND R.Rfq_Submitted_Date BETWEEN to_date(:startDate, 'DD-MON-YYYY HH24:MI:SS') AND to_date(:endDate, 'DD-MON-YYYY HH24:MI:SS') + 1
	                  AND R.Byb_Branch_Code = Primary_R.Byb_Branch_Code
                  ) AS Rfqs_With_Quotes_Count,
                  
                  -- Total RFQs --
                  (
	                  SELECT COUNT(*)
	                  FROM Rfq
	                  WHERE Byb_Branch_Code = Primary_R.Byb_Branch_Code
	                  AND Rfq_Submitted_Date BETWEEN to_date(:startDate, 'DD-MON-YYYY HH24:MI:SS') AND to_date(:endDate, 'DD-MON-YYYY HH24:MI:SS') + 1
                  ) AS Total_Rfq_Count
                FROM Rfq Primary_R
                Where Primary_R.Byb_Branch_Code In (
                Select distinct byb_branch_code from rfq where spb_branch_code = 999999
                ) and Primary_R.Rfq_Submitted_Date Between to_date(:startDate, 'DD-MON-YYYY HH24:MI:SS') AND to_date(:endDate, 'DD-MON-YYYY HH24:MI:SS') + 1
                ) Drvtable,
                BUyer b Where Drvtable.Byb_Branch_Code = b.Byb_Branch_Code
              Order By TotalRfqsToMatchCount Desc
       	";

        $params = array('startDate' => $startDate, 'endDate' => $endDate, 'matchBuyer' => $this->config->shipserv->match->buyerId);
        return array('sql' => $sql, 'params' => $params);
    }

    private function getMatchPoCountSQL($startDate, $endDate, $buyerId) {
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
              AND int_r.byb_branch_code       = :matchId
              AND source_r.spb_branch_code    = 999999
              AND source_r.byb_branch_code    = :buyerId
              AND source_r.rfq_submitted_date between to_date(:startDate, 'DD-MON-YYYY HH24:MI:SS' )and to_date(:endDate, 'DD-MON-YYYY HH24:MI:SS') + 1
              ) MatchEvents
            WHERE p.rfq_internal_ref_no                             = r.rfq_internal_ref_no (+)
            AND p.qot_internal_ref_no                               = q.qot_internal_ref_no (+)
            AND (COALESCE(r.rfq_ref_no, q.qot_ref_no, p.ord_ref_no) = MatchEvents.RefNo)
            AND p.spb_branch_code                                   = MatchEvents.Supplier
            AND p.byb_branch_code                                   = MatchEvents.Buyer
            AND p.ord_internal_ref_no                               > 3214421
            AND p.ord_submitted_date                                > MatchEvents.MatchSent";

        $params = array('startDate' => $startDate, 'endDate' => $endDate, 'matchId' => $this->config->shipserv->match->buyerId, 'buyerId' => $buyerId);
        return array('sql' => $sql, 'params' => $params);
    }

    private function getPotentialPOSavingsSQL($startDate, $endDate, $buyerId) {
        $sql = "Select Sum(Difference) from (
                    SELECT rfq_internal_ref_no,
                      RFQ_REF_NO,
                      (CheapestOriginal - CheapestMatch) AS Difference,
                      CheapestMatch,
                      CheapestOriginal,
                      Saving,
                      N
                    FROM
                      (SELECT *
                      FROM
                        (SELECT r2.rfq_internal_ref_no,
                          r2.rfq_ref_no,
                          ROUND(MIN(qot_total_cost/c.curr_exchng_rate),2) AS CheapestMatch,
                          ROUND(
                          (SELECT MIN(qot_total_cost /c2.curr_exchng_rate)
                          FROM QOT q2,
                            currency c2
                          WHERE q2.qot_currency       = c2.curr_code
                          AND q2.rfq_internal_ref_no IN
                            (SELECT rfq_internal_ref_no
                            FROM rfq r3
                            WHERE byb_branch_code = :buyerId
                            AND r3.rfq_ref_no     = r2.rfq_ref_no
                            )
                          AND qot_total_cost > 0
                          ),2) AS CheapestOriginal,
                          ROUND(100                  - ((ROUND(MIN(qot_total_cost/c.curr_exchng_rate),2) / ROUND(
                          (SELECT MIN(qot_total_cost /c2.curr_exchng_rate)
                          FROM qot q2,
                            currency c2
                          WHERE q2.qot_currency       = c2.curr_code
                          AND q2.rfq_internal_ref_no IN
                            (SELECT rfq_internal_ref_no
                            FROM rfq r3
                            WHERE byb_branch_code = :buyerId
                            AND r3.rfq_ref_no     = r2.rfq_ref_no
                            )
                          AND qot_total_cost > 0
                          ), 2) )                             * 100),2) AS Saving,
                          ntile(10) OVER ( ORDER BY ROUND(100 - ((ROUND(MIN(qot_total_cost/c.curr_exchng_rate),2) / ROUND(
                          (SELECT MIN(qot_total_cost          /c2.curr_exchng_rate)
                          FROM qot q2,
                            currency c2
                          WHERE q2.qot_currency       = c2.curr_code
                          AND q2.rfq_internal_ref_no IN
                            (SELECT rfq_internal_ref_no
                            FROM rfq r3
                            WHERE r3.byb_branch_code = :buyerId
                            AND r3.rfq_ref_no        = r2.rfq_ref_no
                            )
                          AND qot_total_cost > 0
                          ), 2) ) * 100),2) ) N
                        FROM currency c,
                          qot q,
                          rfq r1,
                          rfq r2
                        WHERE c.curr_code         = q.qot_currency
                        AND q.byb_branch_code     = :matchId
                        AND r2.byb_branch_code    = :buyerId
                        AND q.rfq_internal_ref_no = r1.rfq_internal_ref_no
                        AND r1.rfq_pom_source     = r2.rfq_internal_ref_no
                        AND r2.rfq_submitted_date between to_date(:startDate, 'DD-MON-YYYY HH24:MI:SS') and to_date(:endDate, 'DD-MON-YYYY HH24:MI:SS') + 1
                        AND q.qot_total_cost      > 0
                        GROUP BY r2.rfq_internal_ref_no,
                          r2.rfq_ref_no
                        --===========REMOVE FOR ALL SAVINGS RATHER THAN JUST MATCH BEING CHEAPER===============
                        HAVING ROUND(MIN(qot_total_cost/c.curr_exchng_rate),2) < ROUND(
                          (SELECT MIN(qot_total_cost   /c2.curr_exchng_rate)
                          FROM qot q2,
                            currency c2
                          WHERE q2.qot_currency       = c2.curr_code
                          AND q2.rfq_internal_ref_no IN
                            (SELECT rfq_internal_ref_no
                            FROM rfq r3
                            WHERE byb_branch_code != :matchId
                            AND r3.rfq_ref_no      = r2.rfq_ref_no
                            )
                          AND qot_total_cost > 0
                          ), 2)
                         --===========END REMOVE FOR ALL SAVINGS RATHER THAN JUST MATCH BEING CHEAPER===============
                        )
                      )
                      )";

        $params = array('startDate' => $startDate, 'endDate' => $endDate, 'matchId' => $this->config->shipserv->match->buyerId, 'buyerId' => $buyerId);
        return array('sql' => $sql, 'params' => $params);
    }

    private function getMDAInsertionSQL() {
        return "INSERT
                INTO MATCH_DAILY_ADOPTION_STAT
                  (
                    MDA_DATE,
                    MDA_ACTUAL_SAVINGS,
                    MDA_POTENTIAL_SAVINGS,
                    MDA_TOTAL_RFQS,
                    MDA_BYB_BRANCH_CODE,
                    MDA_MATCH_POS,
                    MDA_RFQS_GENERATED,
                    MDA_RFQS_TO_MATCH,
                    MDA_QUOTES_ISSUED,
                    MDA_RFQS_WITH_QUOTE
                  )
                  VALUES
                  (
                    :dateOfStat,
                    0,
                    :potentialSavings,
                    :totalRFQ,
                    :buyerId,
                    :matchPOS,
                    :matchRFQs,
                    :rfqsToMatch,
                    :quotesIssued,
                    :rfqsWithQuote
                  )";
    }

}

class MyDateTime extends DateTime {

    public function setTimestamp($timestamp) {
        $date = getdate((int) $timestamp);
        $this->setDate($date['year'], $date['mon'], $date['mday']);
        $this->setTime($date['hours'], $date['minutes'], $date['seconds']);
    }

    public function getTimestamp() {
        return $this->format('U');
    }

    /**
     * Function to return an array containing the dates from start to end in an array
     * @param type $sStartDate
     * @param type $sEndDate
     * @return array of oracle formated dates
     */
    public function getDaysToArray($sStartDate, $sEndDate) {

        $sStartDate = date("Y-m-d", strtotime($sStartDate));
        $sEndDate = date("Y-m-d", strtotime($sEndDate));
        // Start the variable off with the start date
        $aDays[] = date('d-M-Y', strtotime($sStartDate));

        $sCurrentDate = $sStartDate;

        while (strtotime($sCurrentDate) < strtotime($sEndDate)) {
            $sCurrentDate = date("d-M-Y", strtotime("+1 day", strtotime($sCurrentDate)));
            $aDays[] = $sCurrentDate;
        }

        return $aDays;
    }

}

class Logger {

    private function __construct() {

    }

    public static function log($msg, $noNewLine = false) {
        echo date('Y-m-d H:i:s') . "\t" . $msg;
        if ($noNewLine == false)
            echo "\n";
    }

    public static function logSimple($msg, $noNewLine = false) {
        echo $msg;
        if ($noNewLine == false)
            echo "\n";
    }

    public static function newLine() {
        echo "\n";
    }

}

