<?php

/**
 * XML-RPC Handler for Auth API
 * 
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 */
class Shipserv_Api_Auth
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
	
	/**
	 * Test XML-RPC method
	 * 
	 * @param string $string
	 * @param int $int
	 * @return array
	 */
	public function structtest ($string, $int)
	{
		return array('label' => "Success with args",
					 'test'  => $string,
					 'test2' => $int);
	}
	
	/**
	 * API method for authenticating a user with an identity and credentials
	 * 
	 * The identity will generally be an email address, but depends on the
	 * method used. Default authentication method is ssAuth: ShipServ DB
	 * authorisation. Future methods might include OpenID, LDAP, Google, etc.
	 * 
	 * If authentication is successful, a session will be set to ensure
	 * persistence over HTTP (stateless)
	 * 
	 * @access public
	 * @param string $identity The identity of the user to authenticate
	 * @param string $credentials The credentials with which the user is attempting authentication
	 * @param int $persistenceLength The length of the persistence of session in seconds (default 3600s)
	 * @param string $authMethod The method by which the user should be authenticated (default 'ssAuth')
	 * @return string XML string with the result of authentication. If successful, will include session ID
	 * 			      which should be set in a cookie or equivalent by the calling application
	 */
	public function authenticate ($identity, $credentials, $persistenceLength = 3600, $authMethod = 'ssAuth')
	{
		/**
         * @todo implementation
         */
		
		// validate the authentication method
		
		
		
		
		// validate the supplied credentials against the identity
		/*switch ($result->getCode())
		{
			case Zend_Auth_Result::FAILURE_IDENTITY_NOT_FOUND:
				// do stuff for nonexistent identity 
				$success = false;
				$message = 'The supplied identity does not exist';
				$failedLogins++;
				break;
			
			case Zend_Auth_Result::FAILURE_CREDENTIAL_INVALID:
				// do stuff for invalid credential
				$success = false;
				$message = 'The supplied credentials were incorrect';
				$failedLogins++;
				break;
			
			case Zend_Auth_Result::SUCCESS:
				// if valid, create a session with the appropriate persistence
				$success = true;
				$message = 'The authentication was successful';
				$lastAuthenticatedIP   = 3232300909;
				$lastAuthenticatedTime = '2009-07-17 15:06:00';
				break;
			
			default:
				// do stuff for other failure 
				break;
		}*/
		
		// return result
		
		return array('success'               => true,
					 'message'               => 'The authentication was successful',
					 'lastAuthenticatedIP'   => 3232300909,
					 'lastAuthenticatedTime' => '2009-07-17 15:06:00');
	}
	
	/**
	 * Clears a session from being persistent - in effect, logging the user out.
	 * 
	 * @access public
	 * @param string $sessionId The session id that should be removed
	 * @return string XML indicating success, or failure.
	 */
	public function clearSession ($sessionId)
	{
		/**
         * @todo implementation
         */
		
		// checks if the supplied session id is valid
		
		// if valid, clear it
		
		// return result
	}
}