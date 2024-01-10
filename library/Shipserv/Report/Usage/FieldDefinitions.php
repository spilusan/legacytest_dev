<?php
/**
* Create a field definition for main report and drolldows to create camelCase names from oracle fields
* This will also be a compatibility class later, if we will replace the oracle strorage to store events, Only the database object has to be replaced returning the same array format
*/
class Shipserv_Report_Usage_FieldDefinitions
{

    private static $_instance;
    
    protected $rootReportFields;
    protected $verificationFields;
    protected $activeTradingAccountsFields;
    protected $nonVerificationFields;
    protected $signInsFields;
    protected $failedSignInsFields;
    protected $searchEventsFields;
    protected $spbImpressionFields;
    protected $pagesRfqsFields;
    protected $rfqOrdWoImoFields;

    /**
    * Create a single instance of the class
    * @return object the class instane
    */
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
            static::$_instance->_setReportFields();
            static::$_instance->_setVerificationFields();
            static::$_instance->_setActiveTradingAccountsFields();
            static::$_instance->_setNonVerificationFields();
            static::$_instance->_setSignInsFields();
            static::$_instance->_setFailedSignInsFields();
            static::$_instance->_setSearchEventFields();
            static::$_instance->_setSpbImpressionFields();
            static::$_instance->_setPagesRfqsFields();
            static::$_instance->_setRfqOrdWoImoFields();

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
		    	'id' =>'BYB_BYO_ORG_CODE',
				'name' =>'BYO_NAME',
				'dateCompanyCreated' =>'BYO_CREATED_DATE',
				'activeTradingAccounts' =>'ACTIVE_ACCOUNTS',
				'companyAnonymityLevel' =>'ANONIMITY_LEVEL',
				'verifiedUserAccounts' =>'VERIFIED_USER_ACCOUNTS',
				'nonVerifiedActiveUserAccounts' =>'NON_VERIFIED_USER_ACCOUNTS',
				'pendingJoinRequests' =>'PENDING_JOIN_REQUESTS',
				'successfulSignIns' =>'SUCCESSFULL_SIGN_INS',
				'reviewRequestsReceived' =>'REVIEW_REQUESTS',
				'reviewsSubmitted' =>'REVIEW_REQUESTS_SUBMITTED',
				'approvedSuppliers' =>'APPROVED_SUPPLIERS',
				'branchesWithRfqAutomaticRemindersActivated' =>'RFQ_REMINDER',
				'branchesWithPoAutomaticRemindersActivated' =>'ORD_REMINDER',
				'branchesWithSpendBenchmarkingActivated' =>'SPEND_BENCHMARK_COUNT',
				'buyTabEvents' =>'BUY_TAB_CLICKED',
				'spendBenchmarkingReportEvents' =>'SPEND_BENCHMARKING_EVENTS',
				'matchDashboardEvents' =>'MATCH_DASHBOARD_LAUNCH',
				'matchReportEvents' =>'MATCH_REPORT_LAUNCH',
				'transactionMonitorEvents' =>'TXNMON_LAUNCH',
                'searchEvents' =>'SEARCH_EVENTS',
                'impaPriceBenchmarkingEvents' =>'IMPA_PRICE_BENCHMARK_SERVED',
                'impaSpendTrackerEvents' =>'IMPA_SPEND_TRACKER_SERVED',
                'totalSpendReportEvents' =>'TOTAL_SPEND_SERVED',
                'supplierPageImpressions' => 'SUPPLIER_SEARCH_IMPRESSION',
                'webReporterEvents' => 'WEBREPORTER_LAUNCH',
                'failedSignIns' => 'USER_FAILED_LOGIN',
                'contactRequests' => 'CONTACT_REQUESTS',
                'daysWithEvents' => 'DAYS_WITH_EVENTS',
                'pagesRFQsSent' => 'PAGES_RFQ_CNT',
    			'rfqOrdWoImoCount' => 'RFQ_ORD_WO_IMO_COUNT',
    			'sprReportViews' => 'SPR_REPORT_VIEWS'
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
    * Create the Verificated buyers field and camel case name array
    * @return array field -> Camel cased name list
    */
    protected function _setVerificationFields()
    {
        $this->verificationFields = array(
            'email' => 'PSU_EMAIL',
            'firstName' => 'PSU_FIRSTNAME',
            'lastName' => 'PSU_LASTNAME',
            'creationDate' => 'PSU_CREATION_DATE',
            'level' => 'PUC_LEVEL',
            'status' => 'PUC_STATUS',
            'anonimity' => 'PSU_ANONYMITY_FLAG'
            );
    }
    
    /**
     * Create the RfqOrdWoImo buyers field and camel case name array
     * @return array field -> Camel cased name list
     */
    protected function _setRfqOrdWoImoFields()
    {
    	$this->rfqOrdWoImoFields = array(
    			'id' => 'ID',
    			'vesselName' => 'VESSEL_NAME',
    			'subject' => 'SUBJECT',
    			'printable' => 'PRINTABLE',
    			'internalRefNo' => 'INTERNAL_REF_NO'
    	);
    }

