<?php

class Shipserv_User_Tradenet extends Shipserv_Object {

    protected $username;
    protected $usercode;
    protected $branchcode;
    protected $branchname;
    protected $mtmlbuyer;
    protected $isMtmlbuyer;
    protected $firstname;
    protected $lastname;
    protected $md5code;
    protected $usertype;
    protected $viewbuyer;
    protected $viewundercontract;
    protected $isAuthenticated;
    protected $isAdmin;

    /* S16126 cborja - Add variables for Keppel users/managers */
    protected $isRFQDeadlineMgr;
    protected $isRFQDeadlineAllowed;

    public $isPagesUser;

    protected $roles;

    public function __construct($data) {
			$this->isRFQDeadlineMgr = false;
			$this->isRFQDeadlineAllowed = false;
			$user = Shipserv_User::isLoggedIn();
			$shipservUser = ($user) ? $user->isShipservUser() : false;
			
			if ($shipservUser) {
				// SPC-2498 ShipMate should see deadline control like a customer
				$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
				if ($sessionActiveCompany->type === 'b') {
					$deadlineStatus = $this->hasByoDeadEnabled((int)$sessionActiveCompany->id);
					$this->isRFQDeadlineMgr = $deadlineStatus['isRFQDeadlineMgr'];
					$this->isRFQDeadlineAllowed = $deadlineStatus['isRFQDeadlineAllowed'];
				}
			} else {
				$this->isRFQDeadlineMgr = (isset($data['RFQDEADLINEMGR'])) ? (bool)$data['RFQDEADLINEMGR'] : false;
				$this->isRFQDeadlineAllowed = (isset($data['RFQDEADLINEALLOWED'])) ? (bool)$data['RFQDEADLINEALLOWED'] : false;
			}

			$this->isMtmlBuyer = (isset($data['MTMLBUYER'])) ? $data['MTMLBUYER'] === 'Y' : false;
			foreach((array)$data as $key => $value) {
				/* S16126 */
				$this->{strtolower($key)} = $value;
			}
			$this->isAdmin = ($this->usertype == 'A' || $this->usertype == 'S');

    }

	public function __get ($property)
	{
		return $this->{$property};
	}

	public function __set ($key, $value)
	{
		$this->{$key} = $value;
	}

	
	/**
	 * !!!!!DEPRECATED!!!!!! Use direclty Shipserv_User::canAccessFeature(Shipserv_User::BRANCH_FILTER_TXNMON) instead. 
	 * I kept it here cause the replacement in txnmon would be a bit dangerous and needs careful (time consuming) refactoring
	 */
	public function canAccessTransactionMonitor($byoOrgCode = null, $bybBranchCode = null)
	{

		if ($this->isShipservUser() === true)
		{
			return true;
		}

		//if ($byoOrgCode == null && $bybBranchCode == null) {

			$sql = "
			SELECT
			  COUNT(*) match_count
			FROM
			  pages_user_company
			WHERE
			  puc_psu_id = :userId
			  and puc_company_type = 'BYB'
	  		";

	  		if ($this->getDb()->fetchOne($sql, array('userId' => $this->usercode)) == 0)
	  		{
	  			return true;
	  		}
  		//}

		$innerSql = '';
		$params = array('userId' => $this->username, 'userCode' => $this->usercode);

		if( $byoOrgCode != null )
		{
			$innerSql = '
				AND puc_company_id IN (
					SELECT byb_branch_code FROM buyer_branch
					WHERE byb_byo_org_code=:orgCode
				)
			';
			$params['orgCode'] = $byoOrgCode;
		}

		if( $bybBranchCode != null )
		{
			$sql .= '
				AND puc_company_id=:bybBranchCode
			';
			$params['bybBranchCode'] = $bybBranchCode;
		}


		$sql = "
SELECT
  COUNT(*)
FROM
(
	SELECT
	  1
	FROM
	  weblogic.user_group_hierarchy JOIN weblogic.user_security
	    ON (weblogic.user_group_hierarchy.user_id=weblogic.user_security.user_id)
	    JOIN users ON (weblogic.user_security.user_name=users.usr_name)
	WHERE
	  weblogic.user_security.user_name = :userId
	  AND (weblogic.user_group_hierarchy.group_id=133 OR weblogic.user_group_hierarchy.group_id=118)
	  AND usr_type!='BR'
  UNION ALL
  SELECT 1 FROM
    pages_user_company
  WHERE
    puc_company_type='BYB'
    AND puc_txnmon=1
    AND puc_psu_id=:userCode
	AND puc_status!='DEL'
	" . $innerSql . "
)
		";

		$db = $this->getDb();
		$found = $db->fetchOne($sql, $params);
		return ( $found > 0 );
	}


	/**
	* Check if the tradenet user can access the Approved Supplier page
	* @param integer $byoOrgCode
	* @param integer $bybBranchCode
	* @return boolean true or false
	*/
	public function canAccessApprovedSupplier($byoOrgCode = null, $bybBranchCode = null)
	{
		//S16401 Refactored to use admin level access check Attila O
		//Keeping tbe default parameters for backward compatibility
		if ($this->isShipservUser() || $this->isShipservUserAdmin()) {
			return true;
		}
	}

