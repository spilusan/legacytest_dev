<?php

class Shipserv_Oracle_EndorsementPrivacy extends Shipserv_Oracle
{
	// Table name
	const TABLE_NAME = 'PAGES_ENDORSEMENT_PRIVACY';
	
	// Column - primary key
	const COL_ID = 'PEP_ID';
	
	// Column - owner id (e.g. a supplier branch code, or buyer org code)
	const COL_OWNER_ID = 'PEP_OWNER_ID';
	
	// Column - owner type (e.g. supplier branch, or buyer org)
	const COL_OWNER_TYPE = 'PEP_OWNER_TYPE';
	
	// Column - company to which owner's privacy setting applies
	// If owner type is a supplier branch code, this is a buyer org code
	// If owner type is a buyer org code, this is a supplier branch code
	const COL_REF_ID = 'PEP_REF_ID';
	
	// Column - anonymization on / off
	const COL_ANON = 'PEP_ANON';
	
	const COL_LAST_VIEWED = 'PEP_LAST_VIEWED';
	
	const COL_LAST_UPDATED = 'PEP_LAST_UPDATED';
	
	// Values for COL_OWNER_TYPE column
	const OWNER_TYPE_BUYER = 'BYO';
	const OWNER_TYPE_SUPPLIER = 'SPB';
	
	// Values for COL_ANON column
	const ANON_NO = '0';
	const ANON_YES = '1';
	const ANON_TN = 'T';
	
	// Default value for new records
	const ANON_DEFAULT = self::ANON_NO;
	
	// Special value for COL_REF_ID indicating a global setting (rather than a setting relating to a specific entity)
	const REF_ID_GLOBAL = 0;
	
	public static function isValidAnonPolicy ($policy)
	{
		static $validArr = array (self::ANON_NO, self::ANON_YES, self::ANON_TN);
		return in_array($policy, $validArr);
	}
	
	/**
	 * Persist supplier privacy setting.
	 */
	public function setSupplierPrivacy (Shipserv_Oracle_EndorsementPrivacy_Saveable $privacy)
	{
		$this->setPrivacy(self::OWNER_TYPE_SUPPLIER, $privacy);
	}
	
	/**
	 * Persist buyer privacy setting.
	 */
	public function setBuyerPrivacy (Shipserv_Oracle_EndorsementPrivacy_Saveable $privacy)
	{
		$this->setPrivacy(self::OWNER_TYPE_BUYER, $privacy);
	}
	
	/**
	 * Fetch supplier privacy setting.
	 */
	public function getSupplierPrivacy ($supplierId)
	{
		return $this->getPrivacy(self::OWNER_TYPE_SUPPLIER, $supplierId);
	}
	
	/**
	 * Fetch buyer privacy setting.
	 */
	public function getBuyerPrivacy ($buyerId)
	{
		return $this->getPrivacy(self::OWNER_TYPE_BUYER, $buyerId);
	}
	
	/**
	 * Update last viewed date on all rows belonging to company
	 */
	public function updateViewDate ($ownerType, $ownerId)
	{
		// Ignore request if user is logged in as a super user
		if (($u = Shipserv_User::isLoggedIn()) && $u->isSuper()) return;
		
		// Validate parameters
		if (!in_array($ownerType, array(self::OWNER_TYPE_BUYER, self::OWNER_TYPE_SUPPLIER))) throw new Exception("Illegal owner type: '$ownerType'");
		$ownerId = (int) $ownerId;
		
		// Prepare table & column names
		$tName = $this->db->quoteIdentifier(self::TABLE_NAME);
		
		$cLastViewed = $this->db->quoteIdentifier(self::COL_LAST_VIEWED);
		$cOwnerId = $this->db->quoteIdentifier(self::COL_OWNER_ID);
		$cOwnerType = $this->db->quoteIdentifier(self::COL_OWNER_TYPE);
		
		// Update last viewed date, or insert new row if not present
		$tNow = date('Y-m-d:H:i:s');
		$stmt = $this->db->query("UPDATE $tName SET $cLastViewed = TO_DATE('$tNow', 'YYYY/MM/DD:HH24:MI:SS') WHERE $cOwnerId = :ownerId AND $cOwnerType = :ownerType", compact('ownerId', 'ownerType'));
		if ($stmt->rowCount() == 0)
		{
			// Note: a duplicate key exception could be caused under concurrent conditions.
			// todo: add a try/catch to swallow the relevant exception
			$this->doInsert($ownerId, $ownerType, self::REF_ID_GLOBAL, self::ANON_DEFAULT, $tNow, null);
		}
	}
	
	/**
	 * Implements retrieval of privacy setting for supplier / buyer.
	 */
	private function getPrivacy ($ownerType, $ownerId)
	{
		// Fetch all rows relating to buyer
		$sql = sprintf(
			"SELECT * FROM %s WHERE %s = :ownerId AND %s = :ownerType",
			$this->db->quoteIdentifier(self::TABLE_NAME),
			$this->db->quoteIdentifier(self::COL_OWNER_ID),
			$this->db->quoteIdentifier(self::COL_OWNER_TYPE)
		);
		
		$res = $this->db->fetchAll($sql, array(
			'ownerId' => $ownerId,
			'ownerType' => $ownerType));
		
		// Create array to collect results
		$privacySettings = array('globalAnon' => self::ANON_DEFAULT, 'exceptionPolicies' => array());
		
		// Loop on rows
		foreach ($res as $row)
		{
			$anonVal = $row[self::COL_ANON];
			
			// Only act if anonymize value is determined
			if ($anonVal !== null)
			{
				// Row represents global setting
				if ($row[self::COL_REF_ID] == self::REF_ID_GLOBAL)
				{
					$privacySettings['globalAnon'] = $anonVal;
				}
				
				// Row represents supplier-specific setting
				else
				{					
					$privacySettings['exceptionPolicies'][$row[self::COL_REF_ID]] = $anonVal;
				}
			}
		}
		
		return new Shipserv_Oracle_EndorsementPrivacy_Setting($privacySettings['globalAnon'], $privacySettings['exceptionPolicies']);
	}
	
