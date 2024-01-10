<?php

/**
 * Represents a collection of user-company associations.
 */
class Myshipserv_UserCompany_UserCollection
{
	private $users = array();
	
	/**
	 * @param array $users Array of Myshipserv_UserCompany_User instances
	 */
	public function __construct (array $users)
	{
		foreach ($users as $u)
		{
			if ($u instanceof Myshipserv_UserCompany_User) $this->users[] = $u;
			else throw new Exception("Expected instance of Myshipserv_UserCompany_User");
		}
	}
	
	/**
	 * Fetch all active user-company assocations.
	 * 
	 * @return array of Myshipserv_UserCompany_User
	 */
	public function getActiveUsers ()
	{
		$resArr = array();
		foreach ($this->users as $u)
		{
			if ($u->getStatus() == Myshipserv_UserCompany_Company::STATUS_ACTIVE)
			{
				$resArr[] = $u;
			}
		}
		return $resArr;
	}
	
	/**
	 * Fetch all user-company assocations pending e-mail confirmation
	 * 
	 * @return array of Myshipserv_UserCompany_User
	 */
	public function getPendingUsers ()
	{
		$resArr = array();
		foreach ($this->users as $u)
		{
			if ($u->getStatus() == Myshipserv_UserCompany_Company::STATUS_PENDING)
			{
				$resArr[] = $u;
			}
		}
		return $resArr;
	}
	
	/**
	 * Fetch all user-company assocations pending e-mail confirmation also marked
	 * as admins.
	 * 
	 * @return array of Myshipserv_UserCompany_User
	 */
	public function getPendingAdminUsers ()
	{
		$resArr = array();
		foreach ($this->users as $u)
		{
			if ($u->getStatus() == Myshipserv_UserCompany_Company::STATUS_PENDING
				&& $u->getLevel() == Myshipserv_UserCompany_Company::LEVEL_ADMIN)
			{
				$resArr[] = $u;
			}
		}
		return $resArr;
	}
	
	/**
	 * Fetch all user-company associations (including potentially inactive / 
	 * logically deleted associations).
	 * 
	 * @return array of Myshipserv_UserCompany_User
	 */
	public function getAllUsers ()
	{
		return $this->users;
	}
	
	/**
	 * Fetch all active admin-level user-company associations.
	 * 
	 * @return array of Myshipserv_UserCompany_User
	 */
	public function getAdminUsers ()
	{
		$resArr = array();
		foreach ($this->users as $u)
		{
			if ($u->getStatus() == Myshipserv_UserCompany_Company::STATUS_ACTIVE
				&& $u->getLevel() == Myshipserv_UserCompany_Company::LEVEL_ADMIN)
			{
				$resArr[] = $u;
			}
		}
		return $resArr;
	}
	
	public function getUsers ()
	{
		$resArr = array();
		foreach ($this->users as $u)
		{
			if ($u->getStatus() == Myshipserv_UserCompany_Company::STATUS_ACTIVE
				&& $u->getLevel() == Myshipserv_UserCompany_Company::LEVEL_USER)
			{
				$resArr[] = $u;
			}
		}
		return $resArr;
	}
}
