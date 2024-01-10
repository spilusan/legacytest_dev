<?php
/**
 * Email notification for Supplier Recommendations
 */

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

$app = new Myshipserv_Poller_SupplierRecommendations;
$app->poll();
