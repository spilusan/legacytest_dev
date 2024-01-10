<?php
/**
 * Exception to represent errors occurred on a new RFQ forwarded to suppliers from match
 *
 * @author  Yuriy Akopov
 * @date    2014-05-27
 * @story   S10313
 */
class Shipserv_Match_Forwarder_Exception extends Shipserv_Match_Exception {
    protected $rfqId = null;

    public function getRfqId() {
        return $this->rfqId;
    }

    public function __construct($message, $rfqId = null) {
        $this->rfqId = null;

        parent::__construct($message);
    }
}