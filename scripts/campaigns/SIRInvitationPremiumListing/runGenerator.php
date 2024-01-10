<?php

// Bootstrap Zend & app
require_once '/var/www/SS_myshipserv/application/Bootstrap-cli.php';

class Request_Generator_Cli
{
	private function __construct ()
	{
		// Do nothing
	}

	public static function run ()
	{
		
		// No max execution time
		ini_set('max_execution_time', 0);

		// No upper memory limit
		ini_set('memory_limit', -1);

		$generator = new Myshipserv_EmailCampaign_SIRInvitationPremiumListing_Generator();
		$generator->generate();
	}
}

Request_Generator_Cli::run();