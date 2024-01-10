<?php
class Shipserv_Report_Buyer_Gmv_Purchaser extends Shipserv_Report
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
				$this->buyerTnid = (int)$params['buyerId'];
			}
			if( $params['parent'] != "" )
			{
				$this->isParent = ((int)$params['parent'] === 1);
			}
		}
	}

	public function getData()
	{
		$sql = "
-- getting the list of buyers
WITH
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
		bbb.byb_branch_code IN(" . $this->buyerTnid  . ")
		" . ( ($this->isParent == true) ? "OR b.parent_branch_code=" . $this->buyerTnid  . "" : "" ) . "

),
-- all gmv is coming from this table
all_orders_with_purchaser AS
(
  SELECT
    ro.ord_internal_ref_no
    , ro.ord_imo_no
    , ro.ord_total_vbp_usd
    , ro.rfq_internal_ref_no
    , tg.byb_branch_code
    , tg.spb_branch_code
    , final_total_cost_usd ord_total_cost_usd
    , tg.ord_orig_submitted_date ord_submitted_date
	, tg.ord_is_direct
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
  FROM
    ord_traded_gmv tg JOIN ord ro ON (ro.ord_internal_ref_no=tg.ord_original)
	LEFT JOIN contact ON (cntc_doc_type='ORD' AND cntc_doc_internal_ref_no=ro.ord_internal_ref_no AND cntc_branch_qualifier='BY')
  WHERE
    EXISTS (
      SELECT null FROM all_buyers ab WHERE ab.byb_branch_code=tg.byb_branch_code
    )
    AND tg.ord_orig_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY') AND to_date(:endDate, 'DD-MON-YYYY') + 0.99999
)
,
purchaser AS (
  SELECT
    DISTINCT
      byb_branch_code
      , purchaser_detail
      , TRIM(SUBSTR(purchaser_detail, 0, INSTR(purchaser_detail, ' (')-1)) purchaser_name
      , TRIM(SUBSTR(purchaser_detail, INSTR(purchaser_detail, '('))) purchaser_email
  FROM all_orders_with_purchaser
)

,
transaction_sent_by_purchaser AS (
  SELECT
    lrqp.*
  FROM
    linked_rfq_qot_po lrqp
  WHERE
    EXISTS(
      SELECT null FROM purchaser p WHERE p.purchaser_detail=lrqp.purchaser_detail
    )
)
,
purchaser_data AS (
  SELECT
    /*+ index(linked_rfq_qot_po IDX_LINKED_RQP_N7) */
    purchaser_name
    , lrqp.purchaser_detail
    , (
        COUNT(DISTINCT lrqp.rfq_event_hash)
    ) unique_event_hash
    , (
        COUNT(
			DISTINCT
			CASE WHEN has_po=1 THEN lrqp.rfq_event_hash END
		)
    ) total_po
    , (
        COUNT(
        	DISTINCT
        	CASE WHEN has_po=0 THEN lrqp.rfq_event_hash END
        )
    ) total_rfq_event_no_po
    , (
		SELECT 
		  SUM(x.ord_total_cost_usd)
		FROM
		  all_orders_with_purchaser x
		WHERE
		  x.purchaser_detail=lrqp.purchaser_detail
    ) total_ord_value_usd
    , (
        SUM(leakage_usd_total)
    ) leakage_gmv
    , (
        SUM(leakage_vbp_revenue)
    ) leakage_revenue
  FROM
    linked_rfq_qot_po lrqp JOIN purchaser p
    ON (
      lrqp.purchaser_detail=p.purchaser_detail
    )
  WHERE
    lrqp.rfq_submitted_date BETWEEN to_date(:startDate, 'DD-MON-YYYY') AND to_date(:endDate, 'DD-MON-YYYY') + 0.99999
  GROUP BY
    purchaser_name
    , lrqp.purchaser_detail
)
,
transaction_by_pur_spb AS (
  SELECT
    lrqp.purchaser_detail
    , lrqp.rfq_event_hash
    , COUNT(DISTINCT lrqp.spb_branch_code) total_supplier
  FROM
    transaction_sent_by_purchaser lrqp
  WHERE
    EXISTS(
      SELECT null FROM purchaser p WHERE p.purchaser_detail=lrqp.purchaser_detail
    )
  GROUP BY
    lrqp.purchaser_detail, lrqp.rfq_event_hash
)
,
transaction_by_pur_spb_avg AS (
  SELECT
    lrqp.purchaser_detail
    , avg(total_supplier) avg_spb
  FROM
    transaction_by_pur_spb lrqp
  WHERE
    EXISTS(
      SELECT null FROM purchaser p WHERE p.purchaser_detail=lrqp.purchaser_detail
    )
  GROUP BY
    lrqp.purchaser_detail
)
,
purchaser_stat AS (
  SELECT
    pd.*
    , (
        SELECT
          MIN(r.rfq_submitted_date)
        FROM
          rfq r JOIN contact c ON (
            r.rfq_internal_ref_no=c.cntc_doc_internal_ref_no
            AND c.cntc_doc_type='RFQ'
            AND c.cntc_branch_qualifier='BY'
          )
        WHERE
          TRIM(LOWER(c.cntc_person_name))=p.purchaser_name
          AND c.byb_branch_code=p.byb_branch_code
    ) first_rfq_sent
	, (
        SELECT
          SUM(CASE WHEN ord_is_direct=1 THEN 1 END)
        FROM
          all_orders_with_purchaser dps
        WHERE
          LOWER(p.purchaser_detail)=LOWER(dps.purchaser_detail)
    ) total_direct_po
    , (
        SELECT
          MAX(r.rfq_submitted_date)
        FROM
          rfq r JOIN contact c ON (
            r.rfq_internal_ref_no=c.cntc_doc_internal_ref_no
            AND c.cntc_doc_type='RFQ'
            AND c.cntc_branch_qualifier='BY'
          )
        WHERE
          TRIM(LOWER(c.cntc_person_name))=p.purchaser_name
          AND c.byb_branch_code=p.byb_branch_code
    ) last_rfq_sent
    , av.avg_spb
  FROM
    purchaser p JOIN purchaser_data pd ON (p.purchaser_detail=pd.purchaser_detail)
    JOIN transaction_by_pur_spb_avg av ON (p.purchaser_detail=av.purchaser_detail)
)

SELECT
	p.*
    , round((p.last_rfq_sent - p.first_rfq_sent)/365, 1) activity_in_years
  FROM
    purchaser_stat p
ORDER BY
	total_ord_value_usd DESC
";

		$params = array('startDate' => $this->startDate->format("d-M-Y"), 'endDate' => $this->endDate->format("d-M-Y"));

		if( $_GET['terminated'] == 1 ){
			echo $sql; print_r($params); die();
		}

		$db = $this->getDbByType('ssreport2');
		$key = md5($sql) .  "Shipserv_Report_Buyer_Gmv_Purchaser" . print_r($params, true) . print_r($_GET, true);

		return $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');
	}

	public function getBuyer()
	{
		return $this->buyer;
	}


}
