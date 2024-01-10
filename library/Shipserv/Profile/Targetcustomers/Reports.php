<?
/**
* Base class for supplier targeting requests
* Inherit this class for other request
*/
class Shipserv_Profile_Targetcustomers_Reports extends Shipserv_Report
{
	protected
		$params = array(),
		$activeCompanyId = null,
		$activeCompanyType = null,
		$supplierBuyerRateObj = null,
		$currentRate = null,
		$notificationManager = null,
		$user = null
		;

	/**
	* @param array $params (The post, get params should be passed.)
	*/
	public function __construct( $params = array() )
	{
		if (is_array($params)) {
			$this->params = $params;
			$company = Myshipserv_Helper_Session::getActiveCompanyNamespace();
			if ($company->type == 'v') {
				$this->activeCompanyId = $company->id;
				$this->activeCompanyType = $company->type;
				$this->supplierBuyerRateObj = new Shipserv_Supplier_Rate_Buyer((int)$this->activeCompanyId);
				$rates = $this->supplierBuyerRateObj->getRateObj()->getRate();
				$supplierStandardRate =  ($rates['SBR_RATE_STANDARD'] === null) ? 0 : $rates['SBR_RATE_STANDARD'];
				$supplierTargetRate = ($rates['SBR_RATE_TARGET'] === null) ? 0 : $rates['SBR_RATE_TARGET'];
				$this->currentRate = array(
						  'supplierStandardRate' => $supplierStandardRate
						, 'supplierTargetRate' => $supplierTargetRate
						, 'supplierLockPeriod' => $rates['SBR_LOCK_TARGET']
					);
				$this->user = $user = Shipserv_User::isLoggedIn();
		        // prepare notfication manager to deliver the email
				$this->notificationManager = new  Myshipserv_NotificationManager(Shipserv_Helper_Database::getDb());

			} else {
				throw new Myshipserv_Exception_MessagedException("You need to be logged in as a supplier.", 500);
			}
		}
	}

	/**
	 * returns additional columns for the specific buyer branch. If fullreport is set (for targeget page), the RFQ count, and Quote rate also calculated
	 * if $gilterdate is not null, then the gmv is calculated from this date, else it is calculated for the previous 12 months
	 *
	 * Updated by Yuriy Akopov on 2016-03-03 to overwrite number of RFQs calculation we are suspicious about
	 * Also added support for date and time (as opposed to string date only
	 *
	 * @param   int      $bybBranchCode
	 * @param   bool     $fullReport
	 * @param   DateTime $filterDate
	 *
	 * @return  array
	*/
	protected function getAdditionalInfo($bybBranchCode, $fullReport = false, DateTime $filterDate = null, $withChild = true)
	{
		if (is_null($filterDate)) {
			$filterDate = new DateTime();
			$filterDate->modify("-12 months");  // this to move the default period definition closer to controller
		}

		$buyerInfoDao = Shipserv_Oracle_Targetcustomers_Buyerinfo::getInstance();

		$data = $buyerInfoDao->getBuyerInfo($bybBranchCode, $this->activeCompanyId, $fullReport, $filterDate, $withChild);

		if ($fullReport) {
		    // does not need optimisation
			$data['RFQ_COUNT'] = $buyerInfoDao->getRfqCountInfo($bybBranchCode, $this->activeCompanyId, $filterDate, $withChild);
		}

		// optimised with an index
		$data['SPB_ENABLED'] = $buyerInfoDao->getSpendBenchmarkingEnabled($bybBranchCode);

		return $data;
	}

	/**
	* Returns an array, with the vessel count, and vessel type list
	*/
	public function getVesselInfo( $bybBranchCode,  $filterDate = null, $includeChildren = true )
	{
		// @todo: a crutch by Yuriy Akopov for UAT testing
		/*
		return
			$vesselInfo = array(
				'vesselCount' => 10,
				'vesselTypeList' => array()
			);
		*/
		return  Shipserv_Oracle_Targetcustomers_Vessel::getInstance()->getVesselInfo($bybBranchCode, $this->activeCompanyId, $filterDate, $includeChildren);
	}

