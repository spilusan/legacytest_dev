<?php

class Shipserv_Oracle_UserCompanyRequest_Exception extends Exception
{
	const ADD_ALREADY_EXISTS = 100;
	const UPDATE_NOT_FOUND = 200;
	const FETCH_NOT_FOUND = 300;
}

class Shipserv_Oracle_UserCompanyRequest extends Shipserv_Oracle
{
	// Values for PUCR_COMPANY_TYPE
	const COMP_TYPE_SPB = Myshipserv_UserCompany_Company::TYPE_SPB;
	const COMP_TYPE_BYO = Myshipserv_UserCompany_Company::TYPE_BYO;
	
	// Values for PUCR_STATUS
	const STATUS_PENDING = 'PEN';
	const STATUS_CONFIRMED = 'CON';
	const STATUS_REJECTED = 'REJ';
	const STATUS_WITHDRAWN = 'WDN';
	
	/**
	 * Add a request from user to join company.
	 *
	 * Succeeds only if there is no pending or confirmed request already
	 * in existence for user and company specified. Succeeds if all previous
	 * requests have been rejected.
	 *
	 * @return void
	 * @exception Exception on fail
	 */
	public function addRequest ($userId, $companyType, $companyId)
	{
		// Validate parameters
		$validatedParams = $this->sanitizeParams($userId, $companyType, $companyId);
		
		// Quote parameters
		$quotedVals = array();
		foreach (array('userId', 'companyType', 'companyId') as $k)
		{
			$quotedVals[$k] = $this->db->quote($validatedParams[$k]);
		}
		
		// Quote status values
		$quotedStatusVals = array(
			'pending' => $this->db->quote(self::STATUS_PENDING),
			'confirmed' => $this->db->quote(self::STATUS_CONFIRMED)
		);
		
		// Insert request, but only if there is no existing pending request already
		// Note: if there's already a rejected request, insert goes ahead
		$sql = 
			"INSERT INTO PAGES_USER_COMPANY_REQUEST
				(PUCR_PSU_ID, PUCR_COMPANY_TYPE, PUCR_COMPANY_ID, PUCR_STATUS)
				SELECT {$quotedVals['userId']}, {$quotedVals['companyType']}, {$quotedVals['companyId']}, {$quotedStatusVals['pending']} FROM DUAL
					WHERE NOT EXISTS (
						SELECT * FROM PAGES_USER_COMPANY_REQUEST
							WHERE PUCR_PSU_ID = {$quotedVals['userId']} AND PUCR_COMPANY_TYPE = {$quotedVals['companyType']}
							AND PUCR_COMPANY_ID = {$quotedVals['companyId']} AND PUCR_STATUS IN ({$quotedStatusVals['pending']}))";
		
		$stmt = $this->db->query($sql);
		// added by elvir
		$this->db->commit();
		if ($stmt->rowCount() == 0)
		{
			throw new Shipserv_Oracle_UserCompanyRequest_Exception(
				"Unable to add request: there is already a pending/confirmed request",
				Shipserv_Oracle_UserCompanyRequest_Exception::ADD_ALREADY_EXISTS);
		}
		else
		{
			$sql = "SELECT SEQ_PAGES_USER_COMPANY_REQUEST.CURRVAL FROM DUAL";
			return $this->db->fetchOne($sql);
		}
	}
	
	/**
	 * Update the specified request from pending to specified
	 * status (confirmed or rejected).
	 * 
	 * @return void
	 * @exception Exception on fail
	 */
	public function updateRequest ($reqId, $status)
	{
		// Clean request id
		$reqId = (int) $reqId;
		
		// Check new status is confirmed / rejected / withdrawn
		if (! in_array($status, array(self::STATUS_CONFIRMED, self::STATUS_REJECTED, self::STATUS_WITHDRAWN)))
		{
			throw new Exception("Can only update status to confirmed / rejected / withdrawn");
		}
		
		// Fetch and quote value for pending status
		$pendingStatus = $this->db->quote(self::STATUS_PENDING);
		
		// Update request for given id, only if pending
		$sql =
			"UPDATE PAGES_USER_COMPANY_REQUEST SET PUCR_STATUS = :PUCR_STATUS, PUCR_PROCESSED_DATE = sysdate
				WHERE PUCR_ID = :PUCR_ID AND PUCR_STATUS = $pendingStatus";
		
		$stmt = $this->db->query($sql, array('PUCR_STATUS' => $status, 'PUCR_ID' => $reqId));
		
		// Throw exception if ID did not exist, or if it wasn't pending
		if ($stmt->rowCount() == 0)
		{
			throw new Shipserv_Oracle_UserCompanyRequest_Exception(
				"No pending request found with id: $reqId",
				Shipserv_Oracle_UserCompanyRequest_Exception::UPDATE_NOT_FOUND);
		}
	}
	
