<?php

class Shipserv_Oracle_AccessCode extends Shipserv_Oracle
{
	public function fetchByTnids (array $tnids)
	{
		$tnidSql = $this->quoteArr($tnids);
		$sql = "SELECT * FROM ACCESS_CODE WHERE TNID IN ($tnidSql)";
		return $this->db->fetchAll($sql);
	}
	
	/**
	 * Quote array of values for SQL.
	 *
	 * @return string
	 */
	private function quoteArr (array $vals)
	{
		$quotedArr = array();
		foreach ($vals as $v) $quotedArr[] = $this->db->quote($v);
		
		if ($quotedArr) $vSql = join(', ', $quotedArr);
		else $vSql = 'NULL';
		
		return $vSql;
	}
}
