<?php

class Shipserv_Match_Report_Conversion extends Shipserv_Object {

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
        
        Shipserv_DateTime::monthsAgo(3, $startDate, $endDate);
        
        $this->startDate = $startDate->format('d-M-Y');
        $this->endDate = $endDate->format('d-M-Y');
        
        $this->startDateObject = $startDate;
        $this->endDateObject = $endDate;
        
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
     * Comment by Attila O
     * It looks like this function is not used, the table match_conversion_event does not exists,
     * but refactored for the new stored fucntion anyway
     */
    public function createTmpTableForMatchEvent()
    {
		$sql = "
			INSERT INTO match_conversion_event
			SELECT 
			  DISTINCT p.ord_internal_ref_no,
			  q.qot_ref_no,
			  q.qot_internal_ref_no,
			  get_cheapest_qotid_v4(sourcerfq, rfqhash, 'buyer') AS cheapest_qot_spb_by_byb,
			  get_cheapest_qotid_v4(sourcerfq, rfqhash, 'match') AS cheapest_qot_spb_by_match,
			  get_cheapest_qotid_v4(sourcerfq, rfqhash, 'buyer-100-quoted') AS cheapest_qot_spb_by_byb_100,
			  get_cheapest_qotid_v4(sourcerfq, rfqhash, 'match-100-quoted') AS cheapest_qot_spb_by_match_100,
              MatchEvents.sourcerfq,
              MatchEvents.matchrfq,
              MatchEvents.buyer,
              MatchEvents.supplier,
              MatchEvents.refno,
              MatchEvents.matchsent,
              MatchEvents.matchquote
			FROM ord p,
			  rfq r,
			  qot q,
			  (
          SELECT 
            r2m.rfq_internal_ref_no          AS SourceRFQ,
            rfm.rfq_internal_ref_no          AS MatchRFQ,
            r2m.byb_branch_code              AS Buyer,
            rfm.spb_branch_code              AS Supplier,
            r2m.rfq_ref_no                   AS RefNo,
            r2m.rfq_event_hash               AS RfqHash,
            rfm.rfq_submitted_date           AS MatchSent,
            qrm.qot_internal_ref_no          AS MatchQuote
          FROM
            match_b_rfq_to_match r2m
            , match_b_rfq_forwarded_by_match rfm
            , match_b_qot_match qrm
          WHERE
            r2m.rfq_internal_ref_no=rfm.rfq_sent_to_match
            AND qrm.rfq_internal_ref_no=rfm.rfq_internal_ref_no
            AND r2m.rfq_count=1			  
        ) MatchEvents
			WHERE 
			  p.rfq_internal_ref_no           = r.rfq_internal_ref_no (+)
			  AND p.qot_internal_ref_no       = q.qot_internal_ref_no (+)
			  AND p.ord_ref_no                = MatchEvents.RefNo
			  AND p.spb_branch_code           = MatchEvents.Supplier
			  AND p.byb_branch_code           = MatchEvents.Buyer
		";
		$this->reporting->query("TRUNCATE TABLE match_conversion_event");
		$this->reporting->query($sql);
    }
    
