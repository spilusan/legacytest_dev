<?php
class Shipserv_Report_Supplier_Conversion_Rfq extends Shipserv_Report
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

			if( $params['tnid'] != "" )
			{
				$this->tnid = $params['tnid'];
			}
		}
	}

	public function getData( $rolledUp = false )
	{
		$supplierSql ='SELECT * FROM supplier WHERE spb_branch_code=' . $this->tnid . ' OR parent_branch_code=' . $this->tnid;

		$sql = "
WITH
supplier_data AS (
	" . $supplierSql . "
)
,
base AS (
  SELECT
    DISTINCT
      r.rfq_event_hash
      , r.rfq_internal_ref_no rfq_sent_to_spb
      , r.spb_branch_code spb_branch_code
      , (SELECT qot_internal_ref_no FROM qot WHERE rfq_internal_ref_no=r.rfq_internal_ref_no AND spb_branch_code=r.spb_branch_code AND rownum=1) qot_by_spb
      , (SELECT qot_total_cost_usd FROM qot WHERE rfq_internal_ref_no=r.rfq_internal_ref_no AND spb_branch_code=r.spb_branch_code AND rownum=1) qot_price_by_spb

      , r2.rfq_internal_ref_no rfq_sent_to_other
      , r2.spb_branch_code  spb_other
      , q2.qot_internal_ref_no qot_by_other
      , q2.spb_branch_code qot_spb_branch_code
      , o.ord_internal_ref_no ord_sent_to_other
      , o.ord_total_cost_usd ord_cost_to_other
      , l.lop_ord_internal_ref_no ord_internal_ref_no_no_qot
      , AVG(CASE WHEN q2.spb_branch_code!=r.spb_branch_code AND q2.spb_branch_code IS NOT null THEN q2.qot_total_cost_usd ELSE null END) OVER (PARTITION BY r.rfq_event_hash) average_quoted_price

      , MIN(CASE WHEN q2.spb_branch_code!=r.spb_branch_code AND q2.spb_branch_code IS NOT null THEN q2.qot_total_cost_usd ELSE null END) OVER (PARTITION BY r.rfq_event_hash) cheapest_quoted_price

      , AVG(
          CASE
            WHEN q2.spb_branch_code!=r.spb_branch_code
                  AND q2.spb_branch_code IS NOT null
                  AND r2.rfq_submitted_date IS NOT NULL
                  AND q2.qot_submitted_date IS NOT NULL
                  THEN
               (q2.qot_submitted_date - r2.rfq_submitted_date)
            ELSE
              null
          END
      ) OVER (PARTITION BY r.rfq_event_hash) avg_response_time
    FROM
      rfq r
      , supplier_data s
      , buyer byb
      , rfq r2
          LEFT OUTER JOIN linkable_orphaned_po l ON (l.lop_rfq_internal_ref_no=r2.rfq_internal_ref_no)
          LEFT OUTER JOIN qot q2
          ON ( r2.rfq_is_latest=1 AND q2.rfq_internal_ref_no=r2.rfq_internal_ref_no AND q2.spb_branch_code=r2.spb_branch_code AND r2.rfq_line_count=q2.qot_line_count)
            LEFT OUTER JOIN ord o
            ON (
                  (q2.qot_internal_ref_no=o.qot_internal_ref_no AND q2.spb_branch_code=o.spb_branch_code)
					-- orphaned orders
                  OR (l.lop_ord_internal_ref_no=o.ord_internal_ref_no)
            )
              LEFT OUTER JOIN billable_po rep
              ON (o.ord_internal_ref_no=rep.ord_internal_ref_no)
                LEFT OUTER JOIN poc p
                ON (o.ord_internal_ref_no=p.ord_internal_ref_no)

    WHERE
      r.spb_branch_code=s.spb_branch_code
      AND r.rfq_submitted_date BETWEEN TO_DATE(:startDate, 'DD-MON-YYYY') AND TO_DATE(:endDate, 'DD-MON-YYYY')+0.99999
      AND r.rfq_is_latest=1
      AND r.byb_branch_code=byb.byb_branch_code

      AND r.rfq_event_hash=r2.rfq_event_hash
      --AND r.byb_branch_code=r2.byb_branch_code
	  --AND r.rfq_internal_ref_no=12845100
	  --AND r.rfq_internal_ref_no IN (14775878)

  ORDER BY
    r.rfq_event_hash
)

,
initial_grouped_data AS
(
  SELECT
    rfq_event_hash
    , rfq_sent_to_spb
    , base.spb_branch_code
    , qot_by_spb
    , average_quoted_price
    , cheapest_quoted_price
    , CASE WHEN avg_response_time IS NOT null THEN ROUND(avg_response_time, 1) END avg_response_time
    , SUM(CASE WHEN base.spb_branch_code=spb_other THEN 0 ELSE 1 END ) number_of_competitor
    , SUM(CASE WHEN qot_by_other IS NOT null AND base.spb_branch_code!=qot_spb_branch_code  THEN 1 ELSE 0 END) number_of_competitor_quoted
    , MAX(ord_sent_to_other) ord_sent_to_other
	, MAX(ord_internal_ref_no_no_qot) ord_internal_ref_no_no_qot
  FROM
    base
  GROUP BY
    rfq_event_hash
    , rfq_sent_to_spb
    , base.spb_branch_code
    , qot_by_spb
    , average_quoted_price
    , cheapest_quoted_price
    , avg_response_time

)
,
initial_data AS
(
SELECT
  i.*
  , ( SELECT rfq_internal_ref_no FROM ord WHERE ord_internal_ref_no=i.ord_sent_to_other ) winner_rfq_id
  , ( SELECT qot_internal_ref_no FROM ord WHERE ord_internal_ref_no=i.ord_sent_to_other ) winner_qot_id

	, (
      SELECT
        oo.spb_branch_code
      FROM
        ord oo
      WHERE
        oo.ord_internal_ref_no=i.ord_sent_to_other
        AND rownum=1
  ) ord_spb_branch_code
	, (
      SELECT
        oo.ord_submitted_date
      FROM
        ord oo
      WHERE
        oo.ord_internal_ref_no=i.ord_sent_to_other
        AND rownum=1
  ) ord_submitted_date

FROM
  initial_grouped_data i

)

,
raw_data AS
(
  SELECT
	tbl.*
    , (
			SELECT r.byb_branch_code FROM rfq r
			WHERE r.rfq_internal_ref_no=tbl.rfq_sent_to_match
			AND rownum=1
	) match_buyer_tnid
	, (
			SELECT b.byb_name FROM rfq r JOIN buyer b ON (r.byb_branch_code=b.byb_branch_code)
			WHERE r.rfq_internal_ref_no=tbl.rfq_sent_to_match
			AND rownum=1
	) match_buyer_name
  FROM
  (
	  SELECT

	    r.rfq_internal_ref_no
	    , r.rfq_ref_no
	    , r.rfq_submitted_date
	    , r.rfq_line_count
	    , r.rfq_vessel_name
	    , r.rfq_subject
	    , r.rfq_qot_count
	    , r.rfq_dec_count
	    , r.rfq_ord_count
	    , CASE WHEN q.qot_submitted_date IS NOT null AND r.rfq_submitted_date IS NOT null THEN ROUND( (q.qot_submitted_date - r.rfq_submitted_date), 1) END supplier_response_time
	    , (SELECT rfq_delivery_port FROM request_for_quote@livedb_link WHERE rfq_internal_ref_no=r.rfq_internal_ref_no) DELIVERY_DETAIL

	    , q.qot_internal_ref_no
	    , q.qot_submitted_date
	    , q.qot_total_cost_usd
	    , q.qot_line_count
	    , s.spb_branch_code
	    , s.spb_name
	    , s.spb_country_code
	    , b.byb_branch_code
	    , b.byb_name

	    , b.average_quoted_price
	    , b.cheapest_quoted_price
	    , b.number_of_competitor
	    , b.number_of_competitor_quoted
	    , b.avg_response_time
		, b.ORD_SENT_TO_OTHER
		--, b.ord_internal_ref_no_no_qot
		, (
	      	CASE WHEN b.ord_sent_to_other IS NOT null THEN null
	      	ELSE b.ord_internal_ref_no_no_qot END
	    ) ord_internal_ref_no_no_qot

	    , (
	      SELECT
			DISTINCT MAX(rep.ord_total_cost_discounted_usd) KEEP (DENSE_RANK FIRST ORDER BY rep.primary_id DESC)
	      FROM
			billable_po rep
	      WHERE
	        rep.ord_internal_ref_no=ord_sent_to_other
	    ) ord_total_cost_usd_supplier

		, (
		    CASE WHEN q.qot_line_count > 0 AND r.rfq_line_count > 0 THEN
		      q.qot_line_count/r.rfq_line_count*100
		    ELSE
		      null
		    END
		) qot_items_quoted

	    , (
			SELECT
			COUNT(*)
			FROM
			  match_b_rfq_to_match m JOIN rfq r
			    ON (m.rfq_internal_ref_no=r.rfq_internal_ref_no)
			      JOIN match_b_rfq_forwarded_by_match f
			        ON (f.rfq_sent_to_match=m.rfq_internal_ref_no)
			WHERE
			  r.rfq_event_hash=b.rfq_event_hash
			  AND f.spb_branch_code=b.spb_branch_code
	    ) is_match_rfq

		, (
	    	SELECT r.rfq_internal_ref_no FROM match_b_rfq_to_match m JOIN rfq r ON (m.rfq_internal_ref_no=r.rfq_internal_ref_no) WHERE r.rfq_event_hash=b.rfq_event_hash AND rownum=1
		) rfq_sent_to_match

	    , (
	       SELECT COUNT(*) FROM rfq WHERE rfq_event_hash=b.rfq_event_hash AND rfq_pin_id IS NOT NULL
	    ) is_pages_rfq

	    , CASE
			WHEN q2.qot_submitted_date IS NOT null AND r2.rfq_submitted_date IS NOT null THEN
				ROUND( (q2.qot_submitted_date - r2.rfq_submitted_date), 1)
		  END
		  winner_response_time
		, ord_spb_branch_code
		, ord_submitted_date
	  FROM
	    (
		    initial_data b JOIN rfq r
		      ON (r.rfq_internal_ref_no=b.rfq_sent_to_spb AND r.spb_branch_code=b.spb_branch_code)
		        JOIN supplier s ON (s.spb_branch_code=r.spb_branch_code)
		        JOIN buyer b ON (b.byb_branch_code=r.byb_branch_code)
		        LEFT OUTER JOIN qot q
		          ON (q.qot_internal_ref_no=b.qot_by_spb)
	    ) LEFT OUTER JOIN qot q2
	        ON (winner_qot_id=q2.qot_internal_ref_no)
	      LEFT OUTER JOIN rfq r2
	        ON (winner_rfq_id=r2.rfq_internal_ref_no AND r2.spb_branch_code=b.ord_spb_branch_code )
	) tbl
)
SELECT
	tbl2.*
	, (
		CASE WHEN match_buyer_tnid IS NOT null THEN
			buyer_is_new_match
		ELSE
			buyer_is_new
		END
	) byb_is_new
	, (
		CASE
			WHEN winner_response_time IS NULL  THEN
				supplier_response_time
			ELSE
				winner_response_time
		END
	) winner_response_time
FROM
(
	SELECT
	  DISTINCT
	  d.*
	  , (
		SELECT spb_name FROM supplier WHERE spb_branch_code=ord_spb_branch_code
	  ) winning_supplier
	  , (
	      CASE
			WHEN qot_internal_ref_no IS NOT null /*AND rfq_qot_count=1*/ AND ord_spb_branch_code IS NOT null AND ord_spb_branch_code=spb_branch_code THEN 'Quoted (Won)'
			WHEN qot_internal_ref_no IS NOT null /*AND rfq_qot_count=1*/ AND ord_spb_branch_code IS NOT NULL AND ord_spb_branch_code!=spb_branch_code AND ord_internal_ref_no_no_qot IS null THEN 'Quoted (Lost)'
			WHEN qot_internal_ref_no IS NOT null /*AND rfq_qot_count=1*/ AND ord_spb_branch_code IS NOT NULL AND ord_spb_branch_code!=spb_branch_code AND ord_internal_ref_no_no_qot IS NOT null THEN 'Quoted (Lost to orphaned PO)'
			WHEN qot_internal_ref_no IS NOT null /*AND rfq_qot_count=1*/ AND ord_spb_branch_code IS NULL AND rfq_dec_count=0 AND rfq_qot_count=1 THEN 'Quoted (Open)'
			WHEN ord_internal_ref_no_no_qot IS NOT null AND ord_spb_branch_code=spb_branch_code THEN 'No quote (Won)'
			WHEN ( ord_internal_ref_no_no_qot IS NOT null AND ord_spb_branch_code!=spb_branch_code AND rfq_dec_count=0 AND rfq_qot_count=0) THEN 'No quote (Lost to Orphaned PO)'
			WHEN (qot_internal_ref_no IS null AND ord_spb_branch_code!=spb_branch_code AND rfq_dec_count=0 AND rfq_qot_count=0) THEN 'No quote (Lost)'
			WHEN rfq_dec_count=1 THEN 'Declined'
			ELSE 'No Response'
	      END
	    ) rfq_status
	  , (
	      CASE
	        WHEN is_match_rfq > 0 THEN 'Match'
	        WHEN is_pages_rfq > 0 THEN 'Pages'
	        ELSE 'Tradenet'
	      END
	    ) rfq_source
	  , (
			SELECT
				RTRIM(xmlagg(xmlelement(c, ( SUBSTR(TRIM(REGEXP_REPLACE(TRIM(psa_answer), '[[:cntrl:]]', '__')), 0, 55)) || ',') ).extract ('//text()'), ',') comments
			FROM
				pages_survey_answer@livedb_link
			WHERE
				psa_rfq_internal_ref_no=d.rfq_internal_ref_no
				AND psa_tnid=d.spb_branch_code

	  ) rfq_decline_reason
	  , (
		      SELECT
		        CASE WHEN COUNT(*)=0 THEN 'Y' ELSE 'N' END
		      FROM
		        ord o
		      WHERE
		       	o.ord_submitted_date < TO_DATE(:startDate, 'DD-MON-YYYY')
		        AND d.byb_branch_code=o.byb_branch_code
		        AND d.spb_branch_code=o.spb_branch_code

	  ) buyer_is_new
	  , (
		      SELECT
		        CASE WHEN COUNT(*)=0 THEN 'Y' ELSE 'N' END
		      FROM
		        ord o
		      WHERE
		        o.ord_submitted_date < TO_DATE(:startDate, 'DD-MON-YYYY')
		        AND o.byb_branch_code=d.match_buyer_tnid
		        AND d.spb_branch_code=o.spb_branch_code

	  ) buyer_is_new_match
	  , (
	    CASE
	        -- if other supplier won then compare winner's PO's price with qot given by this supplier
		      WHEN d.ord_total_cost_usd_supplier > 0  AND d.qot_total_cost_usd > 0 AND d.qot_items_quoted=100 THEN
		        TO_CHAR( ( d.ord_total_cost_usd_supplier - d.qot_total_cost_usd ) / d.qot_total_cost_usd * 100 )

	        -- if no supplier won, but there's cheapest qot, then compare cheapest with qot given by this supplier
	        WHEN d.ord_total_cost_usd_supplier IS NULL AND qot_total_cost_usd > 0 AND d.cheapest_quoted_price > 0 AND d.qot_items_quoted=100 THEN
		        TO_CHAR( ( d.cheapest_quoted_price - d.qot_total_cost_usd ) / d.cheapest_quoted_price * 100 )

	        WHEN d.ord_total_cost_usd_supplier IS null AND d.qot_total_cost_usd IS null THEN
        'NA'
	    END
	  )
	  price_competitiveness
	  , (
	    CASE
			WHEN
				-- when supplier and average response time are available, but no winner yet
				supplier_response_time > 0 AND avg_response_time IS NOT null AND winner_response_time IS null THEN
					TO_CHAR( ( d.avg_response_time - d.supplier_response_time ) / d.supplier_response_time * 100 )
			WHEN
				supplier_response_time > 0 AND avg_response_time IS NOT null AND winner_response_time IS NOT null THEN
					TO_CHAR( ( d.winner_response_time - d.supplier_response_time ) / d.supplier_response_time * 100 )
			ELSE
				'NA'
	    END
	  )
	  speed_competitiveness
FROM
	  raw_data d
) tbl2
		";

		$params = array('startDate' => $this->startDate->format("d-M-Y"), 'endDate' => $this->endDate->format("d-M-Y"));
		$db = $this->getDbByType('ssreport2');
		$key = "Shipserv_Report_Supplier_Conversion" . md5($sql) . print_r($params, true);

		if( $_GET['terminated'] == 1 ){
			echo $sql; print_r($params); die();
		}

		return $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');
	}
}
