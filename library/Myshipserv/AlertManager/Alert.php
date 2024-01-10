<?php 
/**
 * Handles alert which will be displayed/used by JS
 * 
 * @author Elvir <eleonard@shipserv.com>
 * @see Myshipserv_AlertManager
 * @see AlertController
 */
class Myshipserv_AlertManager_Alert extends Shipserv_Memcache{
	
	const MEMCACHE_TTL = 120;
	
	/**
	 * Type of alert, see constant below
	 */
	public $type;
	
	/**
	 * Holds information about the alert
	 */
	protected $id;
	protected $title;
	protected $actions;
	protected $user;
	protected $data;
	protected $date;
	protected $priority; 
	protected $db;
	
	/**
	 * Flag to tell front end that this alert is being suppressed/minimised by the user
	 * @var bool
	 */
	protected $isSuppressed = false;
	
	protected $companyId;
	protected $objectId;
	
	/**
	 * Flag to tell that an alert is personal/not group alert
	 */
	protected $isPersonal = false;
	protected $extraKey;
	
	/**
	 * Type of alert
	 */
	const ALERT_PERSONAL_REVIEW_REQUEST = 'personalReviewRequest';
	const ALERT_PERSONAL_CATEGORY_REQUEST = 'personalCategoryRequest';
	const ALERT_PERSONAL_COMPANY_JOIN_REQUEST = 'personalCompanyJoinRequest';
	const ALERT_COMPANY_MEMBERSHIP = 'companyMembership';
	const ALERT_COMPANY_BRAND_AUTH = 'companyBrandAuth';
	const ALERT_COMPANY_USER_JOIN = 'companyUserJoin';
	const ALERT_COMPANY_UNVERIFIED = 'companyUnverified';
	const ALERT_COMPANY_UNREAD_ENQUIRIES = 'companyEnquiries';
	
	static $alertTypes = array(	self::ALERT_COMPANY_BRAND_AUTH,
								self::ALERT_PERSONAL_CATEGORY_REQUEST,
								self::ALERT_COMPANY_MEMBERSHIP,
								self::ALERT_COMPANY_UNVERIFIED,
								self::ALERT_COMPANY_USER_JOIN,
								self::ALERT_PERSONAL_REVIEW_REQUEST,
								self::ALERT_PERSONAL_COMPANY_JOIN_REQUEST,
								self::ALERT_COMPANY_UNREAD_ENQUIRIES );
		
	// optimisation
	static $companyBuyer = array();
	static $companySupplier = array();
	
	static $instance = null;
	
	/**
	 * Singleton to access function from static function
	 * 
	 * @return object this class
	 */
	final public static function getInstance()
	{
		if( null !== self::$instance )
		{
			return self::$instance;
		}
		
		self::$instance = new self;
		return self::$instance;
	}
	
	
	public function get( $var )
	{
		return $this->$var;
	}
	
	public function set( $var, $value )
	{
		$this->$var = $value;
	}
	

	/**
	 * This class can have multiple constructors, just add number of parameters
	 * as the suffix of constructors
	 * Example: self::__construct0(){}
	 * Example: self::__construct1($db){}
	 */
	function __construct( )
	{
		$this->setMemcacheTTL( self::MEMCACHE_TTL );
		
        $a = func_get_args();
        $i = func_num_args();
        if (method_exists($this,$f='__construct'.$i)) {
            call_user_func_array(array($this,$f),$a);
        } 		
	}
	
	function __construct0()
	{
		// do nothing
	}
	
	public function toHtml()
	{
		$output .= "Is this listing still up to date?";
		
		foreach( $this->get("actions") as $action ){
			$output.= "<a href=\"" . $action["url"] . "\">" . $action["title"] . "</a>&nbsp;&nbsp"; 
		}
		
		return $output;
	}
	
