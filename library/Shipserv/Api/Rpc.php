<?php

/**
 * XML-RPC Handler for ShipServ
 * 
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 */
class Shipserv_Api_Rpc
{
	/**
	 * Test XML-RPC method
	 * 
	 * @param string $string
	 * @param int $int
	 * @return string
	 */
	public function test ($string, $int)
	{
		return "Success with args: $string + $int";
	}
}