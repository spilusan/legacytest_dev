<?php
/*
* Reset password 
*/
class Myshipserv_CAS_CasResetPassword extends Myshipserv_CAS_CasHTTP
{

	private static $_instance;
	protected $lastRequestResponse;

    /**
    * Singleton class entry point, create single instance
    * @return object
    */
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }
        
        return static::$_instance;
    }

	/**
	* Protected classes to prevent creating a new instance 
	* @return object
	*/
    protected function __construct()
    {
    	parent::__construct();
	}

	/**
	* Hide clone, protect createing another instance
	* @return unknown
	*/
    private function __clone()
    {
    }

    /**
    * Validate the ticket if the password reminder email still valid, not expired
    * curl -k -X POST -d action="checkTicketValidity" -d requestTicket=QXNFN2PI89WO84XYD40J http://jonah:9080/auth/cas/v1/password
    * @param string $ticket The Ticket to validate
    * @return boolean
    */
    public function validateTicket($ticket)
    {
		$params = array(
				'action' => 'checkTicketValidity',
				'requestTicket' => $ticket
				);

		return $this->_getPasswordRequest($params);
    }

    /**
    * Reset the password
    * curl -k -X POST -d action="changePassword" -d username="acayanan@shipserv.com" -d requestTicket=QXNFN2PI89WO84XYD40J -d newPassword="abcdef" confirmedNewPassword="abcdef" http://jonah:9080/auth/cas/v1/password
    * @param string $email                Email addresss
    * @param string $requestTicket        The ticket (not CAS, but for validating URL)
    * @param string $newPassword          The new password
    * @param string $confirmedNewPassword The new password again to validate password
	* @return boolean
    */
    public function resetPassword($email, $requestTicket, $newPassword, $confirmedNewPassword)
    {
		$params = array(
				'action' => 'changePassword',
				'username' => $email,
				'requestTicket' => $requestTicket,
				'newPassword' => $newPassword,
				'confirmedNewPassword' => $confirmedNewPassword
				);

		return $this->_getPasswordRequest($params);
    }
    
    
    /**
     * Change password the password
     * This function will call an older password endpont on CAS
     * 
     * @param string $email                Email addresss
     * @param string $oldPassword          The old password
     * @param string $newPassword          The new password
     * @return boolean
     */
    public function changePassword($email, $oldPassword, $newPassword)
    {
        $params = array(
            'username' => $email,
            'oldpassword' => $oldPassword,
            'newpassword' => $newPassword,
            'respType' => 'json'
        );
        
        return $this->_getPasswordRequest($params, true);
    }

    /**
    * Send a password reminder email 
    * curl -k -X POST -d action="emailChangePwdLink" -d username="acayanan@shipserv.com" -d resetPwdLink="reset-password-link-going-to-Pages"  http://jonah:9080/auth/cas/v1/password
    * @param string $email Email Address 
    * @return boolean
    */
    public function sendPasswordReminderEmail($email)
    {
		$params = array(
				'action' => 'emailChangePwdLink',
				'username' => $email,
				'resetPwdLink' => $this->_getBaseRequestedUrl().'/auth/cas/passwordManager?pmTask=changePassword'
				);

		return $this->_getPasswordRequest($params);
    }

    /**
     * Return with the last password request result
     *
     * @return null|object
     */
    public function getLastPasswordResponse()
    {
        return $this->lastRequestResponse;
    }

    /**
    * Get the password reminder request
    * @param array $params Zend controller returned params
    * @param bool $changePassword In case of this set, we call the old change password service instead
    * @return boolean
    */
    protected function _getPasswordRequest($params, $changePassword = null)
    {
        $output = ($changePassword == true) ? $this->_casCurl($params, $this->_getCasChangePasswordServiceUrl(), true) : $this->_casCurl($params, $this->_getCasPasswordServiceUrl());
        if ($output !== null) {
            if ($output->getStatus() === 200) {
                $result = json_decode($output->getBody());
                $this->lastRequestResponse = $result;
                if ($result->status === 'success' || $result->status === 200) {
                    return true;
                } else {
                    $this->setErrorMessage(strip_tags($result->message));
                    return false;
                }
            } else {
                $this->setErrorMessage(strip_tags($output->getBody()));
                return false;
            }
        } else {
            if ($changePassword === true) {
                $jsonResponse = json_decode($this->getBody());
                if ($jsonResponse) {
                    if (isset($jsonResponse->error)) {
                        $this->setErrorMessage($jsonResponse->error);
                        return false;
                    }
                }
            }
            
            $this->setErrorMessage('CAS Password reminder exception: Connection error');
            return false;
        }
    }


    /**
    * Get the password service URL
    * @return string
    */
    protected function _getCasPasswordServiceUrl()
    {
        return $this->passwordServiceUrl = $this->config->shipserv->services->cas->rest->casPasswordUrl;
    }
    
    /**
     * Get the password service URL
     * @return string
     */
    protected function _getCasChangePasswordServiceUrl()
    {
        return $this->passwordServiceUrl = $this->config->shipserv->services->cas->rest->casChangePasswordUrl;
    }
    
    

}