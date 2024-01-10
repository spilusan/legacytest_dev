<?php
/**
 * WARNING:
 *
 * THIS HAS BEEN RETIRED IN FAVOUR OF salesforceMonthlyBilling.php
 *
 * At the moment this is a legacy script with a partial support for Active Promotion and multi-tiered rates
 *
 * The new script has full support for those features
 *
 * /Yuriy Akopov on 2016-05-04/
 *
 */

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Cl_Main extends Myshipserv_Cli {
	const PARAM_KEY_MODE = 'mode';
	const PARAM_KEY_MODE_ALL_ACCOUNT = 'all';
	const PARAM_KEY_MODE_SELECTED_TNID = 'tnid';
	const PARAM_KEY_TEST_TXN = 'test';
	const PARAM_KEY_COMPANY = 'company';
	const PARAM_KEY_PERIOD = 'period';
	
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
				)
		);
	}

	/**
	 * @throws Exception
	 */
	public function run() 
	{
		try 
		{
			$params = $this->getParams();
		} 
		catch (Exception $e) 
		{
			$this->output("Parameter error: " . $e->getMessage());
			$this->displayHelp();
			return 1;
		}
		
		$cronLogger = new Myshipserv_Logger_Cron( 'Salesforce_VBP_Monthly_Billing_Upload' );
		$cronLogger->log();
		
		$this->output("Start");
		
		$params = $params[self::PARAM_GROUP_DEFINED];
		$logger = new Myshipserv_Logger_File('salesforce-monthly-billing-report');
		
		// check if period is supplied
		if( $params[self::PARAM_KEY_PERIOD] != null )
		{
			$tmp = explode("-", $params[self::PARAM_KEY_PERIOD]);
			$month = (int)$tmp[0];
			$year = $tmp[1];

			// check if it's valid
			if( $month <= 12 && strlen($year) == 4 && $year <= date("Y") && $year > 2012 )
			{
				$job = new Myshipserv_Salesforce_Report_Billing($month, $year, false);
                $job->runAsCustomPeriodMode();
			}
			// if it is not then throw error
			else 
			{
				$this->output("Invalid date supplied: " . $month . "-" . $year . " (MM-YYYY)");
				exit();
			}
		}
        // if date isn't supplied then use current month and year
		else
		{
			$job = new Myshipserv_Salesforce_Report_Billing(date("m"),date("Y"), false);
		}

		// if user select/opted to specify a set of TNIDs
		if( $params[self::PARAM_KEY_MODE] == self::PARAM_KEY_MODE_SELECTED_TNID )
		{
			if( $params[self::PARAM_KEY_COMPANY] == "" || $params[self::PARAM_KEY_COMPANY] == null )
			{
				$this->output("No suppliers found on the parameter.");
				exit;
			}
			else 
			{
				$companies = explode(",", $params[self::PARAM_KEY_COMPANY]);
				try 
				{
					$job->setTnidToProcess((array)$companies);
				}
				catch(Exception $e)
				{
					$this->output($e->getMessage());
					exit;	
				}
			}
		}
		
		// check if it's a test mode
		if( $params[self::PARAM_KEY_TEST_TXN] == "true" )
		{
			$job->runAsTestTransaction();
		} else {
			throw new Exception("Upload of value events to SalesForce has been disabled until we  mandatory manual control is no longer required");
		}
		
		$job->process();

		$this->output("Finished");
		return 0;
	}
}

$script = new Cl_Main();
$status = $script->run();

exit($status);