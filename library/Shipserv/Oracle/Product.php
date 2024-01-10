<?php

/**
 * Mange PAGES_PRODUCT table.
 */
class Shipserv_Oracle_Product extends Shipserv_Oracle
{
	public function __construct (&$db = null)
	{
		if( $db === null )
			$db = $this->getDb();
		parent::__construct($db);
	}
	
	/**
	 * Returns null if no row is found for ID.
	 * 
	 * @return array
	 */
	public function fetchById ($id)
	{
		$sql = "
			SELECT 
				a.*,
		        c.ID BRAND_ID,
		        c.NAME BRAND_NAME
			FROM 
				PAGES_PRODUCT a JOIN PAGES_BRAND_PRODUCT b ON (a.ID=b.PRODUCT_ID)
				JOIN BRAND c ON (b.BRAND_ID=c.ID)
			WHERE 
				a.ID = :id
		";
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
	public function fetchByBrandId ($brandId)
	{
		$hasModelsExpr = "DECODE((SELECT COUNT(*) FROM PAGES_MODEL WHERE PRODUCT_ID = a.ID), 0, 'N', 'Y')";
		
		$sql = "SELECT a.*, $hasModelsExpr HAS_MODELS FROM PAGES_PRODUCT a INNER JOIN PAGES_BRAND_PRODUCT b ON a.ID = b.PRODUCT_ID WHERE b.BRAND_ID = :brandId";
		return $this->db->fetchAll($sql, array('brandId' => $brandId));
	}
}
