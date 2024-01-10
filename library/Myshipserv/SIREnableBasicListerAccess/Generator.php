<?php
/**
 * @author elvir <eleonard@shipserv.com>
 */
class Myshipserv_SIREnableBasicListerAccess_Generator {

	private $db;
	
	public function  __construct()
	{
		$this->logger = new Myshipserv_Logger(true);
	}
	
	public function generate()
	{
		$this->logger->log("Enabling SIR access for basic lister");
		
		$adapter = new Shipserv_Oracle_Suppliers( $this->getDb() );
		$adapter->enableSVRAccessForAllBasicLister();
		
		$this->logger->log("Finish...");
	}
	
	private static function getDb()
	{
		return $GLOBALS["application"]->getBootstrap()->getResource('db');
	}
}
?>