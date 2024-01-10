<?php
/**
* Get list of Supplier Search Impressions
*/
class Shipserv_Report_Usage_Data_SpbImpressions extends Shipserv_Report
{

	protected $byo;
	protected $range;

	/**
	* Constructor, create instance and set Buyer Org Code
	* @param integer $byo        Buyer Org Code
	* @param integer $range      Months back from current date
	* @return object 
	*/
	public function __construct($byo = null, $range = 36)
	{
		$this->byo = $byo;
		$this->range = -(int)$range;
	}

	/**
	* Return the list of searches for the database
	* @return array
	*/
	public function getData()
	{

		$sql = "
			SELECT 
				TO_CHAR(TO_DATE(rui_view_date,'YYYYMMDD'),'DD-MON-YYYY HH24:MI') pss_view_date,
				usr_name,
				rui_spb_branch_code spb_branch_code,
				spb_name
			FROM
				pages_reg_user_impression rui
				JOIN users@livedb_link usr
				ON (
					usr.usr_user_code = rui.rui_user_code
				)
				JOIN supplier s
				ON (
					s.spb_branch_code = rui.rui_spb_branch_code
				)
				JOIN
				pages_user_company@livedb_link  puc
				ON (
					puc.puc_psu_id = rui.rui_user_code
					and puc.puc_company_type = 'BYO'
					and puc.puc_status = 'ACT'
				)
			WHERE
				rui_view_date > TO_NUMBER(TO_CHAR(ADD_MONTHS(SYSDATE, :range), 'YYYYMMDD'))
				and puc_company_id = :byo
			ORDER BY
				rui_view_date, spb_name
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