<?php
/**
 *
 */
class Shipserv_Report_Dashboard_Engagement extends Shipserv_Report_Dashboard
{

	const MEMCACHE_TTL = 86400;

	public function setParams( $params = null)
	{
		$this->params = $params;
	}

	public function setBuyerTnidToExclude(Array $tnids )
	{
		$this->buyerTnidToExclude = $tnids;
	}

	public function getLeakageData()
	{
		if( count( $this->buyerTnidToExclude ) > 0 )
		{
			$sqlForBuyerTnid = " AND byb_branch_code NOT IN (" . implode(',', (array)$this->buyerTnidToExclude) . ")";
			$sqlForBuyerTnidMatch = " AND r.byb_branch_code NOT IN (" . implode(',', (array)$this->buyerTnidToExclude) . ")";
		}

		$sql = "
			SELECT
			  TO_CHAR(r.rfq_submitted_date , 'YYYY-MON') grouping_string
			  , SUM(leakage_usd_total) leakage_gmv
			  , SUM(leakage_vbp_revenue) leakage_revenue
			  , 'Leakage' txn_type
			FROM
			  linked_rfq_qot_po r
			WHERE
			  r.rfq_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
			  " . $sqlForBuyerTnid . "
			GROUP BY
			  TO_CHAR(r.rfq_submitted_date , 'YYYY-MON')
		";
		if( $_GET['terminated'] == 1 ){
			echo $sql;
			print_r($this->params);
			die();
		}

		$db = $this->getDbByType('ssreport2');
		$key = get_class($this) . 'leakage' . md5($sql) . print_r($this->params, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, self::MEMCACHE_TTL, 'ssreport2');
		return $data;


	}

	public function getTradenetData()
	{

		if( count( $this->buyerTnidToExclude ) > 0 )
		{
			$sqlForBuyerTnid = " AND byb_branch_code NOT IN (" . implode(',', (array)$this->buyerTnidToExclude) . ")";
			$sqlForBuyerTnidMatch = " AND r.byb_branch_code NOT IN (" . implode(',', (array)$this->buyerTnidToExclude) . ")";
		}

		$sql = "

		WITH base_data AS (
		  SELECT
		    TO_CHAR(r.rfq_submitted_date , 'YYYY-MON') grouping_string
		    , r.rfq_event_hash
		    , COUNT(*) total_rfq
		    , COUNT(
		        DISTINCT
		          r.qot_internal_ref_no
		    ) total_qot
		    , COUNT(
		        DISTINCT
		          r.ord_internal_ref_no
		    ) total_ord
		    , SUM (
		        CASE
		          WHEN rfq_is_declined=1 OR has_qot=1 OR has_po=1 THEN
		            1
		        END
		    ) total_rfq_actioned
		  FROM
		    linked_rfq_qot_po r
		  WHERE
					rfq_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
					" . $sqlForBuyerTnid . "
		    AND r.is_match_rfq=0
		  GROUP BY
		    TO_CHAR(r.rfq_submitted_date , 'YYYY-MON'), r.rfq_event_hash
		)
		,
		aggregated_data AS (
		  SELECT
		    grouping_string
		    , SUM( total_rfq ) total_rfq
		    , COUNT( DISTINCT rfq_event_hash ) total_rfq_event
		    , SUM( total_rfq_actioned ) total_actioned_rfq
		    , COUNT( DISTINCT CASE WHEN total_ord=1 THEN rfq_event_hash END ) total_po
		    , COUNT( DISTINCT CASE WHEN total_qot=0 AND total_ord=0 THEN rfq_event_hash END ) total_rfq_event_no_qot
		  FROM
		    base_data
		  GROUP BY
		    grouping_string
		)

		SELECT
		  tbl.*
		  , (
		    CASE
		      WHEN tbl.total_rfq > 0 AND tbl.total_actioned_rfq > 0 THEN
		        ROUND((tbl.total_actioned_rfq/tbl.total_rfq)*100)
		      ELSE
		        0
		    END
		  ) rfq_actioned_rate
		  , (
		    CASE
		      WHEN tbl.total_rfq_event > 0 AND tbl.total_rfq_event_no_qot > 0 THEN
		        ROUND((tbl.total_rfq_event_no_qot/tbl.total_rfq_event)*100)
		      ELSE
		        0
		    END
		  ) rfq_event_unactioned_rate

		  , (
		    CASE
		      WHEN tbl.total_rfq_event > 0 AND tbl.total_po > 0 THEN
		        ROUND((tbl.total_po/tbl.total_rfq_event)*100)
		      ELSE
		        0
		    END
		  ) rfq_po_rate
		  , 'Tradenet' txn_type
		FROM
		(
		  SELECT * FROM aggregated_data
		) tbl
		";


		//echo $sql;
		//print_r($params); die();

		$db = $this->getDbByType('ssreport2');
		$key = get_class($this) . 'tradenet' . md5($sql) . print_r($this->params, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, self::MEMCACHE_TTL, 'ssreport2');
		return $data;

	}

