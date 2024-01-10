<?php
/**
 * Base level queries for branch authorisation related operations
 *
 * Refactored by Yuriy Akopov on 2016-08-25, DE16813
 */
class Shipserv_Oracle_BrandAuthorisation extends Shipserv_Oracle
{
	/**
	 * Retrieve records that match supplied filter
	 *
	 * @param   array   $filters
	 * @param   int     $page
	 * @param   int     $pageSize
	 * @param   bool    $useCache
	 * @param   int     $cacheTTL
	 *
	 * @return  array
	 */
	public function fetch($filters = array(), $page = 0, $pageSize = 20, $useCache = false, $cacheTTL = 86400)
	{
		$sql = '';

		if ($page > 0) {
			$sql .= 'SELECT * FROM (';
		}

		$sql .= 'SELECT PCB.*';

		if ($page > 0) {
			$sql .= ', ROW_NUMBER() OVER (ORDER BY SPB_NAME) R ';
		}

		$sql .= ' FROM PAGES_COMPANY_BRANDS PCB ';

		if ($page > 0) {
			$sql .= ' LEFT JOIN SUPPLIER_BRANCH ON (SPB_BRANCH_CODE=PCB_COMPANY_ID) ';
		}

		$sqlData = array();

		// by default select non-deleted records
		if (!isset($filters['PCB_IS_DELETED'])) {
			$filters['PCB_IS_DELETED'] = 'N';
		}

		// DE6813: disabled check for supplier normalisation as redundant
		if (!empty($filters)) {
			$sql .= ' WHERE ';
		}

		/*
		if ($onlyNonNorm or (count($filters) > 0)) {
			$sql.= ' WHERE ';
		}

		if ($onlyNonNorm) {
			$sql .= ' PCB_COMPANY_ID NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM) ';
		}
		*/

		$key = '';
		if (count($filters) > 0) {
			$isFirst = true;

			foreach ($filters as $column => $value) {
				if (!$isFirst) {
					$sql.= ' AND ';
				} else {
					$isFirst = false;
				}

				if (!is_null($value)) {
					if (is_array($value)) {
						$sql .= $column.' IN (' . $this->_arrToSqlList($value) .') ';
					} else {
						$sql .= $column . ' = :' . $column . '_FILTER';
						$sqlData[$column . '_FILTER'] = $value;
					}
				} else {
					$sql .= ' ('. $column.' IS NULL) ';
				}

				$key .= $column.$value;
			}
		}

		if ($page > 0) {
			$sql .= ')  WHERE R BETWEEN ' . (($page - 1) * $pageSize) . ' and ' . ($page * $pageSize);
		}

		if ($useCache) {
			Myshipserv_Config::decorateMemcacheKey('BRANDAUTHS' . $key);
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		} else {
		    $result = Shipserv_Helper_Database::registryFetchAll(__CLASS__ . '_' . __FUNCTION__ .'_' . $sql . '_' . serialize($sqlData), $sql, $sqlData);
		}

		return $result;
	}

	/**
	 * Remove records that match filters
	 *
	 * @param   array   $filters
	 * @param   bool    $permanently
	 *
	 * @return boolean
	 */
	public function remove(array $filters = null, $permanently = false)
	{
		$sqlData = array();

		if ($permanently) {
			$sql = 'DELETE FROM PAGES_COMPANY_BRANDS ';
		} else {
			$sql = "
				UPDATE PAGES_COMPANY_BRANDS SET
					PCB_IS_DELETED = 'Y', PCB_DATE_DELETED = SYSDATE
			";
		}

		if (is_array($filters) and count($filters) > 0 ) {
			$sql .= " WHERE ";
			$isFirst = true;

			foreach ($filters as $column => $value) {
				if (!$isFirst) {
					$sql .= ' AND ';
				} else {
					$isFirst = false;
				}

				if (!is_null($value)) {
					if (is_array($value)) {
						$sql .= $column . ' IN (' . $this->_arrToSqlList($value) . ') ';
					} else {
						$sql .= $column . ' = :' . $column . '_FILTER';
						$sqlData[$column . '_FILTER'] = $value;
					}
				} else {
					$sql .= $column . ' IS NULL ';
				}
			}

			$result = $this->db->query($sql, $sqlData);

			// if we removing brands from certain supplier -we need to update supplier's timestamp
			if (isset($filters['PCB_COMPANY_ID'])) {
				$this->updateSupplierTimestamp($filters['PCB_COMPANY_ID']);
			}
		}

		return true;
	}

