<?php
class Shipserv_Oracle_MembershipAuthorisation extends Shipserv_Oracle
{
	/**
	 * Retrieve records that match supplied filter
	 *
	 * @todo: rework to Zend_Db_Select and Zend_Paginator (Yuriy Akopov)
	 *
	 * @param   array   $filters
	 * @param   int     $page
	 * @param   int     $pageSize
	 * @param   bool    $useCache
	 * @param   int     $cacheTTL
	 *
	 * @return  array
	 */
	public function fetch($filters = array(), $page = 0, $pageSize = 100, $useCache = false, $cacheTTL = 86400)
	{
		$key = "";
		$sql = "";

		if ($page > 0) {
			$sql .= 'SELECT * FROM (';
		}

		$sql .= '
			SELECT
				SUC.*
		';

		if ($page > 0) {
			$sql .= ', ROW_NUMBER() OVER (ORDER BY SPB_NAME) R ';
		}

		$sql .= '
			FROM
				SUPPLIER_MEMBERSHIP SUC
				JOIN ' . Shipserv_Supplier::TABLE_NAME . ' spb ON
					spb.' . Shipserv_Supplier::COL_ID . ' = suc.SM_SUP_BRANCH_CODE
		';

		$sqlData = array();

		if (count($filters) > 0) {
			$sql .= '
				WHERE
					' . Shipserv_Supplier::getValidSupplierConstraints('spb') . '
			'; // removed by Yuriy Akopov on 2016-10-24, S18410: SM_SUP_BRANCH_CODE NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)';

			$filterConstraints = array();
			foreach ($filters as $column => $value) {
				if (!is_null($value)) {
					$filterConstraints[] = $column . ' = :' . $column . "_FILTER";
					$sqlData[$column . "_FILTER"] = $value;
				} else {
					$filterConstraints[] = $column . ' IS NULL';
				}

				$key .= $column.$value;
			}

			$sql .= ' AND ' . implode(' AND ', $filterConstraints);
		}

		if ($page > 0) {
			$sql .= ')  WHERE R BETWEEN ' . (($page - 1) * $pageSize) . ' AND ' . ($page * $pageSize);
		}

		if ($useCache) {
			$key = $this->memcacheConfig->client->keyPrefix . 'MEMBERSHIPAUTHS' . $key . $this->memcacheConfig->client->keySuffix;
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		} else {
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}

	/**
	 * @todo: rework to Zend_Db_Select and Zend_Paginator (Yuriy Akopov)
	 *
	 * @param   array   $filters
	 * @param   int     $page
	 * @param   string  $region
	 * @param   string  $name
	 * @param   int     $pageSize
	 *
	 * @return  array
	 */
	public function fetchAuths($filters = array(), $page = 1, $region = "", $name = "", $pageSize = 100)
	{
		$db = Shipserv_Helper_Database::getDb();
		$sql = "";

		if ($page > 0) {
			$sql .= 'SELECT * FROM (';
		}

		$sql .= '
			SELECT
				SUC.*
		';

		if ($page > 0) {
			$sql .= ', ROW_NUMBER() OVER (ORDER BY SPB_NAME) R, count(*) over () as totalCount ';
		}

		$sql .= '
			FROM
				SUPPLIER_MEMBERSHIP SUC
				JOIN ' . Shipserv_Supplier::TABLE_NAME . ' spb ON
					spb.' . Shipserv_Supplier::COL_ID . ' = suc.SM_SUP_BRANCH_CODE
		';

		if ($page > 0) {
			if ($region != "") {
				$sql .= ' JOIN ' . Shipserv_Oracle_Countries::TABLE_NAME . ' cnt ON
					spb.' . Shipserv_Supplier::COL_COUNTRY . ' = cnt.' . Shipserv_Oracle_Countries::COL_CODE_COUNTRY . '
				';
			}
		}

		$sqlData = array();

		if (count($filters) > 0) {
			// removed by Yuriy Akopov on 2016-10-24, S18410: SM_SUP_BRANCH_CODE NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)
			$sql .= "
				WHERE
					(
						suc.SM_IS_AUTHORISED IS NULL OR
						suc.SM_IS_AUTHORISED = 'Y'
					)
			";
			$sql .= " AND " . Shipserv_Supplier::getValidSupplierConstraints('spb');

			if ($region != "") {
				$sql .= $db->quoteInto(" AND cnt." . Shipserv_Oracle_Countries::COL_CODE_CONTINENT . " = ?", $region);
			}

			if ($name != "") {
				$sql .= " AND LOWER(spb." . Shipserv_Supplier::COL_NAME . ") " . Shipserv_Helper_Database::escapeLike($db, strtolower($name));
			}

			$filterConstraints = array();
			foreach ($filters as $column => $value) {
				if (!is_null($value)) {
					$filterConstraints[] = $column . ' = :' . $column . "_FILTER";
					$sqlData[$column . "_FILTER"] = $value;
				} else {
					$filterConstraints[] = $column . ' IS NULL';
				}

				$sql .= ' AND ' . implode(' AND ', $filterConstraints);
			}
		}

		if ($page > 0) {
			$sql .= ')  WHERE R BETWEEN ' . ((($page - 1) * $pageSize) + 1) . ' AND ' . ($page * $pageSize);
		}

		$result = $this->db->fetchAll($sql, $sqlData);

		return $result;
	}

	/**
	 * Remove records that match filters
	 *
	 * @param array $filters
	 *
	 * @return boolean
	 */
	public function remove($filters = null)
	{
		$sqlData = array();

		$sql = 'DELETE FROM SUPPLIER_MEMBERSHIP ';

		if (is_array($filters) and count($filters) > 0) {
			$sql.= ' WHERE ';
			$isFirst = true;

			foreach ($filters as $column => $value) {
				if (!$isFirst) {
					$sql.= ' AND ';
				} else {
					$isFirst = false;
				}

				if (!is_null($value)) {
					$sql .= $column.' = :' . $column . "_FILTER";
					$sqlData[$column."_FILTER"] = $value;
				} else {
					$sql .= $column.' IS NULL ';
				}
			}

			$this->db->query($sql, $sqlData);

			//if we removing memberships from certain supplier - we need to update supplier's timestamp
			if (isset($filters['SM_SUP_BRANCH_CODE'])) {
				$this->updateSupplierTimestamp($filters['SM_SUP_BRANCH_CODE']);
			}
		}

		return true;
	}

	/**
	 * Create request or authorisation
	 *
	 * @param   int     $companyId
	 * @param   int     $membershipId
	 * @param   string  $isAuthorised
	 * @param   string  $certificateNumber
	 * @param   string  $expiryDate
	 *
	 * @return  integer
	 */
	public function store($companyId, $membershipId, $isAuthorised, $certificateNumber, $expiryDate)
	{
		$sql = "
			INSERT INTO SUPPLIER_MEMBERSHIP (
				SM_SUP_BRANCH_CODE,
				SM_QO_ID,
				SM_CERTIFICATE_NUMBER,
				SM_EXPIRY_DATE,
				SM_IS_AUTHORISED
			)
			VALUES (
				:companyId,
				:membershipId,
				:certificateNumber,
				:expiryDate,
				:isAuthorised
			)
		";

		$sqlData = array(
			'companyId'	        => $companyId,
			'membershipId'	    => $membershipId,
			'isAuthorised'	    => $isAuthorised,
			'certificateNumber'	=> $certificateNumber,
			'expiryDate'	    => $expiryDate
		);

		$this->db->query($sql, $sqlData);

		$this->updateSupplierTimestamp($companyId);
	}

	/**
	 * Transform request to authorisation
	 *
	 * @param   int     $companyId
	 * @param   int     $membershipId
	 * @param   string  $isAuthorised
	 *
	 * @return  bool
	 */
	public function authorise($companyId, $membershipId, $isAuthorised)
	{
		$sql = "
			UPDATE SUPPLIER_MEMBERSHIP SET
				SM_IS_AUTHORISED = :isAuthorised
			WHERE
				SM_SUP_BRANCH_CODE = :companyId
				AND SM_QO_ID = :membershipId
		";

		$sqlData = array(
			'companyId'	    => $companyId,
			'membershipId'	=> $membershipId,
			'isAuthorised'	=> $isAuthorised
		);
		$this->db->query($sql, $sqlData);

		$this->updateSupplierTimestamp($companyId);

		return true;
	}

	/**
	 * Update supplier's timestamp
	 *
	 * @param   int $companyId
	 *
	 * @return  bool
	 */
	public function updateSupplierTimestamp($companyId)
	{
		$sql = "
			UPDATE SUPPLIER_BRANCH SET
				SPB_UPDATED_DATE = SYSDATE
			WHERE
				SPB_BRANCH_CODE=:companyId
		";

		$sqlData = array(
			'companyId'	=> $companyId
		);
		$this->db->query($sql, $sqlData);

		return true;
	}
}
