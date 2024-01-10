<?php
/**
 * Class managing alerts for logged user.
 *
 * functionalities:
 *  - pull all available alerts and cache it
 * 	- removal of corporate/company based alerts with given memcache key
 *  - handle suppression from front-end and store it on cookie+backend
 *    cache file
 *
 * note:
 * 	- if memcache isn't running, it won't return any alert and won't throw
 *    any error, it will return null
 *
 * @author Elvir <eleonard@shipserv.com>
 */
class Myshipserv_AlertManager extends Shipserv_Memcache {

	/**
	 * store instance of disk cache
	 * @var unknown_type
	 */
	private static $diskCache;

	/**
	 * To store instance of this class
	 * @var object
	 */
    protected static $_instance = null;

	protected $db;
	protected $user;
	protected $company;
	protected $data;
	protected $alertMemcacheKeys;
	protected $total = 0;

	public $groupBy = "";

	const MEMCACHE_TTL = 120;

	function __construct( $db = null )
	{
		// get db
		if( $db != null )
			$this->db = $db;
		else
			$this->db = $this->getDb();

		$this->setMemcacheTTL( self::MEMCACHE_TTL );

		// get config
		$this->config = Zend_Registry::get('config');

		$this->init();

		// get disk cache for pulling suppressed alerts
		$cache = $this->getDiskCache();

		// get any suppressed keys
		$suppressedKeys = $cache->load( $this->getLoggedMemberSuppressedKey() );
	}

	/**
	 * Returns total of alert
	 *
	 * @return int total of alert
	 */
	public function getTotal( $calculateAllAlert = true )
	{
		if( $calculateAllAlert === true )
			return count($this->wallet);
		else
			return $this->total;
	}

	/**
	 * Refreshing alerts available for user
	 *
	 * @void
	 */
	public function refresh()
	{
		$this->init( true );
	}

	/**
	 * Pull all alerts with options
	 *
	 * @param string $filterByType Myshipserv_AlertManager_Alert::ALERT_PERSONAL_REVIEW_REQUEST, ALERT_COMPANY_MEMBERSHIP, ALERT_COMPANY_BRAND_AUTH, ALERT_COMPANY_USER_JOIN
	 * @param string $groupBy at the moment, this only support "type"
	 * @param string $sortBy only support 'type' at the moment
	 * @param bool $returnAsObject TRUE: it'll return as objects, FALSE: return as array
	 * @return Array of alert
	 */
	public function getAlert( $filterByType = "", $groupBy = "", $sortBy = "", $returnAsObject = false )
	{
		// validation
		if( !is_bool( $returnAsObject ) )
		{
			throw new Exception("Invalid parameter type: returnAsObject");
		}

		if( $filterByType != "" && in_array( $filterByType, Myshipserv_AlertManager_Alert::$alertTypes ) === false)
		{
			throw new Myshipserv_Exception_MessagedException("Invalid parameter: filterByType", 400);
		}

		if( $groupBy != "" && $groupBy != "type" )
		{
			throw new Myshipserv_Exception_MessagedException("Invalid parameter: groupBy", 400);
		}

		if( $sortBy != "" && $sortBy != "priority" )
		{
			throw new Myshipserv_Exception_MessagedException("Invalid parameter: sortBy", 400);
		}

		// if there's alert for user
		if( count( $this->wallet ) > 0 )
		{
			foreach( $this->wallet as $alert )
			{
				$alert = $alert["data"];

				// change suppression flag
				if( $suppressedKeys !== false && count( $suppressedKeys ) > 0
					&& in_array( $alert->getPublicKey(), $suppressedKeys ) )
						$alert->set("isSuppressed", true );

				// check if front end wants to group by type
				if( $groupBy == "type" )
				{
					// check filter
					if( $filterByType != "" )
					{
						if( $filterByType == $alert->type )
						{
							$output[$alert->type][] = ( $returnAsObject ) ? $alert : $alert->toArray();
							$this->total++;
						}
					}
					else
					{
						$output[$alert->type][] = ( $returnAsObject ) ? $alert : $alert->toArray();
						$this->total++;
					}
				}

				// otherwise
				else
				{
					// check filter
					if( $filterByType != "" )
					{
						if( $filterByType == $alert->type )
						{
							$output[] = ( $returnAsObject ) ? $alert : $alert->toArray();
							$this->total++;
						}
					}
					else
					{
						$output[] = ( $returnAsObject ) ? $alert : $alert->toArray();
						$this->total++;
					}
				}
			} // end of for loop

			if( $sortBy == "priority" )
			{
				// sort data by priority
				if( $groupBy == "type" )
				{
					foreach( $output as $type => $data )
					{
						uksort( $data, array( __CLASS__, 'sortingByPriorityAlgorithm' ) );
					}
				}
				else
				{
					uksort( $output, array( __CLASS__, 'sortingByPriorityAlgorithm' ) );
				}
			}

			if( $returnAsObject )
				return $output;
			else
				return $this->toArray( $output );
		}
		// if wallet is empty
		else
		{
			if( $returnAsObject )
				return $output;
			else
				return $this->toArray( null );
		}
	}

