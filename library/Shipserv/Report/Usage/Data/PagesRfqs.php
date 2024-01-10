<?php
/**
* Get list of successfull sign ins
*/
class Shipserv_Report_Usage_Data_PagesRfqs extends Shipserv_Report
{

	protected $byo;
	protected $range;

	/**
	* Constructor, create instance and set Buyer Org Code
	* @param integer $byo Buyer Org Code
	* @param integer $range Months back from current date
	* @return object 
	*/
	public function __construct($byo = null, $range = 36)
	{
		$this->byo = $byo;
		$this->range = -(int)$range;
	}

	/**
	* Return the successfull sign ins for the datanase
	* @return array List of successfull sign ins for the particular BYO 
	*/
	public function getData()
	{

		//We may need to add an index if it is too slow
		$sql = "
			SELECT
				r.rfq_internal_ref_no,
				r.rfq_submitted_date,
				u.usr_name,
				r.spb_branch_code,
				s.spb_name
			FROM
				rfq r JOIN rfq r2 
			ON
			(
				r2.rfq_internal_ref_no = r.rfq_pages_rfq_id
			)
			JOIN supplier s
			ON
			(
				s.spb_branch_code = r.spb_branch_code
			)
			LEFT JOIN pages_inquiry_stats pir
			ON (
				pir.pin_rfq_internal_ref_no = r.rfq_internal_ref_no
			)

			LEFT JOIN users@livedb_link u
			ON (
				u.usr_user_code = pir.pin_user_code
				)
			WHERE
				r.rfq_is_latest=1
				AND r.rfq_submitted_date > ADD_MONTHS(SYSDATE, :range)
				AND r.rfq_pages_rfq_id IS NOT null
				AND NOT EXISTS (
					SELECT 1 FROM match_b_rfq_to_match r2m WHERE r2m.rfq_event_hash=r.rfq_event_hash
				)
				AND
				r2.byb_byo_org_code = :byo
			ORDER BY
				r.rfq_internal_ref_no
			";

		$params = array(
			'byo' => $this->byo,
			'range' => $this->range
			);

		$cacheKey = __METHOD__ . '_' . md5($sql.print_r($params, true));
        $records = $this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

		return $records;
	}
}