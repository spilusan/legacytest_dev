<?php
/**
* Get list of user activities
*/
class Shipserv_Report_Usage_Data_UserActivity extends Shipserv_Report
{

	protected $byo;
	protected $reportType;
	protected $range;
	protected $excludeShipmate;

	/**
	* Constructor, create instance and set Buyer Org Code
	* @param integer $byo        Buyer Org Code
	* @param integer $range      Months back from current date
	* @param string  $reportType Report type to filter
    * @param boolean $excludeShipmate
	* @return object 
	*/
	public function __construct($byo = null, $range = 36, $reportType = '', $excludeShipmate = false)
	{
		$this->byo = $byo;
		$this->reportType = $reportType;
		$this->range = -(int)$range;
        $this->excludeShipmate = $excludeShipmate;
	}

	/**
	* Return the successfull sign ins for the datanase
	* @return array List of successfull sign ins for the particular BYO 
	*/
	public function getData()
	{

		$events = Shipserv_Report_Usage_Activity::getInstance()->getEventsByGroupName(trim($this->reportType));

		$sql = "
			SELECT 
				TO_CHAR(pua.pua_date_created,'DD-MON-YYYY HH24:MI') pua_date_created,
            	u.usr_name,
            	pua_activity
			FROM
				pages_user_activity pua
			JOIN
				pages_user_company@livedb_link  puc
				on (
					puc.puc_psu_id = pua.pua_psu_id
					and puc.puc_company_type = 'BYO'
					and puc.puc_status = 'ACT'
				)
			JOIN
				users@livedb_link u
				ON (
					pua.pua_psu_id = u.usr_user_code
				)
			WHERE
				pua_activity IN ('".implode("','", $events)."')\n";
		if ($this->excludeShipmate) {
			$sql .= "and pua_is_shipserv = 'N'\n";
		}
		
		$sql .=  "and pua_date_created > ADD_MONTHS(SYSDATE, :range) and puc.puc_company_id = :byo
			ORDER BY pua_activity, pua_date_created";

		$params = array(
			'byo' => $this->byo,
			'range' => $this->range
			);


		$cacheKey = __METHOD__ . '_' . md5($sql.print_r($params, true));
        $records = $this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

		return $records;
	}

}