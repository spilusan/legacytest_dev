<?php
/**
 * Warm up cache keys for SPR report, force to recreate them  
 */

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

Myshipserv_Spr_WarmupToDbManager::getInstance()->manage($argv, true);
