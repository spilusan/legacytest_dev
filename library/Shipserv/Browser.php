<?php

/**
 * Class to parse the user agent into an easier to identify string, separating out
 * the browser and version
 * 
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ Ltd
 */
class Shipserv_Browser extends Shipserv_Object
{
	/**
	 * The browser version
	 * 
	 * @access protected
	 * @var string
	 */
	protected $version;
	
	/**
	 * The name of the browser, if extracted from the browsers array
	 * 
	 * @access protected
	 * @var string
	 */
	protected $name;
	
	/**
	 * The full user agent
	 * 
	 * @var protected
	 * @var string
	 */
	protected $agent;
	
	/**
	 * Array of valid browser names
	 * 
	 * @access private
	 * @static
	 * @var array
	 */
	private static $browsers = array("firefox", "msie", "opera", "chrome", "safari",
									 "mozilla", "seamonkey", "konqueror", "netscape",
									 "gecko", "navigator", "mosaic", "lynx", "amaya",
									 "omniweb", "avant", "camino", "flock", "aol",
									 "chrome");
	
	/**
	 * Array of crawler names that set the browser name to 'crawler' and the
	 * version to null
	 * 
	 * @access private
	 * @static
	 * @var array
	 */
	private static $crawlers = array("googlebot", "yahoo", "cuil", "scoutjet",
									 "jeeves", "curl", "crawler", "dotbot",
									 "aihitbot", "mj12bot", "accelobot",
									 "scanalert", "msnbot", "camontspider",
									 "baidu", "purebot", "digext", "exabot",
									 "sitebot", "ocelli", "adsbot", "sogou web spider",
									 "nextgensearchbot", "bingbot", "atomic_email_hunter",
									 "turnitinbot", "sph search crawler", "spider", "smarte bot",
									 "sogou", "slurp", "snapbot", "crawler", "semanticdiscovery",
									 "renlifangbot", "proodlebot", "plonebot", "ogglebot", "msnbot", "isrccrawler",
									 "mlbot", "mj12bot", "linguee bot", "krakspider", "gingerbot", "gaisbot",
									 "searchbot", "keyword research", "httrack", "digext", "^mozilla/5.001$",
									 "yandex", "mail.ru", "wbsearchbot", "apptusbot"
								);
	
	/**
	 * Constructor for the browser object. Will parse the current user agent
	 * into a the browser and version, and ignore it if it matches a crawler
	 * 
	 * @access public
	 */
    public function __construct ()
    {
        $this->agent = strtolower($_SERVER['HTTP_USER_AGENT']);
		
		foreach (self::$crawlers as $crawler)
		{
			if (stristr($this->agent, $crawler))
			{
				$this->name    = 'crawler';
				$this->version = null;
				
				return;
			}
		}
		
        foreach (self::$browsers as $browser)
        {
            if (preg_match("#($browser)[/ ]?([0-9.]*)#", $this->agent, $match))
            {
                $this->name    = $match[1] ;
                $this->version = $match[2] ;
                break;
            }
        }
    }
	
	/**
	 * Fetches the name of the browser, correctly formatted
	 *
	 * @access public
	 * @return string
	 */
	public function fetchName ()
	{
		if ($this->name == 'crawler')
		{
			return $this->name;
		}
		elseif (isset($this->name) && ($this->version))
		{
			return $this->name.' '.$this->version;
		}
		else
		{
			return 'UNKNOWN';
		}
	}
	
	public function getIpAddress()
	{
		$remoteIp = Myshipserv_Config::getUserIp();
		return $remoteIp;
	}
	
	public function getReferrer()
	{
		return $_SERVER['HTTP_REFERER'];
	}
    
    public function getUserAgent()
    {
        return $this->agent;
    }
} 