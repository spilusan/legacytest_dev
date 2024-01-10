<?php

/**
 * Mange PAGES_MODEL table.
 */
class Shipserv_Oracle_Model extends Shipserv_Oracle
{
	/**
	 * Returns null if no row is found for ID.
	 * 
	 * @return array
	 */
	public function fetchById ($id)
	{
		$sql = "SELECT * FROM PAGES_MODEL WHERE ID = :id";
		$rowArr = $this->db->fetchAll($sql, array('id' => $id));
		if (count($rowArr) == 0)
		{
			return null;
		}
		else
		{
			return $rowArr[0];
		}
	}
	
	/**
	 * @return array
	 */
	public function fetchByProductId ($productId)
	{
		$sql = "SELECT * FROM PAGES_MODEL WHERE PRODUCT_ID = :productId";
		return $this->db->fetchAll($sql, array('productId' => $productId));
	}
}
