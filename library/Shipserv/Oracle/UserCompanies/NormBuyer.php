<?php

class Shipserv_Oracle_UserCompanies_NormBuyer
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
	 * Takes each id and uses PAGES_BYO_NORM to determine if the id is valid
	 * and not a duplicate. Returns an array:
	 * 
	 * { <original id> => <canonical id>, ... }
	 *
	 * Ids which do not exist in BUYER_ORGANISATION will map to null.
	 * Existing ids, which are mapped in PAGES_BYO_NORM will map to their target
	 * organisation code, or null if the target id does not exist.
	 * 
	 * @return array
	 */
	public function canonicalize ()
	{
		// Query for ids in mapping table, left joining to buyer org table
		$sql =
			"SELECT a.BYO_ORG_CODE ID1, b.PBN_BYO_ORG_CODE ID2, c.BYO_ORG_CODE ID3
				FROM BUYER_ORGANISATION a
					LEFT JOIN PAGES_BYO_NORM b ON b.pbn_byo_org_code = a.BYO_ORG_CODE
					LEFT JOIN BUYER_ORGANISATION c ON c.BYO_ORG_CODE = b.PBN_NORM_BYO_ORG_CODE
				WHERE a.BYO_ORG_CODE IN (" . $this->arrToSqlList(array_keys($this->ids)) . ")";
		
		$rows = $this->db->fetchAll($sql);
		
		// Take a copy of ids
		$myIds = $this->ids;
		
		// Only IDs present in buyer org table are looped over ...
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
				// If normed id successfully maps to an existing buyer org id ...
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
