<?php
/**
* This class is to query suppliers for a user, or belonging to a parent recursievely
*/
class Shipserv_Oracle_PagesUserSupplier extends Shipserv_Object
{
	protected $shipmate;

	/**
	* Constructor intalise values
	* @return object
	*/
	public function __construct()
	{
		$this->shipmate = false;
		if (Shipserv_User::isLoggedIn()) {
			$this->shipmate = Shipserv_User::isLoggedIn()->isShipservUser();
		}
	}

	/**
	* Fetch all suppliers belonging to a user Flat list
	* @param integer $userId User ID
	* @return array
	*/
	public function fetchSuppliersForUser($userId)
	{
		$sql = "
		SELECT 
		   sb.spb_branch_code
		  ,sb.spb_name
		  ,(SELECT count(spb_branch_code) cnt
			FROM
		  	supplier_branch csb
			WHERE 
				csb.spb_parent_branch_code = sb.spb_branch_code ";
				if ($this->shipmate !== true) {
					$sql .= "
						AND directory_entry_status = 'PUBLISHED'
						AND spb_account_deleted = 'N' ";
					}
				$sql .= "AND spb_test_account = 'N'
				AND spb_branch_code <= 999999
		    ) child_count
		FROM
		  pages_user_company puc
		  LEFT JOIN
		  supplier_branch sb
		  ON puc.puc_company_id = sb.spb_branch_code
		WHERE puc.puc_company_type = 'SPB'
			AND puc.puc_status = 'ACT'
			AND puc.puc_psu_id = :userId ";
			if ($this->shipmate !== true) {
					$sql .= "
						AND directory_entry_status = 'PUBLISHED'
						AND spb_account_deleted = 'N' ";
					}
			$sql .= "AND spb_test_account = 'N'
			AND spb_branch_code <= 999999
		ORDER BY 
		  sb.spb_branch_code";

		 $params = array('userId' => $userId);

		$res = $this->fetchCachedQuery($sql, $params, md5(__CLASS__.$sql.implode($params)));
		$data = array();
		$i = 0;
		foreach ($res as  $value) {
			$data[$i]['data'] = $this->camelCase($value);
			if ($data[$i]['data']['childCount'] > 0 ) {
				$data[$i]['children'] = $this->getChildSuppliers($data[$i]['data']['spbBranchCode']);
			}
			$i++;
		}

		return $data;
	}

	/**
	* Return a hierarchical list of suppliers based on parent 
	* @param integer $spbBranchCode Parent Supplier Branch Code
	* @return array (Multi dimensional)
	*/
	public function getSupplierTreeByBranchCode($spbBranchCode)
	{

		$sql = "
		SELECT 
		   sb.spb_branch_code
		  ,sb.spb_name
		  ,(SELECT count(spb_branch_code) cnt
			FROM
		  	supplier_branch csb
			WHERE 
				csb.spb_parent_branch_code = sb.spb_branch_code ";
		if ($this->shipmate !== true) {
			$sql .= "
				AND directory_entry_status = 'PUBLISHED'
				AND spb_account_deleted = 'N' ";
			}
			$sql .= "AND spb_test_account = 'N'
				AND spb_branch_code <= 999999
		    ) child_count
		FROM
		  supplier_branch sb
		WHERE
		  sb.spb_branch_code = :spbBranchCode ";
		if ($this->shipmate !== true) {
			$sql .= "
				AND directory_entry_status = 'PUBLISHED'
				AND spb_account_deleted = 'N' ";
			}
			$sql .= "AND spb_test_account = 'N'
			AND spb_branch_code <= 999999
		ORDER BY 
		  sb.spb_branch_code
		  ";

		 $params = array('spbBranchCode' => $spbBranchCode);

		$res = $this->fetchCachedQuery($sql, $params, md5(__CLASS__.$sql.implode($params)));
		$data = array();
		$i = 0;
		foreach ($res as  $value) {
			$data[$i]['data'] = $this->camelCase($value);
			if ($data[$i]['data']['childCount'] > 0 ) {
				$data[$i]['children'] = $this->getChildSuppliers($data[$i]['data']['spbBranchCode']);
			}
			$i++;
		}

		return $data;

	}

	/**
	* Get a list of child suppliers
	* @param integer $spbBranchCode Parent SPB Branch Code
	* @return array
	*/
	public function getChildSuppliers($spbBranchCode)
	{
		$result = array();
		$i = 0;
		$res = $this->getChildSupplierList($spbBranchCode);
		foreach ($res as $record) {
			$result[$i]['data'] =  $record;
			$children = $this->getChildSuppliers($record['spbBranchCode']);
			if (count($children) > 0) {
				$result[$i]['children'] = $children;
			}
			  
			$i++;
		}

		return $result;

	}

	/**
	* Return a list fo Child Supplier ID's in an array
	* @param integer $spbBranchCode Parent SPB Branch code
	* @return array
	*/
	public function getChildSupplierIds($spbBranchCode)
	{
		$result = array();
		$i = 0;
		$res = $this->getChildSupplierList($spbBranchCode);
		foreach ($res as $record) {
			$result[] =  (int)$record['spbBranchCode'];
			$children = $this->getChildSupplierIds($record['spbBranchCode']);

			if (count($children) > 0) {
				$result = array_merge($result, $children);
			}
			  
			$i++;
		}

		return $result;

	}

	/**
	* Return the list of children 
	* @param integer $spbBranchCode Parent SPB Branch Code
	* @return array
	*/
	protected function getChildSupplierList($spbBranchCode)
	{
		$sql = "SELECT 
				  sb.SPB_BRANCH_CODE
				  ,sb.SPB_NAME
				  , (SELECT count(SPB_BRANCH_CODE) cnt
				    FROM
				      SUPPLIER_BRANCH csb
				    WHERE 
				     	csb.SPB_PARENT_BRANCH_CODE = sb.SPB_BRANCH_CODE ";
						if ($this->shipmate !== true) {
							$sql .= "
								AND directory_entry_status = 'PUBLISHED'
								AND spb_account_deleted = 'N' ";
							}
						$sql .= "AND spb_test_account = 'N'
						AND spb_branch_code <= 999999
				    ) child_count
				FROM supplier_branch sb
				WHERE sb.SPB_PARENT_BRANCH_CODE = :spbBranchCode  AND sb.SPB_BRANCH_CODE != :spbBranchCode ";
				if ($this->shipmate !== true) {
					$sql .= "
						AND directory_entry_status = 'PUBLISHED'
						AND spb_account_deleted = 'N' ";
					}
				$sql .= "AND spb_test_account = 'N'
				AND spb_branch_code <= 999999
				";
				
				$params = array('spbBranchCode' => $spbBranchCode);
				$res = $this->fetchCachedQuery($sql, $params, md5(__CLASS__.$sql.implode($params)));
				$data = array();
				foreach ($res as  $value) {
					$data[] = $this->camelCase($value);
				}
		
		return $data;

	}

}