<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Adapter class for Salesforce. Providing a wrapper for common functions, like query and  update via single and bulk apis 
 * @package myshipserv
 * @author Shane O'Connor <soconnor@shipserv.com>
 * @copyright Copyright (c) 2012, ShipServ
 */
class Shipserv_Helper_Security extends Shipserv_Object {
	const
		HASH_PREFIX = '.895)bfrCxn9Cy)3_';

	/**
	 * Takes superIP addresses from config file, and matches the octets. Also allows for wildcards in 3rd and fourth Octet for WAN ip addresses if nescessary
	 * @param type $ip_address
	 * @return boolean 
	 * @deprecated
	 */
	public static function deprecated_isValidInternalIP($ip_address) {

		$ip = explode(".", trim($ip_address));

		if (count($ip) == 4) {
			$config = Zend_Registry::get('config');
			$validips = explode(",", $config->shipserv->auth->superIps);

			foreach ($validips as $validip) {
				$tmpIP = explode(".", trim($validip));

				if ($ip[0] == $tmpIP[0]) {
					if ($ip[1] == $tmpIP[1]) {
						if ($ip[2] == $tmpIP[2] || ($tmpIP[2] == "*" && $tmpIP[3] == "*")) {
							if ($ip[3] == $tmpIP[3] || $tmpIP[3] == "*") {
								return true;
							}
						}
					}
				}
			}
			return false;
		} else {
			return false;
		}
	}
	
	public static function isValidInternalIP($ip_address) {	
		return parent::isIpInRange($ip_address, Myshipserv_Config::getSuperIps());
	}
	
	public static function getRealUserIP() {
		return parent::getUserIp();
	}

	public static function rfqSecurityHash($rfqId) {
		return md5($rfqId . self::HASH_PREFIX);
	}

}