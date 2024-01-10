<?php
/**
 * A wrapper around Solarium Solr client to be used to access line items Solr index
 *
 * @author  Yuriy Akopov
 * @date    2013-11-26
 * @story   S8855
 */

class Shipserv_Adapters_Solr_LineItems_Index extends Shipserv_Adapters_Solr_Index {
    // possible values of transaction type field determining to which transaction a line item belongs to
    const
        TRANSACTION_TYPE_RFQ    = 'rfq',
        TRANSACTION_TYPE_QUOTE  = 'quote',
        TRANSACTION_TYPE_ORDER  = 'order'
    ;

    // index fields - remember to keep this list up to date with the Line Items index schema!
    const
        FIELD_ID = 'id',

        FIELD_TRANSACTION_TYPE = 'transactionType',
        FIELD_LINE_ITEM_NUMBER = 'lineItemNumber',                      // not used
        FIELD_LINE_ITEM_MATCH  = 'matchTransaction',                    // not used

        FIELD_TXT_DESC              = 'lineItemTxtDesc',
        FIELD_TXT_ID_CODE           = 'lineItemTxtIdCode',
        FIELD_TXT_CFG_MANUFACTURER  = 'lineItemTxtCfgManufacturer',     // not used
        FIELD_TXT_CFG_MODEL_NO      = 'lineItemTxtCfgModelNo',          // not used
        FIELD_TXT_CFG_DESC          = 'lineItemTxtCfgDesc',             // not used
        FIELD_TXT_CFG_NAME          = 'lineItemTxtCfgName',             // not used
        FIELD_TXT_CFG_TYPE          = 'lineItemTxtCfgDeptType',         // not used

        FIELD_TXT_ALL = 'lineItemText',
        FIELD_TXT_DESC_LOWERCASE = 'lineItemTxtDescLowercase',
        FIELD_RFQ_ALL = 'rfqText',

        FIELD_UNIT              = 'lineItemUnit',
        FIELD_QUANTITY          = 'lineItemQuantity',
        FIELD_UNIT_COST         = 'lineItemUnitCost',
        FIELD_TOTAL_COST        = 'lineItemTotalCost',
        FIELD_CURRENCY          = 'lineItemCurrency',                   // not used
        FIELD_UNIT_COST_USD     = 'lineItemUnitCostUSD',
        FIELD_TOTAL_COST_USD    = 'lineItemTotalCostUSD',

        FIELD_RFQ_ID            = 'rfqInternalRefNo',
        FIELD_RFQ_EVENT_HASH    = 'rfqEventHash',
        FIELD_RFQ_REF           = 'rfqPublicRef',                       // not used
        FIELD_RFQ_SUBJECT       = 'rfqSubject',                         // not used
        FIELD_RFQ_DATE          = 'rfqDate',

        FIELD_RFQ_VESSEL_IMO        = 'rfqVesselImo',
        FIELD_RFQ_VESSEL_IMO_VALID  = 'rfqVesselImoValid',              // not used
        FIELD_RFQ_VESSEL_NAME       = 'rfqVesselName',
        FIELD_RFQ_VESSEL_NAME_CLEAN = 'rfqVesselNameClean',
        FIELD_RFQ_VESSEL_TYPE       = 'rfqVesselTypeCode',

        FIELD_RFQ_DELIVERY_PORT = 'rfqDeliveryPort',                    // not used
        FIELD_RFQ_COMMENTS      = 'rfqComments',                        // not used
        FIELD_RFQ_NOTES         = 'rfqNotes',                           // not used

        FIELD_QUOTE_ID      = 'quoteInternalRefNo',                     // not used
        FIELD_QUOTE_REF     = 'quotePublicRef',                         // not used
        FIELD_QUOTE_SUBJECT = 'quoteSubject',                           // not used
        FIELD_QUOTE_DATE    = 'quoteDate',
        FIELD_QUOTE_CURRENCY       = 'quoteCurrency',                   // not used
        FIELD_QUOTE_TOTAL_COST     = 'quoteTotalCost',                  // not used
        FIELD_QUOTE_TOTAL_COST_USD = 'quoteTotalCostUsd',               // not used

        FIELD_ORDER_ID      = 'orderInternalRefNo',
        FIELD_ORDER_REF     = 'orderPublicRef',
        FIELD_ORDER_SUBJECT = 'orderSubject',                           // not used
        FIELD_ORDER_DATE    = 'orderDate',
        FIELD_ORDER_CURRENCY       = 'orderCurrency',
        FIELD_ORDER_TOTAL_COST     = 'orderTotalCost',                  // not used
        FIELD_ORDER_TOTAL_COST_USD = 'orderTotalCostUsd',               // not used

        FIELD_ORDER_VESSEL_IMO        = 'orderVesselImo',
        FIELD_ORDER_VESSEL_IMO_VALID  = 'orderVesselImoValid',          // not used
        FIELD_ORDER_VESSEL_NAME       = 'orderVesselName',              // not used
        FIELD_ORDER_VESSEL_NAME_CLEAN = 'orderVesselNameClean',         // not used
        FIELD_ORDER_VESSEL_TYPE       = 'orderVesselTypeCode',

        FIELD_BUYER_BRANCH_ID        = 'buyerBranchId',
        FIELD_BUYER_BRANCH_NAME      = 'buyerBranchName',               // not used
        FIELD_BUYER_BRANCH_COUNTRY   = 'buyerBranchCountry',            // not used
        FIELD_BUYER_ORG_ID           = 'buyerOrgId',
        FIELD_BUYER_ORG_NAME         = 'buyerOrgName',                  // not used

        FIELD_SUPPLIER_BRANCH_ID        = 'supplierBranchId',           // not used
        FIELD_SUPPLIER_BRANCH_NAME      = 'supplierBranchName',         // not used
        FIELD_SUPPLIER_BRANCH_COUNTRY   = 'supplierBranchCountry',
        FIELD_SUPPLIER_ORG_ID           = 'supplierOrgId',              // not used
        FIELD_SUPPLIER_ORG_NAME         = 'supplierOrgName'             // not used
    ;

    /**
     * Initialises Solarium client with Line Items index credentials
     *
     * @param   int $timeoutAttempts
     */
    public function __construct($timeoutAttempts = 1) {
        $options = array(
            'timeout' => Myshipserv_Config::getSolrTimeoutLineItems()
        );

        parent::__construct(Myshipserv_Config::getSolrUrlLineItems(), $options, $timeoutAttempts);
    }
}
