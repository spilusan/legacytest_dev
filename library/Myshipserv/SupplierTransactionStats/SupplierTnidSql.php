<?php

/**
 * Represents a string of SQL representing a list of supplier ids suitable
 * for injection into an 'IN( ... )' expression. This may be a literal list of
 * ids, or a subquery. It may also evaluate to an empty string, in which case
 * client must omit 'IN' clause altogether.
 */
class Myshipserv_SupplierTransactionStats_SupplierTnidSql
{
	private $sqlStr;
	
	/**
	 * Form a literal list of ids, e.g. '123, 456, 789, ...',
	 * or if array is empty, degrades to self::allSuppliers().
	 */
	public static function fromTnidArr (Zend_Db_Adapter_Oracle $db, array $supplierTnids)
	{
		if ($supplierTnids)
		{
			foreach ($supplierTnids as $k => $v) $supplierTnids[$k] = $db->quote($v);
			$sqlStr = join(', ', $supplierTnids);
			return new self($sqlStr);
		}
		
		return self::allSuppliers();
	}
	
	/**
	 * Form a subquery fetching all supplier ids which are in the same
	 * categories as the supplier ids specified.
	 */
	public static function fromTnidArrViaCategories (Zend_Db_Adapter_Oracle $db, array $supplierTnids)
	{
		$sqlGen = new Myshipserv_SupplierTransactionStats_SupplierCategorySql($db);
		$tnidSql = $sqlGen->suppliersFromSuppliersViaCategories($supplierTnids);
		return new self($tnidSql);
	}
	
	/**
	 * Produces an empty string: clients must be able to handle this!
	 */
	public static function allSuppliers ()
	{
		return new self('');
	}
	
	/**
	 * Private constructor: use a static factory method.
	 */
	private function __construct ($sqlStr)
	{
		$this->sqlStr = $sqlStr;
	}
	
	public function __toString ()
	{
		return $this->sqlStr;
	}
}
