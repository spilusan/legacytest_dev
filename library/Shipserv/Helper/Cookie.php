<?php

/**
 * Helper for managing cookies
 *
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Helper_Cookie extends Zend_Controller_Action_Helper_Abstract
{
	/**
	 * Sets a specific cookie with a value. The cookie is defined by its name
	 * in the configuration.
	 *
	 * @access public
	 * @param string $configName
	 * @param mixed $value
	 * @return boolean
	 */
	public function setCookie ($configName, $value)
	{
		//$config  = Zend_Registry::get('config');
		$config = Shipserv_Object::getConfig();
		$cookie = $config->shipserv->{$configName}->cookie;

		// save the search criteria in a cookie for later reference
		$expiry = ($cookie->expiry == 0) ? 0 : time() + $cookie->expiry;
		
		return setcookie($cookie->name, $value, $expiry, $cookie->path, $cookie->domain);
	}

	/**
	 * Clears the value of a cookie and expires it within 1 second
	 *
	 * @access public
	 * @param string $configName
	 * @return boolean
	 */
	public function clearCookie ($configName)
	{
		$config = Shipserv_Object::getConfig();

		$cookie = $config->shipserv->{$configName}->cookie;

		return setcookie($cookie->name, null, 1, $cookie->path, $cookie->domain);
	}

	/**
	 * Fetches the value of a cookie, based on its config name
	 *
	 * @access public
	 * @param string $configName
	 * @return mixed
	 */
	public function fetchCookie ($configName)
	{
		$config = Shipserv_Object::getConfig();

		$cookie = $config->shipserv->{$configName}->cookie;

		return $_COOKIE[$cookie->name];
	}

	/**
	 * Sets a cookie, but encodes the data into JSON first
	 *
	 * @access public
	 * @param string $configName
	 * @param mixed $data
	 */
	public function encodeJsonCookie ($configName, $data)
	{
		return $this->setCookie($configName, json_encode($data));
	}

	/**
	 * Fetches a cookie storing JSON data, and turns it into an associative array
	 *
	 * @access public
	 * @param string $configName
	 * @return array
	 */
	public function decodeJsonCookie ($configName)
	{
		$config = Shipserv_Object::getConfig();

		$cookie = $config->shipserv->{$configName}->cookie;
		//echo $_COOKIE[$cookie->name];
		$jsonData = json_decode(stripslashes($_COOKIE[$cookie->name]));

		// we now have an object - thanks to the wonder(!) of PHP5, we can iterate over objects
		return self::objToArray($jsonData);
	}

	/**
	 * Recursively convert an object into an array
	 *
	 * @access public
	 * @static
	 * @param object $obj
	 * @return array
	 */
	public static function objToArray ($obj)
	{
		$array = array();
		if (is_object($obj))
		{
			foreach ($obj as $key => $value)
			{
				if (is_object($value))
				{
					$value = self::objToArray($value);
				}

				$array[$key] = $value;
			}
		}

		return $array;
	}
}
