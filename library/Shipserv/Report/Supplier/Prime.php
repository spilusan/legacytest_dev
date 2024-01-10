<?php
class Shipserv_Report_Supplier_Prime extends Shipserv_Report
{	
	
	public function __construct()
	{
		$this->logger = new Myshipserv_Logger_File('prime-supplier-report');
		$this->db = $this->getSSReport2Db();
		
	}
	
	public function storeSupplierData( $tnid )
	{
		
		$this->logger->log(" Running query for: " . $tnid);
		
		$sql = "
WITH 
--------------------------------------------------------------------------------
-- supplier that need to be processed
--------------------------------------------------------------------------------
supplier_data AS
(
  SELECT 
    s.*
    , sp.spb_pcs_score
    , sp.directory_listing_level_id
    , (
        SELECT 
          COUNT(DISTINCT pcb_brand_id)
        FROM
          pages_company_brands@livedb_link
        WHERE
          pcb_company_id=s.spb_branch_code
          AND pcb_is_authorised='Y'
          AND pcb_is_deleted!='Y'
    ) brand_auth_verified
    , (
     --- NOT RELIABLE - please use salesforce_contract instead
      CASE
        WHEN 
          (
            SELECT COUNT(*) FROM vbp_transition_date@livedb_link
            WHERE vst_spb_branch_code=s.spb_branch_code
          ) > 0 THEN
          'Y'
        ELSE
          'N'
      END
  ) vbp
  , sp.spb_acct_mngr_name
  , (
    SELECT 
      RTRIM(xmlagg(xmlelement(c, p.name || ', ') ).extract ('//text()'), ',')
		from 
      product_category@livedb_link p, supply_category@livedb_link sc
		where
			sc.supplier_branch_code = s.spb_branch_code and
			sc.product_category_id = p.id and
      sc.primary_supply_category=1
		
  ) main_category
  FROM 
    supplier s
    , supplier_branch@livedb_link sp
  WHERE
    s.spb_is_test_account=0 
    AND s.spb_is_inactive_account=0 
    AND s.spb_is_deleted_account=0 
    AND s.spb_branch_code=sp.spb_branch_code
    AND s.spb_branch_code=:tnid
)
,
order_buyer AS
(
  SELECT
    DISTINCT
    o.spb_branch_code
    , o.byb_branch_code
    , o.ord_internal_ref_no
    , b.byb_country_code
    , o.ord_line_count
    , (
        CASE 
          WHEN byb_country_code='US' THEN o.byb_branch_code
          ELSE null
        END
    ) buyer_in_us
  FROM
    ord o, buyer b
  WHERE
    o.ord_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate)
    AND EXISTS (
      SELECT null FROM supplier_data dd WHERE dd.spb_branch_code=o.spb_branch_code
    )
    AND b.byb_branch_code=o.byb_branch_code
)
,
order_buyer_summary AS 
(
  SELECT 
    spb_branch_code
    , AVG(ord_line_count) avg_line_item
    , COUNT( 
        DISTINCT buyer_in_us
    ) total_buyer_in_us
  FROM 
    order_buyer
  GROUP BY
    spb_branch_code
)

--------------------------------------------------------------------------------
-- listing basic information about all direct order that this supplier has
--------------------------------------------------------------------------------
,
direct_order_data AS
(
  SELECT
    DISTINCT
    o.spb_branch_code
    , o.byb_branch_code
    , o.ord_internal_ref_no
    , b.byb_country_code
    , (
       MAX(rep.ord_total_cost_discounted_usd) OVER (PARTITION BY rep.ORD_ORIGINAL_NO ORDER BY rep.primary_id DESC ) 	        
    ) ord_total_cost
  FROM
    ord o JOIN billable_po rep ON (rep.ord_original_no=o.ord_internal_ref_no)
    JOIN buyer b ON (o.byb_branch_code=b.byb_branch_code)
  WHERE
    o.ord_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate)
    AND EXISTS (
      SELECT null FROM supplier_data dd WHERE dd.spb_branch_code=o.spb_branch_code
    )
    AND o.qot_internal_ref_no IS null
)

