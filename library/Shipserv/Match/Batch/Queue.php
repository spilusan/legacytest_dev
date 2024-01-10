<?php
class Shipserv_Match_Batch_Queue {
    /**
     * Size of the page requested at once
     *
     * @var int
     */
    protected $pageSize = null;

    /**
     * Number of days to process RFQs from counting from current moment
     *
     * @var int|null
     */
    protected $dayDepth = null;

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
     * Number of the current page of RFQ IDs loaded in a row
     *
     * @var int
     */
    protected $currentPageNo = 0;

    /**
     * Paginator with RFQs to process
     *
     * @var Zend_Paginator
     */
    protected $paginator = null;

    /**
     * @return int
     */
    public function getPageSize() {
        return $this->pageSize;
    }

    /**
     * @return int|null
     */
    public function getDayDepth() {
        return $this->dayDepth;
    }

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
     * @return int
     */
    public function getCurrentPageNo() {
        return $this->currentPageNo;
    }

    /**
     * @return Zend_Paginator
     */
    public function getPaginator() {
        return $this->paginator;
    }

    /**
     * @param   int         $pageSize
     * @param   DateTime    $fromDate
     * @param   string      $logFolder
     *
     * @throws Shipserv_Match_Batch_Exception
     */
    public function __construct($pageSize, $dayDepth, $logFolder = null) {
        $this->pageSize = $pageSize;
        $this->dayDepth = $dayDepth;

        $this->sessionId = uniqid();

        if (!is_null($logFolder)) {

            $path = $logFolder . DIRECTORY_SEPARATOR . date('Y-m-d') . '.log';
            if (($this->logHandleTxt = fopen($path, 'a')) === false) {
                throw new Shipserv_Match_Batch_Exception('Cannot open text log file at ' . $path);
            }

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
                    'Rfq ID',
                    'Search ID',
                    'Time Elapsed',
                    'Supplier Count'
                ));
            }
        }

        $this->paginator = Zend_Paginator::factory($this->getPageSelect());
        $this->paginator->setDefaultItemCountPerPage($this->pageSize);
    }

    /**
     * Returns a query to grab a given number of RFQs to process
     *
     * @return  Zend_Db_Select
     * @throws  Exception
     */
    public function getPageSelect() {
        $buyerOrgIds = Myshipserv_Config::getMatchBuyerIds();
        $this->toTextLog("Preparing to process RFQs from the following buyer organisations: " . implode(', ', $buyerOrgIds));

        $buyerOrgs = array();
        foreach ($buyerOrgIds as $orgId) {
            try {
                $buyerOrgs[] = Shipserv_Buyer::getInstanceById($orgId);
            } catch (Exception $e) {
                continue;
            }
        }

        if (empty($buyerOrgs)) {
            throw new Exception("No valid buyer organisations found, aborting batch processing");
        }

        $select = Shipserv_Rfq_EventManager::getEventListSelect(
            $buyerOrgs,
            array(
                Shipserv_Rfq::COL_ID => new Zend_Db_Expr('MIN(rfq.' . Shipserv_Rfq::COL_ID . ')')
            ),
            null,
            $this->getDayDepth()
        );

        return $select;
    }

    /**
     * Requests unprocessed RFQ IDs and runs / stores searches for them
     *
     * @throws Shipserv_Match_Batch_Queue_FinishedException
     */
    public function processNextPage() {
        $this->currentPageNo++;

        if ($this->currentPageNo > (count($this->paginator))) {
            throw new Shipserv_Match_Batch_Queue_FinishedException("Finished processing " . ($this->currentPageNo - 1) . " pages", $this->rfqsProcessed);
        }

        $this->toTextLog("Preparing to process page " . $this->currentPageNo . " of " . $this->paginator->getDefaultItemCountPerPage() . " RFQs");

        $timeStart = microtime(true);
        $this->paginator->setCurrentPageNumber($this->currentPageNo);
        $rows = $this->paginator->getCurrentItems();

        if (empty($rows)) {
            throw new Shipserv_Match_Batch_Queue_FinishedException("Finished processing " . ($this->currentPageNo - 1) . " pages", $this->rfqsProcessed);
        }

        $this->toTextLog("Loaded " . count($rows) . " IDs in " . Myshipserv_View_Helper_String::secondsToString((microtime(true) - $timeStart)));

        $timeStart = microtime(true);
        foreach ($rows as $row) {
            $this->currentRfqId = (int) $row[Shipserv_Rfq::COL_ID];

            if ($this->matchRfq($this->currentRfqId)) {
                $this->rfqsProcessed++;
            };
        }

        $this->toTextLog("Finished processing page " . $this->currentPageNo . ", searches took " . Myshipserv_View_Helper_String::secondsToString((microtime(true) - $timeStart)));
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
            $search =  Shipserv_Match_Component_Search::getForRfq($rfqId, false, true);
            // search is alredy for one of the RFQs in the same event
            return false;
        } catch (Shipserv_Helper_Database_Exception $e) {
        }

        $oldTimeOut = ini_set('max_execution_time', 0); // starting a time-consuming process of searching for suppliers matching our RFQ

        try {
            $match = new Shipserv_Match_Match($rfqId);
        } catch (Exception $e) {
            $this->toTextLog("An error occurred while tokenising RFQ " . $rfqId . ": " . get_class($e) . " (" . $e->getMessage() . ")");
            throw $e;
        }

        try {
            $suppliers = $match->getMatchedSuppliers();
        } catch (Exception $e) {
            $this->toTextLog("An error occurred while matching RFQ " . $rfqId . ": " . get_class($e) . " (" . $e->getMessage() . ")");
            throw $e;
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
     * Returns IDs of buyer branches which RFQs needs to be pre-matched
     *
     * @return array
     */
    protected function getBuyerBranchIds() {
        $buyerOrgIds = Myshipserv_Config::getMatchBuyerIds();

        $buyerBranchIds = array();
        foreach ($buyerOrgIds as $id) {
            $buyer = Shipserv_Buyer::getInstanceById($id);
            $buyerBranchIds = array_merge($buyerBranchIds, $buyer->getBranchesTnid());
        }

        return $buyerBranchIds;
    }
}