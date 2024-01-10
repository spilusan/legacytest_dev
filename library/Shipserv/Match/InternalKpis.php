<?php

class Shipserv_Match_InternalKpis extends Shipserv_Object {

    private $db;
    private $reporting;

    /**
     * Start and end date for stats
     * @var Oracle Datew
     */
    public $startDate;
    public $endDate;
    private $buyerId = "";

    public function __construct() {
        $this->db = Shipserv_Helper_Database::getDb();
        $this->reporting = Shipserv_Helper_Database::getSsreport2Db();
        $this->startDate = '01-JAN-2012';
        $this->endDate = date("d-M-Y");
    }

    public function setStartDate($d, $m, $y) {
        if (preg_match('#^[0-9]+$#', $m)) {
            $month = strtoupper(date("M", mktime(0, 0, 0, $m, 10)));
        } else {
            $month = $m;
        }

        $day = str_pad((int) $d, 2, "0", STR_PAD_LEFT);
        $this->startDate = "$day-$month-$y";
    }

    /**
     *
     * @param Integer $d
     * @param String/Integer $m Accepts JAN, FEB or 01, 02 or 1,2
     * @param Integer $y Accepts 2/4 digit year representation!
     */
    public function setEndDate($d, $m, $y) {
        if (preg_match('#^[0-9]+$#', $m)) {
            $month = strtoupper(date("M", mktime(0, 0, 0, $m, 10)));
        } else {
            $month = $m;
        }

        $day = str_pad((int) $d, 2, "0", STR_PAD_LEFT);
        $this->endDate = "$day-$month-$y";
    }

    /**
     *
     * @param integer $buyerId
     */
    public function setBuyer($buyerId) {
        if (preg_match('#^[0-9]+$#', $buyerId)) {
            $this->buyerId = $buyerId;
        }
    }

