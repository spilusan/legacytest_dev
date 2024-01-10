<?php
class Shipserv_User_Activity extends Shipserv_Object {
	const USER_MANAGEMENT_ADD = 'USER_MANAGEMENT_ADD';
	const USER_MANAGEMENT_VIEW = 'USER_MANAGEMENT_VIEW';

	const ENQUIRY_BROWSER_VIEW = 'ENQUIRY_BROWSER_VIEW';
	const ENQUIRY_SENDER_MARKED_AS_SPAMMER = 'ENQUIRY_SENDER_MARKED_AS_SPAMMER';
	const ENQUIRY_SENT = 'ENQUIRY_SENT';
	const ENQUIRY_BROWSER_EXPORT_TO_EXCEL = 'ENQUIRY_BROWSER_EXPORT_TO_EXCEL';

	const SIR_VIEW = 'SIR_VIEW';
	const SIR_FORWARD_TO_CUSTOMER = 'SIR_FORWARD_TO_CUSTOMER';
	const SIR_EXPORT_TO_EXCEL = 'SIR_EXPORT_TO_EXCEL';
	const SIR_VIEW_DETAILED= 'SIR_VIEW_DETAILED';

	const USER_LOGIN = 'USER_LOGIN';
	const USER_FAILED_LOGIN = 'USER_FAILED_LOGIN';
	const USER_REQUEST_PASSWORD = 'USER_REQUEST_PASSWORD';
	const USER_DOWNLOAD_MA = 'USER_DOWNLOAD_MA';
	const USER_BLOCKING_BUYER = 'USER_BLOCKING_BUYER';

	const SUPPLIER_UPDATE_LISTING = 'SUPPLIER_UPDATE_LISTING';

    // added by Yuriy Akopov on 2013-09-10, S8093
    const TERM_AND_CONDITIONS_CONFIRMED = 'TERMS_AND_CONDITIONS_CONFIRMED';

    // added by Elvir on 2014-04-24, S9829
    const DETAILED_INFORMATION_COMPLETED = 'DETAILED_INFORMATION_COMPLETED';

    //Added by Attila O 2015-11-16
    const BUY_TAB_CLICKED = 'BUYER_TAB_CLICKED';
    const BUY_TAB_RFQ_LIST = 'BUY_TAB_RFQ_LIST'; //TODO continue adding

    const RFQ_ON_BUY_TAB_EXPAND = 'RFQ_ON_BUY_TAB_EXPAND';
    const MATCH_INT_ON_BUY_TAB_SHOW = 'MATCH_INT_ON_BUY_TAB_SHOW';
    const MATCH_INT_ON_BUY_TAB_EDIT = 'MATCH_INT_ON_BUY_TAB_EDIT';
    const RFQ_SENT_FROM_BUY_TAB = 'RFQ_SENT_FROM_BUY_TAB';
    const SPEND_BENCHMARK_LAUNCH = 'SPEND_BENCHMARK_LAUNCH';
	const SPEND_BENCHMARK_VIEW_QUOTES = 'SPEND_BENCHMARK_VIEW_QUOTES';
	const SPEND_BENCHMARK_FULL_DETAILS = 'SPEND_BENCHMARK_FULL_DETAILS';
	/*
	TODO
		Supplier Blacklisted
		RFQ comparability changed
	*/
	const MATCH_DASHBOARD_LAUNCH = 'MATCH_DASHBOARD_LAUNCH';
	const MATCH_REPORT_LAUNCH = 'MATCH_REPORT_LAUNCH';
	const TRANSACTION_MONITOR_LAUNCH = 'TRANSACTION_MONITOR_LAUNCH';
	const MY_INFO_CLICK = 'MY_INFO_CLICK';
	const MY_COMPANIES_CLICK = 'MY_COMPANIES_CLICK';
	const REVIEW_REQUESTS_CLICK = 'REVIEW_REQUESTS_CLICK';
	const REVIEW_CREATED = 'REVIEW_CREATED';
	const USER_SETTINGS_CLICK = 'USER_SETTINGS_CLICK';
	const APPROVED_SUPPLIERS_CLICK = 'APPROVED_SUPPLIERS_CLICK';

	//Automatic Reminder
	const AUTOMATIC_REMINDERS_CLICK = 'AUTOMATIC_REMINDERS_CLICK';
	const PO_ARM_ACTIVATED = 'PO_ARM_ACTIVATED';
	const PO_ARM_DEACTIVATED = 'PO_ARM_DEACTIVATED';

