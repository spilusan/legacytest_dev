<?php
/**
* Get list of verified accounts related to a byo
*/
class Shipserv_Report_Usage_Data_NonVerifiedAccounts extends Shipserv_Report
{

	protected $byo;

	/**
	* Constructor, create instance and set Buyer Org Code
	* @param integer $byo Buyer Org Code
	* @return object 
	*/
	public function __construct($byo = null)
	{
		$this->byo = $byo;
	}

	/**
	* Return the verified accounts from datanase
	* @return array List of Verified Accounts belonging to a BYO 
	*/
	public function getData()
	{
		$sql = "
			SELECT
				psu_email,
				psu_firstname,
				psu_lastname,
				TO_CHAR(psu_creation_date,'DD-MON-YYYY HH24:MI') psu_creation_date,
				puc_level,
				puc_status
			FROM
				pages_user_company puc JOIN
				pages_user pu
				ON (
					pu.psu_id = puc.puc_psu_id
				)
			WHERE
				puc_company_type = 'BYO'
				and puc.puc_company_id = :byo
				and pu.psu_email_confirmed != 'Y'
			ORDER BY 
				psu_firstname,
				psu_lastname
			";

		$params = array('byo' => $this->byo);

		$cacheKey = __METHOD__ . '_' . md5($sql.print_r($params, true));
        $records = $this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSERVDBA);

		return $records;
	}
}