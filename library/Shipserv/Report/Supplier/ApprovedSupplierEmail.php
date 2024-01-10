<?php

/**
 * Class for setting state of Approved supplier states
 *
 * @author Attila O  2015-04-09
 * 
 */

class Shipserv_Report_Supplier_ApprovedSupplierEmail extends Shipserv_Report
{

	const
    	//DB = 'ssreport2'
    	DB = 'sservdba'
    	;

	public function getEmailList($orgCode)
	{
		$error = '';
		$sql = "SELECT
 					bsm_mail
				FROM
  					buyer_supplier_mailsappr
				WHERE
 					bsm_byo_org_code = :orgCode
				ORDER BY
 					bsm_mail";

     	$params = array(
     		'orgCode' => (int) $orgCode,
     		);

     	$db = $this->getDbByType(self::DB);

		try {
		  $result = $db->fetchAll($sql, $params);
		} catch (Exception $e) {
			throw new Exception($e->getMessage(), 500);
		}

		return $result;
	}

	public function insertEmail($orgCode, $email)
	{
		$this->storeEmail($orgCode, $email);
		return $this->getEmailList($orgCode);
	}

	public function removeEmail($orgCode, $email)
	{
		$this->deleteEmail($orgCode, $email);
		return $this->getEmailList($orgCode);
	}


	protected function deleteEmail($orgCode, $email)
	{
		$sql = "DELETE FROM 
				 buyer_supplier_mailsappr
				WHERE 
				(bsm_byo_org_code=:orgCode and  bsm_mail=:email)";

     	$params = array(
     		'orgCode' => (int) $orgCode,
     		'email' => $email,
     		);

     	$db = $this->getDbByType(self::DB);


		try {
		  $db->Query($sql, $params);
		} catch (Exception $e) {
			throw new Exception("Error Processing Request "+$e->getMessage(), 500);
		}
	}

	protected function storeEmail($orgCode, $email)
	{
		$sql = "INSERT INTO
				 buyer_supplier_mailsappr
				(bsm_byo_org_code, bsm_mail)
				VALUES 
				(:orgCode, :email)";

     	$params = array(
     		'orgCode' => (int) $orgCode,
     		'email' => $email,
     		);

     	$db = $this->getDbByType(self::DB);


		try {
		  $db->Query($sql, $params);
		} catch (Exception $e) {
			throw new Exception("Error Processing Request "+$e->getMessage(), 500);
		}
	}

}