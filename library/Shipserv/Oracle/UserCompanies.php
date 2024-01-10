<?php

/**
 * THIS CLASS IS NOW DEPRECATED - User-company relations are now managed via
 * the PAGES_USER_COMPANY table.
 *
 * Provides access to user-company associations in TradeNet (i.e. outside
 * Pages). The application should normally use a higher-level class to access
 * user-company data, as there is Pages-specific data stored elsewhere to be blended
 * with the TN data.
 *
 * @deprecated
 */
class Shipserv_Oracle_UserCompanies extends Shipserv_Oracle
{
	const COMP_TYPE_SPB = Myshipserv_UserCompany_Company::TYPE_SPB;
	const COMP_TYPE_BYO = Myshipserv_UserCompany_Company::TYPE_BYO;
	
	/**
	 * Search buyers & suppliers by name. Doesn't belong here,
	 * but needed to put it somewhere fast.
	 *
	 * @deprecated
	 * @return array
	 */
	public function getCompanyIdByName ($name)
	{
		throw new Exception('Deprecated');
		
		$sql =
			"SELECT * FROM (
				SELECT 'BYO' COMP_TYPE, BYO.BYO_ORG_CODE COMP_ID, BYO.BYO_NAME COMP_NAME, BYO.BYO_CONTACT_CITY COMP_CITY
					FROM BUYER_ORGANISATION BYO WHERE UPPER(BYO.BYO_NAME) LIKE :byoName AND BYO.BYO_ORG_CODE NOT IN (SELECT PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM)
				UNION ALL
				SELECT 'SPB', SB.SPB_BRANCH_CODE, SB.SPB_NAME, SB.SPB_CITY
					FROM SUPPLIER_BRANCH SB
					WHERE UPPER(SB.SPB_NAME) LIKE :spbName
					AND SB.SPB_BRANCH_CODE NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)
			) T ORDER BY UPPER(T.COMP_NAME)";
		
		$sqlData = array(
			'byoName' => '%' . strtoupper($name) . '%',
			'spbName' => strtoupper($name) . '%',
		);
		
		return $this->db->fetchAll($sql, $sqlData);
	}
	
	/**
	 * Fetch supplier branches by associated user e-mail.
	 *
	 * @deprecated
	 * @return array of canonical supplier branch ids
	 */
	public function getSuppliersByEmail ($email)
	{
		throw new Exception("Deprecated");
		
		$normedIds = $this->memcacheGet(__CLASS__, __FUNCTION__, strtolower($email));
		if ($normedIds === false)
		{
			$ids = $this->getSupplierIdsByEmail($email);
			$normedIds = $this->canonicalizeSupplierIds($ids);
			
			$this->memcacheSet(__CLASS__, __FUNCTION__, strtolower($email), $normedIds);
		}
		return $normedIds;
	}	
	
	/**
	 * Fetch buyer organisations by associated user e-mail.
	 *
	 * @deprecated
	 * @return array of canonical buyer organisation codes
	 */
	public function getBuyersByEmail ($email)
	{
		throw new Exception("Deprecated");
		
		$normedIds = $this->memcacheGet(__CLASS__, __FUNCTION__, strtolower($email));
		if ($normedIds === false)
		{
			$ids = $this->getBuyerIdsByEmail($email);
			$normedIds = $this->canonicalizeBuyerIds($ids);
			
			$this->memcacheSet(__CLASS__, __FUNCTION__, strtolower($email), $normedIds);
		}
		return $normedIds;
	}
	
	/**
	 * Fetch users for given supplier.
	 * Note that for TN users with no Pages user, a new Pages account is
	 * automatically created on the fly.
	 *
	 * @deprecated
	 * @param $id supplier branch code (canonical or non-canonical)
	 * @return Shipserv_Oracle_User_UserCollection
	 */
	public function getUsersForSupplierId ($id)
	{
		throw new Exception("Deprecated");
		
		$users = $this->memcacheGet(__CLASS__, __FUNCTION__, $id);
		if ($users === false)
		{
			$siblingIds = $this->deCanonicalizeSupplier($id);
			$emails = $this->getEmailsForSupplierIds($siblingIds);		
			$ua = new Shipserv_Oracle_User($this->db);
			$users = $ua->fetchUsersByEmails($emails);
			
			$this->memcacheSet(__CLASS__, __FUNCTION__, $id, $users);
		}
		return $users;
	}
	
	/**
	 * Fetch users for given buyer.
	 * Note that for TN users with no Pages user, a new Pages account is
	 * automatically created on the fly.
	 * 
	 * @deprecated
	 * @param $id buyer organisation code (canonical or non-canonical)
	 * @return Shipserv_Oracle_User_UserCollection
	 */
	public function getUsersForBuyerId ($id)
	{
		throw new Exception("Deprecated");
		
		$users = $this->memcacheGet(__CLASS__, __FUNCTION__, $id);
		if ($users === false)
		{
			$siblingIds = $this->deCanonicalizeBuyer($id);
			$emails = $this->getEmailsForBuyerIds($siblingIds);
			$ua = new Shipserv_Oracle_User($this->db);
			$users = $ua->fetchUsersByEmails($emails);
			
			$this->memcacheSet(__CLASS__, __FUNCTION__, $id, $users);
		}
		return $users;
	}
	
	/**
	 * Fetch all possible supplier branch codes (canonical & non-canonical)
	 * which represent the same entity as supplied branch code.
	 *
	 * @param $id supplier branch code (canonical / non-canonical)
	 * @param array supplier branch codes (canonical & non-canonicals)
	 */
	private function deCanonicalizeSupplier ($id)
	{
		$quoted['id'] = $this->db->quote($id);
		
		$sql =
			"SELECT SPB_BRANCH_CODE FROM SUPPLIER_BRANCH WHERE SPB_BRANCH_CODE = {$quoted['id']}
			UNION
			SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM WHERE PSN_NORM_SPB_BRANCH_CODE = {$quoted['id']}
			UNION
			SELECT PSN_NORM_SPB_BRANCH_CODE FROM PAGES_SPB_NORM WHERE PSN_SPB_BRANCH_CODE = {$quoted['id']}
			UNION
			SELECT B.PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM A INNER JOIN PAGES_SPB_NORM B ON A.PSN_NORM_SPB_BRANCH_CODE = B.PSN_NORM_SPB_BRANCH_CODE WHERE A.PSN_SPB_BRANCH_CODE = {$quoted['id']}";
		
		$rows = $this->db->fetchAll($sql);
		$resArr = array();
		foreach ($rows as $r) $resArr[] = $r['SPB_BRANCH_CODE'];
		return $resArr;
	}
	
	/**
	 * Fetch all possible buyer org codes (canonical & non-canonical)
	 * which represent the same entity as supplied org code.
	 *
	 * @param $id buyer org code (canonical / non-canonical)
	 * @param array buyer org codes (canonical & non-canonicals)
	 */
	private function deCanonicalizeBuyer ($id)
	{
		$quoted['id'] = $this->db->quote($id);
		
		$sql =
			"SELECT BYO_ORG_CODE FROM BUYER_ORGANISATION WHERE BYO_ORG_CODE = {$quoted['id']}
			UNION
			SELECT PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM WHERE PBN_NORM_BYO_ORG_CODE = {$quoted['id']}
			UNION
			SELECT PBN_NORM_BYO_ORG_CODE FROM PAGES_BYO_NORM WHERE PBN_BYO_ORG_CODE = {$quoted['id']}
			UNION
			SELECT B.PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM A INNER JOIN PAGES_BYO_NORM B ON A.PBN_NORM_BYO_ORG_CODE = B.PBN_NORM_BYO_ORG_CODE WHERE A.PBN_BYO_ORG_CODE = {$quoted['id']}";
		
		$rows = $this->db->fetchAll($sql);
		$resArr = array();
		foreach ($rows as $r) $resArr[] = $r['BYO_ORG_CODE'];
		return $resArr;
	}
	
	/**
	 * Looks up all e-mail addresses associated with supplied supplier
	 * branch codes.
	 *
	 * @return array of string emails
	 */
	private function getEmailsForSupplierIds (array $ids)
	{
		$quotedIds = array();
		foreach ($ids as $id) $quotedIds[] = $this->db->quote($id);
		if ($quotedIds) $idSql = join(', ', $quotedIds);
		else $idSql = 'NULL';
		
		$sql =
			"SELECT LOWER(SBU_EMAIL_ADDRESS) AS EMAIL FROM SUPPLIER_BRANCH_USER WHERE SBU_SPB_BRANCH_CODE IN ($idSql)
			UNION
			SELECT LOWER(SPB_REGISTRANT_EMAIL_ADDRESS) FROM SUPPLIER_BRANCH WHERE SPB_BRANCH_CODE IN ($idSql)
			UNION
			SELECT LOWER(SPB_EMAIL) FROM SUPPLIER_BRANCH WHERE SPB_BRANCH_CODE IN ($idSql)
			UNION
			SELECT LOWER(PUBLIC_CONTACT_EMAIL) FROM SUPPLIER_BRANCH WHERE SPB_BRANCH_CODE IN ($idSql)
			UNION
			SELECT LOWER(ELECTRONIC_MAIL) FROM CONTACT_PERSON WHERE SUPPLIER_BRANCH_CODE IN ($idSql)";
		
		$rows = $this->db->fetchAll($sql);
		$resArr = array();
		foreach ($rows as $r)
		{
			// todo: strlen is a hack to remove malformed emails. replace with a decent regex
			$rEmail = trim($r['EMAIL']);
			if (strlen($rEmail) > 5) $resArr[] = $rEmail;
		}
		$resArr = array_unique($resArr);
		return $resArr;
	}
	
	/**
	 * Looks up all e-mail addresses associated with supplied buyer
	 * org codes.
	 *
	 * @return array of string emails
	 */
	private function getEmailsForBuyerIds (array $ids)
	{
		$quotedIds = array();
		foreach ($ids as $id) $quotedIds[] = $this->db->quote($id);
		if ($quotedIds) $idSql = join(', ', $quotedIds);
		else $idSql = 'NULL';
		
		$sql =
			"SELECT LOWER(BBU_EMAIL_ADDRESS) AS EMAIL FROM BUYER_BRANCH_USER WHERE BBU_BYO_ORG_CODE IN ($idSql)
			UNION
			SELECT LOWER(BYB_EMAIL_ADDRESS) FROM BUYER_BRANCH WHERE BYB_BYO_ORG_CODE IN ($idSql)
			UNION
			SELECT LOWER(BYB_REGISTRANT_EMAIL_ADDRESS) FROM BUYER_BRANCH WHERE BYB_BYO_ORG_CODE IN ($idSql)
			UNION
			SELECT LOWER(BYO_CONTACT_EMAIL) FROM BUYER_ORGANISATION WHERE BYO_ORG_CODE IN ($idSql)";
		
		$rows = $this->db->fetchAll($sql);
		$resArr = array();
		// todo: remove malformed e-mails here?
		foreach ($rows as $r)
		{
			// todo: strlen is a hack to remove malformed emails. replace with a decent regex
			$rEmail = trim($r['EMAIL']);
			if (strlen($rEmail) > 5) $resArr[] = $rEmail;
		}
		$resArr = array_unique($resArr);
		return $resArr;
	}
	
	/**
	 * Look up suppliers by e-mail across several tables.
	 * 
	 * @param string $email
	 * @return array Supplier branch codes
	 */
	private function getSupplierIdsByEmail ($email)
	{
		$sql = 
			"SELECT SBU_SPB_BRANCH_CODE FROM SUPPLIER_BRANCH_USER WHERE LOWER(SBU_EMAIL_ADDRESS) = LOWER(:email)
			UNION
			SELECT SPB_BRANCH_CODE FROM SUPPLIER_BRANCH WHERE LOWER(SPB_REGISTRANT_EMAIL_ADDRESS) = LOWER(:email)
				OR LOWER(SPB_EMAIL) = LOWER(:email) OR LOWER(PUBLIC_CONTACT_EMAIL) = LOWER(:email)
			UNION
			SELECT SUPPLIER_BRANCH_CODE FROM CONTACT_PERSON WHERE LOWER(ELECTRONIC_MAIL) = LOWER(:email)";
		
		// NB/ Also consider this (yielding *org* code), but not urgent as data considered poor quality:
		// SELECT SUP_ORG_CODE FROM SUPPLIER_ORGANISATION WHERE SUP_CONTACT_EMAIL = :email
		
		$res = $this->db->fetchCol($sql, array('email' => trim($email)));
		return $res;
	}
	
	/**
	 * Look up buyers by e-mail across several tables.
	 * 
	 * @param string $email
	 * @return array Buyer organisation codes
	 */
	private function getBuyerIdsByEmail ($email)
	{
		$sql =
			"SELECT BBU_BYO_ORG_CODE FROM BUYER_BRANCH_USER WHERE LOWER(BBU_EMAIL_ADDRESS) = LOWER(:email)
			UNION
			SELECT BYB_BYO_ORG_CODE FROM BUYER_BRANCH WHERE LOWER(BYB_EMAIL_ADDRESS) = LOWER(:email) OR LOWER(BYB_REGISTRANT_EMAIL_ADDRESS) = LOWER(:email)
			UNION
			SELECT BYO_ORG_CODE FROM BUYER_ORGANISATION WHERE LOWER(BYO_CONTACT_EMAIL) = LOWER(:email)";
		
		return $this->db->fetchCol($sql, array('email' => trim($email)));
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
		$canonicalizer = new Shipserv_Oracle_UserCompanies_NormBuyer($this->db);
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
		$canonicalizer = new Shipserv_Oracle_UserCompanies_NormSupplier($this->db);
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
