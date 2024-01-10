<?php
/**
 * Pages user company related Queries
 * 
 * A special grouped cache is implemented per user.
 * Note: This is done this way as memcached has limited functionality related key manipulation
 * Redis would be better and a betted solution could be done.
 * 
 */

class Shipserv_Oracle_PagesUserCompany extends Shipserv_Oracle
{
	// Values for $companyType method parameter
	const COMP_TYPE_SPB = Myshipserv_UserCompany_Company::TYPE_SPB;
	const COMP_TYPE_BYO = Myshipserv_UserCompany_Company::TYPE_BYO;
	const COMP_TYPE_BYB = Myshipserv_UserCompany_Company::TYPE_BYB;
    const COMP_TYPE_CON = Myshipserv_UserCompany_Company::TYPE_CON;
	
	// Values for $level method parameter
	const LEVEL_USER = Myshipserv_UserCompany_Company::LEVEL_USER;
	const LEVEL_ADMIN = Myshipserv_UserCompany_Company::LEVEL_ADMIN;
	
	// Values for $status method parameter
	const STATUS_ACTIVE = Myshipserv_UserCompany_Company::STATUS_ACTIVE;
	const STATUS_INACTIVE = Myshipserv_UserCompany_Company::STATUS_INACTIVE;
	const STATUS_DELETED = Myshipserv_UserCompany_Company::STATUS_DELETED;
	const STATUS_PENDING = Myshipserv_UserCompany_Company::STATUS_PENDING;
	
	private $joinReqDao;
	private static $instance;
	
	
	public function __construct ($db = null)
	{
		if ($db == null) {
			parent::__construct(self::getDb());	
		} else {
			parent::__construct($db);
		}
		$this->joinReqDao = new Shipserv_Oracle_UserCompanyRequest($db);
	}
	
	
	/**
	 * Singleton function
	 * 
	 * @param String $db  Db connection string. Null is the default, and that will make the code to use default db connection 
	 * @return Shipserv_Oracle_PagesUserCompany
	 */
	public static function getInstance($db = null)
	{
        if (null === static::$instance) {
            static::$instance = new static($db);
        }
        return static::$instance;
	}
	
	
	/**
	 * Sets membership level for user in specified company.
	 * 
	 * @return void
	 * @exception Exception
	 */
	public function updateLevel($userId, $companyType, $companyId, $level)
	{
		// Note: status is ignored, it's just there to make the validation work
		$params = $this->sanitizeParams($userId, $companyType, $companyId, $level, self::STATUS_ACTIVE);
		
		// Remove redundant status as it causes an exception
		unset($params['PUC_STATUS']);
		unset($params['PUC_TXNMON']);
		unset($params['PUC_WEBREPORTER']);
		unset($params['PUC_MATCH']);
		unset($params['PUC_APPROVED_SUPPLIER']);
		unset($params['PUC_BUY']);
		unset($params['PUC_IS_DEFAULT']);
		unset($params['PUC_TXNMON_ADM']);
		unset($params['PUC_AUTOREMINDER']);
		
		$sql =			
			"UPDATE PAGES_USER_COMPANY SET PUC_LEVEL = :PUC_LEVEL, PUC_DATA_OWNER = 'PGS'
				WHERE PUC_PSU_ID = :PUC_PSU_ID AND PUC_COMPANY_TYPE = :PUC_COMPANY_TYPE AND PUC_COMPANY_ID = :PUC_COMPANY_ID";

		$stmt = $this->db->query($sql, $params);
		
		if ($stmt->rowCount() != 1) {
			$this->insertUserCompany($userId, $companyType, $companyId, $level, self::STATUS_ACTIVE, false);
		}
	}
	
	/**
	 * Sets membership status for user in specified company.
	 * 
	 * @return void
	 * @exception Exception
	 */
	public function updateStatus ($userId, $companyType, $companyId, $status)
	{
		// Note: level is ignored, it's just there to make the validation work
		$params = $this->sanitizeParams($userId, $companyType, $companyId, self::LEVEL_USER, $status);
		// Remove redundant status as it causes an exception
		unset($params['PUC_LEVEL']);
		unset($params['PUC_TXNMON']);
		unset($params['PUC_WEBREPORTER']);
		unset($params['PUC_MATCH']);
		unset($params['PUC_APPROVED_SUPPLIER']);
		unset($params['PUC_BUY']);
		unset($params['PUC_IS_DEFAULT']);
		unset($params['PUC_TXNMON_ADM']);
		unset($params['PUC_AUTOREMINDER']);

		
		$sql = 
			"UPDATE PAGES_USER_COMPANY SET PUC_STATUS = :PUC_STATUS, PUC_DATA_OWNER = 'PGS'
				WHERE PUC_PSU_ID = :PUC_PSU_ID AND PUC_COMPANY_TYPE = :PUC_COMPANY_TYPE AND PUC_COMPANY_ID = :PUC_COMPANY_ID";
		
		$stmt = $this->db->query($sql, $params);

		if ($stmt->rowCount() == 0)
		{
			$this->insertUserCompany ($userId, $companyType, $companyId, self::LEVEL_USER, self::STATUS_ACTIVE, false);
		}
	}
	
