<?php

/**
 * Unit Test for Auth Service
 *
 * tests needed:
 *
 *   authorisation: bad identity
 *   authorisation: good identity, bad password
 *   authorisation: good identity, good password
 * 
 * @package shipserv_tests
 * @author Dave Starling <dstarling@shipserv.com>
 */
class Shipserv_Tests_Auth_Test extends PHPUnit_Framework_TestCase
{
	protected $client;
	
	/**
	 * Set up the test - creates XML-RPC Client, etc.
	 *
	 * @access protected
	 */
	protected function setUp ()
	{
		$this->client = new Zend_XmlRpc_Client("http://auth.services.myshipserv.com/query"); 
	}
	
	/**
	 * Garbage collection
	 *
	 * @access protected
	 */
	protected function tearDown ()
	{
		unset($this->client);
	}
	
	/**
	 * Tests a bad identity attempting to authenticate
	 * 
	 */
	public function testBadIdentity ()
	{
		$authArguments = array('badidentity', 'dave');
		
		// The first argument is NAMESPACE.METHOD.  Second argument is what's being passed in
		try
		{
			$result = $this->client->call('auth.authenticate', $authArguments);
		}
		catch (Zend_XmlRpc_Client_HttpException $e)
		{
			$this->fail($e->getCode().' '.$e->getMessage());
		}
		catch (Zend_XmlRpc_Client_FaultException $e)
		{
			$this->fail($e->getCode().' '.$e->getMessage());
		}
		
		$this->assertFalse($result['success']);
		$this->assertEquals($result['message'], 'The supplied identity does not exist');
	}
	
	/**
	 * Tests a good identity attempting to authenticate with bad credentials
	 * 
	 */
	public function testGoodIdentityWithBadCredentials ()
	{
		$authArguments = array('dave', 'badpassword');
		
		// The first argument is NAMESPACE.METHOD.  Second argument is what's being passed in
		try
		{
			$result = $this->client->call('auth.authenticate', $authArguments);
		}
		catch (Zend_XmlRpc_Client_HttpException $e)
		{
			$this->fail($e->getCode().' '.$e->getMessage());
		}
		catch (Zend_XmlRpc_Client_FaultException $e)
		{
			$this->fail($e->getCode().' '.$e->getMessage());
		}
		
		$this->assertFalse($result['success']);
		$this->assertEquals($result['message'], 'The supplied credentials were incorrect');
	}
	
	/**
	 * Tests a good identity attempting to authenticate with good credentials
	 * 
	 */
	public function testGoodIdentityWithGoodCredentials ()
	{
		$authArguments = array('dave', 'dave');
		
		// The first argument is NAMESPACE.METHOD.  Second argument is what's being passed in
		try
		{
			$result = $this->client->call('auth.authenticate', $authArguments);
		}
		catch (Zend_XmlRpc_Client_HttpException $e)
		{
			$this->fail($e->getCode().' '.$e->getMessage());
		}
		catch (Zend_XmlRpc_Client_FaultException $e)
		{
			$this->fail($e->getCode().' '.$e->getMessage());
		}
		
		$this->assertTrue($result['success']);
		$this->assertEquals($result['message'], 'The authentication was successful');
		$this->assertEquals($result['lastAuthenticatedIP'], 3232300909);
		$this->assertEquals($result['lastAuthenticatedTime'], '2009-07-17 15:06:00');
	}
	
	/**
	 * Tests that a user who has been authenticated now has a valid session (i.e. preserving state)
	 *
	 */
	public function testAuthenticatedUserHasSession ()
	{
		$this->markTestIncomplete('This test has not been implemented yet');
	}
	
	/**
	 * Tests if an account is locked out after n failed login attempts
	 * 
	 */
	public function testLockoutAfterNFailedLogins ()
	{
		$this->markTestIncomplete('This test has not been implemented yet');
	}
	
	public function testClearSession ()
	{
		$this->markTestIncomplete('This test has not been implemented yet');
	}
}