	const SELL_TAB_CLICKED = 'SELL_TAB_CLICKED';
	const GMV_SUPPLIER_CLICK = 'GMV_SUPPLIER_CLICK';
	const GMV_SUPPLIER_STATISTICS_CLICK = 'GMV_SUPPLIER_STATISTICS_CLICK';
	const GMV_SUPPLIER_CONVERSION = 'GMV_SUPPLIER_CONVERSION';
	const GMV_BUYER = 'GMV_BUYER';
	const SUPPLIER_COMPANY_USER_SIGN_IN = 'SUPPLIER_COMPANY_USER_SIGN_IN';
	const BUYER_COMPANY_USER_SIGN_IN = 'BUYER_COMPANY_USER_SIGN_IN';
	const SEARCHES_BUYER_COMPANY = 'SEARCHES_BUYER_COMPANY';
	const SEARCHES_SUPPLIER_COMPANY = 'SEARCHES_SUPPLIER_COMPANY';
	
	//Webreporter events
	const WEBREPORTER_LAUNCH = 'WEBREPORTER_LAUNCH';
	const WEBREPORTER_ALL_POS = 'WEBREPORTER_ALL_POS';
	const WEBREPORTER_ALL_RFQS = 'WEBREPORTER_ALL_RFQS';
	const WEBREPORTER_POS_BY_SUPPLIER = 'WEBREPORTER_POS_BY_SUPPLIER';
	const WEBREPORTER_POS_BY_VESSEL = 'WEBREPORTER_POS_BY_VESSEL';
	const WEBREPORTER_SUPPLIER_ANALYSIS = 'WEBREPORTER_SUPPLIER_ANALYSIS';
	const WEBREPORTER_TRANSACTIONS_BY_SUPPLIER = 'WEBREPORTER_TRANS_BY_SUPPLIER';
	const WEBREPORTER_TRANSACTIONS_BY_VESSEL = 'WEBREPORTER_TRANS_BY_VESSEL';
	const IMPA_PRICE_BENCHMARK_REPORT_SERVED = 'IMPA_PRICE_BENCHMARK_SERVED';
	const IMPA_SPEND_TRACKER_REPORT_SERVED = 'IMPA_SPEND_TRACKER_SERVED';
	const TOTAL_SPEND_REPORT_SERVED = 'TOTAL_SPEND_SERVED';

	//Active Promotion
	const ACTIVE_PROMOTION_REPORT_VIEWS = 'AP_REPORT_VIEWS';

	//Contact Requests
	const CONTACT_REQUESTS = 'CONTACT_REQUESTS';
	const CONTACT_REQUESTS_SHOW_TNID = 'CR_SHOW_TNID';
	const CONTACT_REQUESTS_SHOW_DETAILS = 'CR_SHOW_DETAILS';

	//Automatic Compliance Monitoring 
	const ACM_ACTIVATED = 'ACM_ACTIVATED';
	const ACM_DEACTIVATED = 'ACM_DEACTIVATED';

	//Spend Benchmarking Activateted, deactivated (Alias Match settings, and Spend Management settings)
	const SPEND_BENCHMARK_ACTIVATED = 'SPEND_BENCHMARK_ACT';
	const SPEND_BENCHMARK_DEACTIVATED = 'SPEND_BENCHMARK_DEACT';
	
	//SPR report
	const SPR_REPORT_VIEWS = 'SPR_REPORT_VIEWS';

	const
        TABLE_NAME = 'pages_user_activity',
        COL_ID              = 'PUA_ID',
        COL_USER_ID         = 'PUA_PSU_ID',
        COL_ACTIVITY        = 'PUA_ACTIVITY',
        COL_OBJ_NAME        = 'PUA_OBJECT_NAME',
        COL_OBJ_ID          = 'PUA_OBJECT_ID',
        COL_SHIPSERV        = 'PUA_IS_SHIPSERV',
        COL_INFO            = 'PUA_INFO',
        COL_DATE_CREATED    = 'PUA_DATE_CREATED',
        COL_DATE_UPDATED    = 'PUA_DATE_UPDATED'
    ;

	const ORACLE_DATE_FORMAT = 'dd Mon';
	const MEMCACHE_TTL = 3600;

	private $userId;
	private $user;
	private $period;
	private $translationArray;
	private $_eventGroups;
	private $_eventGroupNames;

	public $searches;


	public function translate($const)
	{
		return  array_key_exists($const, $this->translationArray) ? $this->translationArray[$const] : false;
	}