	function __construct5( $db, $type, $data, $config, $user )
	{
		$this->db = $db;	
		$this->data = $data;
		$this->type = $type;
		$this->user = $user;
		
			// when a user wants to join a company 
		if( $type == self::ALERT_COMPANY_USER_JOIN )
		{
			$company = $this->getCompanyById( $data["companyType"], $data["companyId"] );

			if( $data["companyType"] == "SPB" )
			{
				$companyId = $company[0]["SPB_BRANCH_CODE"];
				$companyName = $company[0]["SPB_NAME"];
			}
			
			if( $data["companyType"] == "BYO" )
			{
				$companyId = $company[0]["BYO_ORG_CODE"];
				$companyName = $company[0]["BYO_NAME"];
			}
			$user = $this->getUserById( $data["userId"]);
			
			$this->objectId = $data["userId"];
			$this->companyId = $companyId;
			
			$this->date = date("d-m-Y", strtotime( $data["date"])); 
			$this->title = 'Would you like to add ' . $user->email . ' to ' . $companyName;
			
			$this->addAction("Approve", 'https://' . $_SERVER["HTTP_HOST"] . "/profile/approve-user-request/format/json?reqId=" . $data["requestId"], "json", Myshipserv_AlertManager_Action::POSITIVE_ACTION);
			$this->addAction("Reject", 	'https://' . $_SERVER["HTTP_HOST"] . "/profile/reject-user-request/format/json?reqId=" . $data["requestId"], "json", Myshipserv_AlertManager_Action::NEGATIVE_ACTION);
			
		}
		
			// when a user wants to join a company 
		else if( $type == self::ALERT_PERSONAL_COMPANY_JOIN_REQUEST )
		{
			$company = $this->getCompanyById( $data["PUCR_COMPANY_TYPE"], $data["PUCR_COMPANY_ID"] );
			if( $data["PUCR_COMPANY_TYPE"] == "SPB" )
			{
				$companyId = $company[0]["SPB_BRANCH_CODE"];
				$companyName = $company[0]["SPB_NAME"];
			}
			
			if( $data["PUCR_COMPANY_TYPE"] == "BYO" )
			{
				$companyId = $company[0]["BYO_ORG_CODE"];
				$companyName = $company[0]["BYO_NAME"];
			}
			$user = $this->getUserById( $data["PUCR_PSU_ID"]);
			
			$this->objectId = $data["PUCR_PSU_ID"];
			$this->companyId = $companyId;
			
			$this->date = date("d-m-Y", strtotime( $data["PUCR_CREATED_DATE"])); 
			$this->title = 'You have a pending request to join ' . $companyName;

			$this->addAction("Withdraw", 	'https://' . $_SERVER["HTTP_HOST"] . "/profile/withdraw-join-request/format/json?reqId=" . $data["PUCR_ID"], "json", Myshipserv_AlertManager_Action::NEGATIVE_ACTION);
			
			$this->isPersonal = true;
		}		
		
		// when company/supplier requires brand authorisation
		else if( $type == self::ALERT_COMPANY_BRAND_AUTH )
		{
			$company = $this->getCompanyById( "SPB", $data->companyId );
			$brand = $this->getBrandById( $data->brandId );
			
			$this->objectId = $data->brandId;
			$this->companyId = $company[0]["SPB_BRANCH_CODE"];
			
			$this->date = date("d-m-Y", strtotime( $data->dateRequested)); 

			$this->title = 'Would you like to approve ' . trim( $company[0]["SPB_NAME"] ) . " to be your " . $data->getAuthLevelDisplayName() . " of " . $brand["NAME"];
			
			$this->addAction("Authorise", 	'https://' . $_SERVER["HTTP_HOST"] . "/brand-auth/authorise-brand-auth-request/format/json/?companyId=" . $data->companyId ."&brandId=" . $data->brandId ."&authLevels=" . $data->authLevel ."", "json", Myshipserv_AlertManager_Action::POSITIVE_ACTION);
			$this->addAction("Reject", 		'https://' . $_SERVER["HTTP_HOST"] . "/brand-auth/reject-brand-auth-request/format/json/?companyId=" . $data->companyId ."&brandId=" . $data->brandId ."&authLevels=" . $data->authLevel ."", "json", Myshipserv_AlertManager_Action::NEGATIVE_ACTION);
		}
		
		// when other company requested authorisation to a membership
		else if( $type == self::ALERT_COMPANY_MEMBERSHIP )
		{
			$membership = $data["membership"];
			
			$data = $data["membershipRequest"];
			$company = $this->getCompanyById( "SPB", $data->companyId );
			$brand = $this->getBrandById( $data->brandId );

			$this->objectId = $membership["QO_ID"];
			$this->companyId = $data->companyId;
			
			$this->date = date("d-m-Y", strtotime( $data->dateRequested)); 
			$this->title = 'Would you like to authorise ' . trim( $company[0]["SPB_NAME"] ) . " to have " . $membership["BROWSE_PAGE_NAME"] . "";
			
			$this->addAction("Authorise", 	'https://' . $_SERVER["HTTP_HOST"] . "/membership-auth/authorise-membership-auth-request/format/json/?companyId=" . $data->companyId ."&membershipId=" . $membership["QO_ID"], "json", Myshipserv_AlertManager_Action::POSITIVE_ACTION);
			$this->addAction("Reject", 		'https://' . $_SERVER["HTTP_HOST"] . "/membership-auth/reject-membership-auth-request/format/json/?companyId=" . $data->companyId ."&membershipId=" . $membership["QO_ID"], "json", Myshipserv_AlertManager_Action::NEGATIVE_ACTION);
		}
		
		// when company ask logged user's company to leave a review
		else if( $type == self::ALERT_PERSONAL_REVIEW_REQUEST )
		{
			$company = $this->getCompanyById( "BYO", $data->endorserId );
			if( $data->requestorUserId != "" )
			$user = $this->getUserById( $data->requestorUserId);
			else 
			$user = null;
			$this->objectId = $data->endorserId;
			$this->companyId = $data->endorserId;
			$this->extraKey = $data->code;	
					
			$this->title = 'You have review request for ';// . $company[0]["BYO_NAME"];

			$this->addAction("Leave review", 	'https://' . $_SERVER["HTTP_HOST"] . "/reviews/add-review/reqcode/" . $data->code, "link", Myshipserv_AlertManager_Action::POSITIVE_ACTION);
			$this->addAction("Ignore", 			'https://' . $_SERVER["HTTP_HOST"] . "/reviews/ignore-request/format/json/requestId/" . $data->code, "json", Myshipserv_AlertManager_Action::NEGATIVE_ACTION);
			
			$this->isPersonal = true;
			
		}
		
		// when company ask logged user's for category approval
		else if( $type == self::ALERT_PERSONAL_CATEGORY_REQUEST )
		{
			
			$company = $this->getCompanyById( "SPB", $data["companyId"] );

			$this->objectId = $data["categoryId"];
			$this->companyId = $data["companyId"];
			//$this->extraKey = $data["companyId"];	
					
			$this->title = 'You have category request for ' . $data["categoryName"] . " from "  . $company[0]["SPB_NAME"] . '';

			$this->addAction("Authorise", 	'https://' . $_SERVER["HTTP_HOST"] . "/category-auth/authorise-company/format/json/companyId/" . $data["companyId"] . "/categoryId/" . $data["categoryId"], "json", Myshipserv_AlertManager_Action::POSITIVE_ACTION);
			$this->addAction("Reject", 		'https://' . $_SERVER["HTTP_HOST"] . "/category-auth/reject-company/format/json/companyId/" . $data["companyId"] . "/categoryId/" . $data["categoryId"], "json", Myshipserv_AlertManager_Action::NEGATIVE_ACTION);
			
			$this->isPersonal = true;
		}
		
		// when company is unverified (365 days old listing)
		else if( $type == self::ALERT_COMPANY_UNVERIFIED )
		{
			try{
				$company = $this->getCompanyById( $data["type"], $data["id"] );
				$company = $company[0];
			}catch(Exception $e){
				echo $e->error;
			}
					
			if( $data["type"] == Myshipserv_UserCompany_AdminActions::COMP_TYPE_SPB )
			{
				$this->objectId = $company["SPB_BRANCH_CODE"];
				$this->companyId = $company["SPB_BRANCH_CODE"];
				$this->title = trim( $company["SPB_NAME"] ) . ' is unverified';
			}
			
			$suppliersAdapter = new Shipserv_Oracle_Suppliers($this->db);
			$result = $suppliersAdapter->fetchAccessCodesByBranchCode( $company["SPB_BRANCH_CODE"] );
			
			$this->addAction("Correct", 	'https://' . $_SERVER["HTTP_HOST"] . "/supplier/listing-verified/tnid/" . $company["SPB_BRANCH_CODE"], "json", Myshipserv_AlertManager_Action::POSITIVE_ACTION);
			$this->addAction("Incorrect", 	'http://' . $_SERVER["HTTP_HOST"] . '/pages/admin/selfService/access-code-input.jsf?accessCode=' . $result[0]["ACCESS_CODE"], "link", Myshipserv_AlertManager_Action::NEGATIVE_ACTION);

			$this->isPersonal = false;

		}
		
		// pull total unread
		else if( $type == self::ALERT_COMPANY_UNREAD_ENQUIRIES )
		{
			try{
				$company = $this->getCompanyById( $data["type"], $data["id"] );
				$company = $company[0];
			}catch(Exception $e){
				echo $e->error;
			}
					
			if( $data["type"] == Myshipserv_UserCompany_AdminActions::COMP_TYPE_SPB )
			{
				$this->objectId = $company["SPB_BRANCH_CODE"];
				$this->companyId = $company["SPB_BRANCH_CODE"];
				$this->title = trim( $company["SPB_NAME"] ) . ' has ' . $data['unread'] . ' unread enquir' . (($data['unread']>1)?"ies":"y");
				
			}
			
			$suppliersAdapter = new Shipserv_Oracle_Suppliers($this->db);
			$result = $suppliersAdapter->fetchAccessCodesByBranchCode( $company["SPB_BRANCH_CODE"] );
			
			$this->addAction("Correct", 	'https://' . $_SERVER["HTTP_HOST"] . "/profile/company-enquiry/type/v/id/" . $company["SPB_BRANCH_CODE"], "link", Myshipserv_AlertManager_Action::POSITIVE_ACTION);
			
			$this->isPersonal = false;
			
		}
		
		$this->id = $this->getId(); 
		
		// if personal, use the unique memcache key
		if( $this->isPersonal )
		{
			$key = $this->getUniqueMemcacheKey();
		}
		
		// otherwise use the group key
		else
		{
			$key = $this->getGroupMemcacheKey();
		}
		
		// send data to memcache
		$this->memcacheSet("", "", $key, $this );
	}
	
	
	public function getActionByType( $type )
	{
		foreach( $this->actions as $action )
		{
			if( $action->get("type") == $type )
				return $action;
		}
		return false;
	}
	
