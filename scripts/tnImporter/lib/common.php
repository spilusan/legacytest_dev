<?php

class Tni_Importer
{
	public function run ()
	{
		// Ensure cache table is empty
		$this->clearCache();
		
		// Import e-mails from supplier tables into cache
		// Mark some rows as valid for import as Pages users
		$sci = new Tni_SupplierCacheImporter();
		$sci->import();
		
		// Import e-mails from buyer tables into cache
		// Mark some rows as valid for import as Pages users
		$bci = new Tni_BuyerCacheImporter();
		$bci->import();
	
		// Remove malformed e-mails from cache
		$cec = new Tni_CacheEmailCleaner();
		$cec->clean();
		
		// Add normalised supplier/buyer IDs to cache
		$ccn = new Tni_CacheCompanyNormaliser();
		$ccn->normalise();
		
		// Take rows valid for import as Pages users and check for duplicate
		// e-mails. Mark such rows as not valid for import.
		$this->puImportRemoveMultipleEmails();
		die();
		/*
		// Fetch all e-mails in cache which are marked for import as Pages users
		// and import them into USERS and PAGES_USER.
		$pui = new Tni_PagesUserImporter();
		$pui->import();
		
		// Fetch e-mails in cache which correspond to a valid Pages user
		// with EMAIL_CONFIRMED = 'Y' and mark them with their Pages user ID.
		$this->markPuIdsForUcImport();
		
		// Fetch all distinct rows with a Pages user ID and a normalised
		// company ID and import them into PAGES_USER_COMPANY.
		$uci = new Tni_UserCompanyImporter();
		$uci->import();		
		*/
	}
	
	private function clearCache ()
	{
		$sql =
<<<EOT
DELETE FROM PAGES_TNI_UC_CACHE2
EOT;
		
		Tni_Logger::log("Clearing cache table");
		Tni_Utils::getDb()->query($sql);
	}
	
	private function puImportRemoveMultipleEmails ()
	{
		$sql = 
<<<EOT
UPDATE PAGES_TNI_UC_CACHE2 t SET t.PTUC_PU_IMPORT = 'N'
WHERE
	t.PTUC_EMAIL IN
	(
		SELECT PTUC_EMAIL FROM PAGES_TNI_UC_CACHE2 WHERE PTUC_PU_IMPORT = 'Y' GROUP BY PTUC_EMAIL HAVING COUNT(*) > 1
	)
EOT;
		
		Tni_Logger::log(__METHOD__ . ": ");
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows marked NO pages user import due to duplicate e-mails");
	}
	
	private function markPuIdsForUcImport ()
	{
		$sql = 
<<<EOT
UPDATE PAGES_TNI_UC_CACHE2 t SET t.PTUC_PU_ID =
(
	SELECT PSU_ID FROM PAGES_USER pu
	WHERE
		LOWER(pu.PSU_EMAIL) = t.PTUC_EMAIL
		AND pu.PSU_EMAIL_CONFIRMED = 'Y'
		AND EXISTS
		(
			SELECT * FROM USERS WHERE
				USR_USER_CODE = pu.PSU_ID
				AND USR_TYPE = 'P'
				AND (USR_STS = 'ACT' OR USR_STS IS NULL)
		)
		
)
EOT;
		
		Tni_Logger::log(__METHOD__ . ": ");
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows marked for user-company import with Pages user ID");
	}
}

class Tni_CacheImporterUtils
{
	private function __construct ()
	{
		// Private constructor
	}
	
	public static function wrapInsert ($selectSql)
	{
		$sql =
<<<EOT
		INSERT INTO PAGES_TNI_UC_CACHE2
		(PTUC_COMP_TYPE, PTUC_COMP_ID, PTUC_EMAIL, PTUC_FIRST_NAME, PTUC_LAST_NAME, PTUC_PU_IMPORT)
		(
			$selectSql
		)
EOT;
		return $sql;
	}
}

/**
 * Common methods for TradeNet Importer.
 */
class Tni_Utils
{
	private function __construct ()
	{
		// Cannot instantiate
	}
	
	public static function isEmail ($val)
	{
		return Shipserv_Oracle_User::staticIsEmail($val);
	}
	
	public static function getDb ()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}
}

/**
 * Centralised logging.
 */
class Tni_Logger
{
	private function __construct () { }
	
	public static function log ($msg)
	{
		echo date('Y-m-d H:i:s') . ' ' . $msg . "\n";
	}
}

class Tni_SupplierCacheImporter
{
	private $sqlGen;
	
