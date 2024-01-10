<?php

class Reports_ReportController extends Myshipserv_Controller_Action {

    /**
     * Initialise the controller - set up the context helpers for AJAX calls
     * @access public
     */
    public function init() {
        parent::init();
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                ->addActionContext('port', 'json')
                ->addActionContext('brand', 'json')
                ->addActionContext('category', 'json')
                ->addActionContext('store', 'json')
                ->addActionContext('supplier-impression', 'json')
                ->addActionContext('general-impression', 'json')
                ->addActionContext('send-email-summary-to-customer', 'json')
                ->initContext();

    }

    private function getActiveCompany()
    {
    	$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
    	return $sessionActiveCompany;
    }

	/**
	 * Data webservice behind supplier-centric GMV report
	 * Unlike many other services, returns CSV data rather than JSON
	 *
	 * Refactored and changed by Yuriy Akopov on 2016-08-09, S17177
	 *
	 * @throws  Myshipserv_Exception_MessagedException
	 * @throws  Exception
	 */
    public function gmvDataAction()
    {
    	// security checks
	    $user = $this->getUser();
	    if (($user->canPerform('PSG_VIEW_BILLING_REPORT') === false) and ($user->canPerform('PSG_GMV_SUPPLIER') === false)) {
		    if (!isset($_GET['logmeinplease'])) {
			    throw new Myshipserv_Exception_MessagedException(
			    	"You are not allowed to access this page as specified within your group: " . $user->getGroupName(),
				    500
			    );
		    }
	    }

        $params = $this->params;

	    $supplier = Shipserv_Supplier::getInstanceById($params['tnid'], "", true);
	    if (strlen($supplier->tnid) === 0) {
	    	throw new Myshipserv_Exception_MessagedException("Supplier ID " . $params['tnid'] . " is invalid", 500);
	    }

    	if ((strlen($params['datefrom']) === 0) or (strlen($params['dateto']) === 0)) {
		    throw new Myshipserv_Exception_MessagedException("Start or end date not specified");
	    }

	    try {
		    $dateFrom = new DateTime($params['datefrom']);
		    $dateTo = new DateTime($params['dateto']);
	    } catch (Exception $e) {
	    	throw new Myshipserv_Exception_MessagedException(
	    		"Date interval " . $params['datefrom'] . ' - ' . $params['dateto'] . " is invalid", 500
		    );
	    }

	    // caching is done on ReportService side
	    /*
        $memcacheObj = new Shipserv_Memcache();
        if (!is_object($memcache = $memcacheObj->getMemcache())) {
	        throw new Exception("Failed to access memcached");
        }

	    $cacheKey = '_' . $params['tnid'] . "_" . $params['datefrom'] . "-" . $params['dateto'];
	    */

	    // unlike max_execution_time down below, this is only set once making it a blanket coverage
	    // this is because I don't know if it is safe to shrink the limit when we might have more memory occupied than
	    // allowed by the previous limit because of garbage collector not cleaning up yet
	    $memory_limit = ini_set("memory_limit", "-1");
	    $max_execution_time = ini_set('max_execution_time', 0);
        $report = $supplier->getGMVReport($dateFrom, $dateTo);
	    ini_set('max_execution_time', $max_execution_time);

        if (isset($params['type']) and ($params['type'] === 'csv')) {
            $data = $report->generateCsvForGmvReport();

        } else {
			$rawData = $report->getDataByBuyerGroupByParent();

            $prepData = $rawData['parent'];
            $data = array();

            $replaceKeys = array(
                'internal-ref-no' => 'internalRefNo',
                'original-no'     => 'origRefNo'
            );

            $totalTrans = $report->getTotalTransaction();

            foreach ((array) $prepData as $topKey => $subArrays) {
                $data[$topKey]['ID']         = $topKey;
                $data[$topKey]['NAME']       = $rawData['company'][$topKey];
                $data[$topKey]['CHILDREN']   = $subArrays;
                $data[$topKey]['totalTrans'] = $totalTrans;

                foreach ($data[$topKey]['CHILDREN'] as $subKey => $content) {
                    $data[$topKey]['CHILDREN'][$subKey]['ID'] = $subKey;

                    foreach ($content['DATA'] as $dataKey => $childData) {
                        foreach ($childData as $childKey => $item) {
                            if (in_array($childKey, array_keys($replaceKeys))) {
                                $data[$topKey]['CHILDREN'][$subKey]["NAME"] = $rawData['company'][$subKey];
                                $data[$topKey]['CHILDREN'][$subKey]['DATA'][$dataKey][$replaceKeys[$childKey]] = $item;

                                unset($data[$topKey]['CHILDREN'][$subKey]['DATA'][$dataKey][$childKey]);
                            }
                        }
                    }
                }
            }

            $data = array_values($data);
            $data[0]['supplier'] = $supplier->toArray('gmv');
        }

        // at this point the content is ready and we need to serve it to user

	    if (isset($params['asFile'])) {
		    // trigger download
		    $attachmentFileName = date('Ymd') . 'gmvreport.csv';
		    $this->getResponse()
			    ->setHeader('Pragma', 'public')
			    ->setHeader('Expires', '0')
			    ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
			    ->setHeader('Cache-Control', 'private')
			    ->setHeader('Content-Type', 'application/octet-stream')
			    ->setHeader('Content-Disposition', 'attachment; filename="' . $attachmentFileName . '";')
			    ->setHeader('Content-Transfer-Encoding', 'binary')
		    ;

	        $this->_helper->layout->disableLayout();
	        $this->_helper->viewRenderer->setNeverRender();

	        echo $data;

	    } else {
		    // outputs the string CSV content we've built with escaped double quotation marks
		    $this->_helper->json((array)$data);
	    }
    }

	/**
	 * Yuriy Akopov on 2016-08-09, S17177: This action appears to be redundant (the only use case is commented out)
	 *
	 * @throws Myshipserv_Exception_MessagedException
	 */
    public function gmvNewDataAction()
    {
        set_time_limit(0);
        ini_set("memory_limit", "-1");
        $user = $this->getUser();

        // check if user can see/access this page
        if ( $user->canPerform('PSG_VIEW_BILLING_REPORT') === false ) {
            if (!isset($_GET['logmeinplease'])) {
                throw new Myshipserv_Exception_MessagedException("You are not allowed to access this page as specified within your group: " . $user->getGroupName(), 403);
            }
        }

        //checking if the url is complete
        if(isset($this->params['datefrom']) && isset($this->params['dateto']) && isset($this->params['tnid'])) {

                //the lauout and view have to be disabled to send csv
               $this->_helper->layout->disableLayout();
               $this->_helper->viewRenderer->setNeverRender();

               //The attachent file name will be the current date
               $attachmentFileName = date('Ymd').'gmvreport.csv';
               $this->getResponse()
                   ->setHeader('Pragma', 'public')
                   ->setHeader('Expires', '0')
                   ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0')
                   ->setHeader('Cache-Control', 'private')
                   ->setHeader('Content-Type', 'application/octet-stream')
                   ->setHeader('Content-Disposition', 'attachment; filename="'.$attachmentFileName.'";')
                   ->setHeader('Content-Transfer-Encoding', 'binary');

               //Getting an instance of CSV report object, and setting initial values
               $report = Shipserv_Report_GmvCsvReport::getInstance($this->params['tnid'], $this->params['datefrom'], $this->params['dateto']);

               //echo the actual result
               echo  $report->generateReport();

        }
        else {
            throw new Myshipserv_Exception_MessagedException("Tnid, Start or end date not specified");
        }
    }

    public function supplierResponseRateAction()
    {
        $this->abortIfNotShipMate();

    	$report = new Shipserv_Report_Dashboard_Supplier_ResponseRate;
    	$oldTimeOut = ini_set('max_execution_time', 0);
    	$report->setParams($this->params);
    	$this->view->startDate = $report->startDate;
    	$this->view->endDate = $report->endDate;

    	if( $this->params['fromDate'] != "" ){
            $this->slowLoadingPage();
    		$this->view->data = $report->getData();
    	}
    }

	public function engagementAction()
    {
    	$this->abortIfNotShipMate();
		$this->_helper->layout->setLayout('default');

    	if( $this->params['yyyy'] == null || $this->params['yyyy'] == "" )
    	{
    		$this->params['yyyy'] = date('Y');
    	}
    	$report = new Shipserv_Report_Dashboard_Engagement;

    	//shipserv.pages.report.dashboard.engagement.buyerTnid.exclusion
    	$tnidToExclude = $this->config['shipserv']['pages']['report']['dashboard']['engagement']['buyerTnid']['exclusion'];
    	if( $tnidToExclude != "" )
    	{
    		$tnids = explode(",", $tnidToExclude);
    		$report->setBuyerTnidToExclude($tnids);
    	}


    	$report->setParams($this->params);
        $this->slowLoadingPage();

    	$this->view->data = $report->getData();
    }

    public function pagesDashboardAction()
    {
    	$this->abortIfNotShipMate();
    	/*
    	 if( $this->user->canPerform('PSG_ACCESS_MATCH') === false )
    	 {
    	throw new Myshipserv_Exception_MessagedException('You are not allowed to access this page as specified within your group: ' . $this->user->getGroupName(), 200);
    	}
    	*/
    	$this->_helper->layout->setLayout('default');

    	if( $this->params['yyyy'] == null || $this->params['yyyy'] == "" )
    	{
    		$this->params['yyyy'] = date('Y');
    	}


    	$report = new Shipserv_Report_Dashboard_Pages;
    	$report->setParams($this->params);
        $this->slowLoadingPage();

    	$this->view->data = $report->getData();
    }

    public function ssoDashboardAction()
    {
    	$this->abortIfNotShipMate();
    	/*
    	 if( $this->user->canPerform('PSG_ACCESS_MATCH') === false )
    	 {
    	throw new Myshipserv_Exception_MessagedException('You are not allowed to access this page as specified within your group: ' . $this->user->getGroupName(), 200);
    	}
    	*/
    	$this->_helper->layout->setLayout('default');

    	if( $this->params['yyyy'] == null || $this->params['yyyy'] == "" )
    	{
    		$this->params['yyyy'] = date('Y');
    	}


    	$report = new Shipserv_Report_Dashboard_Onboard;
    	$report->setParams($this->params);
        $this->slowLoadingPage();
        $this->slowLoadingPage();

    	$this->view->data = $report->getData();
    }

    public function ssoInstallationDashboardAction()
    {
    	$this->abortIfNotShipMate();
    	$this->_helper->layout->setLayout('default');

    	$report = new Shipserv_Report_Dashboard_Onboard;
    	$report->setParams($this->params);
        $this->slowLoadingPage();

    	$this->view->data = $report->getInstallationStatistics();
    }

    /**
     * Page to see GMV
     * @throws Myshipserv_Exception_MessagedException
     */
    public function gmvAction()
    {
    	$this->_helper->layout->setLayout('default');

    	$params = $this->params;
    	$config = $this->config;
    	$user = $this->getUser();

        // check if user can see/access this page
    	if ( $user->canPerform('PSG_VIEW_BILLING_REPORT') === false && $user->canPerform('PSG_GMV_SUPPLIER') === false ) {
    		if (!isset($_GET['logmeinplease'])) {
    			//throw new Myshipserv_Exception_MessagedException("You are not allowed to access this page as specified within your group: " . $user->getGroupName() );
                throw new Myshipserv_Exception_MessagedException("Access denied: this page is only available to ShipMates", 403);
    		}
    	}

        if ($user) {
            $user->logActivity(Shipserv_User_Activity::GMV_SUPPLIER_CLICK, 'PAGES_USER', $user->userId, $user->email);
        }

        if( $params['datefrom'] != "" ){
            $df = Shipserv_DateTime::fromString($params['datefrom']);
            $params['datefrom'] = $df->format('Y-m-d');
        }

        if( $params['dateto'] != "" ){
            $dt = Shipserv_DateTime::fromString($params['dateto']);
            $params['dateto'] = $dt->format('Y-m-d');

        }

    	$this->view->tnid = $params["tnid"] ? $params["tnid"] : $this->getActiveCompany()->id;
    	$this->view->dateFrom = $params["datefrom"] ? $params["datefrom"] : date("Y-m-d", strtotime("-1 month"));
    	$this->view->dateTo = $params["dateto"] ? $params["dateto"] : date("Y-m-d");

        // override
    	$params['tnid'] = $this->view->tnid;
    	$supplier = Shipserv_Supplier::getInstanceById($params['tnid']);
        $this->view->params = $params;
        $this->view->supplier = $supplier;
    }

