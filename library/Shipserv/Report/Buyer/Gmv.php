<?php
class Shipserv_Report_Buyer_Gmv extends Shipserv_Report
{
	public $periodForTrends;
	const MEMCACHE_TTL = 43200;

	public function setParams( $params = null)
	{
		if( $params !== null )
		{
			if( $params['buyerName'] != "" ){
				$this->buyerName = strtolower($params['buyerName']);
			}

			if( $params['includeCapexSupplier'] == 1 ){
				$this->includeCapexSupplier = true;
			}
			else {
				$this->includeCapexSupplier = false;
			}

			if( $params['includeValidImo'] == 1 )
			{
				$this->includeValidImo = true;
			}
			else
			{
				$this->includeValidImo = false;
			}

			// requested by Kim to inclue all IMO regardless they're null or not
			$this->includeValidImo = true;


			if( $params['accountManager'] != "" )
			{
				$this->accountManager = strtolower(trim($params['accountManager']));
			}
			if( $params['region'] != "" )
			{
				$this->region = strtolower(trim($params['region']));
			}
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
			if( $params['parentId'] != "" )
			{
				$this->parentId = $params['parentId'];
			}

			if( $params['contractType'] != "" )
			{
				$this->contractType = $params['contractType'];
			}

			if( $params['contractTypeExclusion'] != "" )
			{
				$this->contractTypeExclusion = $params['contractTypeExclusion'];
			}
			else
			{
				$this->contractTypeExclusion = array('POM', 'CCP', 'CN3', 'TRIAL');
			}

			if( isset($params['resetMemcache']) && $params['resetMemcache'] == "1" )
			{
				$this->resetMemcache = true;
			}
			else
			{
				$this->resetMemcache = false;
			}

			if( isset($params['leakage']) && $params['leakage'] == "1" )
			{
				$this->includeLeakage = true;
			}
			else
			{
				$this->includeLeakage = false;
			}
		}
	}

	public function getAccountManager()
	{
		$sql = "
			SELECT DISTINCT BYB_ACCT_MNGR_NAME NAME FROM 	buyer_branch@livedb_link
				WHERE BYB_ACCT_MNGR_NAME IS NOT null
		";

		$db = $this->getDbByType('ssreport2');
		return $db->fetchAll($sql);
	}

	public function getRegion()
	{
		$sql = "
			SELECT DISTINCT byb_top_region REGION FROM 	buyer_branch@livedb_link
				WHERE byb_top_region IS NOT null
		";

		$db = $this->getDbByType('ssreport2');
		return $db->fetchAll($sql);
	}

	public function getContractType()
	{
		$sql = "
			SELECT DISTINCT byb_contract_type CONTRACT_TYPE FROM 	buyer_branch@livedb_link
				WHERE byb_contract_type IS NOT null
		";

		$db = $this->getDbByType('ssreport2');
		return $db->fetchAll($sql);
	}