	/**
	 * Activates membership status for all pending user-company associations
	 * belonging to user.
	 *
	 * - updated 17/02/2011 by DS - removed target level, as this would overwrite
	 * - any updates to the user's admin status if they're made an administrator
	 * - before confirming their email.
	 *
	 * @param int $userId Owner
	 * @param string $status Target status
	 */
	public function activatePendingForUser ($userId)
	{
		// Set all pending user-company rows for user to ACTIVE
		$sql = 
			"UPDATE PAGES_USER_COMPANY SET
                PUC_STATUS = :targetStatus,
                PUC_DATA_OWNER = 'PGS'
			WHERE
				PUC_PSU_ID = :userId
				AND PUC_STATUS = :pendingStatus";
		
		$params = array();
		$params['userId'] = $userId;
		$params['pendingStatus'] = self::STATUS_PENDING;
		$params['targetStatus'] = self::STATUS_ACTIVE;
		
		$stmt = $this->db->query($sql, $params);
		
		// If no rows were updated, return
		if ($stmt->rowCount() == 0)
		{
			return;
		}
		
		// If rows were updated, go on to ensure that there are no outstanding
		// join requests relating to user's newly activated companies.
		
		// Fetch all user's active companies
		$sql = "SELECT * FROM PAGES_USER_COMPANY WHERE PUC_PSU_ID = :userId AND PUC_STATUS = :status";
		
		$params = array();
		$params['userId'] = $userId;
		$params['status'] = self::STATUS_ACTIVE;
		
		// For each active company, close any outstanding join request
		foreach ($this->db->fetchAll($sql, $params) as $r)
		{
			$this->joinReqDao->closePendingRequest($userId, $r['PUC_COMPANY_TYPE'], $r['PUC_COMPANY_ID']);
		}
	}
	