	/**
	 * Implements saving of privacy setting for supplier / buyer.
	 *
	 * todo: wrap in a transaction?
	 */
	private function setPrivacy ($ownerType, Shipserv_Oracle_EndorsementPrivacy_Saveable $privacy)
	{
		$ownerId = $privacy->getOwnerId();
		
		$gAnon = $privacy->getGlobalAnon();
		if (!in_array($gAnon, array(self::ANON_YES, self::ANON_NO, self::ANON_TN))) throw new Exception("Invalid global anonymisation setting: '$gAnon'");
		
		$exList = $privacy->getExceptionList();
		
		// Read last viewed and last updated dates for this row
		$dateParams = $this->readDates($ownerId, $ownerType);
		
		// Clear out existing rows
		$sql = sprintf("DELETE FROM %s WHERE %s = :ownerType AND %s = :ownerId",
			$this->db->quoteIdentifier(self::TABLE_NAME),
			$this->db->quoteIdentifier(self::COL_OWNER_TYPE),
			$this->db->quoteIdentifier(self::COL_OWNER_ID));
		$this->db->query($sql, array(
			'ownerId' => $ownerId,
			'ownerType' => $ownerType));
		
		// Insert row for global setting
		$this->doInsert($ownerId, $ownerType, self::REF_ID_GLOBAL, $gAnon, $dateParams[self::COL_LAST_VIEWED], $dateParams[self::COL_LAST_UPDATED]);
		
		// Loop on company-specific settings
		foreach ($exList as $refId => $anonVal)
		{
			// Insert company specific settings
			$this->doInsert($ownerId, $ownerType, $refId, $anonVal, $dateParams[self::COL_LAST_VIEWED], $dateParams[self::COL_LAST_UPDATED]);
		}
	}
	
	/**
	 * In most cases returns date-time now keyed by last viewed & last updated
	 * column names.
	 *
	 * If the user is logged in and is a super user, then if present, pre-existing
	 * last viewed & update times are returned.
	 * 
	 * @return array
	 */
	private function readDates ($ownerId, $ownerType)
	{
		$tNow = date('Y-m-d:H:i:s');
		$res = array(self::COL_LAST_VIEWED => $tNow, self::COL_LAST_UPDATED => $tNow);
		
		if (($u = Shipserv_User::isLoggedIn()) && $u->isSuper())
		{
			$tName = $this->db->quoteIdentifier(self::TABLE_NAME);
			
			$cLastViewed = $this->db->quoteIdentifier(self::COL_LAST_VIEWED);
			$cLastUpdated = $this->db->quoteIdentifier(self::COL_LAST_UPDATED);
			$cOwnerId = $this->db->quoteIdentifier(self::COL_OWNER_ID);
			$cOwnerType = $this->db->quoteIdentifier(self::COL_OWNER_TYPE);
			$cRefId = $this->db->quoteIdentifier(self::COL_REF_ID);
			
			$vRefId = $this->db->quote(self::REF_ID_GLOBAL);
			
			$rows = $this->db->fetchAll("SELECT TO_CHAR($cLastViewed, 'YYYY/MM/DD:HH24:MI:SS') AS $cLastViewed, TO_CHAR($cLastUpdated, 'YYYY/MM/DD:HH24:MI:SS') AS $cLastUpdated FROM $tName WHERE $cOwnerId = :ownerId AND $cOwnerType = :ownerType AND $cRefId = $vRefId", compact('ownerId', 'ownerType'));
			if ($rows) $res = $rows[0];
			else $res = array(self::COL_LAST_VIEWED => null, self::COL_LAST_UPDATED => null);
		}
		
		return $res;
	}
	
	/**
	 * Add a new row. Note that $lastView & $lastUpdated may be null, and if so,
	 * are translated into DB NULLs.
	 */
	private function doInsert ($ownerId, $ownerType, $refId, $anon, $lastViewed, $lastUpdated)
	{
		$tName = $this->db->quoteIdentifier(self::TABLE_NAME);
		
		$colSqlArr = array(
			$this->db->quoteIdentifier(self::COL_OWNER_ID),
			$this->db->quoteIdentifier(self::COL_OWNER_TYPE),
			$this->db->quoteIdentifier(self::COL_REF_ID),
			$this->db->quoteIdentifier(self::COL_ANON),
			$this->db->quoteIdentifier(self::COL_LAST_VIEWED),
			$this->db->quoteIdentifier(self::COL_LAST_UPDATED),
		);
		$colSql = join(', ', $colSqlArr);
		
		$valSqlArr = array(
			$this->db->quote($ownerId),
			$this->db->quote($ownerType),
			$this->db->quote($refId),
			$this->db->quote($anon),
		);
		if ($lastViewed != '') $valSqlArr[] = "TO_DATE(" . $this->db->quote($lastViewed) . ", 'YYYY/MM/DD:HH24:MI:SS')";
		else $valSqlArr[] = 'NULL';
		if ($lastUpdated != '') $valSqlArr[] = "TO_DATE(" . $this->db->quote($lastUpdated) . ", 'YYYY/MM/DD:HH24:MI:SS')";
		else $valSqlArr[] = 'NULL';
		$valSql = join(', ', $valSqlArr);
		
		$sql = "INSERT INTO $tName ($colSql) VALUES ($valSql)";
		$this->db->query($sql);
	}
}
