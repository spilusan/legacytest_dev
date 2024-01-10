<?php
/**
 * Keep cache keys hot for SPR report, so will not evicted so easily, and if not exist, recreate from DB
 */

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

Myshipserv_Spr_WarmupManager::getInstance()->manage($argv, false);
