<?php

/**
 * Controller for handling supplier-related actions (profiles, microprofiles, etc.)
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class ProfileController extends Myshipserv_Controller_Action
{


	public function preDispatch ()
	{
		parent::preDispatch();
		$this->applyExplicitUser();
		$this->initContext();
	}

	/**
	 * Checks for 'u' parameter (explicit user). If the currently logged-in
	 * user is not the requested user, redirect to log-in.
	 *
	 * @return void
	 */
	private function applyExplicitUser ()
	{
		// Pick up intended user, or return if none
		$intendedUsr = $this->_getParam('u');
		if ($intendedUsr == '') return;

		// If intended user is the logged-in user, return
		$loggedInUser = $this->_getUser();
		if ($intendedUsr == $loggedInUser->userId) return;

		// Effect redirect
		$this->redirectToLogin();
		exit;
	}

	/**
	 * Modifies a URL to remove 'u' parameter
	 *
	 * @return string modified url
	 */
	private function removeExplicitUser ($url)
	{
		$urlArr = explode('/', $url);
		$i = 0;
		while ($i < count($urlArr))
		{
			if ($urlArr[$i] == 'u')
			{
				unset($urlArr[$i]);
				unset($urlArr[$i + 1]);
				$i += 2;
			}
			else $i++;
		}
		return join('/', $urlArr);
	}

    /**
     * Since in that controller user properties are often updated, we also realod the user here
     *
     * @return bool|Shipserv_User
     */
	private function _getUser()
	{
		$user = Shipserv_User::isLoggedIn();
		if (!$user) {
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

        $user = Shipserv_User::getInstanceById($user->userId);

		return $user;
	}

    /**
     * Updates the user session with the current user object
     *
     * @story   DE5299
     * @author  Yuriy Akopov
     * @date    2014-09-24
     */
    protected function updateUserInSession() {
        $user = $this->_getUser();
        $auth = Myshipserv_Config::getAuthStorage();

        $auth->getStorage()->write($user);
    }


	private function initContext ()
	{
		parent::init();

		//getHelper('AjaxContext');
		$ajaxContext = $this->_helper->contextSwitch();
		$ajaxContext->addActionContext('supplier-search', 'json')
			->addActionContext('enquiry-update-response-date', 'json')
			//->addActionContext('company-search', 'json')
			->addActionContext('join-company', 'json')
			->addActionContext('leave-company', 'json')
			->addActionContext('withdraw-join-request', 'json')
			->addActionContext('approve-user-request', 'json')
			->addActionContext('reject-user-request', 'json')
			->addActionContext('invite-user', 'json')
			->addActionContext('person-search', 'json')
			->addActionContext('remove-user', 'json')
			->addActionContext('add-user', 'json')
			->addActionContext('add-user-send-email', 'json')
			->addActionContext('set-administrator-status', 'json')
			->addActionContext('fetch-pending-actions', 'json')
			->addActionContext('company-brands-invite-send', 'json')
			->addActionContext('company-memberships-invite-send', 'json')
			->initContext();
	}

	/**
	 * Modify user details from form
	 */
	private function updateUser (Myshipserv_Form_ProfileDetails $form)
	{
		Shipserv_User::updateDetailsByForm($form, $this->_getUser());
	}

	public function agreementAction()
	{
		$redirectUrl = null;

		$user = $this->_getUser();
		$termAndCondition = Shipserv_Agreement_TermAndCondition::getLatest();
		$termAndCondition->setUser($user);
		$this->view->termAndCondition = $termAndCondition;

		$privacyPolicy = Shipserv_Agreement_PrivacyPolicy::getLatest();
		$privacyPolicy->setUser($user	);
		$this->view->privacyPolicy = $privacyPolicy;
		$this->view->redirect = $this->view->uri()->obfuscate($this->params['redirect']);

		if ($this->params['act'] != "" && $this->params['agree'] == '1') {
			$termAndCondition->agree();
			$privacyPolicy->agree();
			$user->confirmTermsAndConditions();
			$this->view->hasAgreed = true;

			if ($this->params['redirect']) {
				$redirectUrl = ($this->params['benc']) ? $this->view->uri()->deobfuscate($this->params['redirect']) : $this->params['redirect'];
			} else {
				$redirectUrl = '/search';
			}

			$this->redirect( $redirectUrl );
		}
	}

	
    public function indexAction()
    {
        $this->_forward('overview');
    }

	/**
	 * Top-level profile: account details
	 */
	public function overviewAction()
	{
		$form = null;
		$pwForm = null;
		$this->view->saveSuccess = false;
		$user = $this->_getUser();

		$params = $this->_getAllParams();
		$this->view->params= $params;

		$countriesDAO = new Shipserv_Oracle_Countries($this->db);
		$this->view->countries = $countriesDAO->fetchNonRestrictedCountries();

		$vesselDao = new Shipserv_Oracle_Vessel;
		$this->view->vesselTypes = $vesselDao->getTypes();

		$this->view->annualBudget = Shipserv_User::getOptionListForAnnualBudget();

		$this->view->passwordSaveSuccess = false;

		// if user decide to update the detail
		if ($this->getRequest()->isPost()) {
			if ($params['changepassword']) {
				// Handle change password form
				$pwForm = new Myshipserv_Form_ChangePassword();
				if ($pwForm->isValid($_POST)) {
			       	$pwFormVals = $pwForm->getValues();
					$uDao = new Shipserv_Oracle_User($this->getInvokeArg('bootstrap')->getResource('db'));
					$response = Myshipserv_CAS_CasResetPassword::getInstance()->changePassword($this->_getUser()->username, $params['oldPassword'], $pwFormVals['password']);
					if (!$response) {
					    $changePasswordErrorMessage['oldPassword']['nonMatch'] = Myshipserv_CAS_CasResetPassword::getInstance()->getErrorMessage();
					} else {
					    $this->view->passwordSaveSuccess = true;
					}
				} else {
					$changePasswordErrorMessage = $pwForm->getMessages();
					//if( count($changePasswordErrorMessage['confirmPassword']) == 0)
					//$changePasswordErrorMessage['confirmPassword']['nonMatch'] = 'Password do not match';
				}
				$this->view->passwordError = $changePasswordErrorMessage;

			} else {

				// Handle main details form
				$form = new Myshipserv_Form_ProfileDetails();

				if ($form->isValid($_POST)) {
					$this->updateUser($form);
					$this->view->saveSuccess = true;
					$form = null; // Force reload from db below
				} else {
					// Error state - allow form with errors to fall thru below

					// Hack - need to init email explicitly here because
					// field is currently 'ignored' by Form. When this is changed,
					// remove this & the data will come thru in the POST like
					// regular fields.
					$form->populate(array('email' => $user->email));
				}
			}

			$user->completeDetailedInformation();
			$user->purgeMemcache();
			$user = $this->_getUser();

		}
		
		if (!$form) {
			$form = new Myshipserv_Form_ProfileDetails();
			$form->populate($user->getArrayForZendFormValidation());
		}
		
		if (!$pwForm) {
			$pwForm = new Myshipserv_Form_ChangePassword();
		}
		
		if (@$params['emailConfirmed'] == 'true') {
			$this->view->showEmailConfirmedSuccess = true;
		} else if (@$params['emailConfirmed'] === false && @$params['emailConfirmationError'] === true) {
			$this->view->showEmailConfirmedError = true;
			$this->view->emailConfirmedErrorMsg  = $params['emailConfirmationErrorMsg'];
		}
		
		$this->view->user             		= $user;
		$this->view->detailsForm      		= $form;
		$this->view->passwordForm      		= $pwForm;
		$this->view->pendingCompanies  		= $this->_helper->companies->getMyPendingCompanies($this->_getUser());
		$this->view->ownerDetails      		= $this->_makeOwnerDetails();
		$this->view->profileMenuHelper 		= $this->_helper->profileMenu;
		$this->view->pendingReviewRequests 	= Shipserv_ReviewRequest::getRequestsForEmail($this->_getUser()->email);
		$this->view->reviews 				= Shipserv_Review::getUserReviews($this->_getUser()->userId);
		$this->view->companyDetail    		= $this->getCompanyDetail();

		if ($user) {
			if ($user->isPartOfBuyer()) {
            	$user->logActivity(Shipserv_User_Activity::MY_INFO_CLICK, 'PAGES_USER', $user->userId, $user->email);
        	}
        }
	}

	
	public function getCompanyDetail()
	{
		// return ( $this->getActiveCompany()->type != "" ) ? $this->_helper->companies->getCompanyDetail($this->getActiveCompany()->type,$this->getActiveCompany()->id) : null;

		// changed by Yuriy Akopov on 2015-12-21, DE6311, but the whole workflow is a mess begging to be rewritten
		$type = $this->getActiveCompany()->type;
		if (strlen($type) === 0) {
			return null;
		}

		$id = $this->getActiveCompany()->id;

		if ($type === 'byb') {
			// buyer branch - load company org code instead to remain compatible with further functions in the chain
			$buyerBranch = Shipserv_Buyer_Branch::getInstanceById($id);

			$type = 'b';
			$id = $buyerBranch->getOrgId();
		}

		return $this->_helper->companies->getCompanyDetail($type, $id);
	}

	
	/**
	 * Top-level profile: my companies
	 */
	public function companiesAction()
	{
		try
		{
			$user = $this->_getUser();

			$this->view->user                  = $user;
			$this->view->companies             = $this->_helper->companies->getMyCompanies($user, true);
			$this->view->pendingCompanies      = $this->_helper->companies->getMyPendingCompanies($user);
			$this->view->rejectedCompanies     = $this->_helper->companies->getMyRejectedCompanies($user);
			$this->view->reviews               = Shipserv_Review::getUserReviews($user->userId);
			$this->view->ownerDetails          = $this->_makeOwnerDetails();
			$this->view->profileMenuHelper     = $this->_helper->profileMenu;

			$this->view->companyDetail    	   = $this->getCompanyDetail();
			$this->view->confirmEmail = array(

				// Redirect URL on success of email confirmation send
				'redirOk' => $this->_helper->url('companies', null, null, array('confEmlSubmitted' => 1, 'confEmlOk' => 1)),

				// Redirect URL on failure of email confirmation send
				'redirFail' => $this->_helper->url('companies', null, null, array('confEmlSubmitted' => 1, 'confEmlOk' => 0)),

				// Hide companies & show e-mail confirmation block: (i) if e-mail is unconfirmed, or (ii) if send-confirmation was submitted
				'showEmailConf' => !$user->isEmailConfirmed() || $this->_getParam('confEmlSubmitted'),

				// Last action attempted to send confirmation email?
				'confEmlSubmitted' => (bool) $this->_getParam('confEmlSubmitted'),

				// Send-confirmation successful?
				'confEmlWasOk' => (bool) $this->_getParam('confEmlOk'),

				// Error message id email confirmation send failed
				'confEmlErrMsg' => base64_decode($this->_getParam('confEmlSendErr')),
			);
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}

		if ($user) {
			if ($user->isPartOfBuyer()) {
        		$user->logActivity(Shipserv_User_Activity::MY_COMPANIES_CLICK, 'PAGES_USER', $user->userId, $user->email);
        	}
    	}

	}

	
	public function reviewRequestsAction()
	{
		try
		{
			$user = $this->_getUser();


			$this->view->user = $this->_getUser();
			$this->view->ownerDetails = $this->_makeOwnerDetails();
			$this->view->companyDetail = $this->getCompanyDetail();
			$this->view->pendingCompanies = $this->_helper->companies->getMyPendingCompanies($this->_getUser());
			$this->view->profileMenuHelper = $this->_helper->profileMenu;
			$this->view->pendingReviewRequests = Shipserv_ReviewRequest::getRequestsForEmail($this->_getUser()->email);
			$this->view->reviews = Shipserv_Review::getUserReviews($this->_getUser()->userId);
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}
		if ($user) {
			$user->logActivity(Shipserv_User_Activity::REVIEW_REQUESTS_CLICK, 'PAGES_USER', $user->userId, $user->email);
		}
	}

	
	public function myReviewsAction ()
	{
		try
		{
			$this->view->user = $this->_getUser();
			$this->view->ownerDetails = $this->_makeOwnerDetails();
			$this->view->pendingCompanies = $this->_helper->companies->getMyPendingCompanies($this->_getUser());
			$this->view->profileMenuHelper = $this->_helper->profileMenu;
			$this->view->reviews = Shipserv_Review::getUserReviews($this->_getUser()->userId);
			$this->view->companyDetail    	   = $this->getCompanyDetail();
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}
	}
	
	
	private function _makeOwnerDetails ()
	{
		return array('ownerName' => $this->_getUser()->firstName . ' ' . $this->_getUser()->lastName);
	}

	/**
	 * Company detail: privacy settings (buyer / supplier)
	 */
	public function companyAction ()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		// Ensure type in correct domain
		if (@$params['type'] != 'v' && @$params['type'] != 'b') {
			// throw new Exception("Invalid company type: '{$params['type']}'");
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		// Fetch suppliers / buyers owned by current user
		$myCompanyIds = ($params['type'] == 'b' ? $this->_helper->companies->getBuyerIds($this->_getUser()) : $this->_helper->companies->getSupplierIds($this->_getUser()));

		// Ensure user owns requested company
		if (!in_array(@$params['id'], $myCompanyIds)) {
			// throw new Exception("User does not own requested company: {$params['id']}");
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		// Declare var for privacy form
		$form = null;
		$this->view->saveSuccess = false;

		// Handle POST
		if ($this->getRequest()->isPost()) {
			// Instantiate form
			$form = ($params['type'] == 'b' ? new Myshipserv_Form_Endorsement_PrivacyBuyer() : new Myshipserv_Form_Endorsement_PrivacySupplier());

			if ($form->isValid($_POST)) {
				// Save form
				$params['type'] == 'b' ?
					$this->_helper->companies->saveBuyerForm($params['id'], $form)
					: $this->_helper->companies->saveSupplierForm($params['id'], $form);

				$this->view->saveSuccess = true;

				// Reset form (it will be re-loaded from DB below)
				$form = null;
			} else {
				// Invalid form - do nothing & allow error-bearing form object to drop thru
			}
		}

		// If form is not initialized ...
		if (!$form) {
			// Create form & populate from DB
			$method = ($params['type'] == 'b' ? 'makeBuyerFormFromId' : 'makeSupplierFormFromId');
			$form = $this->_helper->companies->$method($params['id']);
		}

		$uaTypeMap = array(
			'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
			'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
            'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON
		);
		$this->_helper->companyUser->fetchUsers($uaTypeMap[$params['type']], $params['id'], $pendingUsers, $approvedUsers);

		// Record that user has viewed company privacy information
		$this->registerCompanyPrivacyView($params['type'], $params['id']);

		$this->view->pendingUsers      = $pendingUsers;
		$this->view->user              = $this->_getUser();
		$this->view->pendingCompanies  = $this->_helper->companies->getMyPendingCompanies($this->_getUser());
		$this->view->ownerDetails      = $this->_makeOwnerDetails();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->privacyForm       = $form;
		$this->view->companyDetail     = $this->getCompanyDetail();
	}

	/**
	 * Record that a user has viewed privacy settings for company
	 *
	 * @param string $companyType 'v' | 'b'
	 */
	private function registerCompanyPrivacyView ($companyType, $companyId)
	{
		$uaTypeMap = array(
			'v' => Shipserv_Oracle_EndorsementPrivacy::OWNER_TYPE_SUPPLIER,
			'b' => Shipserv_Oracle_EndorsementPrivacy::OWNER_TYPE_BUYER
		);
		if (!array_key_exists($companyType, $uaTypeMap)) throw new Exception('Illegal type');
		$epDao = new Shipserv_Oracle_EndorsementPrivacy($this->getInvokeArg('bootstrap')->getResource('db'));
		$epDao->updateViewDate($uaTypeMap[$companyType], $companyId);
	}

	
	public function companyBlockedUserListAction(){
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		$this->redirectToDefaultPageByCompanyType('v');

		// Ensure type in correct domain
		if (@$params['type'] != 'v' && @$params['type'] != 'b') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		// Ensure company ID provided
		if (@$params['id'] == '') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		try
		{
			//$userArr = $this->_helper->companyUser->fetchUsersPenEmlAsAppr($uaTypeMap[$params['type']], $params['id']);
			$daoForInquiry = new Shipserv_Oracle_Enquiry();
       		$bannedUsers = $daoForInquiry->getBlockedUserBySupplierId($params["id"]);
		}
		catch (Myshipserv_Exception_PermissionViolation $e)
		{
			$this->_helper->redirector('companies', 'profile', null, array());
			exit;
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}
		$this->view->tnid			   = $params["id"];
		$this->view->user              = $this->_getUser();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->bannedUsers       = $bannedUsers;
		$this->view->companyDetail     = $this->getCompanyDetail();
		$this->_helper->companyUser->fetchUsers(
				self::companyTypeToDb($this->_getParam('type')),
				$this->_getParam('id'),
				$pendingUsers,
				$approvedUsers
		);

		$this->view->pendingUsers      = $pendingUsers;

	}

	
	public function companyPeopleAction()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters

		$params = $this->params;
		$allowAddUser = true;

		// Ensure type in correct domain
		if (!isset($params['type']) || ($params['type'] != 'v' && $params['type'] != 'b' && $params['type'] != 'c')) {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		// Ensure company ID provided
		if (!isset($params['id']) || !$params['id']) {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		$uaTypeMap = array(
			'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
			'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
            'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON
		);

		try
		{
			$userArr = $this->_helper->companyUser->fetchUsersByType($uaTypeMap[$params['type']], $params['id']);
		}
		catch (Myshipserv_Exception_PermissionViolation $e)
		{
			$this->_helper->redirector('companies', 'profile', null, array());
			exit;
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}

		//Check if Active Company is a supplier, and if this company is deleted, In that case we are not allowing user to be added
		if ($this->getActiveCompany()->type === 'v') {
			$activeSupplierCompany = Shipserv_Supplier::getInstanceById($this->getActiveCompany()->id, "", true, false);
			if ($activeSupplierCompany->accountIsDeleted === 'Y') {
				$allowAddUser = false;
			}
		}
		
		$this->view->allowAddUser 	   = $allowAddUser;
		$this->view->myCompanies 	   = $this->_helper->companies->getMyCompanies($this->_getUser(), true);
		$this->view->availableGroups   = Shipserv_User::getAllGroups();
		$this->view->ssCompanyId 	   = $this->config['shipserv']['company']['tnid'];
		$this->view->user              = $this->_getUser();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;

		$this->view->pendingUsers      = $userArr['pending'];
		$this->view->pendingConfirmationUsers      = $userArr['pendingConfirmation'];
		$this->view->approvedUsers     = $userArr['approved'];
		$this->view->companyDetail     = $this->getCompanyDetail();
		$this->view->companies         = $this->_helper->companies->getMyCompanies($this->_getUser(), true);

		$user = $this->_getUser();
		if ($user) {
			if ($user->isPartOfBuyer()) {
				$user->logActivity(Shipserv_User_Activity::USER_SETTINGS_CLICK, 'PAGES_USER', $user->userId, $user->email);
			}
		}

		//$this->_getUser()->logActivity(Shipserv_User_Activity::USER_MANAGEMENT_VIEW, 'SUPPLIER_BRANCH', $params['id'], "");
	}

	public function storeBybUserAction()
	{

		if ($this->params['userId'] == null) {
			throw new Myshipserv_Exception_MessagedException("Please check your parameters");
		}

		$user = Shipserv_User::getInstanceById( $this->params['userId'] );
		$currentBybBranchCode = $user->fetchCompanies()->getBuyerBranchIds();

		// access to different application
		$match = (array) $this->params['bybBranchCode'];
		$txnMon = (array) $this->params['txnMonitorAccess'];
		$webReporter = (array) $this->params['webReporterAccess'];
		$approvedSupplier = (array) $this->params['approvedSupplAccess'];
		$buy = (array) $this->params['buyTabAccess'];

		$txnMonitorD = (array) $this->params['txnMonitorAccessD'];
		$automaticReminders = (array) $this->params['automaticReminders'];
		$userLevel = ((!isset($this->params['isAdmin']) || !$this->params['isAdmin'] || $this->params['isAdmin']=='false')? 'USR' : 'ADM');
		$selectedBybBranchCode = array_unique( array_merge( $match, $txnMon, $webReporter, $buy, $approvedSupplier, $txnMonitorD, $automaticReminders ) );

		$dao = new Shipserv_Oracle_PagesUserCompany();

		// newly selected including existing
		foreach((array) $this->params['allBybBranchCode'] as $bybBranchCode) {
			$dao->insertUserCompany(
				$this->params['userId'],
				'BYB',
				$bybBranchCode,
				$userLevel,
				($user->emailConfirmed === 'Y'? Shipserv_Oracle_PagesUserCompany::STATUS_ACTIVE : Shipserv_Oracle_PagesUserCompany::STATUS_PENDING),
				true,
				(( in_array($bybBranchCode, $txnMon) !== false )?1:0),
				(( in_array($bybBranchCode, $webReporter) !== false )?1:0),
				(( in_array($bybBranchCode, $match) !== false )?1:0),
				(( in_array($bybBranchCode, $buy) !== false )?1:0),
				(( in_array($bybBranchCode, $approvedSupplier) !== false )?1:0),
		        (( in_array($bybBranchCode, $txnMonitorD) !== false )?1:0),
		        (( in_array($bybBranchCode, $automaticReminders) !== false )?1:0),
		        ($bybBranchCode == $this->params['defaultBybBranchCode']? 1 : 0)
			);
		}

		$this->_helper->json((array) array('ok') );

	}
	

	public function activitiesAction()
	{
		$this->_helper->layout->setLayout('default');
		$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');

		$reportDb = $resource->getDb('ssreport2');

		if( $_SERVER['APPLICATION_ENV'] == "development") {
			$db = $resource->getDb('standbydblocal');
		} else {
			$db = $resource->getDb('standbydb');
		}

		$sql = "
			SELECT * FROM
			(
				SELECT
				  PUA_ACTIVITY
				  , COUNT(*) AS TOTAL
				  , TO_CHAR(PUA_DATE_CREATED, 'MM-YY') MONTH_YEAR
				  , PUA_IS_SHIPSERV
				FROM
				  pages_user_activity
				GROUP BY
				  PUA_ACTIVITY,
				  PUA_IS_SHIPSERV,
				  TO_CHAR(PUA_DATE_CREATED, 'MM-YY')
				ORDER BY
				  PUA_ACTIVITY ASC,
				  TO_CHAR(PUA_DATE_CREATED, 'MM-YY') ASC
			)
			ORDER BY MONTH_YEAR ASC
		";

		$activity['stats'] = $reportDb->fetchAll($sql);

		$sql = "
			SELECT
				*
			FROM
			(
			SELECT
	          TO_CHAR(pst_search_date_time, 'MM-YY') MONTH_YEAR,
			  PST_CATEGORY_ID,
	          (SELECT NAME FROM product_category WHERE ID=PST_CATEGORY_ID) NAME,
	          COUNT(*) AS TOTAL
			FROM
			  pages_statistics
			WHERE
			  pst_search_date_time BETWEEN SYSDATE-90 AND SYSDATE-1
			  AND PST_CATEGORY_ID IS NOT null
			  AND pst_browser <> 'crawler'
			  AND PST_IP_ADDRESS NOT IN (SELECT PSI_IP_ADDRESS FROM PAGES_STATISTICS_IP)
	        GROUP BY
	          PST_CATEGORY_ID
	          , TO_CHAR(pst_search_date_time, 'MM-YY')
			HAVING
			  COUNT(*) > 500

			)
			ORDER BY MONTH_YEAR ASC

		";
		$activity['search'] = $db->fetchAll($sql);

		$this->view->userActivityStats = $activity;
	}
	

	public function companyReviewsAction()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		$this->redirectToDefaultPageByCompanyType('b');

		// Ensure type in correct domain
		if (@$params['type'] != 'v' && @$params['type'] != 'b') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		// Ensure company ID provided
		if (@$params['id'] == '') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		$page = 1;
		if (isset($params['page'])) {
			$page = $params['page'];
		}

		$uaTypeMap = array(
			'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
			'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
            'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON
		);

		try
		{
			$this->_helper->companyUser->fetchUsers($uaTypeMap[$params['type']], $params['id'], $pendingUsers, $approvedUsers);
		}
		catch (Myshipserv_Exception_PermissionViolation $e)
		{
			// todo: add a flash message?
			$this->_helper->redirector('companies', 'profile', null, array());
			exit;
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}

		$endorsementsDao = new Shipserv_Oracle_Endorsements($this->getInvokeArg('bootstrap')->getResource('db'));

		$reviews = array ();
		foreach (Shipserv_Review::getReviewsByEndorser($params['id']) as $review) {
			if (!isset($reviews[$review->endorseeId])) $reviews[$review->endorseeId] = array();
			$reviews[$review->endorseeId][] = $review;
		}

		$this->view->endorsements	   = $endorsementsDao->fetchEndorsementsByEndorser($params['id'],$params['order'],$page);
		$this->view->user              = $this->_getUser();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->reviews = $reviews;
		$this->view->companyDetail     = $this->getCompanyDetail();
		$this->view->order = $params['order'];
		$this->view->currentPage = $page;
	}

	
	public function companyBrandsAction ()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		$config  = Zend_Registry::get('config');

		$this->redirectToDefaultPageByCompanyType('v');

		// Ensure type in correct domain
		if (@$params['type'] != 'v' && @$params['type'] != 'b') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		// Ensure company ID provided
		if (@$params['id'] == '') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		$uaTypeMap = array(
			'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
			'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
            'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON,
     	);

		try
		{
			$this->_helper->companyUser->fetchUsers($uaTypeMap[$params['type']], $params['id'], $pendingUsers, $approvedUsers);
		}
		catch (Myshipserv_Exception_PermissionViolation $e)
		{
			// todo: add a flash message?
			$this->_helper->redirector('companies', 'profile', null, array());
			exit;
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}

		$managedBrands = Shipserv_BrandAuthorisation::getManagedBrands($params['id']);


		if (!$params["brand"]){
			if (count($managedBrands)>0) {
				$brandRow = current($managedBrands);
				$brand = $brandRow["ID"];
			} else {
				throw new Myshipserv_Exception_MessagedException("You don't have brands that you can manage");
			}
		} else {
			if (isset($managedBrands[$params["brand"]])) {
				$brand = $params["brand"];
			} else {
				throw new Myshipserv_Exception_MessagedException("You don't have permission to manage this brand");
			}
		}
		if ($brand) {

			$brandForm = new Myshipserv_Form_Brand();
			try {
				if ($this->getRequest()->isPost() && $brandForm->isValid($this->getRequest()->getParams())) {
					if (!$brandForm->brandLogo->receive()) {
						$messages = $brandForm->brandLogo->getMessages();
						throw new Exception(implode("\n", $messages));
					} else {
						$logoFile = new Myshipserv_AffiliateLogo($brandForm->brandLogo->getFileName());
						$uploadedFile = $logoFile->save();
						$qoAdapter = new Shipserv_Oracle_Brands($this->getInvokeArg('bootstrap')->getResource('db'));
						$qoAdapter->updateImage($uploadedFile["filename"],$brand);
					}
				}
			}
			catch (Exception $e)
			{

			}

			$requestsByCompany = Shipserv_BrandAuthorisation::getBrandRequestsByCompany($brand);

			$authsByCompanyTemp = array();

			$auths = Shipserv_BrandAuthorisation::getAuthorisations($brand);
			foreach ($auths as $auth) {
				if ($auth->authLevel!=Shipserv_BrandAuthorisation::AUTH_LEVEL_OWNER) {
					if (!isset($authsByCompanyTemp[$auth->companyId])) 
					    $authsByCompanyTemp[$auth->companyId] = array();
					$authsByCompanyTemp[$auth->companyId]["auths"][$auth->authLevel] = $auth;
				}
			}

			$profileDao = new Shipserv_Oracle_Profile($this->getInvokeArg('bootstrap')->getResource('db'));
			$countriesDAO = new Shipserv_Oracle_Countries($this->getInvokeArg('bootstrap')->getResource('db'));

			$companies = $profileDao->getSuppliersByIds(array_keys($authsByCompanyTemp));

			$companyNames = array ();
			foreach ($companies as $company) {
				$authsByCompanyTemp[$company["SPB_BRANCH_CODE"]]["companyInfo"] = $company;
				$companyNames[$company["SPB_BRANCH_CODE"]] = $company["SPB_NAME"];
				$country = $countriesDAO->fetchCountryByCode($company["SPB_COUNTRY"]);
				if (count($country)==1)
				{
					$authsByCompanyTemp[$company["SPB_BRANCH_CODE"]]["companyInfo"]["CNT_NAME"] = $country[0]["CNT_NAME"];
					$authsByCompanyTemp[$company["SPB_BRANCH_CODE"]]["companyInfo"]["CNT_CON_CODE"] = $country[0]["CNT_CON_CODE"];
				}
			}
			asort($companyNames);

			$authsByCompany = array();
			foreach ($companyNames as $companyId=>$companyName) {
				$authsByCompany[$companyId] = $authsByCompanyTemp[$companyId];
			}

		} else {
			$requestsByCompany = null;
			$authsByCompany = null;
		}

		$managedBrands = Shipserv_BrandAuthorisation::getManagedBrands($params['id']);
		//get count of pending requests for brands
		foreach ($managedBrands as $managedBrandId=>$brandArray) {
			$managedBrands[$managedBrandId]["PENDING_REQUESTS_COUNT"] = count(Shipserv_BrandAuthorisation::getBrandRequestsByCompany($managedBrandId));
		}

		$this->view->selectedBrand = $brand;
		$this->view->user              = $this->_getUser();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->managedBrands = $managedBrands;
		$this->view->pendingRequestsByCompany = $requestsByCompany;
		$this->view->authorisationsByCompany = $authsByCompany;
		$this->view->companyDetail     = $this->getCompanyDetail();
		$this->view->logoUrlPrefix     = $config->shipserv->affManagement->images->urlPrefix;
	}

	
	public function companyBrandsInviteAction ()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		// Ensure type in correct domain
		if (@$params['type'] != 'v' && @$params['type'] != 'b') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		// Ensure company ID provided
		if (@$params['id'] == '') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		$uaTypeMap = array(
			'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
			'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
            'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON,
		);

		try
		{
			$this->_helper->companyUser->fetchUsers($uaTypeMap[$params['type']], $params['id'], $pendingUsers, $approvedUsers);
		}
		catch (Myshipserv_Exception_PermissionViolation $e)
		{
			// todo: add a flash message?
			$this->_helper->redirector('companies', 'profile', null, array());
			exit;
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}

		$managedBrands = Shipserv_BrandAuthorisation::getManagedBrands($params['id']);

		if (!$params["brand"]) {
			if (count($managedBrands)>0)
			{
				$brandRow = current($managedBrands);
				$brand = $brandRow["ID"];
			}
			else
			{
				$brand = null;
			}
		} else {
			if (isset($managedBrands[$params["brand"]])) {
				$brand = $params["brand"];
			} else {
				throw new Myshipserv_Exception_MessagedException("You don't have permission to manage this brand");
			}
		}


		$this->view->selectedBrand = $brand;
		$this->view->user              = $this->_getUser();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->managedBrands = Shipserv_BrandAuthorisation::getManagedBrands($params['id']);
		$this->view->companyDetail     = $this->getCompanyDetail();
	}
	

	public function companyBrandsInviteSendAction ()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		//notify about rejection
		$notificationManager = new Myshipserv_NotificationManager($this->getInvokeArg('bootstrap')->getResource('db'));
		$notificationManager->brandInviteSupplier(array("name"=>$params["supplierName"],"email"=>$params["supplierEmail"]),$params["text"],$params["brandId"],$params["companyId"]);

		return "success";
	}

	
	public function companyMembershipsInviteAction ()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		// Ensure type in correct domain
		if (@$params['type'] != 'v' && @$params['type'] != 'b') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		// Ensure company ID provided
		if (@$params['id'] == '') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		$uaTypeMap = array(
			'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
			'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
            'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON,
		);

		try
		{
			$this->_helper->companyUser->fetchUsers($uaTypeMap[$params['type']], $params['id'], $pendingUsers, $approvedUsers);
		}
		catch (Myshipserv_Exception_PermissionViolation $e)
		{
			// todo: add a flash message?
			$this->_helper->redirector('companies', 'profile', null, array());
			exit;
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}

		$managedMemberships = Shipserv_MembershipAuthorisation::getOwnedMemberships($params['id']);


		if (!$params["membership"]) {
			if (count($managedMemberships)>0) {
				$membershipRow = current($managedMemberships);
				$membership = $membershipRow["QO_ID"];
			} else {
				throw new Myshipserv_Exception_MessagedException("You don't have permission to manage any membership");
			}
		} else {
			if (isset($managedMemberships[$params["membership"]])) {
				$membership = $params["membership"];
			} else {
				throw new Myshipserv_Exception_MessagedException("You don't have permission to manage these memberships");
			}


		}

		$this->view->selectedMembership = $membership;
		$this->view->user              = $this->_getUser();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->managedMemberships = $managedMemberships;
		$this->view->companyDetail     = $this->getCompanyDetail();
	}

	
	public function companyMembershipsInviteSendAction ()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		//notify about rejection
		$notificationManager = new Myshipserv_NotificationManager($this->getInvokeArg('bootstrap')->getResource('db'));
		$notificationManager->membershipInviteSupplier(array("name"=>$params["supplierName"],"email"=>$params["supplierEmail"]),$params["text"],$params["membershipId"],$params["companyId"]);

		return "success";
	}


	public function categoriesAction ()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		// getting the user
		$user = $this->_getUser();

		$managedCategories = Shipserv_CategoryAuthorisation::getManagedCategories($user->userId);

		if (!$params["category"]) {
			if (count($managedCategories)>0) {
				$categoryRow = current($managedCategories);
				$category = $categoryRow["ID"];
			} else {
				throw new Myshipserv_Exception_MessagedException("You don't have permission to manage any category");
			}
		} else {
			if (isset($managedCategories[$params["category"]])) {
				$category = $params["category"];
			} else {
				throw new Myshipserv_Exception_MessagedException("You don't have permission to manage this category");
			}


		}

		$page = 1;
		if ($params["page"]) {
			$page = intval($params["page"]);
		}

		$region = "";
		if ($params["region"]) {
			$region = $params["region"];
		}

		$name = "";
		if ($params["filterName"]) {
			$name = $params["filterName"];
		}

		if ($params["hdnAction"]) {
			switch ($params["hdnAction"]) {
				case "add":
					$categoryId = intval($params["category"]);
					$companyId = intval($params["supplierToAddId"]);

					echo $categoryId .'-'.$companyId;
					if($companyId>0 and $categoryId>0) {
						//check if supplier is not authorised already
						if (!$auth = Shipserv_CategoryAuthorisation::fetch($companyId, $categoryId, true)) {
							//check if supplier has default authorisation (ie isAuthorised is null)
							if (!$auth = Shipserv_CategoryAuthorisation::fetch($companyId, $categoryId, null)) {
								//create new authorisation
								Shipserv_CategoryAuthorisation::create($companyId, $categoryId, true);
								//echo "here";
							} else {
								$auth->authorise();
							}
						}
					}
					break;
				case "delete":
					if (count($params["selectedSupplierId"])>0)
					{
						foreach ($params["selectedSupplierId"] as $companyId)
						{
							Shipserv_CategoryAuthorisation::removeCompanyAuthsForCategory($companyId, $category);
						}
					}
					break;
			}
		}

		if ($category)
		{

			$requests = Shipserv_CategoryAuthorisation::getRequests($category);

			$authsTemp = array();

			$auths = Shipserv_CategoryAuthorisation::getAuthorisations($category,$page,$region,$name);
			foreach ($auths as $auth) {

				if (!isset($authsTemp[$auth->companyId])) 
				    $authsTemp[$auth->companyId] = array();
				$authsTemp[$auth->companyId]["auth"] = $auth;

			}

			$profileDao = new Shipserv_Oracle_Profile($this->getInvokeArg('bootstrap')->getResource('db'));
			$countriesDAO = new Shipserv_Oracle_Countries($this->getInvokeArg('bootstrap')->getResource('db'));

			$companies = $profileDao->getSuppliersByIds(array_keys($authsTemp));
			foreach ($companies as $company) {
				$authsTemp[$company["SPB_BRANCH_CODE"]]["companyInfo"] = $company;
				$country = $countriesDAO->fetchCountryByCode($company["SPB_COUNTRY"]);
				if (count($country)==1)
				{
					$authsTemp[$company["SPB_BRANCH_CODE"]]["companyInfo"]["CNT_NAME"] = $country[0]["CNT_NAME"];
					$authsTemp[$company["SPB_BRANCH_CODE"]]["companyInfo"]["CNT_CON_CODE"] = $country[0]["CNT_CON_CODE"];
				}
			}

			$auths = $authsTemp;
		} else {
			$requests = null;
			$auths = null;
		}
		$this->view->authsCount = Shipserv_CategoryAuthorisation::getAuthorisationsCount($category,$region,$name);
		$this->view->currentPage = $page;
		$this->view->currentRegion = $region;
		$this->view->currentNameFilter = $name;
		$this->view->pendingRequests = $requests;
		$this->view->authorisations = $auths;
		$this->view->selectedCategory = $category;
		$this->view->user              = $this->_getUser();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->managedCategories = $managedCategories;
		$this->view->ownerDetails = $this->_makeOwnerDetails();
		$this->view->companyDetail     = $this->getCompanyDetail();

	}

	/**
	 * Refactored by Yuriy Akopov on 2016-1024, S18410
	 *
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public function companyMembershipsAction()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;
		$config = Zend_Registry::get('config');

		$this->redirectToDefaultPageByCompanyType('v');

		// Ensure type in correct domain
		if (@$params['type'] != 'v' && @$params['type'] != 'b') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		// Ensure company ID provided
		if (@$params['id'] == '') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		$uaTypeMap = array(
			'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
			'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
            'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON,
		);

		try {
			$this->_helper->companyUser->fetchUsers($uaTypeMap[$params['type']], $params['id'], $pendingUsers, $approvedUsers);
		} catch (Myshipserv_Exception_PermissionViolation $e) {
			// todo: add a flash message?
			$this->_helper->redirector('companies', 'profile', null, array());
			exit;
		} catch (Myshipserv_Exception_NotLoggedIn $e) {
			$this->redirectToLogin();
			exit;
		}

		$managedMemberships = Shipserv_MembershipAuthorisation::getOwnedMemberships($params['id']);

		if (!$params["membership"]) {
			if (count($managedMemberships) > 0) {
				$membershipRow = current($managedMemberships);
				$membership = $membershipRow['QO_ID'];
			} else {
				throw new Myshipserv_Exception_MessagedException("You don't have permission to manage any membership");
			}
		} else {
			if (isset($managedMemberships[$params['membership']])) {
				$membership = $params['membership'];
			} else {
				throw new Myshipserv_Exception_MessagedException("You don't have permission to manage these memberships");
			}
		}

		$page = 1;
		if ($params['page']) {
			$page = intval($params['page']);
		}

		$region = "";
		if ($params['region']) {
			$region = $params['region'];
		}

		$name = "";
		if ($params['filterName']) {
			$name = $params['filterName'];
		}

		$membershipForm = new Myshipserv_Form_Membership();
		try {
			if ($this->getRequest()->isPost() && $membershipForm->isValid($this->getRequest()->getParams())) {
                if (!$membershipForm->membershipLogo->receive()) {
                    $messages = $membershipForm->membershipLogo->getMessages();
                    throw new Exception(implode("\n", $messages));
                } else {
					$logoFile = new Myshipserv_AffiliateLogo($membershipForm->membershipLogo->getFileName());
					$uploadedFile = $logoFile->save();
					$qoAdapter = new Shipserv_Oracle_Memberships($this->getInvokeArg('bootstrap')->getResource('db'));
					$qoAdapter->updateImage($uploadedFile["filename"],$membership);
				}
			}
		} catch (Exception $e) {

		}

		if ($params['hdnAction']) {
			switch ($params['hdnAction']) {
				case 'add':
					$membershipId = intval($params['membership']);
					$companyId = intval($params['supplierToAddId']);

					if(($companyId > 0) and ($membershipId > 0)) {
						//check if supplier is not authorised already
						if (!$auth = Shipserv_MembershipAuthorisation::fetch($companyId, $membershipId, true)) {
							//check if supplier has default authorisation (ie isAuthorised is null)
							if (!$auth = Shipserv_MembershipAuthorisation::fetch($companyId, $membershipId, null)) {
								//create new authorisation
								Shipserv_MembershipAuthorisation::create($companyId, $membershipId, true);
								//echo "here";
							} else {
								$auth->authorise();
							}
						}
					}

					break;

				case 'delete':
					if (count($params["selectedSupplierId"]) > 0) {
						foreach ($params["selectedSupplierId"] as $companyId) {
							Shipserv_MembershipAuthorisation::removeCompanyAuthsForMembership($companyId, $membership);
						}
					}

					break;
			}
		}

		if ($membership) {
			$requests = Shipserv_MembershipAuthorisation::getRequests($membership);
			$authsTemp = array();

			$auths = Shipserv_MembershipAuthorisation::getAuthorisations($membership,$page,$region,$name);
			foreach ($auths as $auth) {
				if (!isset($authsTemp[$auth->companyId])) {
					$authsTemp[$auth->companyId] = array();
				}

				$authsTemp[$auth->companyId]["auth"] = $auth;
			}

			$profileDao = new Shipserv_Oracle_Profile(Shipserv_Helper_Database::getDb());
			$countriesDAO = new Shipserv_Oracle_Countries(Shipserv_Helper_Database::getDb());

			$companies = $profileDao->getSuppliersByIds(array_keys($authsTemp));
			foreach ($companies as $company) {
				$authsTemp[$company['SPB_BRANCH_CODE']]['companyInfo'] = $company;
				$country = $countriesDAO->fetchCountryByCode($company['SPB_COUNTRY']);

				if (count($country) == 1) {
					$authsTemp[$company['SPB_BRANCH_CODE']]['companyInfo']['CNT_NAME']     = $country[0]['CNT_NAME'];
					$authsTemp[$company['SPB_BRANCH_CODE']]['companyInfo']['CNT_CON_CODE'] = $country[0]['CNT_CON_CODE'];
				}
			}

			$auths = $authsTemp;
		} else {
			$requests = null;
			$auths = null;
		}

		$this->view->authsCount         = Shipserv_MembershipAuthorisation::getAuthorisationsCount($membership,$region,$name);
		$this->view->currentPage        = $page;
		$this->view->currentRegion      = $region;
		$this->view->currentNameFilter  = $name;
		$this->view->pendingRequests    = $requests;
		$this->view->authorisations     = $auths;
		$this->view->selectedMembership = $membership;
		$this->view->user               = $this->_getUser();
		$this->view->profileMenuHelper  = $this->_helper->profileMenu;
		$this->view->managedMemberships = Shipserv_MembershipAuthorisation::getOwnedMemberships($params['id']);
		$this->view->companyDetail      = $this->getCompanyDetail();
		$this->view->logoUrlPrefix      = $config->shipserv->affManagement->images->urlPrefix;
	}

	
	public function notificationsAction () {
		if ($this->getRequest()->isPost()) {
			$nForm = new Myshipserv_Form_Alerts();
			if ($nForm->isValid($_POST)) {
				$this->_helper->profileNotifications->saveForm($nForm);
                $this->updateUserInSession();

				$this->view->saveSuccess = true;
			}
		}

		if (!$nForm) {
			$nForm = $this->_helper->profileNotifications->makeForm();
		}

		$this->view->user              = $this->_getUser();
		$this->view->notificationForm  = $nForm;
		$this->view->ownerDetails      = $this->_makeOwnerDetails();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->companyDetail     = $this->getCompanyDetail();

	}

	/**
	 * Ajax/JSON controller for searching suppliers via auto-complete
	 */
	public function supplierSearchAction ()
	{
		$fParams = $this->_getAllParams();
		$fParams['type'] = 'v';
		$this->_forward('company-search', 'profile', null, $fParams);
		return;
	}

	private function makeSearchDetail ($rawCity, $rawCountry, $id)
	{
		$city = trim($rawCity);
		$country = trim($rawCountry);

		// Avoid junk
		if (strlen($city) < 2) 
            $city = '';
		elseif (strtolower($city) == 'city')  
            $city = '';

		if (strtolower($country)=='country') 
		    $country = '';
		if (strlen($country)=='2') {
			$countryDao = new Shipserv_Oracle_Countries($this->getInvokeArg('bootstrap')->getResource('db'));
			$countryRecord = $countryDao->fetchCountryByCode($country);
			$country = $countryRecord[0]["CNT_NAME"];
		}

		$dParts = array();
		if ($city != '') 
		    $dParts[] = $city;
		if ($country != '') 
		    $dParts[] = $country;
		if ($id != '') 
		    $dParts[] = 'TNID: ' . $id;

		return join(', ', $dParts);
	}
	

	/**
	 * ...?type=[v|b]&value=<search string>
	 *
	 * Optional: excUsrComps=[0|1] to remove companies of which logged-in user
	 * 		is a member, or has pending join requests.
	 */
	public function companySearchAction()
	{

		// SPC-3209 remove cache for request
		// $memKey = null;
		// $config = Zend_Registry::get('options');
		// $memcache = new Memcache();
		// if ($memcache->connect($config['memcache']['server']['host'], $config['memcache']['server']['port'])) {
		// 	$memKey = md5('Comnay_Search_' . print_r($this->getRequest()->getParams(), true));
		// 	$data = $memcache->get($memKey);
		// 	if ($data) {
		// 		$this->_helper->json((array)$data);
		// 		return;
		// 	}
		// }
		
		$db = $this->db;

		// Fetch & validate search string
		$sParams = array();
		$sParams['value'] = trim($this->_getParam('value', ''));
		if (strlen($sParams['value']) < 3)
		{
			throw new Exception("Invalid type");
		}

		
		// Fetch & validate search type
		$typeTrans = array('v' => 'SPB', 'b' => 'BYO', 'bb' => 'BYB', 'c' => 'CON');
		$sParams['type'] = @$typeTrans[$this->_getParam('type', '')];
		if ($sParams['type'] === null)
		{
			throw new Exception("Invalid type");
		}

		// Remove user's companies
		$sParams['excUsrComps'] = ($this->_getParam('excUsrComps') == 1);

		// Remove unjoinable companies
		$sParams['excNonJoinReqComps'] = ($this->_getParam('excNonJoinReqComps') == 1);

		if ($sParams['type'] != 'SPB' && $sParams['type'] != 'BYO' && $sParams['type'] != 'BYB' && $sParams['type'] != 'CON')
		{
			throw new Exception("Invalid company type");
		}


		// Determine if this is a name search, or an ID search
		$nameSearchRes = null;
		$keyword = $sParams['value'];
		
		// cached for 1 hr
		if( $sParams['type'] == "SPB" )
		{
            $buyerFilterIdsStr = $this->_getParam('byo');
            $buyerFilterIds = array();

            if (is_array($buyerFilterIdsStr)) {
                $buyerFilterIds = $buyerFilterIdsStr;
            } else {
                if (strlen($buyerFilterIdsStr)) {
                    $buyerFilterIds = explode(',', $buyerFilterIdsStr);
                    foreach ($buyerFilterIds as $buyerIndex => $buyerId) {
                        $buyerId = trim($buyerId);

                        if (strlen($buyerId) === 0) {
                            unset($buyerFilterIds[$buyerIndex]);
                        } else {
                            $buyerFilterIds[$buyerIndex] = $buyerId;
                        }
                    }
                }
            }

            $prevMonths = $this->_getNonEmptyParam('pevMonths');  // @todo: correct the typo in param name is JS

            $includeNonPublished = ($this->_getParam('unpublished', '') === '1');
			$hideTestAccounts = ($this->_getParam('hideTestAccounts', '') === '1');
			$supplierActiveStatus = ($this->_getParam('supplierActiveStatus', '') === '1');

			$adapter = new Shipserv_Oracle_Suppliers($db);
			$result = $adapter->getListOfCompanyByKeyword($keyword, $buyerFilterIds, $prevMonths, $includeNonPublished, $hideTestAccounts, $supplierActiveStatus);
			$t = 'S';
		}
		else if( $sParams['type'] == "BYO" )
		{
			$adapter = new Shipserv_Oracle_Buyer($db);
			$result = $adapter->getListOfCompanyByKeyword($keyword);
			$t = 'B';
		}
		else if( $sParams['type'] == "BYB" )
		{
			$adapter = new Shipserv_Oracle_BuyerBranch($db);
			$result = $adapter->getListOfCompanyByKeyword($keyword);
			$t = 'BB';
		}
        else if( $sParams['type'] == "CON" )
        {
            $result = Shipserv_Consortia::getListOfCompanyByKeyword($keyword);
            $t = 'C';
        }
		$idsToSearch = array();
		foreach((array)$result as $row)
		{
			switch ($sParams['type']) {
				case 'SPB':
					$nameSearchRes[] = array(
							'value' => $row['VALUE'],
							'display' => $row['DISPLAY'],
							'nonDisplay'=>$row['VALUE'],
							'location'=> $row['LOCATION'],
							'code' => $row['CODE'],
							'pk' => $row['PK'],
							'country' => $row['COUNTRY'],
							'hasOrder' => $row['HAS_ORDER']
					);
					break;
				default:
					$nameSearchRes[] = array(
							'value' => $row['VALUE'],
							'display' => $row['DISPLAY'],
							'nonDisplay'=>$row['VALUE'],
							'location'=> $row['LOCATION'],
							'code' => $row['CODE'],
							'pk' => $row['PK']
					);
					break;
			}

			$idsToSearch[] = ($t == "S") ? $row['TNID'] : $row['PARENT_TNID'];
		}


		// Remove companies of which current user is a member
		// or has pending join requests.
		if ($sParams['excUsrComps']) {
			$user = Shipserv_User::isLoggedIn();
			if ($user) {
				$uCompColl = $user->fetchCompanies();
				$uJoinReqs = $user->fetchCompanyJoinRequests();
				if ($sParams['type'] == 'SPB') {
					$uCompIds = $user->fetchCompanies()->getSupplierIds();
					foreach ($uJoinReqs as $jr) {
						if ($jr['PUCR_COMPANY_TYPE'] == 'SPB')
						{
							$uCompIds[] = $jr['PUCR_COMPANY_ID'];
						}
					}
				} elseif ($sParams['type'] == 'BYO') {
					$uCompIds = $user->fetchCompanies()->getBuyerIds();
					foreach ($uJoinReqs as $jr) {
						if ($jr['PUCR_COMPANY_TYPE'] == 'BYO') {
							$uCompIds[] = $jr['PUCR_COMPANY_ID'];
						}
					}
				} elseif ($sParams['type'] == 'BYB') {
					$uCompIds = $user->fetchCompanies()->getBuyerIds();
					foreach ($uJoinReqs as $jr) {
						if ($jr['PUCR_COMPANY_TYPE'] == 'BYB') {
							$uCompIds[] = $jr['PUCR_COMPANY_ID'];
						}
					}
				} elseif ($sParams['type'] == 'CON') {
                    $uCompIds = $user->fetchCompanies()->getConsortiaIds();
                    foreach ($uJoinReqs as $jr) {
                        if ($jr['PUCR_COMPANY_TYPE'] == 'CON') {
                            $uCompIds[] = $jr['PUCR_COMPANY_ID'];
                        }
                    }
                } else {
					throw new Exception("Logic failure");
				}

				foreach ($uCompIds as $id) {
					unset($nameSearchRes[$id]);
				}
			}
		}
		$data = array();
		foreach( (array) $nameSearchRes as $row )
		{
			if( is_array($uCompIds) == true && @in_array($row['pk'], $uCompIds) == false )
			{
				$row['code'] = str_replace("BYB", "BYO", $row['code']);
				$data[] = $row;
			}

			else if( is_array($uCompIds) == false )
			{
				$data[] = $row;
			}
		}

		if ($memKey) {
			$memcache->set($memKey, $data);
		}
		
		$this->_helper->json((array) $data );
	}

	public function joinCompanyAction ()
	{
		$type = (string) $this->_getParam('type', '');
		$id = (int) $this->_getParam('id');

		$res = array();
		$res['ok'] = false;
		$res['msg'] = 'Undefined error';

		try
		{
			$uActions = new Myshipserv_UserCompany_Actions(
				$this->getInvokeArg('bootstrap')->getResource('db'),
				$this->_getUser()->userId, $this->_getUser()->email);

			$reqId = $uActions->requestJoinCompany($type, $id);

			$res['ok']      = true;
			$res['msg']     = '';
			$res['company'] = $this->_helper->companies->makeCompanyFromRequest($this->_getUser(), $reqId);
		}
		catch (Myshipserv_UserCompany_Actions_Exception $e)
		{
			if ($e->getCode() == Myshipserv_UserCompany_Actions_Exception::EXC_JOIN_ALREADY_MEMBER)
			{
				$res['msg'] = 'You are already a member of this company';
			}
			elseif ($e->getCode() == Myshipserv_UserCompany_Actions_Exception::EXC_JOIN_ALREADY_REQUESTED)
			{
				$res['msg'] = 'You already have a pending / confirmed join request for this company';
			}
			elseif ($e->getCode() == Myshipserv_UserCompany_Actions_Exception::EXC_JOIN_NOT_JOIN_REQABLE)
			{
				$res['msg'] = 'This company does not accept membership requests';
			}
		}
		catch (Exception $e)
		{
			// Do nothing
			$res['msg'] = $e->getMessage();

		}

		//$this->view->assign($res);
		$this->_helper->json((array) $res );
	}

	
	public function approveUserRequestAction()
	{
		// expects request parameter
		$reqId = (int) $this->_getParam('reqId');

		$res        = array();
		$res['ok']  = false;
		$res['msg'] = 'Undefined error';

		try
		{
			// approve the request
			$uArr = $this->_helper->companyUser->approveJoinRequest($reqId);

			$res['ok']   = true;
			$res['msg']  = '';
			$res['user']      = $uArr;
		}
		// specific Exceptions should go here...

		catch (Exception $e)
		{
			$res['msg'] = $e->getMessage();
		}

		// should return details of the approved user as JSON
		//$this->view->assign($res);
		$this->_helper->json((array) $res );
	}

	
	public function rejectUserRequestAction()
	{
		// expects request parameter
		$reqId = (int) $this->_getParam('reqId');

		$res        = array();
		$res['ok']  = false;
		$res['msg'] = 'Undefined error';

		try
		{
			$this->_helper->companyUser->rejectJoinRequest($reqId);

			$res['ok']   = true;
			$res['msg']  = '';
		}
		catch (Exception $e)
		{

		}

		//$this->view->assign($res);
		$this->_helper->json((array) $res );
	}


	/**
	 * Add user to a company.
	 *
	 * ...?eml=<user_email>&type=[v|b]&id=<spb_or_byo_code>
	 */
	public function addUserAction()
	{
		// Prepare default return array
		$res = array(
			'ok' => false,
			'user' => null,
			'msg' => "Undefined error",
	        'showTradingAccount' => (
                $this->user 
                && (
                    $this->user->isAdminOfBuyer($this->_getParam('id', 0)) 
                    || ($this->user->isShipservUser() && $this->user->canPerform('PSG_ADD_USER'))
                )
            )
		);

		try
		{
			// To hold validated parameters
			$params = array();

			// Normalise & validate e-mail
			$params['eml'] = strtolower(trim($this->_getParam('eml', '')));
			if ($params['eml'] == '') {
				throw new ProfileController_Exception("Expected e-mail as 'eml'");
			}

			if (strlen($params['eml']) > 100) {
				$res['msg'] = "Email address may not exceed 100 characters.";
				$this->_helper->json((array)$res);
				//$this->view->assign($res);
				return;
			}

			// Type (v|b) doesn't need validation
			$params['userLevel'] = $this->_getParam('userLevel', '');
			$params['type'] = $this->_getParam('type', '');
			$typeConv = array('b' => 'BYO', 'v' => 'SPB');
			if (!isset($typeConv[$params['type']])) {
			    trigger_error('ProfileController::addUserAction - Profile type "' . $params['type'] . '" is not an accepted type', E_USER_WARNING);
			    throw new ProfileController_Exception('Profile type ' . $params['type'] . ' is not an accepted type');
			}
			$orgType = $typeConv[$params['type']];
			
			// Check company ID present & valid
			$params['id'] = (int) $this->_getParam('id', 0);
			if ($params['id'] == 0) {
				throw new ProfileController_Exception("Expected company ID as 'id'");
			}

			// Pass add-user request to business layer (add user to main company)
			$uaa = Myshipserv_UserCompany_AdminActions::forLoggedUser();
			$uaa->addUserToCompany(
				$params['eml'],
				$orgType,
				$params['id'],
				$params['userLevel'],
		        $this->user->username
			);
	
			// Fetch newly created/modified user-company relationship
			$ucd = new Myshipserv_UserCompany_Domain($this->getInvokeArg('bootstrap')->getResource('db'));
			$userCompany = $ucd->fetchUserCompany($params['eml'], $orgType, $params['id'], true);
			
			//Add user to child branches S16320
			if ($params['type'] == 'b') {
			    $dao = new Shipserv_Oracle_PagesUserCompany();		    
    			foreach ((array) Shipserv_Buyer::getInstanceById($params['id'])->getBranchesTnid(false) as $childBranch) {
    		        $dao->insertUserCompany(
    	                $userCompany->userId,
    	                'BYB',
    	                $childBranch->bybBranchCode,
    	                $params['userLevel'],
    	                ($userCompany->emailConfirmed === 'Y'? Shipserv_Oracle_PagesUserCompany::STATUS_ACTIVE : Shipserv_Oracle_PagesUserCompany::STATUS_PENDING), //PUC_STATUS
    	                true, //upsert
    	                1, 1, 1, 1, 1, 1, 1, //apps permissions
    	                0 //default
                    );	
    			}
			}
			
			// S4932
			if ($orgType == 'SPB') {
				$supplier = Shipserv_Supplier::fetch( $params['id'] );
				$supplier->purgeMemcache();
			}

			// Form user-company information for return
			$res['userCompany'] = array(
				'userId'      => $userCompany->userId,
				'username'    => $userCompany->username,
				'firstName'   => $userCompany->firstName,
				'lastName'    => $userCompany->lastName,
				'email'       => $userCompany->email,
				'companyType' => $params['type'],
				'companyId'   => $params['id'],
				'roles' => array('administrator' => false),
			);

			if ($orgType == 'SPB') {
				$supplier = Shipserv_Supplier::fetch($params['id']);
				$supplier->purgeMemcache();
				
			}

			$res['pending'] = false;

			// Check status of user-company relationship and provide success message accordingly
			switch ($userCompany->getStatus())
			{
				case 'ACT':
					$res['msg'] = 'User added.';
					break;

				case 'PEN':
					$res['msg'] = 'User added pending confirmation of e-mail address.';
					$res['pending'] = true;
					break;

				default:
					// Logic error
					throw new ProfileController_Exception("Unable to add user: please try again later, or contact support.");
			}

			// Success
			$res['ok'] = true;

			$user = Shipserv_User::getInstanceById($userCompany->userId);
			$user->updateRights();

			// send activation email to this user to activate his/her account (the email is activated automatically for new users, 
			// but already existing users may have a still pending email, and we don't want to update the status to valid 
			// because of security concerns)
			if ($user->emailConfirmed <> Shipserv_User::EMAIL_CONFIRMED_CONFIRMED) {
			    if ($orgType == 'SPB') {
			        $org = Shipserv_Supplier::getInstanceById($params['id']);
			        $orgName = $org->name;
			    } elseif ($orgType == 'BYO') {
			        $organisation = new Shipserv_Oracle_BuyerOrganisations();
			        $org = $organisation->fetchBuyerOrganisationById($params['id']);			        
			        $orgName = $org[0]['BYO_NAME'];
			    }

				$user->sendJoinCompanyEmailActivation($orgName);
			}
		}
		catch (Shipserv_Oracle_User_Exception_FailCreateUser $e)
		{
			if ($e->getCode() == Shipserv_Oracle_User_Exception_FailCreateUser::CODE_BAD_EMAIL) {
				$res['msg'] = "Invalid e-mail";
			} else {
				// Happens with bad data.
				// e.g. if there is a USER row but no PAGES_USER row, then the user isn't found, the code tries to create it and there's a PK clash.
				$res['msg'] = "Unable to create user: please try again later, or contact support.";
			}
		}
		catch (Myshipserv_UserCompany_AdminActions_Exception $e)
		{
			if ($e->getCode() == Myshipserv_UserCompany_AdminActions_Exception::CODE_ADD_ALREADY_MEMBER) {
				$res['msg'] = "User is already a member, or pending member of company.";
			} elseif ($e->getCode() == Myshipserv_UserCompany_AdminActions_Exception::CODE_ADD_SELF) {
				$res['msg'] = "You cannot add yourself to a company.";
			}
		}
		catch (ProfileController_Exception $e)
		{
			$res['msg'] = $e->getMessage();
		}
		catch (Exception $e)
		{
			$res['msg'] = "Something went wrong, please try again later or contact support.";
			$res['msg'] = (string) $e;
		}

		$this->_getUser()->logActivity(Shipserv_User_Activity::USER_MANAGEMENT_ADD, 'SUPPLIER_BRANCH', $params['id'], $params['eml']);
		$this->_helper->json((array)$res);
	}

	/**
	 * Add user to a company.
	 *
	 * ...?eml=<user_email>&type=[v|b]&id=<spb_or_byo_code>
	 */
	public function addUserSendEmailAction ()
	{
		// Prepare default return array
		$res = array(
			'ok' => false,
			'user' => null,
			'msg' => "Undefined error",
		);

		try
		{
			// To hold validated parameters
			$params = array();

			// Normalise & validate e-mail
			$params['eml'] = strtolower(trim($this->_getParam('eml', '')));
			if ($params['eml'] == '')
			{
				throw new ProfileController_Exception("Expected e-mail as 'eml'");
			}

			if (strlen($params['eml']) > 100)
			{
				$res['msg'] = "Email address may not exceed 100 characters.";
				$this->_helper->json((array)$res);
				//$this->view->assign($res);
				return;
			}


			// Type (v|b) doesn't need validation
			$params['type'] = $this->_getParam('type', '');
						$params['userLevel'] = $this->_getParam('userLevel', '');

			// Check company ID present & valid
			$params['id'] = (int) $this->_getParam('id', 0);
			if ($params['id'] == 0)
			{
				throw new ProfileController_Exception("Expected company ID as 'id'");
			}
			// Pass add-user request to business layer
			$uaa = Myshipserv_UserCompany_AdminActions::forLoggedUser();
			$typeConv = array('b' => 'BYO', 'v' => 'SPB');
			$uaa->addUserToCompany(
				$params['eml'],
				@$typeConv[$params['type']],
				$params['id'],
				$params['userLevel']

			);

			// Fetch newly created/modified user-company relationship
			$ucd = new Myshipserv_UserCompany_Domain($this->getInvokeArg('bootstrap')->getResource('db'));
			$userCompany = $ucd->fetchUserCompany($params['eml'], @$typeConv[$params['type']], $params['id'], true);

			// Form user-company information for return
			$res['userCompany'] = array(
				'userId'      => $userCompany->userId,
				'username'    => $userCompany->username,
				'firstName'   => $userCompany->firstName,
				'lastName'    => $userCompany->lastName,
				'email'       => $userCompany->email,
				'companyType' => $params['type'],
				'companyId'   => $params['id'],
				'roles' => array('administrator' => ($params['userLevel']=="ADM")?true:false),
			);

			// S4932
			if( @$typeConv[$params['type']] == "SPB")
			{
				$supplier = Shipserv_Supplier::fetch( $params['id'] );
				$supplier->purgeMemcache();
			}

			// Check status of user-company relationship and provide success message accordingly
			switch ($userCompany->getStatus())
			{
				case 'ACT':
					$res['msg'] = 'User added.';
					break;

				case 'PEN':
					$res['msg'] = 'User added pending confirmation of e-mail address.';
					break;

				default:
					// Logic error
					throw new ProfileController_Exception("Unable to add user: please try again later, or contact support.");
			}

			// Success
			$res['ok'] = true;
		}
		catch (Shipserv_Oracle_User_Exception_FailCreateUser $e)
		{
			if ($e->getCode() == Shipserv_Oracle_User_Exception_FailCreateUser::CODE_BAD_EMAIL)
			{
				$res['msg'] = "Invalid e-mail";
			}
			else
			{
				// Happens with bad data.
				// e.g. if there is a USER row but no PAGES_USER row, then the user isn't found, the code tries to create it and there's a PK clash.
				$res['msg'] = "Unable to create user: please try again later, or contact support.";
			}
		}
		catch (Myshipserv_UserCompany_AdminActions_Exception $e)
		{
			if ($e->getCode() == Myshipserv_UserCompany_AdminActions_Exception::CODE_ADD_ALREADY_MEMBER)
			{
				$res['msg'] = "User is already a member, or pending member of company.";
			}
			elseif ($e->getCode() == Myshipserv_UserCompany_AdminActions_Exception::CODE_ADD_SELF)
			{
				$res['msg'] = "You cannot add yourself to a company.";
			}
		}
		catch (ProfileController_Exception $e)
		{
			$res['msg'] = $e->getMessage();
		}
		catch (Exception $e)
		{
			$res['msg'] = $e->getMessage() . "Something went wrong, please try again later or contact support.";
			//$res['msg'] = (string) $e;
		}

		$this->_getUser()->logActivity(Shipserv_User_Activity::USER_MANAGEMENT_ADD, 'SUPPLIER_BRANCH', $params['id'], $params['eml']);

		//$this->view->assign($res);
		$this->_helper->json((array)$res);
	}

	public function removeUserAction()
	{
		$params = $this->_getAllParams();

		// Ensure type in correct domain
		if (isset($params['type']) && $params['type'] != 'v' && $params['type'] != 'b') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		// Ensure company ID provided
		if (isset($params['id']) && $params['id'] == '') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		// Ensure user ID provided
		if (isset($params['userId']) && $params['userId'] == '') {
			$this->_helper->redirector('companies', 'profile');
			exit;
		}

		$res        = array();
		$res['ok']  = false;
		$res['msg'] = 'Undefined error';

		try
		{
			// remove the user
			$uaTypeMap = array(
				'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
				'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
                'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON,
			);
			$this->_helper->companyUser->removeUser($uaTypeMap[$params['type']], $params['id'], $params['userId']);
			$res['ok']   = true;
			$res['msg']  = '';
		}
		// specific Exceptions should go here...

		catch (Exception $e)
		{

		}

		$user = Shipserv_User::getInstanceById($params['userId']);
		if ($user->isShipservUser() === true) {
			$user->updateRights();
		}

		//$this->view->assign($res);
		$this->_helper->json((array)$res);
	}

	
	public function setAdministratorStatusAction ()
	{
		// expects request parameter
		$userId = (int) $this->_getParam('userId');
		$type   = (string) $this->_getParam('type', '');
		$id     = (int) $this->_getParam('id');
		$status = ($this->_getParam('status') == 'true') ? true : false;

		$res        = array();
		$res['ok']  = false;
		$res['msg'] = 'Undefined error';

		try
		{
			$uaTypeMap = array(
				'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
				'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
                'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON,
			);

			// change the status
			$uActions = new Myshipserv_UserCompany_AdminActions(
				$this->getInvokeArg('bootstrap')->getResource('db'),
				$this->_getUser()->userId);
			$uActions->setUserAdminForCompany($userId, $uaTypeMap[$type], $id, $status);

			$res['ok']   = true;
			$res['msg']  = '';
		}
		// specific Exceptions should go here...

		catch (Exception $e)
		{
			$res['msg'] = (string) $e;
		}
		//print_r($res); die();
		$this->_helper->json((array)$res);
		//$this->view->assign($res);
	}

	
	public function leaveCompanyAction()
	{
		// Expects type = 'v' | 'b' | 'c'
		$type = (string) $this->_getParam('type', '');
		$id = (int) $this->_getParam('id');

		$res = array();
		$res['ok'] = false;
		$res['msg'] = 'Undefined error';

		try
		{
			$uActions = new Myshipserv_UserCompany_Actions(
				$this->getInvokeArg('bootstrap')->getResource('db'),
				$this->_getUser()->userId, $this->_getUser()->email);

			$uaTypeMap = array(
				'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
				'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
                'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON,
			);

			$uActions->leaveCompany($uaTypeMap[$type], $id);

			$res['ok'] = true;
			$res['msg'] = '';
		}
		catch (Myshipserv_UserCompany_Actions_Exception $e)
		{
			if ($e->getCode() == Myshipserv_UserCompany_Actions_Exception::EXC_LEAVE_LAST_ADMIN) {
				$res['msg'] = 'You cannot remove this company as you are its only administrator.';
			}
		}
		catch (Exception $e)
		{
			// Do nothing
		}

		$user = Shipserv_User::isLoggedIn();
		if ($user !== false) {
			$user->updateRights();
		}

		//$this->view->assign($res);
		$this->_helper->json((array)$res);
	}

	
	public function withdrawJoinRequestAction ()
	{
		$reqId = (int) $this->_getParam('reqId');

		$res = array();
		$res['ok'] = false;
		$res['msg'] = 'Undefined error';

		try
		{
			$uActions = new Myshipserv_UserCompany_Actions(
				$this->getInvokeArg('bootstrap')->getResource('db'),
				$this->_getUser()->userId, $this->_getUser()->email);

			$uActions->withdrawJoinCompanyRequest($reqId);

			$res['ok'] = true;
			$res['msg'] = '';
		}
		catch (Exception $e)
		{
			// DO NOTHING

		}

		//$this->view->assign($res);
		$this->_helper->json((array)$res);
	}


	public function companyAutomaticReminderAction()
	{
		$params = $this->params;
		$user = $this->_getUser();
		$orgId = (int)$params['id'];
		$branchId = (int)$params['trac'];
		$userId = (int)$user->userId;
		$tradenetAccess = false;
		
		if ($user) {
			if (!$user->canAccessFeature($user::BRANCH_FILTER_AUTOREMINDER)) {
				if (!($user->isAdminOf($params['id']) || $user->isShipservUser())) {
					throw new Exception("This page is restricted for admins only", 500);
				}
			} else {
				$tradenetAccess = true;
			}
		}
		
		//S20380 Restrict access to non full members
		if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
			$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'account'));
		};
		
        $bybData = Shipserv_Report_Buyer_Match_BuyerBranches::getInstance()->getBuyerBranches(Shipserv_User::BRANCH_FILTER_AUTOREMINDER, true);

        //Check if parameter trac exist, If not and we have companyes, redirect URL
        if (!array_key_exists('trac', $params)) {
        	if (count($bybData) > 0) {
        	    foreach ($bybData as $byb) {
        	        if ($byb['default']) {
        	            $this->redirect("/profile/company-automatic-reminder/type/b/id/".$orgId."/trac/".$byb['id']);
        	        }
        	    }
        	    //If no default, use the first one of the list
        	    $this->redirect("/profile/company-automatic-reminder/type/b/id/".$orgId."/trac/".$bybData[0]['id']);        		
        	}
        }

		$currentSettings = new Shipserv_Profile_CompanyAutomaticReminder();
		$currentSettings->loadReminder($branchId);

		//get all post data
		if ($this->getRequest()->isPost()) {
			// Fetch form from POST
			$currentSettings->setFormParams($this->_getAllParams());
			$validityArray = $currentSettings->validateParams();

			if (count($validityArray) == 0) {
				// if the form data valid, store the form
				if ($branchId > 0 ) {
					$currentSettings->saveReminder($branchId, $userId);

					//Store event
					if ($user) {
						if ($this->getRequest()->getParam('bbs_rmdr_ord_is_enabled') === 'on') {
							$user->logActivity(Shipserv_User_Activity::PO_ARM_ACTIVATED, 'PAGES_USER', $user->userId, $user->email);
						} else {
							$user->logActivity(Shipserv_User_Activity::PO_ARM_DEACTIVATED, 'PAGES_USER', $user->userId, $user->email);
						}
					}

				}
			}
		}

		if ($user) {
			if ($user->isPartOfBuyer()) {
				$user->logActivity(Shipserv_User_Activity::AUTOMATIC_REMINDERS_CLICK, 'PAGES_USER', $user->userId, $user->email);
			}
		}

		$this->view->user              = $user;
		$this->view->tradenetAccess    = $tradenetAccess;
		$this->view->pendingCompanies  = $this->_helper->companies->getMyPendingCompanies($this->_getUser());
		$this->view->ownerDetails      = $this->_makeOwnerDetails();
		$this->view->companyDetail     = $this->getCompanyDetail();
		$this->view->params			   = $params;
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->reminderData	   = $currentSettings;
		$this->view->companies         = $bybData;
		$this->view->orgId		   	   = $orgId;
		$this->view->userTnId		   = $branchId;
	}
	

	public function companySettingsPagesAction ()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		// Holds settings form information
		$viewSettingsForm = array(
			'form' => null,
			'submitted' => 0,
		);

		$db = $this->db;
		$adapter = new Shipserv_Oracle_Suppliers($db);

		// Process POST
		if ($this->getRequest()->isPost()) {
			// Fetch form from POST
			$settingsFormArr = Myshipserv_Form_CompanySettings::fromPost($this->_getAllParams());

			// If a form could be read from POST, process it
			if ($settingsFormArr !== null) {
				// Record that form was submitted
				$viewSettingsForm['submitted'] = 1;

				// If form validated OK, update DB
				if ($settingsFormArr['isValid']) {
					$fValues = $settingsFormArr['form']->getValues();
					Shipserv_Oracle_PagesCompany::getInstance()->setIsJoinRequestable(
						self::companyTypeToDb($this->_getParam('type')),
						$this->_getParam('id'),
						$fValues[Myshipserv_Form_CompanySettings::FIELD_IS_JOIN_REQABLE]
					);
					Shipserv_Oracle_PagesCompany::getInstance()->setAutoReviewSolicitation(
						self::companyTypeToDb($this->_getParam('type')),
						$this->_getParam('id'),
						$fValues[Myshipserv_Form_CompanySettings::FIELD_AUTO_REV_SOLICIT]
					);
					if ($this->_getParam('type') == 'v') {
						$adapter->setPagesRFQIntegrationWithTradeNetStatus(
							$this->_getParam('id'),
							($this->_getParam('tnIntegration') == 1)
						);

					}
				// If form did not validate OK, preserve invalid form state					
				} else {
					$viewSettingsForm['form'] = $settingsFormArr['form'];
				}
			}
		}

		// If form has not been initialised, do so from DB
		if ($viewSettingsForm['form'] === null) {
			$viewSettingsForm['form'] = Myshipserv_Form_CompanySettings::fromDb(
				self::companyTypeToDb($this->_getParam('type')),
				$this->_getParam('id')
			);
		}

		// Fetch array of pending users for view pending highlight
		$this->_helper->companyUser->fetchUsers(
			self::companyTypeToDb($this->_getParam('type')),
			$this->_getParam('id'),
			$pendingUsers,
			$approvedUsers
		);

		$supplier = Shipserv_Supplier::fetch($this->_getParam('id'));
		$this->view->params			   = $params;
		$this->view->user              = $this->_getUser();
		$this->view->ownerDetails      = $this->_makeOwnerDetails();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->pendingUsers      = $pendingUsers;
		$this->view->settingsForm      = $viewSettingsForm;
		$this->view->companyDetail     = $this->getCompanyDetail();
		$this->view->isIntegrated	   = $adapter->getPagesRFQIntegrationWithTradeNetStatus($this->_getParam('id'));
		$this->view->supplier		   = $supplier;
	}
	

    //Match settings
    public function companySettingsMatchAction () {

		$params = $this->params;

		$user = $this->_getUser();

		if ($user) {
			$tnUser = $user->getTradenetUser();
			if (!$tnUser->canAccessSpendManagement()) {
				throw new Exception("You are not authorised to use this service");
			}
		}
		
		//S20380 Restrict access to non full members
		if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
			$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'account'));
		};

        // set the layout to the new one
        //$this->_helper->layout->setLayout('default');

		// Holds settings form information
		$viewSettingsForm = array(
			'form' => array(
                'method' => 'POST'
			),
			'submitted' => 0,
		);


		// Fetch array of pending users for view pending highlight
		$this->_helper->companyUser->fetchUsers(
			self::companyTypeToDb($this->_getParam('type')),
			$this->_getParam('id'),
			$pendingUsers,
			$approvedUsers
		);

		//check if the approved supplier link has to be displayed

		$supplier = Shipserv_Supplier::fetch($this->_getParam('id'));
		$this->view->params			   = $params;
		$this->view->user              = $user;
		$this->view->ownerDetails      = $this->_makeOwnerDetails();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->pendingUsers      = $pendingUsers;
		$this->view->settingsForm      = $viewSettingsForm;
		$this->view->companyDetail     = $this->getCompanyDetail();
		$this->view->supplier		   = $supplier;
		$this->view->canAccessApprovedSupplier	= $tnUser->canAccessApprovedSupplier();
    }


	private static function companyTypeToDb ($feType)
	{
		static $uaTypeMap = array(
			'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
			'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
            'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON
		);

		if (array_key_exists($feType, $uaTypeMap)) {
			return $uaTypeMap[$feType];
		} else {
			throw new Exception("Unrecognised company type '$feType'");
		}
	}

	
	/**
	 * Ajax call used for fetching the total number of pending actions
	 *
	 * @access public
	 */
	public function fetchPendingActionsAction ()
	{
		$count = $this->_helper->pendingAction->countPendingActions();

		$pendingActions = array('pendingActions' => $count);
		//$this->view->assign($pendingActions);
		$this->_helper->json((array)$pendingActions);
	}

	
	public function userManagementAction()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		try
		{
			if ($params['tnid'] != "") {
				$userArr = $this->_helper->companyUser->fetchUsersPenEmlAsAppr(Myshipserv_UserCompany_Actions::COMP_TYPE_SPB, $params['tnid']);
				$this->view->users = $userArr;
				$this->view->supplier = Shipserv_Supplier::fetch($params['tnid']);
			}

			$this->view->ownerDetails     		= $this->_makeOwnerDetails();
			$this->view->loggedMember     		= Shipserv_User::isLoggedIn();

			$this->view->user                  	= $this->_getUser();
			$this->view->profileMenuHelper     	= $this->_helper->profileMenu;
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}

	}

	public function downloadAction()
    {
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		$file = '/tmp/enquiry/excel/' . $params['fileName'];

	    if (file_exists($file)) {

       		$tnid = str_replace("enquiries_for_TNID_", "", $params['fileName']);
       		$tnid = str_replace(".xlsx", "", $tnid);

       		// Log the download
       		$this->_getUser()->logActivity(Shipserv_User_Activity::ENQUIRY_BROWSER_EXPORT_TO_EXCEL, 'SUPPLIER_BRANCH', $tnid);

	        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	        header("Cache-Control: no-store, no-cache, must-revalidate");
	        header("Cache-Control: post-check=0, pre-check=0", false);
	        header("Pragma: no-cache");
	        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        	header('Content-Disposition: attachment;filename="' . basename($file). '"');

	        ob_clean();
		    flush();
		    readfile($file);
		    exit;
		}
    }

    
    public function exportEnquiryToExcelAction()
    {
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

    	// Tnid is required to export this to excel
    	if (is_null( $params['tnid'])) {
			throw new Myshipserv_Exception_MessagedException("Incorrect TNID", 400);
       	}

		// set the default date
		$now = new DateTime();
		$lastYear = new DateTime();

		$now->setDate(date('Y'), date('m'), date('d')+1);
		$lastYear->setDate(date('Y')-1, date('m'), date('d'));

		// store it to a data structure to be passed around
		$period = array('start' => $lastYear, 'end' => $now );


       	// Create or compile such report
    	$enquiries = Shipserv_Enquiry::getInstanceByTnid($params['tnid'], null, null, $totalFound, $period);
    	$enquiryReport = new Shipserv_Enquiry_Report( $params['tnid'], $enquiries, $period );

    	$this->_helper->layout->disableLayout();
	    $this->_helper->viewRenderer->setNoRender();

    	// Export the report to excel returning the file path to the physical file
		$excelFile = Shipserv_Enquiry_ReportConverter::convert( $enquiryReport, "xlsx" );
   		$response = new Myshipserv_Response("200", "OK", $excelFile);
		$this->_helper->json((array) $response->toArray() );
    }

    
	public function enquiryBrowserAction()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		$config  = Zend_Registry::get('config');

		try
		{
			// set the default date
			$now = new DateTime();
			$lastYear = new DateTime();

			$now->setDate(date('Y'), date('m'), date('d'));
			$lastYear->setDate(date('Y')-1, date('m'), date('d'));

			// store it to a data structure to be passed around
			$period = array('start' => $lastYear, 'end' => $now );

			// for view
			$this->view->ownerDetails = $this->_makeOwnerDetails();

			// get supplier
			$supplier = Shipserv_Supplier::fetch($params['tnid']);

			$this->_getUser()->logActivity(Shipserv_User_Activity::ENQUIRY_BROWSER_VIEW, 'SUPPLIER_BRANCH', $params['tnid']);

			// pagination
			$page = (!isset( $params['page'])) ? 1: $params['page'];
			$totalPerPage = (!isset( $params['total'])) ? 25: $params['total'];

			$this->view->enquiries = Shipserv_Enquiry::getInstanceByTnid($params['tnid'], $page-1, $totalPerPage, $totalFound, $period);
			$this->view->statistic = $supplier->getEnquiriesStatistic( $period );

			$this->view->config = $config;
			$this->view->params	= $params;
			$this->view->supplier = $supplier;

			$this->view->user = $this->_getUser();
			$this->view->profileMenuHelper = $this->_helper->profileMenu;
			$this->view->totalFound = $totalFound;
			$this->view->currentPage = $page;
			$this->view->pageSize = $totalPerPage;
			$this->view->period = $period;
			$this->view->companyDetail     = $this->getCompanyDetail();

			$this->view->tnid = $params['tnid'];
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}

	}


	public function enquiryUpdateResponseDateAction()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		$enquiry = Shipserv_Enquiry::getInstanceByIdAndTnid( $params['enquiryId'], $params['tnid'] );
		if ($enquiry->setRepliedDate()) {
			$response = new Myshipserv_Response("200", "OK");
		} else {
			$response = new Myshipserv_Response("500", "NOT OK");
		}

		$this->_helper->json((array) $response->toArray() );
	}
	
	
	private function getActiveCompany()
	{
		$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		return $sessionActiveCompany;
	}
	
	
	private function redirectToDefaultPageByCompanyType( $companyType )
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		$user = $this->_getUser();

		if ($this->_getUser() === false) {
			//$this->redirect("/profile/companies");
		}

		if( $companyType == 'v' && $params['type'] == "b" ) {
			if (is_object( $user ) && $user->isAdminOfBuyer( $sessionActiveCompany->id )) {
				$this->redirect("/profile/company-people/type/b/id/" . $sessionActiveCompany->id, array('code' => 301));
			} else {
				$this->redirect("/profile/company-reviews/type/b/id/" . $sessionActiveCompany->id, array('code' => 301));
			}

			exit;
		} else if ($companyType == 'b' && $params['type'] == "v") {
			$this->redirect("/profile/company-profile/type/v/id/" . $sessionActiveCompany->id, array('code' => 301));
			exit;
		}
	}
	
	
	public function companyProfileAction()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();

		$this->redirectToDefaultPageByCompanyType('v');

		// Ensure company ID provided
		if (@$params['id'] == '') {
			$this->_helper->redirector('companies', 'profile', array('code' => 301));
			exit;
		}

		$uaTypeMap = array(
			'v' => Myshipserv_UserCompany_Actions::COMP_TYPE_SPB,
			'b' => Myshipserv_UserCompany_Actions::COMP_TYPE_BYO,
            'c' => Myshipserv_UserCompany_Actions::COMP_TYPE_CON
		);

		try
		{
			$userArr = $this->_helper->companyUser->fetchUsersPenEmlAsAppr($uaTypeMap[$params['type']], $params['id']);
		}
		catch (Myshipserv_Exception_PermissionViolation $e)
		{
			//$this->_helper->redirector('companies', 'profile', null, array());
			//echo "B";
			exit;
		}
		catch (Exception $e )
		{
			echo $e->getMessage();
			exit;
		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}

		$this->_helper->companyUser->fetchUsers(
				self::companyTypeToDb($this->_getParam('type')),
				$this->_getParam('id'),
				$pendingUsers,
				$approvedUsers
		);

		$supplier = Shipserv_Supplier::fetch($params['id']);
		$unpublishedCompany = false;
		if ($supplier->tnid == "") {
			//if the suppilier unpublished, ship the check
			$supplier = Shipserv_Supplier::fetch($params['id'], "", true);
			$unpublishedCompany = true;
		}

		$this->view->pendingUsers      = $pendingUsers;
		$this->view->supplier	       = $supplier;
		$this->view->myCompanies       = $this->_helper->companies->getMyCompanies($this->_getUser(), true);
		$this->view->user              = $this->_getUser();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->companyDetail     = $this->getCompanyDetail();
		$this->view->companies         = $this->_helper->companies->getMyCompanies($this->_getUser(), true);
		$this->view->unpublishedCompany  = $unpublishedCompany;

	}

	
	public function companyEnquiryAction()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();

		$config = $this->config;

		$this->redirectToDefaultPageByCompanyType('v');

		if ($params['id'] == "") {
			$this->redirect("/profile/company-enquiry/type/v/id/" . $sessionActiveCompany->id, array('code' => 301));
		}
		$this->view->config = $config;
		if ($params['id'] != "") {
			try
			{
				$now = new DateTime();
				$lastYear = new DateTime();

				$now->setDate(date('Y'), date('m'), date('d'));
				$lastYear->setDate(date('Y')-1, date('m'), date('d'));

				$period = array('start' => $lastYear, 'end' => $now );

				if ($this->_getParam('type') != "" && $this->_getParam('id') != "") {
					$this->view->companyDetail     = $this->getCompanyDetail();
				}

				if ($user = $this->_getUser()) {
					$this->view->user = $user;
				}

				$this->view->myCompanies = $this->_helper->companies->getMyCompanies($user, true);

				$page = ( !isset( $params['page']) ) ? 1: $params['page'];
				$totalPerPage = ( !isset( $params['total']) ) ? 25: $params['total'];
				$supplier = Shipserv_Supplier::fetch($params['id'], "", true);
				$this->view->companies = $this->_helper->companies->getMyCompanies($this->_getUser(), true);

				$this->view->params = $params;
				$this->view->supplier = $supplier;
				$this->view->statistic = $supplier->getEnquiriesStatistic( $period );

				$this->view->enquiries = Shipserv_Enquiry::getInstanceByTnid($params['id'], $page-1, $totalPerPage, $totalFound, $period);
				$this->view->user = $this->_getUser();
				$this->view->profileMenuHelper = $this->_helper->profileMenu;
				$this->view->totalFound = $totalFound;
				$this->view->currentPage = $page;
				$this->view->pageSize = $totalPerPage;
				$this->view->period = $period;

				$this->view->tnid = $params['id'];

				$this->_getUser()->logActivity(Shipserv_User_Activity::ENQUIRY_BROWSER_VIEW, 'SUPPLIER_BRANCH', $params['id']);
			}
			catch (Myshipserv_Exception_NotLoggedIn $e)
			{
				$this->redirectToLogin();
				exit;
			}

		} else {
			try
			{
				$this->view->companyDetail = array();

				if ( $user = $this->_getUser() )
				{
					$this->view->user = $user;
				}
				$this->view->myCompanies = $this->_helper->companies->getMyCompanies($user, true);

				$page = ( !isset( $params['page']) ) ? 1: $params['page'];
				$totalPerPage = ( !isset( $params['total']) ) ? 25: $params['total'];
				$supplier = Shipserv_Supplier::fetch($params['id']);

				$this->view->params	= $params;

				$this->view->enquiries = Shipserv_Enquiry::getInstanceByTnid($params['id'], $page-1, $totalPerPage, $totalFound, $period);
				$this->view->user = $this->_getUser();
				$this->view->profileMenuHelper = $this->_helper->profileMenu;

				$this->view->tnid = $params['id'];
			}
			catch (Myshipserv_Exception_NotLoggedIn $e)
			{
				$this->redirectToLogin();
				exit;
			}
		}
		$this->_helper->companyUser->fetchUsers(
				self::companyTypeToDb($this->_getParam('type')),
				$this->_getParam('id'),
				$pendingUsers,
				$approvedUsers
		);

		$this->view->pendingUsers      = $pendingUsers;

	}

	public function companyEnquiryDetailAction()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		$this->redirectToDefaultPageByCompanyType('v');

		try
		{
			$now = new DateTime();
			$lastYear = new DateTime();

			$now->setDate(date('Y'), date('m'), date('d'));
			$lastYear->setDate(date('Y')-1, date('m'), date('d'));

			// store it to a data structure to be passed around
			$period = array('start' => $lastYear, 'end' => $now );

			$supplier = Shipserv_Supplier::fetch($params['id']);

			if ($this->_getParam('type') != "" && $this->_getParam('id') != "") {
				$this->view->companyDetail     = $this->getCompanyDetail();
			}

			$page = ( !isset( $params['page']) ) ? 0: $params['page'];
			$totalPerPage = ( !isset( $params['total']) ) ? 20: $params['total'];
			$enquiry = Shipserv_Enquiry::getInstanceByIdAndTnid($params['enquiryId'],$params['id']);

			if ($this->_getUser()->isShipservUser() === false) {
				$enquiry->setViewedDate();
			}

			$this->view->params	= $params;
			$this->view->supplier = $supplier;
			$this->view->statistic = $supplier->getEnquiriesStatistic( $period );
			$this->view->enquiries = $enquiry;
			$this->view->user = $this->_getUser();
			$this->view->profileMenuHelper = $this->_helper->profileMenu;
			$this->view->totalFound = $totalFound;
			$this->view->currentPage = $page;
			$this->view->pageSize = $totalPerPage;

			$this->view->tnid = $params['tnid'];

			$this->_helper->companyUser->fetchUsers(
					self::companyTypeToDb($this->_getParam('type')),
					$this->_getParam('id'),
					$pendingUsers,
					$approvedUsers
			);

			$this->view->pendingUsers      = $pendingUsers;

		}
		catch (Myshipserv_Exception_NotLoggedIn $e)
		{
			$this->redirectToLogin();
			exit;
		}
	}