	public function __construct ()
	{
		$this->sqlGen = new Myshipserv_tnImporter_UserCompanySupplierSql();
	}
	
	public function import ()
	{
		$this->populateBranchUsers();
		$this->populateBranchRegistrants();
		$this->populateBranchEmails();
		$this->populateBranchPublicContacts();
		$this->populateBranchContacts();
	}
	
	private function populateBranchUsers ()
	{
		$sql = $this->sqlGen->populateBranchUsers();
		
		Tni_Logger::log(__METHOD__ . ": ");
		$sql = Tni_CacheImporterUtils::wrapInsert($sql);
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows inserted");
	}
	
	private function populateBranchRegistrants ()
	{
		$sql = $this->sqlGen->populateBranchRegistrants();
		
		Tni_Logger::log(__METHOD__ . ": ");
		$sql = Tni_CacheImporterUtils::wrapInsert($sql);
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows inserted");
	}
	
	private function populateBranchEmails ()
	{
		$sql = $this->sqlGen->populateBranchEmails();
		
		Tni_Logger::log(__METHOD__ . ": ");
		$sql = Tni_CacheImporterUtils::wrapInsert($sql);
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows inserted");
	}

	private function populateBranchPublicContacts ()
	{
		$sql = $this->sqlGen->populateBranchPublicContacts();
		
		Tni_Logger::log(__METHOD__ . ": ");
		$sql = Tni_CacheImporterUtils::wrapInsert($sql);
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows inserted");
	}

	private function populateBranchContacts ()
	{
		$sql = $this->sqlGen->populateBranchContacts ();
		
		Tni_Logger::log(__METHOD__ . ": ");
		$sql = Tni_CacheImporterUtils::wrapInsert($sql);
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows inserted");
	}
}

class Tni_BuyerCacheImporter
{
	public function __construct ()
	{
		$this->sqlGen = new Myshipserv_tnImporter_UserCompanyBuyerSql();
	}
	
	public function import ()
	{
		$this->populateBranchUsers();
		$this->populateBranchRegistrants();
		$this->populateBranchEmails();
		$this->populateOrgEmails();
	}
	
	private function populateBranchUsers ()
	{
		$sql = $this->sqlGen->populateBranchUsers();
		
		Tni_Logger::log(__METHOD__ . ": ");
		$sql = Tni_CacheImporterUtils::wrapInsert($sql);
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows inserted");
	}

	private function populateBranchRegistrants ()
	{
		$sql = $this->sqlGen->populateBranchRegistrants();
		
		Tni_Logger::log(__METHOD__ . ": ");
		$sql = Tni_CacheImporterUtils::wrapInsert($sql);
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows inserted");
	}
	
	private function populateBranchEmails ()
	{
		$sql = $this->sqlGen->populateBranchEmails();
		
		Tni_Logger::log(__METHOD__ . ": ");
		$sql = Tni_CacheImporterUtils::wrapInsert($sql);
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows inserted");
	}
	
	private function populateOrgEmails ()
	{
		$sql = $this->sqlGen->populateOrgEmails();
		
		Tni_Logger::log(__METHOD__ . ": ");
		$sql = Tni_CacheImporterUtils::wrapInsert($sql);
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows inserted");
	}
}

class Tni_CacheEmailCleaner
{
	public function clean ()
	{
		$this->removeShortEmails();
		$this->removeBadEmails();
		
		Tni_Logger::log(__METHOD__ . ": cleaning e-mails from PHP");
		$cnt = 0;
		$cntDeleted = 0;
		foreach ($this->fetchEmails() as $eml)
		{
			if (!Tni_Utils::isEmail($eml))
			{
				$this->deleteEmail($eml);
				$cntDeleted++;
			}
			
			$cnt++;
			if (($cnt % 1000) == 0)
			{
				Tni_Logger::log("$cnt e-mails checked, $cntDeleted deleted");
			}
		}
	}
	
	private function removeShortEmails ()
	{
		$sql = 
<<<EOT
DELETE FROM PAGES_TNI_UC_CACHE2 WHERE
	LENGTH(PTUC_EMAIL) < 7
	OR PTUC_EMAIL IS NULL
	OR INSTR(PTUC_EMAIL, '@shipserv.com') != 0
EOT;
		
		Tni_Logger::log(__METHOD__ . ": ");
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows deleted");
	}
	