	/**
	 * Inserts row representing membership of user to company. Optionally, may
	 * perform an 'upsert'.
	 *
	 * @param bool $boolUpsert if true, attempt UPDATE if INSERT fails because row already exists
	 * @return void
	 * @exception Exception
	 */
	public function insertUserCompany($userId, $companyType, $companyId, $level, $status, $boolUpsert, $txnMon=0, $webReporter = 0, $match = 0, $buy = 0, $approvedSupplier = 0, $txnMonAdm = 0, $autoReminder = 0, $isDefault = 0)
	{
		$params = $this->sanitizeParams($userId, $companyType, $companyId, $level, $status, $txnMon, $webReporter, $match,  $buy, $approvedSupplier, $isDefault,  $txnMonAdm, $autoReminder);
		// if somebody leaving shipserv, then remove him/her from pages_shipserv_group
		if( $status == Shipserv_Oracle_PagesUserCompany::STATUS_DELETED ) 
		{
			$config  = Shipserv_Object::getConfig();
			if( $companyId == $config->shipserv->company->tnid )
			{
				$sql = "
					DELETE FROM pages_user_group WHERE pug_psu_id=:PUC_PSU_ID		
				";	
				$this->db->query($sql, array('PUC_PSU_ID' => $userId));
				$this->db->commit();
			}
				
		}
		// if somebody leaving shipserv, then remove him/her from pages_shipserv_group
		if( $status == Shipserv_Oracle_PagesUserCompany::STATUS_DELETED ) 
		{
			$config  = Shipserv_Object::getConfig();
			if( $companyId == $config->shipserv->company->tnid )
			{
				$sql = "
					DELETE FROM pages_user_group WHERE pug_psu_id=:PUC_PSU_ID		
				";	
				$this->db->query($sql, array('PUC_PSU_ID' => $userId));
				$this->db->commit();
			}
				
		}
		// if somebody leaving shipserv, then remove him/her from pages_shipserv_group
		if( $status == Shipserv_Oracle_PagesUserCompany::STATUS_DELETED ) 
		{
			$config  = Shipserv_Object::getConfig();
			if( $companyId == $config->shipserv->company->tnid )
			{
				$sql = "
					DELETE FROM pages_user_group WHERE pug_psu_id=:PUC_PSU_ID		
				";	
				$this->db->query($sql, array('PUC_PSU_ID' => $userId));
				$this->db->commit();
			}
				
		}
		// if somebody leaving shipserv, then remove him/her from pages_shipserv_group
		if( $status == Shipserv_Oracle_PagesUserCompany::STATUS_DELETED ) 
		{
			$config  = Shipserv_Object::getConfig();
			if( $companyId == $config->shipserv->company->tnid )
			{
				$sql = "
					DELETE FROM pages_user_group WHERE pug_psu_id=:PUC_PSU_ID		
				";	
				$this->db->query($sql, array('PUC_PSU_ID' => $userId));
				$this->db->commit();
			}
				
		}
		
		try
		{
			// Try to insert row
			$insertSql = 
				"INSERT INTO PAGES_USER_COMPANY
					(PUC_PSU_ID, PUC_COMPANY_TYPE, PUC_COMPANY_ID, PUC_LEVEL, PUC_STATUS, PUC_DATA_OWNER, PUC_TXNMON, PUC_WEBREPORTER, PUC_MATCH, PUC_BUY, PUC_APPROVED_SUPPLIER, PUC_IS_DEFAULT,PUC_TXNMON_ADM,PUC_AUTOREMINDER)
					VALUES (:PUC_PSU_ID, :PUC_COMPANY_TYPE, :PUC_COMPANY_ID, :PUC_LEVEL, :PUC_STATUS, 'PGS', :PUC_TXNMON, :PUC_WEBREPORTER, :PUC_MATCH, :PUC_BUY, :PUC_APPROVED_SUPPLIER, :PUC_IS_DEFAULT,:PUC_TXNMON_ADM, :PUC_AUTOREMINDER)";
			$this->db->query($insertSql, $params);
		}
		catch (Zend_Db_Statement_Oracle_Exception $e)
		{
			// If this is an upsert, now try to UPDATE
			if ($boolUpsert)
			{
				// Intercept 'ORA-00001: unique constraint violated'
				if ($e->getCode() == 1)
				{

					$updateSql = "
						UPDATE PAGES_USER_COMPANY 
							SET 
								PUC_LEVEL = :PUC_LEVEL
								, PUC_STATUS = :PUC_STATUS
								, PUC_DATA_OWNER = 'PGS'
								, PUC_TXNMON = :PUC_TXNMON
								, PUC_WEBREPORTER = :PUC_WEBREPORTER
								, PUC_MATCH = :PUC_MATCH
								, PUC_BUY = :PUC_BUY
								, PUC_APPROVED_SUPPLIER = :PUC_APPROVED_SUPPLIER
					            , PUC_IS_DEFAULT = :PUC_IS_DEFAULT
					            , PUC_TXNMON_ADM = :PUC_TXNMON_ADM
					            , PUC_AUTOREMINDER = :PUC_AUTOREMINDER
							WHERE 
								PUC_PSU_ID = :PUC_PSU_ID 
								AND PUC_COMPANY_TYPE = :PUC_COMPANY_TYPE 
								AND PUC_COMPANY_ID = :PUC_COMPANY_ID
					";
					
					$updateStmt = $this->db->query($updateSql, $params);
					
					if ($updateStmt->rowCount() != 0)
					{
						// Success
						return;
					}
					else
					{
						// If row was not updated, then we have an unexplained fail
						throw new Exception("Failed to insert or update row");
					}
				}
			}
			
			// Throw on (unless return'ed above)
			throw $e;
		}
	}
	
	
	public function makeDefault($userId, $companyType, $companyId, $isDefault)
	{
		$sql = "
			UPDATE 
				pages_user_company
			SET 
				puc_is_default=null
			WHERE
				puc_company_type='BYB'
				AND puc_psu_id=:userId
				AND puc_company_id IN (
					SELECT 
					  byb_branch_code
					FROM
					  buyer_branch
					WHERE
 					  byb_byo_org_code=(
						SELECT byb_byo_org_code FROM buyer_branch WHERE byb_branch_code=:companyId
					  )
				)
		";
		$updateStmt = $this->db->query($sql, array('userId' => $userId, 'companyId' => $companyId));
		
		$sql = "
			UPDATE
				pages_user_company
			SET
				puc_is_default=1
			WHERE
				puc_company_type='BYB'
				AND puc_psu_id=:userId
				AND puc_company_id=:companyId
		";
		$updateStmt = $this->db->query($sql, array('userId' => $userId, 'companyId' => $companyId));
		
	}
	
