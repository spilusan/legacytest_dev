<?php
/**
 * Script to generate the daily aggregate stats.
 */

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
        $d = getopt('d:');
        
        if(isset($d['d'])){
            $modifier = $d['d'];
        }
        if($modifier){
            $dateToProcess = $modifier;
            $single = true;
        }else{
            $dateToProcess = date('d-M-Y', time() - (60 * 60 * 24));
        }
        $processor = new Shipserv_Match_Processor();
        $result = $processor->lineitemCategorisation('MIN'); 
        
        
		
		
	}
}

Cl_Main::main();