	private function removeBadEmails ()
	{
		$sql = 
<<<EOT
DELETE FROM PAGES_TNI_UC_CACHE2 WHERE INSTR(PTUC_EMAIL, '@') = 0
EOT;
		
		Tni_Logger::log(__METHOD__ . ": ");
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted rows deleted");
	}
	
	private function fetchEmails ()
	{
		$sql = "SELECT DISTINCT PTUC_EMAIL FROM PAGES_TNI_UC_CACHE2";
		
		$emails = array();
		foreach (Tni_Utils::getDb()->fetchAll($sql, array(), Zend_Db::FETCH_NUM) as $r)
		{
			$emails[] = $r[0];
		}
		return $emails;
	}
	
	private function deleteEmail ($email)
	{
		$sql = "DELETE FROM PAGES_TNI_UC_CACHE2 WHERE PTUC_EMAIL = :email";
		Tni_Utils::getDb()->query($sql, array('email' => $email));
	}
}

class Tni_CacheCompanyNormaliser
{
	public function normalise ()
	{
		$this->normSuppliers1();
		$this->normSuppliers2();
		
		$this->normBuyers1();
		$this->normBuyers2();
	}
	
	private function normSuppliers1 ()
	{
		$sql = 
<<<EOT
UPDATE PAGES_TNI_UC_CACHE2 t
SET
	t.PTUC_NORMED_COMP_ID = T.PTUC_COMP_ID
WHERE
	T.PTUC_COMP_TYPE = 'SPB'
	AND NOT EXISTS (SELECT * FROM PAGES_SPB_NORM WHERE PSN_SPB_BRANCH_CODE = t.PTUC_COMP_ID)
EOT;
		
		Tni_Logger::log(__METHOD__ . ": ");
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted suppliers marked canonical");
	}
	
	private function normSuppliers2 ()
	{
		$sql = 
$sql['normSuppliers2_markNonCanonicals'] = 
<<<EOT
UPDATE PAGES_TNI_UC_CACHE2 t
SET
	t.PTUC_NORMED_COMP_ID =
	(
		SELECT PSN_NORM_SPB_BRANCH_CODE FROM PAGES_SPB_NORM WHERE PSN_SPB_BRANCH_CODE = t.PTUC_COMP_ID
	)
WHERE
	t.PTUC_COMP_TYPE = 'SPB'
	AND PTUC_NORMED_COMP_ID IS NULL
EOT;
		
		Tni_Logger::log(__METHOD__ . ": ");
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted non-canonical suppliers marked with normed ID");
	}

	private function normBuyers1 ()
	{
		$sql = 
<<<EOT
UPDATE PAGES_TNI_UC_CACHE2 t
SET
	t.PTUC_NORMED_COMP_ID = T.PTUC_COMP_ID
WHERE
	T.PTUC_COMP_TYPE = 'BYO'
	AND NOT EXISTS (SELECT * FROM PAGES_BYO_NORM WHERE PBN_BYO_ORG_CODE = t.PTUC_COMP_ID)
EOT;
		
		Tni_Logger::log(__METHOD__ . ": ");
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted buyers marked canonical");
	}
	
	private function normBuyers2 ()
	{
		$sql = 
<<<EOT
UPDATE PAGES_TNI_UC_CACHE2 t
SET
	t.PTUC_NORMED_COMP_ID =
	(
		SELECT PBN_NORM_BYO_ORG_CODE FROM PAGES_BYO_NORM WHERE PBN_BYO_ORG_CODE = t.PTUC_COMP_ID
	)
WHERE
	t.PTUC_COMP_TYPE = 'BYO'
	AND PTUC_NORMED_COMP_ID IS NULL
EOT;
		
		Tni_Logger::log(__METHOD__ . ": ");
		$stmt = Tni_Utils::getDb()->query($sql);
		$nInserted = $stmt->rowCount();
		Tni_Logger::log("$nInserted non-canonical buyers marked with normed ID");
	}
}

class Tni_PagesUserImporter
{
	private $uDao;
	
	public function __construct ()
	{
		$this->uDao = new Shipserv_Oracle_User(Tni_Utils::getDb());
	}
	
