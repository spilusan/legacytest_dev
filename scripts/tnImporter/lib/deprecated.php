<?php

/**
 * Main entry point for TradeNet Importer daemon.
 */
class Tni_Importer
{	
	public function run ()
	{
		Tni_Logger::log("Start importing users");
		$this->importUsers();
		
		Tni_Logger::log("Start importing user-company associations");
		$this->importUserCompanyAssocs();
	}
	
	private function importUsers ()
	{
		// Iterate on importable e-mails
		$it = new Tni_TnEmailIterator();
		$im = new Tni_UserImporter();
		$cnt = 0;
		$tStart = microtime(true);
		while (true)
		{
			// Read next e-mail, or break if no more
			$eml = $it->next();
			if ($eml === null)
			{
				break;
			}
			
			// Import e-mail
			$im->import($eml);
			
			$cnt++;
			if (($cnt % 100) == 0)
			{
				$tElapsed = round(microtime(true) - $tStart, 1);
				Tni_Logger::log("Progress report: user import completed for $cnt TN e-mails in {$tElapsed}s");
			}
		}
	}
	
	private function importUserCompanyAssocs ()
	{
		$it = new Tni_PuIterator();
		$im = new Tni_CompanyImporter();
		$cnt = 0;
		$tStart = microtime(true);
		while (true)
		{
			// Read next e-mail, or break if no more
			$usr = $it->next();
			if ($usr === null)
			{
				break;
			}
			
			// Import e-mail
			$im->import($usr['PSU_ID'], $usr['PSU_EMAIL']);
			
			$cnt++;
			if (($cnt % 100) == 0)
			{
				$tElapsed = round(microtime(true) - $tStart, 1);
				Tni_Logger::log("Progress report: user-company associations processed for $cnt Pages users in {$tElapsed}s");
			}
		}
	}
}

/**
 * Iterator over TN e-mails to be imported as Pages users.
 *
 * Not all e-mails returned may be suitable for import. This iterator is designed
 * to yield a sensibly-sized and easily-available superset.
 */
class Tni_TnEmailIterator
{
	private $qRes = null;
	
	/**
	 * Fetch next TN e-mail to be imported as Pages user. Returned e-mails are
	 * trimmed and lower case.
	 * 
	 * @return string, or null if no more.
	 */
	public function next ()
	{
		// Run query if it has not been run
		if ($this->qRes === null)
		{
			$this->qRes = $this->getDb()->query($this->getSql2());
		}
		
		// Loop until we hit a good row
		while (true)
		{
			// Read row and test for end of result set
			$row = $this->qRes->fetch(Zend_Db::FETCH_NUM);
			if (!is_array($row))
			{
				break;
			}
			
			// Test e-mail well-formed
			if (Tni_Utils::isEmail($row[0]))
			{
				// Test not a Shipserv e-mail
				if (strpos($row[0], '@shipserv.com') === false)
				{
					// Return e-mail
					return $row[0];
				}
			}
		}
	}
	
	/**
	 * Return SQL to fetch all e-mails from TN to be imported as Pages users.
	 *
	 * @deprecated Can improve efficiency by fetching a more limited set.
	 */
	private function getSql ()
	{
		$sql =
			
			"SELECT * FROM (
				
				-- Distinct, normalised e-mails
				SELECT DISTINCT LOWER(TRIM(EMAIL)) EMAIL FROM (
					
					-- Supplier e-mails
					SELECT SBU_EMAIL_ADDRESS AS EMAIL FROM SUPPLIER_BRANCH_USER
					UNION ALL
					SELECT SPB_REGISTRANT_EMAIL_ADDRESS FROM SUPPLIER_BRANCH
					UNION ALL
					SELECT SPB_EMAIL FROM SUPPLIER_BRANCH
					UNION ALL
					SELECT PUBLIC_CONTACT_EMAIL FROM SUPPLIER_BRANCH
					UNION ALL
					SELECT ELECTRONIC_MAIL FROM CONTACT_PERSON
					
					-- Buyer e-mails
					UNION ALL			
					SELECT BBU_EMAIL_ADDRESS AS EMAIL FROM BUYER_BRANCH_USER
					UNION ALL
					SELECT BYB_EMAIL_ADDRESS FROM BUYER_BRANCH
					UNION ALL
					SELECT BYB_REGISTRANT_EMAIL_ADDRESS FROM BUYER_BRANCH
					UNION ALL
					SELECT BYO_CONTACT_EMAIL FROM BUYER_ORGANISATION
				)
			) a
			
