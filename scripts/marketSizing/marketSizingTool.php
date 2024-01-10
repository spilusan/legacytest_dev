<?php
define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';

/**
 * An entry point to the command line Market Sizing tool
 *
 * @author  Yuriy Akopov
 * @date    2016-05-03
 * @story   S16472
 */
class Cl_Main extends Myshipserv_Cli
{
    /**
     * Prints usage information
     */
    public function displayHelp()
    {
        print(
            implode(
                PHP_EOL,
                array(
                    "Usage: " . basename(__FILE__) . " ENVIRONMENT [OPTIONS]",
                    "",
                    "ENVIRONMENT has to be development|testing|test2|ukdev|production",
                )
            ) .
            PHP_EOL
        );
    }

    /**
     * Defines parameters accepted by the script
     *
     * @return array
     */
    protected function getParamDefinition()
    {
        // please see displayHelp() function for parameter description
        return array();
    }

    /**
     * @return string
     */
    protected function getReportFilePath()
    {
        $now = new DateTime();
        $filename = sys_get_temp_dir() . DIRECTORY_SEPARATOR .  "market_sizing_" . $now->format('Y-m-d_H-i-s') . ".csv";

        return $filename;
    }

    /**
     * @return  int
     * @throws  Exception
     */
    public function run()
    {
        $logger = new Myshipserv_Logger_File('market-sizing-report');

        $activeSessions = Myshipserv_Search_MarketSizingDb::getActiveSessions();
        if (!empty($activeSessions)) {
            $this->output("Other sessions active, terminating...");
            return 0;
        }

        $sessionRow = Myshipserv_Search_MarketSizingDb::getNextRequest();
        if (empty($sessionRow)) {
            $this->output("No requests in the queue");
            return 0;
        }

        $logger->log("Processing session request (v-201905) " . $sessionRow['ID'] . "...");

        $csvPath = self::getReportFilePath();
        if (($csv = fopen($csvPath, 'w')) === false) {
            $this->output("Failed to open " . $csvPath);
            return 1;
        }

        fputcsv(
            $csv,
            array(
                "Keyword",
                "Country",

                "Pages Searches",

                "RFQ Events",
                "Number of RFQs",
                "RFQ Suppliers",
                "RFQ Buyers",
                "RFQ Top Buyers",
                "RFQ Line Items",

                "Number of POs",
                "Order Suppliers",
                "Order Buyers",
                "Order Top Buyers",
                "Total GMV",
                "Order Line Items",
                "Total Line Item Cost",
                "Total Qty (all UOMs)",
                "Avg. Unit Cost (all UOMs)",
                "Most Common UOM",
                "Most Common UOM %",
            )
        );

        $removeEmptyWords = function ($value) {
            return strlen(trim($value)) > 0;
        };

        $excludeFromRow = explode(PHP_EOL, $sessionRow['EXCLUDE']);
        $excludeFromRow = array_filter($excludeFromRow, $removeEmptyWords);

        $sessionKeywords = explode(PHP_EOL, $sessionRow['INCLUDE']);
        $sessionKeywords = array_filter($sessionKeywords, $removeEmptyWords);
        $sessionKeywords = Myshipserv_Search_MarketSizingDb::sanitiseBrandsInKeywords($sessionKeywords, $logger);
        // print_r($sessionKeywords); die;

        $filters = Myshipserv_Search_MarketSizingDb::getFiltersFromSessionRow($sessionRow);

        Myshipserv_Search_MarketSizingDb::startSession($sessionRow['ID']);

        ini_set('max_execution_time', 0);
        ini_set('memory_limit', '1024M');

        $rowNo = 1;
        foreach ($sessionKeywords as $rowKwd) {
            $isBrandsRows = is_array($rowKwd);

            // print PHP_EOL . PHP_EOL; print_r($rowKwd); print PHP_EOL . PHP_EOL;

            try {
                $tool = new Myshipserv_Search_MarketSizingDb($isBrandsRows, $rowKwd, $excludeFromRow, $filters);
                $this->output("Processing row " . $rowNo . ": \"" . $rowKwd . "\"...");
                $reportRow = $tool->getReportRow($logger);
                $this->output(
                    "Finished processing row " . $rowNo . ': "' .
                    (is_array($rowKwd) ? implode('", "', $rowKwd) : $rowKwd) .
                    '"'
                );

            } catch (Exception $e) {
                $logger->log(get_class($e) . ": " . $e->getMessage());
                $this->emailError($sessionRow, $e, $csvPath, $logger);

                Myshipserv_Search_MarketSizingDb::stopSession($sessionRow['ID']);

                throw $e;
            }

            foreach (array_keys($reportRow['rfqs']) as $countryName) {
                fputcsv(
                    $csv,
                    array(
                        $reportRow['keywords'],
                        $countryName,
                        $reportRow['pagesSearchCount'][$countryName]['COUNT'],
                        $reportRow['rfqs'][$countryName]['RFQ_EVENT_COUNT'],
                        $reportRow['rfqs'][$countryName]['RFQ_COUNT'],
                        $reportRow['rfqs'][$countryName]['RFQ_SPB_COUNT'],
                        $reportRow['rfqs'][$countryName]['RFQ_BYB_COUNT'],
                        $reportRow['rfqs'][$countryName]['RFQ_BYB_TOP_COUNT'],
                        $reportRow['rfqs'][$countryName]['RFQ_LI_COUNT'],

                        $reportRow['orders'][$countryName]['ORD_COUNT'],
                        $reportRow['orders'][$countryName]['ORD_SPB_COUNT'],
                        $reportRow['orders'][$countryName]['ORD_BYB_COUNT'],
                        $reportRow['orders'][$countryName]['ORD_BYB_TOP_COUNT'],
                        round($reportRow['orders'][$countryName]['ORD_TOTAL_COST'], 2),

                        $reportRow['orders'][$countryName]['ORD_LI_COUNT'],
                        round($reportRow['orders'][$countryName]['ORD_LI_COST'], 2),
                        round($reportRow['orders'][$countryName]['ORD_LI_QUANTITY'], 2),
                        round($reportRow['orders'][$countryName]['ORD_UNIT_COST'], 2),
                        $reportRow['orders'][$countryName]['ORD_UNIT'],
                        round($reportRow['orders'][$countryName]['ORD_UNIT_SHARE'], 2)
                    )
                );
            }

            // $excludeFromRow[] = $rowKwd;   // exclude current row keyword from the next row results
            $rowNo++;
        }

        fclose($csv);
        Myshipserv_Search_MarketSizingDb::stopSession($sessionRow['ID']);
        $logger->log("Finished the report - session stopped.");

        $this->emailReport($sessionRow['ID'], $csvPath, $logger);

        return 0;
    }