	public function import ()
	{
		Tni_Logger::log("Importing suppliers as Pages users");
		$cnt = 0;
		foreach ($this->fetchSuppliers() as $s)
		{
			try
			{
				$this->uDao->createUser($s['PTUC_EMAIL'], $s['PTUC_FIRST_NAME'], $s['PTUC_LAST_NAME'], null, $s['SPB_NAME'], 'Y', 'PAGES TNI');
			}
			catch (Shipserv_Oracle_User_Exception_FailCreateUser $e)
			{
				$this->handleFailCreateUser($e);
			}
			
			$cnt++;
			if (($cnt % 100) == 0)
			{
				Tni_Logger::log("Progress: processed $cnt suppliers for import as Pages users");
			}
		}
		
		Tni_Logger::log("Importing buyers as Pages users");
		$cnt = 0;
		foreach ($this->fetchBuyers() as $b)
		{
			try
			{
				$this->uDao->createUser($b['PTUC_EMAIL'], $b['PTUC_FIRST_NAME'], $b['PTUC_LAST_NAME'], null, $b['BYO_NAME'], 'Y', 'PAGES TNI');
			}
			catch (Shipserv_Oracle_User_Exception_FailCreateUser $e)
			{
				$this->handleFailCreateUser($e);
			}
			
			$cnt++;
			if (($cnt % 100) == 0)
			{
				Tni_Logger::log("Progress: processed $cnt buyers for import as Pages users");
			}
		}
	}
	
	private function handleFailCreateUser (Shipserv_Oracle_User_Exception_FailCreateUser $e)
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
	
	private function fetchSuppliers ()
	{
		$sql = 
<<<EOT
SELECT a.PTUC_EMAIL, a.PTUC_FIRST_NAME, a.PTUC_LAST_NAME, b.SPB_NAME
FROM PAGES_TNI_UC_CACHE2 a
LEFT JOIN SUPPLIER_BRANCH b ON b.SPB_BRANCH_CODE = a.PTUC_NORMED_COMP_ID
WHERE a.PTUC_PU_IMPORT = 'Y' AND a.PTUC_COMP_TYPE = 'SPB'
AND NOT EXISTS(SELECT * FROM PAGES_USER WHERE LOWER(PSU_EMAIL) = a.PTUC_EMAIL)
EOT;
		Tni_Logger::log(__METHOD__ . ": ");
		return Tni_Utils::getDb()->fetchAll($sql, array(), Zend_Db::FETCH_ASSOC);
	}
	
	private function fetchBuyers ()
	{
		$sql = 
<<<EOT
SELECT a.PTUC_EMAIL, a.PTUC_FIRST_NAME, a.PTUC_LAST_NAME, b.BYO_NAME
FROM PAGES_TNI_UC_CACHE2 a
LEFT JOIN BUYER_ORGANISATION b ON b.BYO_ORG_CODE = a.PTUC_NORMED_COMP_ID
WHERE a.PTUC_PU_IMPORT = 'Y' AND a.PTUC_COMP_TYPE = 'BYO'
AND NOT EXISTS(SELECT * FROM PAGES_USER WHERE LOWER(PSU_EMAIL) = a.PTUC_EMAIL)
EOT;
		
		Tni_Logger::log(__METHOD__ . ": ");
		return Tni_Utils::getDb()->fetchAll($sql, array(), Zend_Db::FETCH_ASSOC);
	}
}

class Tni_UserCompanyImporter
{
	private $ucAdder;
	
	public function __construct ()
	{
		$this->ucAdder = new Myshipserv_tnImporter_UserCompany(new Tni_PagesUserImporterLogger());
	}
	
	public function import ()
	{
		foreach ($this->fetchUserCompanies() as $uc)
		{
			$this->addUserCompany($uc['PTUC_PU_ID'], $uc['PTUC_COMP_TYPE'], $uc['PTUC_NORMED_COMP_ID']);
		}
	}
	
	private function fetchUserCompanies ()
	{
		$sql = 
<<<EOT
SELECT DISTINCT PTUC_PU_ID, PTUC_COMP_TYPE, PTUC_NORMED_COMP_ID
FROM PAGES_TNI_UC_CACHE2
WHERE PTUC_PU_ID IS NOT NULL AND PTUC_NORMED_COMP_ID IS NOT NULL
MINUS
SELECT PUC_PSU_ID, PUC_COMPANY_TYPE, PUC_COMPANY_ID FROM PAGES_USER_COMPANY
EOT;
		
		Tni_Logger::log(__METHOD__ . ": ");
		return Tni_Utils::getDb()->fetchAll($sql, array(), Zend_Db::FETCH_ASSOC);
	}
	
	private function addUserCompany ($userId, $companyType, $companyId)
	{
		$this->ucAdder->addUserCompany($userId, $companyType, $companyId);
	}
}

class Tni_PagesUserImporterLogger implements Myshipserv_tnImporter_UserCompanyLoggerI
{
	public function log ($msg)
	{
		Tni_Logger::log($msg);
	}
}
