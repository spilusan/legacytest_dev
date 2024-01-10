<?php

/**
 * Helper for parsing unicode results into html entities
 * 
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Helper_Unicode extends Zend_Controller_Action_Helper_Abstract
{
	static $unicodeReplace;
	
	/**
	 * Formats a result
	 * 
	 * @access public
	 * @static
	 * @param array $result Reference to result array
	 * @param
	 */
	public static function formatResultArray (&$result, $key)
	{
		if (!self::$unicodeReplace)
		{
			self::$unicodeReplace = new Shipserv_Unicode();
		}
		
        $result['descriptionMatch'] = self::$unicodeReplace->UTF8entities($result['descriptionMatch']);
		$result['modelMatch']       = self::$unicodeReplace->UTF8entities($result['modelMatch']);
		$result['brandMatch']       = self::$unicodeReplace->UTF8entities($result['brandMatch']);
		$result['transactionMatch'] = self::$unicodeReplace->UTF8entities($result['transactionMatch']);
		$result['catalogueMatch']   = self::$unicodeReplace->UTF8entities($result['catalogueMatch']);
		$result['categoryMatch']    = self::$unicodeReplace->UTF8entities($result['categoryMatch']);
		$result['description']      = self::$unicodeReplace->UTF8entities($result['description']);
	}
	
	/**
	 *
	 *
	 *
	 */
	public function walkThroughResults ($results)
	{
		// disabled causing bottlenect - might be re-enabled later when issue found
		//array_walk($results, array('Shipserv_Helper_Unicode', 'formatResultArray'));
		
		return $results;
	}
}