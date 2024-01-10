<?php

/**
 * Class for reading the ontology
 * 
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Ontology
{
	static $adapter;
	
	/**
	 * Singleton fetcher for the ontology adapter
	 * 
	 * @access public
	 * @static
	 * @return Shipserv_Adapter_Ontology object
	 */
	public static function getAdapter ()
	{
		if (!is_object(self::$adapter))
		{
			$config  = Zend_Registry::get('config');
			
			$adapter = new Shipserv_Adapters_Ontology($config->shipserv->services->ontology->url);
			self::$adapter = $adapter;
		}
		
		return self::$adapter;
	}
}