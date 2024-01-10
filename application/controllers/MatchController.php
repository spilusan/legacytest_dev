<?php
/**
 * Match controller for various actions related to Match.
 * @package myshipserv
 * @author Shane O'Connor <soconnor@shipserv.com>
 * @copyright Copyright (c) 2012, ShipServ
 */
class MatchController extends Myshipserv_Controller_Action 
{
    //Array of external IPs that we want to allow for match
    private $ipRestrictionInPlace = true;

    /**
     * All Match functions to be secured by IP address to Offices. Overriding the inherited constructor to include the check.
     * @param Zend_Controller_Request_Abstract $request
     * @param Zend_Controller_Request_Abstract $request
     * @param Zend_Controller_Response_Abstract $response
     * @param array $invokeArgs
     * @throws exception Access Denied!
     */
    public function __construct(Zend_Controller_Request_Abstract $request, Zend_Controller_Response_Abstract $response, array $invokeArgs = array()) 
    {
        //Check if the IP restriction should be lifted
        $db = Shipserv_Helper_Database::getDb();
        
        $sql = "Select Coalesce(MSE_VALUE, 'N') from Match_Settings where MSE_NAME = 'DISABLE_IP_RESTRICTIONS'";
        $setting = $db->fetchOne($sql);
        
        if ($setting === 'Y') {
            $this->ipRestrictionInPlace = false;
        }
        
        parent::__construct($request, $response, $invokeArgs);
        $this->getRequest()->setParam('forceTab', 'shipmate');
    }

    
    public function orphanedOrderAction()
    {
    	$this->abortIfNotShipMate();

    	$this->_helper->layout->setLayout('default');
    	$pageSize = (($this->params['pageSize'])?$this->params['pageSize']:10);
    	$startRow = ($this->params['page']!="")?($this->params['page']-1)*$pageSize:1;
    	$endRow = ($this->params['page']!="")?($this->params['page'])*$pageSize:$pageSize;
    	$db = Shipserv_Helper_Database::getSsreport2Db();

    	switch( $this->params['certainty'] )
    	{
    		case '75': 	$whereSql = ' AND moq_total_score/50*100 >=75 ';
    					break;

    		case '50':	$whereSql = ' AND moq_total_score/50*100 >= 50 and moq_total_score/50*100 < 75';
    					break;

    		case '25':	$whereSql = ' AND moq_total_score/50*100 >= 25 and moq_total_score/50*100 < 50';
    					break;

    		case '0':	$whereSql = ' AND moq_total_score/50*100 < 25';
    					break;
    	}

    	if( $this->params['qotType'] == "backtest" )
    	{
    		$whereSql .= " AND moq_is_test=1";
    		$sqlForAccuracy = "
				SELECT
				  ROUND(x.matched/(x.matched+x.not_matched)*100) accuracy
				FROM
				(
				  SELECT
				  (
				    SELECT
				      COUNT(*)
				    FROM
				      match_order_quote moq,
				      match_orphaned_order_test mot
				    WHERE
				      moq.moq_ord_internal_ref_no=mot.ord_internal_ref_no
				      AND moq.moq_qot_internal_ref_no=mot.qot_internal_ref_no
				  ) +1 matched,
				  (
				    SELECT
				      COUNT(*)
				    FROM
				      match_order_quote moq,
				      match_orphaned_order_test mot
				    WHERE
				      moq.moq_ord_internal_ref_no=mot.ord_internal_ref_no
				      AND moq.moq_qot_internal_ref_no!=mot.qot_internal_ref_no
				  ) not_matched
				  FROM dual
				) x
    		";
    		$this->view->accuracyData = $db->fetchOne($sqlForAccuracy);
    		$whereSql .= " AND moq_is_test=1";

    	}
    	else
    	{
    		$whereSql .= " AND moq_is_test=0";
    	}

    	if( $this->params['ordType'] == "matched" )
    	{
	    	$sql = "
	    		SELECT
	    			a.*
	    		FROM
	    		(
		    		SELECT
		    			moq_ord_internal_ref_no ord_internal_ref_no,
		    			moq_qot_internal_ref_no qot_internal_ref_no,
		    			moq_qot_type quote_type,
		    			moq_total_score score,
		    			(SELECT rfq_internal_ref_no FROM qot WHERE qot_internal_ref_no=moq_qot_internal_ref_no) rfq_internal_ref_no,
	    				moq_debug debug,
	    				(SELECT DISTINCT qot_internal_ref_no FROM match_orphaned_order_test WHERE ord_internal_ref_no=moq_ord_internal_ref_no AND rownum=1) original_qot_internal_ref_no
		    		FROM
		    			match_order_quote
		    		WHERE
	    				moq_qot_internal_ref_no IS NOT null
		    			" . $whereSql . "
		    		ORDER BY moq_total_score/50*100 DESC
	    	    ) a

	    	";
    	}
    	else
    	{
    		$sql = "

    			SELECT
    				a.*
    				, (SELECT DISTINCT qot.rfq_internal_ref_no FROM qot WHERE qot.qot_internal_ref_no = a.original_qot_internal_ref_no ) original_rfq_internal_ref_no
    			FROM
    			(
		    		SELECT
		    			moq_ord_internal_ref_no ord_internal_ref_no,
		    			null qot_internal_ref_no,
		    			null quote_type,
		    			0 score,
		    			null rfq_internal_ref_no,
	    				moq_debug debug,
    					moq_is_test,
	    				moq_qot_internal_ref_no,
	    				(SELECT DISTINCT qot_internal_ref_no FROM match_orphaned_order_test WHERE ord_internal_ref_no=moq_ord_internal_ref_no AND rownum=1) original_qot_internal_ref_no

    				FROM
		    			match_order_quote
    			) a
		    	WHERE
	    			moq_qot_internal_ref_no IS null

	    	";
    		if( $this->params['qotType'] == "backtest" )
    		{
    			$sql .= " AND moq_is_test=1";
    		}
    		else
    		{
    			$sql .= " AND moq_is_test=0";
    		}
    	}

    	$sqlForTotal = "
    		SELECT
    			COUNT(*)
    		FROM
    		(
    			$sql
	    	)
    	";

    	$sqlForData = "
    		SELECT * FROM
    		(
    			SELECT rownum rn, x.* FROM
    			(
    				$sql
    			) x
    		)
    		WHERE
    			rn BETWEEN $startRow AND $endRow
    	";
    	if( $this->params['qotType'] == "backtest" )
    	{
    		$sqlForStats = "
	    		SELECT
	    			( SELECT COUNT(*) FROM match_order_quote WHERE moq_is_test=1 ) TOTAL_ROW
	    			, ( SELECT COUNT(*) FROM match_order_quote WHERE moq_qot_internal_ref_no IS NOT null AND moq_is_test=1 ) TOTAL_MATCHED_QOT
	    			, ( SELECT COUNT(*) FROM match_order_quote WHERE moq_qot_internal_ref_no IS NOT null AND moq_is_test=1 AND (moq_total_score/50*100) < 50 ) TOTAL_LOWER
	    			, ( SELECT COUNT(*) FROM match_order_quote WHERE moq_qot_internal_ref_no IS NOT null AND moq_is_test=1 AND (moq_total_score/50*100) >= 50 ) TOTAL_UPPER
	    		FROM dual
    					    	";
    	}
    	else
    	{
	    	$sqlForStats = "
	    		SELECT
	    			( SELECT COUNT(*) FROM match_order_quote WHERE moq_is_test=0 ) TOTAL_ROW
	    			, ( SELECT COUNT(*) FROM match_order_quote WHERE moq_qot_internal_ref_no IS NOT null AND moq_is_test=0 ) TOTAL_MATCHED_QOT
	    			, ( SELECT COUNT(*) FROM match_order_quote WHERE moq_qot_internal_ref_no IS NOT null AND moq_is_test=0 AND (moq_total_score/50*100) < 50 ) TOTAL_LOWER
	    			, ( SELECT COUNT(*) FROM match_order_quote WHERE moq_qot_internal_ref_no IS NOT null AND moq_is_test=0 AND (moq_total_score/50*100) >= 50 ) TOTAL_UPPER
	    		FROM dual
	    	";
    	}

    	try
    	{
    		$stats = $db->fetchAll($sqlForStats);
    	}
    	catch(Exception $e)
    	{
    		$stats = array();
    	}

    	$this->view->data = $db->fetchAll($sqlForData);
    	$this->view->stats = $stats;
    	$this->view->params = $this->params;
    	$this->view->paginatorInfo = array('page' => (($this->params['page'])?$this->params['page']:1), 'total' => $db->fetchOne($sqlForTotal), 'pageSize' => (($this->params['pageSize'])?$this->params['pageSize']:100));
    }

