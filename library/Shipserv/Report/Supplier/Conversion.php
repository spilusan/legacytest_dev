<?php
class Shipserv_Report_Supplier_Conversion extends Shipserv_Report
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

			if( $params['id'] != "" )
			{
				$this->tnid = $params['id'];
			}

			if( $params['period'] != "" )
			{
				$this->grouping = $params['period'];
			}
			else
			{
				$this->grouping = self::GROUP_BY_MONTH;
			}
		}
	}

	const GROUP_BY_QUARTER = 'quarterly';
	const GROUP_BY_MONTH = 'monthly';
	const GROUP_BY_WEEK = 'weekly';


	public function getGroupingString( $columnName )
	{
		if( $this->grouping == self::GROUP_BY_QUARTER )
		{
			return "
				SELECT
					TO_CHAR(" . $columnName . " , 'YYYY-Q')
					|| ' -- '
					|| TO_CHAR(ADD_MONTHS(TRUNC(" . $columnName . " , 'Q'),0), 'dd Mon yyyy')
			        || ' TO '
					|| TO_CHAR(LAST_DAY(ADD_MONTHS(TRUNC(" . $columnName . " , 'Q'),2)), 'dd Mon yyyy')
				FROM DUAL
			";
		}
		else if( $this->grouping == self::GROUP_BY_WEEK )
		{
			return "
		      SELECT
				TO_CHAR(" . $columnName . " , 'YYYY-WW')
				|| ' -- '
				|| TO_CHAR(TRUNC (" . $columnName . ", 'YYYY') + (7 * (TO_CHAR(" . $columnName . ", 'WW') - 1)), 'dd Mon yyyy')
		        || ' TO '
		        || TO_CHAR(TRUNC (" . $columnName . ", 'YYYY') + (7 * (TO_CHAR(" . $columnName . ", 'WW'))), 'dd Mon yyyy')
		      FROM
		        dual
			";
		}
		else if( $this->grouping == self::GROUP_BY_MONTH )
		{
			return "
			  SELECT
				TO_CHAR(" . $columnName . " , 'YYYY-MM')
				|| ' -- '
				|| TO_CHAR(ADD_MONTHS(TRUNC(" . $columnName . " , 'MM'),0), 'dd Mon yyyy')
		        || ' TO '
				|| TO_CHAR(LAST_DAY(ADD_MONTHS(TRUNC(" . $columnName . ", 'MM'),0)), 'dd Mon yyyy')
			  FROM DUAL

			";
		}
	}

	public function getData( $rolledUp = false )
	{
		//$this->tnid = 58341;
		$supplierSql ='SELECT * FROM supplier WHERE parent_branch_code=' . $this->tnid . ' OR spb_branch_code=' . $this->tnid;


		$sql = "
WITH
supplier_data AS (
	" . $supplierSql . "
)
,
gmv_data AS(
  SELECT
    o.ord_internal_ref_no
    , o.qot_internal_ref_no
    , p.ord_internal_ref_no poc_internal_ref_no
    , (" . $this->getGroupingString('o.ord_submitted_date') . ") grouping_string
    , s.spb_branch_code
    , s.parent_branch_code
    , MAX(rep.ord_total_cost_discounted_usd) KEEP (DENSE_RANK FIRST ORDER BY rep.primary_id DESC)
        OVER (PARTITION BY rep.ord_internal_ref_no) total_cost_usd
  FROM
    ord o
    , poc p
    , billable_po rep
    , supplier_data s
  WHERE
    o.spb_branch_code=s.spb_branch_code
    AND o.ord_submitted_date BETWEEN TO_DATE(:startDate, 'DD-MON-YYYY') AND TO_DATE(:endDate, 'DD-MON-YYYY')+0.99999
    AND o.ord_internal_ref_no=p.ord_internal_ref_no (+)
    AND o.ord_internal_ref_no = rep.ord_internal_ref_no(+)
)
,
match_data AS (
  SELECT
    DISTINCT
    r.rfq_internal_ref_no
	, CASE WHEN q.qot_internal_ref_no IS NOT null THEN rr.rfq_event_hash END  qot_internal_ref_no
    , CASE WHEN o.ord_internal_ref_no IS NOT null THEN rr.rfq_event_hash END  ord_internal_ref_no
    , CASE WHEN p.ord_internal_ref_no IS NOT null THEN rr.rfq_event_hash END  poc_internal_ref_no
    , r.rfq_submitted_date
    , (" . $this->getGroupingString('r.rfq_submitted_date') . ") grouping_string
    , s.spb_branch_code
    , s.parent_branch_code
    , MAX(rep.ord_total_cost_discounted_usd) KEEP (DENSE_RANK FIRST ORDER BY rep.primary_id DESC)
        OVER (PARTITION BY rep.ord_internal_ref_no) total_cost_usd
  FROM
    match_b_rfq_forwarded_by_match r
    , rfq rr
    , match_b_qot_match q
    , match_b_order_by_match_spb o
    , billable_po rep
    , poc p
	, supplier_data s
  WHERE
    r.rfq_submitted_date BETWEEN TO_DATE(:startDate, 'DD-MON-YYYY') AND TO_DATE(:endDate, 'DD-MON-YYYY')+0.99999
    AND r.rfq_internal_ref_no=rr.rfq_internal_ref_no
	AND r.spb_branch_code=s.spb_branch_code
    AND r.rfq_internal_ref_no=q.rfq_internal_ref_no (+)
	--AND q.spb_branch_code=r.spb_branch_code (+)
    AND q.qot_internal_ref_no=o.qot_sent_to_match (+)
    AND o.ord_internal_ref_no=p.ord_internal_ref_no (+)
	AND o.ord_internal_ref_no = rep.ord_internal_ref_no (+)
)
,
tradenet_data AS (
  SELECT
	DISTINCT
    r.rfq_internal_ref_no
    , r.rfq_event_hash
    , CASE WHEN q.qot_internal_ref_no IS NOT null THEN r.rfq_event_hash END  qot_internal_ref_no
	, CASE WHEN o.ord_internal_ref_no IS NOT null THEN r.rfq_event_hash END  ord_internal_ref_no
    , CASE WHEN q.qot_internal_ref_no IS null AND l.lop_ord_internal_ref_no IS NOT null THEN r.rfq_event_hash END  ord_internal_ref_no_no_qot
    , p.ord_internal_ref_no poc_internal_ref_no
    , (CASE WHEN l.lop_rfq_internal_ref_no IS NOT null THEN 1 END ) is_orphaned
    , r.rfq_submitted_date
    , (" . $this->getGroupingString('r.rfq_submitted_date') . ") grouping_string
    , s.spb_branch_code
    , s.parent_branch_code
    , MAX(rep.ord_total_cost_discounted_usd) KEEP (DENSE_RANK FIRST ORDER BY rep.primary_id DESC)
        OVER (PARTITION BY rep.ord_internal_ref_no) total_cost_usd
  FROM
    rfq r
    LEFT OUTER JOIN linkable_orphaned_po l ON (l.lop_rfq_internal_ref_no=r.rfq_internal_ref_no)
    LEFT OUTER JOIN qot q
      ON (r.rfq_internal_ref_no=q.rfq_internal_ref_no AND r.spb_branch_code=q.spb_branch_code)
      LEFT OUTER JOIN ord o
      ON (
    		(l.lop_ord_internal_ref_no=o.ord_internal_ref_no AND o.ord_submitted_date > r.rfq_submitted_date AND o.spb_branch_code=r.spb_branch_code AND o.byb_branch_code=r.byb_branch_code )
    		OR ( q.qot_internal_ref_no=o.qot_internal_ref_no AND q.spb_branch_code=o.spb_branch_code AND r.spb_branch_code=o.spb_branch_code AND r.rfq_line_count=q.qot_line_count) )
        LEFT OUTER JOIN billable_po rep
        ON (o.ord_internal_ref_no=rep.ord_internal_ref_no)
          LEFT OUTER JOIN poc p
          ON (o.ord_internal_ref_no=p.ord_internal_ref_no)
    , supplier_data s
  WHERE
    r.spb_branch_code=s.spb_branch_code
    AND r.rfq_submitted_date BETWEEN TO_DATE(:startDate, 'DD-MON-YYYY') AND TO_DATE(:endDate, 'DD-MON-YYYY')+0.99999
    AND r.rfq_is_latest=1
    AND r.rfq_internal_ref_no NOT IN (
      SELECT rfq_internal_ref_no FROM match_b_rfq_forwarded_by_match
    )
    AND NOT EXISTS (
    	SELECT 1 FROM match_b_rfq_forwarded_by_match WHERE rfq_internal_ref_no=r.rfq_internal_ref_no
    )
--    AND NOT EXISTS (
--    	SELECT 1 FROM match_b_rfq_to_match WHERE rfq_event_hash=r.rfq_event_hash
--    )
    AND r.byb_branch_code!=11107

)
,
pages_data AS (
  SELECT
	DISTINCT
    r.rfq_internal_ref_no
    , r.rfq_event_hash
	, CASE WHEN q.qot_internal_ref_no IS NOT null THEN r.rfq_event_hash END  qot_internal_ref_no
	, CASE WHEN o.ord_internal_ref_no IS NOT null THEN r.rfq_event_hash END  ord_internal_ref_no
	, CASE WHEN p.ord_internal_ref_no IS NOT null THEN r.rfq_event_hash END  poc_internal_ref_no
    , r.rfq_submitted_date
    , (" . $this->getGroupingString('r.rfq_submitted_date') . ") grouping_string
    , s.spb_branch_code
    , s.parent_branch_code
    , MAX(rep.ord_total_cost_discounted_usd) KEEP (DENSE_RANK FIRST ORDER BY rep.primary_id DESC)
        OVER (PARTITION BY rep.ord_original_no) total_cost_usd
  FROM
    rfq r
    , qot q
    , ord o
    , billable_po rep
    , poc p
	, supplier_data s
  WHERE
    r.spb_branch_code=s.spb_branch_code
    AND r.rfq_submitted_date BETWEEN TO_DATE(:startDate, 'DD-MON-YYYY') AND TO_DATE(:endDate, 'DD-MON-YYYY')+0.99999
    AND r.rfq_pages_rfq_id IS NOT NULL
    AND r.rfq_is_latest=1
    AND r.rfq_internal_ref_no=q.rfq_internal_ref_no (+)
    AND q.qot_internal_ref_no=o.qot_internal_ref_no (+)
    AND o.ord_internal_ref_no = rep.ord_internal_ref_no(+)
    AND o.ord_internal_ref_no=p.ord_internal_ref_no (+)
)
,
match_grouped_data AS
(
  SELECT
    m.spb_branch_code
    , m.parent_branch_code
    , m.grouping_string
    , COUNT(DISTINCT m.rfq_internal_ref_no) m_total_rfq
    , COUNT(DISTINCT m.qot_internal_ref_no) m_total_qot
    , COUNT(DISTINCT m.ord_internal_ref_no) m_total_ord
    , 0 m_total_ord_no_qot
    , COUNT(DISTINCT m.poc_internal_ref_no) m_total_poc
    , SUM(m.total_cost_usd) m_total_gmv
  FROM
    match_data m
  GROUP BY
    spb_branch_code, parent_branch_code, grouping_string
)
,
tn_grouped_data AS
(
  SELECT
    tn.spb_branch_code
    , tn.parent_branch_code
    , tn.grouping_string
    , COUNT(DISTINCT tn.rfq_internal_ref_no) tn_total_rfq
    , CASE WHEN COUNT(DISTINCT tn.qot_internal_ref_no) = 0 THEN 0 ELSE COUNT(DISTINCT tn.qot_internal_ref_no)  END tn_total_qot
    , CASE WHEN COUNT(DISTINCT tn.ord_internal_ref_no) = 0 THEN 0 ELSE COUNT(DISTINCT tn.ord_internal_ref_no)  END tn_total_ord
    , CASE WHEN COUNT(DISTINCT tn.ord_internal_ref_no_no_qot) = 0 THEN 0 ELSE COUNT(DISTINCT tn.ord_internal_ref_no_no_qot) END tn_total_ord_no_qot
    , CASE WHEN COUNT(DISTINCT tn.poc_internal_ref_no) = 0 THEN 0 ELSE COUNT(DISTINCT tn.poc_internal_ref_no)  END tn_total_poc
    , SUM(tn.total_cost_usd) tn_total_gmv
  FROM
    tradenet_data tn
  GROUP BY
    spb_branch_code, parent_branch_code, grouping_string
)
,
pages_grouped_data AS
(
  SELECT
    pgs.spb_branch_code
    , pgs.parent_branch_code
    , pgs.grouping_string
    , COUNT(DISTINCT pgs.rfq_internal_ref_no) pgs_total_rfq
    , COUNT(DISTINCT pgs.qot_internal_ref_no) pgs_total_qot
    , COUNT(DISTINCT pgs.ord_internal_ref_no) pgs_total_ord
    , 0 pgs_total_ord_no_qot
    , COUNT(DISTINCT pgs.poc_internal_ref_no) pgs_total_poc
    , SUM(pgs.total_cost_usd) pgs_total_gmv
  FROM
    pages_data pgs
  GROUP BY
    spb_branch_code, parent_branch_code, grouping_string
)
,
gmv_grouped_data AS
(
  SELECT
    g.spb_branch_code
    , g.parent_branch_code
    , g.grouping_string
    , COUNT(*) g_total_ord
    , SUM(CASE WHEN g.qot_internal_ref_no IS NOT null THEN 1 ELSE 0 END) g_total_linked
    , SUM(CASE WHEN g.qot_internal_ref_no IS NOT null THEN g.total_cost_usd ELSE 0 END) g_gmv_linked
    , SUM(CASE WHEN g.qot_internal_ref_no IS null THEN 1 ELSE 0 END) g_total_direct
    , SUM(CASE WHEN g.qot_internal_ref_no IS null THEN g.total_cost_usd ELSE 0 END) g_gmv_direct
    , SUM(g.total_cost_usd) g_gmv
  FROM
    gmv_data g
  GROUP BY
    spb_branch_code, parent_branch_code, grouping_string
)
,
data_output AS
(
	SELECT
		finalResult.*
	  , CASE WHEN ovr_total_qot > 0 AND ovr_total_rfq > 0 THEN ovr_total_qot/ovr_total_rfq * 100 ELSE 0 END ovr_quote_rate
	  , CASE WHEN ovr_total_ord > 0 AND ovr_total_qot > 0 THEN ovr_total_ord/ovr_total_qot * 100 ELSE 0 END ovr_win_rate
	FROM
	(
		SELECT
		  results.*

		  -- overall
		  , ( TN_TOTAL_RFQ + M_TOTAL_RFQ + PGS_TOTAL_RFQ ) OVR_TOTAL_RFQ
		  , ( TN_TOTAL_QOT + M_TOTAL_QOT + PGS_TOTAL_QOT ) OVR_TOTAL_QOT
		  , ( TN_TOTAL_ORD + M_TOTAL_ORD + PGS_TOTAL_ORD ) OVR_TOTAL_ORD
		  , ( TN_TOTAL_ORD_NO_QOT + M_TOTAL_ORD_NO_QOT + PGS_TOTAL_ORD_NO_QOT ) OVR_TOTAL_ORD_NO_QOT
		  , ( TN_TOTAL_POC + M_TOTAL_POC + PGS_TOTAL_POC ) OVR_TOTAL_POC
		  , ( TN_TOTAL_GMV + M_TOTAL_GMV + PGS_TOTAL_GMV ) OVR_TOTAL_GMV
		FROM
		(
			SELECT
			  tn.*
			  , CASE WHEN tn.tn_total_qot > 0 AND tn.tn_total_rfq > 0 THEN tn.tn_total_qot/tn.tn_total_rfq * 100 ELSE 0 END tn_quote_rate
			  , CASE WHEN tn.tn_total_ord > 0 AND tn.tn_total_qot > 0 THEN tn.tn_total_ord/tn.tn_total_qot * 100 ELSE 0 END tn_win_rate

			  , NVL(pgs.pgs_total_rfq, 0) pgs_total_rfq
			  , NVL(pgs.pgs_total_qot, 0) pgs_total_qot
			  , NVL(pgs.pgs_total_ord, 0) pgs_total_ord
              , NVL(pgs.pgs_total_ord_no_qot, 0) pgs_total_ord_no_qot

			  , NVL(pgs.pgs_total_poc, 0) pgs_total_poc
			  , NVL(pgs.pgs_total_gmv, 0) pgs_total_gmv
			  , CASE WHEN pgs.pgs_total_qot > 0 AND pgs.pgs_total_rfq > 0 THEN pgs.pgs_total_qot/pgs.pgs_total_rfq * 100 ELSE 0 END pgs_quote_rate
			  , CASE WHEN pgs.pgs_total_ord > 0 AND pgs.pgs_total_qot > 0 THEN pgs.pgs_total_ord/pgs.pgs_total_qot * 100 ELSE 0 END pgs_win_rate

			  , NVL(m.m_total_rfq, 0) m_total_rfq
			  , NVL(m.m_total_qot, 0) m_total_qot
			  , NVL(m.m_total_ord, 0) m_total_ord
              , NVL(m.m_total_ord_no_qot, 0) m_total_ord_no_qot
			  , NVL(m.m_total_poc, 0) m_total_poc
			  , NVL(m.m_total_gmv, 0) m_total_gmv
			  , CASE WHEN m.m_total_qot > 0 AND m.m_total_rfq > 0 THEN m.m_total_qot/m.m_total_rfq * 100 ELSE 0 END m_quote_rate
			  , CASE WHEN m.m_total_ord > 0 AND m.m_total_qot > 0 THEN m.m_total_ord/m.m_total_qot * 100 ELSE 0 END m_win_rate

			  , NVL(g_total_ord, 0) g_total_ord
			  , NVL(g_total_linked, 0) g_total_linked
			  , NVL(g_gmv_linked, 0) g_gmv_linked
			  , NVL(g_total_direct, 0) g_total_direct
			  , NVL(g_gmv_direct, 0) g_gmv_direct
			  , NVL(g_gmv, 0) g_gmv

			FROM
			  tn_grouped_data tn
			  , match_grouped_data m
			  , gmv_grouped_data g
			  , pages_grouped_data pgs
			WHERE
			  tn.spb_branch_code=m.spb_branch_code (+)
			  AND tn.spb_branch_code=g.spb_branch_code (+)
			  AND tn.spb_branch_code=pgs.spb_branch_code (+)
			  AND tn.grouping_string=m.grouping_string (+)
			  AND tn.grouping_string=g.grouping_string (+)
			  AND tn.grouping_string=pgs.grouping_string (+)
			ORDER BY
			  tn.spb_branch_code, tn.grouping_string ASC
		) results
	) finalResult
)

SELECT
  " . ( ( $rolledUp === true ) ? "parent_branch_code":"spb_branch_code" ) . " spb_branch_code
  , grouping_string

  , SUM(
      CASE WHEN parent_branch_code != spb_branch_code THEN 1 END
  )  total_children

  , SUM(tn_total_rfq) tn_total_rfq
  , SUM(tn_total_qot) tn_total_qot
  , SUM(tn_total_ord) tn_total_ord
  , SUM(tn_total_ord_no_qot) tn_total_ord_no_qot
  , SUM(tn_total_gmv) tn_total_gmv
  , SUM(tn_total_poc) tn_total_poc
  ,	(
  		CASE WHEN SUM(tn_total_rfq) > 0 THEN
  			( SUM(tn_total_qot) / SUM(tn_total_rfq) ) * 100
  		ELSE
  			0
  		END
  )	tn_quote_rate
  , (
  		CASE WHEN SUM(tn_total_qot) > 0 THEN
  			( ( SUM(tn_total_ord) + SUM(tn_total_ord_no_qot) ) / ( SUM(tn_total_qot) + SUM(tn_total_ord_no_qot) ) ) * 100
  		ELSE
  			0
  		END
  ) tn_win_rate

  , SUM(pgs_total_rfq) pgs_total_rfq
  , SUM(pgs_total_qot) pgs_total_qot
  , SUM(pgs_total_ord) pgs_total_ord
  , SUM(pgs_total_ord_no_qot) pgs_total_ord_no_qot
  , SUM(pgs_total_gmv) pgs_total_gmv
  , SUM(pgs_total_poc) pgs_total_poc
  , AVG(NVL(pgs_quote_rate, 0)) pgs_quote_rate
  , AVG(NVL(pgs_win_rate, 0)) pgs_win_rate

  , SUM(m_total_rfq) m_total_rfq
  , SUM(m_total_qot) m_total_qot
  , SUM(m_total_ord) m_total_ord
  , SUM(m_total_ord_no_qot) m_total_ord_no_qot
  , SUM(m_total_gmv) m_total_gmv
  , SUM(m_total_poc) m_total_poc

  ,	(
  		CASE WHEN SUM(m_total_rfq) > 0 THEN
  			( SUM(m_total_qot) / SUM(m_total_rfq) ) * 100
  		ELSE
  			0
  		END
  )	m_quote_rate
  , (
  		CASE WHEN SUM(m_total_qot) > 0 THEN
  			( ( SUM(m_total_ord) + SUM(m_total_ord_no_qot) ) / ( SUM(m_total_qot) + SUM(m_total_ord_no_qot) ) ) * 100
  		ELSE
  			0
  		END
  ) m_win_rate

  , SUM(g_total_ord) g_total_ord
  , SUM(g_total_linked) g_total_linked
  , SUM(g_gmv_linked) g_gmv_linked
  , SUM(g_total_direct) g_total_direct
  , SUM(g_gmv_direct) g_gmv_direct
  , SUM(g_gmv) g_gmv

  , SUM(ovr_total_rfq) ovr_total_rfq
  , SUM(ovr_total_qot) ovr_total_qot
  , SUM(ovr_total_ord) ovr_total_ord
  , SUM(ovr_total_ord_no_qot) ovr_total_ord_no_qot
  , SUM(ovr_total_gmv) ovr_total_gmv
  , SUM(ovr_total_poc) ovr_total_poc


  ,	(
  		CASE WHEN SUM(ovr_total_rfq) > 0 THEN
  			( SUM(ovr_total_qot) / SUM(ovr_total_rfq) ) * 100
  		ELSE
  			0
  		END
  )	ovr_quote_rate
  , (
  		CASE WHEN SUM(ovr_total_qot) > 0 THEN
  			( ( SUM(ovr_total_ord) + SUM(ovr_total_ord_no_qot) ) / ( SUM(ovr_total_qot) + SUM(ovr_total_ord_no_qot) )) * 100
  		ELSE
  			0
  		END
  ) ovr_win_rate

  , TO_CHAR(TO_DATE(:startDate), 'DD-MON-YYYY') STR_START_DATE
  , TO_CHAR(TO_DATE(:endDate), 'DD-MON-YYYY') STR_END_DATE

FROM
  data_output
GROUP BY
  " . ( ( $rolledUp === true ) ? "parent_branch_code":"spb_branch_code" ) . ", grouping_string
ORDER BY
  " . ( ( $rolledUp === true ) ? "parent_branch_code":"spb_branch_code" ) . ", grouping_string ASC
		";


		$params = array('startDate' => $this->startDate->format("d-M-Y"), 'endDate' => $this->endDate->format("d-M-Y"));

		$db = $this->getDbByType('ssreport2');
		$key = "Shipserv_Report_Supplier_Conversion" . md5($sql) . print_r($params, true);

		//echo $sql; print_r($params); die();

		return $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');
	}
}
