<?php
/**
 * ShipServ User Class
 * 
 * @package shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_User extends Shipserv_Object
{
	static protected $casIsInitialised = false;
	public ?string $status = null;
	
    const TABLE_NAME = 'PAGES_USER';
    const
        COL_ID         = 'PSU_ID',
        COL_NAME_FIRST = 'PSU_FIRSTNAME',
        COL_NAME_LAST  = 'PSU_LASTNAME',
        COL_ALIAS      = 'PSU_ALIAS',
		COL_EMAIL	   = 'PSU_EMAIL',

        COL_ANONYMITY  = 'PSU_ANONYMITY_FLAG',
        COL_ALERT_STS  = 'PSU_ALERT_STATUS',

        COL_DECISION_MAKER = 'PSU_IS_DECISION_MAKER',

        COL_COMPANY             = 'PSU_COMPANY',
        COL_COMPANY_TYPE_ID     = 'PSU_PCT_ID',
        COL_COMPANY_TYPE_OTHER  = 'PSU_OTHER_COMP_TYPE',

        COL_JOB_FUNCTION_ID     = 'PSU_PJF_ID',
        COL_JOB_FUNCTION_OTHER  = 'PSU_OTHER_JOB_FUNCTION',

        COL_UPDATED_BY   = 'PSU_LAST_UPDATE_BY',
        COL_UPDATED_DATE = 'PSU_LAST_UPDATE_DATE',

        COL_COMPANY_ADDRESS  = 'PSU_COMPANY_ADDRESS',
        COL_COMPANY_POSTCODE = 'PSU_COMPANY_ZIP_CODE',
        COL_COMPANY_COUNTRY  = 'PSU_COMPANY_COUNTRY_CODE',
        COL_COMPANY_PHONE    = 'PSU_COMPANY_PHONE',
        COL_COMPANY_WEBSITE  = 'PSU_COMPANY_WEBSITE',
        COL_COMPANY_SPENDING = 'PSU_COMPANY_SPENDING',
        COL_COMPANY_VESSEL_NO = 'PSU_COMPANY_NO_VESSEL'
    ;

    const
        ANON_LEVEL_ALL          = 'SHOW_ALL',
        ANON_LEVEL_COMPANY_JOB  = 'SHOW_JOB_AND_COMPANY_ONLY',
        ANON_LEVEL_COMPANY      = 'SHOW_COMPANY_ONLY',
        ANON_LEVEL_NONE         = 'HIDE_ALL'
    ;

	const ALERTS_IMMEDIATELY = Shipserv_Oracle_User::ALERTS_IMMEDIATELY;
	const ALERTS_WEEKLY = Shipserv_Oracle_User::ALERTS_WEEKLY;
	const ALERTS_NEVER = Shipserv_Oracle_User::ALERTS_NEVER;
	
	const EMAIL_CONFIRMED_CONFIRMED = 'Y';
	const EMAIL_CONFIRMED_UNCONFIRMED = 'N';
	const EMAIL_CONFIRMED_PENDING_LOGIN = 'P';

	// Class version, used to recognise & manage old instances retrieved
	// from session. If you update this class in a non-backward compatible way,
	// increment this integer in order to force re-load.
	const CLS_VERSION = 2;
	
	// Instance's class version. New instances take current class versions,
	// older instances retrieved from session may differ.
	private $clsVersion = self::CLS_VERSION;

    // added by Yuriy Akopov on 2014-07-10
    const
        BRANCH_TYPE_BUYER    = 'BYB',
        BRANCH_TYPE_SUPPLIER = 'SPB',

        BRANCHES_ALL     = 'branches',
        BRANCHES_DEFAULT = 'default'
    ;

    const 
    	BRANCH_FILTER_MATCH = 'PUC_MATCH',
    	BRANCH_FILTER_BUY = 'PUC_BUY',
    	BRANCH_FILTER_TXNMON = 'PUC_TXNMON',
    	BRANCH_FILTER_WEBREPORTER = 'PUC_WEBREPORTER',
    	BRANCH_FILTER_AUTOREMINDER = 'PUC_AUTOREMINDER',
    	BRANCH_FILTER_ANY = ''
    	;
    
	/*
	 * Return values for SPR KP report access check
	 * We need this workaround of multiple values, becouse we also have to display
	 * The value in buyer usage dashboard, but there the logged in user previledges are meaningless
	 * avoiding code duplication
	*/
    const
    	ACCESS_SPR_KP_NO = 0,
    	ACCESS_SPR_KP_YES = 1,			// @todo: apparently never in use?
    	ACCESS_SPR_KP_USERCHECK = 2
    	;

	/**
	 * The Oracle database ID of the user
	 *
	 * @access protected
	 * @var int
	 */
	protected $userId;
	
	/**
	 * The username of the user
	 *
	 * @access protected
	 * @var string
	 */
	protected $username;
	
	/**
	 * The first name of the user
	 *
	 * @access protected
	 * @var string
	 */
	protected $firstName;
	
	/**
	 * The last name of the user
	 *
	 * @access protected
	 * @var string
	 */
	protected $lastName;
	
	/**
	 * The email of the user
	 *
	 * @access protected
	 * @var string
	 */
	protected $email;
	
	/**
	 * The name of the user's company
	 *
	 * @access protected
	 * @var string
	 */
	protected $companyName;
	
	/**
	 * Pages Company Type ID
	 *
	 * @var int
	 */
	protected $pctId;
	
	/**
	 * Alternatice company type
	 *
	 * @var string
	 */
	protected $otherCompanyType;
	
	/**
	 * Pages Job Function ID
	 *
	 * @var int
	 */
	protected $pjfId;
	
	/**
	 * Alternative job role
	 *
	 * @var string
	 */
	protected $otherJobFunction;
	
	/**
	 * Access to SVR
	 *
	 * @var string
	 */
	protected $svrAccess;
		
	/**
	 * How frequently the user wishes to receive review requests
	 *
	 * @access protected
	 * @var string
	 */
	protected $alertStatus;
	
	/**
	 * The user's alias
	 *
	 * @access protected
	 * @var string
	 */
	protected $alias;
	
	/**
	 * Is user's e-mail confirmed?
	 * 'Y' | 'N'
	 */
	protected $emailConfirmed;
	
	/**
	 * @bool
	 */
	private $isSuper = false;
	
	public $company;
	
	private $userDbRow;
	
	/**
	 * Constructor for a user
	 *
	 * @access public
	 * @param array $row from PAGES_USER db
	 */
	protected function __construct ($userRow)
	{	
		parent::__construct();
		$this->userId      = $userRow['PSU_ID'];
		$this->username    = $userRow['USR_NAME'];
		$this->firstName   = $userRow['PSU_FIRSTNAME'];
		$this->lastName    = $userRow['PSU_LASTNAME'];
		$this->email       = $userRow['PSU_EMAIL'];

        // default is immediate notification
        $this->alertStatus      = (strlen($userRow[self::COL_ALERT_STS]) === 0) ? self::ALERTS_IMMEDIATELY : $userRow[self::COL_ALERT_STS];
        // to protect user privacy, default is full anonymity mode
        $this->anonymityFlag 	= (strlen($userRow[self::COL_ANONYMITY]) === 0) ? self::ANON_LEVEL_NONE : $userRow[self::COL_ANONYMITY];

		$this->alias 			= trim($userRow['PSU_ALIAS']);
		$this->emailConfirmed 	= (string) $userRow['PSU_EMAIL_CONFIRMED'];
		$this->companyName 		= $userRow['PSU_COMPANY'];
		$this->pctId 			= $userRow['PSU_PCT_ID'];
		$this->otherCompanyType = $userRow['PSU_OTHER_COMP_TYPE'];
		$this->pjfId 			= $userRow['PSU_PJF_ID'];
		$this->otherJobFunction = $userRow['PSU_OTHER_JOB_FUNCTION'];
		$this->svrAccess 		= $userRow['PSU_SVR_ACCESS'];
		$this->isDecisionMaker 	= $userRow['PSU_IS_DECISION_MAKER'];

		$this->company['name']  = $userRow['PSU_COMPANY'];
		$this->company['type'] = $userRow['PSU_PCT_ID'];
		$this->company['address'] = $userRow['PSU_COMPANY_ADDRESS'];
		$this->company['zipCode'] = $userRow['PSU_COMPANY_ZIP_CODE'];
		$this->company['countryCode'] = $userRow['PSU_COMPANY_COUNTRY_CODE'];
		$this->company['phone'] = $userRow['PSU_COMPANY_PHONE'];
		$this->company['website'] = $userRow['PSU_COMPANY_WEBSITE'];
		$this->company['spending'] = $userRow['PSU_COMPANY_SPENDING'];
		$this->company['noOfVessel'] = $userRow['PSU_COMPANY_NO_VESSEL'];
		
		$vesselDao = new Shipserv_Oracle_Vessel;
		
		$this->userRow = $userRow;
	}
	
	public function getDbRow()
	{
		return $this->userRow;
	}
	
	public function getArrayForZendFormValidation()
	{
		$user = $this;

		$address = array_filter(explode("\n", $user->company['address']), function(string $address) {
			return trim($address) !== '';
		});

		return array(
			'email'   => $user->email,
			'name'    => $user->firstName,
			'surname' => $user->lastName,
			'alias'   => $user->alias,
			'company' => $user->companyName,
			'companyType' 		=> $user->pctId,
			'otherCompanyType' 	=> $user->otherCompanyType,
			'jobFunction' 		=> $user->pjfId,
			'otherJobFunction' 	=> $user->otherJobFunction,
			'isDecisionMaker' 	=> $user->isDecisionMaker,
			'cAddress1' => $address[0],
			'cAddress2' => $address[1],
			'cAddress3' => $address[2],
			'cZipcode' 	=> $user->company['zipCode'],
			'cCountryCode' 	=> $user->company['countryCode'],
			'cPhone' 		=> $user->company['phone'],
			'cWebsite' 		=> $user->company['website'],
			'cSpending' 	=> $user->company['spending'],
			'cNoOfVessel' 	=> $user->company['noOfVessel'],
		);
	}
	
	public static function isValidEmailConfirmed ($val)
	{
		$okVals = array(self::EMAIL_CONFIRMED_CONFIRMED, self::EMAIL_CONFIRMED_UNCONFIRMED, self::EMAIL_CONFIRMED_PENDING_LOGIN);
		return in_array($val, $okVals);
	}
	
	public static function getInstanceByTnUserId($userId)
	{
		$userDb = new Shipserv_Oracle_User(self::getDb());
		// Create updated user object
		return $userDb->fetchUserById($userId, "");
	}
	
	public static function getInstanceById($userId, $type = "P", $skipCheck = false)
	{
		$userDb = new Shipserv_Oracle_User(self::getDb());
		// Create updated user object
		return $userDb->fetchUserById($userId, $type, $skipCheck);
				
	}

	public static function getInstanceByEmail($email)
	{
		$userDb = new Shipserv_Oracle_User(self::getDb());
		
		// Create updated user object
		return $row = $userDb->fetchUserByEmail($email);
				
	}
	
	/**
	 * Instantiate from DB row resulting from join of USER & PAGES_USER tables.
	 * 
	 * @param array $userRow Associative array of columns
	 * @return Shipserv_User
	 */
	public static function fromDb (array $userRow)
	{
		return new self($userRow);
	}
	
	/**
	 * Returns a specific parameter for the current object instantiation
	 * 
	 * @access public
	 * @param string $param The name of the parameter to fetch
	 * @return mixed The value of the parameter
	 */
	public function __get($param)
	{
	    //Lazy load this attr to avoid launching a useless query
	    if ($param === 'company' && is_array($this->company) && (!isset($this->company['vesselTypes']) || ! $this->company['vesselTypes'])) {
	        $this->company['vesselTypes'] = $vesselDao->getSelectedVesselTypeByUser($this->userId);
	    }

		return $this->{$param} ?? null;
	}
	
	public function purgeMemcache()
	{
		$this->memcachePurge(get_class(), 'isShipservUser' , $this->userId . '-isShipServUser');
		$this->purgeCache(md5('userGroupDetail' . $this->userId));  // warning by Yuriy Akopov - that key is purged in its raw form, without decoration (legacy)
	}
	
	public function isShipservUser ()
	{
		$config = parent::getConfig();
		$key = $this->userId . '-isShipServUser';
		$db = $this->getDb();
		
		$isShipservUser = $this->memcacheGet(get_class(), 'isShipservUser', $key);
        $companyId = $config->shipserv->company->tnid;
     	
		if( $isShipservUser !== false )
		{
			return ($isShipservUser == "Y");
		}
		else
		{
			// new implementation
			if( $this->isPartOfSupplier($companyId))
			{
				$this->memcacheSet(get_class(), 'isShipservUser', $key, "Y");
				return true;
			}
			else
			{
				$this->memcacheSet(get_class(), 'isShipservUser', $key, "N");
				return false;
			}
		}
	}
	
	public function updateRights()
	{
		$config = parent::getConfig();

		// update the rights for shipmate if he/she no longer part of shipserv company
		if( $this->isPartOfSupplier($config->shipserv->company->tnid) === false )
		{
			// remove right from pages_user_group table
			$sql = "DELETE FROM pages_user_group WHERE pug_psu_id=:userId";
			$this->getDb()->query($sql, array('userId' => $this->userId));
		}
		
		$this->purgeMemcache();
	}
	
	/**
	 * @return bool
	 */
	public function isEmailConfirmed ()
	{		
		return ($this->emailConfirmed == 'Y');
	}
	
	/**
	 * If TNID wasn't specifid, this function is making sure that this user has access to at lease
	 * a single company. If it's specified, then this function will check if he's belong to that
	 * company
	 * @param unknown_type $tnid
	 */
	public function canAccessSVR( $tnid = null )
	{		
		if ($this->isShipservUser()) return true;

		$accessibleReport = array();
		foreach( $this->fetchCompanies()->getSupplierIds() as $supplierId )
		{
			if( $tnid != null )
			{
				if( $tnid == $supplierId)
				{
					return true;
				}
			}
			else
			{
				$accessibleReport[] = $supplierId;	
			}
		}
		
		if( $tnid != null)
		{
			return ( count( $accessibleReport ) > 0 ) ? true : false;
		}
		else 
		{
			return $accessibleReport;
		}
	}


	/**
	* Check if the user has access for Match Benchmark. If shipmate or application.ini contains the comma separeated TNID shipserv.match.benchmark.tnids 
	*/
	public function canAccessMatchBenchmark()
	{
		if ($this->isShipservUser()) {
			return true;
		} 
		    
		return $this->canAccessFeature(self::BRANCH_FILTER_MATCH);
	}

	/**
	 * Check if only beta customers can see the report and if it is the case
	 * allow only for the specific TNID's
	 * 
	 * @return bool
	 */
	public function canAccessMatchBenchmarkBeta()
	{
			if ($this->isShipservUser()) {
				return true;
			} 

			$config = parent::getConfig();
			$globallyEnabled = (int)$config->shipserv->match->benchmark->enabled;
			
			if ($globallyEnabled === 1) {
				return true;
			}
			
			$allowedTnIds = explode(",",$config->shipserv->match->benchmark->tnids);
			$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
			return in_array($sessionActiveCompany->id, $allowedTnIds);
	}

	/**
	* Check ig the user can access Targeting. Sample application.ini 
	* shipserv.targeting.allow.all = 0
	* shipserv.targeting.allow.tnids = 52323
	*/
	public function canAccessActivePromotion()
	{

		$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		$isAdmin =  $this->isAdminOfSupplier($sessionActiveCompany->id);

		if ($this->isShipservUser() || $isAdmin) {
			if (!$this->isShipservUser()) {
				$config = parent::getConfig();
				if ($config->shipserv->targeting->allow->all != 1) {
					$allowedTnIds = explode(",",$config->shipserv->targeting->allow->tnids);
					if (!(in_array($sessionActiveCompany->id, $allowedTnIds))) {
						return false;
					}
				}
			}

			// reworked by Yuriy Akopov on 2016-04-22, S16390
			$rateObj = new Shipserv_Supplier_Rate((int) $sessionActiveCompany->id);
			if (!$rateObj->canTargetNewBuyers()) {
				return false;   // no one can access
			}

			// either access to AP enabled to everyone ($apAccess === false) or only to ShipMates but the current user is a ShipMate as well
			return true;

		} else {
			return false;
		}
			
	}
	
	/**
	 * Check if the pages user has right to acccess SPR
	 * @return boolean
	 */
	public function canAccessSprKpi()
	{

		if ($this->isShipservUser()) {
			//if user is a shipmate check only group privilege
			if ($this->canPerform('PSG_ACCESS_SPR_KPI') === false) {
				return false;	
			}
			
		} else {

			$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
			if ($sessionActiveCompany->type !== 'b') {
				//The branch is not buyer branch
				return false;
			}
			
			switch (self::getAccessSprKpi($sessionActiveCompany->id)) {
				case self::ACCESS_SPR_KP_NO:
					return false;
					break;
				case self::ACCESS_SPR_KP_USERCHECK:
					return $this->canAccessFeature(Shipserv_User::BRANCH_FILTER_WEBREPORTER);
					break;
				default:
					return true;
					break;
			}
			
		}

		return true;
	}
	
	/**
	 * Return if the buyer branch has access right to SPR reports
	 * concerning if we are not shipmate, and does not concerning
	 * if the logged in user has access right to reports (previously webreporter)
	 * 
	 * @param integer $buyerBranchId
	 * @return integer 0,1,2 (self::ACCESS_SPR_KP_YES | self::ACCESS_SPR_KP_NO | self::ACCESS_SPR_KP_USERCHECK)
	 */
	public static function getAccessSprKpi($buyerBranchId) {
		$config = parent::getConfig();
		
		$buyerBranch = Shipserv_Buyer::getInstanceById($buyerBranchId, true);
		$buyerBranch->normalisationOfCompanyId = $buyerBranch->getNormalisedCompanyByOrgId($buyerBranch->id);
		$buyerBranch->normalisingCompanyIds = $buyerBranch->getNormalingCompaniesByOrgId($buyerBranch->id);

		if ((int)$config->shipserv->spr->allow->all !== 1) {
			if ((int)$buyerBranch->byoAccessKpiSp === 1) {
				return self::ACCESS_SPR_KP_USERCHECK;
			}
			
			$allowedTnIds = explode(",", $config->shipserv->spr->allow->tnids);
			if (!in_array($buyerBranchId, $allowedTnIds)) {
				return self::ACCESS_SPR_KP_NO;
			}
		} else {
		    $allowedTnIds = explode(",", $config->shipserv->spr->exclude->tnids);
		    if (in_array($buyerBranchId, $allowedTnIds)) {
		        return self::ACCESS_SPR_KP_NO;
		    }
			return self::ACCESS_SPR_KP_USERCHECK;
		}
		
		return self::ACCESS_SPR_KP_USERCHECK;
	}
	
	public function canAccessAdNetworkPublisher( $tnid = null )
	{
		$sql = "SELECT COUNT(*) FROM pages_company_publisher WHERE pcp_company_tnid=:tnid";
		return ( $this->getDb()->fetchOne($sql, array('tnid' => $tnid)) > 0 );
	}

	/**
	* Check, if the active company is supplier, and if it is supplier, it must be SmartSupplier or ExpertSupplier
	*/
	public function canAccessKpiTrendReport()
	{
		$company = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		if ($company->type !== 'v') {
			return false;
		} else {
			if ($this->isShipservUser()) return true; 
			$config = parent::getConfig();
			$allowedTnIds = explode(",",$config->shipserv->kpi->trend->report->tnids);
			$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
			return in_array($sessionActiveCompany->id, $allowedTnIds);
		}

		/* This access level is temporary disabled, currently we will let shipmates, and some users to access this page only. */
		/*
			$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
			$tnid = $sessionActiveCompany->id;
			$company = $this->getSelectedCompany($tnid);
			if ($company->type == 'v') {
	        	$supplier = Shipserv_Supplier::fetch($tnid, $this->getDb());
	        return ((int)$supplier->smartSupplier == 1 || (int)$supplier->expertSupplier == 1);
			} else {
				return false;
			}
		*/
	}

	/**
	 * Attempts to register a user
	 * 
	 * @param string $email
	 * @param string $password
	 * @param string $firstName
	 * @param string $lastName
	 * @param string $company
	 * @param string $companyTypeId
	 * @param string $otherCompanyType
	 * @param string $jobFunctionId
	 * @param string $otherJobFunction
	 * @param boolean $marketingUpdated
	 * @return mixed If successful, returns a Shipserv_User object, otherwise FALSE
	 */
	public static function register (
		$email, $password, $firstName, $lastName,
		$company, $companyTypeId, $otherCompanyType,
		$jobFunctionId, $otherJobFunction, $marketingUpdated,
		$rememberMe, $isDecisionMaker, $companyAddress, $companyZipCode,
		$companyCountryCode, $companyPhoneNo, $companyWebsite, $companySpending,
		$vesselCount, $vesselType
	)
	{		
				
		// attempt to register
		$authAdapter = new Shipserv_Adapters_Authentication();
		$result      = $authAdapter->register($email, $password, $firstName,
											  $lastName, $company, $companyTypeId,
											  $otherCompanyType, $jobFunctionId,
											  $otherJobFunction, $marketingUpdated,
											  $companyType, $isDecisionMaker, $companyAddress, $companyZipCode,
											  $companyCountryCode, $companyPhoneNo, $companyWebsite, $companySpending,
											  $vesselCount, $vesselType
											
		);

		// If registration was successful
		if ($result['success'] == true) 
		{
			// Log user in
			$user = self::login($email, $password, $rememberMe);
			if (! $user)
				 throw new Exception("Failed login after registration");

            // added by Yuriy Akopov on 2013-09-13, story S8093
            // users successfully registered via our new form that has Terms and Conditions mandatory checkbox are marked as
            // users who accepted our T&C (as opposed to legacy users)
            $user->confirmTermsAndConditions();

			// Return user
			return $user;
		}
		else
		{
			$errorMessage = $result['messages'][0];
			
			if (stristr($errorMessage, 'duplicate username'))
			{
				$errorMessage = 'Your email address is already taken';
			}
			
			throw new Shipserv_User_Exception_RegisterEmailInUse($errorMessage);
		}
		
		return false;
	}
	
	public static function initialiseCasLogin()
	{
		$config = Zend_Registry::get('config');

		// if CAS authenticated, either get or create a new Zend_Session
		$casRest = Myshipserv_CAS_CasRest::getInstance();

		if ($casRest->casCheckLoggedIn()) {
			// get user from CAS
			$username = $casRest->getUserName();

			// if pages user found - then this is acceptable
			if( strstr($username, '@') !== false )
			{
				self::$casIsInitialised = true;
				$user = Shipserv_User::login($username, null, true, $uFromTn);
			
				Shipserv_User::initialiseUserCompany();				
			}
		}

	}
	
	/**
	 * Checks if the current user is logged in, and returns a user object if they are
	 * 
	 * @access public
	 * @static
	 * @return Shipserv_User|bool
	 */
	public static function isLoggedInOnCas($logType=Myshipserv_CAS_CasRest::LOGIN_ALL)
	{

		$casRest = Myshipserv_CAS_CasRest::getInstance();
		if ($casRest->casCheckLoggedIn($logType)) {
			$username = $casRest->getUserName();
			if( strstr($username, '@') !== false )
			{
	    		$userDb = new Shipserv_Oracle_User(self::getDb());
	    		$newUser = $userDb->fetchUserByEmail($username);
	    		
	    		return $newUser;
			}
		}

		return false;
	}
	
	public static function isLoggedIn ($logType=Myshipserv_CAS_CasRest::LOGIN_ALL)
	{		
		return self::isLoggedInOnCas($logType);
	}

    /**
     * @return Zend_Db_Adapter_Oracle
     */
    public static function getDb ()
	{
		return $GLOBALS["application"]->getBootstrap()->getResource('db');
	}

	/**
	 * Following the worflow described into following documentation, try to log in the user into Cas
	 * https://documentation.uts.nlm.nih.gov/rest/authentication.html
	 * 
	 * @param String $username
	 * @param String $password
	 * @param Bool $rememberMe
	 * @param Bool $cleanActiveCompany this is needed for instance in the 'log in as' action which shipmate can perform. we need to clean current active company in this case
	 * @return Bool  true if everything was fine and we are now logged in, false otherwise 
	 */
	public static function autoLoginViaCas($username, $password, $rememberMe = true, $cleanActiveCompany = false)
	{
	    if ($cleanActiveCompany) {
    		Zend_Session::namespaceUnset('userActiveCompany');
            unset($_COOKIE['lastSelectedCompany']);
            setcookie('lastSelectedCompany', '', time() -  1);
	    }
		$casRest = Myshipserv_CAS_CasRest::getInstance();
		$casRest->resetCaptcha($username);
		return $casRest->casAuthenticate($username, $password, $rememberMe, Myshipserv_CAS_CasRest::LOGIN_PAGES);
	}
		
	/**
	 * Attempts to authenticate a user and returns a populated user object if
	 * successful. Will store the user object in a session if authenticated.
	 * 
	 * @access public
	 * @static
	 * @param string $username The username to authenticate
	 * @param string $password The password with which to authenticate
	 * @param boolean $rememberMe Set to true if the user should remain logged in (time defined in config)
	 * @return Shipserv_User object
	 */	
	public static function loginWithCas($username, $password = null, $rememberMe = false, &$userFromTn = null)
	{
		$userFromTn = null;
		$authAdapter = new Shipserv_Oracle_Authentication(self::getDb(), $username, $password);
		$auth = Zend_Auth::getInstance();
		//$auth->setStorage(new Zend_Auth_Storage_Session($config->shipserv->services->authentication->namespace));
		
		$config  = Zend_Registry::get('config');
		session_set_cookie_params($config->shipserv->services->authentication->cookie->default);
		
		$result = $auth->authenticate($authAdapter);
		// if the authentication was successful
		if ($result->isValid()) {
			// Fetch user
			$user = $result->getIdentity();
			// If super password was used, flag it
			$user->isSuper = self::testSuperPassword($password);
			if($user->isSuper){
				self::logSuperpasswdUse($username);
			}
			// Check for 'pending e-mail confirmation' state & if found, set to 'confirmed'
			self::checkEmlConfPending($user, false);
				
			return $user;
		}
		
		// Login failed: check why
		$accFromTn = $result->accCreatedFromTn();
		if ($accFromTn) {
			$userFromTn = $result->getIdentity();
		}
		
		return false;
		
	}
	
	
	/**
	 * Alias of self::loginWithCas
	 * 
	 * @param string $username The username to authenticate
	 * @param string $password The password with which to authenticate
	 * @param boolean $rememberMe Set to true if the user should remain logged in (time defined in config)
	 * @return Shipserv_User object
	 */
	public static function login($username, $password = null, $rememberMe = false, &$userFromTn = null)
	{
		return self::loginWithCas($username, $password, $rememberMe, $userFromTn);
	}

	
	/**
	 * Check e-mail confirmed status: if in pending, update to confirmed and
	 * update any pending company memberships to active memberships.
	 *
	 * If user is not provided, logged-in user is used from the current session.
	 * If saveToSession is true, the modified user object is saved back to the session.
	 */
	private static function checkEmlConfPending (self $user = null, $saveToSession = true)
	{
		// If user not supplied, fetch logged-in user
		if (!$user) {
			$user = self::isLoggedIn();
			if (!$user) {
				return;
			}
		}
		
		if ($user->emailConfirmed == self::EMAIL_CONFIRMED_PENDING_LOGIN) {			
			// Update DB
			$uDao = new Shipserv_Oracle_User(self::getDb());
			$uDao->confirmEmail($user->userId, true);
			
			// Update user object
			$user->emailConfirmed = self::EMAIL_CONFIRMED_CONFIRMED;
			
			// Update any user-company associations waiting on e-mail confirmation
			$pucDao = new Shipserv_Oracle_PagesUserCompany(self::getDb());
			$pucDao->activatePendingForUser($user->userId);
			
			if ($saveToSession) {
				// Store user in session
				$auth = Zend_Auth::getInstance();
				$auth->setStorage(new Zend_Auth_Storage_Session($config->shipserv->services->authentication->namespace));
				$authStorage = $auth->getStorage();
				$authStorage->write($user);
			}
		}
	}

	/**
	 * Update to confirmed email confirmation status and
	 * update any pending company memberships to active memberships.
	 *
	 */
	public function confirmUserEmail ()
	{

		if ($this->emailConfirmed != self::EMAIL_CONFIRMED_CONFIRMED) {
			// Update DB
			$uDao = new Shipserv_Oracle_User(self::getDb());
			$uDao->confirmEmail($this->userId, true);

			// Update user object
			$this->emailConfirmed = self::EMAIL_CONFIRMED_CONFIRMED;

			// Update any user-company associations waiting on e-mail confirmation
			$pucDao = new Shipserv_Oracle_PagesUserCompany(self::getDb());
			$pucDao->activatePendingForUser($this->userId);

			
		}
	}
	
	private static function testSuperPassword ($password)
	{
		$config = Zend_Registry::get('config');
		return ($config->shipserv->auth->superPassword == $password);
	}
	
	/**
	 * Clears a logged in user
	 * 
	 * @access public
	 */
	public function logout($forgetMe=true)
	{

		Zend_Session::namespaceUnset('userActiveCompany');
		
		Zend_Auth::getInstance()->clearIdentity();

		if ($forgetMe === true) {
			Zend_Session::forgetMe();
		} else {
			Zend_Session::namespaceUnset('phpCAS');
		}
		
        unset($_COOKIE['announcement']); 
        setcookie('announcement', NULL, -1); 

        unset($_COOKIE['SS_ENQ_KEY']);
        setcookie('SS_ENQ_KEY', NULL, -1);
        
        $cookieManager = new Shipserv_Helper_Cookie();
        $cookieManager->clearCookie('enquiryStorage');
        
        
	}
	
	public function isSuper ()
	{
		// Note: cast to bool in case objects from old session don't have the
		// isSuper property (null => false)
		return (bool) $this->isSuper;
	}

	public function isAdminOfSupplier( $tnid = "" )
	{
		
		$ucDom = new Myshipserv_UserCompany_Domain($this->getDb());
		
		$uColl = $ucDom->fetchUsersForCompany('SPB', $tnid);
		foreach ($uColl->getAdminUsers() as $u)
		{
			if( (int) $u->userId == (int)$this->userId )
			{
				return ( $u->getStatus() == Myshipserv_UserCompany_Company::STATUS_ACTIVE
						 && $u->getLevel() == Myshipserv_UserCompany_Company::LEVEL_ADMIN );
				
			}
		}
		return false;
	}
	
	public function canSeeContactViewDetailEasily()
	{
		$collection = $this->fetchCompanies();
		$ids = $collection->getBuyerIds();
		foreach($ids as $id)
		{
			$buyer = Shipserv_Buyer::getInstanceById($id);
			if( $buyer->isIntegrated() || $buyer->isEssmEnabled() )
				return true;	
		}
		return false;
	}

	public function isAdminOf( $tnid )
	{
		if( $this->isAdminOfBuyer($tnid) == true ) return true;
		else
		{
			if( $this->isAdminOfSupplier($tnid) == true ) return true;
			else return false;
		}
	}
	
	public function isAdminOfBuyer( $tnid = "" )
	{
		
		$ucDom = new Myshipserv_UserCompany_Domain($this->getDb());
		
		$uColl = $ucDom->fetchUsersForCompany('BYO', $tnid);
		foreach ($uColl->getAdminUsers() as $u)
		{
			if( $u->userId == $this->userId )
			{
				return ( $u->getStatus() == Myshipserv_UserCompany_Company::STATUS_ACTIVE
						 && $u->getLevel() == Myshipserv_UserCompany_Company::LEVEL_ADMIN );
				
			}
		}
		return false;
	}
	
	public function isPartOfSupplier( $tnid = null )
	{
		if( $tnid === null )
		{
			$collection = $this->fetchCompanies();
			$supplierIds = $collection->getSupplierIds();
			$ucDom = new Myshipserv_UserCompany_Domain($this->getDb());

			foreach ($supplierIds as $tnid)
			{
				$uColl = $ucDom->fetchUsersForCompany('SPB', $tnid);
				
				$supplier = Shipserv_Supplier::getInstanceById($tnid, null, true);
				
				if( $supplier->isPublished() === false )
				{
					return false;
				}
				
				foreach ($uColl->getActiveUsers() as $u)
				{

					if( $u->userId == $this->userId && $u->status != 'DEL' )
					{
						return true;
					}
				}
			}
			return false;
		}
		else
		{
			$ucDom = new Myshipserv_UserCompany_Domain($this->getDb());
			
			$uColl = $ucDom->fetchUsersForCompany('SPB', $tnid);
	
			foreach ($uColl->getActiveUsers() as $u)
			{
				if( $u->userId == $this->userId && $u->status != 'DEL' )
				{
					return true;
					
				}
			}
			return false;
		}		
	}

    /**
     * Modified by Yuriy Akopov on 2013-09-25 to enable check against a particular buyer
     *
     * @param $buyerId
     * @param $withBranches bool
     * * @param $withSubBranches bool
     * @return bool
     */
    public function isPartOfBuyer($buyerId = null, $withBranches = false, $withSubBranches = false)
	{
		$collection = $this->fetchCompanies();
		$buyerIds = $collection->getBuyerIds();
        if (is_null($buyerId)) {
            // legacy function behaviour
            return (count($buyerIds) > 0) ? true: false;
        }

        if (in_array($buyerId, $buyerIds)) {
            return true;
        }

        //TODO: Really needed? This was done by Attila in DE6647 and kept by Claudio assuming it as done for some reasons... but I have some doubts...
        if ($withBranches) {
            $branchIds = $collection->getBuyerBranchIds();
            if (in_array($buyerId, $branchIds)) {
                return true;
            }
        }

        if ($withSubBranches) {
            $subBranchIds = $collection->getBuyerSubBranchIds();
            if (in_array($buyerId, $subBranchIds)) {
                return true;
            }
        }
              
        return false;
	}

	
	public function isAccountManager()
	{
		$sql = "SELECT COUNT(*) TOTAL FROM supplier_branch WHERE spb_acct_mngr_email=:email";
		$rows = $this->getDb()->fetchAll($sql, array(	'email' => $this->email ));
		
		return ($rows[0]['TOTAL']>0)? true : false;
	}
	
	
	/**
     * Will check if user is part of any buyer/supplier 
     */
    public function isPartOfCompany( $companyId = null )
    {
		$memcache = $this::getMemcache();
		$prefix = $this->memcacheConfig->client->keyPrefix ?? '';
		$suffix = $this->memcacheConfig->client->keySuffix ?? '';
    	$key = $prefix . $this->userId . '_isPartOf_' . $companyId . $suffix;
		$cacheTTL = 100;
		
    	if ($memcache)
    	{
    		// Try to retrieve query from Memcache
    		$result = $memcache->get($key);
    		if( $result === 'false' ) $result = false;
    		else if( $result === 'true' ) $result = true;
    		if ($result !== false) return $result;
    	}
    	
    	// Otherwise, fetch from DB & save to cache
        $userCompanies = array_merge($this->fetchCompanies()->getBuyerIds(), $this->fetchCompanies()->getSupplierIds(), $this->fetchCompanies()->getConsortiaIds());
        if( $companyId == null )
        {
        	$result = count( $userCompanies ) > 0 ? true:false;
        }
        else
        {
        	$result = in_array($companyId, $userCompanies);
        }
        
        if ($memcache) $memcache->set($key, ($result)?'true':'false', false, $cacheTTL);
    	return $result;
    }
    
	/**
	 * Retrive list of companies for user
	 *
	 * @return Myshipserv_UserCompany_CompanyCollection
	 */
	public function fetchCompanies($asArray = false)
	{
		if ($asArray) {
		    $myCompanies = array();
			foreach ($this->fetchCompanies()->getSupplierIds() as $r) {
				$supplier = Shipserv_Supplier::fetch($r, "", true);
				$myCompanies[] = array("type" => "v", "name" => $supplier->name, "id" => $supplier->tnid, "value" => "v" . $supplier->tnid);	
			}
			
			foreach ($this->fetchCompanies()->getBuyerIds() as $r) {
				$buyer = Shipserv_Buyer::getInstanceById($r);
				$myCompanies[] = array("type" => "b", "name" => $buyer->name, "id" => $buyer->id, "value" => "b" . $buyer->id);	
			}

			foreach ($this->fetchCompanies()->getBuyerBranchIds() as $r) {
				$buyer = Shipserv_Buyer::getBuyerBranchInstanceById( $r );
				$myCompanies[] = array("type" => "byb", "name" => $buyer->bybName, "id" => $buyer->bybBranchCode, "value" => "byb" . $buyer->bybBranchCode);	
			}

            foreach ($this->fetchCompanies()->getConsortiaIds() as $r) {
                try {
                    $consortia = Shipserv_Consortia::getConsortiaInstanceById($r);
                    $myCompanies[] = array("type" => "c", "name" => $consortia->name, "id" => $consortia->internalRefNo, "value" => "con" . $consortia->internalRefNo);
                } catch (Myshipserv_Exception_MessagedException $e) {
                }
			}

			return $myCompanies;

		} else {
			$ucActions = new Myshipserv_UserCompany_Actions(self::getDb(), $this->userId, $this->email);
			return $ucActions->fetchMyCompanies();
		}
	}
    
    /**
	 * check if any of the companies has Premium Profile
	 *
	 * @return Myshipserv_UserCompany_CompanyCollection
	 */
    
    public function hasPremiumListing()
    {
        $companyIds = $this->fetchCompanies()->getSupplierIds();
        
        foreach ($companyIds as $tnid){
            $supplierObject = Shipserv_Supplier::fetch($tnid);
            if($supplierObject->isPremium()) return true;
        }
        return false;
    }
	
	/**
	 * Fetch list of pending company join requests for user.
	 *
	 * @return array of PAGES_USER_COMPANY_REQUEST table rows as assoc arrays
	 */
	public function fetchCompanyJoinRequests ()
	{
		$ucActions = new Myshipserv_UserCompany_Actions(self::getDb(), $this->userId, $this->email);
		return $ucActions->fetchMyRequestedCompanies()->getPendingRequests();
	}
	
	/**
	 * Update user details: writes to DB and updates user details held in session.
	 *
	 * @param string $firstName
	 * @param string $lastName
	 * @param string $alertStatus
     * @param string  $anonymityFlag
	 * @param string $alias
	 * @param string $companyName
	 * @param int $pctId Pages company type ID
	 * @param string $otherCompanyType
	 * @param int $pjfId Pages job function ID
	 * @param string $otherJobFunction
	 * @return null
	 */
	public static function updateDetails ($firstName, $lastName, $alertStatus, $anonymityFlag, $alias, $companyName, $pctId, $otherCompanyType, $pjfId, $otherJobFunction, $privacySetting = null)
	{
		// Fetch current user object & fail if not present
		$user = self::isLoggedIn();
		if (! $user) throw new Exception("No user logged-in to update");
		
		// Fetch user DAO
		$userDb = new Shipserv_Oracle_User(self::getDb());
		
		// Update DB - should throw exception on failure
		$userDb->updatePagesUser($user->userId, $firstName, $lastName, $alertStatus, $anonymityFlag, $alias, $companyName, $pctId, $otherCompanyType, $pjfId, $otherJobFunction, $privacySetting);
		
		// Create updated user object
		$newUser = $userDb->fetchUserById($user->userId);
		
		// Persist updated user to session
		$config  = Zend_Registry::get('config');
		$auth = Zend_Auth::getInstance();
		$auth->setStorage(new Zend_Auth_Storage_Session($config->shipserv->services->authentication->namespace));
		$auth->getStorage()->write($newUser);
	}
	
	public static function updateDetailsByForm( $form, $user )
	{
		// Fetch current user object & fail if not present
		$user = self::isLoggedIn();
		if (! $user) throw new Exception("No user logged-in to update");
		
		// Fetch user DAO
		$userDb = new Shipserv_Oracle_User(self::getDb());

		// Sanitize form data
		$safeFormData = array_map(function($formItem){
			if (is_string($formItem)) {
				return _htmlspecialchars($formItem);
			}
			return $formItem;
			
		}, $form->getValues());
		// Update DB - should throw exception on failure
		$userDb->updatePagesUserByArray($safeFormData, $user);
		
		// Create updated user object
		$newUser = $userDb->fetchUserById($user->userId);
		
		// Persist updated user to session
		$config  = Zend_Registry::get('config');
		$auth = Zend_Auth::getInstance();
		$auth->setStorage(new Zend_Auth_Storage_Session($config->shipserv->services->authentication->namespace));
		$auth->getStorage()->write($newUser);
		
	}
	
	public static function confirmEmail($boolConfirmed)
	{
		// Fetch current user object & fail if not present
		$user = self::isLoggedIn();
		if (! $user) throw new Exception("No user logged-in to update");
		
		// Fetch user DAO
		$userDb = new Shipserv_Oracle_User(self::getDb());
		
		// Update DB
		$userDb->confirmEmail($user->userId, $boolConfirmed);
		
		// Create updated user object
		$newUser = $userDb->fetchUserById($user->userId);
		
		// Persist updated user to session
		$config  = Zend_Registry::get('config');
		$auth = Zend_Auth::getInstance();
		$auth->setStorage(new Zend_Auth_Storage_Session($config->shipserv->services->authentication->namespace));
		$auth->getStorage()->write($newUser);
	}

	public function getJobFunctionName ()
	{
		if ($this->otherJobFunction) return $this->otherJobFunction;

		$referenceDAO = new Shipserv_Oracle_Reference($this->getDb());
		return $referenceDAO->fetchJobFunctionName($this->pjfId);
	}
	
	public function getDisplayName() {
		return (empty($this->firstName) ? '' : $this->firstName) . (empty($this->lastName) ? '' : " " . $this->lastName);
	}
	
	public function getFirstName() {
		return $this->firstName;
	}
	
	public function getLastName() {
		return $this->lastName;
	}
	
	public function getEmail() {
		return $this->email;
	}
	
	/**
	 * Log super password usage on /tmp foder
	 * @param String $username
	 */
	private static function logSuperpasswdUse($username){
		$filename = "/tmp/pagesSuperUser.log";
		$ipAddress = Myshipserv_Config::getUserIp();
		//Log date & time, IP address, username
		$log = date('Y-m-d H:i:s') . "|" . $ipAddress . "|" . $username ."\n";
		
		$fh = fopen($filename, 'a');
		fwrite($fh, $log);
		fclose($fh);
	}
	
	/**
	 * Log user activity and store it to the database
	 * @param string $activity
	 * @param string $objectName
	 * @param int $objectId
	 * @param string $info
	 */
	public function logActivity( $activity, $objectName = 'PAGES_USER', $objectId = null, $info = "" )
	{
		if( $objectId == null ) $objectId = $this->userId;
		$userDb = new Shipserv_Oracle_User(self::getDb());
		return $userDb->logActivity($this->userId, $activity, $objectName, $objectId, $this->isShipservUser(), $info);
	}
	
	/**
	 * Check if user can edit a listing by specifying the TNID
	 * @param int $tnid
	 */
	public function canEditListing( $tnid )
	{
		return $this->isPartOfSupplier( $tnid );
	}

	/**
	 * Give detail of the user activity (object)
	 * @param Myshipserv_Period $period
	 */
	public function getActivity( $period )
	{
		return new Shipserv_User_Activity( $this->userId, $period );
	}
	
	public function canSendPagesRFQDirectly()
	{
		$userDb = new Shipserv_Oracle_User(self::getDb());
		return $userDb->canSendPagesRFQDirectly($this->userId);
	}

	/**
	* Send the activation email
	* @param integer $orgName
	* @return unknown
	*/
	public function sendJoinCompanyEmailActivation($orgName)
 	{
 	    $nm = new Myshipserv_NotificationManager(self::getDb());
	    return $nm->joinCompanyConfirmEmail($this->userId, $orgName);
 	}
	
	public function sendEmailActivation()
	{
		$nm = new Myshipserv_NotificationManager(self::getDb());
		return $nm->confirmEmail($this->userId);
	}
	
	
	public static function initialiseUserCompany()
	{
	    $user = Shipserv_User::isLoggedIn();
		$view = Zend_Layout::getMvcInstance()->getView();	
		$view->myCompanies = array();
		$view->activeCompany = null;
		if ($user) {
			// initialise the session
			$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
				
			//If there's such cookie, we'll initialise it as default company for this user
			if (isset($_COOKIE['lastSelectedCompany'])) {
				// check if this user is part of the last selected company
				$lastSelectedCompany = json_decode($_COOKIE['lastSelectedCompany']);
			
				// go ahead store the default company to session if user is
				// belong to the company. if not we'll choose the first
				// company -- see below
				if (is_object($lastSelectedCompany) && isset($lastSelectedCompany->tnid) && $user->isPartOfCompany($lastSelectedCompany->tnid)) {
					$activeCompany->type = $lastSelectedCompany->type;
					$activeCompany->id = $lastSelectedCompany->tnid;
					$activeCompany->company = (
				        $lastSelectedCompany->type=='b'? 
				        Shipserv_Buyer::getInstanceById( $lastSelectedCompany->tnid ) 
				        :
				        Shipserv_Supplier::fetch( $lastSelectedCompany->tnid , "", true)
			        ); //Mofified by Attila O, skip check for unpunlished suppliers
                    Myshipserv_Helper_Session::updateReportTradingAccounts();
				}
			}
			
			// fetch companies
			foreach ($user->fetchCompanies(true) as $company) {
			    if ($company['type'] == 'b' || $company['type'] == 'v' || $company['type'] == 'c') {
			        $view->myCompanies[] = $company;
			    } 
			}
			
			// if user is part of companies, then default them to the first one and store it on the cookie
			if (!isset($activeCompany->id) && is_array($view->myCompanies) && isset($view->myCompanies[0]) && isset($view->myCompanies[0]['id'])) {
				// store the first company to the session
				$activeCompany->type = $view->myCompanies[0]['type'];
				$activeCompany->id = $view->myCompanies[0]['id'];
				$activeCompany->company = ($view->myCompanies[0]['type']=='b'? Shipserv_Buyer::getInstanceById($view->myCompanies[0]['id']) : Shipserv_Supplier::fetch($view->myCompanies[0]['id'], "", true));
                Myshipserv_Helper_Session::updateReportTradingAccounts();
				// store this to cookie
				setcookie('lastSelectedCompany', json_encode(array('tnid' => $activeCompany->id, 'type' => $activeCompany->type)), time()+60*60*24*30, '/');
			}

			$view->activeCompany = $activeCompany;
		}
	}
	
	
	public static function getAllGroups()
	{
		$sql = "SELECT * FROM pages_shipserv_group ORDER BY psg_name ASC";
		foreach(self::getDb()->fetchAll($sql) as $row)
		{
			$res[$row['PSG_ID']] = $row;
		}
		return $res;
	}
	
	public function getGroupId()
	{
		$sql = "SELECT PUG_PUG_ID FROM pages_user_group WHERE PUG_PSU_ID=:userId";
		return self::getDb()->fetchOne($sql, array('userId' => $this->userId));
	}
	
	/**
	 * Function to check if user can perform certain type of action
	 * @param unknown $action
	 * @return boolean
	 */
	public function canPerform($action)
	{
		$rights = $this->getGroupDetail();
        
        if(isset($rights[0]) && is_array($rights[0])){
        	$rights = $rights[0];
        }
		
		if( isset($rights['PSG_IP_RESTRICTED']) && $rights['PSG_IP_RESTRICTED'] == "Y" )
		{
			
			if (!$this->isIpInRange(Myshipserv_Config::getUserIp(), Myshipserv_Config::getSuperIps()))
			{
				$passedIPCheck = false;
			}
			else
			{
				$passedIPCheck = true;
			}
		}
		else
		{
			$passedIPCheck = true;
		}
		
		return (($rights[$action])=="Y" && $passedIPCheck == true);
	}
	public function getGroupName()
	{
		$data = $this->getGroupDetail();
		return $data['PSG_NAME'];
	}
	public function getGroupDetail()
	{
		$sql = "SELECT pages_shipserv_group.* FROM pages_user_group, pages_shipserv_group WHERE PSG_ID=PUG_PUG_ID AND PUG_PSU_ID=:userId";
		$data = Shipserv_Helper_Database::registryFetchRow(__CLASS__ . '_' . __FUNCTION__, $sql, array('userId' => $this->userId));
		//$data = $this->fetchCachedQuery($sql, array('userId' => $this->userId), md5('userGroupDetail' . $this->userId), 3000);

		return $data;
	}


	protected function fetchCachedQuery ($sql, $sqlData, $key, $cacheTTL = self::MEMCACHE_TTL, $database = 'sservdba', $debug = false)
    {
		// Note: $memcache could be null
		$memcache = $this::getMemcache();
									       
	 	if ($memcache)
	   	{
           	// Try to retrieve query from Memcache
           	$result = $memcache->get($key);
	     	if ($result !== false) return $result;
		}
		
		// Otherwise, fetch from DB & save to cache
		$result = $this->getDb()->fetchAll($sql, $sqlData);
        if ($memcache) $memcache->set($key, $result, false, $cacheTTL);
		return $result;
	}

	/**
	 * Get the company that user has selected on the top right (that's stored in a session)
	 * @param unknown $tnid
	 * @throws Myshipserv_Exception_MessagedException
	 * @return unknown
	 */
	public static function getSelectedCompany($tnid)
	{
		
		$activeCompany  = new stdClass();
		
		$params['tnid'] = $tnid;
		$db = parent::getDb();
		
		// as $params['tnid'] will be a|v51606 this regex will separate them
		preg_match("/[0-9]{3,}/i", $params['tnid'], $matches);
		
		$companyId = $matches[0];
		$companyType = $params['tnid'][0];
		
		// if company type isn't specified, then check the length of the TNID
		if( !in_array($companyType,array('b','v')) )
		{
			if( $companyId < 20000 )
			{
				$companyType = "b";
			}
			else
			{
				$companyType = "v";
			}
		}
		
		// if buyer
		if($companyType === "b")
		{		
			try
			{
				// if Buyer entered correct buyer org id
				$buyer = Shipserv_Buyer::getInstanceById( $companyId );
			}
			catch( Exception $e )
			{
				// if not found, check if it's being normalised to something else
				if( strstr($e->getMessage(), 'No row found') !== false )
				{
					// check normalisation
					$orgId = Shipserv_Buyer::getByoOrgCodeByTnid($companyId);
					$normalisedOrgId = Shipserv_Buyer::getNormalisedCompanyByOrgId($orgId);
		
					if( $normalisedOrgId != "" )
					{
						$buyer = Shipserv_Buyer::getInstanceById( $normalisedOrgId );
					}
				}
				throw new Myshipserv_Exception_MessagedException("Please check your TNID! System cannot find buyer with TNID: " . $companyId . ".");
			}
		
				
			// if found
			if( !empty($buyer->name) )
			{
				$activeCompany->type="b";
				$activeCompany->id = $buyer->id;
				$activeCompany->company = $buyer;
				$activeCompany->company->tnid = $buyer->id;
				$activeCompany->name = $buyer->name;
                Myshipserv_Helper_Session::updateReportTradingAccounts();
				//setcookie("lastSelectedCompany", json_encode(array("tnid" => $activeCompany->id, "type" => 'b')), time()+60*60*24*30, "/");
			}
			else
			{
				throw new Myshipserv_Exception_MessagedException("Please check your TNID! System cannot find buyer with TNID: " . $companyId . ".");
			}
		
			/*
			if( $companyId != $buyer->id )
			{
				$this->addSuccessMessage("Your buyer TNID: " . $companyId . " is translated to: " . $buyer->id . " (buyer organisation code).");
				$url = str_replace($companyId, $buyer->id, $url);
				$this->redirect("/user/info?r=" . $url);
			}
			*/
		}
		
		// if supplier
		else if($companyType === "v")
		{
		
			$supplier = Shipserv_Supplier::fetch($companyId);
				
			// making sure that supplier isn't normalised to NULL
			$sql = "SELECT PSN_NORM_SPB_BRANCH_CODE FROM PAGES_SPB_NORM WHERE PSN_SPB_BRANCH_CODE=:tnid";
			$r = $db->fetchAll($sql, array('tnid' => $companyId));
			$isNormalisedToNull = (isset($r[0]) && $r[0]['PSN_NORM_SPB_BRANCH_CODE'] == null);
		
			// throw error if not found
			if( $supplier->tnid == null || $isNormalisedToNull )
			{
				throw new Myshipserv_Exception_MessagedException("Please check your TNID! System cannot find supplier with TNID: " . $companyId);
			}
			else
			{
				$activeCompany->type="v";
				$activeCompany->id = $supplier->tnid;
				$activeCompany->company = $supplier;
				$activeCompany->name = $supplier->name;
                Myshipserv_Helper_Session::updateReportTradingAccounts();
			}
		}
		
		return $activeCompany;
	}
	
	/**
	 * Returning the userid
	 * 
	 * @return int
	 */
	public function getUserCode()
	{
		$db = parent::getDb();
		$sql = "SELECT usr_user_code FROM users WHERE usr_name=:email";
		$r = $db->fetchAll($sql, array('email' => $this->email));
		return $r[0]['USR_USER_CODE'];
	}
	
	/**
	 * Returns true if user has completed further detailed information 
	 * as required by the new registration/sign in process
	 * 
	 * @return bool
	 */
	public function completeDetailedInformation()
	{
		$this->logActivity(
			Shipserv_User_Activity::DETAILED_INFORMATION_COMPLETED,
			'PAGES_USER',
			$this->userId
		);
	}

	public function hasCompletedDetailedInformation()
	{
		$data = (Shipserv_User_Activity::getUserCompletedDetailedProfileDate($this) === false )?false:true;
		return $data;
	}
	
	public function updateDetailedInformation( $data )
	{
		$data['userId'] = $this->userId;
		$vesselType = $data['vesselType'];
		unset($data['vesselType']);
		
		$sql = "
			UPDATE
				PAGES_USER 
			SET
				PSU_IS_DECISION_MAKER = :isDecisionMaker,
				PSU_COMPANY_ADDRESS = :cAddress,
			  	PSU_COMPANY_ZIP_CODE = :cZipcode,
			  	PSU_COMPANY_COUNTRY_CODE = :cCountryCode,
			  	PSU_COMPANY_PHONE = :cPhone,
			  	PSU_COMPANY_WEBSITE = :cWebsite,
			  	PSU_COMPANY_SPENDING = :cSpending,
			  	PSU_COMPANY_NO_VESSEL = :cNoOfVessel,
				PSU_COMPANY = :companyName,
				PSU_PJF_ID = :jobType
				
			WHERE 
				PSU_ID = :userId
		";

		$stmt = $this->getDb()->query($sql, $data);
	}

    /**
     * Returns true if there is a record a user has accepted site terms and conditions
     *
     * @author  Yuriy Akopov
     * @date    2013-09-10
     * @story   S8093
     *
     * @return  bool
     */
    public function areTermsAndConditionsConfirmed() {
        if (Shipserv_User_Activity::getUserTermsConfirmationDate($this) === false) {
            return false;
        }

        return true;
    }

    /**
     * Leaves a record in activity log that use has accepted site terms and conditions
     *
     * @author  Yuriy Akopov
     * @date    2013-09-10
     * @story   S8093
     */
    public function confirmTermsAndConditions() {
        $this->logActivity(
            Shipserv_User_Activity::TERM_AND_CONDITIONS_CONFIRMED,
            null,
            null
        );
    }
    
    public static function getActiveUserBySpbBranchCode( $tnid, $pages = false )
    {
    	static $data;
    	if( $pages == false )
    	{
    		$db = parent::getDb();
    		$sql = "SELECT usr_user_code,usr_md5_code FROM supplier_branch_user JOIN users ON (sbu_usr_user_code=usr_user_code AND sbu_sts='ACT') WHERE sbu_spb_branch_code=:tnid";
    		if( $data[$tnid] == null )
    		{
    			$data[$tnid] = $db->fetchAll($sql, array('tnid' => $tnid));
    		}
    		return $data[$tnid];
    	}
    }
    
    
    public static function getActiveUserByBybBranchCode( $tnid, $pages = false )
    {
    	static $data;
    	if( $pages == false )
    	{
    		$db = parent::getDb();
    		$sql = "SELECT usr_user_code,usr_md5_code FROM buyer_branch_user JOIN users ON (bbu_usr_user_code=usr_user_code AND bbu_sts='ACT') WHERE bbu_byb_branch_code=:tnid";
    		if( $data[$tnid] == null )
    		{
    			$data[$tnid] = $db->fetchAll($sql, array('tnid' => $tnid));
    		}
    		return $data[$tnid];
    	}
    }


	/**
	 * Reworked by Yuriy Akopov to fit DE6832 requirements
	 * This request was altered, and refactored again by Attila O BUY-676
	 * on 2017-08-25
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-07-27
	 * @story   DE6832
	 *
	 * @return bool
	 */
    public function canAccessPriceBenchmark()
    {
    	$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
	    $activeCompanyId = $sessionActiveCompany->id;
	    
		//If active compamy is not buyer, no access at all
		if ($sessionActiveCompany->type !== 'b') {
	    	return false;
	    }

    	if ($this->isShipservUser()) {
    		// ShipMates are allowed to use Price Benchmark as long as they are switched into any buyer organisation
    		return true;
	    }

	    // if you are not a ShipMate, then you have to be an admin
    	$allowedBuyerIds = Myshipserv_Config::getPriceBenchmarkAllowedBuyerOrgIds();
	    if (is_null($allowedBuyerIds)) {
	    	// restriction by buyer org ID is disabled, check if reporting is enabled, if yes allow access
	    	return $this->canAccessFeature(Shipserv_User::BRANCH_FILTER_WEBREPORTER);
	    }

    	if (in_array($activeCompanyId, $allowedBuyerIds)) {
    		// ID of the active buyer org is allowed directly
		    return true;
	    }

    	return false;
    }

    public static function getOptionListForAnnualBudget()
    {
    	$data[] = array("id" => 1, "name" => 'Less than USD 1,000');
    	$data[] = array("id" => 2, "name" => 'Between USD 1,000 and 100,000');
    	$data[] = array("id" => 3, "name" => 'Between USD 100,000 and 500,000');
    	$data[] = array("id" => 4, "name" => 'Between USD 1,000,000 and 5,000,000');
    	$data[] = array("id" => 5, "name" => 'More than USD 5,000,000');
    	 
    	return $data;
    }
    
    
    public function getTradenetUser()
    {
    	$tnUser = new Shipserv_Oracle_User_Tradenet();
    	$u = $tnUser->fetchUserByName($this->username);
    	$u->isPagesUser = true;
    	return $u;
    } 
    
    
    /**
     * Historically contexts for Pages is buyer company, but now also need to associate users with branches, not only with organisation
     * This method returns branches the user is associated with, if any, grouped by organisations
     *
     * @author  Yuriy Akopov
     * @date    2014-07-10
     * @story   S10526
     *
     * @param   string  $companyType
     * @param   string  $companyFilter
     *
     * @return  array
     * @throws  Exception
     */
    public function getAssociatedBranches($companyType = self::BRANCH_TYPE_BUYER, $companyFilter = self::BRANCH_FILTER_MATCH) {
        $select = new Zend_Db_Select($this->getDb());
        $select
            ->from(
                array('puc' => 'pages_user_company'),
                array(
                    'ID'  => 'puc.puc_company_id',
                    'DEF' => 'puc.puc_is_default'
                )
            )
            ->where('puc.puc_psu_id = ?', $this->userId)
            ->where('puc.puc_status = ?', 'ACT')
        ;

        switch ($companyType) {
            case self::BRANCH_TYPE_BUYER:
                $select
                    ->join(
                        array('byb' => 'buyer_branch'),
                        'byb.byb_branch_code = puc.puc_company_id',
                        
                        array(
                            'ORG_ID' => 'byb.byb_byo_org_code'
                        )
                    )
	                // DE6330 by Yuriy Akopov on 2016-02-24 - excluding inactive branches
	                ->where('byb.byb_sts <> ?', 'INA')
                    // added by Elvir on 19-Feb-2015; Data structure on the table's been changed
                    //->where('puc.puc_match =?', '1') //TODO change it to buy
                    
                ;
                break;

            case self::BRANCH_TYPE_SUPPLIER:
                $select
                    ->join(
                        array('spb' => 'supplier_branch'),
                        'spb.spb_branch_code = puc.puc_company_id',
                        array(
                            'ORG_ID' => 'spb.spb_sup_org_code'
                        )
                    )
                ;
                break;

            default:
                throw new Exception("Unknown user company type supplied: " . $companyType);
            }

            if (in_array($companyFilter, array(self::BRANCH_FILTER_MATCH, self::BRANCH_FILTER_BUY, self::BRANCH_FILTER_WEBREPORTER, self::BRANCH_FILTER_TXNMON, self::BRANCH_FILTER_AUTOREMINDER))) {
                $select->where("puc.$companyFilter = ?", '1');
            }

        $select->where('puc.puc_company_type = ?', $companyType);

        $rows = $this->getDb()->fetchAll($select);
        $ids = array();
        foreach ($rows as $row) {
            if (!array_key_exists($row['ORG_ID'], $ids)) {
                $ids[$row['ORG_ID']] = array(
                    self::BRANCHES_ALL     => array(),
                    self::BRANCHES_DEFAULT => array()
                );
            }

            $ids[$row['ORG_ID']][self::BRANCHES_ALL][] = $row['ID'];

            if ($row['DEF']) {
                $ids[$row['ORG_ID']][self::BRANCHES_DEFAULT][] = $row['ID'];
            }
        }

        // bring default branch IDs on top
        foreach ($ids as $orgId => $branches) {
            $ids[$orgId][self::BRANCHES_ALL] = array_merge(
                $ids[$orgId][self::BRANCHES_DEFAULT],
                $ids[$orgId][self::BRANCHES_ALL]
            );
        }

        return $ids;
    }

    
    public function getAssociatedBranchCount($companyType = self::BRANCH_TYPE_BUYER, $companyFilter= self::BRANCH_FILTER_MATCH)
    {

    	$select = new Zend_Db_Select($this->getDb());
        $select
            ->from(
                array('puc' => 'pages_user_company'),
                array(
                    'PCS'  => 'count(puc.puc_company_id)'
                )
            )
            ->where('puc.puc_psu_id = ?', $this->userId)
            ->where('puc.puc_status = ?', 'ACT')
            ->where('puc.puc_level =?', 'USR')
        ;

        switch ($companyType) {
            case self::BRANCH_TYPE_BUYER:
                $select
                    ->join(
                        array('byb' => 'buyer_branch'),
                        'byb.byb_branch_code = puc.puc_company_id',
                        
                        array(
                            
                        )
                    );
                break;

            case self::BRANCH_TYPE_SUPPLIER:
                $select
                    ->join(
                        array('spb' => 'supplier_branch'),
                        'spb.spb_branch_code = puc.puc_company_id',
                        array(
                            'ORG_ID' => 'spb.spb_sup_org_code'
                        )
                    )
                ;
                break;

            default:
                throw new Exception("Unknown user company type supplied: " . $companyType);
            }
            //TODO check, if the lines below have to be used
           /*
            switch ($companyFilter) {
                  	case self::BRANCH_FILTER_MATCH:
                  		$select->where('puc.puc_match =?', '1');
                   		break;
					case self::BRANCH_FILTER_BUY:
                   		$select->where('puc.puc_buy =?', '1');
                   		break;
                   	
                   	default:
                    	break;
                    }
           */

        $select->where('puc.puc_company_type = ?', $companyType);

        $rows = $this->getDb()->fetchOne($select);
        return (int)$rows;
    }

    
	public function hasAgreedLatestAgreement()
	{
		$user = $this->getUser();
		if ($user) {

			$termAndCondition = Shipserv_Agreement_TermAndCondition::getLatest();
			$termAndCondition->setUser($this->getUser());

			$privacyPolicy = Shipserv_Agreement_PrivacyPolicy::getLatest();
			$privacyPolicy->setUser($this->getUser());

			// add memcache which last 1 day
			if( $termAndCondition->userHasAgreed() == false || $privacyPolicy->userHasAgreed() == false )
			{
				return false;
			}

			return true;
		} else {
			return false;
		}
	}
	
	public function getAgreementDateForLatestLegalAgreement()
	{
		$sql = "SELECT TO_CHAR(PUA_DATE_CREATED, 'dd-Mon-YYYY hh:ii') FROM pages_User_agreement WHERE pua_psu_id=:userId AND rownum=1 ORDER BY pua_date_created DESC";
		return $this->getDb()->fetchOne($sql, array('userId' => $this->userId));
	}

	/**
	 * Can access match / txnmon / buyer /webreporter / autoreminder ? 
	 * 
	 * @param self::BRANCH_FILTER_MATCH|self::BRANCH_FILTER_BUY|self::BRANCH_FILTER_TXNMON|self::BRANCH_FILTER_WEBREPORTER|self::BRANCH_FILTER_AUTOREMINDER $feature
	 * @return Bool
	 */
	public function canAccessFeature($feature)
	{
	    //Need to be logged in
	    if (!$this->userId) {
	        return false;
	    }

		$isShipservUser = $this->isShipservUser();

		if ($feature === self::BRANCH_FILTER_BUY && !$isShipservUser) {
			return false;
		}

	    //Shipserv users always allowed
	    if ($isShipservUser) {
	    	//For shipmtes, we may also check some additional rules
	    	switch ($feature) {
	    		case self::BRANCH_FILTER_TXNMON:
					return $this->canPerform('PSG_ACCESS_TXNMON'); 
	    			break;
	    		
				case self::BRANCH_FILTER_WEBREPORTER:
					return $this->canPerform('PSG_ACCESS_WEBREPORTER');
	    			break;
	    		default:
	    			return true;
	    			break;
	    	}
	    }	    
	    //Need to have an OrgId selected
	    $orgId = Myshipserv_Helper_Session::getActiveCompanyId();
	    if (!$orgId) {
	        return false;
	    }
	    //Can access only existing features!
	    if (!in_array($feature, array(self::BRANCH_FILTER_MATCH, self::BRANCH_FILTER_BUY, self::BRANCH_FILTER_TXNMON, self::BRANCH_FILTER_WEBREPORTER, self::BRANCH_FILTER_AUTOREMINDER))) {
	        return false;
	    }

		//Check if the trading account settings ever saved, if not, by default the user can see the tab (lazy population)
		$sql = "
		SELECT
		  COUNT(*) CNT
		FROM
		  pages_user_company JOIN buyer_branch ON byb_branch_code=puc_company_id
		WHERE
		  puc_psu_id = :userId
          AND byb_byo_org_code = :orgId
		  AND puc_company_type = 'BYB'
  		";
		
		$sqlParams = array('userId' => $this->userId, 'orgId' => $orgId);
		$regKey = __CLASS__ . '_' . __FUNCTION__ .'_' . $sql . '_' .serialize($sqlParams);
		$res = Shipserv_Helper_Database::registryFetchOne($regKey, $sql, $sqlParams);

		if ((int)$res === 0) {
  		    //Check that there are really some branches for this $orgId. The query above is not enough for doing it, while the following query goes more into details (check norm and hierarchy too)
  		    if (in_array($feature, array(self::BRANCH_FILTER_AUTOREMINDER, self::BRANCH_FILTER_BUY))) {
  		        //Count active active
  		        if (count(Shipserv_Buyer::getInstanceById($orgId)->getBranchesTnid(true, true))) {
  		            return true;
  		        } else {
  		            return false;    
  		        }
  		    } else {
  		        //Count active inactive
  		        if (count(Shipserv_Buyer::getInstanceById($orgId)->getBranchesTnid(true, false))) {
  		            return true;
  		        } else {
  		            return false;
  		        }  		        
  		    }
  		}
  		
  		//Cannot do buy and auomreminder operations on INA branches, but can see INA branches in all reports
  		$statusCondition = '';
  		if (in_array($feature, array(self::BRANCH_FILTER_AUTOREMINDER, self::BRANCH_FILTER_BUY))) {
  		    $statusCondition = " AND BYB_STS != 'INA' ";
  		}
  		//If the trading account settings were saved, check rights
		$sql = "
            SELECT
              DISTINCT(puc_company_id || puc_company_type) AS uniqueid, 
              PAGES_USER_COMPANY.*, 
              BYB_NAME, 
              BYB_STS, 
              BYB_BYO_ORG_CODE, 
              CCF_RFQ_DEADLINE_CONTROL 
            FROM
              pages_user_company 
              JOIN buyer_branch ON puc_company_id=byb_branch_code
              LEFT JOIN pages_byo_norm ON byb_byo_org_code = pbn_byo_org_code 
              LEFT JOIN CUSTOMER_CONFIG ON ccf_branch_code=puc_company_id
            WHERE 
              puc_status='ACT'
              AND puc_company_type='BYB' 
              AND puc_psu_id = :userId
              AND $feature = 1
              $statusCondition
            START WITH puc_psu_id = :userId AND (byb_byo_org_code = :orgId OR pbn_norm_byo_org_code = :orgId)
            CONNECT BY NOCYCLE PRIOR byb_branch_code = byb_under_contract		        
        ";
		
        $sqlParams = array('userId' => $this->userId, 'orgId' => $orgId);
        $regKey = __CLASS__ . '_' . __FUNCTION__ .'_' . $sql . '_' .serialize($sqlParams);
        $res = Shipserv_Helper_Database::registryFetchOne($regKey, $sql, $sqlParams);
              
        return ($res > 0);
	}

    /**
     * Return if the user can access Transaction Report (Consortia)
     */
	public function canAccessTransactionReport()
	{
        $sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
        return ($sessionActiveCompany->type === 'c');
	}
}



class Shipserv_User_Exception_RegisterEmailInUse extends Exception { }

class Shipserv_User_Cas extends Myshipserv_Controller_Action_SSO {
	
}
?>