    public function init() {
        parent::init();
        /*
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('add-category-to-rfq', 'json')
                ->addActionContext('remove-cat-from-rfq', 'json')
                ->addActionContext('add-brand-to-rfq', 'json')
                ->addActionContext('remove-brand-from-rfq', 'json')
                ->setAutoJsonSerialization(true)
                ->initContext();
        */
    }

    public function indexAction() {
        if (!Shipserv_Helper_Security::isValidInternalIP(Shipserv_Helper_Security::getRealUserIP()) && $this->ipRestrictionInPlace) {
            throw new Myshipserv_Exception_MessagedException("Access Denied: " . Shipserv_Helper_Security::getRealUserIP(), 401);
            die();
        }
    }

    /**
     * Match inbox page
     */
    public function inboxAction() {
        // page content is now provided by an independent Match Engine application which will output to an iframe
        Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        if (!Myshipserv_Helper_Session::getActiveCompanyNamespace()->id) {
            throw new Myshipserv_Exception_MessagedException("You are not allowed to perform this action.", 401);
        }

        $this->view->matchInboxUrl = trim(Myshipserv_Config::getMatchUrl(), '/') . '/inbox/index';
    }

    public function automatchStatsAction() {
        Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        if (!Myshipserv_Helper_Session::getActiveCompanyNamespace()->id) {
            throw new Myshipserv_Exception_MessagedException("You are not allowed to perform this action.", 401);
        }

        $this->view->automatchStatsUrl = trim(Myshipserv_Config::getMatchUrl(), '/') . '/automatch/stats';
    }

