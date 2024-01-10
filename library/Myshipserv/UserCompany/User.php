<?php

/**
 * Represents a user in his/her relationship to a company.
 * It's rather pointless - a refactoring candidate.
 */
class Myshipserv_UserCompany_User extends Shipserv_User
{	
	public ?string $status = null;
	public $level;
	
	/**
	 * Parameters as per parent class, with 2 additions.
	 *
	 * @param mixed $status
	 * @param mixed $level
	 */
	public function __construct (Shipserv_User $user, $status, $level)
	{
		parent::__construct($user->getDbRow());
		
		$this->status = (string) $status;
		$this->level = (string) $level;
	}
	
	public function getStatus ()
	{
		return $this->status;
	}
	
	public function getLevel ()
	{
		return $this->level;
	}
}
