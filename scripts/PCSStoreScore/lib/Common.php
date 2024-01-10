<?php
/**
 * This class will be extended
 * @author elvirleonard
 */
class PCS_Common
{
	protected $db = null;
	
	function __construct () 
	{ 
		$this->db = $GLOBALS['application']->getBootstrap()->getResource('db');	
	}
	
	/**
	 * Get user by userId
	 * @param unknown_type $userId
	 */
	public function getUserById( $userId )
	{
		return Shipserv_User::getInstanceById( $userId );
	}
}

class PCS_Score extends PCS_Common
{
	protected function getNextBatch()
	{
		$sql = "SELECT spb_branch_code FROM supplier_branch WHERE ";
		$sql.= " spb_account_deleted = 'N'";
		$sql.= " AND spb_test_account = 'N'";
		$sql.= " AND spb_branch_code <= 999999";
		$sql.= " AND spb_branch_code NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)";
		return $this->db->fetchAll($sql);
	}
	
	public function resetScores()
	{
		$sql = "UPDATE SUPPLIER_BRANCH SET SPB_PCS_SCORE=NULL WHERE SPB_PCS_SCORE IS NOT NULL";
		return $this->db->query($sql);
	}
	
	public function run()
	{
		$score = array();
		foreach( (array)$this->getNextBatch() as $row )
		{
			$profile = new Myshipserv_SupplierListing( $row["SPB_BRANCH_CODE"], $this->db );
			$supplier = Shipserv_Supplier::fetch($row["SPB_BRANCH_CODE"], $this->db);
			$score = $profile->getCompletenessAsPercentage();
			Logger::log('[' . $supplier->tnid . '] - ' . $profile->getCompletenessAsPercentage() . '% - ' . $supplier->name);
			
			$sql = 'UPDATE SUPPLIER_BRANCH SET SPB_PCS_SCORE=:score WHERE spb_branch_code=:tnid';
			$this->db->query($sql, array('tnid' => $row["SPB_BRANCH_CODE"], 'score' => $score));
		}
	}
}
