<?php

/**
 * Parent class for running searches for incoming RFQs so stored searches are ready for users in advance
 * Descendant classes define the rules of binding the queue of such RFQs
 *
 * @author  Yuriy Akopov
 * @date    2014-06-16
 * @story   S10315
 */
abstract class Shipserv_Match_Batch_Queue_Abstract {
    /**
     * Number of RFQs processed in the session
     *
     * @var int
     */
    protected $rfqsProcessed = 0;

    /**
     * ID of the RFQ currently processed
     *
     * @var int
     */
    protected $currentRfqId = null;

    /**
     * Unique ID of the current session
     *
     * @var string
     */
    protected $sessionId = null;

    /**
     * File to log text messages in
     *
     * @var resource|null
     */
    protected $logHandleTxt = null;

    /**
     * File to log stats in
     *
     * @var resource|null
     */
    protected $logHandleCsv = null;

    /**
     * File to log Solr request stats to
     *
     * @var resource|null
     */
    protected $logHandleSolr = null;

    /**
     * Paginator with RFQs to process
     *
     * @var Zend_Paginator
     */
    protected $paginator = null;

    /**
     * @var null|float
     */
    protected $longestQueryTime = null;

    /**
     * @return int
     */
    public function getRfqsProcessed() {
        return $this->rfqsProcessed;
    }

    /**
     * @return int
     */
    public function getCurrentRfqId() {
        return $this->currentRfqId;
    }

    /**
     * @return string
     */
    public function getSessionId() {
        return $this->sessionId;
    }

    /**
     * @return float|null
     */
    public function getLongestQueryTime() {
        return $this->longestQueryTime;
    }

    /**
     * @param   string      $logFolder
     * @param   bool        $debugLog
     *
     * @throws Shipserv_Match_Batch_Exception
     */
    public function __construct($logFolder = null, $debugLog = false) {
        $this->sessionId = uniqid();

        if (!is_null($logFolder)) {
            // text log
            $path = $logFolder . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
            if (($this->logHandleTxt = fopen($path, 'a')) === false) {
                throw new Shipserv_Match_Batch_Exception('Cannot open text log file at ' . $path);
            }

            // CSV log with per-RFQ stats
            $path = $logFolder . DIRECTORY_SEPARATOR . date('Y-m-d') . '.csv';
            $csvExistedBefore = file_exists($path);

            if (($this->logHandleCsv = fopen($path, 'a')) === false) {
                throw new Shipserv_Match_Batch_Exception('Cannot open CSV file at ' . $path);
            }

            if (!$csvExistedBefore) {
                // CSV file has been just created, write CSV headers
                fputcsv($this->logHandleCsv, array(
                    'Session ID',
                    'Datetime',
                    'RFQ ID',
                    'Search ID',
                    'Time Elapsed',
                    'Supplier Count'
                ));
            }

            // debug log with per-Solr request stats
            if ($debugLog) {
                $path = $logFolder . DIRECTORY_SEPARATOR . date('Y-m-d') . '-debug.csv';
                $csvExistedBefore = file_exists($path);

                if (($this->logHandleSolr = fopen($path, 'a')) === false) {
                    throw new Shipserv_Match_Batch_Exception('Cannot open CSV file at ' . $path);
                }

                if (!$csvExistedBefore) {
                    // CSV file has been just created, write CSV headers
                    fputcsv($this->logHandleSolr, array(
                        'Session ID',
                        'Datetime',
                        'RFQ ID',
                        'Tag',
                        'Time Elapsed'
                    ));
                }
            }
        }
    }

    /**
     * Returns paginator with RFQs to be processed
     *
     * @return  Zend_Paginator
     */
    public function getRfqPaginator() {
        if (is_null($this->paginator)) {
            throw new Shipserv_Match_Batch_Exception("This function needs to be overridden in descendant classes when an RFQ queue is defined");
        }

        return $this->paginator;
    }

    /**
     * To be overridden in descendant - converts paginator item into an RFQ id
     *
     * @param   array|int   $item
     * @return  int
     */
    abstract protected function pageItemToRfqId($item);

    /**
     * Requests unprocessed RFQ IDs and runs / stores searches for them
     *
     * @throws Shipserv_Match_Batch_Queue_FinishedException
     */
    public function processNextPage() {
        $paginator = $this->getRfqPaginator();

        $curPageNo  = $paginator->getCurrentPageNumber();
        $totalPages = count($paginator);

        $this->toTextLog("Preparing to process page " . $curPageNo . " of " . $paginator->getItemCountPerPage() . " RFQs");

        // load date from paginator
        $timeStart = microtime(true);
        $rows = $paginator->getCurrentItems();
        if (empty($rows)) {
            throw new Shipserv_Match_Batch_Queue_FinishedException("No RFQ data retrieved from page " . $curPageNo . " pages, finishing processing the queue", $this->rfqsProcessed);
        }

        $this->toTextLog("Loaded " . count($rows) . " IDs in " . Myshipserv_View_Helper_String::secondsToString((microtime(true) - $timeStart)));

        $timeStart = microtime(true);
        $prevRfqProcessed  = $this->rfqsProcessed;

        foreach ($rows as $row) {
            $this->currentRfqId = $this->pageItemToRfqId($row);

            if ($this->matchRfq($this->currentRfqId)) {
                // $this->toTextLog("Processed RFQ " . $this->currentRfqId);
                $this->rfqsProcessed++;
            } else {
                // $this->toTextLog("Skipping RFQ " . $this->currentRfqId . " as it has already been processed");
            };
        }

        $this->toTextLog("Finished processing page " . $curPageNo . ", " . ($this->rfqsProcessed - $prevRfqProcessed) ." searches took " . Myshipserv_View_Helper_String::secondsToString((microtime(true) - $timeStart)));

        // check if everything is now processed
        $nextPageNo = $curPageNo + 1;
        if ($nextPageNo > $totalPages) {
            throw new Shipserv_Match_Batch_Queue_FinishedException("Finished processing " . $totalPages . " pages of RFQ queue", $this->rfqsProcessed);
        }

        // switch to the next page
        $paginator->setCurrentPageNumber($nextPageNo);
    }