	public function getPagesData()
	{
		if( count( $this->buyerTnidToExclude ) > 0 )
		{
			$sqlForBuyerTnid = " AND byb_branch_code NOT IN (" . implode(',', (array)$this->buyerTnidToExclude) . ")";
		}

		$sql = "
WITH pages AS
(
  SELECT
    tbl.*
    , (
      CASE
        WHEN tbl.total_rfq > 0 AND tbl.total_actioned_rfq > 0 THEN
          ROUND((tbl.total_actioned_rfq/tbl.total_rfq)*100)
        ELSE
          0
      END
    ) rfq_actioned_rate
	  , (
	    CASE
	      WHEN tbl.total_rfq_event > 0 AND tbl.total_rfq_event_no_qot > 0 THEN
	        ROUND((tbl.total_rfq_event_no_qot/tbl.total_rfq_event)*100)
	      ELSE
	        0
	    END
	  ) rfq_event_unactioned_rate
    , (
      CASE
        WHEN tbl.total_rfq_event > 0 AND tbl.total_po > 0 THEN
          ROUND((tbl.total_po/tbl.total_rfq_event)*100)
        ELSE
          0
      END
    ) rfq_po_rate
  FROM
  (
    SELECT
      grouping_string
      , txn_type
      , COUNT(DISTINCT rfq_id_spb_id) total_rfq
      , COUNT(DISTINCT rfq_event_hash) total_rfq_event
      , SUM(
          CASE
            WHEN rfq_qot_count=1 OR rfq_dec_count=1 OR rfq_ord_count=1 THEN
              1
            ELSE
              0
          END
      ) total_actioned_rfq
      , COUNT( DISTINCT rfq_event_hash_with_po ) total_po
      , COUNT( DISTINCT rfq_event_hash_unactioned ) total_rfq_event_no_qot
    FROM
    (
      SELECT
        tt.*
        , (
            CASE
              WHEN tt.has_qot=0 AND tt.is_declined=0 THEN
                tt.rfq_event_hash
              ELSE
                null
            END
        ) rfq_event_hash_unactioned
      FROM
      (
	      SELECT
	        (
	          SELECT
	            TO_CHAR(r.rfq_submitted_date , 'YYYY-MON')
	          FROM
	            dual
	        ) grouping_string
	        , r.rfq_internal_ref_no || '-' || r.spb_branch_code rfq_id_spb_id
	        , (
	            CASE
	              WHEN rfq_ord_count>0 THEN
	                r.rfq_internal_ref_no
	            END
	        ) rfq_event_hash_with_po
	        , r.rfq_event_hash
	        , r.rfq_qot_count
	        , r.rfq_dec_count
	        , r.rfq_ord_count
	        , (
	        	SUM(rfq_qot_count) OVER (PARTITION BY rfq_event_hash)
	        ) has_qot
	        , (
	            SUM(rfq_dec_count) OVER (PARTITION BY rfq_event_hash)
	        ) is_declined

	        , 'Pages' txn_type
	      FROM
	        rfq r
	      WHERE
	        r.rfq_is_latest=1
	        AND r.rfq_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
	        AND r.rfq_pages_rfq_id IS NOT null
	        AND NOT EXISTS (
	          SELECT 1 FROM match_b_rfq_to_match r2m WHERE r2m.rfq_event_hash=r.rfq_event_hash
	        )
	        " . $sqlForBuyerTnid . "
      ) tt
    )
    GROUP BY
      grouping_string
      , txn_type
  ) tbl
)
SELECT * FROM pages
		";

		$db = $this->getDbByType('ssreport2');
		$key = get_class($this) . 'pages' . md5($sql) . print_r($this->params, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, self::MEMCACHE_TTL, 'ssreport2');
		return $data;
	}

