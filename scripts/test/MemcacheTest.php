<?php
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';


$config = Zend_Registry::get('options');
$host = $config['memcache']['server']['host'];
$port = $config['memcache']['server']['port'];

echo "\nHost:$host\nPort$port\n";
	
$memcache = new Memcache();
$memcache->connect($host, $port);

$result = $memcache->get('test-key');

echo var_export($result, true) . PHP_EOL;

$result = $memcache->set('test-key', 'test-value');

$result = $memcache->get('test-key');

echo var_export($result, true) . PHP_EOL;