			WHERE
				
				-- Remove obvious junk (not all the junk!)
				LENGTH(a.EMAIL) > 6
				
				-- Remove e-mails that are already Pages Users
				-- Note: TRIM() deliberately not used to ensure function index exploited
				AND NOT EXISTS (SELECT * FROM PAGES_USER WHERE LOWER(PSU_EMAIL) = a.EMAIL)
				
				-- Remove e-mails that have no active buyer/supplier branch users
				AND
				(
					EXISTS
					(
						SELECT * FROM SUPPLIER_BRANCH_USER WHERE LOWER(TRIM(SBU_EMAIL_ADDRESS)) = a.EMAIL AND SBU_STS = 'ACT'
					)
					OR
					EXISTS
					(
						SELECT * FROM BUYER_BRANCH_USER WHERE LOWER(TRIM(BBU_EMAIL_ADDRESS)) = a.EMAIL AND BBU_STS = 'ACT'
					)
				)";
		
		return $sql;
	}
	
	/**
	 * Return SQL to fetch all e-mails from TN to be imported as Pages users.
	 *
	 * This implementation only fetches e-mails belonging to active
	 * buyer/supplier branch users. It removes some junk e-mails but further
	 * junk filtering in code is necessary.
	 *
	 * E-mails already belonging to Pages Users are removed.
	 *
	 * Note that not all e-mails returned are suitable for import. This SQL is
	 * designed to get a sensibly-sized and easily-available superset.
	 * 
	 * @return string
	 */
	private function getSql2 ()
	{
		$sql =
			"SELECT EMAIL FROM
			(
				-- Normalise e-mails and remove duplicates
				SELECT DISTINCT LOWER(TRIM(EMAIL)) EMAIL FROM
				(
					-- All e-mails for active supplier users
					SELECT SBU_EMAIL_ADDRESS AS EMAIL FROM SUPPLIER_BRANCH_USER
						WHERE
							-- Supplier user must be active
							SBU_STS = 'ACT'
							
							-- Supplier user must be tied to an active supplier branch
							AND EXISTS (SELECT * FROM SUPPLIER_BRANCH WHERE SPB_BRANCH_CODE = SBU_SPB_BRANCH_CODE AND SPB_STS = 'ACT')
							
							-- Supplier user must be tied to an active USER of correct type
							AND EXISTS (SELECT * FROM USERS WHERE USR_USER_CODE = SBU_USR_USER_CODE AND USR_TYPE = 'V' AND (USR_STS = 'ACT' OR USR_STS IS NULL))
							
					-- All e-mails for active buyer users
					UNION ALL
					SELECT BBU_EMAIL_ADDRESS FROM BUYER_BRANCH_USER
						WHERE
							-- Buyer user must be active
							BBU_STS = 'ACT'
							
							-- Buyer user must be tied to an active buyer branch
							AND EXISTS (SELECT * FROM BUYER_BRANCH WHERE BYB_BRANCH_CODE = BBU_BYB_BRANCH_CODE AND BYB_STS = 'ACT')
							
							-- Buyer user must be tied to an active USER of correct type
							AND EXISTS (SELECT * FROM USERS WHERE USR_USER_CODE = BBU_USR_USER_CODE AND USR_TYPE = 'B' AND (USR_STS = 'ACT' OR USR_STS IS NULL))
				)
			)
			WHERE
				-- Remove obvious junk (not all junk!)
				LENGTH(EMAIL) > 6
			
			-- Remove e-mails which already belong to Pages Users
			MINUS
			SELECT LOWER(PSU_EMAIL) FROM PAGES_USER";
		
		return $sql;
	}
	
	private function getDb ()
	{
		return Tni_Utils::getDb();
	}
}

/**
 * Iterator over Pages Users for whom company associations are to be imported from
 * TN.
 */
class Tni_PuIterator
{
	private $qRes;
	
