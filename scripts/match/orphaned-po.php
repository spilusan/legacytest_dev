<?php

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Cl_Main extends Myshipserv_Cli {
	const
		PARAM_KEY_MODE      = 'mode',
		PARAM_KEY_VERBOSE   = 'verbose',
		PARAM_KEY_ITEMS     = 'items',
		PARAM_KEY_DB_NAME	= 'db',
		
		MODE_TEST_TXN    	= 'backtest-all',
		MODE_MATCH_TEST_TXN	= 'backtest-match',
		MODE_MATCH_BUYER    = 'match',
		MODE_ALL_BUYER  	= 'all',
		MODE_CUSTOM			= 'custom';
		
	public function displayHelp() {
		print implode(PHP_EOL, array(
				"Usage: " . basename(__FILE__) . " ENVIRONMENT [OPTIONS]",
				"",
				"ENVIRONMENT has to be development|testing|test2|ukdev|production",
				"",
				"Available options:",
				"   -m          Mandatory option - mode to operate in.",
				"               Allowed modes are:",
				"                 " . self::MODE_MATCH_BUYER .   "   - process order by match buyer only",
				"                 " . self::MODE_ALL_BUYER . " - process order sent by all buyer",
				"                 " . self::MODE_TEST_TXN . " - backtesting algorithm using random order",
				"                 " . self::MODE_MATCH_TEST_TXN . " - backtesting algorithm using match PO only",
				"                 " . self::MODE_CUSTOM . " - testing a certain PO only",
				"",
				"   -v          Verbose output - if -v is specified, verbose mode will be generated",
				"   -db         Database to use: STANDBY if specified otherwise it'll use SSERVDBA",
		)) . PHP_EOL;
	}

	/**
	 * Defines parameters accepted by the script
	 *
	 * @return array
	 */
	protected function getParamDefinition() {
		// please see displayHelp() function for parameter description
		return array(
			array(
					self::PARAM_DEF_NAME        => self::PARAM_KEY_MODE,
					self::PARAM_DEF_KEYS        => '-m',
					self::PARAM_DEF_OPTIONAL    => false,
					self::PARAM_DEF_REGEX       =>
					'/^(' . implode('|', array(
							self::MODE_TEST_TXN,
							self::MODE_MATCH_TEST_TXN,
							self::MODE_MATCH_BUYER,
							self::MODE_ALL_BUYER,
							self::MODE_CUSTOM
					)) . ')$/',
			),
			array(
					self::PARAM_DEF_NAME        => self::PARAM_KEY_VERBOSE,
					self::PARAM_DEF_KEYS        => '-v',
					self::PARAM_DEF_NOVALUE     => true
			),
			array(
					self::PARAM_DEF_NAME        => self::PARAM_KEY_DB_NAME,
					self::PARAM_DEF_KEYS        => '-db',
					self::PARAM_DEF_DEFAULT     => 'sservdba'
			)
		);
	}

	/**
	 * @throws Exception
	 */
	public function run() {
		try {
			$params = $this->getParams();
		} catch (Exception $e) {
			$this->output("Parameter error: " . $e->getMessage());
			$this->displayHelp();
			return 1;
		}

		$params = $params[self::PARAM_GROUP_DEFINED];
		$logger = new Myshipserv_Logger_File('match-orphaned-order');
		switch ($params[self::PARAM_KEY_MODE]) {
			case self::MODE_MATCH_BUYER:
				$this->output("Running in queue mode - processing ORD by match buyer");
				$batch = new Shipserv_Match_OrphanedOrder($logger, self::MODE_MATCH_BUYER, $params[self::PARAM_KEY_VERBOSE], $params[self::PARAM_KEY_DB_NAME]);
				break;

			case self::MODE_ALL_BUYER:
				$this->output("Running in queue mode - processing ORD by all buyer");
				$batch = new Shipserv_Match_OrphanedOrder($logger, self::MODE_ALL_BUYER, $params[self::PARAM_KEY_VERBOSE], $params[self::PARAM_KEY_DB_NAME]);
				break;
				
			case self::MODE_TEST_TXN:
				$this->output("Running in queue mode - backtesting algorithm");
				$batch = new Shipserv_Match_OrphanedOrder($logger, self::MODE_TEST_TXN, $params[self::PARAM_KEY_VERBOSE], $params[self::PARAM_KEY_DB_NAME]);
				break;

			case self::MODE_MATCH_TEST_TXN:
				$this->output("Running in queue mode - backtesting algorithm");
				$batch = new Shipserv_Match_OrphanedOrder($logger, self::MODE_MATCH_TEST_TXN, $params[self::PARAM_KEY_VERBOSE], $params[self::PARAM_KEY_DB_NAME]);
				break;
				
			case self::MODE_CUSTOM:
				$this->output("Running in queue mode - custom test data");
				$batch = new Shipserv_Match_OrphanedOrder($logger, self::MODE_CUSTOM, $params[self::PARAM_KEY_VERBOSE], $params[self::PARAM_KEY_DB_NAME]);
				break;
			
			default:
				// should not normally happen as parameter value is validated, but still better safe than sorry
				$this->output("Unknown mode specified");
				return 1;
		}

		$result = $batch->process();

		$this->output("Finished");
		return 0;
	}
}

$script = new Cl_Main();
$status = $script->run();

exit($status);