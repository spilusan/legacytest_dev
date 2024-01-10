<?php


define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';
require_once dirname(dirname(SCRIPT_PATH)) . '/vendor/maxcdn/php-maxcdn/src/MaxCDN.php';

echo "flushing MaxCdn\n";

$config  = Zend_Registry::get('config');
if (!$config->shipserv->cdn->use) {
    die("cdn not in use. no need of flushing\n");
}
$myAlias = $config->shipserv->cdn->maxcdn->alias;
$consumerKey = $config->shipserv->cdn->maxcdn->consumerkey;
$consumerSecret = $config->shipserv->cdn->maxcdn->consumersecret;
$zoneId = $config->shipserv->cdn->maxcdn->zoneid;
$api = new MaxCDN($myAlias, $consumerKey, $consumerSecret); 

// Purge pull zone (clean whole CDN cache)
echo  $api->delete('/zones/pull.json/'.$zoneId.'/cache');

echo "done\n";