	/**
	 * Get unique id of each alert
	 * 
	 * @return string of hash
	 */
	public function getId()
	{
		return md5( "action" . $this->user->id . $this->title . ( ( $this->extraKey != "" ) ? $this->extraKey:"" ) );
	}
	
	/**
	 * Calculate priority based on date
	 * 
	 * @return int difference between today and creation date
	 */
	public function getPriority()
	{
		// calculate priority based on the date
		if( $this->date != "" )
		{
			$date = strtotime($this->date);
			$diff = time() - $date;
		}
		else 
		{
			$diff = time() - 60 * 60 * 24 * 30 * 48; // 4 years ago 
		}
		return $diff;
	}
	
	
	/**
	 * Get information about the memcache keys
	 * 
	 * @return array containing unique id and group id (for shared alert)
	 */
	public function getMemcacheKeys()
	{
		return array(
			"id" => $this->getUniqueMemcacheKey(),
			"group" => $this->getGroupMemcacheKey()
		);
	}
	
	/**
	 * Return public key that is being used by the front end to hide/show (unsuppress) the alert
	 * 
	 * @return String containing memcache id
	 */
	public function getPublicKey()
	{
		return ( $this->isPersonal )?$this->getUniqueMemcacheKey():$this->getGroupMemcacheKey();
	}
	