	public function __construct( $userId = null, $period = null )
	{
		$this->setTranslationArray();
		$this->setEventGroups();
		$this->setEventGroupCategoryNames();

		if( $userId !== null )
		{
			$this->userId 	= $userId;
			$this->user 	= Shipserv_User::getInstanceById($userId, 'P', true);
			$this->period 	= $period->toArray();

			$this->searches 	= $this->getLastSearch();
			$this->SIR 			= $this->getViewedSIR();
			$this->logins 		= $this->getLastLogins();
			$this->RFQInbox 	= $this->getViewedRFQInbox();
			$this->enquiries 	= $this->getLatestEnquiriesSent();
			$this->blockBuyers 	= $this->getBlockedBuyers();

		}
	}
	protected function setTranslationArray()
	{
		$this->translationArray = array(
			self::USER_MANAGEMENT_ADD => 'User mgmt: Adding',
			self::USER_MANAGEMENT_VIEW => 'User mgmt: Visit',
			self::ENQUIRY_BROWSER_VIEW => 'RFQ Inbox: View',
			self::ENQUIRY_SENDER_MARKED_AS_SPAMMER => 'RFQ: marked as spam',
			self::ENQUIRY_SENT => 'RFQ: sent',

			self::ENQUIRY_BROWSER_EXPORT_TO_EXCEL => 'RFQ: export to excel',
			self::SIR_VIEW => 'SIR: View',
			self::SIR_FORWARD_TO_CUSTOMER => 'SIR: Shipmate sending report to customer',
			self::SIR_EXPORT_TO_EXCEL => 'SIR: Export to excel',
			self::SIR_VIEW_DETAILED => 'SIR: View detailed',
			self::USER_LOGIN => 'Login',
			self::USER_FAILED_LOGIN => 'Failed Sign-in',
			self::USER_REQUEST_PASSWORD => 'Login: Request password',

			self::USER_DOWNLOAD_MA => 'MA: Download',
			self::USER_BLOCKING_BUYER => 'User: Block buyer',
			self::SUPPLIER_UPDATE_LISTING => 'Supplier/User: Update listing',

			//Added by Attila O 2015-11-16
			self::BUY_TAB_CLICKED => 'Buy tab clicked',
			self::BUY_TAB_RFQ_LIST => 'Buy tab, RFQ list returned',
		    self::RFQ_ON_BUY_TAB_EXPAND => 'RFQ on buy tab expanded',
		    self::MATCH_INT_ON_BUY_TAB_SHOW => 'Match interpretation shown',
		    self::MATCH_INT_ON_BUY_TAB_EDIT => 'Match interpretation edited',
		    self::RFQ_SENT_FROM_BUY_TAB => 'RFQ sent from buy tab',

		    self::SPEND_BENCHMARK_LAUNCH => 'Spend Benchmarking Report Launched',
			self::SPEND_BENCHMARK_VIEW_QUOTES => 'Spend Benchmarking Report - View quotes clicked',
			self::SPEND_BENCHMARK_FULL_DETAILS => 'Spend Benchmarking Report - Full Details Clicked',

			self::MATCH_DASHBOARD_LAUNCH => 'Match Dashboard Launched',
			self::MATCH_REPORT_LAUNCH => 'Match Report Launched',

			self::TRANSACTION_MONITOR_LAUNCH => 'Transaction Monitor Launched',

			self::MY_INFO_CLICK => 'My Info clicked',
			self::MY_COMPANIES_CLICK => 'My companies clicked',

			self::REVIEW_REQUESTS_CLICK => 'Review requests clicked',
			self::REVIEW_CREATED => 'Review created from Review Requests',
			self::USER_SETTINGS_CLICK => 'User Settings Clicked',
			self::APPROVED_SUPPLIERS_CLICK => 'Approved Suppliers Clicked',

			//Automatic Reminder
			self::AUTOMATIC_REMINDERS_CLICK => 'Automatic Reminders Clicked',
			self::PO_ARM_ACTIVATED => 'PO Automatic Reminders activated',
			self::PO_ARM_DEACTIVATED => 'PO Automatic Reminders deactivated',

			self::SELL_TAB_CLICKED => 'Sell tab clicked',
			self::GMV_SUPPLIER_CLICK => 'GMV Report Supplier clicked',
			self::GMV_SUPPLIER_STATISTICS_CLICK => 'GMV Report Supplier Statistics clicked',
			self::GMV_SUPPLIER_CONVERSION => 'GMV Report Supplier Conversion clicked',
			self::GMV_BUYER => 'GMV Report Buyer clicked',

			self::SUPPLIER_COMPANY_USER_SIGN_IN => 'Supplier company user sign in',
			self::BUYER_COMPANY_USER_SIGN_IN => 'Buyer company user sign-in',

			self::SEARCHES_BUYER_COMPANY => 'Searches by user belonging to Buyer Company',
			self::SEARCHES_SUPPLIER_COMPANY => 'Searches by user belonging to supplier company',

			//Webreporter Events
			self::WEBREPORTER_LAUNCH => 'Clicked on Webreporter',
			self::WEBREPORTER_ALL_POS => 'WebReporter All POs report served',
			self::WEBREPORTER_ALL_RFQS => 'WebReporter All RFQs report served',
			self::WEBREPORTER_POS_BY_SUPPLIER => 'WebReporter POs by Supplier report served',
			self::WEBREPORTER_POS_BY_VESSEL => 'WebReporter POs by Vessel report served',
			self::WEBREPORTER_SUPPLIER_ANALYSIS => 'WebReporter Supplier Analysis report served',
			self::WEBREPORTER_TRANSACTIONS_BY_SUPPLIER => 'WebReporter Transactions by Supplier report served',
			self::WEBREPORTER_TRANSACTIONS_BY_VESSEL => 'WebReporter Transactions by Vessel report served',

			self::IMPA_PRICE_BENCHMARK_REPORT_SERVED => 'IMPA Price Benchmark report served',
			self::IMPA_SPEND_TRACKER_REPORT_SERVED => 'IMPA Spend Tracker report served',
			self::TOTAL_SPEND_REPORT_SERVED => 'Total Spend report served',

			//Actie Promotion
			self::ACTIVE_PROMOTION_REPORT_VIEWS => 'Active Promotion Report Views',

			//Contact Requests
			self::CONTACT_REQUESTS  => 'Contact Requests',
			self::CONTACT_REQUESTS_SHOW_TNID  => 'Contact Requests TradeNet ID number',
			self::CONTACT_REQUESTS_SHOW_DETAILS	=> 'Contact Requests View other contact details',

			//Automatic Compliance Monitoring
			self::ACM_ACTIVATED => 'Automatic Compliance Monitoring Activated',
			self::ACM_DEACTIVATED => 'Automatic Compliance Monitoring Deactivated',

			//Spend Bechmarking Activete, Deactiveate
			self::SPEND_BENCHMARK_ACTIVATED => 'Spend Benchmarking activated',
			self::SPEND_BENCHMARK_DEACTIVATED => 'Spend Benchmarking deactivated', 

			//SPR report
			self::SPR_REPORT_VIEWS => 'SPR Report Views'
				
		);

	}

