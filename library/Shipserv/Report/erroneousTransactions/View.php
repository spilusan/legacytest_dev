<?php
class Shipserv_Report_erroneousTransactions_View 
{

	private static $instance;
	 /**
	 * Hide all stuff we do not want to be accessible for singleton class
	 * *Singleton* via the `new` operator from outside of this class.
	 */

	 protected 
	         $order = null
        	,$quote = null
        	,$supplier = null
        	,$buyer = null
	        ,$data = null
	        ,$sendToGsd = null
	        ,$isSecond = null
	        ,$recepientType = null;

	protected function __construct() {}
	private function __clone() {}
	public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }
        
        return static::$instance;
    }

    protected function initDocument($orderId, $data, $recepientType, $sendToGsd = false, $second = false)
    {
        $this->order = Shipserv_Order::getInstanceById($orderId);
        $this->quote = Shipserv_Quote::getInstanceById($this->order->ordQotInternalRefNo);

        $this->supplier = Shipserv_Supplier::getInstanceById($this->order->ordSpbBranchCode, '', true);
        $this->buyer = Shipserv_Buyer::getBuyerBranchInstanceById($this->order->ordBybBuyerBranchCode, '', true);

        $this->data = $data;
        $this->sendToGsd = $sendToGsd;
        $this->isSecond = $second;
        $this->recepientType = $recepientType;
    }

    public function getEmailTemlateForBuyer($ordInternalRefNo)
    {
    	return $this->getEmailTemplate($ordInternalRefNo, Myshipserv_NotificationManager_Email_ErroneousTransaction::RECIPIENT_BUYER);
    }

	public function getEmailTemlateForSupplier($ordInternalRefNo)
    {
    	return $this->getEmailTemplate($ordInternalRefNo, Myshipserv_NotificationManager_Email_ErroneousTransaction::RECIPIENT_SUPPLIER);
    }

    public function getEmailTemlateForGsd($ordInternalRefNo)
    {
    	return $this->getEmailTemplate($ordInternalRefNo, Myshipserv_NotificationManager_Email_ErroneousTransaction::RECIPIENT_SUPPLIER, true);
    }

    public function getEmailTemlateForSecondReminder($ordInternalRefNo)
    {
    	return $this->getEmailTemplate($ordInternalRefNo, Myshipserv_NotificationManager_Email_ErroneousTransaction::RECIPIENT_SUPPLIER, false, true);
    }

    protected function getEmailTemplate($ordInternalRefNo, $recepientType, $sendToGsd = false, $second = false)
    {

		$poller = new Myshipserv_Poller_ErroneousTransactionMonitor(false);
		$rows = $poller->getRowsForBuyer($ordInternalRefNo);
		$result = '';

		foreach( $rows as $row)
		{
			try{
				$this->initDocument($row['ORD_INTERNAL_REF_NO'], $row, $recepientType, $sendToGsd, $second);
				$result .= $this->getHtml();
			}catch(Exception $e){
				$result = false;
			}
		} 

		return $result;

    }

	protected function getHtml()
    {
        $view = $this->getView();
        $view->order = $this->order;
        $view->quote = $this->quote;
        $view->supplier = $this->supplier;
        $view->buyer = $this->buyer;
        $view->data = $this->data;
        $view->sendToGsd = $this->sendToGsd;
        $view->isSecond = $this->isSecond;
        $view->recepientType = $this->recepientType;
        $view->hostname = Myshipserv_Config::getApplicationHostName();
        $view->startSupplierURL = $this->getStartSupplierUrl($this->order->ordSpbBranchCode, $this->order->ordInternalRefNo);
        $view->buyerMessage = $this->getBuyerMessage($this->order->ordInternalRefNo);
        
        $body = $view->render('email/erroneous-transaction-notification.phtml');

        return $body;
    }

    protected function getView ()
	{
		$view = new Zend_View();
		$view->setScriptPath(APPLICATION_PATH . "/views/scripts/");
		return $view;
	}

    protected function getStartSupplierUrl($ordSpbBranchCode, $ordInternalRefNo)
    {
        return  Myshipserv_Config::getApplicationProtocol() . '://' . Myshipserv_Config::getApplicationHostName() . "/viewpo?login=" . $ordSpbBranchCode . '&porefno=' . $ordInternalRefNo;
    }

    protected function getBuyerMessage($ordInternalRefNo)
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $sql = "
        SELECT
            etn_buy_notification_message
        FROM
            erroneous_txn_notification
        WHERE
            etn_ord_internal_ref_no = :etnOrdRefNo
            and etn_doc_type = 'ORD'";

        $blob = $db->fetchOne($sql, array('etnOrdRefNo' => $ordInternalRefNo));
        return ($blob) ? $blob->load() : '';
    }

}
