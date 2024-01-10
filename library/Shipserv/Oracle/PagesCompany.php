<?php

class Shipserv_Oracle_PagesCompany extends Shipserv_Oracle
{
	private static $inst;
	
	public static function getInstance()
	{
		if (!self::$inst) {
			self::$inst = new self($GLOBALS['application']->getBootstrap()->getResource('db'));
		}
		return self::$inst;
	}
	
	public function getDefaultRow($companyType, $companyId)
	{
		return array(
			'PCO_TYPE' => $companyType,
			'PCO_ID' => $companyId,
			'PCO_ANONYMISED_NAME' => '',
			'PCO_ANONYMISED_LOCATION' => '',
			'PCO_REVIEWS_OPTOUT' => 'N',
			'PCO_IS_JOIN_REQUESTABLE' => 'Y',
			'PCO_AUTO_REV_SOLICIT' => 'Y',
			'PCO_MEMBERSHIP_LEVEL' => 1
		);
	}
	
	/**
	 * Takes a company type and a list of company ids and removes ids which are
	 * not join-requestable.
	 * 
	 * @return array
	 */
	public function filterJoinReqables($companyType, array $companyIds)
	{
		// Form safe SQL list of company ids
		$cIdSqlArr = array();
		foreach ($companyIds as $id) {
			$cIdSqlArr[] = (int) $id;
		}
		if ($cIdSqlArr) {
			$cIdSql = join(', ', $cIdSqlArr);
		} else {
			$cIdSql = 'NULL';
		}
		
		// Read non join-requestable ids
		$sql =
<<<EOF
SELECT PCO_ID FROM PAGES_COMPANY WHERE PCO_TYPE = :companyType AND PCO_ID IN ($cIdSql) AND PCO_IS_JOIN_REQUESTABLE = 'N'
EOF;
		
		$params = array();
		$params['companyType'] = (string) $companyType;
		
		$rows = $this->db->fetchAll($sql, $params);
		
		// Index requested company ids
		$resSet = array();
		foreach ($companyIds as $cId) {
			$resSet[(int) $cId] = 1;
		}
		
		// Loop on non join-requestable ids and remove them
		foreach ($rows as $r) {
			unset($resSet[$r['PCO_ID']]);
		}
		
		return array_keys($resSet);
	}
	
	public function fetchById($companyType, $companyId)
	{
		$sql =
<<<EOF
SELECT * FROM PAGES_COMPANY WHERE PCO_TYPE = :companyType AND PCO_ID = :companyId
EOF;
		
		$params = array();
		$params['companyType'] = (string) $companyType;
		$params['companyId'] = (int) $companyId;
		
		$rows = $this->db->fetchAll($sql, $params);
		if ($rows) {
			return $rows[0];
		} else {
			return $this->getDefaultRow($companyType, $companyId);
		}
	}
	
	/**
	 * Upsert PCO_AUTO_REV_SOLICIT flag
	 */
	public function setAutoReviewSolicitation ($companyType, $companyId, $boolAutoRevSolicitation)
	{
		if (!Myshipserv_UserCompany_Company::isTypeValid($companyType)) {
			throw new Exception("Invalid company type: '$companyType'");
		}
		
		$sql =
<<<EOF
MERGE INTO PAGES_COMPANY TGT
USING (SELECT :autoReviewSolicitation AS AUTO_REV_SOLICITATION FROM DUAL) SRC
ON (TGT.PCO_TYPE = :companyType AND TGT.PCO_ID = :companyId)
WHEN MATCHED THEN
	UPDATE SET TGT.PCO_AUTO_REV_SOLICIT = SRC.AUTO_REV_SOLICITATION
WHEN NOT MATCHED THEN
	INSERT (TGT.PCO_TYPE, TGT.PCO_ID, TGT.PCO_AUTO_REV_SOLICIT) VALUES (:companyType, :companyId, SRC.AUTO_REV_SOLICITATION)
EOF;
		
		$params = array();
		$params['companyType'] = $companyType;
		$params['companyId'] = $companyId;
		$params['autoReviewSolicitation'] = $boolAutoRevSolicitation ? 'Y' : 'N';
		
		$this->db->query($sql, $params);
	}

	/**
	 * Upsert PCO_IS_JOIN_REQUESTABLE flag
	 */
	public function setIsJoinRequestable ($companyType, $companyId, $boolIsJoinRequestable)
	{
		if (!Myshipserv_UserCompany_Company::isTypeValid($companyType)) {
			throw new Exception("Invalid company type: '$companyType'");
		}

		$sql =
<<<EOF
MERGE INTO PAGES_COMPANY TGT
USING (SELECT :isJoinRequestable AS IS_JOIN_REQUESTABLE FROM DUAL) SRC
ON (TGT.PCO_TYPE = :companyType AND TGT.PCO_ID = :companyId)
WHEN MATCHED THEN
	UPDATE SET TGT.PCO_IS_JOIN_REQUESTABLE = SRC.IS_JOIN_REQUESTABLE
WHEN NOT MATCHED THEN
	INSERT (TGT.PCO_TYPE, TGT.PCO_ID, TGT.PCO_IS_JOIN_REQUESTABLE) VALUES (:companyType, :companyId, SRC.IS_JOIN_REQUESTABLE)
EOF;

		$params = array();
		$params['companyType'] = $companyType;
		$params['companyId'] = $companyId;
		$params['isJoinRequestable'] = $boolIsJoinRequestable ? 'Y' : 'N';

		$this->db->query($sql, $params);
	}
	
	/**
	 * Set the new membership level
	 * @param string $companyType
	 * @param integer $companyId
	 * @param integer $membershipLevel
	 * @throws Exception
	 */
	public function setMembershipLevel($companyType, $companyId, $membershipLevel)
	{

		if (!Myshipserv_UserCompany_Company::isTypeValid($companyType)) {
			throw new Exception("Invalid company type: '$companyType'");
		}
		
		$sql = "
				MERGE INTO PAGES_COMPANY TGT
					USING (SELECT :membershipLevel AS PCO_MEMBERSHIP_LEVEL FROM DUAL) SRC
					ON (TGT.PCO_TYPE = :companyType AND TGT.PCO_ID = :companyId)
				WHEN MATCHED THEN
					UPDATE SET TGT.PCO_MEMBERSHIP_LEVEL = SRC.PCO_MEMBERSHIP_LEVEL
				WHEN NOT MATCHED THEN
					INSERT (TGT.PCO_TYPE, TGT.PCO_ID, TGT.PCO_MEMBERSHIP_LEVEL) VALUES (:companyType, :companyId, SRC.PCO_MEMBERSHIP_LEVEL)";
		
		$params = array(
				'companyType' => $companyType,
				'companyId' => $companyId,
				'membershipLevel' => (int)$membershipLevel
		);
		
		$this->db->query($sql, $params);
		
	}
	
	/**
	 * Get the membership level of the company,if not set, default it to 1, as Full Access
	 * @param string $companyType
	 * @param integer $companyId
	 * @return integer
	 */
	public function getMembershipLevel($companyType, $companyId)
	{
		return (int)$this->fetchById($companyType, $companyId)['PCO_MEMBERSHIP_LEVEL'];
	}
}
