<?php

class Shipserv_Oracle_User_UserCollection
{
	private $shipservUsers = array();
	private $inactiveUsers = array();
	
	public function __construct (array $users, array $inactiveUsers = array())
	{
		foreach ($users as $u)
		{
			if ($u instanceof Shipserv_User) $this->shipservUsers[] = $u;
			else throw Exception("Expected Shipserv_User");
		}
		
		foreach ($inactiveUsers as $u)
		{	
			if ($u instanceof Shipserv_User) $this->inactiveUsers[] = $u;
			else throw Exception("Expected Shipserv_User");
		}
	}
	
	/**
	 * Fetch active users.
	 * 
	 * @return array of Shipserv_User
	 */
	public function makeShipservUsers ( $skipCheck = false )
	{
		if( $skipCheck == true ){
			return $users = $this->getAllUsers();
		}
		return $this->shipservUsers;
	}
	
	/**
	 * Fetch active & inactive users.
	 * 
	 * @return array of Shipserv_User
	 */
	public function getAllUsers ()
	{
		return array_merge($this->shipservUsers, $this->inactiveUsers);
	}
	
	public function getInactiveUsers ()
	{
		return $this->inactiveUsers;
	}
}
