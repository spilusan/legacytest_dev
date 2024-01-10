<?php
/**
 * Controller actions for SPR report
 * 
 * @author attilaolbrich
 *
 */
class Spr_IndexController extends Myshipserv_Controller_Action
{

    protected $shipmate;
	/**
	 * Initalise action parameters
	 * {@inheritDoc}
	 * @see Myshipserv_Controller_Action::init()
	 * 
	 * @return unknown
	 */
	public function init()
	{
		parent::init();
		
		$user = Shipserv_User::isLoggedIn();
		
		if (!$user) {
			throw new Myshipserv_Exception_MessagedException('This page requires you to be logged in', 403);
		}
		
		if ($user->canAccessSprKpi() === false) {
			throw new Myshipserv_Exception_MessagedException("You are not allowed to access this page", 403);
		}

        $this->shipmate = $user->isShipservUser();
		
	}
	/**
	 * Action for main report
	 * 
	 * @return unknown
	 * @throws Exception
	 */
	public function indexAction()
	{
		$this->initReport();
		$config = Zend_Registry::get('options');
		$this->view->showCustomRange = false;

		// test if custom range is enabled
		if ($this->shipmate || (int)$config['shipserv']['spr']['customrange']['all'] === 1) {
			$this->view->showCustomRange = true;
		} else {
			if (isset($config['shipserv']['spr']['customrange']['buyers'])) {
				$byoList = $config['shipserv']['spr']['customrange']['buyers'];
				if ($byoList !== '') {
					$enabledBuyerBranches = array_map(
						function($value) {
							return (int)$value;
						},
						explode(',', $byoList)
					);

					$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
					$activeCompanyId = (int)$sessionActiveCompany->id;
					$this->view->showCustomRange = in_array($activeCompanyId, $enabledBuyerBranches);
				}
			}
		}

		$this->view->googleMapsApiKey = $config['google']['services']['maps']['apiKey'];
		$this->view->shipmate = $this->shipmate;
	}
	
	/**
	 * Action for printing
	 * 
	 */
	public function printAction()
	{
		$this->_helper->layout->setLayout('print');
        $this->view->shipmate = $this->shipmate;
		$this->initReport();
	}
	
	/**
	 * Initalize report data
	 * assign result to the view
	 */
	protected function initReport()
	{
		//S20380 Restrict access to non full members
		if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
			$this->_forward('membership-level-access-error', 'error', '', array('menu' => 'analyse'));
		};
		
		$data  = Shipserv_Report_Buyer_Match_BuyerBranches::getInstance()->getBuyerBranches(Shipserv_User::BRANCH_FILTER_WEBREPORTER, false);
		if ($this->_getParam('context') === 'rfq') {
			$data[] = array(
					'id'   => Myshipserv_Config::getProxyPagesBuyer(),
					'name' => 'Pages RFQs',
					'default' => 0
			);
		} elseif ($this->_getParam('context') === 'quote') {
			$data[] = array(
					'id'   => Myshipserv_Config::getProxyPagesBuyer(),
					'name' => 'Pages Quotes',
					'default' => 0
			);
		}
		
		if (count($data) === 0) {
			$this->_helper->viewRenderer('error');
		}
		
		//For some unknown reason, if I do not change the original array, and adding the buyers node, it will fail to load on the require.js helper
		$this->view->buyerBrances = array('buyers' => $data);
		
		//Recording event
		$user = Shipserv_User::isLoggedIn();
		if ($user) {
			$user->logActivity(Shipserv_User_Activity::SPR_REPORT_VIEWS, 'PAGES_USER', $user->userId, $user->email); 
		}
	}
	
}