	/**
	 * Get unique key of alert manager
	 * @return string md5 version of memcache key
	 */
	public function getId()
	{
		return md5( $this->getMemcacheKey() );
	}

	/**
	 * Convert this object to array
	 *
	 * @param array $output
	 */
	public function toArray( $output )
	{
		// final data structure
		return array(
			"total" => $this->getTotal( false ),
			"alerts" => $output
			, "debug" => array( "key" => $this->getMemcacheKey(),
								"id" => $this->getId() )
		);
	}

	/**
	 * Custom sorting function which will compare priority data
	 *
	 * @param array $a
	 * @param array $b
	 * @return int position
	 */
	private static function sortingByPriorityAlgorithm($a, $b)
	{
		if( $a["priority"] == $b["priority"] ) return 0;
		else return ($a["priority"] > $b["priority"])?+1:-1;
	}

	/**
	 * If user choose to remove/suppress the reminder, this will store the key to
	 * the suppressed ids on server disk
	 *
	 * @param string $key
	 * @return bool $output if the data has been stored properly in disk cache
	 */
	public function suppressData( $key )
	{
		$output = false;
		$cache = $this->getDiskCache();

		// pull existing suppressed keys (in array)
		$existing = $cache->load( $this->getLoggedMemberSuppressedKey() );

		// if nothing, save a new one
		if( ! $existing ){
			$output = $cache->save(array($key), $userKey);
		}

		// if exist, append
		else
		{
			if( in_array( $key, $existing ) === true )
				$output = true;
			else
				$output = $cache->save( array_merge(array($key), $existing), $userKey );
		}

		// store it to cookie
		$this->addKeyToCookie( $this->getLoggedMemberSuppressedKey() );

		return $output;
	}

	/**
	 * Remove key from the disk (to restore an alert)
	 * @param string $key
	 * @return bool $output if the data has been stored properly in disk cache
	 */
	public function unsuppressData( $key )
	{
		$output = false;
		$cache = $this->getDiskCache();

		// pull existing suppressed keys (in array)
		$existing = $cache->load( $this->getLoggedMemberSuppressedKey() );

		foreach( $existing as $result )
		{
			if( $result != $key )
				$new[] = $result;
		}

		// remove key from cookie
		$this->removeKeyFromCookie( $this->getLoggedMemberSuppressedKey() );

		return $cache->save( $new, $this->getLoggedMemberSuppressedKey() );
	}

	/**
	 * Initialise data and pull all necessary data before converting it to alert
	 *
	 * @throws Exception
	 */
	private function init( $ignoreCache = false )
	{
		$db = $this->db;

		// get all companies that this user is belong to
	    $user = $this->getLoggedMember();

	    // if user's found
		if ( is_object( $user ) )
		{
			$this->user = $user;

			try {
				$this->convert( $ignoreCache );
			} catch (Exception $e) {
				//echo $e->getMessage();
			}
		}

		// if not logged, throw an exception
		else
		{
			throw new Myshipserv_Exception_MessagedException("You need to login to get all alerts.");
		}
	}

	/**
	 * Manage disk cache
	 *
	 * @return Zend_Cache
	 */
	private function getDiskCache ()
	{
		if (! self::$diskCache)
		{
			$frontendOptions = array(
				'lifetime' => null,
				'automatic_serialization' => true
			);

			$backendOptions = array(
				'cache_dir' => $this->config->shipserv->diskcache->dir
			);

			self::$diskCache = Zend_Cache::factory(
				'Core',
				'File',
				$frontendOptions,
				$backendOptions
			);
		}

		return self::$diskCache;
	}


	/**
	 * Get memcache key of wallet that handle alerts, each logged in user will have different keychain/wallet/keys
	 * @return string memcache key
	 */
	private function getMemcacheKey()
	{
		return
			'AlertManager_userId' . $this->getLoggedMember()->userId;
	}

