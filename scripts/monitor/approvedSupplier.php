<?php

//$new_time = mktime(12, 0, 0, 9, 14, 2014);
//timecop_freeze($new_time);
//timecop_travel($new_time);

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

$app = new Myshipserv_Poller_Reminder_ApprovedSupplier;
$app->poll();