    /**
     * @param array $sessionRow
     * @return string
     */
    protected function getSessionEmailBody(array $sessionRow)
    {
        return implode(
            "\n",
            array(
                "Request ID: " . $sessionRow['ID'],
                "Requested at: " . $sessionRow['REQUESTED'],
                "Session started at: " . $sessionRow['STARTED'],
                "Session finished at: " . $sessionRow['ENDED'],
                "",
                "Date from: " . $sessionRow['DATE_FROM'],
                "Date till: " . $sessionRow['DATE_TILL'],
                "Vessel type: " . $sessionRow['VESSEL_TYPE'],
                "Locations: " . $sessionRow['LOCATIONS'],
                "",
                "Keywords to include: " . $sessionRow['INCLUDE'],
                "",
                "Keywords to exclude: " . $sessionRow['EXCLUDE']
            )
        );
    }

    /**
     * Sends an email about
     *
     * @param   array       $sessionRow
     * @param   Exception   $e
     * @param   string      $csvPath
     * @param   Myshipserv_Logger_File $logger
     * @throws Exception
     */
    public function emailError(array $sessionRow, Exception $e, $csvPath, Myshipserv_Logger_File $logger)
    {
        // email the results and stats
        $mail = new Myshipserv_SimpleEmail(false);
        $mail->setSubject("Error in Market Sizing report " . $sessionRow['REQUESTED']);

        $mail->setBody(
            implode(
                "\n",
                array_merge(
                    array(
                        $this->getSessionEmailBody($sessionRow),
                        "",
                        "Exception type: " . get_class($e),
                        "Exception message: " . $e->getMessage(),
                        "",
                        "Stack trace:",
                    ),
                    $e->getTrace()
                )
            )
        );

        $mail->addAttachment($csvPath, 'text/csv');
        $mail->addAttachment($logger->getFilename(), 'text/plain');

        $mail->send(Myshipserv_Config::getMarketSizingCcEmail());
    }

    /**
     * @param   int                     $sessionId
     * @param   string                  $csvPath
     * @param   Myshipserv_Logger_File  $logger
     *
     * @throws Exception
     */
    public function emailReport($sessionId, $csvPath, Myshipserv_Logger_File $logger)
    {
        $sessionRow = Myshipserv_Search_MarketSizingDb::getRequestById($sessionId);

        $recipients = array(
            $sessionRow['EMAIL'],
            Myshipserv_Config::getMarketSizingCcEmail()
        );
        $logger->log("Emailing report results (" . $logger->getFilename() . ") to " . implode(", ", $recipients));

        // email the results and stats
        $mail = new Myshipserv_SimpleEmail(false);
        $mail->setSubject("Market Sizing report requested at " . $sessionRow['REQUESTED']);

        $mail->setBody($this->getSessionEmailBody($sessionRow));

        $mail->addAttachment($csvPath, 'text/csv');
        $mail->addAttachment($logger->getFilename(), 'text/plain');

        $mail->send($recipients);
    }
}

$script = new Cl_Main();
$status = $script->run();

exit($status);