<?php
class Shipserv_Oracle_Zone_HighlightBanner extends Shipserv_Oracle
{
	public function __construct ($db = null)
	{
		if( $db == null )
		{
			$this->db = Shipserv_Helper_Database::getDb();
		}
		else 
		{
			$this->db = $db;
		}
	}
	
	public function fetchById ($id)
	{
		$sql = "SELECT * FROM pages_zone_sponsorship WHERE pzs_id=:id";
		$data = $this->db->fetchAll($sql, array('id' => $id) );
		return $data[0];
	}
	
}