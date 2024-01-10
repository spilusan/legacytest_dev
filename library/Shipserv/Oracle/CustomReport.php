<?php
/**
 * Class for dealing with PAGES_SVR_USER_CUSTOM_REPORT
 * 
 * @copyright Copyright (c) 2009, ShipServ
 * @todo retrieve list of custom report that is accessible by a user depending on the rights + company
 * 
 */
class Shipserv_Oracle_CustomReport extends Shipserv_Oracle
{
	public function __construct (&$db)
	{
		parent::__construct($db);
	}
	
	public function insert( $title, $description, $userId, $tnid, $data )
	{
		$db = $this->db;
		$sql = 'INSERT INTO pages_svr_user_custom_report';
		$sql .= ' (svr_title, svr_description, svr_usr_user_code, svr_spb_branch_code, svr_date_created, svr_date_updated, svr_data)';
		$sql .= ' VALUES(:svrTitle, :svrDescription, :svrUsrUserCode, :svrSpbBranchCode, SYSDATE, SYSDATE, :svrData)';
		
  		$sqlData = array( "svrTitle" => $title
						, "svrDescription" => $description
						, "svrUsrUserCode" => $userId
						, "svrSpbBranchCode" => $tnid
						, "svrData" => $data );
						
		$db->beginTransaction();
		try{	
			$db->query($sql, $sqlData);
  			$db->commit();
  			return true;
		}
		catch( ControlPanel_Exception $e )
		{
			$db->rollback();
			return false;
		}
	}
	

	public function update( $id, $title = "", $description = "", $userId = "", $tnid = "", $data = "" )
	{
		$db = $this->db;
		$sql = 'UPDATE pages_svr_user_custom_report';
		$sql .= ' SET svr_title=:svrTitle, 
					  svr_description = :svrDescription, 
					  svr_usr_user_code = :svrUsrUserCode, 
					  svr_spb_branch_code = :svrSpbBranchCode,
					  svr_date_updated = SYSDATE, 
					  svr_data = :svrData
				WHERE svr_id = :svrId
		';
		
  		$sqlData = array( "svrId" => $id
  					    , "svrTitle" => $title
						, "svrDescription" => $description
						, "svrUsrUserCode" => $userId
						, "svrSpbBranchCode" => $tnid
						, "svrData" => $data );
						
		$db->beginTransaction();
		try{	
			$db->query($sql, $sqlData);
  			$db->commit();
  			return true;
		}
		catch( ControlPanel_Exception $e )
		{
			$db->rollback();
			return false;
		}
	}
	
		
	
	
	public function fetch ( $filters = array() )
	{
		$sql = 'SELECT * FROM pages_svr_user_custom_report';
		
		if( count( $filters ) > 0 )
		{
			if (count($filters)>0)
			{
				$sql .= $this->processFilter( $filters, $key, $sqlData);
			}
			
		}
		
		$sql .= ' ORDER BY svr_title';
		
			return $this->db->fetchAll($sql, array());
	}
	
}




?>
