<?php
/**
 * CLI Script to update data for MATCH PO Statistics
 * 
 *  @author Shane O Connor
 *  
 */
require_once '/var/www/SS_myshipserv/application/Bootstrap-cli.php';
require_once '/var/www/SS_myshipserv/scripts/matchPOStats/lib/MatchStats.php';

class Match_Stats_Cli 
{
    protected $db = null;

    public function run() {

    	// clear the screen
        echo chr(27) . '[1J';
        
        $c = getopt('d:');
        $m = getopt('m:');
        
        if (isset($c['d'])) 
        {
            $date = $c['d'];
        } 
        else 
        {
            $date = date("d-M-Y", time() - 60 * 60 * 24);
        }
        
        if (isset($m['m'])) 
        {
            $continuous = true;
        } 
        else 
        {
            $continuous = false;
        }
        
        $cronLogger = new Myshipserv_Logger_Cron( 'MatchStats::getMatchStats' );
        $cronLogger->log();
        
        //Get most recent date from Stats.
        $matchStats = new MatchStats();
        $matchStats->getMatchStats($date, $continuous);
    }
}

$cli = new Match_Stats_Cli();
$cli->run();
