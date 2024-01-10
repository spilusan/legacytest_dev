<?php
/**
 * Represents quote line item document from the line items Solr index
 *
 * @author  Yuriy Akopov
 * @date    2013-11-27
 * @story   S8855
 */
class Shipserv_Adapters_Solr_LineItems_Document_Quote extends Shipserv_Adapters_Solr_LineItems_Document {
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
        return $this->getQuote();
    }

    /**
     * @return int
     */
    public function getTransactionDocumentId() {
        return $this->getQuoteId();
    }

    /**
     * @return string
     */
    public function getTransactionDocumentRef() {
        return $this->getQuoteRef();
    }

    /**
     * @return string
     */
    public function getTransactionDocumentSubject() {
        return $this->getQuoteSubject();
    }

    /**
     * @return null|Shipserv_Rfq
     */
    public function getRfq() {
        $rfqId = $this->getRfqId();
        if (is_null($rfqId)) {
            return null;
        }

        $rfq = Shipserv_Rfq::getInstanceById($rfqId);
        return $rfq;
    }

    /**
     * @return  Shipserv_Quote
     *
     * @throws  Shipserv_Adapters_Solr_Exception
     */
    public function getQuote() {
        $quoteId = $this->getQuoteId();
        if (is_null($quoteId)) {
            throw new Shipserv_Adapters_Solr_Exception("Quote line item document " . $this->getDocumentId() . "isn't associated with a quote");
        }

        $quote = Shipserv_Quote::getInstanceById($quoteId);
        return $quote;
    }

    /**
     * @return int
     */
    public function getSupplierBranchId() {
        $values = parent::getSupplierBranchId();
        if (!is_array($values)) {
            return $values;
        }

        return $values[0];
    }

    /**
     * @return string
     */
    public function getSupplierBranchName() {
        $values = parent::getSupplierBranchName();
        if (!is_array($values)) {
            return $values;
        }

        return $values[0];
    }

    /**
     * @return string
     */
    public function getSupplierBranchCountry() {
        $values = parent::getSupplierBranchCountry();
        if (!is_array($values)) {
            return $values;
        }

        return $values[0];
    }

    /**
     * @return int
     */
    public function getSupplierOrgId() {
        $values = parent::getSupplierOrgId();
        if (!is_array($values)) {
            return $values;
        }

        return $values[0];
    }

    /**
     * @return string
     */
    public function getSupplierOrgName() {
        $values = parent::getSupplierOrgName();
        if (!is_array($values)) {
            return $values;
        }

        return $values[0];
    }
}