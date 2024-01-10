<?php

/**
 * Wrapper for Analytics Adapter: drops analytics events for crawlers.
 */
class Shipserv_Adapters_Analytics
{
	// Instance of Shipserv_Browser
	private static $browser;
	
	// Instance of Shipserv_Adapters_Analytics
	private static $adapter;
	
	/**
	 * @return Shipserv_Browser
	 */
	private static function getBrowser ()
	{
		if (! self::$browser)
		{
			self::$browser = new Shipserv_Browser();
		}
		return self::$browser;
	}
	
	/**
	 * @return Shipserv_Adapters_Analytics
	 */
	private static function getAdapter ()
	{
		if (! self::$adapter)
		{
			self::$adapter = new Shipserv_Adapters_Analytics_Api();
		}
		return self::$adapter;
	}
	
	/**
	 * Returns the user's IP address
	 * 
	 * @access private
	 * @static
	 * @return string
	 */
	public static function getIpAddress ()
	{
		$ip = Myshipserv_Config::getUserIp();
		
		// if the user goes through several proxies, it appears these are passed as comma-separated IPs
		// We only want one
		$ipList = explode(',', $ip);
		if (count($ipList) >= 1)
		{
			$ip = trim($ipList[0]);
		}
		
		return $ip;
	}
	
	/**
	 * Magic method implementing conditional proxy
	 */
	public function __call ($name, $arguments)
	{
		$browser = self::getBrowser();
		if ($browser->fetchName() == 'crawler')
		{
			// Do nothing
		}
		else
		{
			// Proxy to adapter
			$adapter = self::getAdapter();
			$res = call_user_func_array(
				array($adapter, $name),
				$arguments
			);
			return $res;
		}
	}

	/**
	 * Get total inquiry for all category with time limit support
	 * 
	 * @param Zend_Db $db
	 * @param string $duration "3 months | 44 days | 3  years"
	 * @return int
	 * @author Elvir <eleonard@shipserv.com>
	 */
	public function getTotalInquiryForAllCategory( $db, $duration = "" )
	{
		$reader = new Shipserv_Oracle_Analytics( $db );
		return $reader->getTotalInquiryForAllCategory( $db, $duration );
	}
	
	/**
	 * Get number of searches accross different categories with or without time restriction
	 * 
	 * @param Zend_Db $db
	 * @param string $duration "3 months | 44 days | 3  years"
	 * @return int
	 * @author Elvir <eleonard@shipserv.com>
	 */
	public function getTotalSearchForAllCategory( $db, $duration = "" )
	{
		$reader = new Shipserv_Oracle_Analytics( $db );
		return $reader->getTotalSearchForAllCategory( $db, $duration );
	}
	
	/**
	 * get number of visitor on each supplier page with or without time restriction
	 * 
	 * @param Zend_Db $db
	 * @param int $supplierId
	 * @param string $duration "3 months | 44 days | 3  years"
	 * @return int
	 * @author Elvir <eleonard@shipserv.com>
	 */
	public function getTotalVisitorOnSupplier( $db, $supplierId, $duration = "" )
	{
		$reader = new Shipserv_Oracle_Analytics( $db );
		return $reader->getTotalVisitorOnSupplier( $db, $supplierId, $duration );
	}
}