	/**
	 * Fetch all users for specified company, irrespective of status, level etc.
	 *
     * @param string $companyType
     * @param integer $companyId
     * @param boolean $forceReread // not used anymore
     *
	 * @return array indexed array of rows as associative arrays
	 * @exception Exception
	 */
	public function fetchUsersForCompany($companyType, $companyId, $forceReread = false)
	{
		$params = array('PUC_COMPANY_TYPE' => $companyType, 'PUC_COMPANY_ID' => $companyId);
		$sql = "SELECT * FROM PAGES_USER_COMPANY WHERE PUC_COMPANY_TYPE = :PUC_COMPANY_TYPE AND PUC_COMPANY_ID = :PUC_COMPANY_ID";

		return $this->fetchUserCachedQuery($sql, $params);
	}
	
	
	/**
	 * Get the pages_user_company entries for this user
	 *  
	 * @param Int $userId
	 * @param Int $orgId = null
	 * @param Bool $excludeIna = false
	 * @param Bool $cached = false
	 * @return Aray
	 */
	public function fetchCompaniesForUser($userId, $orgId = null, $excludeIna = false, $cached = false)
	{

		$byoPlaceholder1 = $byoPlaceholder2 = $excludeInaPlaceholder = '';
	    $params = array('userId' => $userId);
	    if ($orgId) {
	        $byoPlaceholder1 = 'AND (byb_byo_org_code = :companyId OR pbn_norm_byo_org_code = :companyId)';
	        $byoPlaceholder2 = 'AND puc_company_id = :companyId ';
	        $params['companyId'] = (Int) $orgId;
	    }
	    if ($excludeIna) {
	        $excludeInaPlaceholder = " AND byb_sts <> 'INA' ";
	    }
	    
        $sql = "
            SELECT
              DISTINCT(puc_company_id || puc_company_type) AS uniqueid, 
              PAGES_USER_COMPANY.*, 
              BYB_NAME, 
              BYB_STS, 
              BYB_BYO_ORG_CODE, 
              CCF_RFQ_DEADLINE_CONTROL 
            FROM
              pages_user_company 
              JOIN buyer_branch ON puc_company_id=byb_branch_code
              LEFT JOIN pages_byo_norm ON byb_byo_org_code = pbn_byo_org_code 
              LEFT JOIN CUSTOMER_CONFIG ON ccf_branch_code=puc_company_id
            WHERE 
              puc_company_type='BYB' 
              AND puc_psu_id = :userId
              $excludeInaPlaceholder
            START WITH puc_psu_id = :userId $byoPlaceholder1
            CONNECT BY NOCYCLE PRIOR byb_branch_code = byb_under_contract
            
            UNION
            
            SELECT
              DISTINCT(puc_company_id || puc_company_type) AS uniqueid, 
              PAGES_USER_COMPANY.*, 
              '' AS BYB_NAME,
              '' AS BYB_STS, 
              (CASE puc_company_type WHEN 'BYO' THEN puc_company_id ELSE NULL END) AS BYB_BYO_ORG_CODE, 
              NULL AS CCF_RFQ_DEADLINE_CONTROL 
            FROM
              pages_user_company 
            WHERE 
              puc_company_type IN ('BYO', 'SPB', 'CON') 
              AND puc_psu_id = :userId
              $byoPlaceholder2
        ";
			  
		return $this->fetchUserCachedQuery($sql, $params);
     
	}
	
	
	/**
	 * @return array of sanitized parameters
	 * @exception Exception on validation fail
	 */
	private function sanitizeParams($userId, $companyType, $companyId, $level, $status, $txnMon = 0, $webReporter = 0, $match = 0, $buy = 0, $approvedSupplier = 0, $isDeafult = 0,  $txnMonAdm = 0, $autoReminder = 0)
	{
		$resArr = array();
		
		// Turn $userId into an int
		$resArr['PUC_PSU_ID'] = (int) $userId;
		
		// Ensure $companyType is in set of possibles
		$companyType = (string) $companyType;
		if (in_array($companyType, array(self::COMP_TYPE_SPB, self::COMP_TYPE_BYO, self::COMP_TYPE_BYB)))
		{
			$resArr['PUC_COMPANY_TYPE'] = $companyType;
		}
		else
		{
			throw new Exception("Unrecognised company type: $companyType");
		}
		
		// Turn $companyId into an int
		$resArr['PUC_COMPANY_ID'] = (int) $companyId;
		
		// Ensure $level is in set of possibles
		$level = (string) $level;
		if (in_array($level, array(self::LEVEL_USER, self::LEVEL_ADMIN)))
		{
			$resArr['PUC_LEVEL'] = $level;
		}
		else
		{
			throw new Exception("Unrecognised membership level: $level");
		}
		
		// Turn $boolActive into string bool
		$status = (string) $status;
		if (in_array($status, array(self::STATUS_ACTIVE, self::STATUS_INACTIVE, self::STATUS_DELETED, self::STATUS_PENDING)))
		{
			$resArr['PUC_STATUS'] = $status;
		}
		else
		{
			throw new Exception("Unrecognised membership status: $status");
		}
		
		$resArr['PUC_TXNMON'] = $txnMon;
		$resArr['PUC_WEBREPORTER'] = $webReporter;
		$resArr['PUC_MATCH'] = $match;
		$resArr['PUC_BUY'] = $buy;
		$resArr['PUC_APPROVED_SUPPLIER'] = $approvedSupplier;
		$resArr['PUC_IS_DEFAULT'] = $isDeafult;
		$resArr['PUC_TXNMON_ADM'] = $txnMonAdm;
		$resArr['PUC_AUTOREMINDER'] = $autoReminder;

		return $resArr;
	}
	