/**
	 * AJAX interface to show all activities performed by user
	 * This currently available only for shipmate
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public function userActivityAction()
	{
		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;

		// throw error if userId is not specified
		if ($params['userId'] == "") {
			throw new Myshipserv_Exception_MessagedException("Invalid userId");
		}

		$user = Shipserv_User::getInstanceById($params['userId']);

		// resetting the layout for AJAX
		//$this->_helper->layout->setLayout('empty');

		// get data for 30 days ago
		$days = ( $params['period'] != "" ) ? $params['period'] * 30 : 6 * 30;
		$period = Myshipserv_Period::dayAgo($days);
        $this->view->profileMenuHelper = $this->_helper->profileMenu;

		$this->view->period = $period->toArray();
		$this->view->report = $user->getActivity( $period )->toArray();

		if ($this->_getParam('type') != "" && $this->_getParam('id') != "") {
			$this->view->companyDetail     = $this->getCompanyDetail();
		}


		$this->_helper->companyUser->fetchUsers(
			self::companyTypeToDb($this->_getParam('type')),
			$this->_getParam('id'),
			$pendingUsers,
			$approvedUsers
		);

		$this->view->pendingUsers      = $pendingUsers;

		$this->view->params	= $params;
		$this->view->enquiries = $enquiry;
		$this->view->user = $this->_getUser();
		$this->view->subject = $user;
	}


	public function saveGroupAction()
	{
		// tell pages to deliver fresh content for next response
		$requestCacheControl = Myshipserv_Helper_Session::getNamespaceSafely('Myshipserv_DeliverFreshContent');
		$requestCacheControl->noCache = true;

		// delay for 1 sec
		sleep(1);

		//$params = $this->_getAllParams();
		// DE3383 - sanitise parameters
		$params = $this->params;
		$config = $this->config;
		$db = $this->db;
		$user = Shipserv_User::isLoggedIn();

		if ($user !== false) {
			if ($user->isAdminOfSupplier($config['shipserv']['company']['tnid']) === false) {
				throw new Exception("You have to be an administrator of ShipServ company to perform this.");
			}
		}

		$user = Shipserv_User::getInstanceById($params['userId']);

		$sql = "DELETE FROM pages_user_group WHERE pug_psu_id=:userId";
		$db->query($sql, array('userId' => $params['userId']));

		$sql = "INSERT INTO pages_user_group (PUG_PSU_ID, PUG_PUG_ID) VALUES(:userId, :groupId)";
		$db->query($sql, array('userId' => $params['userId'], 'groupId' => $params['groupId']));



		$user->purgeMemcache();

		$response = new Myshipserv_Response("200", "OK");
		$this->_helper->json((array) $response->toArray() );

	}

    public function manageGroupAction()
	{
        $userForManagingGroup = array('jgo@shipserv.com', 'smay@shipserv.com');
        $this->getRequest()->setParam('forceTab','shipmate');

		$this->abortIfNotShipMate();
		if (in_array($this->user->email, $userForManagingGroup) === false && $this->user->canPerform('PSG_ACCESS_MANAGE_GROUP') === false) {
			throw new Myshipserv_Exception_MessagedException("You are not allowed to access this page as specified within your group: " . $this->user->getGroupName(), 401);
		}

		$db = $this->db;

		if ($this->params['psgId'] != "") {
			$sql = "
				UPDATE pages_shipserv_group
				SET
					PSG_NAME = :psgName
					, PSG_DESCRIPTION = :psgDescription
					, PSG_IP_RESTRICTED='".(($this->params['PSG_IP_RESTRICTED'])=="1"?"Y":"N")."'
					, PSG_ACCESS_SIR='".(($this->params['PSG_ACCESS_SIR'])=="1"?"Y":"N")."'
					, PSG_FORWARD_SIR='".(($this->params['PSG_FORWARD_SIR'])=="1"?"Y":"N")."'
					, PSG_ADD_USER='".(($this->params['PSG_ADD_USER'])=="1"?"Y":"N")."'
					, PSG_TURN_TN_INTEGRATION='".(($this->params['PSG_TURN_TN_INTEGRATION'])=="1"?"Y":"N")."'
					, PSG_VIEW_USER_ACTIVITY='".(($this->params['PSG_VIEW_USER_ACTIVITY'])=="1"?"Y":"N")."'
					, PSG_COMPANY_SWITCHER='".(($this->params['PSG_COMPANY_SWITCHER'])=="1"?"Y":"N")."'
					, PSG_VIEW_BILLING_REPORT='".(($this->params['PSG_VIEW_BILLING_REPORT'])=="1"?"Y":"N")."'
					, PSG_ACCESS_INV_TXN_TOOLS='".(($this->params['PSG_ACCESS_INV_TXN_TOOLS'])=="1"?"Y":"N")."'
					, PSG_ACCESS_MATCH='".(($this->params['PSG_ACCESS_MATCH'])=="1"?"Y":"N")."'
					, PSG_ACCESS_MATCH_INBOX='".(($this->params['PSG_ACCESS_MATCH_INBOX'])=="1"?"Y":"N")."'
					, PSG_ACCESS_SALESFORCE='".(($this->params['PSG_ACCESS_SALESFORCE'])=="1"?"Y":"N")."'
					, PSG_ACCESS_DASHBOARD='".(($this->params['PSG_ACCESS_DASHBOARD'])=="1"?"Y":"N")."'
					, PSG_ACCESS_GMV='".(($this->params['PSG_ACCESS_GMV'])=="1"?"Y":"N")."'
					, PSG_ACCESS_MANAGE_GROUP='".(($this->params['PSG_ACCESS_MANAGE_GROUP'])=="1"?"Y":"N")."'
					, PSG_ACCESS_MANAGE_PUBLISHER='".(($this->params['PSG_ACCESS_MANAGE_PUBLISHER'])=="1"?"Y":"N")."'
					, PSG_GMV_SUPPLIER='".(($this->params['PSG_GMV_SUPPLIER'])=="1"?"Y":"N")."'
					, PSG_CONVERSION_SUPPLIER='".(($this->params['PSG_CONVERSION_SUPPLIER'])=="1"?"Y":"N")."'
					, PSG_GMV_BUYER='".(($this->params['PSG_GMV_BUYER'])=="1"?"Y":"N")."'
					, PSG_MARKET_SIZING='".(($this->params['PSG_MARKET_SIZING'])=="1"?"Y":"N")."'
					, PSG_ADMIN_GATEWAY='".(($this->params['PSG_ADMIN_GATEWAY'])=="1"?"Y":"N")."'
					, PSG_PAGES_ADMIN='".(($this->params['PSG_PAGES_ADMIN'])=="1"?"Y":"N")."'
					, PSG_PAGES_ADMIN_ADV='".(($this->params['PSG_PAGES_ADMIN_ADV'])=="1"?"Y":"N")."'
					, PSG_ACCESS_SCORECARD='".(($this->params['PSG_ACCESS_SCORECARD'])=="1"?"Y":"N")."'
					, PSG_ACCESS_SCORECARD_ADV='".(($this->params['PSG_ACCESS_SCORECARD_ADV'])=="1"?"Y":"N")."'
					, PSG_ACCESS_TXNMON='".(($this->params['PSG_ACCESS_TXNMON'])=="1"?"Y":"N")."'
					, PSG_ACCESS_WEBREPORTER='".(($this->params['PSG_ACCESS_WEBREPORTER'])=="1"?"Y":"N")."'
					, PSG_ACCESS_PO_PACK='".(($this->params['PSG_ACCESS_PO_PACK'])=="1"?"Y":"N")."'
					, PSG_ACCESS_SPR_KPI='".(($this->params['PSG_ACCESS_SPR_KPI'])=="1"?"Y":"N")."'		
			        , PSG_MANAGE_USER_AND_COMPANY='".(($this->params['PSG_MANAGE_USER_AND_COMPANY'])=="1"?"Y":"N")."'					        
					, PSG_LOGIN_AS='".(($this->params['PSG_LOGIN_AS'])=="1"?"Y":"N")."'
					, PSG_ACCESS_BC_ADMIN='".(($this->params['PSG_ACCESS_BC_ADMIN']) == "1" ? "Y" : "N")."'
					, PSG_DATE_UPDATED=SYSDATE
				WHERE
					PSG_ID = :psgId
			";

			$db->query(
			        $sql, 
			        array('psgId' => $this->params['psgId'], 'psgName' => $this->params['psgName'], 'psgDescription' => $this->params['psgDescription'])
			);
			$this->redirect("/profile/manage-group");
			
		} else if ($this->params['did'] != "") {
			$sql = "DELETE FROM pages_shipserv_group WHERE psg_id = :did";
			$db->query($sql, array('did' => $this->params['did']));
			$this->redirect("/profile/manage-group");

		} else if ($this->params['newPsgName'] != "") {
			$sql = "
				INSERT INTO pages_shipserv_group
					(PSG_ID, PSG_NAME, PSG_DESCRIPTION, PSG_IP_RESTRICTED, PSG_ACCESS_SIR, PSG_FORWARD_SIR,
					PSG_ADD_USER, PSG_TURN_TN_INTEGRATION, PSG_VIEW_USER_ACTIVITY, PSG_COMPANY_SWITCHER,
					PSG_VIEW_BILLING_REPORT, PSG_ACCESS_INV_TXN_TOOLS, PSG_ACCESS_MATCH, PSG_ACCESS_MATCH_INBOX,
					PSG_ACCESS_SALESFORCE, PSG_ACCESS_DASHBOARD, PSG_ACCESS_GMV, PSG_ACCESS_MANAGE_GROUP,
					PSG_ACCESS_MANAGE_PUBLISHER, PSG_GMV_SUPPLIER, PSG_CONVERSION_SUPPLIER, PSG_GMV_BUYER, PSG_MARKET_SIZING,
					PSG_ADMIN_GATEWAY, PSG_PAGES_ADMIN, PSG_PAGES_ADMIN_ADV, PSG_ACCESS_SCORECARD, PSG_ACCESS_SCORECARD_ADV,
					PSG_ACCESS_TXNMON, PSG_ACCESS_WEBREPORTER, PSG_ACCESS_PO_PACK, PSG_ACCESS_SPR_KPI, PSG_MANAGE_USER_AND_COMPANY, PSG_LOGIN_AS, PSG_ACCESS_BC_ADMIN,
			        PSG_DATE_CREATED, PSG_DATE_UPDATED)

					VALUES(
						(SELECT MAX(psg_id)+1 FROM pages_shipserv_group)
						, :newPsgName
						, :psgDescription
						, '".(($this->params['PSG_IP_RESTRICTED'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_SIR'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_FORWARD_SIR'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ADD_USER'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_TURN_TN_INTEGRATION'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_VIEW_USER_ACTIVITY'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_COMPANY_SWITCHER'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_VIEW_BILLING_REPORT'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_INV_TXN_TOOLS'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_MATCH'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_MATCH_INBOX'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_SALESFORCE'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_DASHBOARD'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_GMV'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_MANAGE_GROUP'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_MANAGE_PUBLISHER'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_GMV_SUPPLIER'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_CONVERSION_SUPPLIER'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_GMV_BUYER'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_MARKET_SIZING'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ADMIN_GATEWAY'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_PAGES_ADMIN'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_PAGES_ADMIN_ADV'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_SCORECARD'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_SCORECARD_ADV'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_TXNMON'])=="1"?"Y":"N")."'
						, '".(($this->params['PSG_ACCESS_WEBREPORTER'])=="1"?"Y":"N")."'
    			        , '".(($this->params['PSG_ACCESS_PO_PACK'])=="1"?"Y":"N")."'
 						, '".(($this->params['PSG_ACCESS_SPR_KPI'])=="1"?"Y":"N")."'
                        , '".(($this->params['PSG_MANAGE_USER_AND_COMPANY'])=="1"?"Y":"N")."'
		                , '".(($this->params['PSG_LOGIN_AS'])=="1"?"Y":"N")."'
		                , '".(($this->params['PSG_ACCESS_BC_ADMIN'])=="1"?"Y":"N")."'
						, SYSDATE
						, SYSDATE
					)
			";
			$db->query(
			        $sql,
			        array('newPsgName' => $this->params['newPsgName'], 'psgDescription' => $this->params['psgDescription'])
	        );
			$this->redirect("/profile/manage-group");
		}

		$sql = "
			SELECT
				g.*
				, (SELECT COUNT(*) FROM pages_user_group WHERE pug_pug_id=psg_id) total_members
				, (
	                SELECT
	                  RTRIM(xmlagg(xmlelement(c, ( SUBSTR(TRIM(REGEXP_REPLACE(TRIM(psu_firstname || ' ' || psu_lastname), '[[:cntrl:]]', '__')), 0, 20)) || '|||') ).extract ('//text()'), ',') \"li_concat\"
	                FROM
	                  pages_user_group JOIN pages_user ON (psu_id=pug_psu_id)
					WHERE pug_pug_id=psg_id

				) members
			FROM
				pages_shipserv_group g
			ORDER BY
				g.psg_id ASC
		";
		$this->view->groups = $db->fetchAll($sql);
	}

	
	public function companyApprovedSuppliersAction()
	{
		$user = $this->_getUser();
		//check if can access this page


		$tnUser = $user->getTradenetUser();
		if (!$tnUser->canAccessApprovedSupplier()) {
			throw new Exception("Error Processing Request, please check the user rights", 401);

		}
		if ($user) {
			$user->logActivity(Shipserv_User_Activity::APPROVED_SUPPLIERS_CLICK, 'PAGES_USER', $user->userId, $user->email);
		}
		
		//S20380 Restrict access to non full members
		if (!Shipserv_Buyer_Membership::errorOnBasicMembership()) {
			$this->_forward('membership-level-access-error', 'error',  '', array('menu' => 'account'));
		};

		$this->view->user              = $this->_getUser();
		$this->view->notificationForm  = $nForm;
		$this->view->ownerDetails      = $this->_makeOwnerDetails();
		$this->view->profileMenuHelper = $this->_helper->profileMenu;
		$this->view->companyDetail     = $this->getCompanyDetail();

	}

	
	public function companyApprovedSuppliersAddAction()
	{

		$this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $supplierIds = $this->_getSupplierIdsFromFile();
        $approvedList = $this->_getApprovedlist();
        $approvedList->add($supplierIds);

        print "{'result':true}";

	}

	
	public function companyApprovedSuppliersDelAction()
	{

		$supplierBrachCode= (int)$this->_getParam('supplierBranchCode');
		$this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

		$approvedList = $this->_getBlacklist();
		$approvedList->remove($supplierBrachCode, 'whitelist');

        print "{'result':true}";

	}

	
	 /**
     * Returns supplier IDs parsed from the uploaded file
     * Supported format - comma/new line separated/mixed IDs, non-integers ignored
     *
     * @return  array
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _getSupplierIdsFromFile() 
    {
        $filename = $data = $_FILES['supplierIdFile']['tmp_name'];

        if (($fh = fopen($filename, 'r')) === false) {
            throw new Myshipserv_Exception_MessagedException("No supplier IDs file specified for the list");
        }

        $supplierIds = array();
        while (($row = fgetcsv($fh)) !== false) {
            foreach ($row as $cell) {
                $id = trim($cell);

                if (filter_var($id, FILTER_VALIDATE_INT) and ($id > 0)) {
                    $supplierIds[] = $id;

                    if (count($supplierIds) === Shipserv_Buyer_ApprovedList::LIST_LENGTH_LIMIT) {
                        return $supplierIds;
                    }
                }
           }
        }

        return $supplierIds;
    }

    
    /**
     * Helper function returning approvedlist manager object for the current buyer / user
     *
     * @return  Shipserv_Buyer_ApprovedList
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _getApprovedlist() 
    {
        $buyerOrg = $this->getUserBuyerOrg();
        $user = Shipserv_User::isLoggedIn();
        if (!$user) {
        	Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        }

        $approvedList = new Shipserv_Buyer_ApprovedList($buyerOrg, $user);
        return $approvedList;
    }

    
    /**
     * Helper function returning approvedlist manager object for the current buyer / user
     *
     * @return  Shipserv_Buyer_ApprovedList
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _getBlacklist() 
    {
        $buyerOrg = $this->getUserBuyerOrg();
        $user = Shipserv_User::isLoggedIn();
        if (!$user) {
            Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        }

        $approvedList = new Shipserv_Buyer_SupplierList($buyerOrg, $user);
        return $approvedList;
    }

    
    public function changeUserNameAction()
    {
    	$params = $this->params;
    	if (array_key_exists('id', $params) && array_key_exists('fistName', $params) && array_key_exists('lastName', $params) && array_key_exists('hash', $params)) {
    		if (md5('sid-'.$params['id']) === $params['hash']) {
    			$db = $this->db;
    			$sql = "
					UPDATE 
					  pages_user
					SET 
					   psu_firstname= :firstName
					  ,psu_lastname= :lastName
					WHERE
					  psu_id = :psuID
				 ";
				$params = array(
						'psuID' => (int)$params['id'],
						'firstName' => $params['fistName'],
						'lastName' => $params['lastName'],
					);
		
				$db->query($sql, $params);
    			$this->_helper->json((array) array('ok') );
    		} 
    	}
    	throw new Myshipserv_Exception_MessagedException("Invalid request", 500);
    	
    }

    /**
    * Target customers, main view
    */
	public function targetCustomersAction()
	{

        $user = Shipserv_User::isLoggedIn();
        if (!$user) {
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        }

        if (!$user->canAccessActivePromotion()) {
        	throw new Myshipserv_Exception_MessagedException("You are not authorised to use this service", 401);
        }
        
        $this->canTargetNewBuyers();

		$params = $this->params;
		$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		$this->redirectToDefaultPageByCompanyType('v');

		$this->_helper->companyUser->fetchUsers(
				self::companyTypeToDb($this->_getParam('type')),
				$this->_getParam('id'),
				$pendingUsers,
				$approvedUsers
		);

		$supplier = Shipserv_Supplier::fetch($params['id']);
		$unpublishedCompany = false;
		if ($supplier->tnid == "") {
			//if the suppilier unpublished, ship the check
			$supplier = Shipserv_Supplier::fetch($params['id'], "", true);
			$unpublishedCompany = true;
		}

		$user->logActivity(Shipserv_User_Activity::ACTIVE_PROMOTION_REPORT_VIEWS, 'SUPPLIER_BRANCH', (int)$this->_getParam('id'));
		
		$this->view->pendingUsers         = $pendingUsers;
		$this->view->myCompanies 	     = $this->_helper->companies->getMyCompanies($this->_getUser(), true);
		$this->view->user                 = $this->_getUser();
		$this->view->profileMenuHelper    = $this->_helper->profileMenu;
		$this->view->companyDetail        = $this->getCompanyDetail();
		$this->view->ownerDetails      	 = $this->_makeOwnerDetails();
		$this->view->companies         	 = $this->_helper->companies->getMyCompanies($this->_getUser(), true);
		$this->view->unpublishedCompany   = $unpublishedCompany;
		$this->view->params = $this->params;

	}

	
	/**
	* Return JSON files for target customers
	*/					
	public function targetCustomersRequestAction()
	{
		set_time_limit(0);
    	ini_set("memory_limit", "-1");

		$user = Shipserv_User::isLoggedIn();

        if (!$user) {
            Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        }
		
        $this->canTargetNewBuyers();

        $params = $this->params;
        //Add case, swith for calling the correct response

        if (!(array_key_exists('type', $params))) {
        	throw new Myshipserv_Exception_MessagedException("Parameter 'type' is missing", 500);
        }

		$response = new Shipserv_Profile_Targetcustomers_Buyers( $params );
		$this->_helper->json((array) $response->getResponseArray() );

	}

    /**
     * Action to get the list of keywrods by a segment, used in Active Promotion Settings page
     * outputs the json represenation of the list
     * sample call /profile/segment-keyword-list?id=21
     * @return unkonwn
     */
	public function segmentKeywordListAction()
	{
	    $segmentId = (int)$this->getRequest()->getParam('id', 0);
	    $segments = new 	Shipserv_Match_Buyer_Segment();
	    $this->_helper->json((array)$segments->getAllKeywordsBySegment($segmentId));
	}
	/**
	* Landing page for Active Promotion decision email, Handle redirects if user already logged in with TradeNet, or not logged in
	*/
	public function activePromoLandingAction()
	{
		$config = Zend_Registry::get('config');
		//$protocol = $config->shipserv->application->protocol .'://'; //Requested to use HTTPS only, get rid of application.ini settings
		$protocol = 'https://';
        $this->initialiseUserFromCAS();
		$user = Shipserv_User::isLoggedIn();
        if (!$user) {
        	if (!$this->userTradenet) {
				$helper = new Myshipserv_View_Helper_Cas;
				$urlForCASLogin = $helper->getCasRestLogin();
				$urlForCASLogin .= '&service='.urlencode($protocol . $_SERVER['HTTP_HOST'] . '/user/cas?redirect='.urlencode($protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']));
				$this->getHelper('redirector')->setCode(302);
				$this->redirect($urlForCASLogin);
			} else {
 				// If the user is logged in as a TradeNet user, avoid forever loop DE6602
				
				// Logout from CAS 
		       	$url = $config->shipserv->services->sso->logout->url . '?service=' . urlencode($protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);

				$casRest = Myshipserv_CAS_CasRest::getInstance();
				$casRest->logoutFromCas();

				// Redirect again to logiut 
				$this->getHelper('redirector')->setCode(302);
				$this->redirect($url);
			}
        }
        			
        if (!$user->canAccessActivePromotion()) {
        	//If the selected company has no access to manage AP, then show an error page
        	$this->_forward('active-promotion-landing-noaccess', 'profile', null, $this->_getAllParams());
        	return;
        }
        
        try {
    		$this->view->data = Shipserv_Profile_Targetcustomers_Landing::getInstance($this->params)->getData()->getResult();
    	} catch (Exception $e) {
    		$this->_forward('active-promotion-landing-noaccess', 'profile', null, $this->_getAllParams());
    		return;
    	}

        //Check if decision has already made 
        $supplierRateBuyer = new Shipserv_Supplier_Rate_Buyer((int)$this->params['supplierid']);
       	$relationShipArray = $supplierRateBuyer->getExplicitRelationshipWithBuyer((int)$this->params['buyerid']);
       	
       	if ($relationShipArray) {

       		$params = $this->_getAllParams();

       		if ($supplierRateBuyer->checkRelationship($relationShipArray, Shipserv_Supplier_Rate_Buyer::REL_STATUS_TARGETED, true)) {
	        	$params['validFrom'] = $relationShipArray['BSR_VALID_FROM'];
	        	$params['validTill'] = $relationShipArray['BSR_VALID_TILL'];
       			$this->_forward('active-promotion-decision-locked', 'profile', null, $params);
       			return;
       		} else  if ($supplierRateBuyer->checkRelationship($relationShipArray, Shipserv_Supplier_Rate_Buyer::REL_STATUS_TARGETED)) {
       			$params['relationship'] = Shipserv_Supplier_Rate_Buyer::REL_STATUS_TARGETED;
	        	$params['validFrom'] = $relationShipArray['BSR_VALID_FROM'];
	        	$params['targetingUser'] = $relationShipArray['_expanded']['user'];
	        	$this->_forward('active-promotion-decision-made', 'profile', null, $params);
	        	return;
	        } else if ($supplierRateBuyer->checkRelationship($relationShipArray, Shipserv_Supplier_Rate_Buyer::REL_STATUS_EXCLUDED)) {
	        	$params['relationship'] = Shipserv_Supplier_Rate_Buyer::REL_STATUS_EXCLUDED;
	        	$params['validFrom'] = $relationShipArray['BSR_VALID_FROM'];
	        	$params['targetingUser'] = $relationShipArray['_expanded']['user'];
	        	$this->_forward('active-promotion-decision-made', 'profile', null, $params);
	        	return;
	        }; 

    	}

	}

	protected function canTargetNewBuyers()
	{
		if ($this->getActiveCompany()->type == 'v') {
			$this->supplierBuyerRateObj = new Shipserv_Supplier_Rate_Buyer($this->getActiveCompany()->id);
			return $this->supplierBuyerRateObj->getRateObj()->canTargetNewBuyers();
		} else {
			throw new Myshipserv_Exception_MessagedException("You need to be logged in as a Supplier to access buyer services", 401);
		}

	}

	public function activePromotionDecisionMadeAction()
	{
		$user = Shipserv_User::isLoggedIn();
        if (!$user) {
        	throw new Myshipserv_Exception_MessagedException("You are not authorised to use this service", 401);
        }

        $this->view->params = $this->_getAllParams();

	}

	public function activePromotionDecisionLockedAction()
	{
		$user = Shipserv_User::isLoggedIn();
        if (!$user) {
        	throw new Myshipserv_Exception_MessagedException("You are not authorised to use this service", 401);
        }

        $this->view->params = $this->_getAllParams();

	}

	public function activePromotionLandingNoaccessAction()
	{
		$user = Shipserv_User::isLoggedIn();
        if (!$user) {
        	throw new Myshipserv_Exception_MessagedException("You are not authorised to use this service", 401);
        }
	}

}

class ProfileController_Exception extends Exception
{

}
