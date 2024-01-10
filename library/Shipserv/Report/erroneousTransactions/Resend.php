<?php
class Shipserv_Report_erroneousTransactions_Resend 
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

	public function reSendNotificationToBuyer($ordInternalRefNo)
	{
		$poller = new Myshipserv_Poller_ErroneousTransactionMonitor();
		return $poller->sendToBuyer($ordInternalRefNo, false, true);
	}

	public function reSendNotificationToSupplier($ordInternalRefNo)
	{
		$poller = new Myshipserv_Poller_ErroneousTransactionMonitor();
		return $poller->sendToSupplier($ordInternalRefNo, false);
	}

}

