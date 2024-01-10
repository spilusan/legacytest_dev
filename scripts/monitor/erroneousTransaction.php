<?php
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';



$app = new Myshipserv_Poller_ErroneousTransactionMonitor;
$app->poll();

$cronLogger = new Myshipserv_Logger_Cron( 'Myshipserv_Poller_ErroneousTransactionMonitor::Poll()' );
$cronLogger->log();
