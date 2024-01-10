<?php

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Cl_Main extends Myshipserv_Cli {
	const PARAM_KEY_MODE                = 'mode';
	const PARAM_KEY_MODE_ALL_ACCOUNT    = 'all';
	const PARAM_KEY_MODE_SELECTED_TNID  = 'tnid';
	const PARAM_KEY_TEST_TXN            = 'test';
	const PARAM_KEY_COMPANY             = 'company';
	const PARAM_KEY_PERIOD              = 'period';
	const PARAM_KEY_FILE_CSV            = 'csv';
	
	public function displayHelp() {
		print implode(PHP_EOL, array(
				"Usage: " . basename(__FILE__) . " ENVIRONMENT [OPTIONS] [TNIDS in COMMA SEPARATED]",
				"",
				"ENVIRONMENT has to be development|testing|test2|ukdev|production",
				"",
				"Available options:",
				"   -mode       Mandatory - mode to operate in.",
				"                 " . self::PARAM_KEY_MODE_ALL_ACCOUNT .   "\t:all accounts",
				"                 " . self::PARAM_KEY_MODE_SELECTED_TNID .   "\t:selected tnids followed by comma separated TNIDs",
				"",
				"   -company    Mandatory if mode is " . self::PARAM_KEY_MODE_SELECTED_TNID . ".",
				"                 acceptable format: 52323,51606,12345",
				"",
				"   -period     Optional - period to process: ",
				"                 acceptable format: 12-2014 (MM-YYYY)",
				"                 if null, it will use current month and year",
				"",
				"   -test       Test mode.",
				"                 true|false, false by default",
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
				self::PARAM_DEF_KEYS        => '-mode',
				self::PARAM_DEF_OPTIONAL    => false,
				self::PARAM_DEF_NOVALUE		=> false,
				self::PARAM_DEF_REGEX       =>
				'/^(' . implode('|', array(
						self::PARAM_KEY_MODE_ALL_ACCOUNT,
						self::PARAM_KEY_MODE_SELECTED_TNID
				)) . ')$/',
			),
			array(
				self::PARAM_DEF_NAME        => self::PARAM_KEY_COMPANY,
				self::PARAM_DEF_KEYS        => '-company',
				self::PARAM_DEF_OPTIONAL    => true,
				self::PARAM_DEF_NOVALUE		=> false
			),
			array(
				self::PARAM_DEF_NAME        => self::PARAM_KEY_PERIOD,
				self::PARAM_DEF_KEYS        => '-period',
				self::PARAM_DEF_OPTIONAL    => true,
				self::PARAM_DEF_NOVALUE		=> false
			),
			array(
				self::PARAM_DEF_NAME        => self::PARAM_KEY_TEST_TXN,
				self::PARAM_DEF_KEYS        => '-test',
				self::PARAM_DEF_OPTIONAL    => true,
				self::PARAM_DEF_NOVALUE		=> false
			),
			array(
				self::PARAM_DEF_NAME        => self::PARAM_KEY_FILE_CSV,
				self::PARAM_DEF_KEYS        => '-csv',
				self::PARAM_DEF_OPTIONAL    => true,
				self::PARAM_DEF_NOVALUE		=> false
			)
		);
	}

	/**
	 * @throws Exception
	 */
	public function run() 
	{
		try {
			$params = $this->getParams();

		} catch (Exception $e) {
			$this->output("Parameter error: " . $e->getMessage());
			$this->displayHelp();
			return 1;
		}
		
		$cronLogger = new Myshipserv_Logger_Cron('Salesforce_Account_Manager_Report');
		$cronLogger->log();
		
		$this->output("Start");
		
		$params = $params[self::PARAM_GROUP_DEFINED];
		$logger = new Myshipserv_Logger_File('salesforce-monthly-billing-report');

		if ($params[self::PARAM_KEY_PERIOD] != null) {
			$tmp = explode("-", $params[self::PARAM_KEY_PERIOD]);
			$month = (int)$tmp[0];
			$year = $tmp[1];

			// check if it's valid
			if (($month <= 12) and (strlen($year) == 4) and ($year <= date("Y")) and ($year > 2012)) {
				$job = new Myshipserv_Salesforce_Report_AccountManager($month, $year, false);
			} else {
				$this->output("Invalid date supplied: " . $month . "-" . $year . " (MM-YYYY)");
				return 1;
			}
		} else {
			// if date isn't supplied then use current month and year
			$job = new Myshipserv_Salesforce_Report_AccountManager(date("m"),date("Y"), false);
		}

		// if user select/opted to specify a set of TNIDs
		if ($params[self::PARAM_KEY_MODE] == self::PARAM_KEY_MODE_SELECTED_TNID) {
			if (strlen($params[self::PARAM_KEY_COMPANY]) === 0) {
				$this->output("No suppliers found on the parameter.");
				return 1;

			} else {
				$companies = explode(',', $params[self::PARAM_KEY_COMPANY]);

				try {
					$job->setTnidToProcess((array)$companies);

				} catch(Exception $e) {
					$this->output($e->getMessage());
					return 1;
				}
			}
		}

		if (strlen($params[self::PARAM_KEY_FILE_CSV])) {
			// added by Yuriy Akopov on 2016-08-19, DE6906
			// request to skip the query and attempt to upload the file already available
			$job->uploadToSF($params[self::PARAM_KEY_FILE_CSV]);

		} else {
			if ($params[self::PARAM_KEY_TEST_TXN] == "true") {
				$job->runAsTestTransaction();
			}

			if (!$job->process()) {
				$this->output("Process failed, please check the log for details");
				return 1;
			}
		}

		$this->output("Finished");
		return 0;
	}
}

$script = new Cl_Main();
$status = $script->run();

exit($status);