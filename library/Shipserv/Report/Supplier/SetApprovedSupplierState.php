<?php

/**
 * Class for setting state of Approved supplier states
 *
 * @author Attila O  2015-04-09
 * 
 */

class Shipserv_Report_Supplier_SetApprovedSupplierState extends Shipserv_Report
{

	const
    	//DB = 'ssreport2'
    	DB = 'sservdba'
    	;

	public function setRaseAlertState($orgCode, $status)
	{
		$error = '';

		$sql = "
		MERGE INTO
			buyer_org_setting b
		USING
		 (SELECT
		 	count(bos_byo_org_code) bos_count
		 FROM
		 	buyer_org_setting
   		WHERE
       bos_byo_org_code = :orgCode) s
   		ON
   			(s.bos_count > 0)
   		WHEN
   		 	 MATCHED THEN
   		 	 	UPDATE SET b.bos_approved_supplier_enabled = :status
   		WHEN
   			 NOT MATCHED THEN
   				INSERT
   		 			(b.bos_byo_org_code, b.bos_approved_supplier_enabled )
     			VALUES
     				(:orgCode, :status)";

     	$params = array(
     		'orgCode' => (int) $orgCode,
     		'status' => $status,
     		);

     	$db = $this->getDbByType(self::DB);


		try {
		  $db->Query($sql, $params);
		} catch (Exception $e) {
		  $error = $e->getMessage();
		}

		return array(
        	'error' => $error,
        	'currentStatus' => $status,
        	);

	}

}