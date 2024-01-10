<?php
/**
 * A queue to process RFQs which IDs were explicitly supplied by user
 *
 * @author  Yuriy Akopov
 * @date    2014-06-16
 * @story   S10315
 */
class Shipserv_Match_Batch_Queue_UserRfqs extends Shipserv_Match_Batch_Queue_Abstract {
    const
        PAGE_SIZE = 100
    ;

    /**
     * @param   int|array   $rfqIds
     * @param   string      $logFolder
     * @param   bool        $debugMode
     *
     * @throws Shipserv_Match_Batch_Exception
     */
    public function __construct($rfqIds, $logFolder = null, $debugMode = false) {
        parent::__construct($logFolder, $debugMode);

        if (!is_array($rfqIds)) {
            $rfqIds = array($rfqIds);
        }

        $this->paginator = Zend_Paginator::factory($rfqIds);
        $this->paginator->setItemCountPerPage(self::PAGE_SIZE);
    }

    /**
     * No need to convert array paginator item - it's the value we need directly
     *
     * @param   int   $item
     * @return  int
     */
    protected function pageItemToRfqId($item) {
        return (int) $item;
    }
}