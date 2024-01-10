<?php

/**
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Order extends Shipserv_Object
{	
	public function __construct ($data)
	{
		// populate the supplier object
		if (is_array($data))
		{
			foreach ($data as $name => $value)
			{
				$this->{$name} = $value;
			}
		}
	}
	
	private function createObjectFromDb( $data )
	{
		$object = new self($data);
		
		return $object;
	}
	
	public static function getInstanceById($id, $useArchive = false)
	{
		$sql = "SELECT * FROM purchase_order WHERE ord_internal_ref_no=:docId";
		$row = parent::getDb()->fetchAll($sql, array('docId' => $id));
		if ($useArchive === true) {
			if (!$row) {
				$sql = "SELECT * FROM purchase_order_arc WHERE ord_internal_ref_no=:docId";
				$row = parent::getDb()->fetchAll($sql, array('docId' => $id));
			}
		}

		$data = parent::camelCase($row[0]);
		return self::createObjectFromDb($data);
	}
	
	
	public function getUrl()
	{
		$users = Shipserv_User::getActiveUserBySpbBranchCode($this->ordSpbBranchCode);
		$url = "http://". $this->getHostname() . "/printables/app/print?docid=". $this->ordInternalRefNo . "&usercode=" . $users[0]['USR_USER_CODE'] . "&branchcode=" . $this->ordSpbBranchCode . "&doctype=ORD&custtype=supplier&md5=" . $users[0]['USR_MD5_CODE'] . "";
		return $url;
	}
	
	public static function getPrintableUrl($id)
	{
		return "/user/printable?d=ord&id=" . $id . "&h=" . md5('ord' . $id);
	}
}