	/**
	 * Fetch next TN e-mail.
	 * 
	 * @return string
	 */
	public function next ()
	{
		// Run query if it has not been run
		if ($this->qRes === null)
		{
			$sql =
				"SELECT b.PSU_ID, LOWER(TRIM(b.PSU_EMAIL)) AS PSU_EMAIL FROM USERS a
					INNER JOIN PAGES_USER b ON a.USR_USER_CODE = b.PSU_ID
					WHERE
						a.USR_TYPE = 'P'
						AND (a.USR_STS = 'ACT' or a.USR_STS IS NULL)
						AND b.PSU_EMAIL_CONFIRMED = 'Y'
						
						-- Optimisation: stops imports for any User who already has 1 user-company association in Pages
						AND NOT EXISTS (SELECT * FROM PAGES_USER_COMPANY WHERE PUC_PSU_ID = a.USR_USER_CODE)";
			
			$this->qRes = Tni_Utils::getDb()->query($sql);
		}
		
		// Read row and test for end of result set
		$row = $this->qRes->fetch(Zend_Db::FETCH_ASSOC);
		if (is_array($row))
		{
			return $row;
		}
	}
}

class Tni_UserImporter
{
	private $uDao;
	
	public function __construct ()
	{
		$this->uDao = new Shipserv_Oracle_User(Tni_Utils::getDb());
	}
	
	/**
	 * Creates a Pages User (and User row) for given e-mail, importing existing
	 * user details from TradeNet if possible.
	 *
	 * If an active TradeNet buyer/supplier user exists, name and company name
	 * are copied across. Otherwise, a blank user is created.
	 */
	public function import ($email)
	{
		// Standardize email
		$email = strtolower(trim($email));
		
		// Fetch active supplier & buyer users
		$suArr = $this->fetchSupplierUser($email);
		$buArr = $this->fetchBuyerUser($email);
		
		try
		{		
			// Just 1 supplier user ...
			if (count($suArr) == 1 && !$buArr)
			{
				// Just 1 supplier user
				$su = $suArr[0];
				$this->createUser($email, $su['SBU_FIRST_NAME'], $su['SBU_LAST_NAME'], $su['SPB_NAME']);
				
				Tni_Logger::log("$email: imported TN supplier user");
			}
			
			// Just 1 buyer user ...
			elseif (count($buArr) == 1 && !$suArr)
			{
				// Just 1 buyer user
				$bu = $buArr[0];
				$this->createUser($email, $bu['BBU_FIRST_NAME'], $bu['BBU_LAST_NAME'], $bu['BYB_NAME']);
				
				Tni_Logger::log("$email: imported TN buyer user");
			}
			
			// Multiple buyer/supplier users, or no users ...
			else
			{
				if ($suArr || $buArr)
				{
					Tni_Logger::log("$email: multiple active TN users, skipping");
				}
				else
				{
					Tni_Logger::log("$email: no active TN users, skipping");
				}
			}
		}
		catch (Shipserv_Oracle_User_Exception_FailCreateUser $e)
		{
			// Log fail - user already exists
			if ($e->getCode() == Shipserv_Oracle_User_Exception_FailCreateUser::CODE_DUPLICATE)
			{
				Tni_Logger::log("$email: pages user already exists, skipping");
			}
			
			// Log fail - bad e-mail provided
			elseif ($e->getCode() == Shipserv_Oracle_User_Exception_FailCreateUser::CODE_BAD_EMAIL)
			{
				Tni_Logger::log("$email: malformed e-mail, skipping");
			}
			
			// If not trapped, throw on
			else
			{
				throw $e;
			}
		}
	}
	
	private function getDb ()
	{
		return Tni_Utils::getDb();
	}
	
	private function createUser ($email, $firstName, $lastName, $companyName)
	{
		$this->uDao->createUser($email, $firstName, $lastName, null, $companyName, 'Y');
	}
	
	/**
	 * Fetch active supplier users by e-mail.
	 * 
	 * @return array
	 */
	private function fetchSupplierUser ($email)
	{
		$sql =
			"SELECT *
			
			FROM SUPPLIER_BRANCH_USER SU
				
				-- User must be tied to an active supplier branch
				INNER JOIN SUPPLIER_BRANCH S ON SU.SBU_SPB_BRANCH_CODE = S.SPB_BRANCH_CODE AND S.SPB_STS = 'ACT'
				
				-- User must be tied to an active user of right type in USERS table
				INNER JOIN USERS U ON SU.SBU_USR_USER_CODE = U.USR_USER_CODE AND U.USR_TYPE = 'V' AND (U.USR_STS = 'ACT' OR U.USR_STS IS NULL)
				
			WHERE
				-- Normalise and match e-mail
				LOWER(TRIM(SU.SBU_EMAIL_ADDRESS)) = :email
				
				-- User must be active
				AND SU.SBU_STS = 'ACT'";
		
		$params['email'] = strtolower(trim($email));
		return $this->getDb()->fetchAll($sql, $params, Zend_Db::FETCH_ASSOC);
	}
	
