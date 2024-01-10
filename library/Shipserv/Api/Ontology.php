<?php

require_once 'arc/ARC2.php';

/**
 * XML-RPC Handler for Auth API
 * 
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 */
class Shipserv_Api_Ontology
{
	static $store;
	
	/**
	 * SPARQL Query method
	 * 
	 * @param string $query
	 * @return array
	 */
	public function query ($query)
	{
		$store = self::fetchStore();
		
		$rs = $store->query($query);
		
		$rows = array();
		
		if (!$store->getErrors())
		{
			$rows = $rs['result']['rows'];
		}
		else
		{
			// throw an exception?
		}
		
		return $rows;
	}
	
	/**
	 * RDF Load method
	 *
	 * @param string $url The URL to load
	 * @return array An array of triples
	 */
	public function loadRDF ($url)
	{
		$store = self::fetchStore();
		
		$parser = ARC2::getRDFParser();
		$parser->parse($url);
		//var_dump($parser);
		
		$query    = 'LOAD <'.$url.'>';
		$res      = $store->query($query);
		$warnings = $store->getWarnings();
		$errors   = $store->getErrors();
		
		return array($query, $res, $warnings, $errors);
	}
	
	public function getTriples ()
	{
		$store = self::fetchStore();
	}
	
	private static function getParser ($url)
	{
		$parser = ARC2::getRDFParser();
	}
	
	private static function fetchStore ()
	{
		if (is_object(self::$store))
		{
			return $store;
		}
		
		// need to move this to a config.ini
		switch (getenv('APPLICATION_ENV'))
		{
			case 'development':
			case 'staging':
				$config = array(
					/* db */
					'db_host' => 'localhost', /* default: localhost */
					'db_name' => 'SS_arc_store',
					'db_user' => 'root',
					'db_pwd' => '',
					/* store */
					'store_name' => 'arc_tests',
					/* network */
					//'proxy_host' => '192.168.1.1',
					//'proxy_port' => 8080,
					/* parsers */
					'bnode_prefix' => 'bn',
					/* sem html extraction */
					'sem_html_formats' => 'rdfa microformats',
				);
				break;
			
			case 'testing':
			case 'production':
				$config = array(
					/* db */
					'db_host' => 'localhost', /* default: localhost */
					'db_name' => 'ssarcstore',
					'db_user' => 'ssarcstore',
					'db_pwd'  => 'doit.9am',
					/* store */
					'store_name' => 'arc_tests',
					/* network */
					//'proxy_host' => '192.168.1.1',
					//'proxy_port' => 8080,
					/* parsers */
					'bnode_prefix' => 'bn',
					/* sem html extraction */
					'sem_html_formats' => 'rdfa microformats',
				);
			break;
		}
		
        $store = ARC2::getStore($config);
        
        if (!$store->isSetUp())
        {
            $store->setUp();
        }
		
		self::$store = $store;
		
		return $store;
	}
	
}