--------------------------------------------------------------------------------
-- aggregating direct order
--------------------------------------------------------------------------------
,
direct_ord_supplier AS (
  SELECT
    DISTINCT 
    spb_branch_code
    , COUNT(DISTINCT ord_internal_ref_no) total_direct_order
    , SUM(ord_total_cost) total_direct_order_usd
    , SUM(
        CASE 
          WHEN byb_country_code='US' THEN
            ord_total_cost
          ELSE 
            0
        END
    ) total_direct_order_usd_us
    , COUNT(DISTINCT byb_branch_code) total_direct_buyer
  FROM
    direct_order_data
  GROUP BY 
    spb_branch_code
)

--------------------------------------------------------------------------------
-- 
--------------------------------------------------------------------------------
,
basic_data AS
(
  SELECT
    r.spb_branch_code
    , r.byb_branch_code
    , r.rfq_event_hash
    , r.rfq_internal_ref_no
    , r.rfq_linkable_qot
    , r.rfq_linkable_ord
    , r.rfq_line_count
    , (
	      SELECT
          DISTINCT MAX(rep.ord_total_cost_discounted_usd) 
          KEEP (DENSE_RANK FIRST ORDER BY rep.primary_id DESC)        	
	      FROM 
          billable_po rep
	      WHERE
	        rep.ord_internal_ref_no=r.rfq_linkable_ord
    ) total_order_usd
    , (
	      SELECT
          DISTINCT MAX(rep.ord_total_cost_discounted_usd) 
          KEEP (DENSE_RANK FIRST ORDER BY rep.primary_id DESC)        	
	      FROM 
          billable_po rep JOIN ord o 
          ON (o.ord_internal_ref_no=rep.ord_internal_ref_no)
            JOIN buyer b
            ON (b.byb_branch_code=o.byb_branch_code AND b.byb_country_code='US')
	      WHERE
	        rep.ord_internal_ref_no=r.rfq_linkable_ord
    ) total_order_usd_us
    , r.rfq_submitted_date
  FROM
    rfq r
  WHERE
    r.rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate)
    AND r.rfq_is_latest=1
    AND EXISTS(
      SELECT null FROM
      supplier_data sd
      WHERE sd.spb_branch_code=r.spb_branch_code
    )
)
--------------------------------------------------------------------------------
,
base_data AS 
(
  SELECT 
    DISTINCT b2.*
  FROM
    basic_data b2
)

