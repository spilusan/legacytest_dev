<?php

class Shipserv_Oracle_Util_PagedQueryRunner
{
	private $db;
	
	public function __construct ($db)
	{
		$this->db = $db;
	}
	
	/**
	 * Execute paged query. Returns e.g.
	 *  array(
	 *   'firstOffset' => <offset of first row>
	 *   'rows' => <result set as array>
	 *  )
	 * 
	 * @param int $firstOffset Offset of first row (starting from 0)
	 * @param int $pageSize Max number of rows to return
	 * @param array Statement parameters
	 * @return array
	 */
	public function execute ($sql, $firstOffset, $pageSize, $params = array())
	{
		// Adjust $firstOffset to start from 1 and sanitize
		$firstOffset = ((int) $firstOffset) + 1;
		if ($firstOffset < 1)
		{
			$firstOffset = 1;
		}
		
		// Calculate $lastOffset and sanitize (at least 1 row)
		$lastOffset = $firstOffset + ((int) $pageSize - 1);
		if ($lastOffset < $firstOffset)
		{
			$lastOffset = $firstOffset;
		}
		
		// Wrap SQL in paging SQL
		$sql = "SELECT * FROM (SELECT A.*, ROWNUM R FROM ($sql) A WHERE ROWNUM <= $lastOffset) WHERE R >= $firstOffset";
		
		// Form return variable
		$res = array('rows' => $this->db->fetchAll($sql, $params));
		
		// Return sanitized first offset & page size (adjusting offset back to 0 start)
		$res['firstOffset'] = $firstOffset - 1;
		$res['pageSize'] = $lastOffset - $firstOffset + 1;
		
		return $res;
	}
}
