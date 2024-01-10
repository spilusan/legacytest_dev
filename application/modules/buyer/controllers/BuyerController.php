<?php
class Buyer_BuyerController extends Myshipserv_Controller_Action {
	/**
	 * Initialise the controller - set up the context helpers for AJAX calls
	 * @access public
	 */
	public function init() {
		parent::init();
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext->addActionContext('rfq-inbox', 'html')->initContext();

		$this->activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
	}

	
	public function gmvTrendAction()
	{
		$this->_helper->layout->setLayout('simple');
		if($this->params['tnid'] != ""){
			$this->slowLoadingPage();

			$report = new Shipserv_Report_Buyer_Gmv;
			$buyer = Shipserv_Buyer::getBuyerBranchInstanceById( $this->params['tnid'] );
			$this->view->data = $report->getTrends($this->params['tnid'], ($this->params['parent']==1));
			$this->view->vesselData = $report->getAverageGMVPerVesselTrends($this->params['tnid'], ($this->params['parent']==1));
			$this->view->totalVesselData = $report->getTotalGMVPerVesselTrends($this->params['tnid'], ($this->params['parent']==1));

			$this->view->periodForTrends = $report->periodForTrends;
			$this->view->buyer = $buyer;
		}else{
			throw new Myshipserv_Exception_MessagedException('No TNID specified');
		}
	}

	
	public function spendGraphAction()
	{
       
        if (!$this->user) {
            Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        }

        //S20380 Restrict access to non full members
        if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
        	$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'analyse'));
        };
        
		$config = $this->config;
		$this->view->selectedNav = 'analyse';
		if( $this->activeCompany->type != 'b'){
			throw new Myshipserv_Exception_MessagedException('This page is only for buyer.');
		}

		// if user is not shipmate, then check if the active buyer ID is in the white listed on config.ini
		if( $this->user->isShipservUser() == false){
			if( false === in_array($this->activeCompany->id, explode(",", $config['shipserv']['buyerSpendTrendGraph']['participant']['buyerId']))){
				throw new Myshipserv_Exception_MessagedException('Your company is not enabled to view this page.');
			}
		}

		$this->slowLoadingPage();
		$report = new Shipserv_Report_Buyer_Gmv;

		if( $this->activeCompany->id != "" ){
			$buyer = Shipserv_Buyer::getInstanceById( $this->activeCompany->id );
			$this->view->data = $report->getTrends($buyer->getBranchesTnid(true), true);
			$this->view->vesselData = $report->getAverageGMVPerVesselTrends($buyer->getBranchesTnid(true), true);
			$this->view->branches= $buyer->getBranchesTnid(false);
		}

		if( $this->params['tnid'] != "" ) {
			$buyer = Shipserv_Buyer::getBuyerBranchInstanceById( $this->params['tnid'] );
			$this->view->data = $report->getTrends($this->params['tnid'], ($this->params['parent']==1));
			$this->view->vesselData = $report->getAverageGMVPerVesselTrends($this->params['tnid'], ($this->params['parent']==1));
		}

		$this->view->periodForTrends = $report->periodForTrends;
		$this->view->buyer = $buyer;

		$this->view->showPriceBenchmarkMenu = $this->user->canAccessPriceBenchmark();
		$this->user->logActivity(Shipserv_User_Activity::TOTAL_SPEND_REPORT_SERVED, 'PAGES_USER', $this->user->userId, $this->user->email);
	}

	
	public function gmvAction()
	{
		// handles saving of information needed for buyer GMV report
		if( $this->params['a'] != "" )
		{
			if( $this->params['information'] != "" && $this->params['type'] != "" && $this->params['buyerTnid'] != "" )
			{
				if( ctype_digit($this->params['buyerTnid']) == true )
				{
					$buyer = Shipserv_Buyer::getBuyerBranchInstanceById( $this->params['buyerTnid'] );
					if( $buyer->saveDetail($this->params['type'], $this->params['information']) )
					{
						$result = array('result' => true );
					}
					else
					{
						throw new Myshipserv_Exception_MessagedException('Error when saving detail to database');
					}
				}
				else
				{
					throw new Myshipserv_Exception_MessagedException('invalid buyer tnid');
				}
			}
			else
			{
				throw new Myshipserv_Exception_MessagedException('invalid parameters');
			}

			$this->_helper->json((array)array('result' => 'ok'));
		}
		else
		{

	        $user = Shipserv_User::isLoggedIn();
	        if ($user) {
	            $user->logActivity(Shipserv_User_Activity::GMV_BUYER, 'PAGES_USER', $user->userId, $user->email);
	        }
			$report = new Shipserv_Report_Buyer_Gmv;
			$this->view->accountManager = $report->getAccountManager();
			$this->view->region = $report->getRegion();
			$this->view->contractType = $report->getContractType();
			$oldTimeOut = ini_set('max_execution_time', 0);
			if( $this->params['fromDate'] != "" )
			{
				$this->slowLoadingPage();

				$report->setParams($this->params);
				$stopwatch = new Shipserv_Helper_Stopwatch(true);
				$this->view->results = $report->getData();
				$this->view->timeElapsed = $stopwatch->getTotal();
			}
		}
	}

	public function gmvBySupplierAction()
	{
		$report = new Shipserv_Report_Buyer_Gmv_Supplier;
		$oldTimeOut = ini_set('max_execution_time', 0);
		if( $this->params['fromDate'] != "" )
		{
			$report->setParams($this->params);
			$this->view->report = $report;
			$this->view->results = $report->getData();
			$this->view->buyer = $report->getBuyer();
		}
	}

	public function gmvBySupplierInteractedWithAction()
	{
		$report = new Shipserv_Report_Buyer_Gmv_Supplier;
		$oldTimeOut = ini_set('max_execution_time', 0);
		if( $this->params['fromDate'] != "" )
		{
			$report->setParams($this->params);
			$this->view->report = $report;
			$this->view->results = $report->getSupplierInteractedWithData();
			$this->view->buyer = $report->getBuyer();
		}
	}

	public function gmvByPurchaserAction()
	{
		$report = new Shipserv_Report_Buyer_Gmv_Purchaser;
		$oldTimeOut = ini_set('max_execution_time', 0);
		if( $this->params['fromDate'] != "" )
		{
			$this->slowLoadingPage();
			$report->setParams($this->params);
			$this->view->report = $report;
			$this->view->results = $report->getData();
			$this->view->buyer = $report->getBuyer();
		}
	}

	public function gmvByTradingUnitAction()
	{
		$report = new Shipserv_Report_Buyer_Gmv_TradingUnit;
		$oldTimeOut = ini_set('max_execution_time', 0);
		if( $this->params['fromDate'] != "" )
		{
			$this->slowLoadingPage();
			$report->setParams($this->params);
			$this->view->report = $report;
			$this->view->results = $report->getData();
			$this->view->buyer = $report->getBuyer();
		}
	}

	public function indexAction()
	{
		//Buyer tab is clicked
		$user = Shipserv_User::isLoggedIn();
		$user->logActivity(Shipserv_User_Activity::BUY_TAB_CLICKED, 'PAGES_USER', $user->userId, $user->email);
		$this->redirect("/buyer/rfq");
	}

	private function _getSessionHash()
	{
		return md5('TradeController::_getSessionHash_' . session_id() . "_" . $this->activeCompany->id);
	}

    /**
     * Helper function that returns a buyer-user session storage
     *
     * @date    2014-10-15
     * @story   S11021
     *
     * @param   Shipserv_Buyer  $buyer
     * @param   Shipserv_User   $user
     *
     * @return Zend_Session_Namespace
     */
    protected function _getBuyerPagesStorage(Shipserv_Buyer $buyer, Shipserv_User $user) {
        $key = implode('_', array(
            'buyerModule',
            $buyer->id,
            $user->userId
        ));

        $storage = Myshipserv_Helper_Session::getNamespaceSafely($key);

        return $storage;
    }

    
	public function rfqAction()
	{
        // only selected buyers and shipmates are allowed to access this function so far
        $user = $this->abortIfNotLoggedIn();

		if (!$user->canAccessFeature($user::BRANCH_FILTER_BUY)) {
			throw new Myshipserv_Exception_MessagedException('Page not found', 404);
		}
        
        //S20380 Restrict access to non full members
        if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
        	$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'buy'));
        };

		$this->_helper->layout->setLayout('default');
		$this->view->hash = $this->_getSessionHash();
		$this->view->params = $this->params;

		if ($this->params['enquiryId'] != "" && $this->params['tnid'] != "" && $this->params['hash'] != "") {
			$enquiry = Shipserv_Enquiry::getInstanceByIdAndTnid($this->params['enquiryId'], $this->params['tnid']);
			if ($enquiry->pinHashKey != $this->params['hash']) {
				throw new Myshipserv_Exception_MessagedException("We cannot find your RFQ, please check your url");
			}
		}

        $user = Shipserv_User::isLoggedIn();
		if ($user === false) {
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

        // added by Yuriy Akopov on 2013-11-29
        try {
            $buyerOrg = $this->getUserBuyerOrg();
            $buyerIds = $buyerOrg->getBranchesTnid(true, true);
        } catch (Exception $e) {
            throw new Myshipserv_Exception_MessagedException("Please select your buyer company before you can view your RFQ outbox", 200);
        }

        if (empty($buyerIds)) {
            throw new Myshipserv_Exception_MessagedException("Selected company does not have associated buyer branches, it is impossible to use RFQ outbox. Please select a different company.", 200);
        }

        // added by Yuriy Akopov by Attila's request
        $this->view->activeCompany = $this->activeCompany;

        $storage = $this->_getBuyerPagesStorage($buyerOrg, $user);
        $this->view->activeBuyerBranchId = $storage->activeBuyerBranchId;
	}

	
	public function quoteAction()
	{
        // only selected buyers and shipmates are allowed to access this function so far
        $user = $this->abortIfNotLoggedIn();

		if (!$user->canAccessFeature($user::BRANCH_FILTER_BUY)) {
			throw new Myshipserv_Exception_MessagedException('Page not found', 404);
		}

        if (!($user->isShipservUser())) {
            throw new Myshipserv_Exception_MessagedException("Sorry, you don't have access to this functionality yet as it is still in beta.");
        }

        //S20380 Restrict access to non full members
        if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
        	$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'buy'));
        };
        
		$this->_helper->layout->setLayout('default');
		$this->view->hash = $this->_getSessionHash();
		$this->view->params = $this->params;

		if( $this->params['enquiryId'] != "" && $this->params['tnid'] != "" && $this->params['hash'] != "" )
		{
			$enquiry = Shipserv_Enquiry::getInstanceByIdAndTnid($this->params['enquiryId'], $this->params['tnid']);
			if( $enquiry->pinHashKey != $this->params['hash'])
			{
				throw new Myshipserv_Exception_MessagedException("We cannot find your Quote, please check your url");
			}
		}

		/*if( $this->activeCompany->type === 'v' )
		{
			throw new Myshipserv_Exception_MessagedException("There is no RFQ Outbox for Suppliers on ShipServ Pages", 200);
		}*/

        $user = Shipserv_User::isLoggedIn();
        if( $user === false )
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

        $buyerOrg = $this->getUserBuyerOrg();
        $storage = $this->_getBuyerPagesStorage($buyerOrg, $user);
        $this->view->activeBuyerBranchId = $storage->activeBuyerBranchId;
	}

	public function poAction()
	{
		$this->_helper->layout->setLayout('default');
		$this->view->hash = $this->_getSessionHash();
		$this->view->params = $this->params;

		$user = Shipserv_User::isLoggedIn();

		if( $this->params['enquiryId'] != "" && $this->params['tnid'] != "" && $this->params['hash'] != "" )
		{
			$enquiry = Shipserv_Enquiry::getInstanceByIdAndTnid($this->params['enquiryId'], $this->params['tnid']);
			if( $enquiry->pinHashKey != $this->params['hash'])
			{
				throw new Myshipserv_Exception_MessagedException("We cannot find your Order, please check your url");
			}
		}

		/*if( $this->activeCompany->type === 'v' )
		{
			throw new Myshipserv_Exception_MessagedException("There is no RFQ Outbox for Suppliers on ShipServ Pages", 200);
		}*/

		if( $user === false )
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}
		
		//S20380 Restrict access to non full members
		if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
			$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'buy'));
		};
	}

    /**
     * A webservice to remember the last buyer branch ID selected by user
     *
     * @date    2014-10-15
     * @story   S11021
     *
     * @throws Myshipserv_Exception_MessagedException
     */
    public function rememberBuyerBranchAction() {
        $branchId = $this->_getParam('buyerBranchId');

        $user = $this->abortIfNotLoggedIn();
        if (!($user->isShipservUser() or $user->isPartOfMatchBetaAccess())) {
            // access restriction lifted in 4.8
            // throw new Myshipserv_Exception_MessagedException("Sorry, you don't have access to this functionality yet as it is still in beta.");
        }

        $buyerOrg = $this->getUserBuyerOrg();

        $storage = $this->_getBuyerPagesStorage($buyerOrg, $user);
        $storage->activeBuyerBranchId = $branchId;

        $this->_helper->json((array)array('result' => 'ok'));
    }

	/**
	 * Rewritten by Yuriy Akopov on 2015-12-14. The workflow resembles /supplier/decline a lot, but I am not sure if it's okay to join them together
	 *
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public function cancelRfqAction()
	{
		$this->_helper->layout->setLayout('simple');
		$this->view->docType = $this->params['doc'];

		// check authentication cache // @todo: Is this really secure enough?! (Yuriy)
		switch ($this->params['doc']) {
			case 'rfq':
				$hashIdParam = 'rfqInternalRefNo';
				break;

			case 'ord':
				$hashIdParam = 'ordInternalRefNo';
				break;

			default:
				throw new Myshipserv_Exception_MessagedException("Unknown document type", 200);
		}

		// check the hash unless in development environment
		if (Myshipserv_Config::getEnv() !== Myshipserv_Config::ENV_DEV) {
			if (strtolower($this->params['h']) !== md5($hashIdParam . '=' . $this->params['id'] . '&spbBranchCode=' . $this->params['s'])) {
				throw new Myshipserv_Exception_MessagedException ("You are not authorised to access this page", 200);
			}
		}

		// authentication passed, instantiate the document
		try {
			switch ($this->params['doc']) {
				case 'rfq':
					$rfq = Shipserv_Rfq::getInstanceById($this->params['id']);
					if (!is_object($rfq) or (strlen($rfq->rfqInternalRefNo) === 0)) {
						throw new Exception("RFQ " . $this->params['id'] . " cannot be found");
					}

					// if we are here, a valid RFQ has been found
					// added by Yuriy Akopov on 2015-12-03, DE6134
					if ($rfq->rfqBybBranchCode == Myshipserv_Config::getProxyMatchBuyer()) {
						$sourceRfq = $rfq->resolveMatchForward();
						$buyer = $sourceRfq->getBuyerBranch();
					} else {
						$buyer = $rfq->getBuyerBranch();
					}
					// changes by Yuriy Akopov end

					$rfqSupplierIds = $rfq->getDirectSupplierIds();
					if (!in_array($this->params['s'], $rfqSupplierIds)) {
						throw new Myshipserv_Exception_MessagedException ("Invalid transaction parameters supplied", 200);
					}

					$suppliers = array();
					$supplierInfo = $rfq->getSuppliers();
					$uniqueSupplierIds = array();
					foreach ($supplierInfo as $spb) {
						$curSupplierId = $spb[Shipserv_Rfq::RFQ_SUPPLIERS_BRANCH_ID];
						if (!in_array($curSupplierId, $uniqueSupplierIds)) {
							$suppliers[] = Shipserv_Supplier::getInstanceById($curSupplierId, '', true);
							$uniqueSupplierIds[] = $curSupplierId;
						}
					}

					$docLabel    = "RFQ";
					$docRefNo    = $rfq->rfqRefNo;
					$docUrl      = $rfq->getUrl('buyer');
					$docSubject  = $rfq->rfqSubject;
					$docPort     = $rfq->rfqDeliveryPort;
					$docDeliveryDate = $rfq->rfqDateTime;
					$docDate     = $rfq->rfqCreatedDate;
					$docVessel   = $rfq->rfqVesselName;
					$docCancelUrl = $rfq->getCancelUrl($this->params['s']);

					$doc = $rfq;
					break;

				case 'ord':
					$ord = Shipserv_PurchaseOrder::getInstanceById($this->params['id']);
					if (!is_object($ord) or (strlen($ord->ordInternalRefNo) === 0)) {
						throw new Exception("Order " . $this->params['id'] . " cannot be found");
					}

					// if we are here, a valid order has been found
					$buyer      = $ord->getBuyerBranch();
					$supplier   = $ord->getSupplier();

					if ((strlen($this->params['s']) === 0) or ($this->params['s'] != $supplier->tnid)) {
						throw new Myshipserv_Exception_MessagedException ("Invalid transaction parameters supplied", 200);
					}

					$suppliers = array($supplier);

					$docLabel   = "Purchase Order";
					$docRefNo   = $ord->ordRefNo;
					$docUrl     = $ord->getUrl('buyer');
					$docSubject = $ord->ordSubject;
					$docPort    = $ord->ordDeliveryPort;
					$docDeliveryDate = $ord->ordDateTime;
					$docDate    = $ord->ordCreatedDate;
					$docVessel  = $ord->ordVesselName;
					$docCancelUrl = $ord->getCancelUrl();

					$doc = $ord;
					break;

				default:
					// should not really happen as we have checked the type above already
					throw new Exception("Unknown document type");
			}
		} catch (Exception $e) {
			throw new Myshipserv_Exception_MessagedException("Your Purchase Order or RFQ cannot be found", 200);
		}

		$this->view->docType     = $this->params['doc'];
		$this->view->buyer       = $buyer;

		usort($suppliers, function($a, $b) {
			if ($a->name > $b->name) return 1;
			if ($a->name < $b->name) return -1;
			return 0;
		});
		$this->view->suppliers   = $suppliers;

		$this->view->docLabel    = $docLabel;
		$this->view->docRefNo    = $docRefNo;
		$this->view->docUrl      = $docUrl;
		$this->view->docSubject  = $docSubject;
		$this->view->docPort     = $docPort;

		$normaliseDate = function ($dateStr) {
			if (strlen($dateStr) === 0) {
				return null;
			}

			try {
				$dateTime = new DateTime($dateStr);
				return $dateTime->format('d M Y');
			} catch (Exception $e) {
				return $dateStr;
			}
		};

		$this->view->docDeliveryDate = $normaliseDate($docDeliveryDate);
		$this->view->docDate     	 = $normaliseDate($docDate);

		$this->view->docVessel    = $docVessel;
		$this->view->docCancelUrl = $docCancelUrl;

		$this->view->isProduction = (Myshipserv_Config::getEnv() === Myshipserv_Config::ENV_LIVE);

		$portAdapter = new Shipserv_Oracle_Ports(Shipserv_Helper_Database::getDb());
		$this->view->docPortLabel = $portAdapter->getPortLabelByCode($docPort);

		if (strlen($this->params['a']) > 0) {
			// check if decline request is valid
			if (md5($this->params['h']) != $this->params['a'] ) {
				throw new Myshipserv_Exception_MessagedException("You are not authorised to access this page", 200);
			}

			if ($doc instanceof Shipserv_Rfq) {
				// support for cancelling notifications for only one suppliers was revoked by Stuart
				/*
				if (strlen($this->params['cancelAll'])) {
					$supplierToCancel = null;
				} else {
					$supplierToCancel = $this->params['s'];
				}
				*/

				$this->view->success = (bool) $doc->cancel(null);

			} else if ($doc instanceof Shipserv_PurchaseOrder) {
				$this->view->success = $doc->cancel();
			}
		}
	}
}