--------------------------------------------------------------------------------
-- pulls rfq which was declined, quoted, unactioned, quote time
--------------------------------------------------------------------------------
,
response_rfq_data AS (
    SELECT
      r.spb_branch_code
      , q.qot_internal_ref_no
      , rr.rfq_resp_date
      , (
        CASE 
          WHEN r.rfq_line_count>0 AND q.qot_line_count>0 THEN
            q.qot_line_count/r.rfq_line_count*100
          ELSE 
            0 
        END
      ) completeness
      , (
        CASE 
          WHEN 
            (q.qot_internal_ref_no IS NOT null AND q.qot_total_cost=0)
            OR rr.rfq_resp_date IS NOT null THEN
            r.rfq_event_hash
          ELSE
            null
        END
      ) declined_rfq_event
      , (
        CASE 
          WHEN 
			(q.qot_internal_ref_no IS NOT null AND q.qot_total_cost>0 AND rr.rfq_resp_date IS null)
			OR (r.rfq_linkable_ord IS NOT null AND q.qot_internal_ref_no IS null AND rr.rfq_resp_date IS null) -- if order's coming from orphaned PO
			THEN
            r.rfq_event_hash
          ELSE
            null
        END
      ) quoted_rfq_event_implied
      , (
        CASE 
          WHEN 
			(q.qot_internal_ref_no IS NOT null AND q.qot_total_cost>0 AND rr.rfq_resp_date IS null)
			THEN
            r.rfq_event_hash
          ELSE
            null
        END
      ) quoted_rfq_event
--      , (
--        CASE 
--          WHEN q.qot_internal_ref_no IS null AND rr.rfq_resp_date IS null THEN
--            r.rfq_event_hash
--          ELSE
--            null
--        END
--      ) unactioned_rfq_event
      , (
        CASE 
          WHEN q.qot_internal_ref_no IS NOT null AND rr.rfq_resp_date IS null THEN
            q.qot_submitted_date-r.rfq_submitted_date
          ELSE
            null
        END
      ) qot_time
    FROM
      -- only process all rfqs on base_data
      (
        SELECT * FROM base_data b 
      ) r LEFT OUTER JOIN qot q
      ON (r.rfq_internal_ref_no=q.rfq_internal_ref_no )
        LEFT OUTER JOIN rfq_resp rr 
        ON (r.rfq_internal_ref_no=rr.rfq_internal_ref_no AND rr.rfq_resp_sts='DEC' AND rr.spb_branch_code=r.spb_branch_code)

)
,
--------------------------------------------------------------------------------
response_data AS (
  SELECT
    spb_branch_code
    , COUNT(DISTINCT declined_rfq_event) total_declined
    , COUNT(DISTINCT quoted_rfq_event) total_quoted
	, COUNT(DISTINCT quoted_rfq_event_implied) TOTAL_QUOTE_EVENT_IMPLIED
    --, COUNT(DISTINCT unactioned_rfq_event) total_unactioned
    , AVG(qot_time) average_quote_time
    , SUM(
        CASE WHEN completeness > 99 THEN 1 ELSE 0 END
    ) total_100_pc
  FROM
    response_rfq_data
  GROUP BY 
    spb_branch_code
)
,
--------------------------------------------------------------------------------
cheapest_qot_data AS 
(
  -- returning which quote is the cheapest one
  SELECT
    rfq.rfq_internal_ref_no
    , rfq.rfq_event_hash
    , qot.qot_internal_ref_no
    , rfq.rfq_linkable_ord
    , qot_total_cost_usd total_cost
    , qot.spb_branch_code
    , (
        CASE 
          WHEN rfq.rfq_line_count>0 AND qot.qot_line_count>0 THEN
            qot.qot_line_count/rfq.rfq_line_count*100
          ELSE 
            0 
        END
    ) completeness
    , (
        CASE 
          WHEN MIN( qot_total_cost_usd )
            OVER (PARTITION BY rfq.rfq_event_hash) = qot_total_cost_usd 
              THEN 1
          ELSE
            0
        END
    ) is_cheapest_qot
    , (
        MIN( qot_total_cost_usd )
        OVER (PARTITION BY rfq.rfq_event_hash)
    ) cheapest_qot
    , (
        MAX( qot_total_cost_usd )
        OVER (PARTITION BY rfq.rfq_event_hash)
    ) most_expensive_qot
    , (
        AVG( qot_total_cost_usd )
        OVER (PARTITION BY rfq.rfq_event_hash)
    ) avg_qot_price
    
  FROM
    rfq JOIN qot ON (rfq_linkable_qot=qot_internal_ref_no)
  WHERE
    EXISTS (
      SELECT null FROM base_data WHERE
        rfq.rfq_event_hash=base_data.rfq_event_hash
    )
  -- --------------------------------------------------------    
)
, 
--------------------------------------------------------------------------------
competitor_analysis AS
(
  -- getting total count of competitor for each rfq event
  SELECT 
    r.* 
    , (
        CASE 
          WHEN r.rfq_linkable_qot IS NOT null THEN
            (
              SELECT 
                COUNT(*) 
              FROM 
                rfq rr 
              WHERE 
                rr.rfq_event_hash=r.rfq_event_hash
                AND rr.spb_branch_code!=r.spb_branch_code
            )
        ELSE
          null
        END
    ) total_competitor  
    , (
        CASE 
          WHEN r.rfq_linkable_qot IS NOT null THEN
            (
              SELECT 
                COUNT(*) 
              FROM 
                rfq rr 
              WHERE 
                rr.rfq_event_hash=r.rfq_event_hash
            
            )
        ELSE
          null
        END
    ) total_competitor_inc_spb
  FROM 
    base_data r
  -- --------------------------------------------------------    
)
--------------------------------------------------------------------------------
,
price_analysis AS 
(
  -- analysing price to check if on each rfq event:
  -- whether the spb is the cheapest
  -- whether the cheapest is winning
  -- whether the cheapest is losing
  SELECT
				
    r.*
    , (
        CASE 
          WHEN total_competitor > 0 THEN
          (
            SELECT 
              COUNT(DISTINCT cps.rfq_event_hash)
            FROM
              cheapest_qot_data cps
            WHERE
              cps.rfq_event_hash=r.rfq_event_hash
              AND cps.is_cheapest_qot=1
              AND r.rfq_linkable_qot=cps.qot_internal_ref_no
          )
        ELSE
          null
        END
    ) cheapest_is_me
    , (
        CASE 
          WHEN total_competitor > 0 THEN
            (
              SELECT 
                COUNT(DISTINCT cps.rfq_event_hash)
              FROM
                cheapest_qot_data cps
              WHERE
                cps.rfq_event_hash=r.rfq_event_hash
                AND cps.rfq_linkable_ord IS NOT null
                AND cps.is_cheapest_qot=1
            )
          ELSE
            null
        END
    ) cheapest_won
    , (
        CASE 
          WHEN total_competitor > 0 THEN
            (
              SELECT 
                COUNT(DISTINCT cps.rfq_event_hash)
              FROM
                cheapest_qot_data cps
              WHERE
                cps.rfq_event_hash=r.rfq_event_hash
                AND cps.rfq_linkable_ord IS NOT null
                AND cps.is_cheapest_qot=0
            )
          ELSE
            null
          END
    ) cheapest_lost
    , (
        SELECT 
          CASE 
            WHEN cps.avg_qot_price > 0 AND cps.most_expensive_qot>0 AND cps.cheapest_qot>0 THEN
              (
                (cps.most_expensive_qot - cps.cheapest_qot)
                / cps.avg_qot_price
              )
            ELSE 
              0
          END
        FROM
          cheapest_qot_data cps
        WHERE
          cps.rfq_event_hash=r.rfq_event_hash
          AND r.rfq_linkable_qot=cps.qot_internal_ref_no    
		  AND rownum=1
    ) qot_price_variation
    , (
        SELECT 
          CASE 
            WHEN cps.avg_qot_price > 0 AND cps.most_expensive_qot>0 AND cps.cheapest_qot>0 AND cps.completeness>99 THEN
              (
                (cps.most_expensive_qot - cps.cheapest_qot)
                / cps.avg_qot_price
              )
            ELSE 
              0
          END
        FROM
          cheapest_qot_data cps
        WHERE
          cps.rfq_event_hash=r.rfq_event_hash
          AND r.rfq_linkable_qot=cps.qot_internal_ref_no    
		  AND rownum=1
    ) qot_price_variation_100pc
  FROM
    competitor_analysis r
)
--------------------------------------------------------------------------------
,
total_data AS 
(
  SELECT 
    spb_branch_code
    , AVG(qot_price_variation) avg_qot_price_variation
    , AVG(qot_price_variation_100pc) avg_qot_price_variation_100pc


    , COUNT(DISTINCT byb_branch_code) total_unique_buyer
    , COUNT(DISTINCT rfq_event_hash) total_rfq    
    , COUNT(DISTINCT rfq_linkable_qot) total_qot_document
    , COUNT(DISTINCT rfq_linkable_ord) total_ord
    , SUM(total_order_usd) total_competitive_gmv    
    , SUM(total_order_usd_us) total_competitive_gmv_us  
    , SUM(CASE WHEN cheapest_won=1 AND cheapest_is_me=1 THEN 1 ELSE 0 END) total_im_cheapest_winning
    , SUM(cheapest_won) total_cheapest_won
    , SUM(cheapest_lost) total_cheapest_lost
    , SUM(total_competitor_inc_spb) total_competitor_inc_spb
  FROM 
    price_analysis
  GROUP BY
    spb_branch_code
)