    public function getStat()
    {
    	
		
$sql = "
SELECT 
  -- pull all
  result_table.*

  -- Marginal Change in Monetisation Rate
  , ROUND(result_table.bid_winner - result_table.bid_of_min_spb_by_byb,2)
    AS change_in_bid
  
  -- Revenue if Buyer Selected Cheapest Buyer Selected Supplier
  , nvl(ROUND(result_table.bid_of_min_spb_by_byb/100 * result_table.CHEAPEST_QOT_BY_BUYER,2),0) 
    AS rvnu_for_cheapest

  -- Actual Revenue
  , nvl((result_table.bid_winner/100 * result_table.po_amount),0)
    AS actual_rvnu
    
  -- Revenue Gain/Loss
  , CASE
  		when nvl((result_table.bid_winner/100 * result_table.po_amount),0) > 0 AND nvl((result_table.bid_of_min_spb_by_byb/100 * result_table.po_amount),0) > 0 THEN
		  	nvl((result_table.bid_winner/100 * result_table.po_amount),0) - 
		  	nvl((result_table.bid_of_min_spb_by_byb/100 * result_table.po_amount),0)
  		else null
  	END
    AS rvnu_gain_lost
		    
FROM
(
  
  SELECT   
	DISTINCT
    -- Number Buyer Selected Suppliers
    (
        SELECT COUNT(1) 
        FROM
          match_b_rfq_also_sent_to_buyer
        WHERE
          rfq_sent_to_match=e.rfq_sent_to_match
      ) as
      count_spb_by_byb
    ------------------------------
		
    -- Number Match Selected Suppliers
    , (
        select count(*) from match_b_rfq_forwarded_by_match where
        (rfq_sent_to_match=e.rfq_sent_to_match) and byb_branch_code=11107
      ) as
      count_spb_by_match
		
   

    -- PO Amount
    , 
		-- get_dollar_value(e.ord_currency, e.ord_total_cost, e.ord_submitted_date) 
		e.ORD_TOTAL_COST_USD
		as po_amount
    ------------------------------
    
    -- Average Bid Buyer Selected Suppliers
    , get_avg_bid_v2(rfq_sent_to_match, 'buyer', e.byb_branch_code) as avg_spb_bid_by_buyer
    ------------------------------
  
    -- Average Bid Buyer Selected Suppliers
    , get_avg_bid_v2(rfq_sent_to_match, 'match', e.byb_branch_code) as avg_spb_bid_by_match
    ------------------------------
  
    -- Bid of Cheapest Buyer Selected Supplier which quoted for 100% items
    , ( 
        SELECT spb_monetization_percent FROM supplier_branch@standby_link
        WHERE spb_branch_code = (
          SELECT spb_branch_code FROM qot WHERE qot_internal_ref_no=e.cheapest_qot_spb_by_byb
        )  
      ) AS bid_of_min_spb_by_byb
  
    -- Cheapest Buyer Selected Supplier Quote
    , (
        SELECT 
			get_dollar_value(qot2.QOT_CURRENCY, qot2.QOT_TOTAL_COST, qot2.QOT_SUBMITTED_DATE)
			--QOT_TOTAL_COST_USD
        FROM qot qot2 
        WHERE 
			qot2.qot_internal_ref_no = e.cheapest_qot_spb_by_byb
			AND rownum=1
      ) AS cheapest_qot_by_buyer
    ------------------------------
  
    -- Cheapest Match Selected Supplier Quote 
    , (
        SELECT 
			get_dollar_value(qot2.QOT_CURRENCY, qot2.QOT_TOTAL_COST, qot2.QOT_SUBMITTED_DATE)
			-- QOT_TOTAL_COST_USD
        FROM qot qot2 
        WHERE 
			qot2.qot_internal_ref_no = e.cheapest_qot_spb_by_match
			AND rownum=1
      ) AS cheapest_qot_by_match
    ------------------------------
  
    -- Bid of Winning Match Supplier
    , (
        SELECT NVL(spb_monetization_percent,0) FROM supplier_branch@standby_link 
        WHERE spb_branch_code=e.spb_branch_code
      ) AS bid_winner
    ------------------------------
  
    -- winner tnid
    , (
        e.spb_branch_code
      ) AS winner_tnid
    ------------------------------
  
    , e.*
    
  
  FROM
    (
 
		SELECT
		  DISTINCT
		  r.rfq_internal_ref_no AS rfq_sent_to_match,
		  r.rfq_ref_no,
	      r.rfq_submitted_date,
	      r.cheapest_qot_spb_by_byb,
		  r.cheapest_qot_spb_by_match,
		  r.cheapest_qot_spb_by_byb_100,
		  r.cheapest_qot_spb_by_match_100,
		
	      q.qot_ref_no,
		  q.qot_internal_ref_no,
		
		  
		  p.ord_internal_ref_no,
	      p.ord_currency,
		  p.ord_total_cost,
		  p.ORD_TOTAL_COST_USD, 
		  p.ord_submitted_date,
		
	      -- buyer branch
	      b.byb_branch_code,
	      b.byb_name,  
	      ------------------------------
	      
	      -- supplier name
	      s.spb_branch_code, 
	      s.spb_name,         
	      ------------------------------

		  p.is_orphaned_po
		FROM
			match_b_order_by_match_spb p LEFT OUTER JOIN match_b_rfq_to_match r
			    ON (p.rfq_sent_to_match=r.rfq_internal_ref_no)
		        JOIN buyer b 
		          ON (p.byb_branch_code=b.byb_branch_code AND byb_is_test_account=0)
		        JOIN supplier s 
		          ON (p.spb_Branch_code=s.spb_Branch_code)
			      LEFT OUTER JOIN qot q ON (p.qot_internal_ref_no = q.qot_internal_ref_no)
		WHERE
		  p.ord_submitted_date BETWEEN TO_DATE('" . $this->startDate . "') AND TO_DATE('" . $this->endDate . "') + 0.99999
	) e
  
) 
result_table   
		  		ORDER BY ord_submitted_date DESC
		";
		$key = md5($sql) . "_MATCH_CONVERSION_KPI" . $this->startDate . $this->endDate;

		// @todo: Have this wrapped in exception if memcache isn't available
		$memcache = Shipserv_Memcache::getMemcache();
		if( $memcached !== null )
		{
			$cachedResults = $memcache->get($key);
		}

		$params = array();
		//echo $sql; print_r($params); die();
		$results = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');
		
		
		
		return $results;
    	 
    	
    }
	
}