	/**
	 * Function to convert alert to array
	 * 
	 * @return array
	 */
	public function toArray()
	{
		foreach( $this->actions as $action ){
			$actionsInArray[] = $action->toArray();	
		}
		return array(
			"id" => $this->getId(),
			"key" => $this->getPublicKey(),
			"message" => $this->title,
			"date" => $this->date,
			"type" => $this->type,
			"isSuppressed" => $this->isSuppressed,
			"priority" => $this->getPriority(),
			"actions" => $actionsInArray
			,"debug" => array(
				"keys" => array(
					"unique" => $this->getUniqueMemcacheKey(),
					"group" => $this->getGroupMemcacheKey()
				),
				"objectId" => (int) $this->objectId,
				"companyId" => (int) $this->companyId
			)
		);
	}
	
	/**
	 * Static method to get group memcache key | this needs to match with the normal method underneath
	 * 
	 * @param int $companyId
	 * @param string $type
	 * @param int $objectId
	 * @return string
	 */
	public static function getGroupMemcacheKeyForCache($companyId, $type, $objectId )
	{
		return
			'Alert' . 
			'__' . 'companyId' . $companyId .
			'__' . 'type' . $type.
			'_' . md5( "objectId" . $objectId . $type );				
		
	}
	
	/**
	 * Normal method to get group memcache key | this needs to match with the normal method underneath
	 * 
	 * @return string
	 */
	public function getGroupMemcacheKey()
	{
		if( $this->isPersonal ) return '';
		return 
			$this->getGroupMemcacheKeyForCache( $this->companyId, $this->type, $this->objectId);
	}
	