	/**
	* This is the grouping of the events
	* @return unknown
	*/
	protected function setEventGroups()
	{
		$this->_eventGroups = array(
			'user_management'      => array(self::USER_MANAGEMENT_ADD, self::USER_MANAGEMENT_VIEW),
			'enquiry'              => array(self::ENQUIRY_BROWSER_VIEW, self::ENQUIRY_SENDER_MARKED_AS_SPAMMER, self::ENQUIRY_SENT, self::ENQUIRY_BROWSER_EXPORT_TO_EXCEL),
			'sir'                  => array(self::SIR_VIEW, self::SIR_FORWARD_TO_CUSTOMER, self::SIR_EXPORT_TO_EXCEL, self::SIR_VIEW_DETAILED),
			'user'                 => array(self::USER_LOGIN, self::USER_REQUEST_PASSWORD, self::USER_DOWNLOAD_MA, self::USER_BLOCKING_BUYER),
			'failed_login'		   => array(self::USER_FAILED_LOGIN),
			'supplier'		       => array(self::SUPPLIER_UPDATE_LISTING),
			'buy'			       => array(self::BUY_TAB_CLICKED, self::RFQ_ON_BUY_TAB_EXPAND, self::MATCH_INT_ON_BUY_TAB_SHOW, self::MATCH_INT_ON_BUY_TAB_EDIT, self::RFQ_SENT_FROM_BUY_TAB, self::BUY_TAB_RFQ_LIST),
			'spend_benchmark'      => array(self::SPEND_BENCHMARK_LAUNCH, self::SPEND_BENCHMARK_VIEW_QUOTES, self::SPEND_BENCHMARK_FULL_DETAILS),
			'match-dashboard'	   => array(self::MATCH_DASHBOARD_LAUNCH),
			'match-report'		   => array(self::MATCH_REPORT_LAUNCH),
			'txnmon'		       => array(self::TRANSACTION_MONITOR_LAUNCH),
			'info-click'	       => array(self::MY_INFO_CLICK),
			'companies-click'      => array(self::MY_COMPANIES_CLICK),
			'review'		       => array(self::REVIEW_REQUESTS_CLICK, self::REVIEW_CREATED),
			'user-settings'        => array(self::USER_SETTINGS_CLICK),
			'approved-suppliers'   => array(self::APPROVED_SUPPLIERS_CLICK),
			'automatic-reminders'  => array(self::AUTOMATIC_REMINDERS_CLICK, self::PO_ARM_ACTIVATED, self::PO_ARM_DEACTIVATED),
			'sell'   			   => array(self::SELL_TAB_CLICKED),
			'gmv'       		   => array(self::GMV_SUPPLIER_CLICK, self::GMV_SUPPLIER_STATISTICS_CLICK, self::GMV_SUPPLIER_CONVERSION, self::GMV_BUYER),
			'sigh-in'     		   => array(self::SUPPLIER_COMPANY_USER_SIGN_IN, self::BUYER_COMPANY_USER_SIGN_IN),
			'search'			   => array(self::SEARCHES_BUYER_COMPANY, self::SEARCHES_SUPPLIER_COMPANY),
			'webreporter'		   => array(self::WEBREPORTER_LAUNCH, self::WEBREPORTER_ALL_POS, self::WEBREPORTER_ALL_RFQS, self::WEBREPORTER_POS_BY_SUPPLIER, self::WEBREPORTER_POS_BY_VESSEL, self::WEBREPORTER_SUPPLIER_ANALYSIS, self::WEBREPORTER_TRANSACTIONS_BY_SUPPLIER, self::WEBREPORTER_TRANSACTIONS_BY_VESSEL),
			'impa_bpm'		  	   => array(self::IMPA_PRICE_BENCHMARK_REPORT_SERVED),
			'impa_spend'		   => array(self::IMPA_SPEND_TRACKER_REPORT_SERVED),
			'total_spend'		   => array(self::TOTAL_SPEND_REPORT_SERVED),
			'active_promotion'     => array(self::ACTIVE_PROMOTION_REPORT_VIEWS),
			'contact_requests'     => array(self::CONTACT_REQUESTS, self::CONTACT_REQUESTS_SHOW_TNID, self::CONTACT_REQUESTS_SHOW_DETAILS),
			'automatic_compliance' => array(self::ACM_ACTIVATED, self::ACM_DEACTIVATED),
			'spend_benchmark_act'  => array(self::SPEND_BENCHMARK_ACTIVATED, self::SPEND_BENCHMARK_DEACTIVATED),
			'spr_report'  		   => array(self::SPR_REPORT_VIEWS)
		);
	}

