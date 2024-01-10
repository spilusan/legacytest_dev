<?php
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

/**
 * An entry point to the Active Promotion-enabled and DB-based billing report generation workflow
 *
 * @author  Yuriy Akopov
 * @date    2016-05-03
 * @story   S16472
 */
class Cl_Main extends Myshipserv_Cli {
	const
		PARAM_KEY_TNID  = 'suppliers',
		PARAM_KEY_MONTH = 'month'
	;

	public function displayHelp() {
		print implode(PHP_EOL, array(
			"Usage: " . basename(__FILE__) . " ENVIRONMENT [OPTIONS]",
			"",
			"ENVIRONMENT has to be development|testing|test2|ukdev|production",
			"",
			"Available options:",
			"   -month       Mandatory, month as YYYY-MM to produce a billing report for",
			"",
			"   -tnid        Optional, comma-separated TNIDs if only specific suppliers should be included",
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
				self::PARAM_DEF_NAME        => self::PARAM_KEY_TNID,
				self::PARAM_DEF_KEYS        => '-tnid',
				self::PARAM_DEF_OPTIONAL    => true,
				self::PARAM_DEF_NOVALUE		=> false,
				self::PARAM_DEF_REGEX       => '/^\d+(,\d+)*$/'
			),
			array(
				self::PARAM_DEF_NAME        => self::PARAM_KEY_MONTH,
				self::PARAM_DEF_KEYS        => array('-month', '-m'),
				self::PARAM_DEF_OPTIONAL    => false,
				self::PARAM_DEF_NOVALUE		=> false,
				self::PARAM_DEF_REGEX       => '/^\d\d\d\d\-\d\d$/'
			)
		);
	}

	/**
	 * @return string
	 */
	protected function getReportFilePath() {
		$now = new DateTime();
		$filename = sys_get_temp_dir() . DIRECTORY_SEPARATOR .  "sf-billing-db_" . $now->format('Y-m-d_H-i-s') . ".csv";

		return $filename;
	}

	/**
	 * @return  int
	 * @throws  Exception
	 */
	public function run() {
		try {
			$params = $this->getParams();
		} catch (Exception $e) {
			$this->output("Parameter error: " . $e->getMessage());
			$this->displayHelp();
			return 1;
		}

		// runs on explicit request at the moment, so no cron log entry
		// $cronLogger = new Myshipserv_Logger_Cron('Salesforce_VBP_Monthly_Billing');
		// $cronLogger->log();

		$logger = new Myshipserv_Logger_File('salesforce-monthly-billing-report-db');

		$params = $params[self::PARAM_GROUP_DEFINED];

		$dateBits = explode('-', $params[self::PARAM_KEY_MONTH]);
		$dateStart = new DateTime(date('Y-m-d', mktime(0, 0, 0, $dateBits[1], 1, $dateBits[0])));
		$dateEnd   = new DateTime(date('Y-m-t', mktime(0, 0, 0, $dateBits[1], 1, $dateBits[0])));
		$dateEnd->modify('+1 day');

		if (strlen($params[self::PARAM_KEY_TNID])) {
			$supplierIds = explode(',', $params[self::PARAM_KEY_TNID]);

		} else {
			$this->output("Requesting suppliers contracted in the billed period...");
			$supplierIds = Shipserv_Supplier_Rate::getContractedSupplierIds($dateStart, $dateEnd);
			$this->output("Found " . count($supplierIds) . " suppliers contracted in " . $params[self::PARAM_KEY_MONTH]);
		}

		$reportTool = new Myshipserv_Salesforce_ValueBasedPricing_RateDb($logger, $dateStart, $dateEnd);

		$csvFilename = $this->getReportFilePath();
		if (($csv = fopen($csvFilename, 'w')) === false) {
			throw new Exception("Unable to open file " . $csvFilename . " for writing");
		} else {
			$this->output("Processing suppliers and saving results in " . $csvFilename);
		}

		fputcsv($csv, Myshipserv_Salesforce_ValueBasedPricing_RateDb::getCsvHeaders());
		$rowCount = 0;
		$supplierNo = 0;
		$errors = array();

		// calculate billing information for all included suppliers
		foreach ($supplierIds as $supplierId) {
			$supplierNo++;

			try {
				$supplierReportRows = $reportTool->getSupplierVbpCsvRows($supplierId);

				foreach ($supplierReportRows as $row) {
					fputcsv($csv, $row);
					$rowCount++;
				}

				$this->output("(" . $supplierNo . " of " . count($supplierIds) .") Wrote ". count($supplierReportRows) . " rows for supplier " . $supplierId);

			} catch (Exception $e) {
				$errMsg = get_class($e) . ": " . $e->getMessage() . " happened when processing supplier " . $supplierId;

				$errors[$supplierId] = $errMsg;
				$logger->log($errMsg);
				$this->output($errMsg);
			}
		}

		$this->output("Produced " . $rowCount . " CSV rows for " . count($supplierIds) . " suppliers");

		// email the results and stats
		$mail = new Myshipserv_SimpleEmail();
		$mail->setSubject("Value Events DB-based report for " . $params[self::PARAM_KEY_MONTH] . " in " . Myshipserv_Config::getEnv());

		if (empty($errors)) {
			$errorMessage = "There were no errors during running the reporting script";
		} else {
			$errorMessage = count($errors) . " have occurred during running the reporting script";
			$errorMessage .= "\n\nTNID\tError\n";
			foreach ($errors as $supplierId => $errMsg) {
				$errorMessage .= $supplierId . "\t" . $errMsg . "\n";
			}
		}

		$mail->setBody(implode("\n", array(
			"Please review the billing report for " . count($supplierIds) . " suppliers in month " . $params[self::PARAM_KEY_MONTH],
			"",
			$errorMessage
		)));

		$mail->setBody($mail->getBody());
		$mail->addAttachment($csvFilename, 'text/csv');
		$mail->addAttachment($logger->getFilename(), 'text/plain');
		$mail->send(Myshipserv_Config::getSalesForceSyncReportEmail(), basename(__FILE__));

		$this->output("Emailed the generated report");

		return 0;
	}
}

$script = new Cl_Main();
$status = $script->run();

exit($status);