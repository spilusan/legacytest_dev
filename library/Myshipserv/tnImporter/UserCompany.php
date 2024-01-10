<?php

class Myshipserv_tnImporter_UserCompany
{
	private $logger;
	private $rowOwner = 'TNI';
	
	public function __construct (Myshipserv_tnImporter_UserCompanyLoggerI $logger = null)
	{
		$this->logger = $logger;
	}
	
	public function setRowOwner ($rowOwner)
	{
		if (!in_array($rowOwner, array('TNI', 'PGS')))
		{
			throw new Exception("Unrecognised row owner '$rowOwner'");
		}
		
		$this->rowOwner = $rowOwner;
	}
	
	public function importUserCompaniesForEmail ($email, $userId)
	{
		$emailNorm = strtolower(trim($email));
		$sqlArr = array();
		
		$sSqlGen = new Myshipserv_tnImporter_UserCompanySupplierSql();
		$sqlArr[] = $sSqlGen->populateBranchUsers($emailNorm);
		$sqlArr[] = $sSqlGen->populateBranchRegistrants($emailNorm);
		$sqlArr[] = $sSqlGen->populateBranchEmails($emailNorm);
		$sqlArr[] = $sSqlGen->populateBranchPublicContacts($emailNorm);
		$sqlArr[] = $sSqlGen->populateBranchContacts($emailNorm);
		
		$bSqlGen = new Myshipserv_tnImporter_UserCompanyBuyerSql();
		$sqlArr[] = $bSqlGen->populateBranchUsers($emailNorm);
		$sqlArr[] = $bSqlGen->populateBranchRegistrants($emailNorm);
		$sqlArr[] = $bSqlGen->populateBranchEmails($emailNorm);
		$sqlArr[] = $bSqlGen->populateOrgEmails($emailNorm);
		
		$sql = join("\nUNION ALL\n", $sqlArr);
		
		$rows = $this->getDb()->fetchAll($sql, array(), Zend_Db::FETCH_NUM);
		
		$sIds = array();
		$bIds = array();
		foreach ($rows as $r)
		{
			if ($r[0] == 'SPB')
			{
				$sIds[$r[1]] = 1;
			}
			elseif ($r[0] == 'BYO')
			{
				$bIds[$r[1]] = 1;
			}
		}
		$sIds = array_keys($sIds);
		$bIds = array_keys($bIds);
		
		$sNormIds = $this->canonicalizeSupplierIds($sIds);
		$bNormIds = $this->canonicalizeBuyerIds($bIds);
		
		// loop and add to u-c table
		foreach (array('SPB' => $sNormIds, 'BYO' => $bNormIds) as $cType => $idArr)
		{
			foreach ($idArr as $id)
			{
				$this->addUserCompany($userId, $cType, $id);
			}
		}
	}
	
	/**
	 * Canonicalizes buyer ids by:
	 *      Transforming ids into master record ids to resolve duplication issues.
	 *      Removing defunct / invalid records.
	 * 
	 * @param array $ids Buyer organisation codes
	 * @return array Canonical buyer organisation codes
	 */
	private function canonicalizeBuyerIds (array $ids)
	{
		$canonicalizer = new Shipserv_Oracle_UserCompanies_NormBuyer($this->getDb());
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
	  *      Transforming ids into master record ids to resolve duplication issues.
	  *      Removing defunct / invalid records.
	  * 
	  * @param array $ids Supplier branch ids
	  * @return array Canonical supplier branch ids
	  */
	private function canonicalizeSupplierIds (array $ids)
	{
		$canonicalizer = new Shipserv_Oracle_UserCompanies_NormSupplier($this->getDb());
		foreach ($ids as $id) $canonicalizer->addId($id);
		$map = $canonicalizer->canonicalize();
		
		$res = array();
		foreach ($map as $sourceId => $normedId)
		{
			if (!empty($normedId)) $res[$normedId] = 1;
		}
		
		return array_keys($res);
	}
	
	public function addUserCompany ($userId, $companyType, $companyId)
	{
		return $this->_addUserCompany($userId, $companyType, $companyId);
	}
	
	private function log ($msg)
	{
		if ($this->logger)
		{
			$this->logger->log($msg);
		}
	}
	
	private function getDb ()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}
	
	private function _addUserCompany ($userId, $companyType, $companyId)
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
		
		if ($this->rowOwner == 'PGS')
		{			
			$sql =
				"UPDATE PAGES_USER_COMPANY
					SET
						PUC_DATA_OWNER = 'PGS'
					WHERE
						PUC_PSU_ID = :psuId
						AND PUC_COMPANY_TYPE = :companyType
						AND PUC_COMPANY_ID = :companyId";
			
			$params = array();
			$params['psuId'] = $userId;
			$params['companyType'] = $companyType;
			$params['companyId'] = $companyId;
			$stmt = $this->getDb()->query($sql, $params);
			
			// If this succeeded, job done
			if ($stmt->rowCount() > 0)
			{
				$this->log("User $userId, company $companyType $companyId: marked existing row owned by 'PGS'");
				return;
			}
		}
		
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
				:rowOwner
			)";
		
		$params = array();
		$params['psuId'] = $userId;
		$params['companyType'] = $companyType;
		$params['companyId'] = $companyId;
		$params['rowOwner'] = $this->rowOwner;
		
		try
		{
			// Run query
			$this->getDb()->query($sql, $params);
			
			// Log success
			$this->log("User $userId, company $companyType $companyId: added new user-company row owned by '{$this->rowOwner}'");
		}
		catch (Zend_Db_Statement_Oracle_Exception $e)
		{
			// Row already existed ...
			if ($e->getCode() == 1)
			{
				$this->log("User $userId, company $companyType $companyId: no action, user-company row already exists");
			}
			
			// If not trapped, throw on
			else
			{
				throw $e;
			}
		}
	}
}
