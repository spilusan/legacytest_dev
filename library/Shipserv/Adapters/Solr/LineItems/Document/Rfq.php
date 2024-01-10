<?php
/**
 * Represents RFQ line item document from the line items Solr index
 *
 * @author  Yuriy Akopov
 * @date    2013-11-27
 * @story   S8855
 */
class Shipserv_Adapters_Solr_LineItems_Document_Rfq extends Shipserv_Adapters_Solr_LineItems_Document {
    /**
     * @return DateTime
     */
    public function getDate() {
        return $this->getQuoteDate();
    }

    /**
     * @return Shipserv_PurchaseOrder
     */
    public function getTransactionDocument() {
        return $this->getRfq();
    }

    /**
     * @return int
     */
    public function getTransactionDocumentId() {
        return $this->getRfqId();
    }

    /**
     * @return string
     */
    public function getTransactionDocumentRef() {
        return $this->getRfqRef();
    }

    /**
     * @return string
     */
    public function getTransactionDocumentSubject() {
        return $this->getRfqSubject();
    }

    /**
     * @return  Shipserv_Rfq
     *
     * @throws  Shipserv_Adapters_Solr_Exception
     */
    public function getRfq() {
        $rfqId = $this->getRfqId();
        if (is_null($rfqId)) {
            throw new Shipserv_Adapters_Solr_Exception("RFQ line item document " . $this->getDocumentId() . "isn't associated with an RFQ");
        }

        $rfq = Shipserv_Rfq::getInstanceById($rfqId);
        return $rfq;
    }
}