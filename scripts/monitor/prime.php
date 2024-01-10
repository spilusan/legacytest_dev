<?php
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';



$app = new Shipserv_Report_Supplier_Prime;
$app->run();