    public function conversionKpiAction()
    {
    	$this->abortIfNotShipMate();

    	if( $this->user->canPerform('PSG_ACCESS_MATCH') === false )
    	{
    		throw new Myshipserv_Exception_MessagedException('You are not allowed to access this page as specified within your group: ' . $this->user->getGroupName(), 401);
    	}

        $this->_helper->layout->setLayout('default');

        $report = new Shipserv_Match_Report_Conversion();
        $formParams = $this->params;

        if (!empty($formParams['fromDate'])) {
            $stt = strtotime(str_replace("/", "-", $formParams['fromDate']));
            $report->setStartDate(date('d', $stt), date('M', $stt), date('Y', $stt));
        }

        if (!empty($formParams['toDate'])) {
            $stt = strtotime(str_replace("/", "-", $formParams['toDate']));
            $report->setEndDate(date('d', $stt), strtoupper(date('M', $stt)), date('Y', $stt));
        }


        $this->view->startDate = $report->startDateObject->format('d/m/Y');
        $this->view->endDate = $report->endDateObject->format('d/m/Y');

        $this->view->results = $report->getStat();
    }

    public function orderDetailAction()
    {
        $this->_helper->layout->setLayout('default');
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $this->view->params = $this->params;
        $this->view->db = $db;
        $sql = "SELECT * FROM match_b_order_by_match_spb WHERE ord_internal_ref_no="  . $this->params['ordId'];
        $row = $db->fetchAll($sql);

        $this->view->rfq = Shipserv_Rfq::getInstanceById($row[0]['RFQ_SENT_TO_MATCH'], null, true);
        $this->view->report = new Shipserv_Match_Report_Buyer_Rfq($row[0]['RFQ_SENT_TO_MATCH'], $row[0]['BYB_BRANCH_CODE'], true);


    }

	public function breakdownPerBuyerAction()
    {
    	$this->abortIfNotShipMate();

    	$this->_helper->layout->setLayout('default');
    	$report = new Shipserv_Match_Report_Buyer($this->params['buyerId']);

    	if (!empty($this->params['startDate'])) {
    		$stt = strtotime(str_replace("/", "-", $this->params['startDate']));
    		$report->setStartDate(date('d', $stt), date('M', $stt), date('Y', $stt));
    	}
    	if (!empty($this->params['endDate'])) {
    		$stt = strtotime(str_replace("/", "-", $this->params['endDate']));
    		$report->setEndDate(date('d', $stt), strtoupper(date('M', $stt)), date('Y', $stt));
    	}

    	$this->view->report = $report;
    	$this->view->params = $this->params;
    	$this->view->buyerName = $report->getBuyerName();
    }

    public function breakdownPerRfqAction()
    {
    	$this->abortIfNotShipMate();

    	$this->_helper->layout->setLayout('default');
    	$this->view->report = new Shipserv_Match_Report_Buyer_Rfq($this->params['rfqId'], $this->params['buyerId'], true);
    	$this->view->rfq = $this->view->report->getRfq();
    	$this->view->params = $this->params;
    	$this->view->buyerName = $this->view->report->getBuyerName();

    }