	/**
	* This is the grouping of the events
	* @return unknown
	*/
	protected function setEventGroupCategoryNames()
	{
		$this->_eventGroupNames = array(
			'user_management'      => 'User Managament',
			'enquiry'              => 'Enquiry',
			'sir'                  => 'SIR3 Report Views',
			'user'                 => 'Successful Sign Ins',
			'supplier'		       => 'Supplier',
			'buy'			       => 'Buy Tab Events',
			'spend_benchmark'      => 'Spend Benchmarking Report Events',
			'match-dashboard'	   => 'Match dashboard Events',
			'match-report'		   => 'Match Report Events',
			'txnmon'		       => 'Transaction Monitor Events',
			'info-click'	       => 'Info',
			'companies-click'      => 'Companies',
			'review'		       => 'Review',
			'user-settings'        => 'User Settings',
			'approved-suppliers'   => 'Apprived Suppliers',
			'automatic-reminders'  => 'Automatic Reminders',
			'sell'   			   => 'Sell tab',
			'gmv'       		   => 'GMV',
			'sigh-in'     		   => 'Sign Ins',
			'search'			   => 'Search',
			'webreporter'		   => 'WebReporter Events',
			'impa_bpm'		  	   => 'IMPA Price Benchmark Events',
			'impa_spend'		   => 'IMPA Spend Tracker Events',
			'total_spend'		   => 'Total Spend Report Events',
			'automatic_compliance' => 'Automatic Compliance Monitoring',
			'spend_benchmark_act'  => 'Branches with Spend Benchmarking Activated',
			'contact_requests'     => 'Contact Requests',
			'failed_login'		   => 'Failed Sign Ins',
			'active_promotion'     => 'Active Promotion Report Views',
			'spr_report'		   => 'SPR Report Views'
		);
	}
	

	/**
	* Getting the events by group
	* @param $groupName Name of event group
	* @return array
	* @throws Myshipserv_Exception_MessagedException
	*/
	public function getEventsByGroupName($groupName)
	{
		if (array_key_exists($groupName, $this->_eventGroups)) {
			return $this->_eventGroups[$groupName];
		} else {
			throw new Myshipserv_Exception_MessagedException("Invalid event group: ".$groupName, 500);
		}
	}

	/**
	* Getting the events by group
	* @param $name Name of event group
	* @return array
	* @throws Myshipserv_Exception_MessagedException
	*/
	public function getEventGroupCategoryName($name)
	{
		if (array_key_exists($name, $this->_eventGroupNames)) {
			return $this->_eventGroupNames[$name];
		} else {
			throw new Myshipserv_Exception_MessagedException("Invalid event group category name: ".$groupName, 500);
		}
	}