SELECT
  s.spb_branch_code TNID
  , s.spb_country_code COUNTRY
  , s.spb_name SUPPLIER_NAME
  , s.vbp vbp
  , s.spb_acct_mngr_name account_manager
  , (
      CASE 
        WHEN s.vbp='Y' THEN s.spb_monetization_percent
        ELSE null
      END
  ) MONETISATION_PC
  , (
      CASE 
        WHEN s.vbp='N' AND ( s.spb_interface!='STARTSUPPLIER' OR s.directory_listing_level_id=4 ) THEN
          s.spb_monetization_percent
        ELSE 
         null
      END
  ) PO_PACK_PC
  , brand_auth_verified
  , s.spb_pcs_score pcs
  , (
      CASE 
        WHEN s.spb_interface='STARTSUPPLIER' THEN 'Y'
        ELSE 'N'
      END
  ) start_supplier
  , (
      CASE 
        WHEN s.spb_interface='SMARTSUPPLIER' THEN 'Y'
        ELSE 'N'
      END
  ) smart_supplier
  
  , (
      CASE 
        WHEN s.directory_listing_level_id=4 THEN 'Y'
        ELSE 'N'
      END
  ) premium_listing
  , d.total_rfq total_rfq_event
  , rd.total_declined total_declined_rfq_event
  , (
      d.total_rfq - rd.total_declined - rd.total_quoted
  ) total_unactioned_rfq_event
  , rd.total_quoted total_quote_event
  , rd.average_quote_time
  , rd.total_100_pc
  , d.total_ord total_competitive_ord
  , d.total_competitive_gmv
  , d.total_unique_buyer
  , dd.total_direct_order direct_po
  , dd.total_direct_order_usd direct_gmv
  , (d.total_competitive_gmv + dd.total_direct_order_usd) total_gmv
  , d.total_competitive_gmv_us 
  , dd.total_direct_order_usd_us
  , (d.total_competitive_gmv_us + dd.total_direct_order_usd_us) total_gmv_us
  
  , d.total_cheapest_won
  , d.total_im_cheapest_winning
  , d.total_competitor_inc_spb
  , d.avg_qot_price_variation
  , d.avg_qot_price_variation_100pc
  , obs.avg_line_item
  , obs.total_buyer_in_us
  , s.main_category
  , rd.total_quote_event_implied 
    --, rd.*
    --, dd.*
    --, d.*
    
  
