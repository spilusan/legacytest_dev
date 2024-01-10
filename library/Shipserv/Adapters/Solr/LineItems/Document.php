<?php
/**
 * A document class for line items Solr index, to be extended by documents for line items of
 * different documents (RFQ, quote, order)
 *
 * @author  Yuriy Akopov
 * @date    2013-11-27
 * @story   S8855
 */
class Shipserv_Adapters_Solr_LineItems_Document extends Solarium_Document_ReadOnly
{
    /**
     * Factory method to cast the current line item document as an object of type specific to its nature
     *
     * @return  Shipserv_Adapters_Solr_LineItems_Document
     *
     * @throws  Shipserv_Adapters_Solr_Exception
     */
    public function getInstanceOfTransactionType()
    {
        $transactionType = $this->getTransactionType();

        switch ($transactionType) {
            case Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ:
                return new Shipserv_Adapters_Solr_LineItems_Document_Rfq($this->getFields());
                break;

            case Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_QUOTE:
                return new Shipserv_Adapters_Solr_LineItems_Document_Quote($this->getFields());
                break;

            case Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER:
                return new Shipserv_Adapters_Solr_LineItems_Document_Order($this->getFields());
                break;
        }

        throw new Shipserv_Adapters_Solr_Exception("Unsupported transaction type " . $transactionType);
    }

    /**
     * @return  Shipserv_Object
     * @throws  Shipserv_Adapters_Solr_Exception
     */
    public function getTransactionDocument()
    {
        throw new Shipserv_Adapters_Solr_Exception("Line item document of the proper class should be instantiated first");
    }

    /**
     * @return  int
     * @throws  Shipserv_Adapters_Solr_Exception
     */
    public function getTransactionDocumentId()
    {
        throw new Shipserv_Adapters_Solr_Exception("Line item document of the proper class should be instantiated first");
    }

    /**
     * @return  string
     * @throws  Shipserv_Adapters_Solr_Exception
     */
    public function getTransactionDocumentRef()
    {
        throw new Shipserv_Adapters_Solr_Exception("Line item document of the proper class should be instantiated first");
    }

    /**
     * @return  float
     */
    public function getScore()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_SCORE};
    }

    /**
     * @return string
     */
    public function getDocumentId()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_ID};
    }

    /**
     * @return string
     */
    public function getTransactionType()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_TRANSACTION_TYPE};
    }

    /**
     * @return int
     */
    public function getNumber()
    {
        return (int) $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_LINE_ITEM_NUMBER};
    }

    /**
     * @return bool
     */
    public function isMatchTransaction()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_LINE_ITEM_MATCH};
    }

    /**
     * @return string
     */
    public function getDesc()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_TXT_DESC};
    }

    /**
     * @return string
     */
    public function getIdCode()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_TXT_ID_CODE};
    }

    /**
     * @return string
     */
    public function getCfgManufacturer()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_TXT_CFG_MANUFACTURER};
    }

    /**
     * @return string
     */
    public function getCfgModelNo()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_TXT_CFG_MODEL_NO};
    }

    /**
     * @return string
     */
    public function getCfgDesc()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_TXT_CFG_DESC};
    }

    /**
     * @return string
     */
    public function getCfgName()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_TXT_CFG_NAME};
    }

    /**
     * @return string
     */
    public function getTxtCfgDeptType()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_TXT_CFG_TYPE};
    }

    /**
     * @return string
     */
    public function getUnit()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_UNIT};
    }

    /**
     * @return float
     */
    public function getQuantity()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_QUANTITY};
    }

    /**
     * @return float
     */
    public function getUnitCost()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_UNIT_COST};
    }

    /**
     * @return float
     */
    public function getTotalCost()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_TOTAL_COST};
    }

    /**
     * @return string
     */
    public function getCurrency()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_CURRENCY};
    }

    /**
     * @return float
     */
    public function getUnitCostUsd()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_UNIT_COST_USD};
    }

    /**
     * @return float
     */
    public function getTotalCostUsd()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_TOTAL_COST_USD};
    }

    /**
     * @return int
     */
    public function getRfqId()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_ID};
    }

    /**
     * @return string
     */
    public function getRfqRef()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_REF};
    }

    /**
     * @return string
     */
    public function getRfqSubject()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_SUBJECT};
    }

    /**
     * @return DateTime|null
     */
    public function getRfqDate()
    {
        return Shipserv_Adapters_Solr_Index::dateTimeFromSolr($this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_DATE});
    }

    /**
     * @return string
     */
    public function getRfqVesselName()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_VESSEL_NAME};
    }

    /**
     * @return string
     */
    public function getRfqVesselImo()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_VESSEL_IMO};
    }

    /**
     * @return int
     */
    public function getQuoteId()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_QUOTE_ID};
    }

    /**
     * @return string
     */
    public function getQuoteRef()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_QUOTE_REF};
    }

    /**
     * @return string
     */
    public function getQuoteSubject()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_QUOTE_SUBJECT};
    }

    /**
     * @return DateTime|null
     */
    public function getQuoteDate()
    {
        return Shipserv_Adapters_Solr_Index::dateTimeFromSolr($this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_QUOTE_DATE});
    }

    /**
     * @return int
     */
    public function getOrderId()
    {
        return (int) $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_ID};
    }

    /**
     * @return string
     */
    public function getOrderRef()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_REF};
    }

    /**
     * @return string
     */
    public function getOrderSubject()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_SUBJECT};
    }

    /**
     * @return DateTime|null
     */
    public function getOrderDate()
    {
        return Shipserv_Adapters_Solr_Index::dateTimeFromSolr($this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_DATE});
    }

    /**
     * @return int
     */
    public function getBuyerBranchId()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_BUYER_BRANCH_ID};
    }

    /**
     * @return string
     */
    public function getBuyerBranchName()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_BUYER_BRANCH_NAME};
    }

    /**
     * @return string
     */
    public function getBuyerBranchCountry()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_BUYER_BRANCH_COUNTRY};
    }

    /**
     * @return int
     */
    public function getBuyerOrgId()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_BUYER_ORG_ID};
    }

    /**
     * @return string
     */
    public function getBuyerOrgName()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_BUYER_ORG_NAME};
    }

    /**
     * @return array
     */
    public function getSupplierBranchId()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_SUPPLIER_BRANCH_ID};
    }

    /**
     * @return array
     */
    public function getSupplierBranchName()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_SUPPLIER_BRANCH_NAME};
    }

    /**
     * @return array
     */
    public function getSupplierBranchCountry()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_SUPPLIER_BRANCH_COUNTRY};
    }

    /**
     * @return array
     */
    public function getSupplierOrgId()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_SUPPLIER_ORG_ID};
    }

    /**
     * @return array
     */
    public function getSupplierOrgName()
    {
        return $this->{Shipserv_Adapters_Solr_LineItems_Index::FIELD_SUPPLIER_ORG_NAME};
    }
}