	/**
	* Check if the tradenet user can access the Spend Management
	* @param integer $byoOrgCode
	* @param integer $bybBranchCode
	* @return boolean true or false
	*/
	public function canAccessSpendManagement($byoOrgCode = null, $bybBranchCode = null)
	{
		if ($this->isShipservUser() || $this->isShipservUserAdmin()) {
			return true;
		}
	}

	/**
	* Check if the tradenet user can access the Transaction Monitor Admin 
	* @param integer $byoOrgCode
	* @param integer $bybBranchCode
	* @return boolean true or false
	*/
	public function canAccessTransactionMonitorAdm($byoOrgCode = null, $bybBranchCode = null)
	{

		if ($this->isShipservUser()) {
			return true;
		}

		//if ($byoOrgCode == null && $bybBranchCode == null) {

			$sql = "
			SELECT
			  COUNT(*) match_count
			FROM
			  pages_user_company
			WHERE
			  puc_psu_id = :userId
			  and puc_company_type = 'BYB'
	  		";
	  		//TODO check where usercode comes from
	  		if ($this->getDb()->fetchOne($sql, array('userId' => $this->usercode)) == 0)
	  		{
	  			return true;
	  		}
  		//}

		$sql = "
			SELECT
				COUNT(*)
			FROM
			  users JOIN pages_user ON (psu_id=usr_user_code)
			    JOIN pages_user_company ON (puc_psu_id=psu_id)
			WHERE
			  psu_id=:userId
			  AND puc_company_type='BYB'
			  AND puc_status='ACT'
			  AND puc_txnmon_adm=1
		";

		$params = array('userId' => $this->usercode);

		if( $byoOrgCode != null )
		{
			$sql .= '
				AND puc_company_id IN (
					SELECT byb_branch_code FROM buyer_branch
					WHERE byb_byo_org_code=:orgCode
				)
			';
			$params['orgCode'] = $byoOrgCode;
		}

		if( $bybBranchCode != null )
		{
			$sql .= '
				AND puc_company_id=:bybBranchCode
			';
			$params['bybBranchCode'] = $bybBranchCode;
		}

		$db = $this->getDb();
		$found = $db->fetchOne($sql, $params);
		return ( $found > 0 );

	}


	/**
	* Check if spend management can be accessed
	* @return boolean true or false
	*/
	public function canAccessSpenManagement()
	{
		if ($this->isShipservUser() || $this->isShipservUserAdmin()) {
			return true;
		}
	}

	/**
	* Check if we are logged in on pages, and the logged in user is shipserv user
	*/
	public function isShipservUser()
	{

		$user = Shipserv_User::isLoggedIn();
    	if (is_object($user)) {
    		 if ( $user->isShipservUser() === true) {
    		 	return true;
    		 }
    	}

    	return false;

	}
	 /**
	 * Check if shipserv user is isAdmin 
	 */
	 public function isShipservUserAdmin()
	 {

		$user = Shipserv_User::isLoggedIn();
    	if (is_object($user)) {
			 $sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
    		 if ( $user->isAdminOf( (int)$sessionActiveCompany->id ) === true) {
    		 	return true;
    		 }
    	}

    	return false;

	 }

	 /**
		* If we logged in as ShipMate we have to check if the BYO has any user assigned as deadline manager 
		* and allowed (SPC-2498)
	  */
	 protected function hasByoDeadEnabled($byo)
	 {
		// I would say that we also shoud check agains puc table if the user is active and assigned to this byo, but does not work that way
		$return = array(
			'isRFQDeadlineMgr' => false,
			'isRFQDeadlineAllowed' => false
		);
		 $sql = "
			SELECT
				NVL(MAX(bbu.bbu_rfq_deadline_mgr), 0) AS rfqdeadlinemgr,
				NVL(MAX(ccf.ccf_rfq_deadline_control), 0) AS rfqdeadlineallowed
			FROM 
			users usr
				left join buyer_branch_user bbu
					on usr.usr_user_code = bbu.bbu_usr_user_code
				left join buyer_branch byb
					on bbu.bbu_byb_branch_code = byb.byb_branch_code
				left join customer_config ccf
					on bbu.bbu_byb_branch_code = ccf.ccf_branch_code
			WHERE
				bbu.bbu_byo_org_code = :byo";
		
			$params = array(
				'byo' => $byo
			);
			$db = $this->getDb();
			$result = $db->fetchAll($sql, $params);
			if (count($result) > 0) {
				$return = array(
					'isRFQDeadlineMgr' => (bool)$result[0]['RFQDEADLINEMGR'],
					'isRFQDeadlineAllowed' => (bool)$result[0]['RFQDEADLINEALLOWED']
				);
			}

			return $return;
		 }
}