FROM 
  total_data d LEFT OUTER JOIN supplier_data s 
  ON (d.spb_branch_code=s.spb_branch_code)
    LEFT OUTER JOIN direct_ord_supplier dd 
    ON (dd.spb_branch_code=s.spb_branch_code)
      LEFT OUTER JOIN response_data rd 
      ON (rd.spb_branch_code=s.spb_branch_code)
        LEFT OUTER JOIN order_buyer_summary obs
        ON (s.spb_branch_code=obs.spb_branch_code)
";
		
		$params = array(
			'startDate' => '01-JAN-2014', 
			'endDate' => '01-JAN-2015',
			'tnid' => $tnid
		);
				
		$result = $this->db->fetchAll($sql, $params);
		
		
		if( count($result)>0 ){
			$this->logger->log(" Storing data for: " . $tnid);
			$sql = "
				INSERT INTO  
					prime_supplier 
						(TNID,COUNTRY,SUPPLIER_NAME,VBP,ACCOUNT_MANAGER,MONETISATION_PC,PO_PACK_PC,BRAND_AUTH_VERIFIED,PCS,START_SUPPLIER,SMART_SUPPLIER,PREMIUM_LISTING,TOTAL_RFQ_EVENT,TOTAL_DECLINED_RFQ_EVENT,TOTAL_UNACTIONED_RFQ_EVENT,TOTAL_QUOTE_EVENT,AVERAGE_QUOTE_TIME,TOTAL_100_PC,TOTAL_COMPETITIVE_ORD,TOTAL_COMPETITIVE_GMV,TOTAL_UNIQUE_BUYER,DIRECT_PO,DIRECT_GMV,TOTAL_GMV,TOTAL_CHEAPEST_WON,TOTAL_IM_CHEAPEST_WINNING,TOTAL_COMPETITOR_INC_SPB,AVG_QOT_PRICE_VARIATION,AVG_QOT_PRICE_VARIATION_100PC,AVG_LINE_ITEM,TOTAL_BUYER_IN_US, TOTAL_GMV_US, MAIN_CATEGORY, TOTAL_QUOTE_EVENT_IMPLIED, TOTAL_COMPETITIVE_GMV_US, TOTAL_DIRECT_ORDER_USD_US) 
					VALUES
						(:TNID, :COUNTRY, :SUPPLIER_NAME, :VBP, :ACCOUNT_MANAGER, :MONETISATION_PC, :PO_PACK_PC, :BRAND_AUTH_VERIFIED, :PCS, :START_SUPPLIER, :SMART_SUPPLIER, :PREMIUM_LISTING, :TOTAL_RFQ_EVENT, :TOTAL_DECLINED_RFQ_EVENT, :TOTAL_UNACTIONED_RFQ_EVENT, :TOTAL_QUOTE_EVENT, :AVERAGE_QUOTE_TIME, :TOTAL_100_PC, :TOTAL_COMPETITIVE_ORD, :TOTAL_COMPETITIVE_GMV, :TOTAL_UNIQUE_BUYER, :DIRECT_PO, :DIRECT_GMV, :TOTAL_GMV, :TOTAL_CHEAPEST_WON, :TOTAL_IM_CHEAPEST_WINNING, :TOTAL_COMPETITOR_INC_SPB, :AVG_QOT_PRICE_VARIATION, :AVG_QOT_PRICE_VARIATION_100PC, :AVG_LINE_ITEM, :TOTAL_BUYER_IN_US, :TOTAL_GMV_US, :MAIN_CATEGORY, :TOTAL_QUOTE_EVENT_IMPLIED, :TOTAL_COMPETITIVE_GMV_US, :TOTAL_DIRECT_ORDER_USD_US)
			";
			
			$this->db->query($sql, $result[0]);
		}
		else 
		{
			$this->logger->log(" Not found: " . $tnid);
		}
		$this->logger->log(" End: " . $tnid);
		
	}
	
	public function storeSupplierTrendData($tnid, $startDate, $endDate, $period)
	{
		$sql = "
				
WITH 
--------------------------------------------------------------------------------
-- supplier that need to be processed
--------------------------------------------------------------------------------
supplier_data AS
(
  SELECT 
    s.*
  FROM 
    supplier s
    , supplier_branch@livedb_link sp
  WHERE
    s.spb_is_test_account=0 
    AND s.spb_is_inactive_account=0 
    AND s.spb_is_deleted_account=0 
    AND s.spb_branch_code=sp.spb_branch_code
    AND s.spb_branch_code=:tnid
)

--------------------------------------------------------------------------------
-- listing basic information about all direct order that this supplier has
--------------------------------------------------------------------------------
,
direct_order_data AS
(
  SELECT
    DISTINCT
    o.spb_branch_code
    , o.byb_branch_code
    , o.ord_internal_ref_no
    , b.byb_country_code
    , (
       MAX(rep.ord_total_cost_discounted_usd) OVER (PARTITION BY rep.ORD_ORIGINAL_NO ORDER BY rep.primary_id DESC ) 	        
    ) ord_total_cost
  FROM
    ord o JOIN billable_po rep ON (rep.ord_original_no=o.ord_internal_ref_no)
    JOIN buyer b ON (o.byb_branch_code=b.byb_branch_code)
  WHERE
    o.ord_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate)
    AND EXISTS (
      SELECT null FROM supplier_data dd WHERE dd.spb_branch_code=o.spb_branch_code
    )
    AND o.qot_internal_ref_no IS null
)

