<?php
/*
* This class encapsualtes the roles of redirecting 
*/
class Myshipserv_CAS_CasRoleRedirector
{
    /**
    * This var containing the instance 
    *
    * @var object
    */
    private static $_instance;

    const TABLE_NAME    = 'users';
    const COL_TYPE    = 'usr_type';
    const COL_USERNAME  = 'usr_name';
    const SELECT_TYPE = 'status';

    /*
    * Protected varoanles
    */
    protected $dao;
    protected $config;
    protected $rootDomain;

    /**
    * Singleton 
    * 
    * @return Myshipserv_CAS_CasRest
    */
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }
        
        return static::$_instance;
    }

    /**
    * Protected classes to prevent creating a new instance 
    * @return object
    */
    protected function __construct()
    {
    	$this->dao = Shipserv_Helper_Database::getDb();
    	$this->config = Myshipserv_Config::getIni();
        $this->rootDomain = 'https://'.$this->getApplicationHostName();

    }

    /**
    * Protect class to be cloned (for a proper singleton)
    */
    private function __clone()
    {
    }

    /**
    * Get the redirection URL by user name
    * @param string $userName The user name
    * @return string URL to redicect
    */
  	public function getRedirectUrl($userName)
  	{
        
        if (strstr($userName, '@') === false) {
            $roles = Shipserv_User_Roles::getInstance()->getUsrRoles($userName);
            if (in_array('adminGateway', $roles)) {
                return $this->rootDomain."/admin";
            } elseif (in_array('pagesAdmin', $roles)) {
                return $this->rootDomain."/pages/admin";
            } 
        }

        //If it was not decided according to the roles where to redirect, decide according to usertype
        $userType = $this->userTypeByName($userName);
        if ($userType) {
        	switch ($userType) {
        		case 'S':
        		case 'A':
        		case 'R':
                    //Redirect to Admin Gateway
                    return $this->rootDomain."/admin";
        		case 'B':
        		case 'V':
        			//redirect user to eSSM via /LoginToSystem url
        			return $this->convertToRedirectUrl($this->rootDomain."/LoginToSystem", true);
        			break;
        		case 'BR':
        		case 'SR':
        			//redirect user to WebReport (PHP)
        			return $this->rootDomain."/webreporter/not-available-for-tradenet?mode=session";
        			break;
        		case 'BL':
        		case 'DL':
        			//redirect user to Logistics via /LoginToSystem unlesss we have this on PHP
        			return $this->convertToRedirectUrl($this->rootDomain."/LoginToSystem", true);
        			break;
        		case 'ST':
        			//redirect user to StatusTracker via /LoginToSystem unless we have this on PHP
        			return $this->convertToRedirectUrl($this->rootDomain."/LoginToSystem", true);
        			break;
        		case 'P':
        			//pages, rerirect to /search
        			return $this->rootDomain."/search";
        			break;
        		default:
        			//In other cases loginToSystem will decide where to go
        			return $this->convertToRedirectUrl($this->rootDomain."/LoginToSystem");
        			break;
        	}
        } else {
        	throw new Myshipserv_Exception_MessagedException("Invalid user account: $userName", 403);
        }
        return $this->rootDomain."/search";
  	}

    /**
    * Get the User Type 
    * Throws error
    * @param string $userName User Name
    * @return string 
    */
  	protected function userTypeByName($userName)
  	{
        $select = new Zend_Db_Select($this->dao);
        $select
            ->from(
                array('u' => self::TABLE_NAME),
                array(
                    self::SELECT_TYPE => 'u.' . self::COL_TYPE
                )
            )
            ->where('u.' . self::COL_USERNAME . ' = ?', $userName);

        $id = $select->getAdapter()->fetchOne($select);    
        return $id;
  	}

    /**
    * Convert the url to a redirecter URL for going to CAS redirect page to manage the Java Session, If we do not do this, apps like WebBuyer (ESSM) Pages Admin, Admin GW will not work
    * or we will end up in a forever redirection loop
    * @param string $url (Can be absolute, relative, If relative it will add the current host to make it absolute, as the redicct page do a URL validation containing shipserv.com)
    * @param string $forceHttp if we want to force the app to HTTP (for backward compatibility for apps not yet moved to HTTPS)
    * @return string 
    */
  	protected function convertToRedirectUrl($url, $forceHttp = false)
  	{
  		if (strpos($url, 'http') === false) {
  			$callUrl =  'https://'.$this->getApplicationHostName().$url;
  		} else {
  			$callUrl = $url;
  		}
  		
        if ($forceHttp === true) {
            $callUrl = str_replace('https://', 'http://', $callUrl);
        }
        
        /*
        * return $this->config->shipserv->services->cas->rest->redirectUrl . '?service=' . urlencode($callUrl);
        * Because /LoginToSystem not capable of properly getting the url encoded param, I had to remove the urlencode part, Here technically we have an invalid URL, but this is how it works
        * The loginToSystem will generate an url encoded version, then will redirect again, so it is an unnesesary redirect, but can only be fixed by Allan on Java side
        */
  		return $this->config->shipserv->services->cas->rest->redirectUrl . '?service=' . urlencode($callUrl);
  	}

    /**
    * Get the hostname of the application
    * @return string
    */
    public function getApplicationHostName()
    {
        return $this->config->shipserv->application->hostname;
    }

    /**
    * Get the root domain
    * @return string
    */
    public function getRootDomain()
    {
        return $this->rootDomain;
    }
  
}

