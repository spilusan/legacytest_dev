<?php
/*
* This class resends the password reset email to the user, using CURL 
* Refactored using the CAS REST call
*/
class Shipserv_User_PasswordResetByCas {

	/**
	* Sending password reminder
	* @param string $username 
	* @return bool if successful
	*/
	public function sendReminder($username)
	{
		$casResetPassword = Myshipserv_CAS_CasResetPassword::getInstance();
		return $casResetPassword->sendPasswordReminderEmail($username) ;
	}

}

