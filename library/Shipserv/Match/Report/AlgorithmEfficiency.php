<?php
/**
 *
 */
class Shipserv_Match_Report_AlgorithmEfficiency extends Shipserv_Report_Dashboard
{
	public function setParams( $params = null)
	{
		$this->params = $params;
	}

	public function setBuyerTnidToExclude(Array $tnids )
	{
		$this->buyerTnidToExclude = $tnids;
	}

	public function getMatchReleaseData()
	{
		$sql = "

			WITH base_data AS (
				SELECT
				  mrs_match_version || mrs_settings_hash
				  , MIN(mrs_date) date_released
				FROM
				  match_rfq_search
				WHERE
				  mrs_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
				GROUP BY
				  mrs_match_version || mrs_settings_hash
				ORDER BY
				  MIN(mrs_date) ASC
			)
			SELECT
			    TO_CHAR(date_released, 'YYYY-MON') grouping_string
			  , TO_CHAR(date_released, 'YYYYMM') sorting_string
			  , COUNT(*) total_release
			FROM
			  base_data
			GROUP BY
			    TO_CHAR(date_released, 'YYYY-MON')
			  , TO_CHAR(date_released, 'YYYYMM')
			ORDER BY
			  TO_CHAR(date_released, 'YYYYMM') ASC
		";


		$db = $this->getDb();
		$key = get_class($this) . 'match-rehlease' . md5($sql) . print_r($this->params, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2));
		return $data;

	}

	public function getEfficiencyData()
	{
		if( lg_count( $this->buyerTnidToExclude ) > 0 )
		{
			$sqlForBuyerTnid = " AND byb_branch_code NOT IN (" . implode($this->buyerTnidToExclude, ',') . ")";
			$sqlForBuyerTnidMatch = " AND r.byb_branch_code NOT IN (" . implode($this->buyerTnidToExclude, ',') . ")";
		}

// average quote response time
		$sql = "
        WITH base_data AS (
          SELECT
              rfm.rfq_sent_to_match
            , rfm.rfq_internal_ref_no rfq_fwd_id
            , r2m.rfq_submitted_date
            , r.rfq_event_hash
            , qm.qot_internal_ref_no || qm.spb_branch_code quoted_rfq_hash
            , mo.ord_internal_ref_no || mo.spb_branch_code ordered_rfq_hash
            , r2m.potential_saving
            , r2m.realised_saving
            , rfm.rfq_submitted_date - r.rfq_submitted_date processing_time
			      , r2m.cheapest_qot_spb_by_byb_100
			      , r2m.cheapest_qot_spb_by_match_100
            , (CASE WHEN rfm.rfq_sent_to_match is not null AND qm.qot_internal_ref_no is null THEN 1 ELSE 0 end) rfq_evet_not_quoted
          FROM
            -- ANCHOR IS WHEN RFQ SENT TO MATCH
            -- GETTING RFQ_EVENT_HASH INFORMATION
            match_b_rfq_to_match r2m JOIN rfq r
            ON (r.rfq_internal_ref_no=r2m.rfq_internal_ref_no)

              LEFT OUTER JOIN match_b_rfq_forwarded_by_match rfm
              ON (r2m.rfq_internal_ref_no=rfm.rfq_sent_to_match)


                -- QOT RECEIVED BY MATCH
                LEFT OUTER JOIN match_b_qot_match qm
                ON ( rfm.rfq_internal_ref_no=qm.rfq_internal_ref_no )

                  -- GETTING ORDER TO MATCH SELECTED SUPPLIER
                  LEFT OUTER JOIN match_b_order_by_match_spb mo
                  ON (mo.rfq_sent_to_match=r2m.rfq_internal_ref_no)

          WHERE
            r2m.rfq_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
        )
        ,
        group_data AS (
          SELECT
              rfq_event_hash
            , rfq_submitted_date
            , COUNT(DISTINCT rfq_event_hash) total_sent_to_match
            , COUNT(DISTINCT CASE WHEN rfq_fwd_id IS NOT null THEN rfq_fwd_id END) total_forwarded
            , COUNT(DISTINCT CASE WHEN quoted_rfq_hash IS NOT null THEN quoted_rfq_hash END) total_quoted
            , COUNT(DISTINCT CASE WHEN ordered_rfq_hash IS NOT null THEN rfq_event_hash END) total_ordered_match_spb
			      , COUNT(DISTINCT CASE WHEN cheapest_qot_spb_by_byb_100>0 AND cheapest_qot_spb_by_match_100>0 THEN rfq_event_hash END ) total_byb_sel_cheapest
            , MAX(potential_saving) potential_saving
            , MAX(realised_saving) realised_saving
            , AVG(processing_time) processing_time
            , COUNT(DISTINCT CASE WHEN rfq_evet_not_quoted = 1 THEN rfq_event_hash END ) rfq_evet_not_quoted
          FROM
            base_data
          GROUP BY
            rfq_event_hash
            , rfq_submitted_date
        ), final_data AS
        (
        SELECT
            TO_CHAR(g.rfq_submitted_date, 'YYYY-MON') grouping_string
          , TO_CHAR(g.rfq_submitted_date, 'YYYYMM') sorting_string
          , SUM(g.total_sent_to_match) total_sent_to_match
          , SUM(g.total_forwarded) total_forwarded
          , SUM(g.total_quoted) total_quoted
		      , SUM(g.total_quoted)/SUM(g.total_forwarded)*100 quote_rate
          , SUM(g.total_ordered_match_spb) total_ordered_match_spb
          , SUM(g.potential_saving) potential_saving
          , SUM(g.realised_saving) realised_saving
		      , SUM(CASE WHEN potential_saving > 0 THEN 1 END) total_event_cheaper
		      , SUM(g.total_byb_sel_cheapest) total_b_sel_cheaper
          , AVG(processing_time) avg_processing_time
          , SUM(rfq_evet_not_quoted) rfq_evet_not_quoted
        FROM
          group_data g
        GROUP BY
          TO_CHAR(g.rfq_submitted_date, 'YYYY-MON')
          , TO_CHAR(g.rfq_submitted_date, 'YYYYMM')
        ORDER BY
          TO_CHAR(g.rfq_submitted_date, 'YYYYMM') ASC
       )
       SELECT 
          fd.*
        , (potential_saving / total_sent_to_match) avg_potential_saving
        , (realised_saving / total_sent_to_match) avg_realised_saving
       FROM
        final_data fd
		";

		$db = $this->getDbByType('ssreport2');
		$key = get_class($this) . 'efficiency' . md5($sql) . print_r($this->params, true);
    //echo "<pre>".$sql."</pre>"; die();
		$data = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');
		return $data;


	}

	public function getData( $rolledUp = false )
	{

		// transforming columns fetched from the db for viewing
		foreach($this->getEfficiencyData() as $row)
		{
      $new['Total RFQ events processed'][$row['GROUPING_STRING']] = $row['TOTAL_SENT_TO_MATCH'];
      $new['RFQs Forwarded by Match'][$row['GROUPING_STRING']] = $row['TOTAL_FORWARDED'];
      $new['Match RFQ Quoted'][$row['GROUPING_STRING']] = $row['TOTAL_QUOTED'];
      $new['% Quote Rate'][$row['GROUPING_STRING']] = $row['QUOTE_RATE'];
      $new['Order from Match RFQs'][$row['GROUPING_STRING']] = $row['TOTAL_ORDERED_MATCH_SPB'];
      $new['Potential Savings'][$row['GROUPING_STRING']] = $row['POTENTIAL_SAVING'];
      $new['Realised Savings'][$row['GROUPING_STRING']] = $row['REALISED_SAVING'];
      $new['Total Match RFQ events cheaper than Buyer selected supplier'][$row['GROUPING_STRING']] = $row['TOTAL_EVENT_CHEAPER'];
      $new['Total Match RFQ events with 100% buyer and match quotes'][$row['GROUPING_STRING']] = @($row['TOTAL_B_SEL_CHEAPER']);

      $new['Average Match Inbox Processing Time (d)'][$row['GROUPING_STRING']] = $row['AVG_PROCESSING_TIME'];
      $new['Average Potential Savings per Match RFQ events'][$row['GROUPING_STRING']] = $row['AVG_POTENTIAL_SAVING'];
      $new['Average Realised Savings per Match RFQ events'][$row['GROUPING_STRING']] = $row['AVG_REALISED_SAVING'];

      $new['% RFQ events where no matched suppliers quoted'][$row['GROUPING_STRING']]
      = 100-(@($row['RFQ_EVET_NOT_QUOTED']/$new['Total RFQ events processed'][$row['GROUPING_STRING']]*100));

      $new['% RFQ events where buyer chose matched supplier'][$row['GROUPING_STRING']]
      = @($new['Order from Match RFQs'][$row['GROUPING_STRING']]/$new['Total RFQ events processed'][$row['GROUPING_STRING']]*100);

      $new['% Match Quotes converted to PO'][$row['GROUPING_STRING']]
      = @($new['Order from Match RFQs'][$row['GROUPING_STRING']]/$new['Match RFQ Quoted'][$row['GROUPING_STRING']]*100);

      $new['% Match Quoted RFQ Events Cheaper than Buyer Selected Suppliers'][$row['GROUPING_STRING']]
      = isset($row['TOTAL_B_SEL_CHEAPER']) && $row['TOTAL_B_SEL_CHEAPER'] <> 0 ? @($row['TOTAL_EVENT_CHEAPER']/$row['TOTAL_B_SEL_CHEAPER']*100) : 0;
		}

		foreach($this->getMatchReleaseData() as $row)
		{
			$new['Total Match Engine Releases'][$row['GROUPING_STRING']] = $row['TOTAL_RELEASE'];
		}

		// end of loop
        return $new;

	}

}