	/**
	* Transform small caps, - format text (coming from javascript log events) to log event string, and validate.
	*/
	public function getTransformedActivityType($activityString)
	{

		$convertedString = str_replace('-', '_', strtoupper($activityString));
		if (array_key_exists($convertedString, $this->translationArray)) {
			return $convertedString; 
		} else {
			 throw new Myshipserv_Exception_MessagedException("Invalid parameter value for Activity Log  (".$activityString .")", 500);
		}
	}

	public function getLastSearch()
	{
		$sql = "
SELECT *
FROM
(
	SELECT
	  pst_search_text SEARCH_QUERY
	  , TO_CHAR(MAX(pst_search_date_time), '" . self::ORACLE_DATE_FORMAT . "') LAST_DATE
	  , MAX(pst_search_date_time) as LAST_DATE_AS_DATE
	FROM
		(
	     	SELECT
				pst_search_text,
		        pst_search_date_time,
		        pst_search_text || to_char( pst_search_date_time, 'DD-MON-YY') grouped_columns

			FROM
				pages_statistics
			WHERE
				pst_usr_user_code=:userId
				AND
				(
					TRIM(pst_search_text) IS NOT NULL
				)
				AND pst_search_date_time BETWEEN TO_DATE(:endDate) + 0.99999 AND TO_DATE(:startDate)

		)
	GROUP BY
	   grouped_columns,
	   pst_search_text
   )
ORDER BY
	LAST_DATE_AS_DATE DESC
				";
		$params = array( 'userId' => $this->userId, "startDate" => $this->period['start']->format('d M Y'), "endDate" => $this->period['end']->format('d M Y') );

		return $this->getDb()->fetchAll( $sql, $params );
	}

	public function getViewedSIR()
	{
		$sql = "
			SELECT * FROM
			(
				SELECT
				  pua_object_id TNID
				  , TO_CHAR(MAX(pua_date_created), '" . self::ORACLE_DATE_FORMAT . "') LAST_DATE
				  , MAX(pua_date_created) AS LAST_DATE_AS_DATE
				FROM
				  (
						SELECT
							pua_object_id,
					        pua_date_created,
					        pua_object_id || to_char( pua_date_created, 'DD-MON-YY') grouped_columns
						FROM
							pages_user_activity
						WHERE
							pua_psu_id=:userId
							AND pua_activity='SIR_VIEW'
							AND pua_date_created BETWEEN TO_DATE(:endDate) AND TO_DATE(:startDate)
				    )
				GROUP BY
				   grouped_columns, pua_object_id
				ORDER BY
					MAX(pua_date_created) DESC
			)
			ORDER BY
				LAST_DATE_AS_DATE DESC

		";
		$params = array( 'userId' => $this->userId, "startDate" => $this->period['start']->format('d M Y'), "endDate" => $this->period['end']->format('d M Y') );

		//foreach( $this->getDb()->fetchAll( $sql, $params ) as $row )
		foreach ($this->getSsreport2Db()->fetchAll( $sql, $params ) as $row) {
			$supplier = Shipserv_Supplier::fetch($row['TNID'], "", false);

			if( $supplier->tnid != null )
			{
				$data[] = array(
					"id" => $row['TNID'],
					"date" => $row['LAST_DATE'],
					"name" => $supplier->name,
					"url" => $supplier->getUrl()
				);
			}
		}
		return $data;

	}

	public function getViewedRFQInbox()
	{
		$sql = "
			SELECT * FROM
			(
			SELECT
			  pua_object_id TNID
			  , TO_CHAR(MAX(pua_date_created), '" . self::ORACLE_DATE_FORMAT . "') LAST_DATE
			  , MAX(pua_date_created) LAST_DATE_AS_DATE
			FROM
			  (
					SELECT
						pua_object_id,
				        pua_date_created,
				        pua_object_id || to_char( pua_date_created, 'DD-MON-YY') grouped_columns
					FROM
						pages_user_activity
					WHERE
						pua_psu_id=:userId
						AND pua_activity='ENQUIRY_BROWSER_VIEW'
						AND pua_date_created BETWEEN TO_DATE(:endDate) + 0.99999 AND TO_DATE(:startDate)
			    )
			GROUP BY
			   grouped_columns, pua_object_id
			)
			ORDER BY
				LAST_DATE_AS_DATE DESC
		";
		$params = array( 'userId' => $this->userId, "startDate" => $this->period['start']->format('d M Y'), "endDate" => $this->period['end']->format('d M Y') );

		//foreach( $this->getDb()->fetchAll( $sql, $params ) as $row )
		foreach($this->getSsreport2Db()->fetchAll($sql, $params) as $row) {
			$supplier = Shipserv_Supplier::fetch($row['TNID'], "", true);
			if( $supplier->tnid != null )
			{
				$data[] = array(
					"id" => $row['TNID'],
					"date" => $row['LAST_DATE'],
					"name" => $supplier->name,
					"url" => $supplier->getUrl()
				);
			}
		}
		return $data;
	}
	public function  getLastLogins()
	{
		$sql = "
			SELECT
			  a.pua_object_id tnid,
			  TO_CHAR(a.pua_date_created, '" . self::ORACLE_DATE_FORMAT . "') LAST_DATE
			FROM
			  pages_user_activity a
			  INNER JOIN
			  (
						SELECT
							pua_object_id,
					        pua_date_created
						FROM
							pages_user_activity
						WHERE
							pua_psu_id=:userId
							AND pua_activity='USER_LOGIN'
							AND pua_date_created BETWEEN TO_DATE(:endDate) + 0.99999 AND TO_DATE(:startDate)
			  ) b
			ON (a.pua_object_id = b.pua_object_id AND a.pua_date_created=b.pua_date_created)
			ORDER BY a.pua_date_created DESC
		";
		$params = array( 'userId' => $this->userId, "startDate" => $this->period['start']->format('d-M-Y'), "endDate" => $this->period['end']->format('d-M-Y') );
		//foreach( $this->getDb()->fetchAll( $sql, $params ) as $row )
		foreach ($this->getSsreport2Db()->fetchAll($sql, $params) as $row ) {
				$data[] = array(
					"id" => $row['TNID'],
					"date" => $row['LAST_DATE']
				);
		}
		return $data;
	}

