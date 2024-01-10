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

		// get the mode
		$m = getopt('m:');
		if( isset( $m['m'] ) ) $mode = $m['m'];
		
		// This will download impression/clicks data for SIR from Google DFP
		if( $mode == "search-data" )
		{
			$adapter = new Shipserv_Adapters_MongoDb();
			$adapter->importSearchStatistic();
		}
		else if( $mode == "line-item-data" )
		{
			$adapter = new Shipserv_Adapters_MongoDb();
			$adapter->importPurchaseOrderLineItems();
		}
                else if ($mode == "rfq-li-data"){
                        $adapter = new Shipserv_Adapters_MongoDb();
			$adapter->importRFQLineItems();
                }
		else if( $mode == "purchase-order-data" )
		{
			$adapter = new Shipserv_Adapters_MongoDb();
			$adapter->importPurchaseOrder();
		}
		else if( $mode == "store-search-statistic" )
		{
			$adapter = new Shipserv_Adapters_MongoDb();
			$adapter->exportSearchStatisticData();
		}
		else
		{
			echo "-- invalid command\n";
			echo "-- usage: php run.php development|testing|production -m search-data|line-item-data|purchase-order-data \n";
		}
	}
}

Request_Generator_Cli::run();