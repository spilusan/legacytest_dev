<?php


define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

echo "flushing memcache\n";
Shipserv_Memcache::getMemcache()->flush();
echo "done\n";