    public function efficiencyAction()
    {
        $this->abortIfNotShipMate();

        if( $this->user->canPerform('PSG_ACCESS_MATCH') === false )
    	{
    	       throw new Myshipserv_Exception_MessagedException('You are not allowed to access this page as specified within your group: ' . $this->user->getGroupName(), 401);
    	}

    	$this->_helper->layout->setLayout('default');

    	if( $this->params['yyyy'] == null || $this->params['yyyy'] == "" )
    	{
    		$this->params['yyyy'] = date('Y');
    	}

        $this->slowLoadingPage();

    	$report = new Shipserv_Match_Report_AlgorithmEfficiency;
    	$report->setParams($this->params);

    	$this->view->data = $report->getData();
    }

    
    public function usageReportAction()
    {
    	$this->abortIfNotShipMate();

    	if( $this->user->canPerform('PSG_ACCESS_MATCH') === false )
    	{
    		throw new Myshipserv_Exception_MessagedException('You are not allowed to access this page as specified within your group: ' . $this->user->getGroupName(), 401);
    	}

    	$this->_helper->layout->setLayout('default');

    	if( $this->params['yyyy'] == null || $this->params['yyyy'] == "" )
    	{
    		$this->params['yyyy'] = date('Y');
    	}

        $this->slowLoadingPage();

    	$report = new Shipserv_Match_Report_Usage;
    	$report->setParams($this->params);

    	$this->view->totalRfqSentToMatch 	= $report->getStatisticOfTotalRfqSentToMatch();
    	$this->view->totalQuoteReceived 	= $report->getStatisticOfTotalQuoteReceived();
    	$this->view->totalForwardedRfq 		= $report->getStatisticOfTotalForwardedRfq();
    	$this->view->totalQuoteRate		 	= $report->getStatisticOfQuoteRate();
    	$this->view->report = $report;
    }

    
    public function adoptionKpiAction()
    {
    	$this->abortIfNotShipMate();

    	if( $this->user->canPerform('PSG_ACCESS_MATCH') === false )
    	{
    		throw new Myshipserv_Exception_MessagedException('You are not allowed to access this page as specified within your group: ' . $this->user->getGroupName(), 401);
    	}

        $this->_helper->layout->setLayout('default');

        $this->slowLoadingPage();

        $internalKpis = new Shipserv_Match_Report_AdoptionRate();

        if (!empty($this->params['fromDate'])) {
            $stt = strtotime(str_replace("/", "-", $this->params['fromDate']));
            $internalKpis->setStartDate(date('d', $stt), date('M', $stt), date('Y', $stt));
        }

        if (!empty($this->params['toDate'])) {
            $stt = strtotime(str_replace("/", "-", $this->params['toDate']));
            $internalKpis->setEndDate(date('d', $stt), strtoupper(date('M', $stt)), date('Y', $stt));
        }

        if (!empty($this->params['toDate'])) {
        	$internalKpis->setPurchaserEmail($this->params['email']);
        }

        if(!empty($this->params['showBuyer']) || $this->params['showBuyer'] == "all")
        $internalKpis->includeNonMatchBuyer();

        $this->view->results = $internalKpis->getStat();
        $this->view->totalRfqEvent = $internalKpis->getTotalRfqEvent();

        $this->view->startDate = $internalKpis->startDateObject->format('d/m/Y');
        $this->view->endDate = $internalKpis->endDateObject->format('d/m/Y');
    }

    
    public function internalKpisAction() 
    {
    	$this->abortIfNotShipMate();

        $this->_helper->layout->setLayout('default');

        $internalKpis = new Shipserv_Match_InternalKpis();

        $formParams = $this->params;

        if (!empty($formParams['fromDate'])) {
            $stt = strtotime(str_replace("/", "-", $formParams['fromDate']));
            $internalKpis->setStartDate(date('d', $stt), date('M', $stt), date('Y', $stt));
        }
        if (!empty($formParams['toDate'])) {
            $stt = strtotime(str_replace("/", "-", $formParams['toDate']));
            $internalKpis->setEndDate(date('d', $stt), strtoupper(date('M', $stt)), date('Y', $stt));
        }

        $results = $internalKpis->getAllStatsForBuyers();

        $this->view->results = $results;

        $this->view->startDate = $internalKpis->startDate;
        $this->view->endDate = $internalKpis->endDate;

//        if (!empty($formParams['TNID'])) {
//            $internalKpis->setBuyer($formParams['TNID']);
//            $this->view->chosenBuyer = $formParams['TNID'];
//        }
//
//        $this->view->statRfqsSentToMatch = $internalKpis->statRfqsSentToMatch();
//        $this->view->statRfqsSentFromMatch = $internalKpis->statRfqsSentFromMatch();
//        $this->view->statQuotesRecieved = $internalKpis->statTotalQuotesBack();
//        $this->view->statRfqsWithQuote = $internalKpis->statCountRFQsWithQuotes();
//        $this->view->statAvgTimeToQuote = $internalKpis->statAvgTimeForQuote();
//        $this->view->statTotalMatchPOs = $internalKpis->statTotalMatchPOs();
//        $this->view->statTotalPOs = $internalKpis->statTotalPOs();
//
//        $this->view->buyers = $buyers;
    }

    
    /**
     * Reworked by Yuriy Akopov on 2016-10-10, S18245
     *
     * Function to provide stats and links to previously sent RFQs from Match.
     * Includes both those forwarded, and those removed from the inbox manually.
     *
     * @throws  Myshipserv_Exception_MessagedException
     */
    public function sentBoxAction() 
    {
        $this->_helper->layout->setLayout('default');

        if ($this->user->canPerform('PSG_ACCESS_MATCH_INBOX') === false) {
        	//throw new Myshipserv_Exception_MessagedException("You are not allowed to access this page as specified within your group: " . $this->user->getGroupName(), 401);
             throw new Myshipserv_Exception_MessagedException("Access denied: this page is only available to ShipMates", 401);
        }

        $filters  = $this->_getParam('filter');
	    if (!is_array($filters)) {
	    	$filters = array();
	    }

	    $dateParams = array('MATCH_RFQ_DATE_FROM', 'MATCH_RFQ_DATE_TO');
	    foreach ($dateParams as $param) {
			if (array_key_exists($param, $filters)) {
				if (strlen($filters[$param])) {
					try {
						$filters[$param] = new DateTime($filters[$param]);
						continue;
					} catch (Exception $e) {
						throw new Myshipserv_Exception_MessagedException("Invalid date format " . $filters[$param]);
					}
				}
			}

			$filters[$param] = null;
	    }

        $orderBy  = $this->_getNonEmptyParam('orderBy', 'RFQ_ID');
	    $orderDir = $this->_getNonEmptyParam('orderDir', 'desc');

		$select = Shipserv_Match_Manager::getMatchOutboxSelect($filters, $orderBy, $orderDir);
	    $paginator = Zend_Paginator::factory($select);

	    $pageNo = $this->_getNonEmptyParam('pageNo', 1);
	    $paginator->setCurrentPageNumber($pageNo);
	    $paginator->setItemCountPerPage(20);

	    $results = $paginator->getCurrentItems();
	    $extendedResults = array();
	    foreach ($results as $row) {
	    	$extendedResults[] = Shipserv_Match_Manager::extendMatchOutboxRow($row);
	    }

        $this->view->rows = $extendedResults;
        $this->view->matchUrl = trim(Myshipserv_Config::getMatchUrl(), '/');

	    $this->view->filters  = $filters;
	    $this->view->orderBy  = $orderBy;
	    $this->view->orderDir = $orderDir;

	    $helper = new Myshipserv_View_Helper_Paginator();
	    $this->view->pageMarks = $helper->getPaginatorMarks($paginator);
	    $this->view->pageNo = $paginator->getCurrentPageNumber();
	    $this->view->pageSize = $paginator->getItemCountPerPage();
    }

