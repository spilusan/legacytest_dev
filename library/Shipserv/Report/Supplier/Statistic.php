<?php
class Shipserv_Report_Supplier_Statistic extends Shipserv_Report
{
	public $startDate = null;
	public $endDate = null;
	public $periodForTrends;

	function __construct(){
		Shipserv_DateTime::monthsAgo(3, $startDate, $endDate);
		$this->startDate = $startDate;
		$this->endDate = $endDate;
	}

	public function setParams( $params = null)
	{

		if( $params !== null )
		{

			if( $params['fromDate'] != "" )
			{
				$tmp = explode("/", $params['fromDate']);
				$this->startDate = new DateTime();
				$this->startDate->setDate($tmp[2], (int)$tmp[1], (int)$tmp[0]);

			}

			if( $params['toDate'] != "" )
			{
				$tmp = explode("/", $params['toDate']);
				$this->endDate = new DateTime();
				$this->endDate->setDate($tmp[2],(int) $tmp[1], (int)$tmp[0]);
			}

			$this->params = $params;
		}
	}

	public function getAccountManager()
	{
		$sql = "
			SELECT
				DISTINCT SPB_ACCT_MNGR_NAME NAME
			FROM
				supplier_branch@livedb_link
			WHERE
				SPB_ACCT_MNGR_NAME IS NOT null
			ORDER BY
				spb_acct_mngr_name ASC NULLS LAST

		";

		$db = $this->getDbByType('ssreport2');
		return $db->fetchAll($sql);
	}

	public function getRegion()
	{
		$sql = "
			SELECT DISTINCT SPB_ACCOUNT_REGION REGION FROM supplier_branch
			ORDER BY SPB_ACCOUNT_REGION ASC
		";

		$db = $this->getDb();
		return $db->fetchAll($sql);
	}

	public function getCountry()
	{
		$sql = "
			SELECT
				DISTINCT
					cnt_name COUNTRY
					, spb_country COUNTRY_CODE
			FROM
				supplier_branch
				JOIN country
					ON (spb_country=cnt_country_code)
			WHERE
				SPB_COUNTRY IS NOT null
			ORDER BY
				cnt_name
		";

		$db = $this->getDb();
		return $db->fetchAll($sql);
	}

	public function getIntegrationType()
	{
		$sql = "
			SELECT DISTINCT SPB_INTERFACE INTEGRATION_TYPE FROM supplier
				WHERE SPB_INTERFACE IS NOT null
		";

		$db = $this->getDbByType('ssreport2');
		return $db->fetchAll($sql);
	}


