<?php

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Cl_Main extends Myshipserv_Cli {
	const PARAM_KEY_MODE = 'kpi';
	const MODE_PULL_DAILY = 'daily';

	public function displayHelp() {
		print implode(PHP_EOL, array(
				"Usage: " . basename(__FILE__) . " ENVIRONMENT [OPTIONS]",
				"",
				"ENVIRONMENT has to be development|testing|test2|ukdev|production",
				"",
				"Available options:",
				"   -m          Mandatory option - mode to operate in.",
				"               Allowed modes are:",
				"                 " . self::MODE_PULL_DAILY .   "   - daily update",
				"",
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
							self::MODE_PULL_DAILY
					)) . ')$/',
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
		$logger = new Myshipserv_Logger_File('match-cron-kpi-adoption');

		$cronLogger = new Myshipserv_Logger_Cron( 'Shipserv_Match_Report_AdoptionRate_Cron' );
		$cronLogger->log();

		switch ($params[self::PARAM_KEY_MODE]) {

			case self::MODE_PULL_DAILY:
				$this->output("Pulling daily data");
				$batch = new Shipserv_Match_Report_AdoptionRate_Cron();
				break;

			default:
				// should not normally happen as parameter value is validated, but still better safe than sorry
				$this->output("Unknown mode specified");
				return 1;
		}

		$result = $batch->rebuildTable();
		$result = $batch->updateBuyerStats();

		$this->output("Warming up data for each day");

		$internalKpis = new Shipserv_Match_Report_AdoptionRate();
		$result = $internalKpis->getStat();

		$this->output("Finished");
		return 0;
	}
}

$script = new Cl_Main();
$status = $script->run();

exit($status);