	public function getData()
	{
		$sql = "
-- -----------------------------------------------------------------------------
-- pulling prime supplier
-- -----------------------------------------------------------------------------
WITH
-- -----------------------------------------------------------------------------
-- getting list of buyers and all attributes
-- -----------------------------------------------------------------------------
buyers AS (
	SELECT
	    bbb.byb_branch_code
	    , (
	    	CASE WHEN b.parent_branch_code = bbb.byb_branch_code THEN null
	      ELSE b.parent_branch_code END
	    ) parent_branch_code
	    , bbb.byb_name
		, bbb.BYB_ACCT_MNGR_NAME
	    , bbb.byb_contract_type
	    , bbb.BYB_POTENTIAL_UNITS
	    , bbb.BYB_POTENTIAL_GMV_PER_UNIT
	    , bbb.byb_monthly_fee_per_unit
	    , bbb.byb_country buyer_country
	    , bbb.byb_top_region buyer_region
		, b.gmv_trend_3m
		, b.ord_initial_txn_date
	  FROM
	    buyer_branch@livedb_link bbb JOIN buyer b ON (b.byb_branch_code=bbb.byb_branch_code)
	  WHERE
	    bbb.byb_sts='ACT'
	    AND bbb.byb_test_account='N'
	    AND LOWER(bbb.byb_name) NOT LIKE '%demo%'
	    AND LOWER(bbb.byb_name) NOT LIKE '%test%'
		" . (($this->accountManager != "")?"AND LOWER(TRIM(bbb.BYB_ACCT_MNGR_NAME))='" . strtolower($this->accountManager) . "'":"") . "
		" . (($this->region != "")?"AND LOWER(TRIM(bbb.byb_top_region))='" . strtolower(trim($this->region)) . "'":"") . "
		" . ((count($this->contractType) > 0 )?"AND bbb.byb_contract_type IN ('" . implode("','", $this->contractType) . "')":"") . "
)
,
-- -----------------------------------------------------------------------------
-- getting hierarchy of the buyers
-- -----------------------------------------------------------------------------
all_buyers AS (
	SELECT
	  b.*
	  , LEVEL
	FROM
		buyers b
		" . (($this->parentId != "")?"START WITH byb_branch_code=" . $this->parentId:"START WITH parent_branch_code IS NULL") . "
		" . (($this->buyerName != "")?"AND LOWER(b.byb_name) LIKE '%" . strtolower($this->buyerName) . "%'":"") . "
	  CONNECT BY prior byb_branch_code = parent_branch_code
	ORDER BY level, byb_name
)
" . ( ($this->includeLeakage === false)?"":"
,
-- -----------------------------------------------------------------------------
-- calculating leakage for each buyer
-- -----------------------------------------------------------------------------
buyer_leakage AS (
  SELECT
    lrqp.byb_branch_code
	, SUM(lrqp.leakage_vbp_revenue) rev_leakage
	, SUM(lrqp.leakage_usd_total) gmv_leakage
  FROM
    linked_rfq_qot_po lrqp
  WHERE
  	lrqp.rfq_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY') AND to_date(:endDate, 'DD-MON-YYYY') + 0.99999
	AND EXISTS (
      SELECT null FROM all_buyers ab WHERE ab.byb_branch_code=lrqp.byb_branch_code
    )
  GROUP BY
    lrqp.byb_branch_code
)
" ) . "
,
-- -----------------------------------------------------------------------------
-- getting the list of traded orders using order traded GMV.
-- where purchaser information exists
-- -----------------------------------------------------------------------------
all_orders_with_purchaser AS
(
  SELECT
  	DISTINCT
  	/*+ LEADING(tg) ORDERED index(ORD_TRADED_GMV IDX_ORD_TRADED_GMV_N12) index(ORD IDX_ORD_N10) */
    ro.ord_internal_ref_no
	, DECODE(
		vslh1.vslh_id_grouped_to,
		0, decode( vslh1.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh1.vslh_imo, '-', 'INVALID IMO', vslh1.vslh_imo )) || '~' || decode( vslh1.vslh_ihs_name, '-', decode( vslh1.vslh_name, '-', 'NO VESSEL NAME', vslh1.vslh_name ), vslh1.vslh_ihs_name ),
		nvl(( select decode( vslh2.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh2.vslh_imo, '-', 'INVALID IMO', vslh2.vslh_imo )) || '~' || decode( vslh2.vslh_ihs_name, '-', decode( vslh2.vslh_name, '-', 'NO VESSEL NAME', vslh2.vslh_name ), vslh2.vslh_ihs_name )
				from vessel_history vslh2
			   where vslh2.vslh_id = vslh1.vslh_id_grouped_to
				 and rownum = 1 ),
			'INVALID IMO~NO VESSEL NAME'
		   )
	) as imo_no_vessel_name
    , ro.ord_total_vbp_usd
    , ro.rfq_internal_ref_no
    , tg.byb_branch_code
    , tg.spb_branch_code
    --, final_total_cost_usd ord_total_cost_usd
    , tg.ord_total_cost_usd
    , tg.ord_orig_submitted_date ord_submitted_date
	, (
        CASE
          WHEN TRIM(cntc_person_name) IS NOT null AND TRIM(cntc_person_email_address) IS NOT null THEN
            LOWER(cntc_person_name || ' (' || cntc_person_email_address || ')')
          WHEN TRIM(cntc_person_name) IS NOT null AND TRIM(cntc_person_email_address) IS null THEN
            LOWER(cntc_person_name)
          WHEN TRIM(cntc_person_name) IS null AND TRIM(cntc_person_email_address) IS NOT null THEN
            LOWER('Unknown (' || cntc_person_email_address || ')')
        END
    ) purchaser_detail
	, s.spb_is_paying
	, s.spb_is_prime_supplier
	, ab.parent_branch_code
  FROM
	ord_traded_gmv tg JOIN ord ro ON (tg.ord_original=ro.ord_internal_ref_no)
	JOIN contact ON (cntc_doc_type='ORD' AND cntc_doc_internal_ref_no=ro.ord_internal_ref_no AND cntc_branch_qualifier='BY')
	JOIN supplier s ON (tg.spb_branch_code=s.spb_branch_code AND s.spb_is_test_account=0)
	JOIN vessel_history vslh1 ON (
		vslh1.byb_branch_code = ro.byb_branch_code
		AND vslh1.vslh_is_valid_imo = validate_imo_no( upper( trim( ro.ord_imo_no )))
		AND vslh1.vslh_imo = nvl( ro.ord_imo_no, '-' )
		AND vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( ro.ord_vessel_name ))), '-' )
	)
	JOIN all_buyers ab ON (ab.byb_branch_code=ro.byb_branch_code)
  WHERE
	tg.byb_branch_code IN (
		SELECT byb_branch_code FROM all_buyers
	)
	" . ( ( $this->includeValidImo ) ? "":"AND ro.ord_imo_no IS NOT null" ) . "
    AND tg.ord_orig_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY') AND to_date(:endDate, 'DD-MON-YYYY') + 0.99999
)
,
-- -----------------------------------------------------------------------------
-- pulling all orders where PO doesn't have the purchaser information
-- -----------------------------------------------------------------------------
all_orders_without_purchaser AS
(
  SELECT
  	DISTINCT
  	/*+ index(ORD IDX_ORD_N10) */
    ro.ord_internal_ref_no
	, DECODE( vslh1.vslh_id_grouped_to,
			0, decode( vslh1.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh1.vslh_imo, '-', 'INVALID IMO', vslh1.vslh_imo )) || '~' || decode( vslh1.vslh_ihs_name, '-', decode( vslh1.vslh_name, '-', 'NO VESSEL NAME', vslh1.vslh_name ), vslh1.vslh_ihs_name ),
			nvl(( select decode( vslh2.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh2.vslh_imo, '-', 'INVALID IMO', vslh2.vslh_imo )) || '~' || decode( vslh2.vslh_ihs_name, '-', decode( vslh2.vslh_name, '-', 'NO VESSEL NAME', vslh2.vslh_name ), vslh2.vslh_ihs_name )
					from vessel_history vslh2
				   where vslh2.vslh_id = vslh1.vslh_id_grouped_to
					 and rownum = 1 ),
				'INVALID IMO~NO VESSEL NAME'
			   )
		  ) as imo_no_vessel_name
	, ro.ord_total_vbp_usd
    , ro.rfq_internal_ref_no
    , tg.byb_branch_code
    , tg.spb_branch_code
    --, final_total_cost_usd ord_total_cost_usd
    , tg.ord_total_cost_usd
    , tg.ord_orig_submitted_date ord_submitted_date
	, null purchaser_detail
	, s.spb_is_paying
	, s.spb_is_prime_supplier
	, ab.parent_branch_code
  FROM
	  ord_traded_gmv tg JOIN ord ro ON (tg.ord_original=ro.ord_internal_ref_no)
	  JOIN supplier s ON (tg.spb_branch_code=s.spb_branch_code AND s.spb_is_test_account=0)
	  JOIN vessel_history vslh1 ON (
  		vslh1.byb_branch_code = ro.byb_branch_code
  		AND vslh1.vslh_is_valid_imo = validate_imo_no( upper( trim( ro.ord_imo_no )))
  		AND vslh1.vslh_imo = nvl( ro.ord_imo_no, '-' )
  		AND vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( ro.ord_vessel_name ))), '-' )
  	  )
	  JOIN all_buyers ab ON (ab.byb_branch_code=ro.byb_branch_code)

  WHERE
	tg.byb_branch_code IN (
		SELECT byb_branch_code FROM all_buyers
	)
	" . ( ( $this->includeValidImo ) ? "":"AND ro.ord_imo_no IS NOT null" ) . "
    AND tg.ord_orig_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY') AND to_date(:endDate, 'DD-MON-YYYY') + 0.99999
	AND NOT EXISTS(
      SELECT NULL FROM all_orders_with_purchaser alwp WHERE alwp.ord_internal_ref_no=ro.ord_internal_ref_no
    )
)
,
-- -----------------------------------------------------------------------------
-- combining orders with purchaser and without purchaser together
-- @rationale: left outer join on big table seems to be quite expensive operation
-- -----------------------------------------------------------------------------
all_orders AS (
  SELECT * FROM all_orders_with_purchaser
  UNION ALL
  SELECT * FROM all_orders_without_purchaser
)
,
-- -----------------------------------------------------------------------------
-- getting information about competitive order (where RFQ or QOT exists)
-- getting all transaction which related to the previous cube
-- join on ord_internal_ref_no
-- -----------------------------------------------------------------------------
transaction_with_linked_po AS (
  SELECT
    lrqp.rfq_submitted_date AS rfq_submitted_date
    , o.byb_branch_code AS byb_branch_code
	, o.parent_branch_code AS parent_branch_code
    , o.spb_branch_code AS spb_branch_code
    , lrqp.rfq_event_hash AS rfq_event_hash
    , lrqp.qot_price AS qot_price
    , lrqp.is_cheapest AS is_cheapest
    , lrqp.is_quickest AS is_quickest
    , o.ord_submitted_date AS ord_submitted_date
    , o.ord_total_cost_usd AS ord_total_cost_usd
    , o.ord_internal_ref_no AS ord_internal_ref_no
	, o.imo_no_vessel_name AS imo_no_vessel_name
    , o.ord_total_vbp_usd AS ord_total_vbp_usd
	, o.purchaser_detail AS purchaser_detail
	, o.spb_is_paying
	, o.spb_is_prime_supplier
  FROM
    linked_rfq_qot_po lrqp JOIN all_orders o
      ON (o.ord_internal_ref_no=lrqp.ord_internal_ref_no)
)
,
-- -----------------------------------------------------------------------------
-- getting all transaction that is not competitive or direct order
-- this will pull all orders that doesn't exists in linked_rfq_qot_po
-- -----------------------------------------------------------------------------
transaction_without_linked_po AS (
  SELECT
    null rfq_submitted_date
    , o.byb_branch_code AS byb_branch_code
	, o.parent_branch_code AS parent_branch_code
    , o.spb_branch_code AS spb_branch_code
    , null rfq_event_hash
    , null qot_price
    , null is_cheapest
    , null is_quickest
    , o.ord_submitted_date AS ord_submitted_date
    , o.ord_total_cost_usd AS ord_total_cost_usd
    , o.ord_internal_ref_no AS ord_internal_ref_no
    , o.imo_no_vessel_name AS imo_no_vessel_name
    , o.ord_total_vbp_usd AS ord_total_vbp_usd
	, o.purchaser_detail AS purchaser_detail
	, o.spb_is_paying
	, o.spb_is_prime_supplier
  FROM
    all_orders o
  WHERE
    NOT EXISTS(
      SELECT NULL FROM transaction_with_linked_po lpo
      WHERE o.ord_internal_ref_no=lpo.ord_internal_ref_no
    )
)
,
-- -----------------------------------------------------------------------------
-- unionising two cube to avoid  slow query with left outer join
-- -----------------------------------------------------------------------------
transaction_with_po AS(
    SELECT * FROM transaction_with_linked_po
    UNION ALL
    SELECT * FROM transaction_without_linked_po
)
,
-- -----------------------------------------------------------------------------
-- getting unique supplier per buyer
-- -----------------------------------------------------------------------------
unique_data AS(
  SELECT
    byb_branch_code
    , NVL(COUNT(DISTINCT spb_branch_code),0) unique_supplier
    , NVL(COUNT(DISTINCT CASE WHEN spb_is_paying=1 THEN spb_branch_code END ),0) unique_premium_supplier
    , NVL(COUNT(DISTINCT CASE WHEN spb_is_paying=0 THEN spb_branch_code END ),0) unique_basic_supplier
  FROM
    transaction_with_po
  GROUP BY
    byb_branch_code

)
,
-- -----------------------------------------------------------------------------
-- Calculating aggregated data like gmv, gmv for premium, basic supplier,
-- calculating revenue, getting total count of trading unit
-- important cube for final calculation
-- -----------------------------------------------------------------------------
base_gmv AS (
	SELECT
	  twp.byb_branch_code
	  , COUNT(*) total_ord
	  , (
		  NVL(SUM(twp.ord_total_cost_usd),0)
	  ) gmv
	  , (
		  SUM (
			CASE WHEN spb_is_paying=0 THEN
			  ord_total_cost_usd
			END
		  )
	  ) gmv_unique_basic_supplier
	  , (
		  SUM (
			CASE WHEN spb_is_paying=1 THEN
			  ord_total_cost_usd
			END
		  )
	  ) gmv_unique_premium_supplier
	  , (
		  SUM (
			CASE WHEN spb_is_prime_supplier=1 THEN
			  ord_total_cost_usd
			END
		  )
	  ) gmv_prime_supplier
	  , (
		  SUM (
			  ord_total_vbp_usd
		  )
	  ) revenue_supplier
	  , (
		  COUNT( DISTINCT imo_no_vessel_name)
	  ) trading_unit
	  , (
		  COUNT( DISTINCT spb_branch_code )
	  ) unique_spb_interacted_with
	  , (
		  SELECT COUNT(DISTINCT spb_branch_code) FROM transaction_without_linked_po WHERE byb_branch_code=twp.byb_branch_code
	  ) unique_supplier
	  , (
		  COUNT(
			DISTINCT
			CASE WHEN spb_is_paying=0 THEN
			  twp.spb_branch_code
			END
		  )
	  ) unique_basic_supplier
	  , (
		  COUNT(
			DISTINCT
			CASE WHEN spb_is_paying=1 THEN
			  twp.spb_branch_code
			END
		  )
	  ) unique_premium_supplier
	  , (
		  COUNT(
			DISTINCT
			purchaser_detail
		  )
	  ) unique_purchaser
	FROM
	  transaction_with_po twp
	GROUP BY
	  twp.byb_branch_code
)
,
-- -----------------------------------------------------------------------------
-- now we need to get all transaction where the anchor document is now RFQ
-- important column: rfq_submitted_date
-- -----------------------------------------------------------------------------
linked_rqp_cube AS (
	SELECT
	  lrqp.*
	FROM
	  linked_rfq_qot_po lrqp
	WHERE
	  EXISTS (
		SELECT null FROM all_buyers ab WHERE ab.byb_branch_code=lrqp.byb_branch_code
	  )
	  AND lrqp.rfq_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY') AND to_date(:endDate, 'DD-MON-YYYY') + 0.99999
)
,
-- -----------------------------------------------------------------------------
-- alias for previous cube
-- -----------------------------------------------------------------------------
all_transaction AS (
	SELECT * FROM linked_rqp_cube
)
,
-- -----------------------------------------------------------------------------
-- getting stuff like rfq event with PO, total rfq event, etc
-- -----------------------------------------------------------------------------
base_transaction AS (
  SELECT
    a.byb_branch_code
    , (
        COUNT (
          DISTINCT
          CASE WHEN  a.has_po=1 THEN rfq_event_hash END
        )
    ) rfq_event_with_po
    , (
        COUNT (
          DISTINCT
          rfq_event_hash
        )
    ) rfq_event
  FROM
    all_transaction a
  GROUP BY
  	a.byb_branch_code
)
,
-- -----------------------------------------------------------------------------
-- joining information about GMV and rfq related information (total event +
-- total event with no PO)
-- -----------------------------------------------------------------------------
aggregate_statistic_base AS (
  SELECT
    bg.byb_branch_code
    , bg.total_ord
    , bg.gmv
    , bg.gmv_unique_basic_supplier
    , bg.gmv_unique_premium_supplier
	, bg.gmv_prime_supplier
    , bg.revenue_supplier
    , bg.trading_unit
    , bg.unique_supplier
	, bg.unique_spb_interacted_with
    , bg.unique_basic_supplier
    , bg.unique_premium_supplier
    , bt.rfq_event_with_po
    , bt.rfq_event
	, (
		" . ( ($this->includeLeakage === false)?"NULL":"
		SELECT
			gmv_leakage
		FROM
			buyer_leakage bb
		WHERE
			bb.byb_branch_code=bg.byb_branch_code
		") . "
	) gmv_leakage
	, (
		" . ( ($this->includeLeakage === false)?"NULL":"
		SELECT
			rev_leakage
		FROM
			buyer_leakage bb
		WHERE
			bb.byb_branch_code=bg.byb_branch_code
		") . "
	) revenue_leakage
	, bg.unique_purchaser
  FROM
      base_gmv bg LEFT OUTER JOIN base_transaction bt
	  ON (bt.byb_branch_code=bg.byb_branch_code)
)
,
-- -----------------------------------------------------------------------------
-- aggregate statistic for the final result
-- -----------------------------------------------------------------------------
aggregate_statistic_joined AS (
  SELECT
      b.byb_branch_code
      , b.byb_name buyer_name
      , b.byb_branch_code buyer_tnid
      , (
        	CASE
				WHEN b.parent_branch_code=b.byb_branch_code THEN null
				ELSE b.parent_branch_code
			END
      ) parent_id
      , b.parent_branch_code
      , b.BYB_ACCT_MNGR_NAME
      , b.byb_contract_type
      , b.buyer_country
      , b.buyer_region
	  -- -----------------------------------------------------------------------
	  -- * * * coming from salesforce
	  -- -----------------------------------------------------------------------
      , NVL(b.byb_potential_units,0) 					byb_potential_units

	  -- -----------------------------------------------------------------------
	  -- * * * editable column from the buyer GMV UI
	  -- -----------------------------------------------------------------------
      , NVL(b.BYB_POTENTIAL_GMV_PER_UNIT,0) 			BYB_POTENTIAL_GMV_PER_UNIT

	  -- -----------------------------------------------------------------------
	  -- * * * Calculating potential GMV per unit based on above data
	  -- -----------------------------------------------------------------------
      , (
          NVL(b.BYB_POTENTIAL_GMV_PER_UNIT,0) * ( ABS(TRUNC(to_date(:endDate, 'DD-MON-YYYY'))-TRUNC(to_date(:startDate, 'DD-MON-YYYY')))/365 )
      ) BYB_POTENTIAL_GMV_PER_UNIT_PR

	  -- -----------------------------------------------------------------------
	  -- * * * editable column from the buyer GMV UI
	  -- -----------------------------------------------------------------------
      , NVL(b.byb_monthly_fee_per_unit,0) 				byb_monthly_fee_per_unit
      , (
          NVL(b.BYB_POTENTIAL_GMV_PER_UNIT,0) * NVL(b.byb_potential_units,0)
          * ( ABS(TRUNC(to_date(:endDate, 'DD-MON-YYYY'))-TRUNC(to_date(:startDate, 'DD-MON-YYYY')))/365 )
      ) total_potential_gmv

      , NVL(bg.total_ord, 0) 							total_ord
      , NVL(bg.gmv, 0) 									gmv
      , NVL(bg.gmv_unique_basic_supplier, 0) 			gmv_unique_basic_supplier
      , NVL(bg.gmv_unique_premium_supplier, 0) 			gmv_unique_premium_supplier
	  , NVL(bg.gmv_prime_supplier, 0) 					gmv_prime_supplier
      , NVL(bg.revenue_supplier, 0) 					revenue_supplier
      , NVL(bg.trading_unit, 0) 						trading_unit
      , NVL(bg.unique_spb_interacted_with, 0) 			unique_spb_interacted_with
	  , NVL(bg.unique_supplier, 0) 						unique_supplier
      , NVL(bg.unique_basic_supplier, 0) 				unique_basic_supplier
      , NVL(bg.unique_premium_supplier, 0) 				unique_premium_supplier
      , (
          	CASE
				WHEN b.GMV_TREND_3M=1 THEN 'up'
				WHEN b.GMV_TREND_3M=0 THEN 'down'
				ELSE ''
			END
      ) gmv_trend
      , NVL(bg.rfq_event_with_po, 0) 					rfq_event_with_po
      , NVL(bg.rfq_event, 0) 							rfq_event
      , NVL(bg.gmv_leakage, 0) 							gmv_leakage
      , NVL(bg.revenue_leakage, 0) 						revenue_leakage
	  , NVL(bg.unique_purchaser, 0) 					unique_purchaser
	  , (
			0
	  ) prime_spb_gmv
	  , b.ord_initial_txn_date
  FROM
      all_buyers b JOIN aggregate_statistic_base bg
	  ON (bg.byb_branch_code=b.byb_branch_code)
)
,
-- -----------------------------------------------------------------------------
-- getting information about the buyer where it doesn't have any GMV data or
-- any other data for that matters
-- -----------------------------------------------------------------------------
aggregate_statistic_un AS (
  SELECT
      b.byb_branch_code
      , b.byb_name buyer_name
      , b.byb_branch_code buyer_tnid
      , (
          CASE WHEN b.parent_branch_code=b.byb_branch_code THEN null ELSE b.parent_branch_code END
      ) parent_id
      , b.parent_branch_code
      , b.BYB_ACCT_MNGR_NAME
      , b.byb_contract_type
      , b.buyer_country
      , b.buyer_region
      , NVL(b.byb_potential_units,0) byb_potential_units
      , NVL(b.BYB_POTENTIAL_GMV_PER_UNIT,0) BYB_POTENTIAL_GMV_PER_UNIT
      , (
          NVL(b.BYB_POTENTIAL_GMV_PER_UNIT,0) * ( ABS(TRUNC(to_date(:endDate, 'DD-MON-YYYY'))-TRUNC(to_date(:startDate, 'DD-MON-YYYY')))/365 )
      ) BYB_POTENTIAL_GMV_PER_UNIT_PR
      , NVL(b.byb_monthly_fee_per_unit,0) byb_monthly_fee_per_unit
      , (
          NVL(b.BYB_POTENTIAL_GMV_PER_UNIT,0) * NVL(b.byb_potential_units,0)
          * ( ABS(TRUNC(to_date(:endDate, 'DD-MON-YYYY'))-TRUNC(to_date(:startDate, 'DD-MON-YYYY')))/365 )
      ) total_potential_gmv

      , 0 total_ord
      , 0 gmv
      , 0 gmv_unique_basic_supplier
      , 0 gmv_unique_premium_supplier
	  , 0 gmv_prime_supplier
      , 0 revenue_supplier
      , 0 trading_unit
      , 0 unique_spb_interacted_with
	  , 0 unique_supplier
      , 0 unique_basic_supplier
      , 0 unique_premium_supplier
      , '' gmv_trend
      , 0 rfq_event_with_po
      , 0 rfq_event
      , 0 gmv_leakage
      , 0 revenue_leakage
	  , 0 unique_purchaser
	  , (
		0
	  ) prime_spb_gmv
	  ,b.ord_initial_txn_date first_trading_date
  FROM
      all_buyers b
  WHERE
    NOT EXISTS(
      SELECT null
      FROM aggregate_statistic_joined j
      WHERE j.byb_branch_code=b.byb_branch_code
    )
)
,
-- -----------------------------------------------------------------------------
-- joining those two final aggregate statistic to avoid left outer join
-- -----------------------------------------------------------------------------
aggregate_statistic AS (
  SELECT * FROM aggregate_statistic_joined
  UNION ALL
  SELECT * FROM aggregate_statistic_un
)
,
-- -----------------------------------------------------------------------------
-- getting actual revenue data based on aggregate_statistic above
-- -----------------------------------------------------------------------------
advanced_statistic AS (
  SELECT
    b.*
    -- ----------------------------------------------------------------------------------------
    , (
        12 * NVL(b.trading_unit,0) * NVL(b.byb_monthly_fee_per_unit,0)
      * ( ABS(TRUNC(to_date(:endDate, 'DD-MON-YYYY'))-TRUNC(to_date(:startDate, 'DD-MON-YYYY')))/365 )
    ) actual_subcr_rev
    -- ----------------------------------------------------------------------------------------
    , (
        12 * NVL(b.byb_potential_units,0) * NVL(b.byb_monthly_fee_per_unit,0)
      * ( ABS(TRUNC(to_date(:endDate, 'DD-MON-YYYY'))-TRUNC(to_date(:startDate, 'DD-MON-YYYY')))/365 )
    ) potential_subcr_rev
    -- ----------------------------------------------------------------------------------------
    , (
        CASE
          WHEN b.revenue_supplier = 0 OR b.gmv = 0 OR total_potential_gmv = 0 THEN 0
          ELSE
            (NVL(b.revenue_supplier,0) / NVL(b.gmv,0) * 100) * total_potential_gmv
        END
    ) potential_spb_rev
    -- ----------------------------------------------------------------------------------------
    , (
        CASE
          WHEN b.byb_potential_units = 0 OR b.byb_monthly_fee_per_unit = 0 OR b.revenue_supplier = 0 OR b.gmv = 0 OR total_potential_gmv = 0 THEN 0
          ELSE
            (12 * NVL(b.byb_potential_units,0) * NVL(b.byb_monthly_fee_per_unit,0)) +
            ((NVL(b.revenue_supplier,0) / NVL(b.gmv,0) * 100) * total_potential_gmv)
        END
    ) total_potential_rev
    -- ----------------------------------------------------------------------------------------
    , (
        (12 * NVL(b.trading_unit,0) * NVL(b.byb_monthly_fee_per_unit,0)) + b.revenue_supplier
    ) actual_total_rev
    , (
        CASE
          WHEN  b.byb_potential_units = 0 OR b.gmv = 0
                OR b.total_potential_gmv = 0 OR b.trading_unit = 0
                OR b.byb_monthly_fee_per_unit = 0 OR b.revenue_supplier = 0 THEN 0
          ELSE
            (
              (12 * NVL(b.byb_potential_units,0) * NVL(b.byb_monthly_fee_per_unit,0)) +
              ((NVL(b.revenue_supplier,0) / NVL(b.gmv,0) * 100) * total_potential_gmv)
            )
            -
            ( (12 * NVL(b.trading_unit,0) * NVL(b.byb_monthly_fee_per_unit,0)) + b.revenue_supplier )
        END
    ) rev_opportunity
    -- ----------------------------------------------------------------------------------------
  FROM
    aggregate_statistic b
)
,
-- -----------------------------------------------------------------------------
-- aliasing because of legacy query
-- -----------------------------------------------------------------------------
base_data AS (
  SELECT * FROM advanced_statistic
)
,
-- -----------------------------------------------------------------------------
-- on the UI, if the view is the top level, we need to aggregate the children
-- data and add these children to parent's data
-- this cube (parent_data) only consists of the data of the parent itself not
-- including its children
-- -----------------------------------------------------------------------------
parent_data AS (
  SELECT
    final_result.*
  FROM
  (
    SELECT
      rr.*
      , (rr.pc_gmv_spb_monetisation_rate * rr.total_potential_gmv/100) potential_spb_rev
      , (rr.pc_gmv_spb_monetisation_rate * rr.total_potential_gmv/100) + rr.potential_subcr_rev total_potential_rev2
	  , (CASE WHEN rr.rfq_event > 0 THEN rr.rfq_event_with_po / rr.rfq_event * 100 ELSE 0 END) pc_rfq_event_with_po
    FROM
    (
      SELECT
        2 as depth
        , 'xx' path_id
        , t1.byb_branch_code
        , t1.buyer_name
        , t1.buyer_tnid
        , t1.parent_id
        , t1.BYB_ACCT_MNGR_NAME
        , t1.byb_contract_type
        , t1.buyer_country
        , t1.buyer_region
        , t1.byb_monthly_fee_per_unit
        , (
          	t1.gmv_trend
        ) gmv_trend
        , t1.gmv_leakage
        , t1.revenue_leakage
        , t1.gmv
        , t1.BYB_POTENTIAL_GMV_PER_UNIT
		, (CASE WHEN t1.BYB_POTENTIAL_GMV_PER_UNIT > 0 THEN 1 END) TOTAL_CHLDRN_HAS_P_GMV
        , t1.BYB_POTENTIAL_GMV_PER_UNIT_PR
        , t1.total_potential_gmv
        , t1.actual_subcr_rev
        , t1.potential_subcr_rev
		, (
			t1.unique_spb_interacted_with
        ) unique_spb_interacted_with
		, (
		  SELECT unique_supplier FROM unique_data WHERE byb_branch_code=t1.buyer_tnid
		) unique_supplier

		, (
		  SELECT unique_premium_supplier FROM unique_data WHERE byb_branch_code=t1.buyer_tnid
		) unique_premium_supplier

		, (
		  SELECT unique_basic_supplier FROM unique_data WHERE byb_branch_code=t1.buyer_tnid
		) unique_basic_supplier
        , t1.GMV_UNIQUE_BASIC_SUPPLIER
        , t1.gmv_unique_premium_supplier
		, t1.gmv_prime_supplier
        , t1.revenue_supplier
        , t1.total_potential_rev
        , t1.actual_total_rev
        , t1.rev_opportunity
        , 0 total_children
        , t1.trading_unit trading_unit_xxx
		, (
			SELECT COUNT( DISTINCT imo_no_vessel_name) FROM transaction_with_po WHERE byb_branch_code=t1.byb_branch_code
		) trading_unit
        , t1.byb_potential_units
        , (
              CASE
                WHEN (t1.TOTAL_POTENTIAL_GMV) = 0 THEN 0
                ELSE
                  (t1.gmv) / (t1.TOTAL_POTENTIAL_GMV) * 100
              END
        ) pc_gmv_captured
        , (
              CASE
                WHEN t1.TOTAL_POTENTIAL_GMV = 0 THEN 0
                ELSE
                  NVL(t1.gmv,0) / NVL(t1.TOTAL_POTENTIAL_GMV,0)*100
              END
        ) c_pc_gmv_captured
        , (
              CASE
                WHEN (t1.gmv) = 0 THEN 0
                ELSE
                  (t1.gmv_unique_premium_supplier) / (t1.gmv) * 100
              END
        ) pc_gmv_to_premium_spb

        , (
              CASE
                WHEN t1.gmv = 0 THEN 0
                ELSE
                  NVL(t1.gmv_unique_premium_supplier,0) / NVL(t1.gmv,0) * 100
              END
        ) c_pc_gmv_to_premium_spb

        , (
              CASE
                WHEN t1.gmv = 0 THEN 0
                ELSE
                  t1.revenue_supplier / t1.gmv * 100
              END
        ) pc_gmv_spb_monetisation_rate

        , (
           CASE
                WHEN t1.gmv = 0 THEN 0
                ELSE
                  NVL(t1.revenue_supplier,0) / NVL(t1.gmv,0) * 100
              END
        ) c_pc_gmv_spb_monetisation_rate
        , (
            CASE
              WHEN t1.total_potential_rev = 0 THEN 0
              ELSE
                t1.actual_total_rev / t1.total_potential_rev * 100
            END
        ) potential_rev_captured
        , t1.rfq_event
        , t1.rfq_event_with_po
		, t1.unique_purchaser
		, t1.prime_spb_gmv
		, t1.ord_initial_txn_date first_trading_date
      FROM
        base_data t1
      WHERE
        t1.byb_branch_code=" . (($this->parentId != "")?$this->parentId:"9999999999") . "
    ) rr
  ) final_result
)
,
-- -----------------------------------------------------------------------------
-- rolling up children data to the parent
-- -----------------------------------------------------------------------------
all_aggregated_data AS
(
	SELECT
	  final_result.*
	FROM
	(
		SELECT
			rr.*
			, (rr.pc_gmv_spb_monetisation_rate * rr.total_potential_gmv/100) potential_spb_rev
			, (rr.pc_gmv_spb_monetisation_rate * rr.total_potential_gmv/100) + rr.potential_subcr_rev total_potential_rev2
	    	, (CASE WHEN rr.rfq_event > 0 THEN rr.rfq_event_with_po / rr.rfq_event * 100 ELSE 0 END) pc_rfq_event_with_po
		FROM
		(
			SELECT
			  level as depth
			  ,	substr(replace(SYS_CONNECT_BY_PATH(decode(t1.buyer_tnid, null, t1.buyer_name, t1.buyer_tnid), '>'), '>', '/'), 2) path_id
			  , t1.byb_branch_code
			  , t1.buyer_name
			  , t1.buyer_tnid
			  , t1.parent_id
			  , t1.BYB_ACCT_MNGR_NAME
			  , t1.byb_contract_type
			  , t1.buyer_country
			  , t1.buyer_region
			  , t1.byb_monthly_fee_per_unit
			  , t1.gmv_trend
	      	  , (
			      SELECT SUM(t2.gmv_leakage)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) gmv_leakage
	      	  , (
			      SELECT SUM(t2.revenue_leakage)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) revenue_leakage
			  , (
			      SELECT SUM(t2.gmv)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) gmv
			  , (
			      SELECT AVG(t2.BYB_POTENTIAL_GMV_PER_UNIT)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) BYB_POTENTIAL_GMV_PER_UNIT
			  , (
			      SELECT SUM(CASE WHEN t2.BYB_POTENTIAL_GMV_PER_UNIT > 0 THEN 1 ELSE 0 END )
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) TOTAL_CHLDRN_HAS_P_GMV
			  , (
			      SELECT AVG(t2.BYB_POTENTIAL_GMV_PER_UNIT_PR)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) BYB_POTENTIAL_GMV_PER_UNIT_PR
			  , (
			      SELECT SUM(t2.total_potential_gmv)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) total_potential_gmv
			  , (
			      SELECT SUM(t2.actual_subcr_rev)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) actual_subcr_rev
			  , (
			      SELECT SUM(t2.potential_subcr_rev)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) potential_subcr_rev
			  , (
				  SELECT SUM(t2.unique_spb_interacted_with)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) unique_spb_interacted_with
			  , (
            	  SELECT SUM(t2.unique_supplier)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) unique_supplier
			  , (
            	  SELECT SUM(t2.unique_premium_supplier)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) unique_premium_supplier
			  , (
            	  SELECT SUM(t2.unique_basic_supplier)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) unique_basic_supplier
			  , (
			      SELECT SUM(t2.GMV_UNIQUE_BASIC_SUPPLIER)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) GMV_UNIQUE_BASIC_SUPPLIER
			  , (
			      SELECT SUM(t2.gmv_unique_premium_supplier)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) gmv_unique_premium_supplier
			  , (
			      SELECT SUM(t2.gmv_prime_supplier)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) gmv_prime_supplier
			  , (
			      SELECT SUM(t2.revenue_supplier)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) revenue_supplier
			  , (
			      SELECT SUM(t2.total_potential_rev)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) total_potential_rev
			  , (
			      SELECT SUM(t2.actual_total_rev)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) actual_total_rev
			  , (
			      SELECT SUM(t2.rev_opportunity)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) rev_opportunity
			  , (
			      SELECT COUNT(*)-1
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) total_children
			  , (
			      SELECT SUM(t2.trading_unit)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) trading_unit_xxx
			  , (
				  SELECT COUNT( DISTINCT imo_no_vessel_name) FROM transaction_with_po WHERE byb_branch_code=t1.buyer_tnid OR parent_branch_code=t1.buyer_tnid
			  ) trading_unit
			  , (
			      SELECT SUM(t2.byb_potential_units)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) byb_potential_units
			  , (
			      SELECT
			        CASE
			          WHEN SUM(t2.TOTAL_POTENTIAL_GMV) = 0 THEN 0
			          ELSE
			            SUM(t2.gmv) / SUM(t2.TOTAL_POTENTIAL_GMV) * 100
			        END
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) pc_gmv_captured
			  , (
			        CASE
			          WHEN t1.TOTAL_POTENTIAL_GMV = 0 THEN 0
			          ELSE
			            NVL(t1.gmv,0) / NVL(t1.TOTAL_POTENTIAL_GMV,0)*100
			        END
			  ) c_pc_gmv_captured
			  , (
			      SELECT
			        CASE
			          WHEN SUM(t2.gmv) = 0 THEN 0
			          ELSE
			            SUM(t2.gmv_unique_premium_supplier) / SUM(t2.gmv) * 100
			        END
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) pc_gmv_to_premium_spb
			  , (
			        CASE
			          WHEN t1.gmv = 0 THEN 0
			          ELSE
			            NVL(t1.gmv_unique_premium_supplier,0) / NVL(t1.gmv,0) * 100
			        END
			  ) c_pc_gmv_to_premium_spb
			  , (
			      SELECT
			        CASE
			          WHEN SUM(t2.gmv) = 0 THEN 0
			          ELSE
			            SUM(t2.revenue_supplier) / SUM(t2.gmv) * 100
			        END
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) pc_gmv_spb_monetisation_rate
			  , (
			        CASE
			          WHEN t1.gmv = 0 THEN 0
			          ELSE
			            NVL(t1.revenue_supplier,0) / NVL(t1.gmv,0) * 100
			        END
			  ) c_pc_gmv_spb_monetisation_rate
			  , (
			      SELECT
			        CASE
			          WHEN SUM(t2.total_potential_rev) = 0 THEN 0
			          ELSE
			            SUM(t2.actual_total_rev) / SUM(t2.total_potential_rev) * 100
			        END
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) potential_rev_captured
			  , (
			      SELECT
			        SUM(t2.rfq_event)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) rfq_event
			  , (
			      SELECT
			        SUM(t2.rfq_event_with_po)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) rfq_event_with_po
			  , (
			      SELECT
			        SUM(t2.unique_purchaser)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) unique_purchaser
			  , (
			      SELECT
			        SUM(t2.prime_spb_gmv)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
			  ) prime_spb_gmv
        	 , (
                  SELECT
			        MIN(t2.ord_initial_txn_date)
			      FROM base_data t2
			      START WITH t2.buyer_tnid=t1.buyer_tnid
			      CONNECT BY PRIOR t2.buyer_tnid=t2.parent_id
		        ) first_trading_date
			FROM
				base_data t1
				" . (($this->parentId != "")?"START WITH byb_branch_code=" . $this->parentId:"START WITH parent_id IS NULL") . "
			CONNECT BY PRIOR t1.buyer_tnid=t1.parent_id
			ORDER BY level, buyer_name
		) rr
	) final_result
	WHERE
		depth=" . (($this->parentId != "")?2:1) . "
)
-- if parentId is not empty; then get the data for the parent only (not aggregated up)
SELECT * FROM parent_data