	/**
	 * Flush user grouped cache
	 * Should be called after login
	 */
	public function flushUserCache()
	{
		$key = $this->getUserKey();
		$memcached = $this->getMemcache();
		
		if ($memcached) {
			return $memcached->delete($key, 0);
		}
		
	}
	/**
	 * Genereate a key for each user
	 * 
	 * @return string
	 */
	protected function getUserKey()
	{
		$key = null;
		if ($user = Shipserv_User::isLoggedIn()) {
			$key = 'PUC_CACHE:' . $user->userId;
		}

		return $key;
	}

	/**
	 * Getting a value from the grouped user cache
	 * 
	 * @param string $userKey
	 * 
	 * @return any
	 */
	protected function getUserCace($userKey)
	{
		$result = null;
		$key = $this->getUserKey();

		if ($key) {
			$cacheData = $this->fetchFromMemcache($key);

			if ($cacheData !== null) {
				if ($cacheData !== false && isset($cacheData[$userKey])) {
					$result = $cacheData[$userKey];
				}
			}
		}

		return $result;
	}

	/**
	 * Store a data in the user grouped cacke 
	 * 
	 * @param string $userKey 
	 * @param any $data
	 * 
	 * @return null
	 */
	protected function setUserCache($userKey, $data)
	{
		$key = $this->getUserKey();

		if ($key) {
			$cacheData = $this->fetchFromMemcache($key);
			if ($cacheData !== null) {
				if ($cacheData === false) {
					$cacheData = [];
				}
			
				$cacheData[$userKey] = $data;
				$this->saveToMemcache($key, $cacheData);
			}
		}
	}

	/**
	 * Save to memcache
	 * 
	 * @param string $key
	 * @param any $value
	 * 
	 * @return null
	 */
	private function saveToMemcache($key, $value)
	{
		$memcached = $this->getMemcache();

		if ($memcached) {
			$memcached->set($key, $value, false, Shipserv_Memcache::MEMCACHE_TTL);
		}
	}
	
	/**
	 * Read from cache
	 * 
	 * @param string $key
	 * 
	 * @return mixed or false if item not in cache.
	 */
	private function fetchFromMemcache($key)
	{
		$memcached = $this->getMemcache();
		return $memcached ? $memcached->get($key) : null;
	}

	/**
	 * Execute orecle query if not cached in user grouped key
	 * 
	 * @param string $sql
	 * @param array @parmas
	 * 
	 * @return array
	 */
	private function fetchUserCachedQuery($sql, $params)
	{
		$res = null;
		$cacheKey = md5($sql. '_' . serialize($params));

		$res = $this->getUserCace($cacheKey);
		if ($res === null || $res === false) {
			$res = $this->fetchQuery($sql, $params);
			$this->setUserCache($cacheKey, $res);
		}

		return $res;
	}
}