	public function getData()
	{
		//$this->tnid = 58341;
		$cond = [];

		if( $this->params['tnid'] != "" )
		$cond[] = 's.spb_branch_code=' . $this->params['tnid'];

		if( $this->params['accountManager'] != "" )
			$cond[] = "sb.SPB_ACCT_MNGR_NAME='" . $this->params['accountManager'] . "'";

		if( $this->params['spbName'] != "" )
			$cond[] = "LOWER(sb.spb_name) LIKE '%" . strtolower($this->params['spbName']) . "%'";

		if( $this->params['region'] != "" )
			$cond[] = "sb.SPB_ACCOUNT_REGION='" . $this->params['region']. "'";;

		if( $this->params['country'] != "" )
			$cond[] = "sb.SPB_COUNTRY='" . $this->params['country']. "'";;

		if( $this->params['type'] != "" )
			$cond[] = "s.SPB_INTERFACE='" . $this->params['type']. "'";;


		$supplierSql ="
		SELECT
			tbl.*
			, CASE
	            WHEN tbl.directory_listing_level_id = 4 THEN TO_CHAR('Premium')
	            ELSE 'Basic'
	          END SPB_LISTING_LEVEL
		FROM
		(
			SELECT
				sb.spb_branch_code
				, sb.spb_name
				, sb.SPB_REGION
				, sb.SPB_ACCT_MNGR_NAME
				, sb.SPB_COUNTRY
				, s.SPB_INTERFACE
				, sb.SPB_MONETIZATION_PERCENT
				, sb.SPB_PCS_SCORE
				, c.cnt_name country_name
				, sb.directory_entry_status SPB_LISTING_STATUS
				, sb.directory_listing_level_id
			FROM
				supplier_branch@livedb_link sb JOIN supplier s
				ON (s.spb_branch_code=sb.spb_branch_code)
					JOIN country@livedb_link c
					ON (c.cnt_country_code=spb_country)
			WHERE
				sb.SPB_ACCOUNT_DELETED = 'N' and
				sb.spb_test_account = 'N' and
				sb.SPB_BRANCH_CODE <= 999999
		";

		if( count($cond) > 0 )
			$supplierSql .= "AND " . implode(" AND ", $cond);

		if( $this->params['matchLevel'] == 'prime-supplier'){
			$supplierSql .= "
			AND sb.spb_branch_code IN(
				SELECT
				  DISTINCT mso_owner_id
				FROM
				  match_supplier_rfq@livedb_link JOIN match_supplier_keyword_set@livedb_link
				  ON (MSS_ID=MSR_MSS_ID)
				    JOIN match_supplier_keyword_owner@livedb_link
				      ON (mso_mss_id=mss_id)
				WHERE
				  mso_owner_type='SPB'
			)
			";
		}

		if( $this->params['matchLevel'] == 'matched'){
			$supplierSql .= "
			AND sb.spb_branch_code IN(
				SELECT DISTINCT spb_branch_code
				FROM match_b_rfq_forwarded_by_match
				WHERE
					rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.999999
			)
			";
		}



		$supplierSql .= ') tbl';

		$sql = "
WITH
all_supplier AS (
  " . $supplierSql . "
)
,
gmv_trend_03_mo AS (
  SELECT
    o.spb_branch_code
    , SUM(o.ord_total_cost_usd) total_gmv
  FROM
    ord_traded_gmv o JOIN ord oo ON (oo.ord_internal_ref_no=o.ord_internal_ref_no)
  WHERE
    o.ord_orig_submitted_date BETWEEN add_months(trunc(sysdate,'mm'),-1) - 90 AND add_months(trunc(sysdate,'mm'),-1)
    AND EXISTS (
      SELECT null FROM all_supplier ab WHERE ab.spb_branch_code=o.spb_branch_code
    )
  GROUP BY
    o.spb_branch_code
)
,
gmv_trend_36_mo AS (
  SELECT
    o.spb_branch_code
    , SUM(o.ord_total_cost_usd) total_gmv
  FROM
    ord_traded_gmv o JOIN ord oo ON (oo.ord_internal_ref_no=o.ord_internal_ref_no)
  WHERE
  	o.ord_orig_submitted_date BETWEEN add_months(trunc(sysdate,'mm'),-1) - 180 AND add_months(trunc(sysdate,'mm'),-1) - 90
    AND EXISTS (
      SELECT null FROM all_supplier ab WHERE ab.spb_branch_code=o.spb_branch_code
    )
  GROUP BY
    o.spb_branch_code

), pricing AS
(
SELECT 
  sbr_sf_source_type
  ,sbr_rate_standard
  ,sbr_rate_target
  ,sbr_spb_branch_code
FROM
  SUPPLIER_BRANCH_RATE@livedb_link sbr
WHERE 
  sbr_valid_from <= SYSDATE
  and (sbr_valid_till>SYSDATE or sbr_valid_till is null)
  AND EXISTS (
      SELECT null FROM all_supplier ab WHERE ab.spb_branch_code=sbr.sbr_spb_branch_code
    )
), transaction_base AS (
  SELECT
    r.spb_branch_code
    , r.byb_branch_code
    , (select parent_branch_code from buyer where byb_branch_code = r.byb_branch_code and rownum = 1) parent_branch_code
    , r.rfq_event_hash
    , q.qot_internal_ref_no
    , o.ord_internal_ref_no
    , p.ord_internal_ref_no poc_internal_ref_no
    , q.qot_total_cost_usd own_quote_price
    , otg.FINAL_TOTAL_COST_USD ord_total_cost_usd
    , otg.FINAL_TOTAL_VBP_USD ord_total_vbp_usd
    , ( q.qot_submitted_date - r.rfq_submitted_date ) time_to_quote
    , (
      CASE WHEN rr.rfq_resp_date IS NOT NULL THEN 
      rr.rfq_resp_date - r.rfq_submitted_date ELSE q.qot_submitted_date - r.rfq_submitted_date END
      ) response_time
    , (
      CASE WHEN q.qot_total_cost_usd=0 OR rr.rfq_resp_date IS NOT null THEN 1 END
    ) is_declined
  	, (
  	  CASE WHEN q.qot_total_cost_usd>0 THEN 1 END
  	) is_quoted
  	, (
  	  CASE WHEN q.qot_internal_ref_no is NULL THEN 1 END
  	) is_unactioned
    , (
      CASE WHEN o.ord_total_cost_usd>0 THEN o.byb_branch_code END 
    ) ord_byb_branch_code
    , MIN(q2.qot_total_cost_usd) cheapest_quote_price
    , (
      SELECT 
 		  (
		    CASE WHEN COUNT(CASE WHEN HAS_QOT = 1 THEN 1 END) > 1
		    AND COUNT(CASE WHEN spb_branch_code = r.spb_branch_code AND HAS_QOT = 1 THEN 1 END) > 0
		    AND COUNT(CASE WHEN spb_branch_code = r.spb_branch_code AND HAS_PO = 1 THEN 1 END) > 0 THEN 1 END
		  ) winner
 		FROM
  			linked_rfq_qot_po l 
		WHERE 
  			rfq_event_hash = r.rfq_event_hash
    ) supplier_won_competition
    , (
      SELECT 
 		  (
		    CASE WHEN COUNT(CASE WHEN HAS_QOT = 1 THEN 1 END) > 1
		    AND COUNT(CASE WHEN spb_branch_code = r.spb_branch_code AND HAS_QOT = 1 THEN 1 END) > 0
		    AND COUNT(CASE WHEN HAS_PO = 1 THEN 1 END) > 0 THEN 1 END
		  ) winner
 		FROM
  			linked_rfq_qot_po l 
		WHERE 
  			rfq_event_hash = r.rfq_event_hash
    ) has_winner
    , (
      SELECT 
 		  (
		    CASE WHEN COUNT(CASE WHEN HAS_QOT = 1 AND IS_QOT_COMPLETE = 1 THEN 1 END) > 1
		    AND COUNT(CASE WHEN spb_branch_code = r.spb_branch_code AND HAS_QOT = 1 AND IS_QOT_COMPLETE = 1 THEN 1 END) > 0
		    THEN 1 END
		  ) competitive
 		FROM
  			linked_rfq_qot_po l 
		WHERE 
  			rfq_event_hash = r.rfq_event_hash
    ) price_competitive
    , (
      SELECT 
 		  (
		    CASE WHEN COUNT(CASE WHEN HAS_QOT = 1 AND IS_QOT_COMPLETE = 1 THEN 1 END) > 1
		    AND COUNT(CASE WHEN spb_branch_code = r.spb_branch_code AND HAS_QOT = 1 AND IS_QOT_COMPLETE = 1 AND IS_CHEAPEST = 1 THEN 1 END) > 0
		    THEN 1 END
		  ) cheapest_competitive
 		FROM
  			linked_rfq_qot_po l 
		WHERE 
  			rfq_event_hash = r.rfq_event_hash
    ) cheapest_competitive
	, (
		SELECT
		  (
			  CASE WHEN
			  	MIN(CASE WHEN l.spb_branch_code = r.spb_branch_code THEN q.QOT_TOTAL_COST_USD END) < MIN(CASE WHEN l.spb_branch_code != r.spb_branch_code THEN q.QOT_TOTAL_COST_USD END) 
		  	  THEN
			  	  (MIN(CASE WHEN l.spb_branch_code != r.spb_branch_code THEN q.QOT_TOTAL_COST_USD END)
			      - MIN(CASE WHEN l.spb_branch_code = r.spb_branch_code THEN q.QOT_TOTAL_COST_USD END))
			      / MIN(CASE WHEN l.spb_branch_code != r.spb_branch_code THEN q.QOT_TOTAL_COST_USD END) * 100
		      END
		  ) cheaper
		FROM
		  linked_rfq_qot_po l join qot q on (q.qot_internal_ref_no = l.qot_internal_ref_no)
		where 
		  l.rfq_event_hash = r.rfq_event_hash
		  and is_qot_complete = 1
	  ) cheaper
  FROM
    rfq r LEFT OUTER JOIN qot q
      ON (r.rfq_internal_ref_no=q.rfq_internal_ref_no AND r.spb_branch_code=q.spb_branch_code AND q.qot_is_latest=1)
        LEFT OUTER JOIN ord o
          ON (q.qot_internal_ref_no=o.qot_internal_ref_no )
		  	LEFT OUTER JOIN ord_traded_gmv otg
				ON (otg.ord_original=o.ord_internal_ref_no)
            LEFT OUTER JOIN poc p
              ON (o.ord_internal_ref_no=p.ord_internal_ref_no)
    LEFT OUTER JOIN rfq r2
      ON (
        r.rfq_event_hash=r2.rfq_event_hash
        AND r.rfq_line_count>0
		AND DECODE(r.rfq_line_count,0,0,q.qot_line_count/r.rfq_line_count)=1
        AND r.spb_branch_code!=r2.spb_branch_code
      )
        LEFT OUTER JOIN qot q2
          ON (
            r2.rfq_internal_ref_no=q2.rfq_internal_ref_no
            -- price competitiveness
            AND (
              r.rfq_line_count > 0
              AND r2.rfq_line_count > 0
              AND q.qot_line_count/r.rfq_line_count >= q2.qot_line_count/r2.rfq_line_count
            )
          )
    LEFT OUTER JOIN rfq_resp rr
      ON (r.rfq_internal_ref_no=rr.rfq_internal_ref_no AND rr.rfq_resp_sts='DEC')
  WHERE
    r.spb_branch_code IN (SELECT spb_branch_code FROM all_supplier)
    AND r.rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.999999

  GROUP BY
    r.spb_branch_code
    , r.byb_branch_code
    , r.rfq_event_hash
    , q.qot_internal_ref_no
    , o.ord_internal_ref_no
    , p.ord_internal_ref_no
    , q.qot_total_cost_usd
	, otg.FINAL_TOTAL_COST_USD
    , otg.FINAL_TOTAL_VBP_USD
    , (
      CASE WHEN q.qot_total_cost_usd=0 OR rr.rfq_resp_date IS NOT null THEN 1 END
    )
	, (
  	  CASE WHEN q.qot_total_cost_usd>0 THEN 1 END
  	)
 	, (
  	  CASE WHEN o.ord_total_cost_usd>0 THEN o.byb_branch_code END
  	)
    , ( q.qot_submitted_date - r.rfq_submitted_date )
    , (
      CASE WHEN rr.rfq_resp_date IS NOT NULL THEN 
      rr.rfq_resp_date - r.rfq_submitted_date ELSE q.qot_submitted_date - r.rfq_submitted_date END
      ) 
)
--SELECT * FROM transaction_base;
,
transactions AS (
SELECT
  tb.*
  , (
      CASE
        WHEN tb.own_quote_price < cheapest_quote_price
        THEN 1
        ELSE 0
      END
  ) competitive_quote
FROM
  transaction_base tb
)
,
result AS
(
	SELECT
	  transactions.spb_branch_code
	  , ss.spb_name
	  , ss.SPB_REGION
	  , ss.SPB_ACCT_MNGR_NAME
	  , ss.SPB_COUNTRY
	  , ss.SPB_INTERFACE
	  , ss.SPB_MONETIZATION_PERCENT
	  , ss.SPB_PCS_SCORE
	  , ss.country_name
	  , ss.SPB_LISTING_STATUS
	  , ss.SPB_LISTING_LEVEL
	  , COUNT( rfq_event_hash ) total_rfq
	  , COUNT( DISTINCT rfq_event_hash ) total_rfq_event
	  , COUNT( DISTINCT qot_internal_ref_no ) total_qot
	  , SUM( is_declined ) total_declines
	  --, COUNT( DISTINCT ord_internal_ref_no ) total_ord
	  ,(
	     SELECT COUNT(DISTINCT ord_internal_ref_no) from ord_traded_gmv where ord_orig_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.999999
	     and spb_branch_code = transactions.spb_branch_code
	  ) total_ord
	  , COUNT( DISTINCT poc_internal_ref_no ) total_poc
	  , COUNT( DISTINCT byb_branch_code ) total_buyer
	  , COUNT( DISTINCT parent_branch_code ) total_parent_buyer
	  , COUNT( DISTINCT ord_byb_branch_code) total_buyer_ordered
	  , COUNT( DISTINCT CASE WHEN ord_total_cost_usd > 0 THEN parent_branch_code END) total_parent_buyer_ordered
	  , (
	  		SELECT SUM(FINAL_TOTAL_COST_USD) FROM ord_traded_gmv WHERE spb_branch_code=transactions.spb_branch_code AND ord_orig_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.999999
	  ) total_gmv
	  , SUM(competitive_quote) total_is_cheapest
	  , SUM(competitive_quote) / (COUNT( DISTINCT rfq_event_hash )) * 100 price_competitiveness
	  , MIN(ss.spb_monetization_percent) po_fee
	  , MIN(ss.spb_monetization_percent/100) * (
	  		SELECT SUM(FINAL_TOTAL_COST_USD) FROM ord_traded_gmv WHERE spb_branch_code=transactions.spb_branch_code AND ord_orig_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.999999
	  ) estimated_revenue
	  --, (ROUND(
	  --     ( ( COUNT( DISTINCT qot_internal_ref_no ) ) + ( SUM( is_declined ) ) )
	  --    / COUNT( DISTINCT rfq_event_hash ) * 100
	  --    ,2)
	  ---) response_rate
	  ,(
	  	ROUND(
	  		(COUNT( DISTINCT rfq_event_hash ) - SUM(is_unactioned))
	  		/ COUNT( DISTINCT rfq_event_hash ) * 100
	  		,2
	  		)
	   ) response_rate
	  , (ROUND(
	       SUM(IS_QUOTED)
	      / COUNT( DISTINCT rfq_event_hash ) * 100
	      , 2)
	  ) quote_rate
	  , SUM(is_unactioned) is_unactioned
	  , ROUND(AVG(time_to_quote), 2) avg_time_to_quote
	  , ROUND(COUNT(CASE WHEN response_time <=3 THEN 1 END) / COUNT( DISTINCT rfq_event_hash ) * 100, 2) responsiveness
	  , COUNT (
	  	DISTINCT
	      CASE WHEN ord_internal_ref_no IS null THEN rfq_event_hash END
	  ) rfq_event_no_po
	  , COUNT (
	      DISTINCT
		  CASE WHEN ord_internal_ref_no IS null AND competitive_quote=1 THEN rfq_event_hash END
	  ) rfq_event_no_po_cheapest
	  , SUM (
	      CASE WHEN ord_internal_ref_no IS null AND competitive_quote=1 THEN own_quote_price END
	  ) estimated_leakage
	  , SUM (
	      CASE WHEN ord_internal_ref_no IS null AND competitive_quote=1 THEN own_quote_price * ss.spb_monetization_percent/100  END
	  ) estimated_rev_leakage
	  , COUNT(transactions.has_winner) has_winner
	  , CASE WHEN COUNT(transactions.has_winner) > 0 THEN ROUND(COUNT(transactions.supplier_won_competition) /  COUNT(transactions.has_winner) * 100, 2) END win_rate
	  , COUNT(transactions.price_competitive) price_competitive_count
	  , CASE WHEN COUNT(transactions.price_competitive) > 0 THEN ROUND(COUNT(transactions.cheapest_competitive) / COUNT(transactions.price_competitive) * 100,2) END cheapest_competitive
	  , ROUND(
	  		AVG(transactions.cheaper)
	  		,2
	  		) cheaper
	FROM
	  transactions JOIN all_supplier ss
	    ON (ss.spb_branch_code=transactions.spb_branch_code)
	GROUP BY
	  transactions.spb_branch_code
	  , ss.spb_name
	  , ss.SPB_REGION
	  , ss.SPB_ACCT_MNGR_NAME
	  , ss.SPB_COUNTRY
	  , ss.SPB_INTERFACE
	  , ss.SPB_MONETIZATION_PERCENT
	  , ss.SPB_PCS_SCORE
	  , ss.country_name
	  , ss.SPB_LISTING_STATUS
	  , ss.SPB_LISTING_LEVEL
)
,
final_data AS (
  SELECT
      r.*
      , (
        SELECT
          COUNT(*)
        FROM
          ord o
        WHERE
          o.ord_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.999999
          AND o.rfq_internal_ref_no IS null
          AND o.qot_internal_ref_no IS null
          AND o.spb_branch_code=r.spb_branch_code
      ) direct_po
      , (
          SELECT
            gt.total_gmv
          FROM
            gmv_trend_03_mo gt
          WHERE
            gt.spb_branch_code=r.spb_branch_code

      ) gmv_03
      , (
          SELECT
            gt.total_gmv
          FROM
            gmv_trend_36_mo gt
          WHERE
            gt.spb_branch_code=r.spb_branch_code
      ) gmv_36
	  , (
	  		SELECT
				SUM(rr.potential_saving)
			FROM
				match_b_rfq_to_match rr JOIN match_b_qot_match q
				ON (rr.cheapest_qot_spb_by_match_100=q.qot_internal_ref_no)
			WHERE
				q.spb_branch_code=r.spb_branch_code
	  ) match_potential_saving
	  , (
		  SELECT
			  SUM(rr.realised_saving)
		  FROM
			  match_b_rfq_to_match rr JOIN match_b_qot_match q
			  ON (rr.cheapest_qot_spb_by_match_100=q.qot_internal_ref_no)
		  WHERE
			  q.spb_branch_code=r.spb_branch_code
	  ) match_realised_saving
	  , (
	  SELECT
	  	CASE WHEN COUNT( DISTINCT rf.rfq_internal_ref_no) > 0 THEN
	    COUNT( DISTINCT
	        CASE
	          WHEN rm.cheapest_qot_spb_by_match_100 = qm.qot_internal_ref_no THEN
	            rr.rfq_event_hash
	        END
	    )
		/ COUNT( DISTINCT rf.rfq_internal_ref_no)
		* 100
		ELSE
		0
		END
	  FROM
	    match_b_rfq_forwarded_by_match rf LEFT OUTER JOIN match_b_qot_match qm
	    ON (rf.rfq_internal_ref_no=qm.rfq_internal_ref_no)
	      JOIN match_b_rfq_to_match rm ON (rm.rfq_internal_ref_no=rf.rfq_sent_to_match)
	        JOIN rfq rr ON (rr.rfq_internal_ref_no=rm.rfq_internal_ref_no)
	  WHERE
	    rr.spb_branch_code!=999999
	    AND rr.rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.999999
		AND rf.spb_branch_code=r.spb_branch_code
	  )	percentage_of_cheapest_qot
  FROM
      result r
)

SELECT
  fd.*
  ,pc.*
  , (
        CASE
			WHEN fd.gmv_36 < fd.gmv_03
				THEN 'up'
			WHEN fd.gmv_36 > fd.gmv_03
				THEN 'down'
			WHEN fd.gmv_36 = fd.gmv_03
				THEN 'equal'
			ELSE null
		END
  ) gmv_trend
FROM
  final_data fd
	  LEFT JOIN pricing pc
	  ON (
	  	fd.spb_branch_code = pc.sbr_spb_branch_code
	  	)
";


		$params = array('startDate' => $this->startDate->format("d-M-Y"), 'endDate' => $this->endDate->format("d-M-Y"));

		if( $_GET['terminated'] == 1 ){
			echo $sql; print_r($params); die();
		}

		$db = $this->getDbByType('ssreport2');
		$key = "Shipserv_Report_Supplier_Cxsion" . md5($sql) . print_r($params, true);

		//echo $sql; print_r($params); die();

		return $this->fetchCachedQuery ($sql, $params, $key, (60*60*24), 'ssreport2');

	}



	public function getTrends( $spbBranchCode ){
		$multiplier = 1;
		$sql = "
		WITH base AS (
			SELECT
			  TO_CHAR(ord_orig_submitted_date, 'YYYYMM') month_number
			  , TO_CHAR(ord_orig_submitted_date, 'MON') month_name
			  , SUM(final_total_cost_usd) total_gmv
			FROM
			  	ord_traded_gmv
			WHERE
				ord_orig_submitted_date BETWEEN ADD_MONTHS( TRUNC(SYSDATE, 'MM'), :startMonth) AND LAST_DAY(ADD_MONTHS( TRUNC(SYSDATE, 'MM'), :endMonth))
				AND spb_branch_code=:spbBranchCode
			GROUP BY
			  TO_CHAR(ord_orig_submitted_date, 'YYYYMM')
			  , TO_CHAR(ord_orig_submitted_date, 'MON')
			ORDER BY
			  TO_CHAR(ord_orig_submitted_date, 'YYYYMM')
		)
	  	SELECT
	  		m.month_number
	  		, m.month_name
	  		, m.month_year
	  		, b.total_gmv
	  	FROM
	  		(
	    			SELECT
	    				TO_CHAR(trunc(add_months(sysdate, :endMonth-level),'MM'), 'YYYYMM') month_number
	    				, TO_CHAR(trunc(add_months(sysdate, :endMonth-level),'MM'), 'MON') month_name
	    				, TO_CHAR(trunc(add_months(sysdate, :endMonth-level),'MM'), 'MON-YYYY') month_year
	    			FROM dual
	    			CONNECT BY LEVEL<=12
	    	) m LEFT OUTER JOIN base b ON m.month_number=b.month_number

		";
		$params = array(
			'spbBranchCode' => $spbBranchCode
			, 'startMonth' => -24
			, 'endMonth' => -13
		);

		$key = md5($sql) .  "Shipserv_Report_Supplier_Statistic" . print_r($params, true) . print_r($_GET, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');

		foreach($data as $row)
		{
			$new[1][$row['MONTH_NAME'] ] = $row;
		}

		$params = array(
			'spbBranchCode' => $spbBranchCode
			, 'startMonth' => -12
			, 'endMonth' => -1
		);
		$key = md5($sql) .  "Shipserv_Report_Supplier_Statistic" . print_r($params, true) . print_r($_GET, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');

		foreach($data as $row)
		{
			$new[0][$row['MONTH_NAME'] ] = $row;
		}

		foreach($new[0] as $monthNumber => $data ){
			$result[] = array(
				(($new[1][$monthNumber]['MONTH_NAME']!="")?$new[1][$monthNumber]['MONTH_NAME']:$new[0][$monthNumber]['MONTH_NAME'])
				, (($new[0][$monthNumber]['TOTAL_GMV']>0)?(int)$new[0][$monthNumber]['TOTAL_GMV']:0)
				, (($new[1][$monthNumber]['TOTAL_GMV']>0)?(int)$new[1][$monthNumber]['TOTAL_GMV']:0)
				, $new[0][$monthNumber]['MONTH_YEAR']
				, $new[1][$monthNumber]['MONTH_YEAR']
				
			);
		}

		$sql = "
			SELECT
				TO_CHAR(ADD_MONTHS( TRUNC(SYSDATE, 'MM'),-12), 'DD Month YYYY') || ' to ' || TO_CHAR(LAST_DAY(ADD_MONTHS( TRUNC(SYSDATE, 'MM'),-1)), 'DD Month YYYY') period_1y
				, TO_CHAR(ADD_MONTHS( TRUNC(SYSDATE, 'MM'),-24), 'DD Month YYYY') || ' to ' || TO_CHAR(LAST_DAY(ADD_MONTHS( TRUNC(SYSDATE, 'MM'),-13)), 'DD Month YYYY') period_2y
			FROM
				dual
		";

		$db = $this->getDbByType('ssreport2');

		$this->periodForTrends = $db->fetchAll($sql);



		return $result;
	}

}