    public function invalidTxnPickerAction()
    {
    	$this->_helper->layout->setLayout('default');
    	$ssreport2 = Shipserv_Helper_Database::getSsreport2Db();
    	$user = $this->getUser();

    	if ( $user->canPerform('PSG_ACCESS_INV_TXN_TOOLS') === false )
    	{
            throw new Myshipserv_Exception_MessagedException("You are not allowed to access this page as specified within your group: " . $user->getGroupName(), 403);
    	}

    	if( $this->params['a'] == 'Set ORD as Invalid' || $this->params['a'] == 'Set POC as Invalid'  )
    	{
    		$docType = strstr($this->params['a'], 'ORD')?'ORD':'POC';
    		Shipserv_InvalidTransaction::store($this->params['docId'], $docType, $this->params['comments']);
    		$this->redirect('/reports/invalid-txn-picker?h=' . rand(0, 10000000000));
    	}
    	else if( $this->params['a'] == 'Continue')
    	{

    		$invalidTxn = Shipserv_InvalidTransaction::getInstancesByOrdInternalRefNo($this->params['docId'], $this->params['docType']);
    		$invalidTxn = $invalidTxn[0];
    		$invalidTxn->delete();
    	}

    	$this->view->invalidTxn = Shipserv_InvalidTransaction::getInstances();
    	$this->view->searchResult = Shipserv_InvalidTransaction::getInstancesByOrdInternalRefNo($this->params['documentInternalRefNo']);
    	$this->view->params=$this->params;
    	$this->user = $this->user;
    }


    public function billingAction()
    {
    	$this->_helper->layout->setLayout('default');

    	$params = $this->params;
    	$config = Zend_Registry::get('options');
    	$user = $this->getUser();

    	if ( $user->canPerform('PSG_VIEW_BILLING_REPORT') === false ) {
    		if (!isset($_GET['logmeinplease'])) {
    			//throw new Myshipserv_Exception_MessagedException("You are not allowed to access this page as specified within your group: " . $user->getGroupName() );
                 throw new Myshipserv_Exception_MessagedException("Access denied: this page is only available to ShipMates", 403);
                
    		}
    	}

    	if( $params['a'] == "Generate"  )
    	{
    		$params['month'] = (int) $params['month'];

    		$upperDate = new DateTime();
    		$lowerDate = new DateTime();
    		$upperDate->setDate($params['year'], $params['month'], cal_days_in_month(CAL_GREGORIAN, $params['month'], $params['year']));
    		$lowerDate->setDate($params['year'], $params['month'], 1);

    		$key = md5($params['month'] . $params['year'] . $params['supplier']);

    		if( $params['supplier'] == "all" )
    		{
    			$this->suppliers = Shipserv_Supplier::getSuppliersOnValueBasedPricing();
    		}
    		else
    		{
    			$this->suppliers = array(Shipserv_Supplier::getInstanceById($this->getActiveCompany()->id));
    		}

    		$data = array();
    		foreach( (array) $this->suppliers as $supplier )
    		{
    			$info['supplier'] = $supplier;
    			$info['data'] = $supplier->getValueBasedEventForBillingReport($params['month'], $params['year']);
    			$data[] = $info;
    		}

    		$this->view->data = $data;
    		$this->view->period = $period;
    		$this->view->params = $params;
    		$this->view->key = $key;

    		$dataToBeSerialised = array("startDate" => $lowerDate->format("d M y"), 'endDate' => $upperDate->format("d M y"), "data" => $data);

    		// storing this on the file
    		exec("rm -fR /tmp/billing-data-" . session_id() . "*");
    		$fileStorage = "/tmp/billing-data-" . session_id() . $key;
    		file_put_contents($fileStorage, serialize($dataToBeSerialised));
    		$this->view->mcStatus = file_exists($fileStorage);
    	}
    	else if( $params['a'] == 'export')
    	{
    		$fileStorage = "/tmp/billing-data-" . session_id() . $params['key'];
    		$string = file_get_contents($fileStorage);
    		$data = unserialize($string);


    		header('Content-Encoding: UTF-8');
    		header('Content-type: text/csv; charset=UTF-8');
    		header("Content-Disposition: attachment; filename=" . ( ($params['t'] == 'gmv') ? "gmv-detail-for-" . $params['tnid'] : "biling-report-for-SF" ) . ".csv");
    		header("Pragma: no-cache");
    		header("Expires: 0");

    		iconv_set_encoding("internal_encoding", "UTF-8");
    		iconv_set_encoding("output_encoding", "UTF-8");
    		ob_start();
    		if( $params['t'] == 'gmv' )
    		{
    			foreach($data['data'] as $supplierRow)
    			{
    				if( $params['tnid'] == $supplierRow['supplier']->tnid)
    				{
    					$d = $supplierRow['supplier']->getGMVBreakdownForBillingReport($params['month'], $params['year']);
    					$supplierRow['data']['gmv-detail'] = $d['gmv-detail'];
    					$supplierRow['data']['gmv-detail-total'] = $d['gmv-detail-total'];

    					echo "SIR value=" .  $supplierRow['data']['gmv-detail-total'] . "\n";

    					echo "Buyer Tnid, Buyer name, Internal ref no, Original no, Reference no, Vessel name, Submitted date,  Total cost, Currency, Currency rate, Document Type, Total Cost (USD),Adjusted cost, Invalid Currency?, Invalid Transaction\n";
    					foreach((array)$supplierRow['data']['gmv-detail'] as $row)
	    				{
	    					$data = array();
		    				$data[] = $row['buyer-tnid'];
		    				$data[] = $row['buyer-name'];
		    				$data[] = $row['internal-ref-no'];
		    				$data[] = $row['original-no'];
		    				$data[] = iconv("UTF-8","ISO-8859-1",$row['ref-no']);//utf8_encode($row['ref-no']);
	    					$data[] = $row['vessel-name'];
		    				$data[] = $row['submitted-date'];
		    				$data[] = $row['total-cost'];
		    				$data[] = $row['currency'];
		    				$data[] = $row['currency-rate'];
		    				$data[] = $row['doc-type'];
		    				$data[] = $row['total-cost-usd'];
		    				$data[] = $row['adjusted-cost'];
		    				$data[] = $row['invalid-currency'];
		    				$data[] = $row['is-txn-invalid'];

		    				echo $this->arrayToCsv($data);
	    					;
    					}
    				}
    			}
    		}
    		else
    		{
    			echo "Billing report for:, " . $data['data'][0]['data']['billingPeriod'] . "\n";
    			echo "TNID, Start date, End date, GMV, Unactioned RFQ, Unique contact view, Page 1 search impression\n";

	    		foreach($data['data'] as $row)
	    		{
	    			if( $row['data']['gmv'] != Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED && $row['data']['unactioned'] != Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED && $row['data']['uniqueContactView'] != Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED && $row['data']['searchImpression'] != Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED)
	    			{
		    			$data = array();
		    			$data[] = $row['supplier']->tnid;
		    			$data[] = $row['data']['startDate'];
		    			$data[] = $row['data']['endDate'];
		    			$data[] = $row['data']['gmv'];
		    			$data[] = $row['data']['unactioned'];
		    			$data[] = $row['data']['uniqueContactView'];
		    			$data[] = $row['data']['searchImpression'];
		    			echo $this->arrayToCsv($data);
	    			}
	    		}
    		}
    		$content = ob_get_contents();
    		ob_end_clean();

    		$content = str_replace(Shipserv_Report_MonthlyBillingReport::NO_DATA, "0", $content);
    		//$content = str_replace(Shipserv_Report_MonthlyBillingReport::NOT_TRANSITIONED, "0", $content);
    		echo $content;
    		die();
    	}
    }


    /**
     * Interface for SIR
     */
    public function indexAction( $forceNew = false)
    {
        $tnid = null;
        $suppliers = array();
        $params = $this->params;
        $config = $this->config;
        $isShipMate =false;
        if ($user = $this->getUser()) {
            $this->view->user = $user;
            $isShipMate = $user->isLoggedIn()->isShipservUser();
        }

        if ($user && $user->isShipservUser() && !$user->canPerform('PSG_ACCESS_SIR')) {
            throw new Myshipserv_Exception_MessagedException('You are not allowed to access SIR as specified within your group: ' . $user->getGroupName(), 403);
        }
        $this->view->isShipMate = $isShipMate;

        // get tnid from the textfield
        if (isset($params["tnid"]) && $params["tnid"] != "") {
            $tnid = $params["tnid"];
            // DEV-1610 Redirect to selected TNID
            if ((int)$this->getActiveCompany()->id !== (int)$tnid) {
                $this->redirect('/user/switch-company?tnid=' . (int)$tnid . '&redirect=' . urlencode('/reports/'));
            }

        }

        // for shipserv user -- check the IP address, makesure it's white listed
        if ($user->isShipservUser())
        {
            if ( $user->canPerform('PSG_ACCESS_SIR') === false && $user->isPartOfCompany($tnid) === false ) {
                if (!isset($_GET['logmeinplease'])) {
                	throw new Myshipserv_Exception_MessagedException("You are not allowed to access SIR as specified within your group: " . $user->getGroupName(), 403);
                }
            }
        }

        // check if it's numeric
        if (isset($params["tnid"]) && !is_numeric($params["tnid"])) {
            throw new Myshipserv_Exception_MessagedException("Please check your TradeNet ID on your web browser. Make sure that it is a numeric", 400);
        }


        if( $tnid == null )
        {
	        if ($tnid == null && $this->getActiveCompany()->type == 'v' && $this->getActiveCompany()->id != "" )
	        {
	        	$tnid = $this->getActiveCompany()->id;
	        }
	        else if( $tnid == null && $this->getActiveCompany()->type != 'v' )
	        {
	        	throw new Myshipserv_Exception_MessagedException("Your active company is not a supplier, please choose a supplier to see its report.", 403);
	        }
	        else
	        {
	            if ($user->isShipservUser())
	            {
	                // mid performing supplier on shipserv
	                $tnid = 58983;
	            }
	            else
	            {
	            	throw new Myshipserv_Exception_MessagedException("Sorry, you cannot access this page as you are NOT part of ANY suppliers.", 403);
	            }
	        }
        }

        if( $user->isShipservUser() === false && $user->isPartOfCompany($tnid) === false )
        {
        	throw new Myshipserv_Exception_MessagedException("Sorry, you cannot access this page as you are NOT part of this suppliers.", 403);
        }

        // show suppliers eventhough they're not published
        $this->view->unpublished = (Shipserv_Supplier::fetch($tnid, $this->getDb(), false)->tnid == "");
        $supplier = Shipserv_Supplier::fetch($tnid, $this->getDb(), true);

        if ($supplier->tnid == '') {
            throw new Myshipserv_Exception_MessagedException("Please check your TradeNet ID on your web browser, System cannot find supplier with TNID: " . $tnid, 400);
        }

        // final check
        if ($supplier->svrAccess == 'N' && $user->isShipservUser() === false)
            throw new Myshipserv_Exception_MessagedException("Access to SIR is disabled for " . $supplier->name . ".", 403);

        if ($supplier->tnid != null) {
            $this->view->supplierProfile = $supplier;
        } else {
            throw new Myshipserv_Exception_MessagedException("Please check your TradeNet ID, System cannot find supplier with TNID: " . $tnid, 400);
        }

        // get count of unactioned rfq
        $adapter = new Shipserv_Adapters_Report();
        $data = $adapter->getSupplierUnactionedRFQ($tnid);
        $this->view->unactionedRfqCount = $data['unactioned-rfq']['count'];
        $this->getUser()->logActivity(Shipserv_User_Activity::SIR_VIEW, 'SUPPLIER_BRANCH', $supplier->tnid);

        // get which page to show
        $tabToView = '';
        $tabToView = (isset($params["view"])) ? $params["view"] : '';
        $this->view->tabToView = $tabToView;

        if (false === $forceNew)
        {
            if (!($this->isSir3User($tnid))){
                $this->_helper->viewRenderer('report/sir2', null, true);
            }
        }
    }

    /*
    * Force to load SIR3
    */
    public function sirNewAction()
    {
        $this->_helper->viewRenderer('report/index', null, true);
        $this->indexAction(true);
    }

    /**
    * Redirect SIR3 to SIR2, if Detailed Brakedown is clicked
    */
    public function sirDetailsGatewayAction()
    {
        $tnid = $this->getRequest()->getParam('tnid', null);

        if ($tnid) {
            // DEV-1610 Redirect to selected TNID
            if ((int)$this->getActiveCompany()->id !== (int)$tnid) {
                $this->redirect('/user/switch-company?tnid=' . (int)$tnid . '&redirect=' . urlencode('/reports/sir-details'));
            }
        }


        $this->_helper->viewRenderer('report/sir-gateway', null, true);
        $this->indexAction(true);
    }