	/**
	 * Create request or authorisation
	 *
	 * @param   int     $companyId
	 * @param   int     $brandId
	 * @param   string  $authLevel
	 * @param   string  $isAuthorised
	 *
	 * @return  int
	 */
	public function store($companyId, $brandId, $authLevel, $isAuthorised)
	{
		//check if authorisation was previously granted but then deleted
		if ($auth = $this->fetch(
			array(
				'PCB_BRAND_ID'      => $brandId,
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_AUTH_LEVEL'    => $authLevel,
				'PCB_IS_DELETED'    => 'Y'
			)
		)) {
			// deleted record exists, restore it.
			$sql = "
				UPDATE PAGES_COMPANY_BRANDS SET
					PCB_IS_DELETED = 'N'
				WHERE
					PCB_COMPANY_ID = :companyId
					AND PCB_BRAND_ID = :brandId
					AND PCB_AUTH_LEVEL = :authLevel
					AND PCB_IS_DELETED = 'Y'
			";

			$sqlData = array(
				'companyId'	=> $companyId,
				'brandId'	=> $brandId,
				'authLevel'	=> $authLevel
			);
			$this->db->query($sql, $sqlData);

			$createdId = $auth['PCB_ID'];

		} else {
			$sql = "
				INSERT INTO PAGES_COMPANY_BRANDS (
					PCB_ID, PCB_COMPANY_ID, PCB_BRAND_ID, PCB_AUTH_LEVEL, PCB_IS_AUTHORISED, PCB_DATE_CREATED
				)
				VALUES (
					SEQ_PAGES_COMP_BRANDS.nextval, :companyId, :brandId, :authLevel, :isAuthorised, SYSDATE
				)
			";

			$sqlData = array(
				'companyId'	    => $companyId,
				'brandId'	    => $brandId,
				'authLevel'	    => $authLevel,
				'isAuthorised'	=> $isAuthorised
			);

			$this->db->query($sql, $sqlData);

			$createdId = $this->db->lastSequenceId('SEQ_PAGES_COMP_BRANDS');

			if ($isAuthorised === 'Y') {
				$sql = "
					UPDATE PAGES_COMPANY_BRANDS SET
						PCB_DATE_APPROVED = SYSDATE
					WHERE
						PCB_ID = :recordId
				";

				$sqlData = array(
					'recordId'	=> $createdId
				);

				$this->db->query($sql, $sqlData);
			}

			if ($isAuthorised === 'N') {
				$sql = "
					UPDATE PAGES_COMPANY_BRANDS SET
						PCB_DATE_REQUESTED = SYSDATE
					WHERE
						PCB_ID = :recordId
				";

				$sqlData = array(
					'recordId' => $createdId
				);

				$this->db->query($sql, $sqlData);
			}
		}

		$this->updateSupplierTimestamp($companyId);

		return $createdId;
	}

	/**
	 * Transfrom request to authorisation
	 *
	 * @param   int     $companyId
	 * @param   int     $brandId
	 * @param   string  $authLevel
	 * @param   string  $isAuthorised
	 *
	 * @return  bool
	 */
	public function authorise($companyId, $brandId, $authLevel, $isAuthorised)
	{
		$sql = "
			UPDATE PAGES_COMPANY_BRANDS SET
				PCB_DATE_APPROVED = SYSDATE,
				PCB_IS_AUTHORISED = :isAuthorised
			WHERE
				PCB_COMPANY_ID = :companyId
				AND PCB_BRAND_ID = :brandId
				AND PCB_AUTH_LEVEL = :authLevel
		";

		$sqlData = array(
			'companyId'	=> $companyId,
			'brandId'	=> $brandId,
			'authLevel'	=> $authLevel,
			'isAuthorised'	=> $isAuthorised
		);

		$this->db->query($sql, $sqlData);

		$this->updateSupplierTimestamp($companyId);

		return true;
	}

	/**
	 * Transfrom request to authorisation
	 *
	 * @param   int     $companyId
	 * @param   int     $brandId
	 * @param   string  $authLevel
	 * @param   string  $isAuthorised
	 *
	 * @return  boolean
	 */
	public function deauthorise($companyId, $brandId, $authLevel, $isAuthorised)
	{
		$sql = "
			UPDATE PAGES_COMPANY_BRANDS SET
				PCB_IS_AUTHORISED = :isAuthorised
			WHERE
				PCB_COMPANY_ID = :companyId
				AND PCB_BRAND_ID = :brandId
				AND PCB_AUTH_LEVEL = :authLevel
		";

		$sqlData = array(
			'companyId'	    => $companyId,
			'brandId'	    => $brandId,
			'authLevel'	    => $authLevel,
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
				SPB_BRANCH_CODE = :companyId
		";

		$sqlData = array(
			'companyId'	=> $companyId
		);

		$this->db->query($sql, $sqlData);

		return true;
	}

	/**
	 * @param   array   $arr
	 *
	 * @return  string
	 */
	private function _arrToSqlList($arr)
	{
		$sqlArr = array();

		foreach ($arr as $item) {
			$sqlArr[] = $this->db->quote($item);
		}

		if (!$sqlArr) {
			$sqlArr[] = 'NULL';
		}

		return join(', ', $sqlArr);
	}
}