    /**
     * Number of RFQs that Match was asked to process
     * @return integer
     */
    public function statRfqsSentToMatch() {
        $query = "SELECT COUNT(*)
                    FROM rfq
                    WHERE spb_branch_code = 999999
                    AND rfq_submitted_date BETWEEN to_date(:startDate, 'd-m-y') AND to_date(:endDate";
        if (!empty($this->buyerId)) {
            $query .= "AND byb_branch_code = " . $this->buyerId;
        }
        $result = $this->reporting->fetchOne($query);
        return $result;
    }

    public function statsRfqsSentToMatchGroupedByBuyer() {

    }

    /**
     * Number of RFQs sent out by Match to matched suppliers
     * @return Integer
     */
    public function statRfqsSentFromMatch() {
        $query = "SELECT COUNT(*)
                    FROM rfq r, rfq r2
                    WHERE r.byb_branch_code      = 11107
                    AND r.rfq_pom_source  = r2.rfq_internal_ref_no
                    AND r.rfq_pom_source IS NOT NULL
                    AND r.rfq_submitted_date BETWEEN {$this->startDate} AND {$this->endDate}";

        if (!empty($this->buyerId)) {
            $query .= " AND r2.byb_branch_code = " . $this->buyerId;
        }
        $result = $this->reporting->fetchOne($query);
        return $result;
    }

    public function statTotalQuotesBack() {
        $query = "SELECT COUNT(*)
                    FROM QOT q,
                      RFQ r,
                      RFQ r2
                    WHERE q.rfq_internal_ref_no = r.rfq_internal_ref_no
                    AND r.rfq_pom_source        = r2.rfq_internal_ref_no
                    AND q.qot_submitted_date   IS NOT NULL
                    AND r.byb_branch_code       = 11107
                    AND q.qot_total_cost        > 0
                    AND r.rfq_submitted_date BETWEEN {$this->startDate} AND {$this->endDate}";

        if (!empty($this->buyerId)) {
            $query .= " AND r2.byb_branch_code = " . $this->buyerId;
        }

        $result = $this->reporting->fetchOne($query);
        return $result;
    }

    public function statCountRFQsWithQuotes() {
        $query = "
            SELECT COUNT(DISTINCT r.rfq_ref_no)
            FROM RFQ r
            WHERE r.spb_branch_code    = 999999
            AND r.rfq_internal_ref_no IN
              (SELECT rfq_pom_source
              FROM rfq r2,
                qot q
              WHERE r2.rfq_internal_ref_no = q.rfq_internal_ref_no
              AND r2.byb_branch_code       = 11107
              AND q.qot_total_cost         > 0
              AND q.qot_submitted_date    IS NOT NULL
              )
            AND r.rfq_submitted_date BETWEEN {$this->startDate} AND {$this->endDate}";

        if (!empty($this->buyerId)) {
            $query .= " AND r.byb_branch_code = " . $this->buyerId;
        }

        $result = $this->reporting->fetchOne($query);
        return $result;
    }

    public function statAvgTimeToProcessRFQ() {
        $query = "SELECT ROUND(AVG((r.rfq_submitted_date - r2.rfq_submitted_date) * 24),0) AS HoursToProcess
                    FROM rfq r,
                      rfq r2
                    WHERE r.rfq_pom_source = r2.rfq_internal_ref_no
                    AND r.rfq_pom_source  IS NOT NULL
                    AND r2.spb_branch_code = 999999
                    AND r.byb_branch_code  = 11107
                    AND r.rfq_submitted_date BETWEEN {$this->startDate} AND {$this->endDate}";

        if (!empty($this->buyerId)) {
            $query .= " AND r.byb_branch_code = " . $this->buyerId;
        }

        $result = $this->reporting->fetchOne($query);
        return $result;
    }

    /**
     * Returns the average time in days for a forwarded RFQ to receive a quote.
     * @return float;
     */
    public function statAvgTimeForQuote() {
        $query = "SELECT AVG(q.qot_submitted_date - r.rfq_submitted_date)
                    FROM qot q,
                      rfq r,
                      rfq r2
                    WHERE q.rfq_internal_ref_no = r.rfq_internal_ref_no
                    AND r.rfq_pom_source        = r2.rfq_internal_ref_no
                    AND r2.spb_branch_code      = 999999
                    AND q.byb_branch_code       = 11107
                    AND r.rfq_submitted_date BETWEEN {$this->startDate} AND {$this->endDate}";

        if (!empty($this->buyerId)) {
            $query .= " AND r2.byb_branch_code = " . $this->buyerId;
        }

        $result = $this->reporting->fetchOne($query);

        return $result;
    }

    public function statTotalMatchPOs() {
        $this->config = Zend_Registry::get('config');
        $this->dbRaw = oci_pconnect(
                $this->config->resources->multidb->ssreport2->username, $this->config->resources->multidb->ssreport2->password, $this->config->resources->multidb->ssreport2->dbname
        );

        if ($_SERVER['APPLICATION_ENV'] == "development") {
            $tableSuffix = "@moses_link";
        } else {
            $tableSuffix = "";
        }

        $sql = "
            CREATE TABLE tmp_matchrfq AS
                SELECT rfq1.byb_branch_code,
                rfq1.rfq_ref_no,
                rfq2.rfq_submitted_date,
                rfq2.spb_branch_code
              FROM rfq rfq1,
                rfq rfq2
              WHERE rfq1.rfq_internal_ref_no = rfq2.rfq_pom_source
              AND rfq2.rfq_pom_source       IS NOT NULL
              AND rfq2.byb_branch_code       = 11107
              AND rfq1.spb_branch_code       = 999999
              AND rfq1.rfq_submitted_date BETWEEN  {$this->startDate} and {$this->endDate} ";
        if (!empty($this->buyerId)) {
            $sql .= " AND rfq1.byb_branch_code        = " . $this->buyerId;
        }

        $oSt = oci_parse($this->dbRaw, $sql);
        oci_execute($oSt);
        //oci_free_statement($oSt);
        $e = oci_error();


        $sql = "Create table tmp_subsequent_submissions as
                SELECT r.byb_branch_code,
                999999       AS newMatch ,
                r.rfq_ref_no AS newRFQNo,
                r.rfq_submitted_date,
                p.spb_branch_code,
                p.ord_internal_ref_no
              FROM rfq r ,
                qot q,
                ord p,
                tmp_matchrfq
              WHERE r.rfq_internal_ref_no = q.RFQ_INTERNAL_REF_NO
              AND p.qot_internal_ref_no   = q.qot_internal_ref_no
              AND r.rfq_ref_no            = p.ord_ref_no
              AND r.rfq_ref_no           IS NOT NULL
              AND r.rfq_ref_no            = tmp_matchrfq.rfq_ref_no
              AND r.byb_branch_code       = tmp_matchrfq.byb_branch_code
              AND p.spb_branch_code       = tmp_matchrfq.spb_branch_code
              AND r.rfq_submitted_date    > tmp_matchrfq.rfq_submitted_date
              AND r.rfq_submitted_date BETWEEN {$this->startDate} and {$this->endDate} ";


        $oSt = oci_parse($this->dbRaw, $sql);
        oci_execute($oSt);
        //oci_free_statement($oSt);
        $e = oci_error();

        $sql = "INSERT INTO tmp_subsequent_submissions
                SELECT o.byb_branch_code,
                  999999,
                  ord_ref_no,
                  ord_submitted_date,
                  o.spb_branch_code,
                  ord_internal_ref_no
                FROM ord o,
                  tmp_matchrfq t
                WHERE o.ord_ref_no         = t.rfq_ref_no
                AND o.qot_internal_ref_no IS NULL
                AND o.byb_branch_code      = t.byb_branch_code
                AND o.spb_branch_code      = t.spb_branch_code
                AND o.ord_submitted_date   > t.rfq_submitted_date
 ";


        $oSt = oci_parse($this->dbRaw, $sql);
        oci_execute($oSt);
        $e = oci_error();
        //oci_free_statement($oSt);

        $sql = "Select Count(DISTINCT ord_internal_ref_no) as POCOUNT  from tmp_subsequent_submissions";

        $onSt = oci_parse($this->dbRaw, $sql);
        oci_execute($onSt);
        $e = oci_error();

        oci_fetch($onSt);
        $e = oci_error();

        $poCount = oci_result($onSt, 'POCOUNT');
        $e = oci_error();

        $sql = "Drop table tmp_matchrfq";
        $oSt = oci_parse($this->dbRaw, $sql);
        oci_execute($oSt);

        $sql = "Drop table tmp_subsequent_submissions";
        $oSt = oci_parse($this->dbRaw, $sql);
        oci_execute($oSt);

        oci_free_statement($oSt);
        oci_close($this->dbRaw);
        unset($oSt);
        unset($this->dbRaw);
        return $poCount;
    }

    public function statTotalPOs() {
        $sql = "SELECT COUNT(*)
                FROM ORD p,
                  qot q,
                  rfq r
                WHERE p.qot_internal_ref_no = q.qot_internal_ref_no
                AND q.rfq_internal_ref_no   = r.rfq_internal_ref_no
                AND r.rfq_submitted_date BETWEEN {$this->startDate} and {$this->endDate}  ";

        if (!empty($this->buyerId)) {
            $query .= " AND r.byb_branch_code = " . $this->buyerId;
        }

        $result = $this->reporting->fetchOne($sql);

        return $result;
    }

    public function getAllStatsForBuyers() {
        $sql = "SELECT MDA_BYB_BRANCH_CODE AS Buyer_TNID,
                byb_Name                 AS Buyer_Name,
                BYB_COUNTRY_CODE,
                Mda_Total_Rfqs           AS Total_Rfqs,
                Mda_Rfqs_To_Match        AS Rfqs_Submitted_To_Match,
                ROUND((Mda_Rfqs_Generated / Mda_Rfqs_To_Match), 1)  As Suppliers_Per_Rfq,
                CASE
                  WHEN Mda_Rfqs_To_Match > 0
                  AND Mda_Total_Rfqs     > 0
                  THEN ROUND((Mda_Rfqs_To_Match / Mda_Total_Rfqs) * 100 ,1)
                  ELSE 0
                END                AS Adoption_Rate,
                Mda_Rfqs_Generated AS Rfqs_Generated,
                Mda_Quotes_Issued  AS Quotes_Issued,
                CASE
                  WHEN Mda_Quotes_Issued > 0
                  AND Mda_Rfqs_Generated > 0
                  THEN ROUND( (Mda_Quotes_Issued / Mda_Rfqs_Generated ) * 100, 2)
                  ELSE 0
                END           AS Quote_Rate,
                Mda_Match_Pos AS Match_Pos,
                CASE
                  WHEN Mda_Match_Pos    > 0
                  AND Mda_Rfqs_To_Match > 0
                  THEN ROUND((Mda_Match_Pos / Mda_Rfqs_To_Match) * 100 ,2)
                  ELSE 0
                END                   AS Win_Rate,
                Mda_Potential_Savings AS Potential_Savings
              FROM
                (Select Mda_Byb_Branch_Code,
                  byb_name,
                  byb_country_code,
                  SUM(Mda_Actual_Savings)    AS Mda_Actual_Savings ,
                  SUM(Mda_Potential_Savings) AS Mda_Potential_Savings ,
                  SUM(Mda_Total_Rfqs)        AS Mda_Total_Rfqs,
                  SUM(Mda_Match_Pos)         AS Mda_Match_Pos,
                  SUM(Mda_Rfqs_Generated)    AS Mda_Rfqs_Generated,
                  SUM(Mda_Rfqs_To_Match)     AS Mda_Rfqs_To_Match,
                  SUM(Mda_Quotes_Issued)     AS Mda_Quotes_Issued
                FROM Match_Daily_Adoption_Stat,
                  buyer
                Where Match_Daily_Adoption_Stat.Mda_Byb_Branch_Code = Buyer.Byb_Branch_Code
                And Match_Daily_Adoption_Stat.Mda_Date Between to_date(:startDate) And to_date(:endDate)
                AND BYB_IS_TEST_ACCOUNT = 0
                AND Mda_Rfqs_To_Match > 0
                GROUP BY Mda_Byb_Branch_Code,
                  Byb_Name, byb_country_code

                ) Order By  Mda_Rfqs_To_Match Desc
              ";

        $sql2 = "
WITH launch_date_table AS
(
  SELECT 
    mdas1.mda_byb_branch_code AS byb_branch_code,
    CASE 
      WHEN MIN(mdas1.MDA_DATE) > to_date(:startDate) THEN MIN(mdas1.MDA_DATE)
      ELSE to_date(:startDate)
    END
    AS LAUNCH_DATE
  FROM 
    match_daily_adoption_stat mdas1
  WHERE 
    mdas1.mda_rfqs_to_match>0
  GROUP BY mdas1.mda_byb_branch_code
)

SELECT MDA_BYB_BRANCH_CODE AS Buyer_TNID,
  byb_Name                 AS Buyer_Name,
  BYB_COUNTRY_CODE,
  Mda_Total_Rfqs           AS Total_Rfqs,
  Mda_Rfqs_To_Match        AS Rfqs_Submitted_To_Match,
  ROUND((Mda_Rfqs_Generated / Mda_Rfqs_To_Match), 1)  As Suppliers_Per_Rfq,
  CASE
    WHEN Mda_Rfqs_To_Match > 0
    AND Mda_Total_Rfqs     > 0
    THEN ROUND((Mda_Rfqs_To_Match / Mda_Total_Rfqs) * 100 ,1)
    ELSE 0
  END                AS Adoption_Rate,
  Mda_Rfqs_Generated AS Rfqs_Generated,
  Mda_Quotes_Issued  AS Quotes_Issued,
  CASE
    WHEN Mda_Quotes_Issued > 0
    AND Mda_Rfqs_Generated > 0
    THEN ROUND( (Mda_Quotes_Issued / Mda_Rfqs_Generated ) * 100, 2)
    ELSE 0
  END           AS Quote_Rate,
  Mda_Match_Pos AS Match_Pos,
  CASE
    WHEN Mda_Match_Pos    > 0
    AND Mda_Rfqs_To_Match > 0
    THEN ROUND((Mda_Match_Pos / Mda_Rfqs_To_Match) * 100 ,2)
    ELSE 0
  END                   AS Win_Rate,
  Mda_Potential_Savings AS Potential_Savings
FROM
  (
SELECT 
  byb_branch_code Mda_Byb_Branch_Code,
  (
    SELECT TO_DATE(launch_date_table.launch_date)
    FROM launch_date_table
    WHERE launch_date_table.byb_branch_code=a.byb_branch_code
  ) LAUNCH_DATE,
  TO_DATE(:startDate) START_DATE,
  TO_DATE(:endDate) END_DATE,
  (
    SELECT 
      SUM(mda_total_rfqs) 
    FROM 
      match_daily_adoption_stat 
    WHERE 
      Mda_Byb_Branch_Code=a.byb_branch_code
      AND Mda_Date 
        Between 
          (
            SELECT launch_date_table.launch_date
            FROM launch_date_table
            WHERE launch_date_table.byb_branch_code=a.byb_branch_code
          ) 
          And
          to_date(:endDate)
  ) AS Mda_Total_Rfqs
  ,
  a.Mda_Total_Rfqs_old,
  a.*
FROM
(
  Select 
    Mda_Byb_Branch_Code byb_branch_code,
    byb_name,
    byb_country_code,
    SUM(Mda_Actual_Savings)    AS Mda_Actual_Savings ,
    SUM(Mda_Potential_Savings) AS Mda_Potential_Savings ,
    SUM(Mda_Total_Rfqs)        AS Mda_Total_Rfqs_old,
    SUM(Mda_Match_Pos)         AS Mda_Match_Pos,
    SUM(Mda_Rfqs_Generated)    AS Mda_Rfqs_Generated,
    SUM(Mda_Rfqs_To_Match)     AS Mda_Rfqs_To_Match,
    SUM(Mda_Quotes_Issued)     AS Mda_Quotes_Issued
  FROM Match_Daily_Adoption_Stat,
    buyer
  Where Match_Daily_Adoption_Stat.Mda_Byb_Branch_Code = Buyer.Byb_Branch_Code
  And Match_Daily_Adoption_Stat.Mda_Date Between to_date(:startDate) And to_date(:endDate)
  AND BYB_IS_TEST_ACCOUNT = 0
  --AND Mda_Rfqs_To_Match > 0
  GROUP BY Mda_Byb_Branch_Code,
    Byb_Name, byb_country_code
) a
  ) Order By  Mda_Rfqs_To_Match Desc
        
        ";
        
        $params = array('startDate' => strtoupper($this->startDate), 'endDate' => strtoupper($this->endDate));

        $results = $this->reporting->fetchAll($sql, $params);

        return $results;
    }

}

