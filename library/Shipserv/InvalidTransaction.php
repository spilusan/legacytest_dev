<?php

/**
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_InvalidTransaction extends Shipserv_Object
{
	const DOC_TYPE_ORD = "ORD";
	const DOC_TYPE_POC = "POC";

	public function __construct ($data)
	{

		if (is_array($data))
		{
			foreach ($data as $name => $value)
			{
				$this->{$name} = $value;
			}
		}
	}

	private static function createObjectFromDb( $data, $docTypes )
	{
		$data = parent::camelCase($data);

		$object = new self($data);
		$object->docTypes = $docTypes;
		if( count($docTypes) == 1 )
		{
			$object->docType = $docTypes[0];
		}

		return $object;
	}

	public static function getInstances()
	{
		$db = Shipserv_Helper_Database::getSsreport2Db();

    	$sql = "
			SELECT
			  DISTINCT
    			ord.spb_branch_code
			  , ord.byb_branch_code
			  , rfq.rfq_internal_ref_no
			  , qot.qot_internal_ref_no
			  , ord.ord_internal_ref_no
			  , ord.ord_currency
			  , ord.ord_total_cost
			  , poc.poc_currency
			  , poc.poc_total_cost
			  , inv_txn.itx_doc_type
			  , inv_txn.itx_comment
    	 	FROM
			  invalid_transaction inv_txn
			  , ord
			  , poc
			  , qot
			  , rfq
			WHERE
			  ord.ord_internal_ref_no=inv_txn.itx_ord_internal_ref_no
			  AND ord.qot_internal_ref_no=qot.qot_internal_ref_no (+)
			  AND qot.rfq_internal_ref_no=rfq.rfq_internal_ref_no (+)
			  AND ord.ord_internal_ref_no=poc.ord_internal_ref_no (+)
    	";

		foreach( $db->fetchAll($sql) as $row )
		{
			$data[$row['ORD_INTERNAL_REF_NO']]['docType'][] = $row['ITX_DOC_TYPE'];
			$data[$row['ORD_INTERNAL_REF_NO']]['row'] = $row;

		}

		foreach ($data as $ordId => $d)
		{
			$objects[] = self::createObjectFromDb($data[$ordId]['row'], $data[$ordId]['docType']);
		}

		return $objects;
	}

	public static function getInstancesByOrdInternalRefNo($docId, $docType = array())
	{
		$db = Shipserv_Helper_Database::getSsreport2Db();

		$sql = "
				SELECT
				  DISTINCT
					ord.spb_branch_code
				  , ord.byb_branch_code
				  , rfq.rfq_internal_ref_no
				  , qot.qot_internal_ref_no
				  , ord.ord_internal_ref_no
				  , ord.ord_currency
				  , ord.ord_total_cost
				  , poc.poc_currency
				  , poc.poc_total_cost
				FROM
				  ord
				  , poc
				  , qot
				  , rfq
				WHERE
				  ord.qot_internal_ref_no=qot.qot_internal_ref_no (+)
				  AND qot.rfq_internal_ref_no=rfq.rfq_internal_ref_no (+)
				  AND ord.ord_internal_ref_no=poc.ord_internal_ref_no (+)
				  AND ord.ord_internal_ref_no=:docId
	    	";
		foreach( $db->fetchAll($sql, array('docId' => $docId)) as $row )
		{
			$objects[] = self::createObjectFromDb($row, array($docType));
		}
		return $objects;

	}

	public static function removeTransactionFromOrdTradedGmv($docId){

	}

	public static function store($docId, $docType, $comment)
	{
		$user = Shipserv_User::isLoggedIn();
		$db = Shipserv_Helper_Database::getSsreport2Db();

		$sql = "
			DELETE
				FROM invalid_transaction
			WHERE
				itx_ord_internal_ref_no=:docId
				AND itx_doc_type=:docType
		";
		$db->query($sql, array('docId' => $docId, 'docType' => $docType));

		$sql = "
			INSERT INTO
			invalid_transaction (itx_ord_internal_ref_no, 	itx_doc_type, 	itx_usr_user_code, 	itx_comment, 	itx_updated_by, itx_updated_date, 	itx_created_by, itx_created_date)
			VALUES 				(:docId, 					:docType,		:userId,			:comments,		:userId,		SYSDATE,			:userId,		SYSDATE)
		";
		$params = array('docId' => $docId,'docType' =>$docType, 'comments' => $comment, 'userId' => $user->userId);
		$db->query($sql, $params);

		$sql = "
			UPDATE ord_traded_gmv
			SET
			  ord_original=ord_original+100000000000
			  , ord_internal_ref_no=ord_internal_ref_no+100000000000
			  , ord_orig_submitted_date=ord_orig_submitted_date+(365*100)
			  , ord_submitted_date=ord_submitted_date+(365*100)
			WHERE
			  ord_original=:docId
			  AND ord_original<100000000000

		";
		$db->query($sql, array('docId' => $docId));

		$db->commit();
	}

	public function delete()
	{
		$db = Shipserv_Helper_Database::getSsreport2Db();
		$sql = "
			DELETE
				FROM invalid_transaction
			WHERE
				itx_ord_internal_ref_no=:docId
				AND itx_doc_type=:docType
		";
		$db->query($sql, array('docId' => $this->ordInternalRefNo, 'docType' =>$this->docType));

		$sql = "
			UPDATE ord_traded_gmv
			SET
			  ord_original=ord_original-100000000000
			  , ord_internal_ref_no=ord_internal_ref_no-100000000000
			  , ord_orig_submitted_date=ord_orig_submitted_date-(365*100)
			  , ord_submitted_date=ord_submitted_date-(365*100)
			WHERE
			  ord_original=100000000000 + :docId
			  AND ord_original>100000000000
		";
		$db->query($sql, array('docId' => $docId));

	}

	public function getRfq()
	{
		try
		{
			$rfq = Shipserv_Rfq::getInstanceById($this->rfqInternalRefNo);
		}
		catch (Exception $e)
		{
			$rfq = null;
		}
		return $rfq;

	}

	public function getQuote()
	{
		try
		{
			$quote = Shipserv_Quote::getInstanceById($this->qotInternalRefNo);
		}
		catch (Exception $e)
		{
			$quote = null;
		}
		return $quote;
	}

	public function getPurchaseOrder()
	{
		return Shipserv_PurchaseOrder::getInstanceById($this->ordInternalRefNo);
	}

	public function getPurchaseOrderConfirmation()
	{
		$po = Shipserv_PurchaseOrder::getInstanceById($this->ordInternalRefNo);
		return $po->getPurchaseOrderConfirmation();
	}

	public function getBuyer()
	{
		return Shipserv_Buyer::getBuyerBranchInstanceById($this->bybBranchCode);
	}
	public function getSupplier()
	{
		return Shipserv_Supplier::getInstanceById($this->spbBranchCode);
	}

	public function isInvalidOrd()
	{
		return in_array(self::DOC_TYPE_ORD, $this->docTypes);
	}

	public function isInvalidPoc()
	{
		return in_array(self::DOC_TYPE_POC, $this->docTypes);
	}

}