	public function getLatestEnquiriesSent()
	{
		$sql = "
SELECT
  pin_id,
  TO_CHAR(pin_creation_date, '" . self::ORACLE_DATE_FORMAT . "') creationDate
FROM
  pages_inquiry
WHERE
  pin_usr_user_code=:userId
  AND pin_creation_date BETWEEN TO_DATE(:endDate) + 0.99999 AND TO_DATE(:startDate)+1
  AND pin_status='RELEASED'
ORDER BY
	pin_creation_date DESC
		";

		$params = array( 'userId' => $this->userId, "startDate" => $this->period['start']->format('d M Y'), "endDate" => $this->period['end']->format('d M Y') );
		foreach( $this->getDb()->fetchAll( $sql, $params ) as $row )
		{
			$enquiry = Shipserv_Enquiry::getInstanceById( $row['PIN_ID'] );
			if( $enquiry->pinId != "" )
			{
				$data[] = array(
					"id" => $row['PIN_ID'],
					"enquiry" => $enquiry,
					"date" => $row['CREATIONDATE']
				);
			}
		}
		return $data;
	}

	public function getBlockedBuyers()
	{
		$sql = "
			SELECT * FROM
			(
				SELECT
				  pua_object_id TNID
				  , TO_CHAR(MAX(pua_date_created), '" . self::ORACLE_DATE_FORMAT . "') LAST_DATE
				  , MAX(pua_date_created) AS LAST_DATE_AS_DATE
				FROM
				  (
						SELECT
							pua_object_id,
					        pua_date_created,
					        pua_object_id || to_char( pua_date_created, 'DD-MON-YY') grouped_columns
						FROM
							pages_user_activity
						WHERE
							pua_psu_id=:userId
							AND pua_activity='USER_BLOCKING_BUYER'
							AND pua_date_created BETWEEN TO_DATE(:endDate) + 0.99999 AND TO_DATE(:startDate)
				    )
				GROUP BY
				   grouped_columns, pua_object_id
				ORDER BY
					MAX(pua_date_created) DESC
			)
			ORDER BY
				LAST_DATE_AS_DATE DESC

		";
		$params = array( 'userId' => $this->userId, "startDate" => $this->period['start']->format('d M Y'), "endDate" => $this->period['end']->format('d M Y') );

		//foreach( $this->getDb()->fetchAll( $sql, $params ) as $row )
		foreach($this->getSsreport2Db()->fetchAll( $sql, $params ) as $row)
		{

		}
	}

	public function toArray()
	{
		return array(
			"searches" => $this->searches,
			"SIR" => $this->SIR,
			"logins" => $this->logins,
			"RFQInbox" => $this->RFQInbox,
			"enquiries" => $this->enquiries
		);
	}

    /**
     * Returns the date user last time accepted terms and conditions or false if they have never accepted them
     *
     * @todo: static method sticks out like a sore thumb, but there is no support for "all the period of time" in the legacy
     * @todo: so in order to add a flag independent of period we would need to re-write/re-test legacy queries above
     * @todo: will need to do that one day
     *
     * @author  Yuriy Akopov
     * @date    2013-09-10
     * @story   S8093
     *
     * @param   Shipserv_user|int   $user
     *
     * @return  DateTime|bool
     */
    public static function getUserTermsConfirmationDate($user) {
    	return self::getDateByActivity($user, self::TERM_AND_CONDITIONS_CONFIRMED);
    }