	/**
	 * Fetch active buyer users by e-mail.
	 *
	 * @return array
	 */
	private function fetchBuyerUser ($email)
	{
		$sql =
			"SELECT *
			
			FROM BUYER_BRANCH_USER BU
			
				-- User must be tied to an active buyer branch
				INNER JOIN BUYER_BRANCH B ON BU.BBU_BYB_BRANCH_CODE = B.BYB_BRANCH_CODE AND B.BYB_STS = 'ACT'
				
				-- User must be tied to an active user of right type in USERS table
				INNER JOIN USERS U ON BU.BBU_USR_USER_CODE = U.USR_USER_CODE AND U.USR_TYPE = 'B' AND (U.USR_STS = 'ACT' OR U.USR_STS IS NULL)
				
			WHERE
				-- Normalise and match e-mail
				LOWER(TRIM(BU.BBU_EMAIL_ADDRESS)) = :email
				
				-- User must be active
				AND BU.BBU_STS = 'ACT'";
		
		$params['email'] = strtolower(trim($email));
		return $this->getDb()->fetchAll($sql, $params, Zend_Db::FETCH_ASSOC);
	}
}




// CAUTION:
// I MAY HAVE SCREWED UP BUYER BRANCH VS BUYER ORG IN HERE, SO IF I HAVE TO PUT THIS INTO THE APP, NEEDS CAREFUL CHECKING
class Tni_CompanyImporter
{
	private $uDao;
	private $ucDao;
	
	public function __construct ()
	{
		$this->uDao = new Shipserv_Oracle_User(Tni_Utils::getDb());
		$this->ucDao = new Tni_TnUserCompanyDao();
	}
	
	public function import ($userId, $email)
	{
		// Fetch companies for e-mail
		$companies = $this->ucDao->getCompaniesByEmail($email);
		
		// If there are companies, import them ...
		if ($companies['SPB'] || $companies['BYO'])
		{
			// Import supplier associations
			foreach ($companies['SPB'] as $sId)
			{
				$this->addUserCompany($userId, 'SPB', $sId);
			}
			
			// Import buyer associations
			foreach ($companies['BYO'] as $bId)
			{
				$this->addUserCompany($userId, 'BYO', $bId);
			}
		}
		
		// If there are no companies ...
		else
		{
			Tni_Logger::log("User $userId: no companies found, skipping");
		}
	}
	
	private function addUserCompany ($userId, $companyType, $companyId)
	{
		// Sanitize & validate user ID
		$userId = (int) $userId;
		if ($userId == 0)
		{
			throw new Exception("Bad user ID");
		}
		
		// Validate company type
		if (!in_array($companyType, array('SPB', 'BYO')))
		{
			throw new Exception("Invalid company type: '$companyType'");
		}
		
		// Sanitize & validate company ID
		$companyId = (int) $companyId;
		if ($companyId == 0)
		{
			throw new Exception("Bad company ID");
		}
		
		// Attemp to update existing user-company row,
		// but only if row owner is 'TNI', i.e. TradeNet Importer
		$sql =
			"UPDATE PAGES_USER_COMPANY
				SET
					PUC_LEVEL = 'ADM',
					PUC_STATUS = 'ACT'
				WHERE
					PUC_PSU_ID = :psuId
					AND PUC_COMPANY_TYPE = :companyType
					AND PUC_COMPANY_ID = :companyId
					AND PUC_DATA_OWNER = 'TNI'";
		
		$params = array();
		$params['psuId'] = $userId;
		$params['companyType'] = $companyType;
		$params['companyId'] = $companyId;
		$stmt = Tni_Utils::getDb()->query($sql, $params);
		
		// If this succeeded, job done
		if ($stmt->rowCount() > 0)
		{
			Tni_Logger::log("User $userId, company $companyType $companyId: updated existing user-company row");
			return;
		}
		
		// Either no row exists, or row exists but TNI is not owner.
		// Attempt insert and suppress error if row existed.
		$sql =
			"INSERT INTO PAGES_USER_COMPANY
			(
				PUC_PSU_ID,
				PUC_COMPANY_TYPE,
				PUC_COMPANY_ID,
				PUC_LEVEL,
				PUC_STATUS,
				PUC_DATA_OWNER
			)
			VALUES
			(
				:psuId,
				:companyType,
				:companyId,
				'ADM',
				'ACT',
				'TNI'
			)";
		
		$params = array();
		$params['psuId'] = $userId;
		$params['companyType'] = $companyType;
		$params['companyId'] = $companyId;
		
		try
		{
			// Run query
			Tni_Utils::getDb()->query($sql, $params);
			
			// Log success
			Tni_Logger::log("User $userId, company $companyType $companyId: added new user-company row");
		}
		catch (Zend_Db_Statement_Oracle_Exception $e)
		{
			// Row already existed ...
			if ($e->getCode() == 1)
			{
				Tni_Logger::log("User $userId, company $companyType $companyId: existing user-company row not owned by TNI, skipped");
			}
			
			// If not trapped, throw on
			else
			{
				throw $e;
			}
		}
	}
}

