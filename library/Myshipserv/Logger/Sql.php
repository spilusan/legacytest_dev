<?php 
class Myshipserv_Logger_Sql
{

    private static $_instance;
    protected $logger;
    protected $logAll;
    protected $runningTime;
    
    /**
    * Create Singleton Instance
    * @param boolean $logAll      Log all event
    * @param boolean $runningTime Log events runnning longer then X
    * @return object 
    */
    public static function getInstance($logAll = false, $runningTime = 0)
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
            static::$_instance->logAll = $logAll;
            static::$_instance->runningTime = $runningTime;
        }
        
        return static::$_instance;
    }

	/**
	* Protected classes to prevent creating a new instance 
	* @return object
	*/
    protected function __construct()
    {
    	
    }

    /**
    * Protect to clone class
    * @return object
    */
    private function __clone()
    {
    }

    /**
    * Log DB queryes to sql-profile-log
    * This only happens if application.ini says resources.db.params.profiler.enabled=true
    * 
    * @return unknown
    */
	public function log()
	{
		$dbsToProfile = array(
                'sservdba' =>  Shipserv_Helper_Database::getDb(),
                'ssreport2' =>  Shipserv_Helper_Database::getSsreport2Db(),
            );

        foreach ($dbsToProfile as $dbname => $db) {
            $profiler = $db->getProfiler();

            if ($profiler->getEnabled() === true) {
            	if (!$this->logger) {
            		$this->logger = new Myshipserv_Logger_File('sql-profile-log'); 
            	}

            	$contextLogString = '[Database name: ' . $dbname . '] [Request URI: '. $_SERVER['REQUEST_URI'] .'] [Params: '. (empty($_REQUEST)? '{}' : json_encode($_REQUEST)) . ']';
                $queryProfiles = $profiler->getQueryProfiles();
                if ($queryProfiles) {
                    $totalTime    = $profiler->getTotalElapsedSecs();
                    $queryCount   = $profiler->getTotalNumQueries();
                    $longestTime  = 0;
                    $longestQuery = null;
                    $longestQueryParams = null;
                    
                    $hashList = array();
                    $duplicatedQueryCount = 0;
                    foreach ($queryProfiles as $query) {
                    	array_push($hashList, md5($query->getQuery() . '_' . serialize($query->getQueryParams())));
                    }
                    
                    foreach ($queryProfiles as $query) {
                    	$executeTimes = $this->arrayCountHashes(md5($query->getQuery() . '_' . serialize($query->getQueryParams())), $hashList);
                    	$duplicatedQueryCount += ($executeTimes > 1) ? 1 : 0;
						if ($this->logAll === true) {
							if ($query->getElapsedSecs() > $this->runningTime) {
                        		$this->logger->log(
                        		    '[QueryLog]'
                                    . ' ' . $contextLogString 
                    		        . " [Query: " . $this->nl2Space($query->getQuery()) . "]"
                    		        . " [QueryParams: " . json_encode((array) $query->getQueryParams()) . "]" 
                    		        . " [Exectime: " . $query->getElapsedSecs() . "]"
                        			. " [Executed: " . $executeTimes . " times]"
                		        );
                        	}
                        }
                        
                        if ($query->getElapsedSecs() > $longestTime) {
                            $longestTime  = $query->getElapsedSecs();
                            $longestQuery = $this->nl2Space($query->getQuery());
                            $longestQueryParams = '';
                            if (count($query->getQueryParams()) > 0) { 
                                $longestQueryParams = json_encode($query->getQueryParams());
                            }
                        }
                    }
                    
                    $this->logger->log('[QuerySummary] ' . $contextLogString . ' [Executed: ' . $queryCount . ' queries in ' . $totalTime . ' seconds' . "]");
                    $this->logger->log('[QuerySummary] ' . $contextLogString . ' [Average query length: ' . $totalTime / $queryCount . ' seconds' . "]");
                    $this->logger->log('[QuerySummary] ' . $contextLogString . ' [Queries per second: ' . $queryCount / $totalTime . "]");
                    $this->logger->log('[QuerySummary] ' . $contextLogString . ' [Longest query length: ' . $longestTime . "]");
                    $this->logger->log('[QuerySummary] ' . $contextLogString . ' [Duplicated query count: ' .  $duplicatedQueryCount. "]");
                    if ($this->logAll === false) {
                    	$this->logger->log('[QuerySummary] ' . $contextLogString . " [Longest query: $longestQuery ] [QueryParams: $longestQueryParams]");
                	}
                }
            }
        }
	}

    /**
    * Beautify SQL, and merge into one row
    * @param string $string
    * @return string
    */
    protected function nl2Space($string)
    {
        return  preg_replace('!\s+!', ' ', str_replace(array("\n", "\r"), " ", $string));
    }
    
    /**
     * Count the number of matching hashes
     * @param string $value
     * @param array $array
     * @return number
     */
    protected function arrayCountHashes($value, $array)
    {
    	$result = 0;
    	for ($i=0;$i<count($array);$i++) {
    	
    		if ($array[$i] === $value) {
    			$result ++;
    		}
    	}
    	
    	return $result;
    }
}
