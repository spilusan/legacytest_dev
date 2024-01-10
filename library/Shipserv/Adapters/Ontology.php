<?php

/**
 * Adapter class for Ontology Service
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 */
class Shipserv_Adapters_Ontology
{
	/**
	 * The XML-RPC Client
	 * 
	 * @var object
	 * @access protected
	 */
	protected $client;
	
	/**
	 * Set up the XML-RPC interface
	 * 
	 * @access public
	 * @param string $url The URL of the service
	 */
	public function __construct ($url)
	{
		$this->client = new Zend_XmlRpc_Client($url);
	}
	
	/**
	 * REQUEST FORMAT:
	 * 
	 * 	methodName
	 * 		
	 * 	params
	 *		
	 */
	public function query ($query)
	{
		$parameters = array($query);
		
		$result = $this->client->call('ontology.query', $parameters);
		
		return $result;
	}
}