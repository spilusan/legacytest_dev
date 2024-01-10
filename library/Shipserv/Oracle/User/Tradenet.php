<?php

class Shipserv_Oracle_User_Tradenet extends Shipserv_Oracle
{

	/**
	 * Feth one user by buyer branch code
	 * @param integer $bybBranchCode
	 * @return array
	 */
	public function fetchOneUserByBybBranchCode($bybBranchCode)
	{
		$db = Shipserv_Helper_Database::getDb();
		$sql = "SELECT usr_user_code,usr_md5_code, usr_name FROM buyer_branch_user JOIN users ON (bbu_usr_user_code=usr_user_code) WHERE bbu_byb_branch_code=:tnid";
		$result = $db->fetchAll($sql, array('tnid' => $bybBranchCode));
		$user = $this->fetchUserByName($result[0]['USR_NAME']);

		return $user;
	}

	/**
	 * Get the user by it's name, If cached is true it will use memcache
	 * @param string $username
	 * @param boolean $cached
	 * @return array
	 */
    public function fetchUserByName($username, $cached = false)
    {
		/* S16126 cborja - Include columns for Keppel users/managers */
    	
    	$params = array(
    			':username' => $username
    	);
    	
        $sql = "
		        SELECT  usr_md5_code          AS md5code,
                        usr_user_code         AS usercode,
                        usr_name              AS username,
                        usr_type              AS usertype,
                        usr_is_view_buyer_org AS viewbuyer,
                        usr_is_view_under_contract       AS viewUnderContract,
						NVL(bbu_rfq_deadline_mgr, 0)     AS rfqdeadlinemgr,
						NVL(ccf_rfq_deadline_control, 0) AS rfqdeadlineallowed
                FROM    users,
				        buyer_branch_user,
						customer_config
                WHERE   usr_name = :username
				  AND   bbu_usr_user_code (+) = usr_user_code
				  AND   ccf_branch_code (+) = bbu_byb_branch_code
		";
  
        $user = ($cached === false) ?  Shipserv_Helper_Database::registryFetchRow(__CLASS__ . '_' . __FUNCTION__, $sql, $params) : $this->fetchCachedQuery($sql, $params, md5('getUser' . $sql . print_r($params, true)));
        
		if ($user['USERTYPE'] === 'P') {
			/* S16126 cborja - Include columns for Keppel users/managers */
			$sql = "
                    SELECT  byb_branch_code       AS branchcode,
                            byb_name              AS branchname,
                            byb_mtml_buyer        AS mtmlbuyer,
                            psu_firstname        AS firstname,
                            psu_lastname         AS lastname,
                            usr_md5_code          AS md5code,
                            usr_user_code         AS usercode,
                            usr_name              AS username,
                            usr_type              AS usertype,
                            1 AS viewbuyer,
                            1 AS viewUnderContract,
                            NVL(psu_rfq_deadline_mgr, 0)     AS rfqdeadlinemgr,
                            NVL(ccf_rfq_deadline_control, 0) AS rfqdeadlineallowed
                    FROM    pages_user psu 
                            JOIN users usr
                            ON (
                              psu_id=usr_user_code
                            )
                            LEFT JOIN pages_user_company puc
                            ON (
                              puc_psu_id = psu_id
                              AND puc_company_type='BYB'
                              AND (
                                puc_webreporter=1
                                OR puc_txnmon=1
                                OR puc_match=1
                                OR puc_buy=1
                                OR puc_approved_supplier=1
                                OR puc_txnmon_adm=1
                                OR puc_autoreminder=1 
                                )
                            )
                            LEFT JOIN buyer_branch byb
                            ON (
                              BYB_BRANCH_CODE = puc_company_id
                            )
                            LEFT JOIN customer_config ccf
                            ON (
                              ccf_branch_code = byb_branch_code
                            )
                     WHERE   psu_email     = :username
            ";

			$user = ($cached === false) ? Shipserv_Helper_Database::registryFetchRow(__CLASS__ . '_' . __FUNCTION__, $sql, $params) : $this->fetchCachedQuery($sql, $params, md5('getUserP' . $sql . print_r($params, true)));
		} else if ($user["USERTYPE"] !== 'A' && $user["USERTYPE"] !== 'S') {
			/* S16126 cborja - Include columns for Keppel users/managers */

			$sql = "
                   SELECT  
                            byb.byb_branch_code       AS branchcode,
                            byb.byb_name              AS branchname,
                            byb.byb_mtml_buyer        AS mtmlbuyer,
                            bbu.bbu_first_name        AS firstname,
                            bbu.bbu_last_name         AS lastname,
                            usr.usr_md5_code          AS md5code,
                            usr.usr_user_code         AS usercode,
                            usr.usr_name              AS username,
                            usr.usr_type              AS usertype,
                            usr.usr_is_view_buyer_org AS viewbuyer,
                            usr.usr_is_view_under_contract       AS viewUnderContract,
                            NVL(bbu.bbu_rfq_deadline_mgr, 0)     AS rfqdeadlinemgr,
                            NVL(ccf.ccf_rfq_deadline_control, 0) AS rfqdeadlineallowed
                    FROM    users usr,
                            buyer_branch_user bbu,
                            buyer_branch byb,
                            customer_config ccf
                    WHERE   usr.usr_name     = :username
                    AND     bbu.bbu_usr_user_code (+) = usr.usr_user_code
                    AND     byb.byb_branch_code (+)= bbu.bbu_byb_branch_code
                    AND     ccf.ccf_branch_code (+) = bbu.bbu_byb_branch_code";

            $user = ($cached === false) ? Shipserv_Helper_Database::registryFetchRow(__CLASS__ . '_' . __FUNCTION__, $sql, $params) : $this->fetchCachedQuery($sql, $params, md5('getUserAS' . $sql . print_r($params, true)));
        }


        
        
        $u = new Shipserv_User_Tradenet($user);
        
        return $u;
    }

}
