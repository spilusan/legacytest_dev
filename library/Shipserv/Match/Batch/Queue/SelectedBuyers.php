<?php
/**
 * A queue to process RFQs from selected buyers only to reduce pressure on system resources
 *
 * @author  Yuriy Akopov
 * @date    2014-06-16
 * @story   S10315
 */
class Shipserv_Match_Batch_Queue_SelectedBuyers extends Shipserv_Match_Batch_Queue_Abstract {
    const
        PAGE_SIZE = 100    // how many RFQ rows to request at once
    ;

    /**
     * @var DateTime|null
     */
    protected $timeStarted = null;

    /**
     * @param   int         $dayDepth
     * @param   string      $logFolder
     * @param   bool        $debugMode
     *
     * @throws Shipserv_Match_Batch_Exception
     */
    public function __construct($dayDepth, $logFolder = null, $debugMode = false) {
        parent::__construct($logFolder, $debugMode);

        $this->timeStarted = new DateTime();

        $select = $this->getPageSelect($dayDepth);

        $this->paginator = Zend_Paginator::factory($select);
        $this->paginator->setItemCountPerPage(self::PAGE_SIZE);
    }

    /**
     * Returns a query to grab a given number of RFQs to process
     *
     * @param   int $dayDepth
     *
     * @return  Zend_Db_Select
     * @throws  Exception
     */
    public function getPageSelect($dayDepth) {
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
            throw new Shipserv_Match_Batch_Exception("No valid buyer organisations found, aborting batch processing");
        }

        $select = Shipserv_Rfq_EventManager::getBuyerRfqsNoFwdSelect($buyerOrgs, array(
            Shipserv_Rfq::COL_EVENT_HASH => new Zend_Db_Expr('RAWTOHEX(' . Shipserv_Rfq::COL_EVENT_HASH . ')'),
            Shipserv_Rfq::COL_DATE       => Shipserv_Rfq::COL_DATE
        ), null, $dayDepth);

        // add a date limitation so the script doesn't run forever if new RFQs keeps arriving while it's processing them
        $selectWrap = new Zend_Db_Select($select->getAdapter());
        $selectWrap
            ->from(
                array('rfq' => $select),
                '*'
            )
            ->where('rfq.' . Shipserv_Rfq::COL_DATE . ' <= ' . Shipserv_Helper_Database::getOracleDateExpr($this->timeStarted))
        ;

        $this->toTextLog("The query to be used in this session is " . $selectWrap->assemble());

        return $selectWrap;
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

    /**
     * Extracts the column from db row returned by paginator
     *
     * @param   array   $item
     * @return  int
     */
    protected function pageItemToRfqId($item) {
        $timeStart = microtime(true);
        $rfqId = Shipserv_Rfq_EventManager::getRfqsForEvent($item[Shipserv_Rfq::COL_EVENT_HASH], true, true);
        $elapsed = microtime(true) - $timeStart;

        if ($elapsed > $this->longestQueryTime) {
            $this->longestQueryTime = $elapsed;
        }

        return (int) $rfqId;
    }
}