    /**
     * Main method to show an RFQ and its tags. This is a temporary placeholder as this will eventually be automated.
     * @throws exception
     */
    public function matchAction() 
    {
        if (!Shipserv_Helper_Security::isValidInternalIP(Shipserv_Helper_Security::getRealUserIP()) && $this->ipRestrictionInPlace) {
            throw new Myshipserv_Exception_MessagedException("Access Denied: " . Shipserv_Helper_Security::getRealUserIP(), 401);
            die();
        }

        $this->_helper->layout->setLayout('blank');

        $db = $this->getInvokeArg('bootstrap')->getResource('db');

        $params = $this->_getAllParams();

        if (isset($params['txtRfqid'])) {

            //TODO: Pull from form
            $match = new Shipserv_Match_Match((int) trim($params['txtRfqid']));
            //ini_set('memory_limit', '300M');
            //TODO: REfactor to use existing RFQ class
            $rfq = new Shipserv_Tradenet_RequestForQuote((int) trim($params['txtRfqid']), $db);
            $rfqSuppliers = $rfq->rfq_suppliers;
            foreach ($rfqSuppliers as $rfqSupplier) {
                $supplier = Shipserv_Supplier::fetch($rfqSupplier, $db);
                $rfqSuppliersDetails[] = array('tnid' => $rfqSupplier, 'name' => $supplier->name, 'url' => $supplier->getUrl());
                unset($supplier);
            }

            $results = $match->getMatchedSuppliers();

            foreach ($results as $result) {
                $sql = "Select spb_name from Supplier_Branch where spb_branch_code = :tnid ";
                $params = array('tnid' => $result['supplier']);
                //$supplier = $db->fetchAll($sql,$params);
                $supplier = Shipserv_Supplier::fetch($result['supplier'], $db);
                $country = $supplier->countryName;
                $categories = $supplier->categories;
                $output[] = array('name' => $supplier->name, 'location' => $country, 'categories' => $categories, 'url' => $supplier->getUrl(), 'tnid' => $result['supplier'], 'score' => $result['score'], 'comment' => $result['comment']);
            }

            $this->view->rfqTags = $match->getCurrentTags();
            $this->view->rfqBrands = $match->getCurrentBrands();
            $this->view->rfqCategories = $match->getCurrentCategories();
            unset($match);
            $this->view->rfqSuppliers = $rfqSuppliersDetails;
            unset($rfqSuppliersDetails);

            $this->view->rfqid = trim($params['txtRfqid']);
            $this->view->rfq = $rfq;
            unset($rfq);
            $this->view->results = $output;
            unset($output);
            //ini_set('memory_limit', '138M');
        }
    }

    
    public function updateAlgoValuesAction() 
    {
        // page content is now provided by an independent Match Engine application which will output to an iframe
        Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        if (!Myshipserv_Helper_Session::getActiveCompanyNamespace()->id) {
            throw new Myshipserv_Exception_MessagedException("You are not allowed to perform this action.", 401);
        }

        $this->view->matchSettingsUrl = trim(Myshipserv_Config::getMatchUrl(), '/') . '/config/match-settings';
        $this->view->user = Shipserv_User::isLoggedIn();
    }

    
    public function updateMatchStopwordsAction() 
    {
        
        // page content is now provided by an independent Match Engine application which will output to an iframe
        Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        if (!Myshipserv_Helper_Session::getActiveCompanyNamespace()->id) {
            throw new Myshipserv_Exception_MessagedException("You are not allowed to perform this action.", 401);
        }

        $this->view->matchStopwordsUrl = trim(Myshipserv_Config::getMatchUrl(), '/') . '/config/match-stopwords';
        $this->view->user = Shipserv_User::isLoggedIn();
    }

    
    public function matchStatsAction() 
    {
    	$this->abortIfNotShipMate();

        if (!Shipserv_Helper_Security::isValidInternalIP(Shipserv_Helper_Security::getRealUserIP()) && $this->ipRestrictionInPlace) {
            throw new Myshipserv_Exception_MessagedException("Access Denied: " . Shipserv_Helper_Security::getRealUserIP(), 401);
            die();
        }
        $db = $this->getInvokeArg('bootstrap')->getResource('db');

        $sql1 = "Select count(*) as r from purchase_order where ord_qot_internal_ref_no in
                (Select qot_internal_ref_no from quote where qot_rfq_internal_ref_no in
                    (select rfq_internal_ref_no from request_for_quote where rfq_ref_no in
                        (select rfq_ref_no from request_for_quote where rfq_internal_ref_no in
                            (select rqr_rfq_internal_ref_no from rfq_quote_relation where rqr_spb_branch_code = 999999)
                        )
                    )
                )";
        $result = $db->fetchAll($sql1);
        $this->view->pocount = $result[0]['R'];

        $query = "Select Count(*) as r from rfq_response where rfp_rfq_internal_ref_no in (Select rfq_internal_ref_no from request_for_quote where rfq_byb_branch_code = 11107) and rfp_sts = 'ACC'";
        $result = $db->fetchAll($query);
        $this->view->rfqToMatch = $result[0]['R'];

        $query = "Select count(*) as r from request_for_quote where rfq_byb_branch_code = 11107 and rfq_sourcerfq_internal_no is not null";
        $result = $db->fetchAll($query);
        $this->view->rfqFromMatch = $result[0]['R'];

        $query = "Select count(*) as r from quote where qot_total_cost > 0 and qot_byb_branch_code = 11107";
        $result = $db->fetchAll($query);
        $this->view->quoteCount = $result[0]['R'];

        $query = "Select Count(*) as r from rfq_response where rfp_rfq_internal_ref_no in (Select rfq_internal_ref_no from request_for_quote where rfq_byb_branch_code = 11107) and rfp_sts = 'DEC'";
        $result = $db->fetchAll($query);
        $this->view->rfqDeclines = $result[0]['R'];

        $query = "Select count(*) as r from request_for_quote r where rfq_byb_branch_code = 11107 and RFQ_INTERNAL_REF_NO in (Select qot_rfq_internal_ref_no from Quote where quote.qot_byb_branch_code = 11107 and qot_rfq_internal_ref_no = r.rfq_internal_ref_no and quote.qot_total_cost > 0)";
        $result = $db->fetchAll($query);
        $this->view->rfqQuotedRFQs = $result[0]['R'];
    }

    
    public function sendQuoteToBuyerAction() 
    {
        if (!Shipserv_Helper_Security::isValidInternalIP(Shipserv_Helper_Security::getRealUserIP()) && $this->ipRestrictionInPlace) {
            throw new Myshipserv_Exception_MessagedException("Access Denied: " . Shipserv_Helper_Security::getRealUserIP(), 401);
            die();
        }

        $this->_helper->layout->setLayout('empty');
        $db = $this->getInvokeArg('bootstrap')->getResource('db');
        $params = $this->_getAllParams();

        $nm = new Myshipserv_NotificationManager($db);

        $nm->stopSending();

        if ($this->getRequest()->getParam('mode') == 'html')
            $nm->returnAsHtml();

        if ($this->getRequest()->getParam('send') == 1) {
            $nm->startSending();
        }

        $result = $nm->sendSSMatchQuoteToBuyer($params['quoteId']);

        if ($this->getRequest()->getParam('mode') == 'html') {
            echo $result[0];
            die();
        } else {
            $response = new Myshipserv_Response("200", "OK");
            $this->_helper->json((array)$response);
        }
    }

    
    public function displayMatchQuotesAction() 
    {
        if (!Shipserv_Helper_Security::isValidInternalIP(Shipserv_Helper_Security::getRealUserIP()) && $this->ipRestrictionInPlace) {
            throw new Myshipserv_Exception_MessagedException("Access Denied: " . Shipserv_Helper_Security::getRealUserIP(), 401);
            die();
        }

        $db = $this->getInvokeArg('bootstrap')->getResource('db');

        $sql = "
            Select
                  case when rfq1.rfq_sourcerfq_internal_no is not null then
                    (select rfq_byb_branch_code from request_for_quote where rfq_internal_ref_no = rfq1.rfq_sourcerfq_internal_no)
                  else
                    rfq_byb_branch_code
                  end as Buyer,
                  rfq_ref_no,
                  rfq1.rfq_updated_date,
                  Case when qot_byb_branch_code = 11107
                    then 'Match'
                    else 'Original'
                  End as Quote_Type,
                  qot_byb_branch_code,
                  qot_submitted_date,
                  qot_spb_branch_code,
                  round(qot_total_cost/currency.curr_exchange_rate) as cost,
                  qot_terms_of_payment,
                  spb_country as Supplier_location,
                  po.ord_submitted_date,
                  po.ord_currency,
                  qot_currency,
                  round((Select Count(*) from quote_line_item where qli_qot_internal_ref_no = q1.qot_internal_ref_no and qli_unit_cost > 0)/(Select Count(*) from quote_line_item where qli_qot_internal_ref_no = q1.qot_internal_ref_no) * 100) as PercentageQuotedItems,
                  round(po.ord_total_cost/currency.curr_exchange_rate) as Ord_Cost
            from
                request_for_quote rfq1,
                quote q1 left outer join purchase_order po on q1.qot_internal_ref_no = po.ord_qot_internal_ref_no,
                currency,
                supplier_branch
            where
                rfq1.rfq_internal_ref_no = qot_rfq_internal_ref_no
                and supplier_branch.spb_branch_code = qot_spb_branch_code
                and q1.qot_currency = currency.curr_code
                and qot_rfq_internal_ref_no in (select rfq_internal_ref_no from request_for_quote where rfq_ref_no in (select rfq_ref_no from request_for_quote r2, rfq_quote_relation rqr where rqr_rfq_internal_ref_no = r2.rfq_internal_ref_no and rqr.rqr_spb_branch_code = 999999))
                and qot_total_cost > 0
                and rfq1.rfq_byb_branch_code = q1.qot_byb_branch_code
            Order by
                Qot_Submitted_date,
                RFQ_REF_NO,
                Quote_Type
        ";
        $results = $db->fetchAll($sql);
        $this->_helper->layout->setLayout('blank');
        $this->view->results = $results;
    }

