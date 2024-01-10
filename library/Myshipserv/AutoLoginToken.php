<?php
/**
 * In order to give auto login a user to the system, a token need to be created. This 
 * class handles the token generation and the auto login itself. What you'll need to do
 * is to specify the userId that needs to be logged in, url redirection and how long is 
 * the token going to be valid for.
 * 
 * @usage please see: Myshipserv_NotificationManager_Email_BrandAuthInviteBrandOwner::getBody()
 * 
 * @author Elvir <eleonard@shipserv.com>
 */
class Myshipserv_AutoLoginToken
{
	private static $inst;
	
	private $db;
	private $uDao;
	private $token;
	private $tokenId;
	
	public function __construct ($db)
	{
		$this->db = $db;
		$this->uDao = new Shipserv_Oracle_AutoLoginToken($db);
	}
	
	public static function getInstance ()
	{
		if (!self::$inst)
		{
			self::$inst = new self($GLOBALS['application']->getBootstrap()->getResource('db'));
		}
		return self::$inst;
	}
	
	/**
	 * Class to handle token generation for autologin. This will return string
	 *  of unique URL that is only accessible for one time.
	 *
	 * @param Integer $userId userId of user that will be automatically logged in
	 * @param String $urlRedirect information about the redirection, all process should be on the redirection on the the token check page
	 * @param String $expiry Expiration of the token (available options: 1 click, 1 day, 2 days, 3 days, 4 days and so on)
	 * @return String tokenId
	 */
	public function generateToken($userId, $urlRedirect, $expiry)
	{		
		// generate tokenId to be stored
		$tokenId = md5( $userId . date("Y-m-d h:i:S") . $urlRedirect );

		// store in db
		$result = $this->uDao->store( $userId, $tokenId, $urlRedirect, $expiry);
		
		$this->tokenId = $tokenId;
		
		return $tokenId;
		
	}
	
	/**
	 * Validate token and change the status of it to visited depending on the expiry parameter specified
	 * 
	 * @param string tokenId
	 * @return false|array this function can return false or information about the token in array which can be used in the caller
	 */
	public function checkToken($tokenId)
	{
		// get token detail
		$tokens = $this->uDao->fetch( array("PAT_TOKEN_ID" => $tokenId ), 0,1 );
		
		// check if token exists
		if( count( $tokens ) > 0 )
		{
			$token = $tokens[0];
			
			// update visited date
			$this->uDao->updateDateVisited( $tokenId );
			
			// then get the data w/ the new detail
			$tokens = $this->uDao->fetch( array("PAT_TOKEN_ID" => $tokenId ), 0,1 );
			
			// validate token according to the expiry column 
			if( $token["PAT_EXPIRY"] == "1 click")
			{
				// remove it once it's been used
				$this->uDao->remove(array("PAT_TOKEN_ID" => $tokenId));
			}
			
			// process x days expiry
			else if( strstr( $token["PAT_EXPIRY"], "day") !== false)
			{
				// get number of days
				$x = explode(" ", $token["PAT_EXPIRY"]);
				$numOfDays = $x[0];
				
				// helper
				$string =  $token["PAT_DATE_CREATED"];
				
				$start = strtotime( $string );
				$end = $start + ( $numOfDays * 86400 );
				$current = time();
				
				//check and kill the token if it's more than longer than the expiry date
				if( $current > $end )
				$this->uDao->remove(array("PAT_TOKEN_ID" => $tokenId));
			}
			
			//
			else if( $token["PAT_EXPIRY"] == "" )
			{
				
			}
			
			// if expiry isn't supported
			else
			{
				throw new Exception("AutoLoginToken cannot support " . $token["expiry"]);
			}
			
			return $token;
		}
		// return false if token not found
		else
		{
			return false;		
		}
	}
	
	/**
	 * return unique token
	 * at the moment, we only use the md5(url), but we can add some clever stuff later
	 * 
	 * @return string md5 token
	 */
	private function hash($url)
	{
		$hash = md5( $url );
		return $hash;
	}
	
	/**
	 * Based on all data, this function will then create a url to be used to verify the autologin token
	 * 
	 * @return string url
	 */
	public function generateUrlToVerify()
	{
		return 'https://' . $_SERVER['HTTP_HOST'] . $this->makeLinkPath('check', 'token', null, array("t"=>$this->tokenId));
	}
	
	/**
	 * Creates a relative URL path from parameters.
	 *
	 * Note: conscious decision not to use Zend URL helper - handles empty modules inadequately.
	 * Note: conscious decision not to use '?&' style parameters - these mess up some encoding/redirection practices used by the app.
	 * 
	 * @return string
	 */
	protected function makeLinkPath ($action, $controller, $module, array $paramArr)
	{
		$url = '';
		
		if ($module != '')
		{
			$url .= '/' . $module;
		}
		
		$url .= '/' . $controller . '/' . $action;
		
		foreach ($paramArr as $pn => $pv)
		{
			$url .= '/' . urlencode($pn) . '/' . urlencode($pv);
		}
		
		return $url;
	}
		
}