	/**
	* Getting the users assigned to the active selected company
	*/
	public function getApprovedUsers()
	{
		
		$userArr = $this->getUsers();
		$userStatuses = Shipserv_Oracle_Targetcustomers_Usersetting::getInstance();

		$result = array();
		foreach ($userArr['approved'] as $key => $userRecord) {
			if ($userRecord['roles']['administrator'] == true) 
			{
				$status = $userStatuses->getData($key, $this->activeCompanyId);

				array_push($result, 
					array(
						'id' => $key,
						'name' => $userRecord['fullName'],
						'email' => $userRecord['email'],
						'receiveNotifications' => $status['notification'], 
						'canTargetExclude' => $status['canTarget']
					));
			}
		}

		return $result;
	}

	protected function getUsers()
	{
		$helper = Zend_Controller_Action_HelperBroker::getStaticHelper('companyUser');
		
		$uaTypeMap = array(
			'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
			'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
            'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON
		);
		
		return  $helper->fetchUsersByType($uaTypeMap[$this->activeCompanyType], $this->activeCompanyId);
	}

	public function storeMaxQuotesPerBuyer( $params )
	{
		return  Shipserv_Oracle_Targetcustomers_Store::getInstance()->storeMaxQuotesPerBuyer( $this->activeCompanyId, $params);
	}

	public function getMaxQotPerBuyer()
	{

		$result = Shipserv_Oracle_Targetcustomers_Buyerinfo::getInstance()->getMaxQotPerBuyer($this->activeCompanyId);

		if (count($result) > 0) {
			$statusValue = $result[0]['SPB_PROMOTION_CHECK_QUOTES']  == 1;
			$response = array(
				  'max' => $result[0]['SPB_PROMOTION_MAX_QUOTES']
				 ,'status' => $statusValue
 			   	);
		} else {
			$response = array(
				  'max' => null
				 ,'status' => false
 			   	);
		}

		return $response;
	}

	/**
	* This functin is not used now, as requirements has changed
	*/
	public function storeUserTargetPerUser( $params )
	{

		$userId = $params['id'];
		if (array_key_exists('receiveNotifications', $params) && array_key_exists('canTargetExclude', $params)) {
			return  Shipserv_Oracle_Targetcustomers_Store::getInstance()->storeUserTargetInfoPerUser(  $userId, $this->activeCompanyId, (int)$params['receiveNotifications'] , (int) $params['canTargetExclude'] );
		} else if (array_key_exists('receiveNotifications', $params)) {
			return  Shipserv_Oracle_Targetcustomers_Store::getInstance()->storeUserNotificationInfoPerUser(  $userId, $this->activeCompanyId, (int)$params['receiveNotifications'] );
		} else if (array_key_exists('canTargetExclude', $params)) {
			return  Shipserv_Oracle_Targetcustomers_Store::getInstance()->storeUserCanTargetPerUser(  $userId, $this->activeCompanyId, (int)$params['canTargetExclude'] );
		}
		
	}

	public function storeUserTargetInfo( $params )
	{	
		return  Shipserv_Oracle_Targetcustomers_Store::getInstance()->storeUserTargetInfo( $this->getUsers(), $this->activeCompanyId, $params );
	}

	public function resultIfEmpty()
	{
		return 	array(
			  'currentRate' => $this->currentRate
			, 'vessel' => array(
				  	  'vesselCount' => 0
					, 'vesselTypeList' => array()
				)
			);

	}

	/**
	* Setting child brances, for a list of buyer_branch ID's 
	* @param array $responses
	*/
	protected function setBulkHierarchy( $responses, $branchFieldName = 'BYB_BRANCH_CODE' )
	{	

		$hasData = false;
		$topBranchIds = array();
		if (is_array($responses)) {
			foreach ($responses as $response) {
				if ($response['IS_TOP']  == 1 && $response['BYB_PROMOTE_CHILD_BRANCHES'] == 0) {
					$hasData = true;
					array_push($topBranchIds, $response[$branchFieldName]);
				}
			}
		}

		// Validation skip was added as the last parameter, !!!!!! Double check it with Yuriy !!!!!
		$hierarchy = ($hasData === true) ? Shipserv_Buyer_Branch::getAllBranchesInTheHierarchyBulk($topBranchIds, true, true, false) : array();
		Shipserv_Oracle_Targetcustomers_Buyerinfo::getInstance()->setHierarchy($hierarchy);
		Shipserv_Oracle_Targetcustomers_Vessel::getInstance()->setHierarchy($hierarchy);
 	}


}