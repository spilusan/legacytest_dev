<?php
/**
 * A wrapper around Solarium Solr client to be used to access legacy suppliers index
 *
 * @author  Yuriy Akopov
 * @date    2014-02-11
 * @story   S8493
 */

class Shipserv_Adapters_Solr_Suppliers_Index extends Shipserv_Adapters_Solr_Index {
    const
        FIELD_ID            = 'id',
        FIELD_NAME          = 'name',
        FIELD_ADDRESS       = 'address',
        FIELD_TRADE_RANK    = 'tradeRank',
        FIELD_LIST_LEVEL    = 'listLevel',
        FIELD_ESERVICE      = 'eService',
        FIELD_DESCRIPTION   = 'description',
        FIELD_ORDERS        = 'orders',
        FIELD_CATALOG       = 'catalog',
    
        FIELD_CATEGORY_PRIMARY      = 'primCat',
        FIELD_CATEGORY_SECONDARY    = 'secCat',
        
        FIELD_BRANDS            = 'brands',
        FIELD_BRANDS_OEM        = 'OEMBrands',
        FIELD_BRANDS_ALL        = 'AllBrands',
        FIELD_BRAND_OEM_ID      = 'OEMBrandId',
        FIELD_BRAND_ID          = 'brandId',
        FIELD_BRAND_MODEL       = 'brandModel',
        FIELD_BRAND_ID_AUTH_VER = 'brandIdAuthVer',
        
        FIELD_AUTHORISED_AGENT      = 'AABrands',
        FIELD_AUTHORISED_AGENT_ID   = 'AABrandId',
        
        FIELD_AUTHORISED_INSTALLER_ID   = 'AIRBrandId',
        FIELD_AUTHORISED_INSTALLER      = 'AIRBrands',
    
        FIELD_HAS_CATALOGUE         = 'hasCatalogue',
        FIELD_SPECIALISATION_INDEX  = 'specializationIndex',
    
        FIELD_PORTS_PRESENT     = 'portsPresent',
        FIELD_PORTS_SERVED      = 'portsServed',
        FIELD_COUNTRY           = 'country',
        FIELD_DATE_CREATED      = 'created_date',
        FIELD_CATEGORY_ID       = 'categoryId',
        FIELD_MEMBERSHIP_ID     = 'membershipId',
        FIELD_CERTIFICATION_ID  = 'certificationId',
        FIELD_ATTACHMENT        = 'attachment',
        FIELD_HOME_PORTS        = 'homePorts',

        FIELD_MONETISATION_PERCENT  = 'monPercent',
        FIELD_MONETISATION_WEIGHT   = 'monWeight'
    ;

    /**
     * Initialises Solarium client with Line Items index credentials
     *
     * @param   bool    $useCustomAdapter
     */
    public function __construct() {
        $options = array(
            'timeout' => Myshipserv_Config::getSolrTimeoutSuppliers()
        );

        parent::__construct(Myshipserv_Config::getSolrUrlSuppliers(), $options);
    }
}