    /**
    * Restrict to SIR2 stable version only
    */
    public function sirStableAction()
    {
        $tnid = $this->getRequest()->getParam('tnid', null);

        if ($tnid) {
            // DEV-1610 Redirect to selected TNID
            if ((int)$this->getActiveCompany()->id !== (int)$tnid) {
                $this->redirect('/user/switch-company?tnid=' . (int)$tnid . '&redirect=' . urlencode('/reports/sir-stable'));
            }
        }

        $this->_helper->viewRenderer('report/sir2', null, true);
        $this->indexAction();
    }

    /**
     * Interface for SmartSupplier
     */
    public function smartAction()
    {
    	// /reports/smart-sir?u={USER_ID}&s={SPB_BRANCH_CODE}&h={MD5('SHIPSERV_SMART_SUPPLIER_SIR' + USER_ID + SPB_BRANCH_CODE)}
    	// sample: http://sir.myshipserv.com/reports/smart-sir?u=500012&s=51387&h=413cff9d3491c7b1614965bbe68ede0c
    	$this->_helper->layout->setLayout('blank');


    	$tnid = null;
    	$userid = null;
    	$hash = null;

    	$suppliers = array();
    	$params = $this->params;

    	// get userid from the textfield
    	if (isset($params["u"]) && $params["u"] != "") {
    		$userid = $params["u"];
    	}

    	// get branchcode from the textfield
    	if (isset($params["s"]) && $params["s"] != "") {
    		$tnid = $params["s"];
    	}

    	// get hash from the textfield
    	if (isset($params["h"]) && $params["h"] != "") {
    		$hash = $params["h"];
    	}

    	if (null === $tnid || null === $userid || null ===  $hash) {
    		throw new Myshipserv_Exception_MessagedException("Invalid URL", 400);
    	}

    	//generating hash for security check
    	$hashCode = md5('SHIPSERV_SMART_SUPPLIER_SIR'.$userid.$tnid);

    	if ($hashCode !== $hash) {
    		throw new Myshipserv_Exception_MessagedException("Invalid URL", 400);
    	}

    	// check if it's numeric
    	if (isset($params["tnid"]) && !is_numeric($params["tnid"])) {
    		throw new Myshipserv_Exception_MessagedException("Please check your Branch Code. Make sure that it is a numeric", 400);
    	}

    	// check if it's numeric
    	if (isset($params["u"]) && !is_numeric($params["u"])) {
    		throw new Myshipserv_Exception_MessagedException("Please check your User ID. Make sure that it is a numeric", 400);
    	}

    	//check the user id, if it is valid
    	$db = Shipserv_Helper_Database::getStandByDb(true);

    	$sql = 'SELECT count(*) as SUPPLYCOUNT from SUPPLIER_BRANCH_USER where SBU_USR_USER_CODE =:usercode and SBU_SPB_BRANCH_CODE = :branchcode';

    	$results = $db->fetchAll($sql, array('usercode' => $userid,
    										'branchcode' => $tnid));

    	if (0 === $results[0]['SUPPLYCOUNT']) {
    		throw new Myshipserv_Exception_MessagedException("Invalid user id or branch code", 400);
    	}

    	// show suppliers eventhough they're not published
    	$supplier = Shipserv_Supplier::fetch($tnid, $this->getDb(), true);

    	if ($supplier->tnid == '') {
    		throw new Myshipserv_Exception_MessagedException("Please check your TradeNet ID, System cannot find supplier with TNID: " . $tnid, 400);
    	}

    	if ($supplier->tnid != null) {
    		$this->view->supplierProfile = $supplier;
    	} else {
    		throw new Myshipserv_Exception_MessagedException("Please check your TradeNet ID, System cannot find supplier with TNID: " . $tnid, 400);
    	}


    	// get count of unactioned rfq
    	$adapter = new Shipserv_Adapters_Report();
    	$data = $adapter->getSupplierUnactionedRFQ($tnid);

        //checking if shipmage or not
        $sUser = Shipserv_Report_Supplier_Insight_Smart::getInstance((int)$userid);
        $isShipMate = $sUser->isShipservUserByUserId();
        $this->view->isShipmate = $isShipMate;

        $this->view->userid = $userid;
        $this->view->tnid = $tnid;
        $this->view->hash = $hash;

        if (isset($params["d"])) {
            $this->view->isDetailed = (int)$params["d"];
        } else {
            $this->view->isDetailed = 0;
        }

        // get which page to show
        $tabToView = '';
        $tabToView = (isset($params["view"])) ? $params["view"] : '';
        $this->view->tabToView = $tabToView;

        $this->view->oldVersion = (isset($params["v"]) && $params["v"] == "1");
        $this->view->isSir3  = ($this->isSir3User($tnid) || $isShipMate);
    	$this->view->unactionedRfqCount = $data['unactioned-rfq']['count'];

    }


    public function matchAction() {
    	if (!Shipserv_Helper_Security::isValidInternalIP(Shipserv_Helper_Security::getRealUserIP())) {
    		// throw new exception("Access Denied: " . Shipserv_Helper_Security::getRealUserIP());
    		// die();
    	}elseif (! $user = $this->getUser()){
    		$this->_forward("index");
    	}
    	$this->_helper->layout->setLayout('default');

    	$p = $this->params;

    	//Get BuyerID, if not a buyer throw error.
    	$user = Shipserv_User::isLoggedIn();
    	if (!is_object($user)) {
    		return false;
    	}

    	//$buyerCompaniesIds = $user->fetchCompanies()->getBuyerIds();
    	$db = Shipserv_Helper_Database::getStandByDb(true);
    	$reporting = Shipserv_Helper_Database::getSsreport2Db();

    	$sql = "Select usr_md5_code from users where usr_user_code = :userId";
    	$params = array('userId' => $user->userId);

    	$userMD5 = $db->fetchOne($sql, $params);

    	if (!$p['page']) {
    		$page = 1;
    	} else {
    		$page = $p['page'];
    	}

    	if (empty($p['sortMethod'])) {
    		$sortMethod = 1;
    	} else {
    		if (empty($_POST['sortMethod'])) {
    			$sortMethod = $p['sortMethod'];
    		} else {
    			$sortMethod = $_POST['sortMethod'];
    		}
    	}
    	$pageCount = 10;

    	$buyerOrganisationNS = Myshipserv_Helper_Session::getActiveCompanyNamespace();

    	$buyerOrganisation = $buyerOrganisationNS->id;

    	//TODO: Push this code to helper class (buyer class perhaps?)

    	$sql = "Select BYB_BRANCH_CODE, BYB_NAME from Buyer_branch b where byb_byo_org_code = :orgCode and BYB_BRANCH_CODE in (Select rfq_byb_branch_code from request_for_quote r, rfq_quote_relation rqr
                where
                    r.rfq_internal_ref_no = rqr.rqr_rfq_internal_ref_no
                    and  rqr.rqr_spb_branch_code = 999999
                    and rqr.rqr_byb_branch_code = b.byb_branch_code) order by BYB_NAME";
    	$params = array('orgCode' => $buyerOrganisation);

    	$results = $db->fetchAll($sql, $params);
    	foreach ($results as $result) {
    		$buyerDetails[] = array('branch' => $result['BYB_BRANCH_CODE'], 'name' => $result['BYB_NAME']);
    	}

    	if (count($buyerDetails) == 0) {

    	}
    	$branchCode = $buyerDetails[0]['branch'];
    	if (empty($p['period']) && empty($p['periodOld'])) {
    		$selectedPeriod = 50;
    	} else {
    		if (empty($_POST['period'])) {
    			$selectedPeriod = $p['period'];
    		} else {
    			$selectedPeriod = $_POST['period'];
    		}
    	}

    	if(is_null($branchCode)){
    		$this->redirect('/reports');
    		die();
    	}
    	$stats = new Shipserv_Match_Stats($branchCode);

    	$noRFQsToMatch = $stats->getCountRFQSToMatch($selectedPeriod);
    	$this->view->noRFQsSentToMatch = $noRFQsToMatch;

    	$this->view->noRFQsNotSentToMatch = $stats->getCountRFQsNotSentToMatch($selectedPeriod);

    	$this->view->noQuotesFromMatch = $stats->getCountQuotesFromMatch($selectedPeriod);
    	try {
    		$this->view->percentageQuotesToMatch = $stats->getPercentageRFQSToMatch($selectedPeriod);
    	} catch (Exception $ex) {
    		$this->view->percentageQuotesToMatch = 0;
    	}
    	$this->view->noPOsFromMatchSuppliers = $stats->getCountPOsMatchSuppliers($selectedPeriod);

    	$this->view->rfqs = $stats->getRFQsSentToMatch($selectedPeriod, $page, $pageCount, $sortMethod);

    	if ($noRFQsToMatch > $pageCount) {
    		$noExtraPage = ($noRFQsToMatch % $pageCount) == 0;
    		$lastPage = intval($noRFQsToMatch / $pageCount);
    		if (!$noExtraPage) {
    			$lastPage++;
    		}
    	} else {
    		$lastPage = 1;
    	}
    	//$user->
    	//Temp Check if RFQs been sent to Match. If not, throw error.
    	$this->view->page = $page;
    	$this->view->rfqsPerPage = $pageCount;
    	$this->view->lastPage = $lastPage;

    	$this->view->sortMethod = $sortMethod;

