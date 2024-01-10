<?php
/**
* Get list of RFQ, PO wo IMO 
*/
class Shipserv_Report_Usage_Data_RfqOrdWoImo extends Shipserv_Report
{

	protected $byo;

	/**
	* Constructor, create instance and set Buyer Org Code
	* @param integer $byo Buyer Org Code
	* @param integer $range The time range
	* @return object 
	*/
	public function __construct($byo = null, $range = 0)
	{
		$this->byo = $byo;
		$this->range = $range;
	}

	/**
	* Return the RFQ, PO wo IMO 
	* @return array List of Verified Accounts belonging to a BYO 
	*/
	public function getData()
	{

		/*
			Todo ADD 
			Terms (has used accepted latest terms and conditions – may need a little investigation here)
			Number of events
			Number of days in period where at least one event exists, 
			Date of most recent event
		*/
		$sql = "
				WITH rfq_wo_imo AS
				(
					SELECT
					  'RFQ' printable,
					  r.rfq_internal_ref_no internal_ref_no,
					  r.rfq_valid_vessel_name vessel_name,
					  r.rfq_subject subject,
				      r.rfq_submitted_date txn_date
					FROM
					  rfq r JOIN buyer b on r.byb_branch_code = b.byb_branch_code
					WHERE
					  r.rfq_submitted_date > ADD_MONTHS(SYSDATE, :range)
					  and b.byb_byo_org_code = :byo
					  and r.rfq_imo_no is null
					ORDER BY
					 r.rfq_internal_ref_no
				), ord_wo_imo AS
				(
					SELECT
					  'PO' printable,
					  o.ord_internal_ref_no internal_ref_no,
					  o.ord_valid_vessel_name vessel_name,
					  o.ord_subject subject,
				      o.ord_submitted_date txn_date
					FROM
					  ord o JOIN buyer b on o.byb_branch_code = b.byb_branch_code
					WHERE
					  o.ord_submitted_date > ADD_MONTHS(SYSDATE, :range)
					  and b.byb_byo_org_code = :byo
					  and o.ord_imo_no is null
					ORDER BY
					 o.ord_internal_ref_no
				), aggregated AS 
				(
					SELECT
						*
					FROM
						rfq_wo_imo
					UNION ALL
					SELECT
						*
					FROM
						ord_wo_imo
				), ordered_aggregated AS
				(
				SELECT 
				  a.*
				FROM
				  aggregated a
				ORDER BY
				  txn_date DESC
				)
				
				SELECT
					rownum id,
					oa.*
				FROM
					ordered_aggregated oa
			";

		$params = array(
				'byo' => $this->byo,
				'range' => -$this->range
		);
		
		$cacheKey = __METHOD__ . '_' . md5($sql.print_r($params, true));
        $records = $this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

		return $records;
	}
}