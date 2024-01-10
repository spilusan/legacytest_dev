<?php

/**
 * Adapter class for Authentication Service
 *
 * Note: removed authenticate() method. Re-engineered into
 * Shipserv_Oracle_Authentication class.
 * 
 * @package Shipserv
 * @implements Zend_Auth_Adapter_Interface
 * @author Dave Starling <dstarling@shipserv.com>
 */
class Shipserv_Adapters_Authentication
{
	/**
	 * The XML-RPC Client
	 * 
	 * @var object
	 * @access protected
	 */
	protected $client;
	
	/**
	 * The username of the user to verify
	 * 
	 * @access private
	 * @var string
	 */
	private $username;
	
	/**
	 * The password of the user to verify
	 * 
	 * @access private
	 * @var string
	 */
	private $password;
	
	/**
	 * Set up the XML-RPC interface
	 * 
	 * @access public
	 */
	public function __construct ($username = null, $password = null)
	{
		$config  = Zend_Registry::get('config');
		
		$this->client = new Zend_XmlRpc_Client($config->shipserv->services->authentication->url);
		
		$this->username = $username;
		$this->password = $password;
	}
	
	/**
	 * Adapter for authenticating
	 * 
	 * REQUEST FORMAT:
	 * 
     *   <struct>
     *      email (string)
     *      password (string)
     *      firstName (string)
     *      lastName (string)
     *      company (string)
     *      companyTypeId (string) – lookup table: select pct_id, pct_description from pages_user_company_type
     *      otherCompanyType (string)
     *      jobFunctionId (string) – lookup table: select pjf_id, pjf_description from pages_user_job_function
     *      otherJobFunction (string)
     *      marketingUpdated (boolean)
	 * 
	 * RESPONSE:
     * 	<struct>
     * 		userId (string)
     *  	username (string)
     *  	firstName (string)
     *  	lastName (string)
     *  	email (string)
     * 
     *  	    faultCode=1   faultString=”system error”   <-- this is generated when an unexpected error occurs
     *          faultCode=2   faultString=”authentication failed”
     *          faultCode=3   ffaultString=”duplicate username”
	 * 
	 * @access public
	 * @param string $email
	 * @param string $password
	 * @param string $firstName
	 * @param string $lastName
	 * @param string $company
	 * @param string $companyTypeId
	 * @param string $otherCompanyType
	 * @param string $jobFunctionId
	 * @param string $otherJobFunction
	 * @param boolean $marketingUpdated
	 * @return mixed 
	 */
	public function register (
		$email, $password, $firstName, $lastName, $company,
		$companyTypeId, $otherCompanyType, $jobFunctionId,
		$otherJobFunction, $marketingUpdated,
		$companyType, $isDecisionMaker, $companyAddress, $companyZipCode,
		$companyCountryCode, $companyPhoneNo, $companyWebsite, $companySpending,
		$vesselCount, $vesselType
		  
	)
	{
		
		$parameters = array(
			array(
				'email'            => $email,
				'password'         => $password,
				'firstName'        => $firstName,
				'lastName'         => $lastName,
				'company'          => $company,
				'companyTypeId'    => $companyTypeId,
				'otherCompanyType' => $otherCompanyType,
				'jobFunctionId'    => $jobFunctionId,
				'otherJobFunction' => $otherJobFunction,
				'marketingUpdated' => $marketingUpdated,
				'isDecisionMaker'	=> $isDecisionMaker,
				'companyAddress'	=> $companyAddress,
				'companyZipCode'	=> $companyZipCode,
				'companyCountryCode' => $companyCountryCode,
				'companyPhoneNo'	=> $companyPhoneNo,
				'companyWebsite' 	=> $companyWebsite,
				'companySpending'	=> $companySpending,
				'vesselCount' => $vesselCount,
				'vesselType' => $vesselType
			)
		);
		
		try
		{
			$response = $this->client->call('User.register', $parameters);
			
			$registerResult = array('success'  => true,
									'response' => $response,
									'messages' => array());
		}
		catch (Zend_XmlRpc_Client_FaultException $e)
		{
			$registerResult = array('success'  => false,
									'response' => $response,
									'messages' => array($e->getMessage()));
		}
		catch (Zend_XmlRpc_Client_HttpException $e)
		{
			$registerResult = array('success'  => false,
									'response' => $response,
									'messages' => array('Unable to reach authentication service, please try later'));
		}

		return $registerResult;
	}
	
	/**
	 * Adapter for sending a forgotten password
	 * 
	 * REQUEST FORMAT:
	 * 
     *      email (string)
	 * 
	 * RESPONSE:
     * 		success (boolean)
     * 
     *  	    faultCode=1   faultString=”system error”   <-- this is generated when an unexpected error occurs
     * 
	 * @access public
	 * @param string $email
	 * @return array
	 */
	public function sendPassword ($email)
	{		
		$parameters = array('email' => $email);
		try
		{
			$response = $this->client->call('User.sendPassword', $parameters);
			
			if ($response === false)
			{
				$failure = true;
			}
			else
			{
				$failure = false;
			}
			
			$sendPasswordResult = array('failure'  => $failure,
										'response' => $response,
										'messages' => array());
		}
		catch (Zend_XmlRpc_Client_FaultException $e)
		{
			$sendPasswordResult = array('failure'  => true,
										'response' => $response,
										'messages' => array($e->getMessage()));
		}
		catch (Zend_XmlRpc_Client_HttpException $e)
		{
			$sendPasswordResult = array('failure'  => true,
										'response' => $response,
										'messages' => array('Unable to reach authentication service, please try later'));
		}
		
		return $sendPasswordResult;
	}
	
	private static function getDb ()
	{
		return $GLOBALS["application"]->getBootstrap()->getResource('db');
	}
}