UNION ALL

-- aggregated data up
SELECT * FROM all_aggregated_data
";
		// preparing the startData
		$params = array(
			'startDate' => $this->startDate->format("d-M-Y")
			, 'endDate' => $this->endDate->format("d-M-Y")
		);

		if( $_GET['terminated'] == 1 ){
			echo $sql; print_r($params); die();
		}

		$key = md5($sql) .  "Shipserv_Report_Buyer_Gmv" . print_r($params, true) . print_r($_GET, true);

		if( $this->resetMemcache == true )
		{
			$this->purgeCache($key);  // warning by Yuriy Akopov - that key is purged in its raw form, without decoration (legacy)
		}

		$result = $this->fetchCachedQuery ($sql, $params, $key, self::MEMCACHE_TTL, 'ssreport2');

		return $result;
	}

	public function getTrends( $tnids, $isParent=true ){

		if( is_array($tnids) == false ) $bybBranchCode[] = $tnids;
		else $bybBranchCode = $tnids;

		$sql = "
		WITH base AS (
			SELECT
			  TO_CHAR(ord_orig_submitted_date, 'YYYYMM') month_number
			  , TO_CHAR(ord_orig_submitted_date, 'MON') month_name
			  , TO_CHAR(ord_orig_submitted_date, 'MON-YYYY') month_year
			  , SUM(final_total_cost_usd) final_total_gmv
			  , SUM(ord_total_cost_usd) total_gmv
			FROM
			  ord_traded_gmv otg
        	  	JOIN 
        		supplier s  ON (otg.spb_branch_code = s.spb_branch_code)
			WHERE
				s.spb_is_test_account=0
				AND	ord_orig_submitted_date BETWEEN ADD_MONTHS( TRUNC(SYSDATE, 'MM'), :startMonth) AND LAST_DAY(ADD_MONTHS( TRUNC(SYSDATE, 'MM'), :endMonth))
				AND byb_branch_code IN (
					SELECT byb_branch_code
					FROM buyer
					WHERE
						byb_branch_code IN (" . implode(",", $bybBranchCode) . ")
						" . ( ($isParent == true) ? "OR parent_branch_code IN (" . implode(",", $bybBranchCode) . ")" : "" ) . "
				)
			GROUP BY
			  TO_CHAR(ord_orig_submitted_date, 'YYYYMM')
			  , TO_CHAR(ord_orig_submitted_date, 'MON')
			  , TO_CHAR(ord_orig_submitted_date, 'MON-YYYY')
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
  					TO_CHAR(trunc(add_months(sysdate, :endMonth-level + 1),'MM'), 'YYYYMM') month_number
  					, TO_CHAR(trunc(add_months(sysdate, :endMonth-level + 1),'MM'), 'MON') month_name
  					, TO_CHAR(trunc(add_months(sysdate, :endMonth-level + 1),'MM'), 'MON-YYYY') month_year
  				FROM dual
  				CONNECT BY LEVEL<=12
  			) m LEFT OUTER JOIN base b ON m.month_number=b.month_number
		  ORDER BY
		  	month_number
		";

		$params = array(
			'startMonth' => -24
			, 'endMonth' => -13
		);

		if( $_GET['terminated'] == 1 ){
			echo $sql; print_r($params); die();
		}


		$key = md5($sql) .  "Shipserv_Report_Buyer_Gmv" . print_r($params, true) . print_r($_GET, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, self::MEMCACHE_TTL, 'ssreport2');

		foreach($data as $row)
		{
			$new[1][$row['MONTH_NAME'] ] = $row;
		}

		$params = array(
			'startMonth' => -12
			, 'endMonth' => -1
		);
		$key = md5($sql) .  "Shipserv_Report_Buyer_Gmv" . print_r($params, true) . print_r($_GET, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, self::MEMCACHE_TTL, 'ssreport2');

		foreach($data as $row)
		{
			$new[0][$row['MONTH_NAME'] ] = $row;
		}

		foreach((array)$new[0] as $monthNumber => $data ){
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


	public function getAverageGMVPerVesselTrends( $tnids, $isParent=true ){

		if( is_array($tnids) == false ) $bybBranchCode[] = $tnids;
		else $bybBranchCode = $tnids;

		$sql = "
		WITH base AS (
			SELECT
		      TO_CHAR(ord_orig_submitted_date, 'YYYYMM') month_number
			  , TO_CHAR(ord_orig_submitted_date, 'MON') month_name
			  , TO_CHAR(ord_orig_submitted_date, 'MON-YYYY') month_year
		      , SUM(final_total_cost_usd) final_total_gmv
		      , SUM(ord_traded_gmv.ord_total_cost_usd) total_gmv

			  , COUNT(DISTINCT DECODE( vslh1.vslh_id_grouped_to,
		  			0, decode( vslh1.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh1.vslh_imo, '-', 'INVALID IMO', vslh1.vslh_imo )) || '~' || decode( vslh1.vslh_ihs_name, '-', decode( vslh1.vslh_name, '-', 'NO VESSEL NAME', vslh1.vslh_name ), vslh1.vslh_ihs_name ),
		  			nvl(( select decode( vslh2.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh2.vslh_imo, '-', 'INVALID IMO', vslh2.vslh_imo )) || '~' || decode( vslh2.vslh_ihs_name, '-', decode( vslh2.vslh_name, '-', 'NO VESSEL NAME', vslh2.vslh_name ), vslh2.vslh_ihs_name )
		  					from vessel_history vslh2
		  				   where vslh2.vslh_id = vslh1.vslh_id_grouped_to
		  					 and rownum = 1 ),
		  				'INVALID IMO~NO VESSEL NAME'
		  			   )
		  		  )
			)  total_trading_unit

		    FROM
		    	ord_traded_gmv JOIN ord ON (ord_traded_gmv.ord_original=ord.ord_internal_ref_no)
        	  	JOIN 
        		supplier s  ON (ord_traded_gmv.spb_branch_code = s.spb_branch_code)
				JOIN vessel_history vslh1 ON (
		  			vslh1.byb_branch_code = ord.byb_branch_code
		  			AND vslh1.vslh_is_valid_imo = validate_imo_no( upper( trim( ord.ord_imo_no )))
		  			AND vslh1.vslh_imo = nvl( ord.ord_imo_no, '-' )
		  			AND vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( ord.ord_vessel_name ))), '-' )
		  		)
		    WHERE
		      s.spb_is_test_account=0
		      AND ord_orig_submitted_date BETWEEN ADD_MONTHS( TRUNC(SYSDATE, 'MM'), :startMonth) AND LAST_DAY(ADD_MONTHS( TRUNC(SYSDATE, 'MM'), :endMonth))
			  AND ord_traded_gmv.byb_branch_code IN (
				  SELECT byb_branch_code
				  FROM buyer
				  WHERE
					  byb_branch_code IN (" . implode(",", $bybBranchCode) . ")
					  " . ( ($isParent == true) ? "OR parent_branch_code IN (" . implode(",", $bybBranchCode) . ")" : "" ) . "
			  )

		    GROUP BY
		      TO_CHAR(ord_orig_submitted_date, 'YYYYMM')
		      , TO_CHAR(ord_orig_submitted_date, 'MON')
			  , TO_CHAR(ord_orig_submitted_date, 'MON-YYYY')
		    ORDER BY
		      TO_CHAR(ord_orig_submitted_date, 'YYYYMM')
		)
		SELECT
			m.month_number
			, m.month_name
			, m.month_year
			, b.total_gmv
			, b.total_trading_unit
			, b.total_gmv/b.total_trading_unit AVG_GMV
		FROM
			(
				SELECT
					TO_CHAR(trunc(add_months(sysdate, :endMonth-level +1),'MM'), 'YYYYMM') month_number
					, TO_CHAR(trunc(add_months(sysdate, :endMonth-level +1),'MM'), 'MON') month_name
					, TO_CHAR(trunc(add_months(sysdate, :endMonth-level +1),'MM'), 'MON-YYYY') month_year
				FROM dual
				CONNECT BY LEVEL<=12
			) m LEFT OUTER JOIN base b ON m.month_number=b.month_number
		ORDER BY
			m.month_number
		";


		$params = array(
			'startMonth' => -24
			, 'endMonth' => -13
		);
		//echo $sql; print_r($params); die();

		$key = md5($sql) .  "Shipserv_Report_Buyer_Gmv_getAverageGMVPerVesselTrends" . print_r($params, true) . print_r($_GET, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, self::MEMCACHE_TTL, 'ssreport2');

		foreach($data as $row)
		{
			$new[1][$row['MONTH_NAME'] ] = $row;
		}
		$params = array(
			'startMonth' => -12
			, 'endMonth' => -1
		);
		$key = md5($sql) .  "Shipserv_Report_Buyer_Gmv_getAverageGMVPerVesselTrends" . print_r($params, true) . print_r($_GET, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, self::MEMCACHE_TTL, 'ssreport2');

		foreach($data as $row)
		{
			$new[0][$row['MONTH_NAME'] ] = $row;
		}

		foreach((array)$new[0] as $monthNumber => $data ){
			$result[] = array(
				(($new[1][$monthNumber]['MONTH_NAME']!="")?$new[1][$monthNumber]['MONTH_NAME']:$new[0][$monthNumber]['MONTH_NAME'])
				, (($new[0][$monthNumber]['AVG_GMV']>0)?(int)$new[0][$monthNumber]['AVG_GMV']:0)
				, (($new[1][$monthNumber]['AVG_GMV']>0)?(int)$new[1][$monthNumber]['AVG_GMV']:0)
				, (($new[1][$monthNumber]['TOTAL_TRADING_UNIT']>0)?(int)$new[1][$monthNumber]['TOTAL_TRADING_UNIT']:0)
				, (($new[0][$monthNumber]['TOTAL_TRADING_UNIT']>0)?(int)$new[0][$monthNumber]['TOTAL_TRADING_UNIT']:0)
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

		$this->periodForTrendsVessel = $db->fetchAll($sql);

		return $result;
	}

	public function getTotalGMVPerVesselTrends( $tnids, $isParent=true )
	{


		$result = array(
					'priorToTrailing' => array(
							'totalGmv' => 0,
							'totalTradingUnit' => 0,
							'avgGmvPerUnit' => 0,
						)
					,'trailing' => array(
							'totalGmv' => 0,
							'totalTradingUnit' => 0,
							'avgGmvPerUnit' => 0,
						)
			);
		
		if( is_array($tnids) == false ) $bybBranchCode[] = $tnids;
		else $bybBranchCode = $tnids;

		$sql = "
		WITH base AS (
			SELECT
				  SUM(final_total_cost_usd) final_total_gmv
				, SUM(ord_traded_gmv.ord_total_cost_usd) total_gmv
				, COUNT(DISTINCT DECODE( vslh1.vslh_id_grouped_to,
				0, decode( vslh1.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh1.vslh_imo, '-', 'INVALID IMO', vslh1.vslh_imo )) || '~' || decode( vslh1.vslh_ihs_name, '-', decode( vslh1.vslh_name, '-', 'NO VESSEL NAME', vslh1.vslh_name ), vslh1.vslh_ihs_name ),
				nvl(( select decode( vslh2.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh2.vslh_imo, '-', 'INVALID IMO', vslh2.vslh_imo )) || '~' || decode( vslh2.vslh_ihs_name, '-', decode( vslh2.vslh_name, '-', 'NO VESSEL NAME', vslh2.vslh_name ), vslh2.vslh_ihs_name )
					FROM vessel_history vslh2
					WHERE vslh2.vslh_id = vslh1.vslh_id_grouped_to
					and rownum = 1 ),
					'INVALID IMO~NO VESSEL NAME'
					)
		  		  )
				)  total_trading_unit
			FROM
				ord_traded_gmv JOIN ord ON (ord_traded_gmv.ord_original=ord.ord_internal_ref_no)
			        JOIN 
        				supplier s  ON (ord_traded_gmv.spb_branch_code = s.spb_branch_code)
					JOIN vessel_history vslh1 ON (
						vslh1.byb_branch_code = ord.byb_branch_code
						AND vslh1.vslh_is_valid_imo = validate_imo_no( upper( trim( ord.ord_imo_no )))
						AND vslh1.vslh_imo = nvl( ord.ord_imo_no, '-' )
						AND vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( ord.ord_vessel_name ))), '-' )
					)
			WHERE
				s.spb_is_test_account=0
				AND ord_orig_submitted_date BETWEEN ADD_MONTHS( TRUNC(SYSDATE, 'MM'), :startMonth) AND LAST_DAY(ADD_MONTHS( TRUNC(SYSDATE, 'MM'), :endMonth))
				AND ord_traded_gmv.byb_branch_code IN (
					SELECT byb_branch_code
					FROM buyer
					WHERE
						 byb_branch_code IN (" . implode(",", $bybBranchCode) . ")
						" . ( ($isParent == true) ? "OR parent_branch_code IN (" . implode(",", $bybBranchCode) . ")" : "" ) . "
					)
			)
			SELECT
				  b.total_gmv
				, b.total_trading_unit
				, (
					CASE WHEN
						b.total_trading_unit > 0
					THEN
						ROUND(
							b.total_gmv / b.total_trading_unit
							,0
						)
					ELSE
						0
					END
				) avg_gmv_per_unit
			FROM
				base b";

		$params = array(
			'startMonth' => -24
			, 'endMonth' => -13
		);


		$key = md5($sql) .  "Shipserv_Report_Buyer_Gmv_getTotalGMVPerVesselTrends" . print_r($params, true) . print_r($_GET, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, self::MEMCACHE_TTL, 'ssreport2');

		if (count($data)  == 1)
		{
			$result['priorToTrailing'] = array(
							'totalGmv' => $data[0]['TOTAL_GMV'],
							'totalTradingUnit' => $data[0]['TOTAL_TRADING_UNIT'],
							'avgGmvPerUnit' => $data[0]['AVG_GMV_PER_UNIT']
						);
		}
		

		$params = array(
			'startMonth' => -12
			, 'endMonth' => -1
		);
		$key = md5($sql) .  "Shipserv_Report_Buyer_Gmv_getTotalGMVPerVesselTrends" . print_r($params, true) . print_r($_GET, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, self::MEMCACHE_TTL, 'ssreport2');

		if (count($data)  == 1)
		{
			$result['trailing'] = array(
							'totalGmv' => $data[0]['TOTAL_GMV'],
							'totalTradingUnit' => $data[0]['TOTAL_TRADING_UNIT'],
							'avgGmvPerUnit' => $data[0]['AVG_GMV_PER_UNIT']
						);
		}

		return $result;

	}
}