    /**
     * Runs search for the given RFQ and stores its results
     *
     * @param   int $rfqId
     *
     * @return  bool
     * @throws  Exception
     */
    protected function matchRfq($rfqId) {
        $timeStart = microtime(true);

        // check if a search already exists for the event
        try {
            $search =  Shipserv_Match_Component_Search::getForRfq($rfqId, false);
            // search is already stored for one of the RFQs in the same event
            return false;
        } catch (Shipserv_Helper_Database_Exception $e) {
            // search doesn't exist, carry on
        }

        $oldTimeOut = ini_set('max_execution_time', 0); // starting a time-consuming process of searching for suppliers matching our RFQ

        try {
            $match = new Shipserv_Match_Match($rfqId);
        } catch (Exception $e) {
            $this->toTextLog("An error occurred while tokenising RFQ " . $rfqId . ": " . get_class($e) . " (" . $e->getMessage() . ")");
            throw $e;
        }

        $debugLogMode = is_resource($this->logHandleSolr);
        if ($debugLogMode) {
            Myshipserv_Search_Match::initStats();
        }

        try {
            $suppliers = $match->getMatchedSuppliers();
        } catch (Exception $e) {
            $this->toTextLog("An error occurred while matching RFQ " . $rfqId . ": " . get_class($e) . " (" . $e->getMessage() . ")");
            throw $e;
        }

        if ($debugLogMode) {
            $stats = Myshipserv_Search_Match::getStats();
            $this->toSolrLog($rfqId, $stats);
        }

        ini_set('max_execution_time', $oldTimeOut); // finished with the time-consuming operation

        try {
            $search = Shipserv_Match_Component_Search::fromMatchEngine($match, false);
        } catch (Exception $e) {
            $this->toTextLog("An error occurred while storing search results for RFQ " . $rfqId . ": " . get_class($e) . " (" . $e->getMessage() . ")");
            throw $e;
        }

        $this->toStatsLog($rfqId, $search, microtime(true) - $timeStart, count($suppliers));

        return true;
    }

    /**
     * Writes a message to text log if logging is enabled
     *
     * @param   string  $message
     * @param   bool    $output
     *
     * @return  bool
     */
    public function toTextLog($message, $output = true) {
        $message .= PHP_EOL;

        if ($output) {
            print implode(' ', array(
                "[" . date('Y-m-d H:i:s') . "]",
                $message
            ));
        }

        if (!is_resource($this->logHandleTxt)) {
            return false;
        }

        $result = fwrite($this->logHandleTxt, implode(' ', array(
            "[" . date('Y-m-d H:i:s') . "]",
            "[" . $this->sessionId . "]",
            $message
        )));

        return ($result !== false);
    }

    /**
     * Writes a time report about an RFQ processed if logging is enabled
     *
     * @param   int                             $rfqId
     * @param   Shipserv_Match_Component_Search $search
     * @param   float                           $elapsed
     * @param   int                             $resultCount
     *
     * @return bool
     */
    protected function toStatsLog($rfqId, $search, $elapsed, $resultCount) {
        if (!is_resource($this->logHandleCsv)) {
            return false;
        }

        $result = fputcsv($this->logHandleCsv, array(
            $this->sessionId,
            date('Y-m-d H:i:s'),
            $rfqId,
            $search->getId(),
            $elapsed,
            $resultCount
        ));

        return ($result !== false);
    }

    /**
     * Writes detailed time report on per-RFQ basis
     *
     * @param   int     $rfqId
     * @param   array   $stats
     *
     * @return bool
     */
    protected function toSolrLog($rfqId, array $stats) {
        if (!is_resource($this->logHandleSolr)) {
            return false;
        }

        foreach ($stats as $record) {
            fputcsv($this->logHandleSolr, array(
                $this->sessionId,
                date('Y-m-d H:i:s'),
                $rfqId,
                $record[Myshipserv_Search_Match::SOLR_STAT_TAG][Shipserv_Match_Match::TERM_TAG],
                $record[Myshipserv_Search_Match::SOLR_STAT_TIME]
            ));
        }

        return true;
    }
}