--------------------------------------------------------------------------------
-- aggregating direct order
--------------------------------------------------------------------------------
,
direct_ord_supplier AS (
  SELECT
    DISTINCT 
    spb_branch_code
    , COUNT(DISTINCT ord_internal_ref_no) total_direct_order
    , SUM(ord_total_cost) total_direct_order_usd
    , SUM(
        CASE 
          WHEN byb_country_code='US' THEN
            ord_total_cost
          ELSE 
            0
        END
    ) total_direct_order_usd_us
    , COUNT(DISTINCT byb_branch_code) total_direct_buyer
  FROM
    direct_order_data
  GROUP BY 
    spb_branch_code
)

--------------------------------------------------------------------------------
-- 
--------------------------------------------------------------------------------
,
basic_data AS
(
  SELECT
    r.spb_branch_code
    , r.byb_branch_code
    , r.rfq_event_hash
    , r.rfq_internal_ref_no
    , r.rfq_linkable_qot
    , r.rfq_linkable_ord
    , r.rfq_line_count
    , (
	      SELECT
          DISTINCT MAX(rep.ord_total_cost_discounted_usd) 
          KEEP (DENSE_RANK FIRST ORDER BY rep.primary_id DESC)        	
	      FROM 
          billable_po rep
	      WHERE
	        rep.ord_internal_ref_no=r.rfq_linkable_ord
    ) total_order_usd
    , (
	      SELECT
          DISTINCT MAX(rep.ord_total_cost_discounted_usd) 
          KEEP (DENSE_RANK FIRST ORDER BY rep.primary_id DESC)        	
	      FROM 
          billable_po rep JOIN ord o 
          ON (o.ord_internal_ref_no=rep.ord_internal_ref_no)
            JOIN buyer b
            ON (b.byb_branch_code=o.byb_branch_code AND b.byb_country_code='US')
	      WHERE
	        rep.ord_internal_ref_no=r.rfq_linkable_ord
    ) total_order_usd_us
    , r.rfq_submitted_date
  FROM
    rfq r
  WHERE
    r.rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate)
    AND r.rfq_is_latest=1
    AND EXISTS(
      SELECT null FROM
      supplier_data sd
      WHERE sd.spb_branch_code=r.spb_branch_code
    )
)
--------------------------------------------------------------------------------
,
base_data AS 
(
  SELECT 
    DISTINCT b2.*
  FROM
    basic_data b2
)

