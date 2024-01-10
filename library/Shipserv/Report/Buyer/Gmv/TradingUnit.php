<?php
class Shipserv_Report_Buyer_Gmv_TradingUnit extends Shipserv_Report
{
	public $periodForTrends;

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
				$this->isParent = ($_GET['parent']==1);
			}
		}
	}

	public function getData()
	{
		
		/* Fething the list of buyers, recursively, I had to fetch the buyers separately, and create a list of buyer ID's
		 * otherwise implementing it into the query was very slow, oracle couln not optimize properly
		 */ 
		
		$sql = "
			SELECT
		    	byb_branch_code
		  	FROM
		    	buyer b\n";
		if ($this->isParent == true) {
			$sql .= "START WITH b.byb_branch_code = ".$this->buyerTnid." CONNECT BY NOCYCLE PRIOR b.byb_branch_code = b.parent_branch_code";
		} else {
			$sql .= "WHERE b.byb_branch_code = ".$this->buyerTnid;
		}
			
		
		$db = $this->getDbByType('ssreport2');
		$key = md5($sql) .  "Shipserv_Report_Buyer_Gmv_TxradingUnit" . $sql;
		$branches = array();
		$brancRec = $this->fetchCachedQuery ($sql, array(), $key, (60*60*2), 'ssreport2');
		foreach ($brancRec as $record) {
			array_push($branches, $record['BYB_BRANCH_CODE']);
		}
		$fullBranchList = implode(", ", $branches);
		
		//We have to dispable calculating Leakage Data, and other aggregated data, 'cos of performance issues, requested by Stuart
		//This trick saves to refactor the whole query
		$branchList = ($this->isParent == true) ? '-1' : $fullBranchList;
		
		$sql = "
		WITH
		all_orders AS (
		  SELECT
        	ooo.ord_internal_ref_no ord_internal_ref_no
		  FROM
		    ord_traded_gmv otg
				JOIN ord ooo ON (otg.ord_internal_ref_no=ooo.ord_internal_ref_no)
					JOIN buyer b ON (otg.byb_branch_code=b.byb_branch_code)
		  WHERE
		  	b.byb_branch_code IN (".$fullBranchList.")
			AND ord_orig_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY') AND to_date(:endDate, 'DD-MON-YYYY') + 0.99999
		)
		,
		direct_ord_w_vessel_base AS (
		  SELECT
		  	DISTINCT
			DECODE( vslh1.vslh_id_grouped_to,
				0, decode( vslh1.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh1.vslh_imo, '-', 'INVALID IMO', vslh1.vslh_imo )) || '~' || decode( vslh1.vslh_ihs_name, '-', decode( vslh1.vslh_name, '-', 'NO VESSEL NAME', vslh1.vslh_name ), vslh1.vslh_ihs_name ),
				nvl(( select decode( vslh2.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh2.vslh_imo, '-', 'INVALID IMO', vslh2.vslh_imo )) || '~' || decode( vslh2.vslh_ihs_name, '-', decode( vslh2.vslh_name, '-', 'NO VESSEL NAME', vslh2.vslh_name ), vslh2.vslh_ihs_name )
					from vessel_history vslh2
					 where vslh2.vslh_id = vslh1.vslh_id_grouped_to
					 and rownum = 1 ),
				  'INVALID IMO~NO VESSEL NAME'
				   )
				) as imo_no_vessel_name
			, null rfq_event
			, otg.byb_branch_code
			, otg.spb_branch_code
		    , 1 has_po
			, ooo.ord_internal_ref_no ord_internal_ref_no
		    , otg.FINAL_TOTAL_COST_USD po_price
		    , 0 leakage_usd_total
		    , 0 leakage_vbp_revenue
		  FROM
			all_orders ao JOIN ord_traded_gmv otg ON (ao.ord_internal_ref_no=otg.ord_internal_ref_no)
			JOIN ord ooo ON (otg.ord_internal_ref_no=ooo.ord_internal_ref_no)
			JOIN vessel_history vslh1 ON (
				vslh1.byb_branch_code = ooo.byb_branch_code
				AND vslh1.vslh_is_valid_imo = validate_imo_no( upper( trim( ooo.ord_imo_no )))
				AND vslh1.vslh_imo = nvl( ooo.ord_imo_no, '-' )
				AND vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( ooo.ord_vessel_name ))), '-' )
			)
		  WHERE
			-- not exist in competitive table
			NOT EXISTS (
				SELECT null FROM linked_rfq_qot_po lll WHERE otg.ord_internal_ref_no=lll.ord_internal_ref_no
			)
		)
		,
		direct_ord_w_vessel AS (
			SELECT
				utl_raw.cast_to_raw(
					ooo.byb_branch_code
					|| substr( imo_no_vessel_name, 1, instr( imo_no_vessel_name, '~' ) - 1 )
					|| substr( imo_no_vessel_name, instr( imo_no_vessel_name, '~' ) + 1 )
					|| ooo.ord_internal_ref_no
					|| ooo.spb_branch_code
				) rfq_event_hash
				, substr( imo_no_vessel_name, 1, instr( imo_no_vessel_name, '~' ) - 1 ) as imo_no
           		, substr( imo_no_vessel_name, instr( imo_no_vessel_name, '~' ) + 1 ) as vessel_name
				, ooo.*
				, null rfq_has_order
			FROM
				direct_ord_w_vessel_base ooo
		)
		,
		-- all gmv is coming from this table
		competitive_ord_w_vessel_base AS
		(
		  SELECT
		  	DISTINCT
			DECODE( vslh1.vslh_id_grouped_to,
				0, decode( vslh1.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh1.vslh_imo, '-', 'INVALID IMO', vslh1.vslh_imo )) || '~' || decode( vslh1.vslh_ihs_name, '-', decode( vslh1.vslh_name, '-', 'NO VESSEL NAME', vslh1.vslh_name ), vslh1.vslh_ihs_name ),
				nvl(( select decode( vslh2.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh2.vslh_imo, '-', 'INVALID IMO', vslh2.vslh_imo )) || '~' || decode( vslh2.vslh_ihs_name, '-', decode( vslh2.vslh_name, '-', 'NO VESSEL NAME', vslh2.vslh_name ), vslh2.vslh_ihs_name )
					from vessel_history vslh2
					 where vslh2.vslh_id = vslh1.vslh_id_grouped_to
					 and rownum = 1 ),
				  'INVALID IMO~NO VESSEL NAME'
				   )
				) as imo_no_vessel_name
			, lrqp.rfq_event_hash rfq_event
			, byb_branch_code
			, spb_branch_code
			, has_po
			, ord_internal_ref_no
			, po_price
			, leakage_usd_total
			, leakage_vbp_revenue
		  FROM
			all_orders ao JOIN linked_rfq_qot_po lrqp ON (ao.ord_internal_ref_no=lrqp.ord_internal_ref_no)
			JOIN ord ooo ON (lrqp.ord_internal_ref_no=ooo.ord_internal_ref_no)
			JOIN vessel_history vslh1 ON (
				vslh1.byb_branch_code = ooo.byb_branch_code
				AND vslh1.vslh_is_valid_imo = validate_imo_no( upper( trim( ooo.ord_imo_no )))
				AND vslh1.vslh_imo = nvl( ooo.ord_imo_no, '-' )
				AND vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( ooo.ord_vessel_name ))), '-' )
			)
		  WHERE
			NOT EXISTS(
				SELECT
					NULL
				FROM
					direct_ord_w_vessel dd
				WHERE
					dd.ord_internal_ref_no=lrqp.ord_internal_ref_no
			)
		)
		,
		competitive_ord_w_vessel AS (
			SELECT
				utl_raw.cast_to_raw(
					ooo.rfq_event
					|| substr( imo_no_vessel_name, 1, instr( imo_no_vessel_name, '~' ) - 1 )
					|| substr( imo_no_vessel_name, instr( imo_no_vessel_name, '~' ) + 1 )
				) rfq_event_hash
				, substr( imo_no_vessel_name, 1, instr( imo_no_vessel_name, '~' ) - 1 ) as imo_no
				, substr( imo_no_vessel_name, instr( imo_no_vessel_name, '~' ) + 1 ) as vessel_name
				, ooo.*
				, (
			        SELECT
			          CASE WHEN count(rfq_event_hash) > 0 THEN ooo.rfq_event END  po_by_rfq_envent
			        FROM
			          linked_rfq_qot_po
			        where
			          rfq_event_hash = ooo.rfq_event 
			          and byb_branch_code IN (".$branchList.")
			          and has_po = 1
			    ) rfq_has_order
			FROM
				competitive_ord_w_vessel_base ooo

		)
		,
	    leakage_data_base AS (
	      	SELECT
				DISTINCT
				DECODE( vslh1.vslh_id_grouped_to,
	              0, decode( vslh1.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh1.vslh_imo, '-', 'INVALID IMO', vslh1.vslh_imo )) || '~' || decode( vslh1.vslh_ihs_name, '-', decode( vslh1.vslh_name, '-', 'NO VESSEL NAME', vslh1.vslh_name ), vslh1.vslh_ihs_name ),
	              nvl(( select decode( vslh2.vslh_is_valid_imo, 0, 'INVALID IMO', decode( vslh2.vslh_imo, '-', 'INVALID IMO', vslh2.vslh_imo )) || '~' || decode( vslh2.vslh_ihs_name, '-', decode( vslh2.vslh_name, '-', 'NO VESSEL NAME', vslh2.vslh_name ), vslh2.vslh_ihs_name )
	                from vessel_history vslh2
	                 where vslh2.vslh_id = vslh1.vslh_id_grouped_to
	                 and rownum = 1 ),
	                'INVALID IMO~NO VESSEL NAME'
	                 )
	              ) as imo_no_vessel_name

				, lrqp.rfq_event_hash rfq_event
				, lrqp.byb_branch_code
		        , lrqp.spb_branch_code
		        , has_po
		        , lrqp.ord_internal_ref_no
		        , po_price
		        , leakage_usd_total
		        , leakage_vbp_revenue
			FROM
				linked_rfq_qot_po lrqp
				JOIN rfq ooo ON (lrqp.rfq_internal_ref_no=ooo.rfq_internal_ref_no)
				JOIN vessel_history vslh1 ON (
					vslh1.byb_branch_code = ooo.byb_branch_code
					AND vslh1.vslh_is_valid_imo = validate_imo_no( upper( trim( ooo.rfq_imo_no )))
					AND vslh1.vslh_imo = nvl( ooo.rfq_imo_no, '-' )
					AND vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( ooo.rfq_vessel_name ))), '-' )
				)

			WHERE
				lrqp.byb_branch_code IN (".$branchList.")
		        AND lrqp.rfq_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY') AND to_date(:endDate, 'DD-MON-YYYY') + 0.99999
		        AND lrqp.ord_internal_ref_no IS NULL
		)
		,
		leakage_data AS (
			SELECT
				utl_raw.cast_to_raw(
					ooo.rfq_event
					|| substr( imo_no_vessel_name, 1, instr( imo_no_vessel_name, '~' ) - 1 )
					|| substr( imo_no_vessel_name, instr( imo_no_vessel_name, '~' ) + 1 )
				) rfq_event_hash
				, substr( imo_no_vessel_name, 1, instr( imo_no_vessel_name, '~' ) - 1 ) as imo_no
				, substr( imo_no_vessel_name, instr( imo_no_vessel_name, '~' ) + 1 ) as vessel_name
				, ooo.*
				, (
			        SELECT
			          CASE WHEN count(rfq_event_hash) > 0 THEN ooo.rfq_event END  po_by_rfq_envent
			        FROM
			          linked_rfq_qot_po
			        where
			          rfq_event_hash = ooo.rfq_event 
			          and byb_branch_code IN (".$branchList.")
			          and has_po = 1
			    ) rfq_has_order
			FROM
				leakage_data_base ooo
			WHERE
	          EXISTS(
	            SELECT null FROM competitive_ord_w_vessel x WHERE ooo.imo_no_vessel_name=x.imo_no_vessel_name
	            UNION
	            SELECT null FROM direct_ord_w_vessel x WHERE ooo.imo_no_vessel_name=x.imo_no_vessel_name
	          )

		)
		,
		base_data AS (
		  SELECT * FROM competitive_ord_w_vessel
		  UNION ALL
		  SELECT * FROM direct_ord_w_vessel
		  UNION ALL
		  SELECT * FROM leakage_data
		)
		,
		vessel AS (
		  SELECT
		    DISTINCT
		      vessel_name
		      , imo_no
		  FROM base_data
		)
		,
		vessel_data AS (
		  SELECT
		    lrqp.vessel_name
			, lrqp.imo_no
		    , (
		        COUNT(DISTINCT lrqp.rfq_event_hash)
		    ) unique_event_hash
		    , (
		        COUNT(DISTINCT CASE WHEN has_po = 1 THEN lrqp.rfq_event_hash END)
		    ) total_po
		    , (
		        COUNT(
		          DISTINCT
		          CASE WHEN has_po=0 THEN lrqp.rfq_event_hash END

		        )
		    ) total_rfq_event_no_po
		    , (
		        SUM(po_price)
		    ) total_ord_value_usd
		    , (
		        SUM(leakage_usd_total)
		    ) leakage_gmv
		    , (
		        SUM(leakage_vbp_revenue)
		    ) leakage_revenue
	        , (
	          COUNT( CASE WHEN rfq_event  IS NULL AND has_po=1 THEN 1 END)
	        ) total_direct_orders
	        , (
	          COUNT(DISTINCT rfq_has_order ) 
	        ) po_by_rfq_event
           	, (
	            SELECT COUNT(distinct ord_internal_ref_no) FROM ord ooo
              		JOIN vessel_history vslh1 ON (
						vslh1.byb_branch_code = ooo.byb_branch_code
						AND vslh1.vslh_is_valid_imo = validate_imo_no( upper( trim( ooo.ord_imo_no )))
						AND vslh1.vslh_imo = nvl( ooo.ord_imo_no, '-' )
						AND vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( ooo.ord_vessel_name ))), '-' )
					)
              WHERE
	            ooo.byb_branch_code IN (".$branchList.")
              	AND 
              	(
              		vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( lrqp.vessel_name ))), '-' )
              		OR vslh1.vslh_ihs_name = nvl( upper( trim( clean_vessel_name( lrqp.vessel_name ))), '-' )
              	)
	            AND ooo.ord_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY') AND to_date(:endDate, 'DD-MON-YYYY') + 0.99999
		    ) total_po_in_period 
			, (
	            SELECT COUNT(distinct ord_internal_ref_no) FROM ord ooo
					JOIN vessel_history vslh1 ON (
						vslh1.byb_branch_code = ooo.byb_branch_code
						AND vslh1.vslh_is_valid_imo = validate_imo_no( upper( trim( ooo.ord_imo_no )))
						AND vslh1.vslh_imo = nvl( ooo.ord_imo_no, '-' )
						AND vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( ooo.ord_vessel_name ))), '-' )
					)
              WHERE
	            ooo.byb_branch_code IN (".$branchList.")
              	AND 
              	(
              		vslh1.vslh_name = nvl( upper( trim( clean_vessel_name( lrqp.vessel_name ))), '-' )
              		OR vslh1.vslh_ihs_name = nvl( upper( trim( clean_vessel_name( lrqp.vessel_name ))), '-' )
              	)
	            AND ooo.ord_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY') AND to_date(:endDate, 'DD-MON-YYYY') + 0.99999
              AND
              NOT EXISTS (
                SELECT null FROM linked_rfq_qot_po lll WHERE ooo.ord_internal_ref_no=lll.ord_internal_ref_no
              )
		    ) total_direct_po_in_period  
		  FROM
		  vessel p JOIN base_data lrqp
			  ON (lrqp.vessel_name=p.vessel_name AND lrqp.imo_no=p.imo_no)
		  GROUP BY
		    lrqp.vessel_name, lrqp.imo_no
		)
		,
		transaction_by_vessel_spb AS (
		  SELECT
		    lrqp.vessel_name
			, lrqp.imo_no
		    , lrqp.rfq_event_hash
		    , COUNT(DISTINCT lrqp.spb_branch_code) total_supplier
		  FROM
		    base_data lrqp
		  WHERE
		    EXISTS(
		      SELECT null FROM vessel p WHERE p.vessel_name=lrqp.vessel_name
		    )
		  GROUP BY
		    lrqp.vessel_name, lrqp.imo_no, lrqp.rfq_event_hash
		)
		,
		transaction_by_ves_spb_avg AS (
		  SELECT
		    lrqp.vessel_name
		    , avg(total_supplier) avg_spb
		  FROM
		    transaction_by_vessel_spb lrqp
		  WHERE
		    EXISTS(
		      SELECT null FROM vessel p WHERE p.vessel_name=lrqp.vessel_name
		    )
		  GROUP BY
		    lrqp.vessel_name
		)
		,
		vessel_stat AS (
		  SELECT
		    pd.*
		    , av.avg_spb
		  FROM
		  vessel p LEFT OUTER JOIN vessel_data pd ON (p.vessel_name=pd.vessel_name AND p.imo_no=pd.imo_no)
		   LEFT OUTER JOIN transaction_by_ves_spb_avg av ON (p.vessel_name=av.vessel_name AND p.imo_no=pd.imo_no)
		)

		SELECT
			DISTINCT *
		FROM
			vessel_stat
		ORDER BY
			total_ord_value_usd DESC NULLS LAST

";

		$params = array('startDate' => $this->startDate->format("d-M-Y"), 'endDate' => $this->endDate->format("d-M-Y"));

		if( $_GET['terminated'] == 1 ){
			echo $sql; print_r($params); die();
		}

		//$db = $this->getDbByType('ssreport2');
		$key = md5($sql) .  "Shipserv_Report_Buyer_Gmv_TxradingUnit" . print_r($params, true) . print_r($_GET, true);

		return $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');
	}

	public function getBuyer()
	{
		return $this->buyer;
	}
}