    	$this->view->stats = $stats;
    	$this->view->user = $this->getUser();
    	$this->view->userMD5 = $userMD5;
    	$this->view->selectedBranch = $branchCode;
    	$this->view->branches = $buyerDetails;
    	$this->view->selectedPeriod = $selectedPeriod;
    }

    
    public function matchNewAction() 
    {
    	$buyerDetails =[];
        $this->_helper->layout->setLayout('default');
        $p = $this->params;
        //$this->forceAuthentication();

        //Get BuyerID, if not a buyer throw error.
        $user = Shipserv_User::isLoggedIn();
        if (!is_object($user)) {
            return false;
        }
        
        //S20380 Restrict access to non full members
        if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
        	$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'analyse'));
        };
        
        $user->logActivity(Shipserv_User_Activity::MATCH_DASHBOARD_LAUNCH, 'PAGES_USER', $user->userId, $user->email);
        if (!($user->canAccessMatchBenchmark())) {
            throw new Myshipserv_Exception_MessagedException("Sorry, you cannot access the report.", 403);
        }
        
        $buyerOrgCompany = $this->getUserBuyerOrg();
        $this->view->byoOrgCode = (int) $buyerOrgCompany->id;

        $branchCode = null;
        if (isset($p['branches']) && $p['branches'] != null && ctype_digit($p['branches'])) {
            $branchCode = $p['branches'];
        } else {
            if (isset($p['branches']) && $p['branches'] != null && preg_match('/^([0-9]+,?)+$/', $p['branches'])) {
                $buyerIdLists = explode(",", $p['branches']);
                $buyerIds = array();
                foreach ($buyerIdLists as $id) {
                    $buyer = Shipserv_Buyer::getBuyerBranchInstanceById($id);
                    array_push(
                        $buyerIds,
                        array(
                            'id' => (int)$id,
                            'name' => $id.' - '.$buyer->bybName
                            )
                        );                
                };
            } else {
                $buyers = Shipserv_Report_Buyer_Match_BuyerBranches::getInstance($p);
                $buyerIds = $buyers->getBuyerBranches();                
            }

            foreach ($buyerIds as $branch)
            {
                if( strstr(strtolower($branch['name']), "test") == false )
                    $buyerDetails[] = array('branch' => (int) $branch['id'], 'name' => $branch['name']);
            }

            $branchCode = (lg_count($buyerDetails) > 0) ? $buyerDetails[0]['branch'] : null;
            //make sure, if we have only test account, it will be selected, filtered
            if ($branchCode == null) {
                $branchCode = (count($buyerIds) > 0) ? $buyerIds[0]['id'] : null;
            /*
            * Before conflict resolution
            $buyerIds = Shipserv_Report_Buyer_Match_BuyerBranches::getInstance()->getBuyerBranches(Shipserv_User::BRANCH_FILTER_MATCH);
            foreach ($buyerIds as $branch) {
                if ($branch['default']) {
                    $branchCode = $branch['id'];
                }
            */
            }
            //If no default found, assign to $branchCode the first branch of the list
            if (is_null($branchCode) && count($buyerIds) > 0) {
                $branchCode = $buyerIds[0]['id'];
            }            
            
        }

        /*
        if( $branchCode == null )
        {

            //Removed and replaced this part by Attila O, as the dropdown uses a different method to fetch the buyers as JSON. Using the same class instead
            $buyerIds = $buyerOrgCompany->getBranchesTnid();
            $data = array();
            foreach ($buyerIds as $id)
            {
                $branch = Shipserv_Buyer::getBuyerBranchInstanceById($id, "", true);
                $buyerDetails[] = array('branch' => (int) $branch->bybBranchCode, 'name' => $branch->bybName);
            }

            $branchCode = $buyerDetails[0]['branch'];

        }
        */

        if ($branchCode != null) {
	        $adoptionReport = new Shipserv_Match_Report_AdoptionRate();
	        $statsForGraphReport = new Shipserv_Match_Report_Usage_Monthly();

	        $statsForGraphReport->setBybBranchCode($branchCode);
	        $statsForGraphReport->setAsMonthly();

            $from = $this->params['from'] ?? '';
            $to = $this->params['to'] ?? '';
	        if ($from != ""  || $to != "") {
	        	$tmp = explode("/", $from);

	        	$adoptionReport->setStartDate($tmp[0], $tmp[1], $tmp[2]);
	        	$statsForGraphReport->setStartDate($tmp[0], $tmp[1], $tmp[2]);

	        	$tmp = explode("/",  $to);
	        	$adoptionReport->setEndDate($tmp[0], $tmp[1], $tmp[2]);
	        	$statsForGraphReport->setEndDate($tmp[0], $tmp[1], $tmp[2]);
	        }

            $vessel = $this->params['vessel'] ?? '';
        	if ($vessel != "") {
	        	$adoptionReport->setVesselName($vessel);
	        	$statsForGraphReport->setVesselName($vessel);
	        }

            $purchaser = $this->params['purchaser'] ?? '';
	        if ($purchaser != "") {
	        	$adoptionReport->setPurchaserEmail($purchaser);
	        	//$statsForGraphReport->setPurchaserEmail($this->params['email']);
	        }

            if ($buyerIds) {
                $branchlist = array();
                foreach ($buyerIds as $buyerId) {
                       array_push($branchlist, $buyerId['id']);
                }
            } else {
                $branchlist = $branchCode;
            }

	        $this->view->statsForGraph = $statsForGraphReport->getStatistic();
	        $this->view->adoptionReport = $adoptionReport->getStatByBuyerId($branchlist);
	        $this->view->adoptionReportAvg = $adoptionReport->getAverageStatistic();
	        $this->view->adoptionReportBest = $adoptionReport->getBestPerformerStatistic();
	        $this->view->report = $adoptionReport;

	        $this->view->selectedBranch = $branchCode;
            $this->view->allowAcces = true;

        } else {
            $this->view->allowAcces = false;
        }

        $this->view->params = $p;
        $this->view->showPriceBenchmarkMenu = $this->user->canAccessPriceBenchmark();
    }

    
    public function matchReportAction()
    {

    	$user = Shipserv_User::isLoggedIn();
        if (is_object($user)) {
            $user->logActivity(Shipserv_User_Activity::MATCH_REPORT_LAUNCH, 'PAGES_USER', $user->userId, $user->email);
             if (!($user->canAccessMatchBenchmark())) {
                throw new Myshipserv_Exception_MessagedException("Sorry, you cannot access the report.", 403);
             }
        } else {
            Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        }
        
        //S20380 Restrict access to non full members
        if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
        	$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'analyse'));
        };
        
        $this->_helper->layout->setLayout('default');
        $p = $this->params;
        $buyerOrganisationNS = Myshipserv_Helper_Session::getActiveCompanyNamespace();
        $buyerOrganisation = $buyerOrganisationNS->buyer->id;
        $buyerOrgCompany = $this->getUserBuyerOrg();

        $data = array();
        $branchCode = null;
        $branches = Shipserv_Report_Buyer_Match_BuyerBranches::getInstance()->getBuyerBranches(Shipserv_User::BRANCH_FILTER_MATCH);

        foreach ($branches as $branch) {
            if ($branch['default']) {
                $branchCode = $branch['id'];
            }            
        }
        //If no default found, assign to $branchCode the first branch of the list
        if (is_null($branchCode) && count($branches) > 0) {
            $branchCode = (count($branches) > 0)? $branches[0]['id'] : null;
        }
        
        $report = new Shipserv_Match_Report_Buyer($branchCode);

        $startDateObject = new DateTime();
        $startDateObject->setDate(date('Y')-1, date('n'), date('j'));

        $endDateObject = new DateTime();
        $endDateObject->setDate(date('Y'), date('n'), date('j'));


        $this->view->buyerId = $branchCode;
        $this->view->vesselName = "";
        $this->view->fromDate = $startDateObject->format("d/m/Y");//24/07/2013";
        $this->view->toDate = $endDateObject->format("d/m/Y"); //"24/07/2014";

        $this->view->showPriceBenchmarkMenu = $this->user->canAccessPriceBenchmark();
    }

    
    public function supplierStatsAction()
    {
    	$this->abortIfNotShipMate();

        $user = Shipserv_User::isLoggedIn();
        if ($user) {
            $user->logActivity(Shipserv_User_Activity::GMV_SUPPLIER_STATISTICS_CLICK, 'PAGES_USER', $user->userId, $user->email);
        }
        // No max execution time
        ini_set('max_execution_time', 0);
        
        // No upper memory limit
        ini_set('memory_limit', -1);
    	$report = new Shipserv_Report_Supplier_Statistic;
    	$this->view->accountManager = $report->getAccountManager();
    	$this->view->region = $report->getRegion();
    	$this->view->country = $report->getCountry();
    	$this->view->type = $report->getIntegrationType();
    	$oldTimeOut = ini_set('max_execution_time', 0);
    	$this->view->report = $report;

    	if( $this->params['fromDate'] != "" )
    	{
            $this->slowLoadingPage();

    		$report->setParams($this->params);
    		$this->view->results = $report->getData();
    	}
    }

    public function supplierConversionAction()
    {
    	$this->abortIfNotShipMate();

        $user = Shipserv_User::isLoggedIn();
        if ($user) {
            $user->logActivity(Shipserv_User_Activity::GMV_SUPPLIER_CONVERSION, 'PAGES_USER', $user->userId, $user->email);
        }

    	$report = new Shipserv_Report_Supplier_Conversion;
    	$oldTimeOut = ini_set('max_execution_time', 0);
    	if( $this->params['id'] != "" )
    	{
            $this->slowLoadingPage();

	    	$report->setParams($this->params);
	    	$this->view->results = $report->getData(($this->params['parent'] == null ));
	    	$this->view->startDate = $report->startDate;
	    	$this->view->endDate = $report->endDate;
    	}
    }

    public function supplierConversionListRfqAction()
    {
    	$this->abortIfNotShipMate();
    	$report = new Shipserv_Report_Supplier_Conversion_Rfq;
    	$oldTimeOut = ini_set('max_execution_time', 0);
    	if( $this->params['tnid'] != "" )
    	{
            $this->slowLoadingPage();

    		$report->setParams($this->params);
    		$this->view->results = $report->getData();
    		$this->view->startDate = $report->startDate;
    		$this->view->endDate = $report->endDate;
    	}
    }

    /** FILTERS * */

    /**
     * @deprecated in favor for /data/source/*
     * @throws Myshipserv_Exception_MessagedException
     */
    public function portAction() {

    	throw new Myshipserv_Exception_MessagedException("Deprecated");

        $adapter = new Shipserv_Oracle_Ports($this->getDb());
        $data = $adapter->fetchAllPortsGroupedByCountry();

		if( $this->params['r'] != '0' )
		{
        	$response = new Myshipserv_Response("200", "OK", $data);
        	$data = $response->toArray();
		}
        $this->_helper->json((array)$data);
    }
    /**
     * @deprecated in favor for /data/source/*
     * @throws Myshipserv_Exception_MessagedException
     */

    public function brandAction() {
    	throw new Myshipserv_Exception_MessagedException("Deprecated");

        $brandDao = new Shipserv_Oracle_Brands($this->getDb());
        $data = $brandDao->search();
        $formattedData = array();
        foreach ($data as $brand) {
            array_push($formattedData, array('id' => $brand["ID"], 'name' => $brand["NAME"]));
        }

        $response = new Myshipserv_Response("200", "OK", $formattedData);
        $this->_helper->json((array)$response->toArray());
    }

    /**
     * @deprecated in favor for /data/source/*
     * @throws Myshipserv_Exception_MessagedException
     */
    public function categoryAction() {
    	throw new Myshipserv_Exception_MessagedException("Deprecated");

        $adapter = new Shipserv_Oracle_Categories($this->getDb());
        $data = $adapter->fetchNestedCategories();
        $formattedData = array();
        foreach ($data as $category) {
            array_push($formattedData, array('id' => $category["ID"], 'name' => $category["NAME"]));
        }
        $response = new Myshipserv_Response("200", "OK", $formattedData);
        $this->_helper->json((array)$response->toArray());
    }

    /** REPORTING CALLS * */

    /**
     * This is to save and pull stored reports available for viewing
     */
    public function customReportsAction() {
        $params = $this->params;

        // if post then assume that the operation is update or insert
        if ($this->getRequest()->isPost()) {
            // checking required fields
            if (is_null($params["tnid"]) || is_null($params["title"]) || is_null($params["data"])) {
                throw new Myshipserv_Exception_MessagedException("Incorrect parameters", 400);
            }

            $dao = new Shipserv_Oracle_CustomReport($this->getDb());

            // determine whether it's an update or insert operation
            if ($params["id"] != "") {
                $dmlResult = $dao->update($params["id"], $params["title"], $params["description"], $this->getUser()->userId, $params["tnid"], $params["data"]);
            } else {
                $dmlResult = $dao->insert($params["title"], $params["description"], $this->getUser()->userId, $params["tnid"], $params["data"]);
            }

            // send response to user
            if ($dmlResult === true) {
                $response = new Myshipserv_Response("200", "OK");
                $this->_helper->json((array)$response->toArray());
            } else {
                $response = new Myshipserv_Response("500", "Insertion error on PAGES_SVR_USER_CUSTOM_REPORT", $data);
                $this->_helper->json((array)$response->toArray());
            }
        }

        // view or pull data
        else {
            $data = Shipserv_Report_CustomReport::fetch();
            $response = new Myshipserv_Response("200", "OK", $data);
            $this->_helper->json((array)$response->toArray());
        }
    }

    /**
     * Interface to export to excel
     * so many things to do in here.
     * Example: http://svr.myshipserv.com/reports/api/export-to-excel/tnid/57926/start/20080101/end/20090101/start2/20080101/end2/20090101
     */
    public function exportToExcelAction() {
        $params = $this->params;

        // Tnid is required to export this to excel
        if (is_null($params['tnid'])) {
            throw new Myshipserv_Exception_MessagedException("Incorrect TNID", 400);
        }

        if ($this->getUser()->canAccessSVR($params["tnid"]) === false) {
            throw new Myshipserv_Exception_MessagedException("Sorry, you cannot access the report for this supplier.", 403);
        }

        // Preparing all the parameters
        // Periods
        $startDate1 = ( $params['start'] != '' ) ? $params['start'] : null;
        $endDate1 = ( $params['end'] != '' ) ? $params['end'] : null;
        $startDate2 = ( $params['start2'] != '' ) ? $params['start2'] : null;
        $endDate2 = ( $params['end2'] != '' ) ? $params['end2'] : null;

        // Other filters
        $ports = ( $params['location'] != '' ) ? $this->convertToArray($params['location']) : array();
        $categories = ( $params['categories'] != '' ) ? $this->convertToArray($params['categories']) : array();
        $brands = ( $params['brands'] != '' ) ? $this->convertToArray($params['brands']) : array();
        $products = ( $params['products'] != '' ) ? $this->convertToArray($params['products']) : array();

        // Create or compile such report
        $report = Shipserv_Report::createFullSupplierValueReport($params['tnid'], $startDate1, $endDate1, $startDate2, $endDate2, $ports, $categories, $brands, $products);
        ;

        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender();

        // Export the report to excel returning the file path to the physical file
        $excelFile = $report->toExcel();
        $response = new Myshipserv_Response("200", "OK", $excelFile);
        $this->_helper->json((array)$response->toArray());
    }

    public function downloadAction() {
        $params = $this->params;
        $file = '/tmp/svr/excel/' . $params['fileName'];

        if (file_exists($file)) {
            $tnid = str_replace("report_for_TNID_", "", $params['fileName']);
            $tnid = str_replace(".xlsx", "", $tnid);

            // Log the download
            $this->getUser()->logActivity(Shipserv_User_Activity::SIR_EXPORT_TO_EXCEL, 'SUPPLIER_BRANCH', $tnid);

            header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
            header("Cache-Control: no-store, no-cache, must-revalidate");
            header("Cache-Control: post-check=0, pre-check=0", false);
            header("Pragma: no-cache");
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="' . basename($file) . '"');

            ob_clean();
            flush();
            readfile($file);
            exit;
        }
    }

    /**
     * This call is used to pull all related reports for a single supplier including the average with single or multiple periods
     * UI is not recommended to use this but please use supplier-impression instead
     * @throws Myshipserv_Exception_MessagedException
     */
    public function fullSupplierImpressionAction() {
        $params = $this->params;

        // Tnid is required to export this to excel
        if (is_null($params['tnid'])) {
            throw new Myshipserv_Exception_MessagedException("Incorrect parameters", 400);
        }

        // check if it's numeric
        if (!is_numeric($params["tnid"])) {
            throw new Myshipserv_Exception_MessagedException("Please check your TradeNet ID. Make sure that it is a numeric", 400);
        }

        if ($this->getUser()->canAccessSVR($params["tnid"]) === false) {
            throw new Myshipserv_Exception_MessagedException("Sorry, you cannot access the report for this supplier.", 403);
        }

        // Preparing all the parameters
        // Periods
        $startDate1 = ( $params['start'] != '' ) ? $params['start'] : null;
        $endDate1 = ( $params['end'] != '' ) ? $params['end'] : null;
        $startDate2 = ( $params['start2'] != '' ) ? $params['start2'] : null;
        $endDate2 = ( $params['end2'] != '' ) ? $params['end2'] : null;

        // Other filters
        $ports = ( $params['location'] != '' ) ? $this->convertToArray($params['location']) : array();
        $categories = ( $params['categories'] != '' ) ? $this->convertToArray($params['categories']) : array();
        $brands = ( $params['brands'] != '' ) ? $this->convertToArray($params['brands']) : array();
        $products = ( $params['products'] != '' ) ? $this->convertToArray($params['products']) : array();

        // Create or compile such report
        $report = Shipserv_Report::createFullSupplierValueReport($params['tnid'], $startDate1, $endDate1, $startDate2, $endDate2, $ports, $categories, $brands, $products);
        ;
        $response = new Myshipserv_Response("200", "OK", $report->toArray());
        $this->_helper->json((array)$response->toArray());
    }

    /**
     * Get supplier impression
     * @throws Myshipserv_Exception_MessagedException
     * @deprecated
     */
    public function supplierImpressionAction() {
        $params = $this->params;

        // check if tnid is specified
        if (is_null($params['tnid']) || is_numeric($params['tnid']) == false) {
            throw new Myshipserv_Exception_MessagedException("Incorrect parameters. Please make sure that you supply correct ID", 400);
        }

        // making sure that the dates are in valid format
        if ($params['start'] != '') {
            if ($this->isDateFormat($params['start']) == false || $this->isDateFormat($params['end']) == false) {
                throw new Myshipserv_Exception_MessagedException("Invalid date format, please use the following format: YYYYMMDD", 400);
            }
            if ($this->convertStringToDateTime($params['start'])->format('U') > $this->convertStringToDateTime($params['end'])->format('U')) {
                throw new Myshipserv_Exception_MessagedException("Invalid dates. Make sure that the start date < end date.", 400);
            }
        }

        $user = Shipserv_User::isLoggedIn();
        // check the access right
        if ($user->canAccessSVR($params["tnid"]) === false && $user !== false) {
            throw new Myshipserv_Exception_MessagedException("Sorry, you cannot access the report for this supplier.", 403);
        }

        $startDate1 = ( $params['start'] != '' ) ? $params['start'] : null;
        $endDate1 = ( $params['end'] != '' ) ? $params['end'] : null;
        $startDate2 = ( $params['start2'] != '' ) ? $params['start2'] : null;
        $endDate2 = ( $params['end2'] != '' ) ? $params['end2'] : null;
        // get the report based on the $_GET parameter
        $report = Shipserv_Report::getSupplierValueReport($params['tnid'], $startDate1, $endDate1, $startDate2, $endDate2);

        // put the report to the response
        $response = new Myshipserv_Response("200", "OK", $report->toArray());

        // convert response to JSON
        $this->_helper->json((array)$response->toArray());
    }

    /**
    * Action for price benchmark tab
    */
    public function priceBenchmarkAction()
    {
    	
        $user = Shipserv_User::isLoggedIn();
        if (!$user) {
            Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        }
        
        if (!Shipserv_PriceBenchmark::checkUserAccess($this)) {
            throw new Myshipserv_Exception_MessagedException("You are not authorised to access this page", 404);
        }
        
        //S20380 Restrict access to non full members
        if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
        	$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'analyse'));
        };

        $this->view->defaultFromDate = Shipserv_PriceBenchmark::getDefaultFromDate();
        $this->view->defaultToDate = new DateTime();
        $this->view->maxSelectedSuppliers  = $this->config['shipserv']['enquiryBasket']['maximum'];
        $this->view->basketCookie = $this->config['shipserv']['enquiryBasket']['cookie'];

        if ($this->_getParam('priceTracker')) {
            // we are returning from Price Tracker Tool, so we need to provide form to pre-populate filters for user
            $impaCode = $this->_getParam('priceTrackerImpa');
            $product = Shipserv_Oracle_ImpaCatalogue::getRowByCode($impaCode);

            $this->view->priceTracker = array(
                'impa'   => $impaCode,
                'desc'   => $product[Shipserv_Oracle_ImpaCatalogue::COL_DESC],
                'unit'   => $this->_getParam('priceTrackerUnit'),
                'date'   => $this->_getParam('priceTrackerDate'),
                'refine' => $this->_getParam('priceTrackerRefine')
            );
        } else {
            $this->view->priceTracker = null;
        }

        $this->_helper->layout->setLayout('default');
        $this->view->showPriceBenchmarkMenu = $this->user->canAccessPriceBenchmark();

        $this->user->logActivity(Shipserv_User_Activity::IMPA_PRICE_BENCHMARK_REPORT_SERVED, 'PAGES_USER', $this->user->userId, $this->user->email);

    }

    /**
    *   Action for price tracker tool
    */
    public function priceTrackerAction()
    {

        $user = Shipserv_User::isLoggedIn();
        if (!$user) {
            Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        }

        if (!Shipserv_PriceBenchmark::checkUserAccess($this)) {
            throw new Myshipserv_Exception_MessagedException("You are not authorised to access this page", 404);
        }

        //S20380 Restrict access to non full members
        if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
        	$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'analyse'));
        };
        
        // $warmUpResult = $this->warmUpPriceSection();
        // var_dump($warmUpResult);

        $this->view->defaultFromDate = Shipserv_PriceBenchmark::getDefaultFromDate();
        $this->view->defaultDayRange = 0;
        $this->view->showPriceBenchmarkMenu = $this->user->canAccessPriceBenchmark();

        $this->_helper->layout->setLayout('default');
        $this->user->logActivity(Shipserv_User_Activity::IMPA_SPEND_TRACKER_REPORT_SERVED, 'PAGES_USER', $this->user->userId, $this->user->email);
    }

    /**
     * Get general impression
     * @deprecated
     */
	public function generalImpressionAction() {
        $params = $this->params;

        $startDate1 = ( $params['start'] != '' ) ? $params['start'] : null;
        $endDate1 = ( $params['end'] != '' ) ? $params['end'] : null;
        $startDate2 = ( $params['start2'] != '' ) ? $params['start2'] : null;
        $endDate2 = ( $params['end2'] != '' ) ? $params['end2'] : null;

        $ports = ( $params['location'] != '' ) ? $this->convertToArray($params['location']) : array();
        $categories = ( $params['categories'] != '' ) ? $this->convertToArray($params['categories']) : array();
        $brands = ( $params['brands'] != '' ) ? $this->convertToArray($params['brands']) : array();
        $products = ( $params['products'] != '' ) ? $this->convertToArray($params['products']) : array();
        $countries = ( $params['countries'] != '' ) ? $this->convertToArray($params['countries']) : array();

        $adapter = new Shipserv_Adapters_Report();
        $report = Shipserv_Report::getGlobalValueReport($startDate1, $endDate1, $startDate2, $endDate2, $ports, $categories, $brands, $products, $countries);

        $response = new Myshipserv_Response("200", "OK", $report->toArray());
        $this->_helper->json((array)$response->toArray());
    }

    public function supplierAction()
    {

        $params = $this->params;

        // check if tnid is specified
        if (is_null($params['tnid']) || is_numeric($params['tnid']) == false) {
            throw new Myshipserv_Exception_MessagedException("Incorrect parameters. Please make sure that you supply correct ID", 400);
        }

        // making sure that the dates are in valid format
        if ($params['start'] != '') {
            if ($this->isDateFormat($params['start']) == false || $this->isDateFormat($params['end']) == false) {
                throw new Myshipserv_Exception_MessagedException("Invalid date format, please use the following format: YYYYMMDD", 400);
            }
            if ($this->convertStringToDateTime($params['start'])->format('U') > $this->convertStringToDateTime($params['end'])->format('U')) {
                throw new Myshipserv_Exception_MessagedException("Invalid dates. Make sure that the start date < end date.", 400);
            }
        }

        $user = Shipserv_User::isLoggedIn();

        // check the access right
        if ($user !== false && $user->canAccessSVR($params["tnid"]) === false) {
        	throw new Myshipserv_Exception_MessagedException("Sorry, you cannot access the report for this supplier.", 403);
        }

        $startDate1 = $params['start'] ?? null;
        $endDate1 = $params['end'] ?? null;
        $startDate2 = $params['start2'] ?? null;
        $endDate2 = $params['end2'] ?? null;

        // get the report based on the $_GET parameter
        $report = Shipserv_Report::getSupplierValueReport($params['tnid'], $startDate1, $endDate1, $startDate2, $endDate2);
        $report = $report->toArray();
        $report = $report['supplier'];

        $this->mergeArray($report['search-summary']['category-searches-global'], $report['search-summary']['category-searches-local']);
        $this->mergeArray($report['search-summary']['brand-searches-global'], $report['search-summary']['brand-searches-local']);

        // convert response to JSON
        $this->_helper->json((array)($report));
	}

    private function mergeArray(&$array1, &$array2)
    {

        if( $array1 === null )
        {
            $array1 = array();
        }

        if( $array2 === null )
        {
            $array2 = array();
        }

        $newData = array();

    	foreach($array1 as $row)
    	{
    		$newData[$row['name']] = $row;
    	}

    	foreach($array2 as $row)
    	{
    		if( $newData[$row['name']] == null )
    		{
    			$newData[$row['name']] = array('id' => $row['id'], 'name' => $row['name'], 'search' => 0, 'click' => 0);
    		}
    	}

    	ksort($newData);
    	$array1 = $newData;

    	$d = array();
    	foreach($array1 as $x)
    	{
    		$d[] = $x;
    	}
    	$array1 = $d;


    	$newData = array();

    	foreach($array2 as $row)
    	{
    		$newData[$row['name']] = $row;
    	}

    	foreach($array1 as $row)
    	{
    		if( $newData[$row['name']] == null )
    		{
    			$newData[$row['name']] = array('id' => $row['id'], 'name' => $row['name'], 'search' => 0, 'click' => 0);
    		}
    	}
    	ksort($newData);
    	$array2 = $newData;
    	$d = array();
    	foreach($array2 as $x)
    	{
    		$d[] = $x;
    	}
    	$array2 = $d;

    	return $newData;
    }

    /**
     * Used for warming up the data with contents from Google DFP.
     * @deprecated
     */
    public function importAction() {
        ini_set('output_buffering', 'off');
        @apache_setenv('no-gzip', 1);
        @ini_set('zlib.output_compression', 0);
        @ini_set('implicit_flush', 1);

        $params = $this->params;
        $arr = explode("-", $params['date']);

        // if interval is defined, it will then try to download report for given interval
        if (isset($params["interval"]) && $params["interval"] != "") {
            echo "\n<br />Entering interval mode";

            $date = new DateTime();
            $date->setDate($arr[2], (int) $arr[1], (int) $arr[0]);
            $adapter = new Shipserv_Adapters_Report_GoogleDFP();
            $adapter->saveReport($date, $params["interval"]);
        }
        // if user use date, it'll batch up report by day
        else {
            echo "\n<br />Try pulling data for " . $params["interval"] . " previous days.";

            $nextDayTs = 0;
            for ($i = 0; $i < 10; $i++) {
                if ($nextDayTs == 0) {
                    $date = new DateTime();
                    $date->setDate($arr[2], (int) $arr[1], (int) $arr[0]);
                } else {
                    $date = new DateTime();
                    $date->setDate(date('Y', $nextDayTs), date('m', $nextDayTs), date('d', $nextDayTs));
                }

                echo "\n<br />Pulling date for: " . $date->format('d-m-Y');
                sleep(5);
                $adapter = new Shipserv_Adapters_Report_GoogleDFP();
                $adapter->saveReport($date);

                $currentTs = $date->format("U");
                $nextDayTs = $currentTs + 86400;
            }
        }
        die();
        //$r = Zend_Controller_Action_HelperBroker::getStaticHelper('redirector');
        //$r->gotoUrl('/reports/api/import/date/' . date('d-m-Y', $nextDayTs));
    }

    public function sendEmailSummaryToCustomerAction() {
        $params = $this->params;
		$user = Shipserv_User::isLoggedIn();
        $adapter = new Shipserv_Oracle_Mailer($this->db);
        $notificationManager = new Myshipserv_NotificationManager($this->getDb());

        $error = false;
        $emails = $params["emails"];
        $tnid = $params["destinationTnid"];
        $message = $params['bodyText'];
        $salutation = $params['fromText'];
        $startDate = $params['startDate'];
        $endDate = $params['endDate'];

        $startDate = new DateTime;
        $endDate = new DateTime;
        $startDate->setDate(substr($params['startDate'], 0, 4), substr($params['startDate'], 4, 2), substr($params['startDate'], 6, 2));
        $endDate->setDate(substr($params['endDate'], 0, 4), substr($params['endDate'], 4, 2), substr($params['endDate'], 6, 2));

        if($this->user->isShipservUser() == false || $this->user->canPerform('PSG_FORWARD_SIR') == false)
        {
        	throw new Myshipserv_Exception_MessagedException("You are not allowed to perform this action.", 403);
        }

        try {
            $report = Shipserv_Report::getSupplierValueReport($tnid, $startDate->format("Ymd"), $endDate->format("Ymd"));
            $supplier = Shipserv_Supplier::fetch($tnid);
        } catch (Exception $e) {
            $error = true;
        }

        if ($error == false) {
            try {
                foreach ((array) $emails as $e) {
                    if ($e != "") {
                        // send report to the customer
                        $notificationManager->sendSIRSummaryToCustomer($e, $subject, $supplier, $report, array("from" => $startDate->format("d M Y"), "to" => $endDate->format("d M Y")), $this->getDb(), $this->mode, $message, $salutation);
                        $d[] = $e;
                    }
                }
                // send report to the logged member
                $notificationManager->sendSIRSummaryToCustomer($this->getUser()->email, 'SIR Summary for ' . $supplier->name . " (TNID:" . $tnid . ") has been sent to " . implode(", ", $d), $supplier, $report, array("from" => $startDate->format("d M Y"), "to" => $endDate->format("d M Y")), $this->getDb(), $this->mode, $message, $salutation);

                $this->getUser()->logActivity(Shipserv_User_Activity::SIR_FORWARD_TO_CUSTOMER, 'SUPPLIER_BRANCH', $supplier->tnid);
            } catch (Exception $e) {
                $error = true;
            }

            $supplier->purgeMemcache();
        }

        // put the report to the response
        $response = new Myshipserv_Response("200", "OK", null);

        // convert response to JSON
        $this->_helper->json((array)$response->toArray());
    }

    public function transactionsAction()
    {
        Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        if (!$this->getActiveCompany()->id) {
            throw new Myshipserv_Exception_MessagedException("You are not allowed to perform this action.", 403);
        }

    	$this->_helper->layout->setLayout('default');

    	if( $this->params['yyyy'] == null || $this->params['yyyy'] == "" )
    	{
    		$this->params['yyyy'] = date('Y');
    	}

    	$report = new Shipserv_Tradenet_Usage;
    	$report->setParams($this->params);
    	$this->view->report = $report;
    	$this->view->params = $this->params;
    }

    /** UTILITIES * */
    public function preDispatch() {
        parent::preDispatch();
        if(in_array( $this->params['action'], array('smart', 'brands') ) === false ) {
        	//TODO check this funcition
        	 // $this->getUser();
        }
    }

    private function getUser() {
        if (!$this->user) {
            Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        }
        return $this->user;
    }

    private function getDb() {
        return $this->getInvokeArg('bootstrap')->getResource('db');
    }

    /**
     * Check if user have access to given TNID;
     *
     * @param int $tnid
     */
    private function _canAccess($tnid) {
        $supplier = Shipserv_Supplier::fetch($tnid, $this->getDb());
        $result = false;

        // gives an OK if it's shipserv user
        if ($this->getUser()->isShipservUser())
            return true;

        // check if he's part of the company
        $supplierTnids = $this->getUser()->fetchCompanies()->getSupplierIds();

        return ( in_array($tnid, $supplierTnids) );
    }

    /**
     * Making sure that the date has this format: YYYYMMDD
     * @param int $date
     */
    private function isDateFormat($date) {
        if (strlen($date) != 8) {
            return false;
        }
        if (!is_numeric($date)) {
            return false;
        }

        return true;
    }

    /**
     * Convert YYYYMMDD to DateTime format
     * @param string $inputDate
     */
    private function convertStringToDateTime($inputDate) {
        $date = new DateTime;
        $date->setDate(substr($inputDate, 0, 4), substr($inputDate, 4, 2), substr($inputDate, 6, 2));
        return $date;
    }

    private function convertToArray($string) {
        if (is_array($string))
            return $string;
        if (strstr($string, ",") !== false) {
            return explode(",", $string);
        } else {
            return array($string);
        }
    }

    private function getStandByDb() {
        $resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');

        return $resource->getDb('standbydb');
    }

    public function pagesAction()
    {
        Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        if (!$this->getActiveCompany()->id) {
            throw new Myshipserv_Exception_MessagedException("You are not allowed to perform this action.", 403);
        }

    	$this->_helper->layout->setLayout('default');

    	if( $this->params['yyyy'] == null || $this->params['yyyy'] == "" )
    	{
    		$this->params['yyyy'] = date('Y');
    	}


    	$report = new Shipserv_User_Activity;


    	$this->view->report = $report->getActivityStats($this->params);;

    }

    /**
     * $fields is the array to be serialised
     * $keys is an optional array of keys to be used
     *   if $keys is used, those only keys in $fields that exist in $keys will be used
     *   and keys that exist in $keys but are not in $fields will be added as empty positions
     */
    private function arrayToCsv($fields, $keys = false, $delimiter = ',', $enclosure = '"')
    {
        $str = '';
        $escape_char = '\\';

        $toCsv = $fields;

        if($keys) {
            $toCsv = array();
            foreach($keys as $key) {
                if (isset($fields[$key]) && !empty($fields[$key])) {
                    $toCsv[$key] = $fields[$key];
                }else {
                    $toCsv[$key] = '';
                }
            }
        }

        foreach ($toCsv as $value)
        {
            if (strpos($value, $delimiter) !== false ||
                strpos($value, $enclosure) !== false ||
                strpos($value, "\n") !== false ||
                strpos($value, "\r") !== false ||
                strpos($value, "\t") !== false ||
                strpos($value, ' ') !== false)
            {
                $str2 = $enclosure;
                $escaped = 0;
                $len = strlen($value);
                for ($i=0;$i<$len;$i++)
                {
                    if ($value[$i] == $escape_char)
                        $escaped = 1;
                    else if (!$escaped && $value[$i] == $enclosure)
                        $str2 .= $enclosure;
                    else
                        $escaped = 0;
                    $str2 .= $value[$i];
                }
                $str2 .= $enclosure;
                $str .= sprintf($str2).$delimiter;
            }
            else
                $str .= sprintf($value).$delimiter;
        }
        $str = substr($str,0,-1);
        $str .= "\n";
        return $str;
    }


    /**
     * SIR2 - Interface for Market
     */
    public function marketAction()
    {
    	$params = $this->params;

    	$startDate1 = ( $params['start'] != '' ) ? $params['start'] : null;
    	$endDate1 = ( $params['end'] != '' ) ? $params['end'] : null;
    	$startDate2 = ( $params['start2'] != '' ) ? $params['start2'] : null;
    	$endDate2 = ( $params['end2'] != '' ) ? $params['end2'] : null;

    	$ports = ( $params['location'] != '' ) ? $this->convertToArray($params['location']) : array();
    	$categories = ( $params['categories'] != '' ) ? $this->convertToArray($params['categories']) : array();
    	$brands = ( $params['brands'] != '' ) ? $this->convertToArray($params['brands']) : array();
    	$products = ( $params['products'] != '' ) ? $this->convertToArray($params['products']) : array();
    	$countries = ( $params['countries'] != '' ) ? $this->convertToArray($params['countries']) : array();

    	$adapter = new Shipserv_Adapters_Report();
    	$report = Shipserv_Report::getGlobalValueReport($startDate1, $endDate1, $startDate2, $endDate2, $ports, $categories, $brands, $products, $countries);
    	$report = $report->toArray();
    	$report = $report['general'];
    	$this->_helper->json((array)$report);
    }

    /**
     * New page for SIRv3
     * @throws Myshipserv_Exception_MessagedException
     */
    public function supplierInsightReportAction()
    {
    	$tnid = null;
    	$suppliers = array();
    	$params = $this->params;
    	$config = $this->config;

    	if ($user = $this->getUser())
    	{
    		$this->view->user = $user;
    	}

    	// get tnid from the textfield
    	if (isset($params["tnid"]) && $params["tnid"] != "") {
    		$tnid = $params["tnid"];
    	}

    	// for shipserv user -- check the IP address, makesure it's white listed
    	if ($user->isShipservUser())
    	{
    		if ( $user->canPerform('PSG_ACCESS_SIR') === false && $user->isPartOfCompany($tnid) === false ) {
    			if (!isset($_GET['logmeinplease'])) {
    				throw new Myshipserv_Exception_MessagedException("You are not allowed to access SIR as specified within your group: " . $user->getGroupName() );
    			}
    		}
    	}

    	// check if it's numeric
    	if (isset($params["tnid"]) && !is_numeric($params["tnid"])) {
    		throw new Myshipserv_Exception_MessagedException("Please check your TradeNet ID on your web browser. Make sure that it is a numeric", 400);
    	}

    	if ($tnid == null) {
    		if ($user->isShipservUser())
    		{
    			// mid performing supplier on shipserv
    			$tnid = 58983;
    		}
    		else
    		{
    			throw new Myshipserv_Exception_MessagedException("Sorry, you cannot access this page as you are NOT part of ANY suppliers.", 403);
    		}
    	}

    	$otp = new Myshipserv_Security_OneTimePassword($tnid, 300);
    	echo $otp->getKey();

    	die();

    	if( $user->isShipservUser() === false && $user->isPartOfCompany($tnid) === false )
    	{
    		throw new Myshipserv_Exception_MessagedException("Sorry, you cannot access this page as you are NOT part of this suppliers.", 403);
    	}

    	// show suppliers eventhough they're not published
        $this->view->unpublished = (Shipserv_Supplier::fetch($tnid, $this->getDb(), false)->tnid == "");
    	$supplier = Shipserv_Supplier::fetch($tnid, $this->getDb(), true);

    	if ($supplier->tnid == '') {
    		throw new Myshipserv_Exception_MessagedException("Please check your TradeNet ID on your web browser, System cannot find supplier with TNID: " . $tnid, 400);
    	}

    	if ($supplier->svrAccess == 'N' && $user->isShipservUser() === false)
    		throw new Myshipserv_Exception_MessagedException("Access to SIR is disabled for " . $supplier->name . ".", 403);

    	if ($supplier->tnid != null) {
    		$this->view->supplierProfile = $supplier;
    	} else {
    		throw new Myshipserv_Exception_MessagedException("Please check your TradeNet ID, System cannot find supplier with TNID: " . $tnid, 400);
    	}

    	$this->getUser()->logActivity(Shipserv_User_Activity::SIR_VIEW, 'SUPPLIER_BRANCH', $supplier->tnid);
    }


    /**
     *
     * @throws Myshipserv_Exception_MessagedException
     * @throws Exception
     * @examples
     *
     *
     * Conversion view
     * > Brand awareness
     * - http://dev4.myshipserv.com/reports/supplier-insight-data?tnid=52323&type=brand-awareness&startDate=01-JAN-2014&endDate=01-AUG-2014&skipTokenCheck=1
     *
     * Conversion view
     * > Lead conversion & win rates
     * - http://dev4.myshipserv.com/reports/supplier-insight-data?tnid=52323&type=lead-conversion&startDate=01-JAN-2014&endDate=01-AUG-2014&skipTokenCheck=1
     *
     * Conversion view
     * > Lead generation
     * - http://dev4.myshipserv.com/reports/supplier-insight-data?tnid=52323&type=lead-generation&startDate=01-JAN-2014&endDate=01-AUG-2014&skipTokenCheck=1
     *
     * Conversion view
     * > Lead conversion & win rates
     * >> PO value by Buyer analysis
     * - http://dev4.myshipserv.com/reports/supplier-insight-data?tnid=52323&type=po-value-by-buyer&startDate=01-JAN-2014&endDate=01-AUG-2014&skipTokenCheck=1
     *
     * Transaction and revenue view
     * > Other value events
     * - http://dev4.myshipserv.com/reports/supplier-insight-data?tnid=52323&type=pages-stats&startDate=01-JAN-2014&endDate=01-AUG-2014&skipTokenCheck=1
     */
    public function supplierInsightDataAction()
    {

        // DEV-2447 Speed up report(s)
        // DEV-2715
        Myshipserv_CAS_CasRest::getInstance()->sessionWriteClose();
        // security checks
    	// check if security token is valid
    	if( $this->params['token'] == '' && !isset($this->params['skipTokenCheck']) ){
    		throw new Myshipserv_Exception_MessagedException( 'Security token is missing', 200 );
    	}

    	$otp = new Myshipserv_Security_OneTimePassword();

    	if( ! isset($this->params['skipTokenCheck']) )
    	{
	    	try{
	    		$tnid = $otp->getData($this->params['token']);
	    	}
	    	catch(Myshipserv_Exception_MessagedException $e)
	    	{
	    		throw new Myshipserv_Exception_MessagedException( 'You are not allowed to access this page', 200 );
	    	}

	    	if( $tnid != $this->params['tnid'] ){
	    		throw new Myshipserv_Exception_MessagedException( 'You are not allowed to access this page', 200 );
	    	}
    	}

    	switch( $this->params['type'] )
    	{
    		case Shipserv_Report_Supplier_Insight_Brand::ADAPTER_NAME:
				$o = new Shipserv_Report_Supplier_Insight_Brand;
    			break;

    		case Shipserv_Report_Supplier_Insight_LeadGeneration::ADAPTER_NAME:
    			$o = new Shipserv_Report_Supplier_Insight_LeadGeneration;
    			break;

    		case Shipserv_Report_Supplier_Insight_Conversion::ADAPTER_NAME:
    			$o = new Shipserv_Report_Supplier_Insight_Conversion;
    			break;

    		case Shipserv_Report_Supplier_Insight_TransactionalSummary::ADAPTER_NAME:
    			$o = new Shipserv_Report_Supplier_Insight_TransactionalSummary;
    			break;

    		case Shipserv_Report_Supplier_Insight_PurchaseOrder::ADAPTER_NAME:
    			$o = new Shipserv_Report_Supplier_Insight_PurchaseOrder;
    			$o->setGroupBy('buyer');
    			break;

    		case Shipserv_Report_Supplier_Insight_Pages::ADAPTER_NAME:
    			$o = new Shipserv_Report_Supplier_Insight_Pages;
    			break;
            case Shipserv_Report_Supplier_Insight_Gmv::ADAPTER_NAME:
                $o = new Shipserv_Report_Supplier_Insight_Gmv;
                break;
            case Shipserv_Report_Supplier_Insight_AveQotTime::ADAPTER_NAME:
                $o = new Shipserv_Report_Supplier_Insight_AveQotTime;
                break;
            case  Shipserv_Report_Supplier_Insight_QotTimePriceSensitive::ADAPTER_NAME:
                $o = new  Shipserv_Report_Supplier_Insight_QotTimePriceSensitive;
                break;
            case  Shipserv_Report_Supplier_Insight_QcQotWonAllSuppliers::ADAPTER_NAME:
                $o = new  Shipserv_Report_Supplier_Insight_QcQotWonAllSuppliers;
                break;
            case  Shipserv_Report_Supplier_Insight_QcQotWonBySupplier::ADAPTER_NAME:
                $o = new  Shipserv_Report_Supplier_Insight_QcQotWonBySupplier;
                break;
            case  Shipserv_Report_Supplier_Insight_AveQotTimeByCompetitors::ADAPTER_NAME:
                $o = new  Shipserv_Report_Supplier_Insight_AveQotTimeByCompetitors;
                break;
            case  Shipserv_Report_Supplier_Insight_GetCustomers::ADAPTER_NAME:
                $o = new  Shipserv_Report_Supplier_Insight_GetCustomers;
                break;
            case  Shipserv_Report_Supplier_Insight_NotQuotedRfq::ADAPTER_NAME:
                $o = new  Shipserv_Report_Supplier_Insight_NotQuotedRfq;
                break;
            case  Shipserv_Report_Supplier_Insight_QotLostWorth::ADAPTER_NAME:
                $o = new  Shipserv_Report_Supplier_Insight_QotLostWorth;
                break;
             case  Shipserv_Report_Supplier_Insight_TradingStats::ADAPTER_NAME:
                $o = new  Shipserv_Report_Supplier_Insight_TradingStats;
                break;
             case  Shipserv_Report_Supplier_Insight_HowQuickestCheapestSupplierQuotes::ADAPTER_NAME:
                $o = new  Shipserv_Report_Supplier_Insight_HowQuickestCheapestSupplierQuotes;
                break;

    	}

    	if( $o == null )
    	{
    		throw new Exception("Incorrect URL. Please check your URL.", 404);
    	}



        switch( $this->params['type'] )
        {

            case Shipserv_Report_Supplier_Insight_PurchaseOrder::ADAPTER_NAME:

                if( $this->params['startDate1'] == "" && $this->params['endDate1'] == "" ) {

                    // get startDate and endDate of previous quarter
                    Shipserv_DateTime::previousQuarter($startDate1, $endDate1);

                } else {

                    if( $this->params['startDate1'] != "" ){
                        $startDate1 = Shipserv_DateTime::fromString($this->params['startDate1']);
                    }

                    if( $this->params['endDate1'] != "" ){
                        $endDate1 = Shipserv_DateTime::fromString($this->params['endDate1']);
                    }
                }

                if( $this->params['startDate2'] == "" && $this->params['endDate2'] == "" ) {

                    // get startDate and endDate of previous quarter
                    Shipserv_DateTime::previousQuarter($startDate2, $endDate2);

                } else {

                    if( $this->params['startDate2'] != "" ){
                        $startDate2 = Shipserv_DateTime::fromString($this->params['startDate2']);
                    }

                    if( $this->params['endDate2'] != "" ){
                        $endDate2 = Shipserv_DateTime::fromString($this->params['endDate2']);
                    }
                }

                break;
            default:

                if( $this->params['startDate'] == "" && $this->params['endDate'] == "" ) {

                    // get startDate and endDate of previous quarter
                    Shipserv_DateTime::previousQuarter($startDate, $endDate);

                } else {

                    if( $this->params['startDate'] != "" ){
                        $startDate = Shipserv_DateTime::fromString($this->params['startDate']);
                    }

                    if( $this->params['endDate'] != "" ){
                        $endDate = Shipserv_DateTime::fromString($this->params['endDate']);
                    }
                }
                break;
        }


    	// check if TNID is valid
    	//@todo

    	// setting up tnid
    	$o->setTnid($this->params['tnid']);

    	// setting up start and end date
        switch( $this->params['type'] )
        {
            case Shipserv_Report_Supplier_Insight_PurchaseOrder::ADAPTER_NAME:
                $o->setDateRangePeriod($startDate1, $endDate1, $startDate2, $endDate2);
                break;
            default:
                $o->setDatePeriod($startDate, $endDate);
                break;
        }

    	$data = $o->getData();

    	// make request to report service
    	$this->_helper->json((array)$data);
    }

    /**
    * Check if the tnid given in param should see the old, or new SIR (SIR3)
    * application.ini should contain comma separated list of tnid's or one tnid shipserv.sir3.show.tnid = tnid1,tnid2,tnid3....
    * @param integer $tnid
    * @return boolean
    */
    public function isSir3User( $tnid )
    {
        $config = $this->config;

        $showAll = ($config["shipserv"]["sir3"]["show"]["all"] == 1) ? true : false;

        if ($showAll === true) {
            return true;
        }

        $tnidConf = $config["shipserv"]["sir3"]["show"]["tnid"];

        if( strstr($tnidConf, ',') )
        {
            $tnids = explode(",", $tnidConf);
        }
        else
        {
            $tnids = array($tnidConf);
        }

        foreach ((array)$tnids as $sir3tnid )
        {
            if ($tnid == $sir3tnid) {
                return true;
            }
        }
        return false;
    }

    public function publisherAction()
    {

    	$user = Shipserv_User::isLoggedIn();
    	if( $user == false )
    	{
            Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
    	}

    	if( $this->params['admin'] == 1 ){

    		$this->abortIfNotShipMate();

    		if( $this->user->canPerform('PSG_ACCESS_MANAGE_PUBLISHER') === false )
    		{
    			throw new Myshipserv_Exception_MessagedException("You are not allowed to access this page as specified within your group: " . $this->user->getGroupName() );
    		}

    		$db = $this->db;

    		if( $this->params['adminAction'] == 'update' ){

    			if( !(
    					isset($this->params['companyType'])
    					&& isset($this->params['companyId'])
    					&& isset($this->params['siteId'])
    					&& isset($this->params['oldCompanyId'])
    					&& isset($this->params['oldSiteId'])
    			 ) ){
    				throw new Exception('Invalid request, please check your parameters.', 400);
    			}

    			$sql = "
    				UPDATE
    					pages_company_publisher
    				SET
    					pcp_company_type=:companyType,
    					pcp_company_tnid=:companyId,
    					pcp_adzerk_site_id=:siteId
    				WHERE
    					pcp_company_tnid=:oldCompanyId
    					AND pcp_adzerk_site_id=:oldsiteId

    			" ;
    			$params = array(
    				'companyType' => $this->params['companyType']
    				, 'companyId' => $this->params['companyId']
    				, 'siteId' => $this->params['siteId']
    				, 'oldsiteId' => $this->params['oldsiteId']
    				, 'oldCompanyId' => $this->params['oldCompanyId']
    			);
    			$db->query($sql, $params);
    			$db->commit();

    		}else if( $this->params['adminAction'] == 'insert'){
    			$sql = "
    				INSERT INTO
    					pages_company_publisher(pcp_company_type, pcp_company_tnid, pcp_adzerk_site_id)
    					VALUES (:companyType, :companyId, :siteId)
    			" ;
    			$params = array(
    				'companyType' => $this->params['companyType']
    				, 'companyId' => $this->params['companyId']
    				, 'siteId' => $this->params['siteId']
    			);
    			$db->query($sql, $params);
    			$db->commit();
    		}else if( $this->params['adminAction'] == 'delete'){
    			$sql = "DELETE FROM pages_company_publisher WHERE pcp_adzerk_site_id=:siteId AND pcp_company_tnid=:companyId" ;
    			$params = array(
    				'companyId' => $this->params['companyId']
    				, 'siteId' => $this->params['siteId']
    			);

    			$db->query($sql, $params);
    			$db->commit();
    		}

    		$this->redirect('/reports/publisher');
    	}

    	$adapter = new Shipserv_Adapters_Adzerk;

    	$this->view->tnid = $params["tnid"] ? $params["tnid"] : $this->getActiveCompany()->id;
    	// override
    	$params['tnid'] = $this->view->tnid;


    	// processing
    	$tmp = explode("/", $this->params['datefrom']);
    	$startDate = $tmp[1] . '/' . $tmp[0] . '/' . $tmp[2];

    	$tmp = explode("/", $this->params['dateto']);
    	$endDate = $tmp[1] . '/' . $tmp[0] . '/' . $tmp[2];
    	$data = $adapter->requestReport($startDate, $endDate, $this->params['siteId']);
    	$this->view->report = $data;

    	if( $this->user->isShipservUser() )
    	{
    		//$this->view->publisher = $adapter->getListOfPublisher();
    		$this->view->site = $adapter->getListOfSite();
    	}
    	else
    	{
	    	//$this->view->publisher = $adapter->getListOfPublisher($params['tnid'] );
	    	$this->view->site = $adapter->getListOfSite($params['tnid']);
    	}

    	$this->view->data = $adapter->getActivePublisherInPages();
    }

    public function publisherDataAction()
    {
    	$adapter = new Shipserv_Adapters_Adzerk;
    	$data = $adapter->requestReport('1/1/2014', '12/30/2014', 11692);
    	$this->_helper->json((array)$data);
    }

    /*
     * Brand managament report
     */
    public function brandmanagementAction()
    {

        $this->abortIfNotShipMate();

        $this->_helper->layout->setLayout('default');
        $report = new Shipserv_Report_Dashboard_Brandmanagement;

        $sum= $report->getSummaryData();
        $sumData = array_pop($sum);

        $this->view->summaryInfo = $sumData;

    }

    /**
    * Internal supplier KPI dashboard action
    */
    public function internalSupplierKpiAction()
    {
        $this->abortIfNotShipMate();
    }

    /*
    * Internal Supplier KPI report, json service
    */
    public function internalSupplierKpiReportAction()
    {
        $this->abortIfNotShipMate();
        $data = array();
        $report = new Shipserv_Report_Dashboard_InternalSupplierKpi;
        $data = $report->getResult();
        $this->_helper->json((array)$data);
    }

    public function brandreportAction()
    {
        $this->abortIfNotShipMate();
        $report = new Shipserv_Report_Dashboard_Brandmanagement;
        $data = $report->getData();
        $this->_helper->json((array)array('data' => $data));
    }

    /**
    * Return list of TNID's belong to a user
    */
    public function supplierCompaniesListAction()
    {
     
        $user = $this->getUser();
        if (is_object($user)) {

            $supplierList = new Shipserv_Oracle_PagesUserSupplier();
            //$data = $supplierList->fetchSuppliersForUser($user->userId);
            $data = $supplierList->getSupplierTreeByBranchCode($this->getActiveCompany()->id);

            $this->_helper->json((array)array(
                'data' => $data,
                ));
            } else {
                throw new Myshipserv_Exception_MessagedException("Cannot find user", 500);
            }
    }

    
    /**
    * KPI Trend report
    */
    public  function kpiTrendAction()
    {
        $user = $this->getUser();
        if ($user) {
            if (!$user->canAccessKpiTrendReport()) {
                throw new Myshipserv_Exception_MessagedException("Smart and Expert supplier only", 403);
            } 
        } else { 
                throw new Myshipserv_Exception_MessagedException("Your do not have access to see this report.", 403);
        }

        $this->view->tnid = $this->getActiveCompany()->id;
        $this->view->sir3 =  $this->params['status'] == 'new';
    }

    /**
    * Returns Json for KPI Trend Report
    */
    public  function kpiTrendReportAction()
    {
        //Get report data
        if ($this->params['datefrom'] != '') {
            $report = Shipserv_Report_KpiTrendReport::getInstance($this->params['tnid'], $this->params['showChild'], $this->params['datefrom'], $this->params['dateto'] );
        } else {
            $report = Shipserv_Report_KpiTrendReport::getInstance($this->params['tnid'], $this->params['showChild']);
        }
       
       switch ($this->params['type']) {
           case 'volume':
               $data = $report->getReport(Shipserv_Report_KpiTrendReport::REPORT_VOLUME);
               break;
           case 'directvolume':
               $data = $report->getReport(Shipserv_Report_KpiTrendReport::DIRECT_VOLUME);
               break;
           case 'gmv':
               $data = $report->getReport(Shipserv_Report_KpiTrendReport::REPORT_GMV);
               break;
            case 'qot':
               $data = $report->getReport(Shipserv_Report_KpiTrendReport::REPORT_QUOTE);
               break;
           default:
               throw new Myshipserv_Exception_MessagedException("Invalid report type", 500);
               break;
       }
       
       $this->_helper->json((array)array('data' => $data));

    }

    /**
    * Match Supplier Report action
    * Author: Attila O 04/09/2015
    */
    public function matchSupplierReportAction()
    {

    	$user = $this->getUser();

        $user->logActivity(Shipserv_User_Activity::SPEND_BENCHMARK_LAUNCH, 'PAGES_USER', $user->userId, $user->email);
        $canAccess = true;

        if ($user) {
            if (!$user->canAccessMatchBenchmark()) {
                $canAccess = false;
                
            }
            if (!$user->canAccessMatchBenchmarkBeta()) {
                $canAccess = false;
            }
        } else {
            $canAccess = false;
        }

        if ($canAccess == false) {
             $options =  Shipserv_Profile_Menuoptions::getInstance()->getAnalyseTabOptions();
             if (count($options) > 0) {
                $this->redirect($options[0]['href']);
                die();
             } else {
                $this->redirect('/search');
                die();
             }
        }

        if ($this->getActiveCompany()->type != 'b') {
                throw new Myshipserv_Exception_MessagedException("Your active company is not a buyer, please choose a buyer to see its report.", 403);
        }
            
        //S20380 Restrict access to non full members
        if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
        	$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'analyse'));
        };
        
        $this->view->activeCompanyForLink = $this->getActiveCompany()->id;
        $this->view->showPriceBenchmarkMenu = $this->user->canAccessPriceBenchmark();
    }

    /**
    * Match Supplier Report action, Returns JSON, Requeres post or get param type (supplier, quote, supplier-branch)
    * Author: Attila O 04/09/2015
    * @return string JSON
    */
    public function matchSupplierReportDataAction()
    {
    	$bybBranchCode = $this->params['bybBranchCode'];
        
        $keyWordId =  $this->getRequest()->getParam('keyword');
        $keyWordId = ($keyWordId === "") ? null : (int)$keyWordId;
        
        $segmentId =  $this->getRequest()->getParam('segment');
        $segmentId = ($segmentId === "") ? null : (int)$segmentId;
        
        $quality =  $this->getRequest()->getParam('quality');
        if ($quality === "" || $quality === null) {
        	$quality = null;
        } else {
        	if (!preg_match('/^([0-9]+,?)+$/', $quality)) {
        		throw new Myshipserv_Exception_MessagedException(
        				"The quality parameter must contain a list of comma separeted nuber or an individual number, or should be empty, or not set",
        				500
        				);
        	}
        	$quality = explode(",", $quality);
        }
        
        $sameq= ($this->getRequest()->getParam('sameq') === "true") ? true : false;
        
        $purgeCache = ($this->params['purgeCache'] == 'purge');
         if ( $this->getActiveCompany()->type != 'b')
            {
                throw new Myshipserv_Exception_MessagedException("Your active company is not a buyer, please choose a buyer to see its report.", 403);
            }
       //Select wich report should be returned
        switch ($this->params['type']) {
            case 'supplier':
                $report = new Shipserv_Report_Supplier_Match();
                $data = $report->getSupplierList($bybBranchCode, $this->params['vessel'], $segmentId, $keyWordId, $quality, $sameq, (int)$this->params['date'] ,(int)$this->params['page'],(int)$this->params['itemPerPage'], $purgeCache);
                break;
            case 'supplier-aggregated':
            	$report = new Shipserv_Report_Supplier_Match();
            	$data = $report->getSupplierList($bybBranchCode, $this->params['vessel'], $segmentId, $keyWordId, $quality, $sameq, (int)$this->params['date'] ,(int)$this->params['page'],(int)$this->params['itemPerPage'], $purgeCache, false, (int)$this->params['spbBranchCode']);
            	break;
            case 'quote':
                $report = new Shipserv_Report_Supplier_Match();
                $data = $report->getQuoteList((int)$this->params['spbBranchCode'],  $bybBranchCode, $this->params['vessel'], $segmentId, $keyWordId, $quality, $sameq, (int)$this->params['date'] ,(int)$this->params['page'],(int)$this->params['itemPerPage']);
                break;
            case 'supplier-branch':
                $report = new Shipserv_Report_Supplier_Match();
                $data = $report->getSupplierData((int)$this->params['spbBranchCode'], $bybBranchCode, (int)$this->params['date']);
                break;
            case 'quote-quality':
            	$oracleReference = new Shipserv_Oracle_Reference($this->getInvokeArg('bootstrap')->getResource('db'));
            	$this->_helper->json((array)$oracleReference->fetchQuoteQualities());
            	break;
            default:
                throw new Myshipserv_Exception_MessagedException("Invalid parameter value for type (".$this->params['type'].")", 500);
                break;
        }

        $this->_helper->json((array)$data);
    }

    /**
    * Log events occured on Frontend side
    */
    public function logJsEventAction()
    {
         $user = Shipserv_User::isLoggedIn();
         if ($user) {
             $event = (array_key_exists('event', $this->params)) ? $this->params['event'] : '';
             $info = (array_key_exists('info', $this->params)) ? ($this->params['info'] == '') ? $user->email : $this->params['info'] : $user->email;
             $activity =  new Shipserv_User_Activity();
             $user->logActivity($activity->getTransformedActivityType($event), 'PAGES_USER', $user->userId, $info);
            
             $this->_helper->json((array)array('Ok'));
        } else {
             $this->_helper->json((array)array('Error: Not logged in'));
        }
    }

    /*
    * Export Spend Benchmarking report data
    * Author: Attila O 27/01/2016
    * @return Exported CSV
    */
    public function matchSupplierReportExportAction()
    {

        set_time_limit( 0 );
        $supplierBranchCode = null;
        $bybBranchCode = $this->params['bybBranchCode'];
        
        $keyWordId =  $this->getRequest()->getParam('keyword');
        $keyWordId = ($keyWordId === "") ? null : (int)$keyWordId;
        
        $segmentId =  $this->getRequest()->getParam('segment');
        $segmentId = ($segmentId === "") ? null : (int)$segmentId;
        
        $quality =  $this->getRequest()->getParam('quality');
        if ($quality === "" || $quality === null) {
        	$quality = null;
        } else {
        	if (!preg_match('/^([0-9]+,?)+$/', $quality)) {
        		throw new Myshipserv_Exception_MessagedException(
        				"The quality parameter must contain a list of comma separeted nuber or an individual number, or should be empty, or not set",
        				500
        				);
        	} 
        	$quality = explode(",", $quality);
        }

        $sameq= ($this->getRequest()->getParam('sameq') === "true") ? true : false;
        
        $purgeCache = ($this->params['purgeCache'] == 'purge');
        if ( $this->getActiveCompany()->type != 'b')
        {
			throw new Myshipserv_Exception_MessagedException("Your active company is not a buyer, please choose a buyer to see its report.", 403);
        }
       //Select wich report should be returned
        switch ($this->params['type']) {
            case 'supplier':
                $report = new Shipserv_Report_Supplier_Match();
                $data = $report->getSupplierList($bybBranchCode, $this->params['vessel'],$segmentId,  $keyWordId, $quality, $sameq, (int)$this->params['date'] ,1,25, $purgeCache, false);
                break;
            case 'quote':
                $report = new Shipserv_Report_Supplier_Match();
                $data = $report->getQuoteList((int)$this->params['spbBranchCode'],  $bybBranchCode, $this->params['vessel'], $segmentId, $keyWordId, $quality, $sameq, (int)$this->params['date'] ,1,25, false);
                $supplierBranchCode = $this->params['spbBranchCode'];
                break;
            default:
                throw new Myshipserv_Exception_MessagedException("Invalid parameter value for type (".$this->params['type'].")", 500);
                break;
        }

        $this->getResponse()->appendBody($report->exportToExcel($this->_helper,  $this->getResponse(), $data['data'], $this->params['type'], $supplierBranchCode ));

    }


	public function vesselsAction()
	{
		$this->abortIfNotShipMate();

		//Init params and views attributes
		$this->view->name = $name = trim($this->getRequest()->getParam('name'));
		$this->view->country = $country = $this->getRequest()->getParam('country');
		$this->view->allCountries = Shipserv_Report::getVesselesCountries();
		$this->view->byOwner = false;
		$this->view->byManager = false;
		$this->view->ownerCount = 0;
		$this->view->managerCount = 0;
		$types = (array) $this->getRequest()->getParam('types');
		//if no type is given, assume the user was searching for both types
		if (!$types) {
		    $types = array('owner', 'manager');
		}
		
		//Launch the query
		$results = Shipserv_Report::getVesseles($name, $country, $types);

		//Count manager and owner entries matching
		$count = array('owner' => 0, 'manager' => 0);
		foreach ($results as &$line) {
		    $count['owner'] += $line['OWNER_IS_MATCHING'];
		    $count['manager'] += $line['MANAGER_IS_MATCHING'];
		    unset($line['OWNER_IS_MATCHING'], $line['MANAGER_IS_MATCHING']);
		}
		$this->view->ownerCount = $count['owner'];
		$this->view->managerCount = $count['manager'];
		$this->view->totalCount = count($results);
		
		if (in_array('owner', $types)) {
			$this->view->byOwner = true;
		}
		if (in_array('manager', $types)) {
			$this->view->byManager = true;
		}
		
		//Bind the result to view
		$this->view->results = $results;
    }


}