	/**
	 * Updates latest (i.e. current) PENDING request for given user and company to
	 * WITHDRAWN status.
	 */
	public function closePendingRequest ($userId, $companyType, $companyId)
	{
		$sql =
			"UPDATE PAGES_USER_COMPANY_REQUEST SET
				PUCR_STATUS = :targetStatus,
				PUCR_PROCESSED_DATE = sysdate
			WHERE PUCR_ID = (
				SELECT PUCR_ID FROM (
					SELECT * FROM PAGES_USER_COMPANY_REQUEST
					WHERE
						PUCR_PSU_ID = :userId
						AND PUCR_COMPANY_TYPE = :companyType
						AND PUCR_COMPANY_ID = :companyId
						AND PUCR_STATUS = :matchStatus
					ORDER BY PUCR_ID DESC
				) WHERE ROWNUM <= 1
			)";
		
		$params = array();
		$params['targetStatus'] = self::STATUS_WITHDRAWN;
		$params['userId'] = $userId;
		$params['companyType'] = $companyType;
		$params['companyId'] = $companyId;
		$params['matchStatus'] = self::STATUS_PENDING;
		
		$this->db->query($sql, $params);
	}
	
	/**
	 * @return array indexed array of rows as associative arrays
	 */
	public function fetchRequestsForCompany ($companyType, $companyId)
	{
		$sql = 
			"SELECT PUCR_ID, PUCR_PSU_ID, PUCR_COMPANY_TYPE, PUCR_COMPANY_ID,
				to_char(PUCR_CREATED_DATE, 'yyyy-mm-dd hh24:mi:ss') as PUCR_CREATED_DATE,
				PUCR_STATUS,
				to_char(PUCR_PROCESSED_DATE, 'yyyy-mm-dd hh24:mi:ss') as PUCR_PROCESSED_DATE
				
				FROM PAGES_USER_COMPANY_REQUEST
					WHERE PUCR_COMPANY_TYPE = :PUCR_COMPANY_TYPE
						AND PUCR_COMPANY_ID = :PUCR_COMPANY_ID
						AND 
						(
					      (
                			PUCR_COMPANY_TYPE='BYO' AND PUCR_COMPANY_ID NOT IN (SELECT PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM) 
						  )
			              OR
			              (
			                PUCR_COMPANY_TYPE='SPB'
			              )
						)
					ORDER BY PUCR_CREATED_DATE ASC";
		
		return $this->db->fetchAll($sql, array('PUCR_COMPANY_TYPE' => $companyType,
			'PUCR_COMPANY_ID' => $companyId));
	}
	
	public function fetchRequestsForUser ($userId)
	{
		$sql = 
			"SELECT PUCR_ID, PUCR_PSU_ID, PUCR_COMPANY_TYPE, PUCR_COMPANY_ID,
				to_char(PUCR_CREATED_DATE, 'yyyy-mm-dd hh24:mi:ss') as PUCR_CREATED_DATE,
				PUCR_STATUS,
				to_char(PUCR_PROCESSED_DATE, 'yyyy-mm-dd hh24:mi:ss') as PUCR_PROCESSED_DATE
				
				FROM PAGES_USER_COMPANY_REQUEST
					WHERE PUCR_PSU_ID = :PUCR_PSU_ID
					AND 
					  (
					      (
                			PUCR_COMPANY_TYPE='BYO' AND PUCR_COMPANY_ID NOT IN (SELECT PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM) 
						  )
			              OR
			              (
			                PUCR_COMPANY_TYPE='SPB' 
			              )
					  )
					ORDER BY PUCR_CREATED_DATE ASC";
		
		return $this->db->fetchAll($sql, array('PUCR_PSU_ID' => $userId));
	}
	
	/**
	 * @return array indexed array of rows as associative arrays
	 */
	public function fetchRequestById ($reqId)
	{
		$sql = 
			"SELECT PUCR_ID, PUCR_PSU_ID, PUCR_COMPANY_TYPE, PUCR_COMPANY_ID,
				to_char(PUCR_CREATED_DATE, 'yyyy-mm-dd hh24:mi:ss') as PUCR_CREATED_DATE,
				PUCR_STATUS,
				to_char(PUCR_PROCESSED_DATE, 'yyyy-mm-dd hh24:mi:ss') as PUCR_PROCESSED_DATE
				FROM PAGES_USER_COMPANY_REQUEST
				
				WHERE PUCR_ID = :PUCR_ID";
		
		$row = $this->db->fetchRow($sql, array('PUCR_ID' => $reqId));
		if (is_array($row))
		{
			return $row;
		}
		
		throw new Shipserv_Oracle_UserCompanyRequest_Exception("No request exists for ID: $reqId", Shipserv_Oracle_UserCompanyRequest_Exception::FETCH_NOT_FOUND);
	}
	
	/**
	 * @return array of sanitized parameters
	 * @exception Exception on validation fail
	 */
	private function sanitizeParams ($userId, $companyType, $companyId)
	{
		$resArr = array();
		
		// Turn $userId into an int
		$resArr['userId'] = (int) $userId;
		
		// Ensure $companyType is in set of possibles
		$companyType = (string) $companyType;
		if (in_array($companyType, array(self::COMP_TYPE_SPB, self::COMP_TYPE_BYO)))
		{
			$resArr['companyType'] = $companyType;
		}
		else
		{
			throw new Exception("Unrecognised company type: $companyType");
		}
		
		// Turn $companyId into an int
		$resArr['companyId'] = (int) $companyId;
		
		return $resArr;
	}
}