class Tni_TnUserCompanyDao
{
	private static $boolSpbIdxCreated = false;
	
	/**
	 * @return array
	 */
	public function getCompaniesByEmail ($email)
	{
		$sNonCanon = $this->getSupplierIdsByEmail($email);
		if ($sNonCanon)
		{
			$sArr = $this->canonicalizeSupplierIds($sNonCanon);
		}
		else
		{
			$sArr = array();
		}
		
		$bNonCanon = $this->getBuyerIdsByEmail($email);
		if ($bNonCanon)
		{
			$bArr = $this->canonicalizeBuyerIds($bNonCanon);
		}
		else
		{
			$bArr = array();
		}
		
		$res = array();
		$res['SPB'] = $sArr;
		$res['BYO'] = $bArr;
		return $res;
	}
	
	private function createSpbIdx ()
	{
		$sql =
			"INSERT INTO PAGES_SPB_EMAIL_IDX (PSEI_EMAIL, PSEI_SPB_BRANCH_CODE)
				SELECT * FROM
				(
					SELECT DISTINCT LOWER(TRIM(EMAIL)) EMAIL, SPB_BRANCH_CODE FROM
					(
						SELECT SPB_REGISTRANT_EMAIL_ADDRESS AS EMAIL, SPB_BRANCH_CODE FROM SUPPLIER_BRANCH
						UNION ALL SELECT SPB_EMAIL, SPB_BRANCH_CODE FROM SUPPLIER_BRANCH
						UNION ALL SELECT PUBLIC_CONTACT_EMAIL, SPB_BRANCH_CODE FROM SUPPLIER_BRANCH
					)
				) WHERE
					LENGTH(EMAIL) > 6
					AND INSTR(EMAIL, '@') != 0";
		
		Tni_Logger::log("Creating custom index: PAGES_SPB_EMAIL_IDX");
		Tni_Utils::getDb()->query($sql);
	}
	
	/**
	 * Look up suppliers by e-mail across several tables. The IDs returned are
	 * not canonical.
	 * 
	 * Inactive supplier branches may be returned: the intention is that these
	 * will be removed in a separate canonicalisation step.
	 * 
	 * @param string $email
	 * @return array Supplier branch codes
	 */
	private function getSupplierIdsByEmail ($email)
	{
		if (!self::$boolSpbIdxCreated)
		{
			$this->createSpbIdx();
			self::$boolSpbIdxCreated = true;
		}
		
		$sql = 
			"SELECT DISTINCT SPB_BRANCH_CODE FROM
			(
				-- Search supplier users
				SELECT a.SBU_SPB_BRANCH_CODE AS SPB_BRANCH_CODE FROM SUPPLIER_BRANCH_USER a
					WHERE
						LOWER(TRIM(a.SBU_EMAIL_ADDRESS)) = :email
						
						-- Supplier user must be active
						AND a.SBU_STS = 'ACT'
						
						-- Active USER of correct type must exist
						AND EXISTS
						(
							SELECT * FROM USERS WHERE
								USR_USER_CODE = a.SBU_USR_USER_CODE
								AND USR_TYPE = 'V'
								AND (USR_STS = 'ACT' OR USR_STS IS NULL)
						)
				
				-- Search supplier branches
				UNION ALL
				SELECT PSEI_SPB_BRANCH_CODE AS SPB_BRANCH_CODE FROM PAGES_SPB_EMAIL_IDX
					WHERE PSEI_EMAIL = :email
				
				-- Search contacts
				UNION ALL
				SELECT SUPPLIER_BRANCH_CODE FROM CONTACT_PERSON
					WHERE
						LOWER(TRIM(ELECTRONIC_MAIL)) = :email
			)";
		
		$params = array();
		$params['email'] = strtolower(trim($email));
		$rows = Tni_Utils::getDb()->fetchCol($sql, $params);
		return $rows;
	}
	
