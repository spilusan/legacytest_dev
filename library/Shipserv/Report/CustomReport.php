<?php

/**
 * Supplier Custom Report
 * 
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Report_CustomReport extends Shipserv_Object
{
	public static function fetch( $filters = array() )
	{
		$db = $GLOBALS["application"]->getBootstrap()->getResource('db');
		
		$dao = new Shipserv_Oracle_CustomReport( $db );
		$data = $dao->fetch( $filters );
		return $data;
	}
}