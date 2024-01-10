<?php
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(dirname(SCRIPT_PATH))) . '/application/Bootstrap-cli.php';
class Request_Generator_Cli
{
	public static function run()
	{
		// No max execution time
		ini_set('max_execution_time', 0);

		// No upper memory limit
		ini_set('memory_limit', -1);

		$generator = new Myshipserv_EmailCampaign_UpsellNonPayingSupplier_Generator();
		$generator->generate();
	}
}

Request_Generator_Cli::run();