<?php

/**
 * Generates SQL to fetch suppliers within a category and vice-versa.
 */
class Myshipserv_SupplierTransactionStats_SupplierCategorySql
{
	const CATS_FROM_SUPPLIERS = "SELECT DISTINCT PRODUCT_CATEGORY_ID FROM SUPPLY_CATEGORY WHERE SUPPLIER_BRANCH_CODE IN (%s)";
	
	const SUPPLIERS_FROM_CATS = "SELECT DISTINCT SUPPLIER_BRANCH_CODE FROM SUPPLY_CATEGORY WHERE PRODUCT_CATEGORY_ID IN (%s)";
	
	public function __construct (Zend_Db_Adapter_Oracle $db)
	{
		$this->db = $db;
	}
	
	private function sqlizeArr (array $arr)
	{
		foreach ($arr as $k => $v) $arr[$k] = $this->db->quote($v);
		return join(', ', $arr);
	}
	
	/**
	 * Forms SQL to fetch categories to which specified suppliers belong.
	 * 'All (distinct) categories for the given suppliers'
	 */
	public function categoriesFromSuppliers (array $supplierTnidArr)
	{
		return sprintf(self::CATS_FROM_SUPPLIERS, $this->sqlizeArr($supplierTnidArr));
	}
	
	/**
	 * Forms SQL to fetch suppliers who belong to specified categories.
	 * 'All (distinct) suppliers in the given categories'.
	 */
	public function suppliersFromCategories (array $categoryIdArr)
	{
		return sprintf(self::SUPPLIERS_FROM_CATS, $this->sqlizeArr($categoryIdArr));
	}
	
	/**
	 * Forms SQL to fetch suppliers who belong to the categories to which the
	 * specified suppliers belong.
	 * 'All (distinct) suppliers who are in the same categories as the
	 * given suppliers'.
	 */
	public function suppliersFromSuppliersViaCategories(array $supplierTnidArr)
	{
		return sprintf(
			self::SUPPLIERS_FROM_CATS,
			sprintf(self::CATS_FROM_SUPPLIERS, $this->sqlizeArr($supplierTnidArr))
		);
	}
}
