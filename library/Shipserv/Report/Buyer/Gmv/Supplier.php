<?php
class Shipserv_Report_Buyer_Gmv_Supplier extends Shipserv_Report
{
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

			if( $params['buyerId'] != "" )
			{
				$this->buyer = Shipserv_Buyer::getBuyerBranchInstanceById($params['buyerId']);
				$this->buyerTnid = $params['buyerId'];
			}
			if( $params['parent'] != "" )
			{
				$this->isParent = ((int)$params['parent'] == 1);
			}
		}
	}

	public function getBuyer()
	{
		return $this->buyer;
	}

	public function getSupplierInteractedWithData(){
		$sql = "


		-- -----------------------------------------------------------------------------
		-- pulling prime supplier
		-- -----------------------------------------------------------------------------
		WITH
		-- -----------------------------------------------------------------------------
		-- getting list of buyers and all attributes
		-- -----------------------------------------------------------------------------
		all_buyers AS (
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
			 FROM
			    buyer_branch@livedb_link bbb JOIN buyer b ON (b.byb_branch_code=bbb.byb_branch_code)
			 WHERE
				bbb.byb_branch_code = :buyerTnid
				" . ( ($this->isParent == true) ? "OR b.parent_branch_code = :buyerTnid" : "" ) . "
		
		),
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
		    , final_total_cost_usd ord_total_cost_usd
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
		    , final_total_cost_usd ord_total_cost_usd
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
		    lrqp.rfq_internal_ref_no
		    , lrqp.rfq_submitted_date AS rfq_submitted_date
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
		    , lrqp.rfq_is_declined
		    , 0 leakage_usd_total
		    , 0 leakage_vbp_revenue
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
		    null rfq_internal_ref_no
		    , null rfq_submitted_date
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
		    , NULL rfq_is_declined
		    , 0 leakage_usd_total
		    , 0 leakage_vbp_revenue
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
		--
		-- rfq sent within the date range
		--
		transaction_sent AS(
		  SELECT
		    lrqp.rfq_internal_ref_no
		    , lrqp.rfq_submitted_date
		    , ab.byb_branch_code AS byb_branch_code
		    , ab.parent_branch_code AS parent_branch_code
		    , lrqp.spb_branch_code AS spb_branch_code
		    , lrqp.rfq_event_hash
		    , lrqp.qot_price
		    , lrqp.is_cheapest
		    , lrqp.is_quickest
		    , o.ord_submitted_date AS ord_submitted_date
		    , o.ord_total_cost_usd AS ord_total_cost_usd
		    , o.ord_internal_ref_no AS ord_internal_ref_no
		    , DECODE( vslh1.vslh_id_grouped_to,
		        0, decode( vslh1.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh1.vslh_imo, '-', 'INVALID IMO', vslh1.vslh_imo )) || '~' || decode( vslh1.vslh_ihs_name, '-', decode( vslh1.vslh_name, '-', 'NO VESSEL NAME', vslh1.vslh_name ), vslh1.vslh_ihs_name ),
		        nvl(( select decode( vslh2.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh2.vslh_imo, '-', 'INVALID IMO', vslh2.vslh_imo )) || '~' || decode( vslh2.vslh_ihs_name, '-', decode( vslh2.vslh_name, '-', 'NO VESSEL NAME', vslh2.vslh_name ), vslh2.vslh_ihs_name )
		            from vessel_history vslh2
		             where vslh2.vslh_id = vslh1.vslh_id_grouped_to
		             and rownum = 1 ),
		          'INVALID IMO~NO VESSEL NAME'
		           )
		        ) as imo_no_vessel_name
		    , o.ord_total_vbp_usd AS ord_total_vbp_usd
		    , lrqp.purchaser_detail AS purchaser_detail
		    , s.spb_is_paying
		    , s.spb_is_prime_supplier
		    , lrqp.rfq_is_declined
		    , lrqp.leakage_usd_total
		    , lrqp.leakage_vbp_revenue
		  FROM
		    linked_rfq_qot_po lrqp JOIN supplier s
		    ON (s.spb_branch_code=lrqp.spb_branch_code)
		    	JOIN all_buyers ab
		      ON (ab.byb_branch_code=lrqp.byb_branch_code)
		        JOIN vessel_history vslh1
		        ON (
		          vslh1.byb_branch_code = lrqp.byb_branch_code
		          AND vslh1.vslh_is_valid_imo = validate_imo_no( upper( trim( lrqp.imo_no )))
		          AND vslh1.vslh_imo = nvl( lrqp.imo_no, '-' )
		          AND vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( lrqp.vessel_name ))), '-' )
		        )
		          LEFT OUTER JOIN ord o
		          ON (lrqp.ord_internal_ref_no=o.ord_internal_ref_no)
		  WHERE
		    lrqp.rfq_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY') AND to_date(:endDate, 'DD-MON-YYYY') + 0.99999
		    AND NOT EXISTS(
		      SELECT NULL FROM transaction_with_po twp
		      WHERE lrqp.ord_internal_ref_no=twp.ord_internal_ref_no
		    )
		    AND NOT EXISTS(
		      SELECT NULL FROM transaction_with_po twp
		      WHERE lrqp.rfq_internal_ref_no=twp.rfq_internal_ref_no
		    )

		)
		,
		--
		-- all transactions gathered together
		--
		all_transaction_base AS (
		  SELECT * FROM transaction_with_po
		  UNION ALL
		  SELECT * FROM transaction_sent
		)
		,
		--
		--
		--
		all_transaction AS (
		  SELECT
		    a.*
		    , COUNT(ord_internal_ref_no) OVER (PARTITION BY rfq_event_hash) rfq_event_has_order
		    , COUNT(DISTINCT spb_branch_code) OVER (PARTITION BY rfq_event_hash) total_supplier
		  FROM
		    all_transaction_base a
		)
		,
		--
		--
		--
		base AS (
		SELECT
		  spb_branch_code
		  , COUNT(DISTINCT rfq_event_hash) total_rfq_event
		  , COUNT(
			  DISTINCT
			  CASE
				WHEN qot_price IS NULL AND rfq_is_declined!=1 THEN rfq_event_hash
			  END
		  ) total_rfq_event_ignored
		  , COUNT(
			  DISTINCT
			  CASE
				WHEN qot_price IS NOT null OR rfq_is_declined=1
				THEN rfq_event_hash
			  END
		  ) total_rfq_event_actioned
		  , COUNT(
			  DISTINCT
			  CASE
				WHEN qot_price > 0
				THEN rfq_event_hash
			  END
		  ) total_rfq_event_quoted
		  , COUNT(
			  DISTINCT
			  CASE
				WHEN ord_total_cost_usd is null 
				THEN rfq_event_hash
			  END
		  ) total_rfq_event_no_order
		  -- RFQ Events where no PO resulted
		  , COUNT(
			  DISTINCT
			  CASE
				WHEN ord_internal_ref_no IS NOT null
				THEN rfq_event_hash
			  END
		  ) total_rfq_event_with_order
		  -- RFQ Events where PO raised

		  , COUNT(
			  DISTINCT
			  CASE
				WHEN total_supplier=1
				THEN rfq_event_hash
			  END
		  ) total_rfq_event_sole_spb
		  -- RFQ Events where sole supplier

		  , COUNT(
			  DISTINCT
			  CASE
				WHEN is_cheapest=1 AND ord_internal_ref_no IS NOT null
				THEN rfq_event_hash
			  END
		  ) total_rfq_event_is_cheapest
		  -- RFQ Events where supplier was cheapest but no PO was raised

		  , SUM(
			  leakage_usd_total
		  ) total_leakage
		  -- Estimated GMV Leakage

		  , SUM(
			  leakage_vbp_revenue
		  ) total_revenue_leakage
		  -- Estimate Revenue Leakage

		  -- Number Competitive POs with no quote (POs won by supplier where there is an underlying RFQ event and this supplier did not quote)


		  , COUNT(
			  DISTINCT
			  CASE
				WHEN ord_internal_ref_no IS NOT null AND qot_price > 0
				THEN
				  rfq_event_hash
			  END
		  ) total_competitive_po
		  -- Number Competitive POs with Quote (POs won by supplier where there is an underlying RFQ event and this supplier quoted)

		  , COUNT(
			  DISTINCT
			  CASE
				WHEN ord_internal_ref_no IS NOT null AND rfq_internal_ref_no IS null
				THEN rfq_event_hash
			  END
		  ) total_direct_po
		  -- Number Direct POs (From buyer to this supplier, where no RFQ/Quote or supplier was only one selected)

		  , SUM(
			ord_total_cost_usd
		  ) actual_gmv
		  -- Actual GMV

		  , (
			CASE
			  WHEN SUM(ord_total_cost_usd)>0
			  THEN SUM(ord_total_vbp_usd)/SUM(ord_total_cost_usd)*100
			  ELSE 0
			END
		  ) achieved_monetisation_pc
		  -- Monetisation

		  , SUM(
			ord_total_vbp_usd
		  ) revenue
		  -- Revenue

	  , COUNT(DISTINCT CASE WHEN ord_total_cost_usd is NOT NULL THEN imo_no_vessel_name END ) total_vessel
		  , (
		      ROUND(
		        SUM(ord_total_cost_usd) / COUNT(DISTINCT byb_branch_code)
		        ,2
		        )
		     ) avg_gmv_per_supplier
		  , (
				SELECT
				 ROUND(
				  SUM(ord_total_cost_usd) / COUNT(DISTINCT byb_branch_code)
				  ,2
				  ) avg_gmv
				FROM
				  ord_traded_gmv otg
				WHERE 
				  otg.spb_branch_code = all_transaction.spb_branch_code
				  AND otg.ord_orig_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY') AND to_date(:endDate, 'DD-MON-YYYY') + 0.99999
			) avg_gmv

		  FROM
		    all_transaction
		  GROUP BY
		    spb_branch_code
		)

		SELECT
		  b.*
		  , DECODE(total_rfq_event, 0, 0, total_rfq_event_actioned/total_rfq_event*100) response_rate
		  , DECODE(total_rfq_event, 0, 0, total_rfq_event_quoted/total_rfq_event*100) quote_rate
		  , DECODE(total_rfq_event, 0, 0, total_rfq_event_no_order/total_rfq_event*100) no_po_rate
		  , DECODE(total_rfq_event, 0, 0, total_rfq_event_with_order/total_rfq_event*100) win_rate
		   , (
		      SELECT
		        COUNT(DISTINCT byb_branch_code) 
		      FROM
		        ord
		      WHERE
		        spb_branch_code = s.spb_branch_code
     	  ) total_buyers_per_supplier
      	 , (
		      SELECT  
		        (
			        CASE WHEN SUM(final_total_vbp_usd) > 0 THEN
				        SUM(CASE WHEN byb_branch_code = :buyerId THEN final_total_vbp_usd else 0 END) /
				        SUM(final_total_vbp_usd) * 100 
			        END
		        ) supplier_buyer_revenue
		      FROM
		        ord_traded_gmv tg
		      WHERE
		      	tg.spb_branch_code = s.spb_branch_code
	      ) supplier_buyer_revenue
		  , s.spb_name
		  , ss.spb_acct_mngr_name
		  , s.spb_monetization_percent MONETISATION_PC
		  , ss.spb_region
		  , s.spb_country_code
		  , s.spb_interface

		FROM
		  base b JOIN supplier s
		  ON (s.spb_branch_code=b.spb_branch_code)
		    JOIN (SELECT spb_branch_code, spb_acct_mngr_name, spb_region FROM supplier_branch@livedb_link) ss
		    ON (ss.spb_branch_code=s.spb_branch_code)
		WHERE
	      s.spb_branch_code IN (
	        SELECT spb_branch_code FROM all_orders
	      )
		";

		$params = array(
			'startDate' => $this->startDate->format("d-M-Y"),
			'endDate' => $this->endDate->format("d-M-Y"),
			'buyerId' => $this->getBuyer()->bybBranchCode,
			"buyerTnid" => (int)$this->buyerTnid
		);
			
		if( $_GET['terminated'] == 1 ){
			echo $sql; print_r($params); die();
		}

		$key = md5($sql) .  "Shipserv_Report_Buyxer_Gmv_SupplierInteractedWith" . print_r($params, true);
		return $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');

	}

	public function getData()
	{
		$sql = "
SELECT
  	order_data.*
  	, supplier.spb_monetization_percent
	, supplier.SPB_IS_PRIME_SUPPLIER
	, (
		SELECT
			COUNT(DISTINCT o.byb_branch_code)
		FROM
			ord_traded_gmv o
		WHERE
			o.spb_branch_code=supplier.spb_branch_code
			AND o.ORD_ORIG_SUBMITTED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
	) total_unique_buyer
FROM
	(
		SELECT
		  otg.spb_branch_code
		  , COUNT(*) total_orders
		  , COUNT(DISTINCT o.ord_imo_no) total_unique_vessel
		  , SUM(FINAL_TOTAL_COST_USD) total_gmv
		  , AVG(FINAL_TOTAL_COST_USD) average_cost
		  , SUM(FINAL_TOTAL_VBP_USD) total_revenue
		FROM
		  ord_traded_gmv otg JOIN ord o ON (otg.ord_original=o.ord_internal_ref_no)
		WHERE
		  otg.byb_branch_code IN (SELECT byb_branch_code FROM buyer_branch@livedb_link WHERE byb_under_contract=:buyerId OR byb_branch_code=:buyerId)
		  AND ORD_ORIG_SUBMITTED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
		GROUP BY
		  otg.spb_branch_code
	) order_data
	, supplier
WHERE
  	order_data.spb_branch_code=supplier.spb_branch_code
";
		$params = array(
			'startDate' => $this->startDate->format("d-M-Y"),
			'endDate' => $this->endDate->format("d-M-Y"),
			'buyerId' => $this->getBuyer()->bybBranchCode
		);

		$key = md5($sql) .  "Shipserv_Report_Buyer_Gmv_Supplier" . print_r($params, true);
		return $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');
	}
}