    /**
     * Create the Active Trading account fields, and camel case name array
     * @return array field -> Camel cased name list
     */
    protected function _setActiveTradingAccountsFields()
    {
    	$this->activeTradingAccountsFields = array(
    			'bybBranchCode' => 'BYB_BRANCH_CODE',
    			'bybName' => 'BYB_NAME'
    	);
    }    

    /**
    * Retrive a camel cased name by a database field name for verified Branch list 
    * @param string $paramName The name of the field
    * @return string the camelCasedName
    */
    public function getVerificationFieldName($paramName)
    {
        return (array_key_exists($paramName, $this->verificationFields)) ? $this->verificationFields[$paramName] : false;
    }

    /**
    * Getter for Verified brances report field name, camel case name pairs
    * @return array List of field names and camel case field names
    */
    public function getVerificationFields()
    {
        return $this->verificationFields;
    }
    
    /**
     * Getter for RfqOrdWoImo brances report field name, camel case name pairs
     * @return array List of field names and camel case field names
     */
    public function getRfqOrdWoImoFields()
    {
    	return $this->rfqOrdWoImoFields;
    }
    
    /**
     * Getter for Active Trading Acocunts fields, camel case name pairs
     * @return array List of field names and camel case field names
     */
    public function getActiveTradingAccountsFields()
    {
    	return $this->activeTradingAccountsFields;
    }
    

    /**
    * Create the Non Verificated buyers field and camel case name array
    * @return array field -> Camel cased name list
    */
    protected function _setNonVerificationFields()
    {
        $this->nonVerificationFields = array(
            'email' => 'PSU_EMAIL',
            'firstName' => 'PSU_FIRSTNAME',
            'lastName' => 'PSU_LASTNAME',
            'creationDate' => 'PSU_CREATION_DATE',
            'level' => 'PUC_LEVEL',
            'status' => 'PUC_STATUS'
            );
    }

    /**
    * Retrive a camel cased name by a database field name for Non verified Branch list 
    * @param string $paramName The name of the field
    * @return string the camelCasedName
    */
    public function getNonVerificationFieldName($paramName)
    {
        return (array_key_exists($paramName, $this->nonVerificationFields)) ? $this->nonVerificationFields[$paramName] : false;
    }

    /**
    * Getter for Non Verified brances report field name, camel case name pairs
    * @return array List of field names and camel case field names
    */
    public function getNonVerificationFields()
    {
        return $this->nonVerificationFields;
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
    * Create the failed Sign Ins field and camel case name array
    * @return array field -> Camel cased name list
    */
    protected function _setFailedSignInsFields()
    {
        $this->failedSignInsFields = array(
            'dateTime' => 'PUA_DATE_CREATED',
            'userName' => 'USR_NAME',
            );
    }

    /**
    * Create the Search Events field and camel case name array
    * @return array field -> Camel cased name list
    */
    protected function _setSearchEventFields()
    {
        $this->searchEventsFields = array(
            'dateTime' => 'PST_SEARCH_DATE',
            'userName' => 'USR_NAME',
            'searchText' => 'PST_SEARCH_TEXT',
            'location' => 'PST_COUNTRY_CODE'
            );
    }

    /**
    * Create the Search impression field and camel case name array
    * @return array field -> Camel cased name list
    */
    protected function _setSpbImpressionFields()
    {
        $this->spbImpressionFields = array(
            'dateTime' => 'PSS_VIEW_DATE',
            'userName' => 'USR_NAME',
            'spbBranchCode' => 'SPB_BRANCH_CODE',
            'spbName' => 'SPB_NAME'
            );
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

    /**
    * Getter for Sign Ins report field name, camel case name pairs
    * @return array List of field names and camel case field names
    */
    public function getSignInsFields()
    {
        return $this->signInsFields;
    }

    /**
    * Getter for failed Sign Ins report field name, camel case name pairs
    * @return array List of field names and camel case field names
    */
    public function getFailedSignInsFields()
    {
        return $this->failedSignInsFields;
    }
    /**
    * Rerurn the search event field and name associations 
    * @return array
    */
    public function getSearchEventFields()
    {
        return $this->searchEventsFields;
    }

    /**
    * Rerurn the fields for search impressions
    * @return array
    */
    public function getSpbImpressionFields()
    {
        return $this->spbImpressionFields;
    }

    /**
    * Create the Pages RFQ fields and camel case name array
    * @return array field -> Camel cased name list
    */
    protected function _setPagesRfqsFields()
    {
        $this->pagesRfqsFields = array(
                'rfqInternalRefNo' => 'RFQ_INTERNAL_REF_NO',
                'dateTime' => 'RFQ_SUBMITTED_DATE',
                'usrName' => 'USR_NAME',
                'spbBranchCode' => 'SPB_BRANCH_CODE',
                'spbName' => 'SPB_NAME'
                );
    }

    /** Getter for pages RFQs fields
    * @return array List of field names and camel case field names
    */
    public function getPagesRfqsFields()
    {
        return $this->pagesRfqsFields;
    }

}
