<?php
// Bootstrap Zend & app

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

/**
 * SIR Google API Extracted
 *
 * Class Cl_Main
 */
class Cl_Main extends Myshipserv_Cli
{
	const PARAM_KEY_MODE = 'm';

	const MODE_DOWNLOAD_GOOGLE_DATA           = 'download-google-data';
    const MODE_RESET_GOOGLE_DATA              = 'reset-google-data';
    const MODE_RECONCILE_GOOGLE_DATA          = 'reconcile-google-data';
    const MODE_ENABLE_ALL_BASIC_LISTER_ACCESS = 'enable-all-basic-lister-access';
    const MODE_DOWNLOAD_ACTIVE_BANNER         = 'download-active-banner';
    const MODE_INITIALISE_OATH2               = 'initialise-oauth2';
	
	/**	
	 * Defines parameters accepted by the script
	 *
	 * @return array
	 */
	protected function getParamDefinition()
    {
		// please see displayHelp() function for parameter description
		return array(
				array(
						self::PARAM_DEF_NAME        => self::PARAM_KEY_MODE,
						self::PARAM_DEF_KEYS        => '-m',
						self::PARAM_DEF_OPTIONAL    => false,
						self::PARAM_DEF_REGEX       =>
						'/^(' . implode('|', array(
								self::MODE_DOWNLOAD_GOOGLE_DATA,
								self::MODE_RESET_GOOGLE_DATA,
								self::MODE_RECONCILE_GOOGLE_DATA,
								self::MODE_ENABLE_ALL_BASIC_LISTER_ACCESS,
								self::MODE_DOWNLOAD_ACTIVE_BANNER,
								self::MODE_INITIALISE_OATH2
						)) . ')$/',
				)
		);
	}

    /**
     * Display help info
     */
	public function displayHelp() {
		print implode(PHP_EOL, array(
                    'Usage: ' . basename(__FILE__) . ' ENVIRONMENT [OPTIONS]',
                    '',
                    'ENVIRONMENT has to be development|testing|test2|ukdev|production',
                    '',
                    'Available options:',
                    '   -m          Mandatory option - mode to operate in.',
                    '               Allowed modes are:',
                    '                 ' . self::MODE_DOWNLOAD_GOOGLE_DATA .   '   - download daily impression/clicks data for SIR from Google DFP',
                    '                 ' . self::MODE_RESET_GOOGLE_DATA .   '   - truncate Google DFP data on Pages',
                    '                 ' . self::MODE_RECONCILE_GOOGLE_DATA .   '   - truncate and re-pull Google DFP data on Pages',
                    '                 ' . self::MODE_ENABLE_ALL_BASIC_LISTER_ACCESS .   '   - enable SIR access for basic lister',
                    '                 ' . self::MODE_DOWNLOAD_ACTIVE_BANNER .   '   - download active banners',
                    '                 ' . self::MODE_INITIALISE_OATH2 .   '   - Initialising oauth2 authentication',
                    ''
		        )
            ) . PHP_EOL;
	}

    /**
     * Run DFP extraction and storing the result in Database
     *
     * @return int
     * @throws Exception
     */
	public function run()
	{
		// No max execution time
		ini_set('max_execution_time', 0);

		// No upper memory limit
		ini_set('memory_limit', -1);

		// get the mode
		try {
			$params = $this->getParams();
		} catch (Exception $e) {
			$this->output('Parameter error: ' . $e->getMessage());
			$this->displayHelp();
			return 1;
		}
		
		$params = $params[self::PARAM_GROUP_DEFINED];

		$cronLogger = new Myshipserv_Logger_Cron('SIR_' . $params[self::PARAM_KEY_MODE]);
		$cronLogger->log();
		
		switch ($params[self::PARAM_KEY_MODE]) {
			
			// This will download impression/clicks data for SIR from Google DFP
			case self::MODE_DOWNLOAD_GOOGLE_DATA:
				$adapter = new Shipserv_Adapters_Report_GoogleDFP();
				$adapter->updateTableWithLatestDataFromGoogle(true);
				break;
				
			// This will truncate Google DFP data on Pages
			case self::MODE_RESET_GOOGLE_DATA:
				$adapter = new Shipserv_Adapters_Report_GoogleDFP();
				$adapter->resetTable(true);
				break;
			
			// This will truncate and re-pull Google DFP data on Pages
			case self::MODE_RECONCILE_GOOGLE_DATA:
				$adapter = new Shipserv_Adapters_Report_GoogleDFP();
				$adapter->resetTable(true);
				$adapter->updateTableWithLatestDataFromGoogle(true);
				break;

			// This will enable SIR access for basic lister
			case self::MODE_ENABLE_ALL_BASIC_LISTER_ACCESS:
				$generator = new Myshipserv_SIREnableBasicListerAccess_Generator();
				$generator->generate();
				break;
			
			// download active banners
			case self::MODE_DOWNLOAD_ACTIVE_BANNER:
				$adapter = new Shipserv_Adapters_Report_GoogleDFP();
				$adapter->getActiveBanner();
				break;
				
			// Initialising oauth2 authentication
			case self::MODE_INITIALISE_OATH2:
				$adapter = new Shipserv_Adapters_Report_GoogleDFP();
				$adapter->performAuthenticationCheck();
				break;				
				
		}
	}
}

$script = new Cl_Main();
$status = $script->run();

exit($status);
