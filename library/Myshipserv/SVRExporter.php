<?php
/**
 * 
 * @author Elvir <eleonard@shipserv.com>
 */
class Myshipserv_SVRExporter extends Shipserv_Memcache {
	
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
	public function setReport( $calculateAllAlert = true )
	{
		if( $calculateAllAlert === true )
			return count($this->wallet);
		else
			return $this->total;
	}
		
}