	public function getMatchData()
	{
		if( count( $this->buyerTnidToExclude ) > 0 )
		{
			$sqlForBuyerTnidMatch = " AND r.byb_branch_code NOT IN (" . implode(',', (array)$this->buyerTnidToExclude) . ")";
		}

		$sql = "
WITH match AS
(
  SELECT
    tbl.grouping_string
	, tbl.txn_type
	, tbl.total_rfq
	, tbl.total_rfq_event
  	, tbl.total_rfq_event_no_qot
  	, tbl.total_actioned_rfq
	, tbl.total_po
  	, tbl.total_match_po
	, (
      CASE
        WHEN tbl.total_actioned_rfq > 0 AND tbl.total_rfq > 0 THEN
          ROUND((tbl.total_actioned_rfq/tbl.total_rfq)*100)
        ELSE
          0
      END
  	) rfq_actioned_rate
	, (
	    CASE
	      WHEN tbl.total_rfq_event > 0 AND tbl.total_rfq_event_no_qot > 0 THEN
	        ROUND((tbl.total_rfq_event_no_qot/tbl.total_rfq_event)*100)
	      ELSE
	        0
	    END
	) rfq_event_unactioned_rate

  	, (
      CASE
        WHEN tbl.total_rfq_event > 0 AND tbl.total_po > 0 THEN
          ROUND((tbl.total_po/tbl.total_rfq_event)*100)
        ELSE
          0
      END
  	) rfq_po_rate
  	, (
      CASE
        WHEN tbl.total_rfq_event > 0 AND tbl.total_match_po > 0 THEN
          ROUND((tbl.total_match_po/tbl.total_rfq_event)*100)
        ELSE
          0
      END
  	) rfq_match_po_rate

FROM
  (
    SELECT
      grouping_string
      , txn_type
      , COUNT(DISTINCT rfq_id_spb_id) total_rfq
      , COUNT(DISTINCT rfq_event_hash) total_rfq_event
      , SUM(total_frfq_response) total_actioned_rfq
      , COUNT( DISTINCT bo ) + COUNT( DISTINCT mo ) total_po
      , COUNT( DISTINCT bo ) total_b_po
      , COUNT( DISTINCT mo ) total_match_po

      , COUNT( DISTINCT rfq_event_hash_unactioned ) total_rfq_event_no_qot
    FROM
    (
      SELECT
        qq.*
        , (
            CASE
              WHEN qq.has_qot=0 AND qq.is_declined=0 THEN
                qq.rfq_event_hash
              ELSE
                null
            END
        ) rfq_event_hash_unactioned

      FROM
      (
        SELECT
          tt.*
          , (
              SUM(tt.total_frfq_response) OVER (PARTITION BY tt.rfq_event_hash)
          ) has_qot
          , (
              SUM(tt.total_frfq_response) OVER (PARTITION BY tt.rfq_event_hash)
          ) is_declined
        FROM
        (
	      SELECT
			(
	          SELECT
	            TO_CHAR(r.rfq_submitted_date , 'YYYY-MON')
	          FROM
	            dual
	        ) grouping_string
	        , r.rfq_internal_ref_no || '-' || f.spb_branch_code rfq_id_spb_id
	        , (
	            CASE
	              WHEN has_order=1 THEN
	                r.rfq_internal_ref_no
	            END
	        ) rfq_event_hash_with_po
	        , (
	          (
	        	SELECT
	        		COUNT( DISTINCT qot_internal_ref_no )
	        	FROM
	        		match_b_qot_match
	        	WHERE
	        		rfq_sent_to_match=r.rfq_internal_ref_no
	            AND spb_branch_code=f.spb_branch_code
	          )
	          +
	          (
	            SELECT
	        		COUNT( DISTINCT rfq_internal_Ref_no)
	        	FROM
	        		rfq_resp
	        	WHERE
	        		rfq_internal_Ref_no=f.rfq_internal_ref_no
	            	AND spb_branch_code=f.spb_branch_code
	        		AND RFQ_RESP_STS='DEC'
	          )
	        ) total_frfq_response
	        , r.rfq_event_hash
	        , r.has_quote
	        , r.has_order
	          , bo.ord_internal_ref_no bo
	          , mo.ord_internal_ref_no mo

	        , 'Match' txn_type
	      FROM
	        match_b_rfq_to_match r JOIN match_b_rfq_forwarded_by_match f
	          ON (f.rfq_sent_to_match = r.rfq_internal_ref_no)
		          LEFT OUTER JOIN match_b_ord_from_byb_s_spb bo ON (r.rfq_internal_ref_no=bo.rfq_sent_to_match)
		          LEFT OUTER JOIN match_b_order_by_match_spb mo ON (r.rfq_internal_ref_no=mo.rfq_sent_to_match)

	      WHERE
	        r.rfq_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
	        " . $sqlForBuyerTnidMatch . "
        ) tt
      ) qq
    )
    GROUP BY
      grouping_string
      , txn_type
  ) tbl
)
SELECT * FROM match
		";
		$db = $this->getDbByType('ssreport2');
		$key = get_class($this) . 'match' . md5($sql) . print_r($this->params, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, self::MEMCACHE_TTL, 'ssreport2');
		return $data;

	}

