<?php

// Bootstrap Zend & app
require_once '/var/www/SS_myshipserv/application/Bootstrap-cli.php';

class Request_Generator_Cli
{
	private function __construct ()
	{
		// Do nothing
	}

	public static function run ( $argv )
	{
		
		// No max execution time
		ini_set('max_execution_time', 0);

		// No upper memory limit
		ini_set('memory_limit', -1);
		
		$campaignName = $argv[3];
		
		// This will download impression/clicks data for SIR from Google DFP
		if( $campaignName == "open-rate-awareness-campaign" )
		{
			$generator = new Myshipserv_EmailCampaign_Notification_Generator();
			$generator->generate(1);
		}
		else if( $campaignName == "zero-user-campaign" )
		{
			$generator = new Myshipserv_EmailCampaign_Notification_Generator();
			$generator->generate(2);
		}
		else if( $campaignName == 'rfq-integration' )
		{
			$generator = new Myshipserv_EmailCampaign_Notification_Generator();
			$generator->generate(7);
		}
		else
		{
			echo "-- invalid command\n";
			echo "-- usage: php run.php development|testing|production -c zero-user-campaign|open-rate-awareness-campaign|rfq-integration -m html [-d true:turn on debug mode] [-e true:retry error]\n";
		}
		
	}
}

Request_Generator_Cli::run( $argv );