	/**
	 * Get unique memcache key for each alert
	 * 
	 * @return string
	 */
	public function getUniqueMemcacheKey()
	{
		return 
			'Alert__' . $this->getId();				
	}
	
	/**
	 * Add any action available to an alert
	 * 
	 * @param string $title
	 * @param string $url
	 * @param string $callType
	 */
	public function addAction( $title, $url, $callType, $type )
	{
		if( $callType != "json" && $callType != "link" )
			throw new Exception("Invalid type of call on class Alert");
			
		$action = new Myshipserv_AlertManager_Action($title, $url, $callType, $type);
		
		// store actions to array 
		$this->actions[] = $action;
	}
	

	
	
	/**
	 * Helper function to get a brand by using its ID
	 * 
	 * @see Shipserv_Oracle_Brands
	 * @param int $id
	 * @return object
	 */
	private function getBrandById( $id )
	{
		$brandAdapter = new Shipserv_Oracle_Brands( $this->db );
		return $brandAdapter->fetch(intval($id));
	}
	
	/**
	 * Helper function to get user using its ID
	 * 
	 * @see Shipserv_Oracle_User
	 * @param int $id
	 * @return object
	 */
	private function getUserById( $id )
	{
		$userAdapter = new Shipserv_Oracle_User( $this->db );
		return $userAdapter->fetchUserById( $id );
	}
	
	/**
	 * Helper function to get company using its ID
	 * Note that there's a caching layer in this function
	 * 
	 * @param char(3) $type
	 * @param int $id
	 * @return object
	 */
	private function getCompanyById( $type, $id )
	{
		$byoDao = new Shipserv_Oracle_BuyerOrganisations($this->db);
		
		$spbDao = new Shipserv_Oracle_Suppliers($this->db);
						// Pull out company details from supplier / buyer
		$companyDetail = null;
		
		if ( $type == 'BYO')
		{
			if( !isset( $this->companyBuyer[$id] ) )
			{
				// Fetch buyer record from db
				$company = $byoDao->fetchBuyerOrganisationById( $id );
				
				$this->companyBuyer[$id] = $company;
			}
			else 
			{
				return $this->companyBuyer[$id];
			}
		}
		
		else
		{
			if( !isset( $this->companySupplier[$id] ) )
			{
				$company = $spbDao->fetchSuppliersByIds(array($id));
				$this->companySupplier[$id] = $company;
			}
			else 
			{
				return $this->companySupplier[$id];
			}
		}
		return $company;
	}
}