	public function getData( $rolledUp = false )
	{
		$data = array();
		$tradenet = array();
		$pages = array();
		$match = array();

		$tradenet = $this->getTradenetData();
		$pages = $this->getPagesData();
		$match = $this->getMatchData();
		$leakage = $this->getLeakageData();

		//echo '<pre>';
		//print_r($match);
		//echo '</pre>';
		//print_r($match); die();
		$data = array_merge($tradenet, $pages, $match, $leakage);

		// transforming columns fetched from the db for viewing

		foreach($data as $row)
		{

			$overall['Total Rfq'][$row['GROUPING_STRING']] += $row['TOTAL_RFQ'];
			$overall['Total Actioned Rfq'][$row['GROUPING_STRING']] += $row['TOTAL_ACTIONED_RFQ'];
			$overall['Total Rfq Event'][$row['GROUPING_STRING']] += $row['TOTAL_RFQ_EVENT'];
			$overall['Total PO In RFQ Event'][$row['GROUPING_STRING']] += $row['TOTAL_PO'];
			$overall['Total RFQ event NO Qot'][$row['GROUPING_STRING']] += $row['TOTAL_RFQ_EVENT_NO_QOT'];
			

			$totalRfqEvent = 0;
			$totalRfqEventActioned = 0;

			if( $row['TXN_TYPE'] == 'Leakage' )
			{
				$new['GMV Leakage'][$row['GROUPING_STRING']] = $row['LEAKAGE_GMV'];
				$new['Revenue Leakage'][$row['GROUPING_STRING']] = $row['LEAKAGE_REVENUE'];
			}

			if( $row['TXN_TYPE'] == 'Tradenet' )
			{
				$new['Tradenet RFQ'][$row['GROUPING_STRING']] = $row['TOTAL_RFQ'];
				$new['Tradenet RFQ events'][$row['GROUPING_STRING']] = $row['TOTAL_RFQ_EVENT'];
				$new['Actioned Tradenet RFQ'][$row['GROUPING_STRING']] = $row['TOTAL_ACTIONED_RFQ'];
				$new['Tradenet RFQ events resulted to PO'][$row['GROUPING_STRING']] = $row['TOTAL_PO'];
				$new['%TradeNet RFQs Actioned'][$row['GROUPING_STRING']] = $row['RFQ_ACTIONED_RATE'];
				$new['%TradeNet RFQ Events resulting in a PO'][$row['GROUPING_STRING']] = $row['RFQ_PO_RATE'];
				$new['%Tradenet RFQ Events with No Quotes'][$row['GROUPING_STRING']] = $row['RFQ_EVENT_UNACTIONED_RATE'];

				$totalRfqEvent += $row['TOTAL_RFQ_EVENT'];
				$totalRfqEventActioned += $row['TOTAL_ACTIONED_RFQ'];
			}

			if( $row['TXN_TYPE'] == 'Pages' )
			{
				$new['Pages RFQ'][$row['GROUPING_STRING']] = $row['TOTAL_RFQ'];
				$new['Pages RFQ events'][$row['GROUPING_STRING']] = $row['TOTAL_RFQ_EVENT'];
				$new['Actioned Pages RFQ'][$row['GROUPING_STRING']] = $row['TOTAL_ACTIONED_RFQ'];
				$new['Pages RFQ events resulted to PO'][$row['GROUPING_STRING']] = $row['TOTAL_PO'];
				$new['%Pages RFQs Actioned'][$row['GROUPING_STRING']] = $row['RFQ_ACTIONED_RATE'];
				$new['%Pages RFQ Events resulting in a PO'][$row['GROUPING_STRING']] = $row['RFQ_PO_RATE'];
				$new['%Pages RFQ Events with No Quotes'][$row['GROUPING_STRING']] = $row['RFQ_EVENT_UNACTIONED_RATE'];

				$totalRfqEvent += $row['TOTAL_RFQ_EVENT'];
				$totalRfqEventActioned += $row['TOTAL_ACTIONED_RFQ'];
			}

			if( $row['TXN_TYPE'] == 'Match' )
			{
				$new['Match RFQ'][$row['GROUPING_STRING']] = $row['TOTAL_RFQ'];
				$new['Match RFQ events'][$row['GROUPING_STRING']] = $row['TOTAL_RFQ_EVENT'];
				$new['Actioned Match RFQ'][$row['GROUPING_STRING']] = $row['TOTAL_ACTIONED_RFQ'];
				$new['Match RFQ events resulted to PO'][$row['GROUPING_STRING']] = $row['TOTAL_PO'];
				$new['Match RFQ events resulted to a match PO'][$row['GROUPING_STRING']] = $row['TOTAL_MATCH_PO'];
				$new['%Match RFQs Actioned'][$row['GROUPING_STRING']] = $row['RFQ_ACTIONED_RATE'];

				$totalRfqEvent += $row['TOTAL_RFQ_EVENT'];
				$totalRfqEventActioned += $row['TOTAL_ACTIONED_RFQ'];

				// fix
				$new['%Match RFQ Events resulting in a PO'][$row['GROUPING_STRING']] = $row['RFQ_PO_RATE'];
				$new['%Match RFQ events resulting in a match PO'][$row['GROUPING_STRING']] = $row['RFQ_MATCH_PO_RATE'];

				$new['%Match RFQ Events with No Quotes'][$row['GROUPING_STRING']] = $row['RFQ_EVENT_UNACTIONED_RATE'];
			}

			$new['RFQ Action Rates'][$row['GROUPING_STRING']] = null;
			$new['%RFQ Events Resulting in a PO'][$row['GROUPING_STRING']] = null;
			$new['%RFQ Events with No Quotes'][$row['GROUPING_STRING']] = null;
			$new['RFQ Counts'][$row['GROUPING_STRING']] = null;
			$new['RFQ Event Counts'][$row['GROUPING_STRING']] = null;
			$new['Actioned RFQ Counts'][$row['GROUPING_STRING']] = null;
			$new['Leakage'][$row['GROUPING_STRING']] = null;

			//overall
			$new['%Overall RFQs Actioned'][$row['GROUPING_STRING']] = @($overall['Total Actioned Rfq'][$row['GROUPING_STRING']]/$overall['Total Rfq'][$row['GROUPING_STRING']]*100);
			$new['%Overall RFQ Events Resulting in a PO'][$row['GROUPING_STRING']] = @($overall['Total PO In RFQ Event'][$row['GROUPING_STRING']]/$overall['Total Rfq Event'][$row['GROUPING_STRING']]*100);
			$new['%Overall RFQ Events with no Quotes'][$row['GROUPING_STRING']] = ($overall['Total Rfq Event'][$row['GROUPING_STRING']] != 0) ? $overall['Total RFQ event NO Qot'][$row['GROUPING_STRING']] / $overall['Total Rfq Event'][$row['GROUPING_STRING']] * 100 : 0;
		}
		// end of loop

		$x = array();
		$x['RFQ Action Rates'] = $new['RFQ Action Rates'];
		$x['%TradeNet RFQs Actioned'] = $new['%TradeNet RFQs Actioned'];
		$x['%Match RFQs Actioned'] = $new['%Match RFQs Actioned'];
		$x['%Pages RFQs Actioned'] = $new['%Pages RFQs Actioned'];
		$x['%Overall RFQs Actioned'] = $new['%Overall RFQs Actioned'];

		$x['%RFQ Events Resulting in a PO'] = $new['%RFQ Events Resulting in a PO'];
		$x['%TradeNet RFQ Events resulting in a PO'] = $new['%TradeNet RFQ Events resulting in a PO'];
		$x['%Match RFQ Events resulting in a PO'] = $new['%Match RFQ Events resulting in a PO'];
		$x['%Match RFQ events resulting in a match PO'] = $new['%Match RFQ events resulting in a match PO'];
		$x['%Pages RFQ Events resulting in a PO'] = $new['%Pages RFQ Events resulting in a PO'];
		$x['%Overall RFQ Events Resulting in a PO'] = $new['%Overall RFQ Events Resulting in a PO'];

		$x['%RFQ Events with No Quotes'] = $new['%RFQ Events with No Quotes'];
		$x['%Tradenet RFQ Events with No Quotes'] = $new['%Tradenet RFQ Events with No Quotes'];
		$x['%Match RFQ Events with No Quotes'] = $new['%Match RFQ Events with No Quotes'];
		$x['%Pages RFQ Events with No Quotes'] = $new['%Pages RFQ Events with No Quotes'];
		$x['%Overall RFQ Events with no Quotes'] = $new['%Overall RFQ Events with no Quotes'];

		$x['RFQ Counts'] = $new['RFQ Counts'];
		$x['Tradenet RFQ'] = $new['Tradenet RFQ'];
		$x['Match RFQ'] = $new['Match RFQ'];
		$x['Pages RFQ'] = $new['Pages RFQ'];

		$x['RFQ Event Counts'] = $new['RFQ Event Counts'];
		$x['Tradenet RFQ events'] = $new['Tradenet RFQ events'];
		$x['Match RFQ events'] = $new['Match RFQ events'];
		$x['Pages RFQ events'] = $new['Pages RFQ events'];


		$x['Actioned RFQ Counts'] = $new['Actioned RFQ Counts'];
		$x['Actioned Tradenet RFQ'] = $new['Actioned Tradenet RFQ'];
		$x['Actioned Match RFQ'] = $new['Actioned Match RFQ'];
		$x['Actioned Pages RFQ'] = $new['Actioned Pages RFQ'];


		$x['RFQ Events resulting in a PO'] = $new['RFQ Events resulting in a PO'];
		$x['Tradenet RFQ events resulted to PO'] = $new['Tradenet RFQ events resulted to PO'];
		$x['Match RFQ events resulted to PO'] = $new['Match RFQ events resulted to PO'];
		$x['Match RFQ events resulted to a match PO'] = $new['Match RFQ events resulted to a match PO'];
		//$x['Match RFQ events resulted to a PO'] = $new['Match RFQ events resulted to a PO'];

		$x['Pages RFQ events resulted to PO'] = $new['Pages RFQ events resulted to PO'];

		$x['Leakage'] = $new['Leakage'];
		$x['GMV Leakage'] = $new['GMV Leakage'];
		$x['Revenue Leakage'] = $new['Revenue Leakage'];



		return $x;

	}

}
