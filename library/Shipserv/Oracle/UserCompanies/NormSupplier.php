<?php

/**
 * Canonicalizes supplier branch ids
 */
class Shipserv_Oracle_UserCompanies_NormSupplier
{
	private $db;
	private $ids = array();
	
	public function __construct ($db)
	{
		$this->db = $db;
	}
	
	/**
	 * Add an id to be canonicalized.
	 */
	public function addId ($id)
	{
		if (is_scalar($id)) $this->ids[$id] = null;
	}
	
	/**
	 * Takes each id and uses PAGES_SPB_NORM to determine if the id is valid
	 * and not a duplicate. Returns an array:
	 * 
	 * { <original id> => <canonical id>, ... }
	 *
	 * Ids which do not exist in SUPPLIER_BRANCH will map to null.
	 * Existing ids, which are mapped in PAGES_SPB_NORM will map to their target
	 * branch code, or null if the target id does not exist.
	 * 
	 * @return array
	 */
	public function canonicalize ()
	{
		$sql =
			"SELECT a.SPB_BRANCH_CODE ID1, b.PSN_SPB_BRANCH_CODE ID2, c.SPB_BRANCH_CODE ID3
				FROM SUPPLIER_BRANCH a
					LEFT JOIN PAGES_SPB_NORM b ON b.PSN_SPB_BRANCH_CODE = a.SPB_BRANCH_CODE
					LEFT JOIN SUPPLIER_BRANCH c ON c.SPB_BRANCH_CODE = b.PSN_NORM_SPB_BRANCH_CODE
				WHERE a.SPB_BRANCH_CODE IN (" . $this->arrToSqlList(array_keys($this->ids)) . ")";
		
		$rows = $this->db->fetchAll($sql);
		
		// Take a copy of ids
		$myIds = $this->ids;
		
		// Only IDs present in SUPPLIER_BRANCH table are looped over ...
		foreach ($rows as $r)
		{
			// If id exists, but is not in the mapping ...
			if ($r['ID2'] === null)
			{
				// Id is canonical, so map to self
				$myIds[$r['ID1']] = $r['ID1'];
			}
			
			// If id is in the mapping ...
			else
			{
				// If normed id successfully maps to an existing supplier branch id ...
				if ($r['ID3'] !== null)
				{
					// Add 'redirect' to mapping
					$myIds[$r['ID1']] = $r['ID3'];
				}
			}
		}
		
		return $myIds;
	}
	
	/**
	 * Transforms values of input array into a quoted list suitable for
	 * a SQL in clause: e.g. 3, 'str val', ...
	 *
	 * @return string
	 */
	private function arrToSqlList ($arr)
	{
		$sqlArr = array();
		foreach ($arr as $item)
		{
			$sqlArr[] = $this->db->quote($item);
		}
		if (!$sqlArr) $sqlArr[] = 'NULL';
		return join(', ', $sqlArr);
	}
}
