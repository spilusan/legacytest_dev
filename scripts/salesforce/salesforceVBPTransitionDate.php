<?php
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Cl_Main
{
	/**
	 * Creates / resets a CSV file to log SalesForce sync errors
	 *
	 * @return  string
	 */
	public static function getSyncErrorsCsvFilename() {
		$now = new DateTime();
		$filename =
			sys_get_temp_dir() . DIRECTORY_SEPARATOR .
			basename(__FILE__, '.php') . '_errors_' . $now->format('Y-m-d') . '.csv'
		;

		return $filename;
	}

	public static function main() {
		$tnid = null;

		global $argv;
		if ((count($argv) > 2) and (strlen($argv[2]))) {
			$tnid = $argv[2];
		}

		// added by Yuriy Akopov to suppress numerous access to non-initialised stdClass fields in the legacy code
		error_reporting(E_ALL^E_NOTICE);

		$csvFilename = self::getSyncErrorsCsvFilename();
		if (($csv = fopen($csvFilename, 'w')) === false) {
			throw new Exception("Cannot open a file " . $csvFilename . " to log possible sync errors");
		}

		$app = new Myshipserv_Salesforce_ValueBasedPricing_Rate($csv);

		$executionTime = ini_set('max_execution_time', null);

		$result = $app->pullVBPAndPOPackPercentage($tnid);

		ini_set('max_execution_time', $executionTime);

		// email the CSV file generated with sync errors in it or that everything went without errors
		$mail = new Myshipserv_SimpleEmail();

		if ($app->getSyncErrorCount() === 0) {
			// there were no errors, file can be deleted
			$subjectPostfix = "OK";
			$message = "There were no errors during the sync" . PHP_EOL;
		} else {
			$subjectPostfix = "errors";
			$message = "There were " . $app->getSyncErrorCount() . " errors during the sync, please check " . basename($csvFilename) . " for details" . PHP_EOL;
			$mail->addAttachment($csvFilename, 'text/csv');
		}

		$mail->setSubject("SalesForce rates sync in " . Myshipserv_Config::getEnv() . ": " . $subjectPostfix);
		$mail->setBody($message);
		$mail->send(Myshipserv_Config::getSalesForceSyncReportEmail(), basename(__FILE__));

		print($message);

		// this is deprecated replace with above		
		//$result = $app->pullVBPTransitionDate();
		//$result = $app->pullVBPTransitionDateForSubContractedAccount();
		
		$cronLogger = new Myshipserv_Logger_Cron( 'Salesforce_VBP_Transition_Date' );
		$cronLogger->log();

		if (is_null($tnid)) {
			$executionTime = ini_set('max_execution_time', null);

			$app = new Myshipserv_Salesforce_ValueBasedPricing();
			$result = $app->downloadAllVBPObjects();
			
			ini_set('max_execution_time', $executionTime);
		}
	}
}

$memoryLimit = ini_set('memory_limit', '1024M');
Cl_Main::main();








