<?php
/**
* Get list of successfull sign ins
*/
class Shipserv_Report_Usage_Data_SearchEvents extends Shipserv_Report
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

		//$excludeShipmate = false;
		$sql = "
			SELECT
				TO_CHAR(pst_search_date,'DD-MON-YYYY HH24:MI') pst_search_date,
				usr_name,
				pst_search_text,
				pst_country_code
			FROM
				pages_search_stats pst
				JOIN users@livedb_link usr
					ON (
						usr.usr_user_code = pst.pst_user_code
					)
				JOIN pages_user_company@livedb_link puc
					ON (
						pst.pst_user_code = puc.puc_psu_id
					)
				WHERE
					puc_company_type='BYO'
					and puc_status='ACT'
					and puc_company_id = :byo
					and pst_search_date > ADD_MONTHS(SYSDATE, :range)
		";
		
		/*
		if ($excludeShipmate) {
			$sql .= "and pua_is_shipserv = 'N'\n";
		}
		*/
		$params = array(
			'byo' => $this->byo,
			'range' => $this->range
		);

		$cacheKey = __METHOD__ . '_' . md5($sql.print_r($params, true));
        $records = $this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

		return $records;
	}

}