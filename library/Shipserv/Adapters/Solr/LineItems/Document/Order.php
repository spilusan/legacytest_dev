<?php
/**
 * Represents order line item document from the line items Solr index
 *
 * @author  Yuriy Akopov
 * @date    2013-11-27
 * @story   S8855
 */
class Shipserv_Adapters_Solr_LineItems_Document_Order extends Shipserv_Adapters_Solr_LineItems_Document {
    /**
     * @return DateTime
     */
    public function getDate() {
        return $this->getOrderDate();
    }

    /**
     * @return Shipserv_PurchaseOrder
     */
    public function getTransactionDocument() {
        return $this->getOrder();
    }

    /**
     * @return int
     */
    public function getTransactionDocumentId() {
        return $this->getOrderId();
    }

    /**
     * @return string
     */
    public function getTransactionDocumentRef() {
        return $this->getOrderRef();
    }

    /**
     * @return string
     */
    public function getTransactionDocumentSubject() {
        return $this->getOrderSubject();
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
     * @return  Shipserv_Quote|null
     */
    public function getQuote() {
        $quoteId = $this->getQuoteId();
        if (is_null($quoteId)) {
            return null;
        }

        $quote = Shipserv_Quote::getInstanceById($quoteId);
        return $quote;
    }

    /**
     * @return  Shipserv_PurchaseOrder
     *
     * @throws  Shipserv_Adapters_Solr_Exception
     */
    public function getOrder() {
        $orderId = $this->getOrderId();
        if (is_null($orderId)) {
            throw new Shipserv_Adapters_Solr_Exception("Order line item document " . $this->getDocumentId() . "isn't associated with an order");
        }

        $order = Shipserv_PurchaseOrder::getInstanceById($orderId);
        return $order;
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