    public function testBuyerAlertAction() {
        Shipserv_Match_BuyerAlert::processWaitingMatchQuotes();
    }

    public function testStatsAction() {
        $stats = new Shipserv_Match_Stats(10477);

        $numQuotes = $stats->getCountQuotesFromMatch($stats->getReportPeriod2Month());
        $numPOs = $stats->getCountPOsMatchSuppliers($stats->getReportPeriod2Month());
        $numRfqs = $stats->getCountRFQSToMatch($stats->getReportPeriod2Month());
        $percentageRFQs = $stats->getPercentageRFQSToMatch($stats->getReportPeriod2Month());

        $results = array($numQuotes, $numPOs, $numRfqs, $percentageRFQs);
        $this->view->results = $results;
    }

    public function matchHistogramTestAction() {

        $db = $this->getInvokeArg('bootstrap')->getResource('db');

        $results = array();

        if (!empty($_POST)) {
            $qword = $_POST['query'];
            $sql = "
             SELECT mkh_keyword,
                mkh_cat_id,
                p.name,
                COUNT(mkh_cat_id)
              FROM match_keyword_cat_histogram,
                product_category p
              WHERE mkh_cat_id = p.id
              AND mkh_keyword  = :qword
              AND p.id not in (5,9,49)
              GROUP BY mkh_keyword,
                mkh_cat_id,
                p.name
              ORDER BY COUNT(mkh_cat_id) Desc
            ";
            $params = array('qword' => $qword);

            $results = $db->fetchAll($sql, $params);
        }

        $this->view->results = $results;
    }

