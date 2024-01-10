<?php
/**
 * Get list of active trading accounts related to a byo
*/
class Shipserv_Report_Usage_Data_ActiveTradingAccounts extends Shipserv_Report
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
	 * Return the active trading accounts from datanase
	 * @return array List of Verified Accounts belonging to a BYO
	 */
	public function getData()
	{

	
		$sql = "
				SELECT
              		byb_branch_code,
              		byb_name
				FROM
					buyer b2
				WHERE
					b2.byb_is_inactive_account = 0
					and b2.byb_is_test_account = 0
				START WITH 
					b2.byb_byo_org_code = :byo
					--	or b2.byb_byo_org_code IN (
					--		SELECT pbn_byo_org_code FROM pages_byo_norm@livedb_link WHERE pbn_norm_byo_org_code=b.byb_byo_org_code
					--	)
				CONNECT BY NOCYCLE PRIOR b2.byb_branch_code = b2.parent_branch_code
			";

		$params = array('byo' => $this->byo);

		$cacheKey = __METHOD__ . '_' . md5($sql.print_r($params, true));
		$records = $this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

		return $records;
	}
}