<?php

use Google\AdsApi\AdManager\AdManagerServices;
use Google\AdsApi\AdManager\Util\v201902\ReportDownloader;
//use Google\AdsApi\AdManager\Util\v201902\StatementBuilder;
use Google\AdsApi\AdManager\v201902\ExportFormat;
use Google\AdsApi\AdManager\v201902\ReportJob;
use Google\AdsApi\AdManager\v201902\ReportQuery;
use Google\AdsApi\AdManager\Util\v201902\AdManagerDateTimes;

/**
 * Class Myshipserv_Dfp_RunReport
 *
 * This class will extract data from Google using google DFP library
 * The requested report parameters must be injected and the file where we save it.
 *
 * On construct it requires a session which is created by Myshipserv_Dfp_GetCurrentUser
 */
class Myshipserv_Dfp_RunReport
{

    protected $session;

    /**
     * Set session
     *
     * Myshipserv_Dfp_RunReport constructor.
     * @param object $session
     */
    public function __construct($session)
    {
        $this->session = $session;
    }

    /**
     * Run the report on google DFP
     *
     * @param Myshipserv_Dfp_ReportParams $reportParams
     * @param string $filePath
     * @return bool|string
     * @throws Myshipserv_Exception_MessagedException
     */
    public function runReport(Myshipserv_Dfp_ReportParams $reportParams, $filePath)
    {

        $serviceType = $reportParams->getServiceType();
        $dimensions = $reportParams->getDimensions();
        $dimensionAttributes = $reportParams->getDimensionAttributes();
        $columns = $reportParams->getColumns();
        $dateRangeType = $reportParams->getDateRangeType();
        $startDate = $reportParams->getStartDate();
        $endDate = $reportParams->getEndDate();

        $dfpServices = new AdManagerServices();
        $reportService = $dfpServices->get($this->session, $serviceType);

        // Create statement to filter on a parent ad unit with the root ad unit ID
        // to include all ad units in the network.

        //$statementBuilder = new StatementBuilder();

        // Create report query.
        $reportQuery = new ReportQuery();

        if ($dimensions !== null) {
            $reportQuery->setDimensions($dimensions);
        }


        if ($dimensionAttributes !== null) {
            $reportQuery->setDimensionAttributes($dimensionAttributes);
        }

        if ($columns !== null) {
            $reportQuery->setColumns($columns);
        }

        $reportQuery->setDateRangeType($dateRangeType);

        $reportQuery->setStartDate(
            AdManagerDateTimes::fromDateTime($startDate)->getDate()
        );

        $reportQuery->setEndDate(
            AdManagerDateTimes::fromDateTime($endDate)->getDate()
        );

        // Set the filter statement.
        //$reportQuery->setStatement($statementBuilder->toStatement());

        // Set the ad unit view to hierarchical.
        // $reportQuery->setAdUnitView(ReportQueryAdUnitView::HIERARCHICAL);

        // Create report job and start it.
        $reportJob = new ReportJob();
        $reportJob->setReportQuery($reportQuery);
        $reportJob = $reportService->runReportJob($reportJob);

        $this->downloadReport($reportService, $reportJob->getId(), $filePath);
        $extactedFileName = $this->extractReport($filePath);

        return $extactedFileName;
    }


    /**
     * @param object $reportService
     * @param int $reportJobId
     * @param string $filePath
     * @throws Myshipserv_Exception_MessagedException
     */
    protected function downloadReport($reportService, $reportJobId, $filePath)
    {
        // Create report downloader to poll report's status and download when ready.
        $reportDownloader = new ReportDownloader($reportService, $reportJobId);
        if ($reportDownloader->waitForReportToFinish()) {
            // Download the report.
            $reportDownloader->downloadReport(ExportFormat::CSV_DUMP, $filePath);
        } else {
            throw  new Myshipserv_Exception_MessagedException('Google DFP, unable to execute report', 500);
        }
    }

    /**
     * Extract Gzip file
     * Output file name is the original without the extension
     * The extracted file name returned
     *
     * @param string $filePath
     * @return bool|string
     */
    protected function extractReport($filePath)
    {
        $dstName = str_replace('.gz', '', $filePath);
        $sfp = gzopen($filePath, 'rb');
        $fp = fopen($dstName, 'w');

        while ($string = gzread($sfp, 4096)) {
            fwrite($fp, $string, strlen($string));
        }

        gzclose($sfp);
        fclose($fp);

        if (unlink($filePath)) {
            return $dstName;
        }

        return false;
    }

}