, 
--------------------------------------------------------------------------------
competitor_analysis AS
(
  -- getting total count of competitor for each rfq event
  SELECT 
    r.* 
  FROM 
    base_data r
  -- --------------------------------------------------------    
)
--------------------------------------------------------------------------------
,
price_analysis AS 
(
  SELECT
    r.*
  FROM
    competitor_analysis r
)
--------------------------------------------------------------------------------
,
total_data AS 
(
  SELECT 
    spb_branch_code
    , COUNT(DISTINCT byb_branch_code) total_unique_buyer
    , COUNT(DISTINCT rfq_linkable_ord) total_ord
    , SUM(total_order_usd) total_competitive_gmv    
  FROM 
    price_analysis
  GROUP BY
    spb_branch_code
)



SELECT
  s.spb_branch_code TNID
  , d.total_unique_buyer
  , (d.total_competitive_gmv + dd.total_direct_order_usd) total_gmv  
FROM 
  total_data d JOIN supplier_data s 
  ON (d.spb_branch_code=s.spb_branch_code)
    JOIN direct_ord_supplier dd 
    ON (dd.spb_branch_code=s.spb_branch_code)
				
		";
		
		$params = array(
			'startDate' => $startDate,
			'endDate' => $endDate,
			'tnid' => $tnid
		);
		$result = $this->db->fetchAll($sql, $params);
		
		if( count($result)>0 ){
			$this->logger->log(" Storing data for trend for " . $period . " period: " . $tnid);
			if( $period == 1 )
			{
				$sql = "
					UPDATE
						prime_supplier
					SET
						TOTAL_GMV_PREV=:TOTAL_GMV
						, TOTAL_UNIQUE_BUYER_PREV=:TOTAL_UNIQUE_BUYER
					WHERE
						tnid=:TNID
				";
				$this->db->query($sql, $result[0]);
			}
			else if( $period == 2 )
			{
				$sql = "
					UPDATE
						prime_supplier
					SET
						TOTAL_GMV_PREV2=:TOTAL_GMV
						, TOTAL_UNIQUE_BUYER_PREV2=:TOTAL_UNIQUE_BUYER
					WHERE
						tnid=:TNID
				";
				$this->db->query($sql, $result[0]);
			}
		}
		else
		{
			$this->logger->log(" Not found: " . $tnid);
		}
		$this->logger->log(" End: " . $tnid);
		
	}
	
	public function run()
	{
		$this->runAllSupplier();
		$this->runToUpdateTrends();
	}
	
	public function runToUpdateTrends()
	{
		$sql = "
			SELECT tnid FROM
			prime_supplier ps
		";
		foreach( $this->db->fetchAll($sql) as $row)
		{
			$this->logger->log("Processing calculation for trend for: " . $row['TNID'] );
			$this->storeSupplierTrendData($row['TNID'], '01-JAN-2013', '01-JAN-2014', 1);
			$this->storeSupplierTrendData($row['TNID'], '01-JAN-2012', '01-JAN-2013', 2);
		}		
	}
	
	public function runAllSupplier()
	{
		$sql = "
		  SELECT
		    sp.spb_branch_code TNID
		  FROM
			supplier s
		    , supplier_branch@livedb_link sp
		  WHERE
		    s.spb_is_test_account=0
		    AND s.spb_is_inactive_account=0
		    AND s.spb_is_deleted_account=0
		    AND s.spb_branch_code=sp.spb_branch_code
			AND NOT EXISTS(
				SELECT null FROM
				prime_supplier ps
				WHERE ps.tnid=s.spb_branch_code
			)
		";
		foreach( $this->db->fetchAll($sql) as $row)
		{
			$this->logger->log("Processing: " . $row['TNID']);
			$this->storeSupplierData($row['TNID']);
		}
	}
	
}