	/**
	 * Once any outstanding actions have been pulled we will then convert them to alert
	 * Previously $this->data contains all necessary information which is required by
	 * Myshipserv_AlertManager_Alert class
	 *
	 * @return void
	 */
	private function convert( $ignoreCache = false )
	{
		// get unique key for each user
		$key = $this->getMemcacheKey();

		// pulls group alert
		if( $ignoreCache == true )
			$keychain = false;
		else
			$keychain = @$this->memcacheGet("", "", $key);

		$loggedMember = $this->getLoggedMember();

		// if keychain for this user is not available
		if( $keychain === false || $keychain == null)
		{
			// pull all data needs to be converted to alert from pending action class
			$pendingAction = new Myshipserv_Controller_Action_Helper_PendingAction();
			$this->data = $pendingAction->getData();


			// parse through the different types of actions
			foreach( $this->data as $type => $results )
			{

				// parse through each action
				foreach( $results as $result )
				{
					// turn it into alert
					$alert = new Myshipserv_AlertManager_Alert($this->db, $type, $result, $this->config, $loggedMember);

					// remove any broken alerts (eg: when there's disrepancies on the db)
					// case 1: 	when querying supplierId 208579 on dev using Shipserv_Oracle_Suppliers::fetch returns
					// 			empty array
					// store it to user's wallet
					$this->wallet[] = array( "uniqueKey" => $alert->getUniqueMemcacheKey(),
									  		 "groupKey" => $alert->getGroupMemcacheKey(),
											 "data" => $alert );

					// split group notification and personal
					// for group notification
					if( $alert->getGroupMemcacheKey() != "" )
					{
						$keychain[] = $alert->getGroupMemcacheKey();
					}
					// for personal notification
					else
					{
						$keychain[] = $alert->getUniqueMemcacheKey();
					}

				}
			}

			// store to memcache
			$this->memcacheSet("", "", $key, $keychain);
		}

		// if cache hit
		else
		{
			foreach( $keychain  as $keyToSeek )
			{
				if( $keyToSeek != "" )
				{
					$alert = $this->memcacheGet( "","", $keyToSeek  );
					if( $alert !== false )
					{
						$this->wallet[] = array( "uniqueKey" => $alert->getUniqueMemcacheKey(),
										  		 "groupKey" => $alert->getGroupMemcacheKey(),
												 "data" => $alert );
					}
				}
			}
		}
	}

	/**
	 * Get db resource
	 */
	private function getDb()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}

	/**
	 * Get logged member
	 *
	 * @return Shipserv_User logged user
	 * @throws Myshipserv_Exception_NotLoggedIn if not logged n
	 */
	private function getLoggedMember()
	{
		$user = Shipserv_User::isLoggedIn();
		if (!$user)
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}
		return $user;
	}


	/**
	 * Static function to remove any company action on memcache
	 *
	 * @param int $companyId
	 * @param string $type Myshipserv_AlertManager_Alert::ALERT_PERSONAL_REVIEW_REQUEST, ALERT_COMPANY_MEMBERSHIP, ALERT_COMPANY_BRAND_AUTH, ALERT_COMPANY_USER_JOIN
	 * @param int $objectId
	 * @return bool TRUE if memcache delete data successfully
	 */
	public static function removeCompanyActionFromCache($companyId, $type, $objectId)
	{
		$alertManager = self::getInstance();
		$config = $alertManager->getConfig();
	  	// get unique key for each action + objectId + companyId
		$key = Myshipserv_AlertManager_Alert::getGroupMemcacheKeyForCache( $companyId, $type, $objectId );

		// remove from memcache and clean the cookie
		$alertManager->removeKeyFromCookie( $key );
		return $alertManager->purgeCache($key,0); 
	}

	/**
	 * Function to remove memcache data, developer can specify prefix of keys that wants to be purged
	 *
	 * @param string $prefix
	 * @return bool TRUE
	 */
	public static function removeMemcacheData( $prefix )
	{
		$self = self::getInstance();

		$caches = self::peekMemcache( $prefix );

		foreach( $caches as $key => $cache )
		{
			if( $self->purgeCache( $key ) )
			{
				$result = true;
				$self->removeKeyFromCookie( $key );
			}
			else
			{
				$result = false;
			}
		}
		return $result;
	}

	/**
	 * Singleton to access function from static function
	 *
	 * @return object this class
	 */
	final public static function getInstance()
	{
		if( null !== self::$_instance )
		{
			return self::$_instance;
		}

		self::$_instance = new self;
		return self::$_instance;
	}

	/**
	 * This helper function will return the name of the file for storing suppressed alert memcache keys
	 *
	 * return String
	 */
	private function getLoggedMemberSuppressedKey()
	{
		return 'surpressed_alert_for_' . $this->getLoggedMember()->userId;
	}

	/**
	 * Store id to cookie for the front end to read if user had suppressed particular alert
	 *
	 * @param string $id
	 * @return bool result of saving the id to the cookie
	 */
	public function addKeyToCookie( $id )
	{
		$cookieManager = Zend_Controller_Action_HelperBroker::getStaticHelper('Cookie');

		$hiddenIds = $cookieManager->decodeJsonCookie("alert");

		return $cookieManager->encodeJsonCookie("alert", ( count( $hiddenIds ) == 0 ) ? array( $params["id"]) : array_merge( $hiddenIds, array( $params["id"] ) ) );

	}

	/**
	 * Remove id from cookie
	 *
	 * @param string $id
	 * @return bool result of saving the id to the cookie
	 */
	public function removeKeyFromCookie( $id )
	{
		// get the cookie manager
		$cookieManager = Zend_Controller_Action_HelperBroker::getStaticHelper('Cookie');

		// get the latest data
		$hiddenIds = $cookieManager->decodeJsonCookie("alert");

		// process it
		foreach( $hiddenIds as $hiddenId )
		{
			if( $hiddenId != $id )
				$new[] = $id;
		}

		// store it onto cookie
		return $cookieManager->encodeJsonCookie("alert", $new );

	}

}
