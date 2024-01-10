<?php
/**
 * Detecting if the user agent is a web crawler
 * TODO we could use a more advanced, and accurate method using an alwasy updatea user agent list
 * @author attilaolbrich
 *
 */

class Myshipserv_Helper_CrawlerDetect
{
	/**
	 * Detect if the visitor is a crawler bot 
	 * @return boolean
	 */
	public static function detect() {
		return (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/bot|crawl|slurp|spider/i', $_SERVER['HTTP_USER_AGENT']));
	}
}