    public function generateHistogramAction() {
        if (!Shipserv_Helper_Security::isValidInternalIP(Shipserv_Helper_Security::getRealUserIP()) && $this->ipRestrictionInPlace) {
            throw new Myshipserv_Exception_MessagedException("Access Denied: " . Shipserv_Helper_Security::getRealUserIP(), 401);
            die();
        }

        $proc = new Shipserv_Match_Processor();

        $proc->lineitemCategorisation();
    }

    public function indexImpaAction() {
        if (!Shipserv_Helper_Security::isValidInternalIP(Shipserv_Helper_Security::getRealUserIP()) && $this->ipRestrictionInPlace) {
            throw new Myshipserv_Exception_MessagedException("Access Denied: " . Shipserv_Helper_Security::getRealUserIP(), 401);
            die();
        }

        $processor = new Shipserv_Match_Processor();

        $results = $processor->indexImpaCatalogue();
    }

    public function prototypeSegmentAction() {
        //First get the categories and build the heirarchy:
        $sql = "Select * from product_category where parent_id is null order by name";
        $db = Shipserv_Helper_Database::getDb();

        $reporting = Shipserv_Helper_Database::getSsreport2Db();

        $parentCategories = $db->fetchAll($sql);

        foreach ($parentCategories as &$cat) {
            $sql = "SELECT DISTINCT ID,
                    name,
                    LEVEL
                  FROM product_category
                    START WITH id       = :catid
                    CONNECT BY prior id = parent_id
                  ORDER BY level, Name";
            $params = array('catid' => $cat['ID']);

            $subcats = $db->fetchAll($sql, $params);
            $cat['subcats'] = $subcats;
        }

        if (!empty($_POST)) {
            $selectedCat = $_POST['category'];
            $this->view->selectedCat = $selectedCat;

            $query = "SELECT COUNT(*)                   AS LI_COUNT,
                        SUM(LIC_NORM_VALUE)             AS TOTAL,
                        COUNT(DISTINCT lic_supplier_id) AS Supps,
                        COUNT(DISTINCT lic_buyer_id)    AS Buyers,
                        Count(Distinct lic_doc_id)      AS DOCS
                      FROM
                        (SELECT *
                        FROM ssreport2.line_item_category_info
                        WHERE
                        lic_doc_type = :docType
                        and (lic_cat_1 IN
                          (SELECT ID
                          FROM product_category@live_link
                            START WITH id       =  :cat
                            CONNECT BY prior id = parent_id
                          )
                        OR lic_cat_2 IN
                          (SELECT ID
                          FROM product_category@live_link
                            START WITH id       = :cat
                            CONNECT BY prior id = parent_id
                          )
                        OR lic_cat_3 IN
                          (SELECT ID
                          FROM product_category@live_link
                            START WITH id       = :cat
                            CONNECT BY prior id = parent_id
                          )
                        ))";
            $params = array('cat' => $selectedCat, 'docType' => 'QOT');
            $qotresults = $reporting->fetchRow($query, $params);

            $params = array('cat' => $selectedCat, 'docType' => 'ORD');
            $ordresults = $reporting->fetchRow($query, $params);

            $this->view->qotresults = $qotresults;
            $this->view->ordresults = $ordresults;
        }

        $this->view->cats = $parentCategories;
    }

	/*
	public function debugAction() {
		$rfq = Shipserv_Rfq::getInstanceById(15965863);
		$supplier = Shipserv_Supplier::getInstanceById(52323);
		$rfq->addRecipientSupplier($supplier);
		var_dump('lol');
		die();
	}
	*/
}
