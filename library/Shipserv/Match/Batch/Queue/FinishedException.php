<?php
/**
 * Exception thrown when there is nothing (left) to process for match script
 *
 * @author  Yuriy Akopov
 * @date    2014-05-22
 * @story   10315
 */
class Shipserv_Match_Batch_Queue_FinishedException extends Shipserv_Match_Batch_Exception {
    /**
     * @var int
     */
    protected $rfqsProcessed = null;

    /**
     * @return int
     */
    public function getRfqsProcessed() {
        return $this->rfqsProcessed;
    }

    /**
     * @param string $message
     * @param int $rfqsProcessed
     */
    public function __construct($message, $rfqsProcessed) {
        $this->rfqsProcessed = $rfqsProcessed;

        parent::__construct($message);
    }
}