<?php
class Shipserv_Report_erroneousTransactions_Confirm
{

	private static $instance;
	 /**
	 * Hide all stuff we do not want to be accessible for singleton class
	 * *Singleton* via the `new` operator from outside of this class.
	 */
	protected function __construct() {}
	private function __clone() {}

	public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }

	public function confirm($ordInternalRefNo)
	{
		$sql = "
				UPDATE
					erroneous_txn_notification
				SET
				  etn_supplier_response = 'ACC'
				WHERE
				  etn_doc_type = 'ORD'
				  and etn_ord_internal_ref_no = :ordInternalRefNo";

			$db = Shipserv_Helper_Database::getSsreport2Db();
			$db->query($sql, array('ordInternalRefNo' => (int)$ordInternalRefNo));
	}

}

