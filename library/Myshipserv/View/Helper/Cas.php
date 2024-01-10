<?php

class Myshipserv_View_Helper_Cas extends Zend_View_Helper_Abstract
{
	/**
	* Initalise cas object, set the config
	* @return object
	*/
	function __construct()
	{
		$this->config = $GLOBALS['application']->getBootstrap()->getOptions();
	}

	/**
	* Initalise cas
	* @return object
	*/
	public function cas()
	{
        $this->config = $GLOBALS['application']->getBootstrap()->getOptions();
		return $this;
	}

	/**
	* Get the Pages login url 
	* @return string
	*/
	public function getCasLogin()
	{
		return $this->config['shipserv']['services']['sso']['login']['url'];
	}

	/**
	* Get the Pages login url 
	* @return string
	*/
	public function getPagesLogin()
	{
		$url = $this->getCasLogin() . '?x=0&pageLayout=new&service=';
		return $url;
	}

	/**
	* Get the Tradenet login url 
	* @return string
	*/
	public function getTradenetLogin()
	{
		$url = $this->getCasLogin() . '?service=' . urlencode($this->getRootDomain() . '/user/cas?redirect=/LoginToSystem');
		return $url;
	}

	/**
	* Get the CAS logout url 
	* @return string
	*/
	public function getCasLogout()
	{
		return $this->config['shipserv']['services']['sso']['logout']['url'];
	}

	/**
	* Get the CAS REST login URL
	* @param boolean $extend Extend path with URL to redirect to TxnMonitor
	* @return string
	*/
	public function getCasRestLogin($extend = false)
	{
		$url = $this->config['shipserv']['services']['cas']['rest']['loginUrl'];
		if ($extend === true) {
			$targetUrl = '&service=' . urlencode($this->getRootDomain() . '/txnmon/new');
			//For testing with a temporary gateway, Remove refactor later
			//$url .= '?service=' . urlencode('/login-gateway'.$targetUrl);
			$url .= $targetUrl;
		}
		return $url;
	}

	/**
	* Returns the tradenet login URL for REST CAS ligin
	* @param boolean $extend If true, then we extend it to redirect to transaction monitor by default
	* @return string
	*/
	public function getCasRestTradenetLogin($extend = true)
	{
		$url = $this->config['shipserv']['services']['cas']['rest']['tradenetLoginUrl'];
		if ($extend === true) {
			$targetUrl = '?service=' . urlencode($this->getRootDomain() . '/txnmon');
			//For testing with a temporary gateway, Remove refactor later
			//$url .= '?service=' . urlencode('/login-gateway'.$targetUrl);
			$url .= $targetUrl;
		}
		return $url;
	}

	/**
	* Get the HTTP protocol of the loaded page
	* With moving to HTTPS we return always https
	* @return string (http:// or https://)
	*/
	public function getHttpProtocol()
	{
		//return ($_SERVER['APPLICATION_ENV'] == 'development') ? 'http://' : 'https://';
		//With moving to HTTPS we return always https
		return 'https://';
	}

	/**
	* Get the loaded domain 
	* @return string (Like https://www.shipserv.com)
	*/
	public function getRootDomain()
	{
		return $this->getHttpProtocol() . $_SERVER['HTTP_HOST'];
	}

}