	/**
	 * Look up buyers by e-mail across several tables. The IDs returned are not
	 * canonical.
	 *
	 * Inactive buyer organisations may be returned: the intention is that these
	 * will be removed in a separate canonicalisation step.
	 * 
	 * @param string $email
	 * @return array Buyer organisation codes
	 */
	private function getBuyerIdsByEmail ($email)
	{
		$sql = 
			"SELECT DISTINCT BYO_ORG_CODE FROM
			(
				SELECT a.BBU_BYO_ORG_CODE AS BYO_ORG_CODE FROM BUYER_BRANCH_USER a
					WHERE
						LOWER(TRIM(a.BBU_EMAIL_ADDRESS)) = :email
						
						-- Buyer user 
						AND a.BBU_STS = 'ACT'
						
						-- Active USER of correct type must exist
						AND EXISTS
						(
							SELECT * FROM USERS WHERE
								USR_USER_CODE = a.BBU_USR_USER_CODE
								AND USR_TYPE = 'B'
								AND (USR_STS = 'ACT' OR USR_STS IS NULL)
						)
						
				UNION ALL
				SELECT BYB_BYO_ORG_CODE FROM BUYER_BRANCH
					WHERE
						(
							LOWER(TRIM(BYB_EMAIL_ADDRESS)) = :email
							OR LOWER(TRIM(BYB_REGISTRANT_EMAIL_ADDRESS)) = :email
						)
				
				UNION ALL
				SELECT BYO_ORG_CODE FROM BUYER_ORGANISATION
					WHERE
						LOWER(TRIM(BYO_CONTACT_EMAIL)) = :email
			)";
		
		$params = array();
		$params['email'] = strtolower(trim($email));
		$rows = Tni_Utils::getDb()->fetchCol($sql, $params);
		return $rows;
	}
	
	/**
	 * Canonicalizes buyer ids by:
	 * 	Transforming ids into master record ids to resolve duplication issues.
	 * 	Removing defunct / invalid records.
	 * 
	 * @param array $ids Buyer organisation codes
	 * @return array Canonical buyer organisation codes
	 */
	private function canonicalizeBuyerIds ($ids)
	{
		$canonicalizer = new Shipserv_Oracle_UserCompanies_NormBuyer(Tni_Utils::getDb());
		foreach ($ids as $id) $canonicalizer->addId($id);
		$map = $canonicalizer->canonicalize();
		
		$res = array();
		foreach ($map as $sourceId => $normedId)
		{
			if (!empty($normedId)) $res[$normedId] = 1;
		}
		
		return array_keys($res);
	}
	
	/**
	 * Canonicalizes supplier ids by:
	 * 	Transforming ids into master record ids to resolve duplication issues.
	 * 	Removing defunct / invalid records.
	 * 
	 * @param array $ids Supplier branch ids
	 * @return array Canonical supplier branch ids
	 */
	private function canonicalizeSupplierIds ($ids)
	{
		$canonicalizer = new Shipserv_Oracle_UserCompanies_NormSupplier(Tni_Utils::getDb());
		foreach ($ids as $id) $canonicalizer->addId($id);
		$map = $canonicalizer->canonicalize();
		
		$res = array();
		foreach ($map as $sourceId => $normedId)
		{
			if (!empty($normedId)) $res[$normedId] = 1;
		}
		
		return array_keys($res);
	}
}
