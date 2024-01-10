<?php


// Bootstrap Zend & app
require_once '/var/www/SS_myshipserv/application/Bootstrap-cli.php';

// Include library
//require_once 'lib/common.php';

class Cl_Main
{
	/**
	 * Main entry point for script
	 */
	public static function main ()
	{
		// Load file specified via CLI
        $c = getopt('c:');
        
        if(isset($c['m'])){
            $modifier = $c['m'];
        }
        $processor = new Shipserv_Match_Processor();
        if($modifier == 'top'){
            $result = $processor->lineitemCategorisation('MIN'); 
        }elseif($modifier == 'bottom'){
            $result = $processor->lineitemCategorisation('MAX'); 
        }else{
            echo "--Usage: php categorise.php -m top|bottom development|production; Top or Bottom refers to the direction of the script (for multi threading)";
        }
        
		
		
	}
}

Cl_Main::main();