    public static function getUserCompletedDetailedProfileDate($user){
    	return self::getDateByActivity($user, self::DETAILED_INFORMATION_COMPLETED);
    }

    public static function getDateByActivity($user, $activity)
    {
    	if ($user instanceof Shipserv_User) {
    		$userId = $user->userId;
    	} else {
    		$userId = $user;
    	}
    	
    	$params = array(
    			'userid'    => $userId,
    			'activity'  => $activity
    	);
    	
    	//$db = Shipserv_Helper_Database::getDb();
    	$db = Shipserv_Helper_Database::getSsreport2Db();

    	$select = new Zend_Db_Select($db);
    	$select
    	->from(
    			array('pua' => self::TABLE_NAME),
    			array(
    					'confirmation_date' => new Zend_Db_Expr(
    							"TO_CHAR(GREATEST(" . self::COL_DATE_CREATED . ", " . self::COL_DATE_UPDATED . "), 'YYYY-MM-DD HH24:MI:SS')"
    					)
    			)
    	)
    	->where('pua.' . self::COL_USER_ID . ' = :userid')
    	->where('pua.' . self::COL_ACTIVITY . ' = :activity')
    	// shouldn't happen, but still let's protect ourselves from multiple records by preferring the most recent one
    	->order('pua.' . self::COL_DATE_UPDATED . ' DESC')
    	->order('pua.' . self::COL_DATE_CREATED . ' DESC')
    	->where('ROWNUM = 1')
    	;
    	
    	/* 
    	 * I have to memcache it locally, as this method called from a static method, and regardless of that this class is derived
    	 * from a memcachabe class it will break
    	 */
    	$config = Zend_Registry::get('config');
    	$memcache = new Memcache();
    	$connection = $memcache->pconnect($config->memcache->server->host, $config->memcache->server->port);
    	
    	$regKey = md5(__CLASS__ . '_' . __FUNCTION__ . '_' .  serialize($params));
    	$date = null;
    	if ($connection) {
    		$date = $memcache->get($regKey);
    	}
		
    	if (!$date) {
    		$date = $db->fetchOne($select, $params);
    		
    		if ($connection) {
    			$memcache->set($regKey, $date, null, self::MEMCACHE_TTL);
    		}
    	}
    	
    	if (strlen($date) === 0) {
    		return false;
    	}

    	$date = new DateTime($date);
    	
    	return $date;

    }

    
    public function getActivityStats($params)
    {
        $params['yyyy'] = intval($params['yyyy']);
    	$sql = "
			SELECT
			  PUA_ACTIVITY ACTIVITY
			  , TO_CHAR(pua_date_created, 'YYYYWW') WEEK_STR
			  , COUNT(*) TOTAL
			FROM
			  pages_user_activity
			WHERE
			  pua_is_shipserv='N'
    		  AND pua_date_created BETWEEN TO_DATE('01-JAN-" . $params['yyyy'] . "') AND TO_DATE('01-JAN-" . $params['yyyy'] . "') + 365
			GROUP BY
			    PUA_ACTIVITY
			  , TO_CHAR(pua_date_created, 'YYYYWW')
			ORDER BY
			  TO_CHAR(pua_date_created, 'YYYYWW') ASC
    	";
    	//$db = $this->getDb();
    	//table moved to reportdb
    	$db = $this->getSsreport2Db();
    	$data = $db->fetchAll($sql);
    	$x = array();
    	foreach($data as $row) {
    		$x[$row['ACTIVITY']][(int)str_replace($params['yyyy'], "", $row['WEEK_STR'])] = (int)$row['TOTAL'];
    	}
    	$x['SEARCH'] = $this->getSearchStats($params);
    	return $x;
    }
    

    public function getSearchStats($params)
    {
		$sql = "
			SELECT
			  TO_CHAR(pst_search_date_time, 'YYYYWW') WEEK_STR
			  , COUNT(*) TOTAL
			FROM
			  pages_statistics
			WHERE
			  pst_search_date_time BETWEEN TO_DATE('01-JAN-" . $params['yyyy'] . "') AND TO_DATE('01-JAN-" . $params['yyyy'] . "') + 365
			GROUP BY
			  TO_CHAR(pst_search_date_time, 'YYYYWW')
			ORDER BY
			  TO_CHAR(pst_search_date_time, 'YYYYWW') ASC
		";
		$db = $this->getDb();
		$data = $db->fetchAll($sql);
		$x = array();
		foreach($data as $row)
		{
			$x[(int)str_replace($params['yyyy'], "", $row['WEEK_STR'])] = (int)$row['TOTAL'];
		}
		return $x;

    }
}
