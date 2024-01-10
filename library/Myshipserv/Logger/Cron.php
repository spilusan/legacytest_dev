<?php 
/**
 * Class responsible to writing log to a physical file
 * 
 * @author Elvir <eleonard@shipserv.com>
 */
class Myshipserv_Logger_Cron extends Myshipserv_Logger_Db
{	
	public function log($string = null, $data = null)
	{
		$string = implode(" ", $_SERVER["argv"]);
		parent::log($string);
	}
}