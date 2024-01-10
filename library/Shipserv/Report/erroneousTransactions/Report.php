<?php
class Shipserv_Report_erroneousTransactions_Report extends Shipserv_Report
{

	protected $useCache = false;
	
	public function getTransactionNotifications($page, $itemPerPage)
	{
		$user = Shipserv_User::isLoggedIn();

		$sql = "
				SELECT
				   etn_ord_internal_ref_no
				  ,etn_doc_type
				  ,etn_notification_date
				  ,etn_gsd_notified_date
				  ,etn_corrected_date
				  ,etn_supplier_response
				  ,etn_sec_notification_date
				  ,etn_buy_notification_date
		          ,o.ord_submitted_date
		          ,o.spb_branch_code
		          ,o.byb_branch_code
		          ,b.byb_name
		          ,s.spb_name
				FROM
				  erroneous_txn_notification e LEFT JOIN ord o
          		  ON o.ord_internal_ref_no = e.etn_ord_internal_ref_no
                	JOIN supplier s ON s.spb_branch_code = o.spb_branch_code
                  		JOIN buyer b ON b.byb_branch_code = o.byb_branch_code
          		WHERE
				  e.etn_doc_type = 'ORD'
				  AND e.etn_gsd_notified_date is NULL
				  AND e.etn_corrected_date is NULL
				ORDER BY
				  o.ord_submitted_date DESC";

		$params = array();

		if ($this->useCache) {
			$key = "Shipserv_Report_erroneousTransactions_Report" . md5($sql) . print_r($params, true);
			$result =  $this->camelCaseRecordSet($this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2'));
		} else {
			$db = Shipserv_Helper_Database::getSsreport2Db();
			$result =  $this->camelCaseRecordSet($db->fetchAll($sql, $params));
		}

		$itemCount = count($result);
		$pageCount = floor($itemCount / $itemPerPage) + (($itemCount % $itemPerPage) > 0);
		$startPage = ($page >0) ? ($page - 1) : 0;
		$startItem = $startPage * $itemPerPage;
		$endItem = ($startPage+1) * $itemPerPage;
		$endItem = ($endItem > $itemCount) ? $itemCount : $endItem;

		$data = array();
		for ($i = $startItem; $i<$endItem; $i++) {
			$data[$i] = $result[$i];

			$data[$i]['printableURL'] = $this->getPrintable('ord', $result[$i]['etnOrdInternalRefNo']);
			$data[$i]['reminderCount'] = ($result[$i]['etnSecNotificationDate'] == null) ? 1 : 2; 
			$data[$i]['etnReason'] = $this->getReasons((int)$result[$i]['etnOrdInternalRefNo']);
			$data[$i]['etnResponse'] = $this->getReadableStatus($result[$i]['etnSupplierResponse']);
			$data[$i]['url-i'] = 	'/reports/invalid-txn-picker?documentInternalRefNo=' . $result[$i]['etnOrdInternalRefNo'] . '&a=Search&h=' . md5('PO' . $result[$i]['etnOrdInternalRefNo']);
		}

		return array(
				  'data' => $data
				, 'itemCount' => $itemCount
				, 'pageCount' => $pageCount
				);
		;
	}

	/**
	* Camel case the whole recordset
	* @param array $rec, Record set array
	* @return array camel cased, and filtered recordset according to the selected page
	*/
	protected function camelCaseRecordSet( $rec )
	{
		$data = array();
		foreach ($rec as $value) {
			$data[] = $this->camelCase($value);
		}
		return $data;
	}

	protected function getPrintable( $docType, $refNo )
	{
		return "http://". Shipserv_Object::getHostname() ."/user/printable?d=".$docType."&id=" . $refNo . "&h=" . md5($docType.$refNo);
	}

	protected function getReadableReason( $reason ) 
	{
		 switch ($reason) {
		 	case 'PO_QOT_DIFFERENT_CURRENCY':
		 		return 'currency differs';
		 		break;
		 	case 'PO_QOT_LINE_ITEM_DIFFERENT_UNIT_PRICE':
		 		return 'unit price differs';
		 		break;
		 	case 'PO_LARGE_ORDER':
		 		return 'large order';
		 		break;
		 	default:
			 	return '';
		 		break;
		 }
	}

	protected function getReasons($ordInternalRefNo)
	{
		$result = '';
		$sql = "
			SELECT
				etc_description 
			FROM 
				erroneous_txn_check_result
			WHERE 
				etc_etn_ord_internal_ref_no = :ordInternalRefNo";

		$reasonArray = array();

		$db = Shipserv_Helper_Database::getSsreport2Db();
		$records = $db->fetchAll($sql, array('ordInternalRefNo' => $ordInternalRefNo));
		foreach ($records as $record) {
			$reason = $this->getReadableReason($record['ETC_DESCRIPTION']);
			if (!(in_array($reason , $reasonArray))) {
				array_push($reasonArray, $reason);
			}
		}

		foreach ($reasonArray as $reason) {
			$result .=  ($result === '') ? $reason : ','.$reason;
		}

		return $result;

	}

	protected function getReadableStatus($statusCode) {

		 switch ($statusCode) {
		 	case 'ACC':
		 		return 'Correct as issued';
		 		break;
		 	case 'CON':
		 		return 'I will send a correcting POC';
		 		break;
		 	case 'SEN':
		 		return 'Request buyer reissues PO';
		 		break;
		 	default:
		 		return '';
		 		break;
		 }
	}



}