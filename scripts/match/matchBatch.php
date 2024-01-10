<?php
/**
 * CLI interface to run searches against the RFQs and store search results
 *
 * @author  Yuriy Akopov
 * @date    2013-09-02
 * @story   8133
 */

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

class Cl_Main extends Myshipserv_Cli {
    // command line parameter keys - see displayHelp() for explanation
    const
        PARAM_KEY_MINS_AGO       = 'minAgo',
        PARAM_KEY_MINS_AGO_SHORT = '-m',

        PARAM_KEY_FOLDER         = 'folder',
        PARAM_KEY_FOLDER_SHORT   = '-f',

        PARAM_KEY_DEBUG          = 'debug',
        PARAM_KEY_DEBUG_SHORT    = '-d'
    ;

    public function displayHelp() {
        print implode(PHP_EOL, array(
            "Usage: " . basename(__FILE__) . " ENVIRONMENT [OPTIONS] [RFQIDS]",
            "",
            "ENVIRONMENT has to be development|testing|test2|ukdev|production",
            "",
            "Available options:",

            "   " . self::PARAM_KEY_MINS_AGO_SHORT . "          RFQs from how many minutes ago to process",
            "",

            "   " . self::PARAM_KEY_FOLDER_SHORT . "          Folder for text and CSV logs - if not supplied, no logs would be generated",
            "",

            "   " . self::PARAM_KEY_DEBUG_SHORT . "          If log folder if specified, this option will add a CSV with Solr stats there",
            "",

            "[RFQIDS]  is one or more RFQ identifiers - when they are specified",
            "          only these RFQs will be processed"
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
                self::PARAM_DEF_NAME        => self::PARAM_KEY_MINS_AGO,
                self::PARAM_DEF_KEYS        => self::PARAM_KEY_MINS_AGO_SHORT,
                self::PARAM_DEF_OPTIONAL    => true,
                self::PARAM_DEF_DEFAULT     => 30,
                self::PARAM_DEF_REGEX       => '/^\d+$/'
            ),
            array(
                self::PARAM_DEF_NAME        => self::PARAM_KEY_FOLDER,
                self::PARAM_DEF_KEYS        => self::PARAM_KEY_FOLDER_SHORT,
                self::PARAM_DEF_OPTIONAL    => true
            ),
            array(
                self::PARAM_DEF_NAME        => self::PARAM_KEY_DEBUG,
                self::PARAM_DEF_KEYS        => self::PARAM_KEY_DEBUG_SHORT,
                self::PARAM_DEF_OPTIONAL    => true,
                self::PARAM_DEF_NOVALUE     => true
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

        $debugMode = $params[self::PARAM_GROUP_DEFINED][self::PARAM_KEY_DEBUG];
        if ($debugMode) {
            $this->output('Debug mode enabled');
        }

        $logFolderPath = $params[self::PARAM_GROUP_DEFINED][self::PARAM_KEY_FOLDER];
        $rfqIds = $params[self::PARAM_GROUP_UNDEFINED];

        if (count($rfqIds) === 0) {
            $minsAgo = $params[self::PARAM_GROUP_DEFINED][self::PARAM_KEY_MINS_AGO];
            if (strlen($minsAgo) === 0) {
                $this->displayHelp();
                return 1;
            }

            $dayDepth = (1 / 24 / 60) * $minsAgo;

            $queue = new Shipserv_Match_Batch_Queue_SelectedBuyers($dayDepth, $logFolderPath, $debugMode);
        } else {
            $queue = new Shipserv_Match_Batch_Queue_UserRfqs($rfqIds, $logFolderPath, $debugMode);
        }

        $timeStart = microtime(true);
        while (true) {
            try {
                $queue->processNextPage();
            } catch (Shipserv_Match_Batch_Queue_FinishedException $e) {
                break;
            }

            if ($debugMode) {
                // in debug mode, stop after so many searches performed
                if ($queue->getRfqsProcessed() >= 100) {
                    break;
                }
            }
        }
        $elapsed = microtime(true) - $timeStart;

        $this->output("Finished after processing " . $queue->getRfqsProcessed() . " RFQs in " . Myshipserv_View_Helper_String::secondsToString($elapsed));

        return 0;
    }
}

$script = new Cl_Main();
$status = $script->run();

exit($status);
