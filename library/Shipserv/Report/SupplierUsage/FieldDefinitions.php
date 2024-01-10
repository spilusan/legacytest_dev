<?php
/**
* Create a field definition for main report and drolldows to create camelCase names from oracle fields
* This will also be a compatibility class later, if we will replace the oracle strorage to store events, Only the database object has to be replaced returning the same array format
*/
class Shipserv_Report_SupplierUsage_FieldDefinitions
{

    private static $_instance;
    
    protected $rootReportFields;
    protected $signInsFields;

    /**
    * Create a single instance of the class
    * @return object the class instane
    */
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
            static::$_instance->_setReportFields();
            static::$_instance->_setSignInsFields();
        }
        
        return static::$_instance;
    }

    /**
    * Protected classes to prevent creating a new instance 
    */
    protected function __construct()
    {
    
    }
    
    /**
    * Protected classes to clone new instane
    * @return unknown
    */
    private function __clone()
    {

    }

    /**
    * Create the report field and camel case name array
    * @return array field -> Camel cased name list
    */
    protected function _setReportFields()
    {
    	$this->rootReportFields = array(
            'id' => 'PUC_COMPANY_ID',
            'name' => 'SPB_NAME',
            'city' => 'SPB_CITY',
            'country' => 'SPB_COUNTRY',
            'gmv' => 'GMV',
            'membershipLevel' => 'MEMBERSHIP_LEVEL',
            'listingLevel' => 'DIRECTORY_LISTING_LEVEL',
            'dateCompanyCreated' => 'SPB_CREATED_DATE',
            'profileCompleteScore' => 'SPB_PCS_SCORE',
            'spotlightListing' => 'SPOTLIGHT_LISTING',
            'bannerAdvert' => 'BANNER_ADV',
            'catalogueUploaded' => 'CATALOGUE_UPLOADED',
            'ssoListing' => 'SSO_LISTING',
            'brandOwner' => 'BRAND_OWNER',
            'adNetwork' => 'AD_NETWORK',
            'showCustomersPrivacySetting' => 'ANONIMITY',
            'verifiedUserAccounts' => 'VERIFIED_USER_ACCOUNTS',
            'nonVerifiedActiveUserAccounts' => 'NON_VERIFIED_USER_ACCOUNTS',
            'pendingJoinRequests' => 'PENDING_JOIN_REQUESTS',
            'successfulSignIns' => 'SUCCESSFULL_SIGN_INS',
            'failedSignIns' => 'USER_FAILED_LOGIN',
            'searchEvents' => 'SEARCH_EVENTS',
            'supplierPageImpressions' => 'SUPPLIER_SEARCH_IMPRESSION',
            'contactRequests' => 'CONTACT_REQUESTS',
            'pagesRfqsSent' => 'PAGES_RFQ_CNT',
            'activePromotion' => 'ACTIVE_PROMOTION',
            'activePromotionReportViews' => 'AP_REPORT_VIEWS',
            'buyersOnApPendingList' => 'AP_PENDING_COUNT',
            'buyersOnApPromoteList' => 'AP_TARGETED_COUNT',
            'buyersOnApExcludeList' => 'AP_EXCLUDED_COUNT',
            'sir3ReportViews' => 'SIR3_REPORT_VIEWS',
            'engLevel' => 'DAYS_WITH_EVENTS'
    		);
    }

    /**
    * Returns the fiels name according to the translated field value 
    * @param  string $paramName Then name of the paramater 
    * @return string (or false if not found)
    */
    public function getRootReportFieldName($paramName)
    {
    	return (array_key_exists($paramName, $this->rootReportFields)) ? $this->rootReportFields[$paramName] : false;
    }

    /**
    * Getter for root report field name, camel case name pairs
    * @return array List of field names and camel case field names
    */
    public function getReportFields()
    {
    	return $this->rootReportFields;
    }
    
    /**
     * Create the Sign Ins field and camel case name array
     * @return array field -> Camel cased name list
     */
    protected function _setSignInsFields()
    {
    	$this->signInsFields = array(
    			'dateTime' => 'PUA_DATE_CREATED',
    			'userName' => 'USR_NAME',
    	);
    }
    
    /**
     * Getter for Sign Ins report field name, camel case name pairs
     * @return array List of field names and camel case field names
     */
    public function getSignInsFields()
    {
    	return $this->signInsFields;
    }
    
    /**
     * Retrive a camel cased name by a database field name for Sign Ins
     * @param string $paramName The name of the field
     * @return string the camelCasedName
     */
    public function getSignInsFieldName($paramName)
    {
    	return (array_key_exists($paramName, $this->nonVerificationFields)) ? $this->nonVerificationFields[$paramName] : false;
    }
 

}
