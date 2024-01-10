<?php

/**
 * XML-RPC Handler for ACL API
 * 
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 */
class Shipserv_Api_Acl
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
	
	public function isAllowed ()
	{
		
	}
	
	public function fetchAllRoles ()
	{
		
	}
	
	public function fetchRoles ()
	{
		
	}
	
	public function fetchPermissions ()
	{
		
	}
	
	public function fetchRolePermissions ()
	{
		
	}
}