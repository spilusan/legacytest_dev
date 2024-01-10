<?php
/**
 * Manage SPR warmup scripts, This must be called via CLI
 * @author attilaolbrich
 */

class Myshipserv_Spr_WarmupToDbManager
{
    /**
     * @var Myshipserv_Spr_WarmupManager
     */
	private static $_instance;

    /**
     * @var Myshipserv_ReportService_Gateway
     */
	protected $reportService;

    /**
     * @var string
     */
	protected $sessionId;

    /**
     * List of supported time interval names
     * Moved from inside the function by Yuriy Akopov on 2017-06-30, S20499
     *
     * @var array
     */
    protected static $periodList = array(
        'week',
        'month',
        'quarter'
    );

    /**
     * Default list of services to warm
     * Moved from inside the function by Yuriy Akopov on 2017-06-30, S20499
     *
     * @var array
     */
	protected static $warmServices = array(
        'shipserv-average' => array(
            // quoting tab
            array(
                Myshipserv_ReportService_Gateway::REPORT_SPR_QUOTING => array(
            				'response-rate-all-quoted',
            				'response-rate-all-ignored',
            				'response-rate-all-declined',
            				'response-rate-all-pending',
                        'get-avg-response-time-summary',
                        'quote-completeness',
                        'quote-variance',
                        'decline-reason'
            		)
            ),
            // ordering tab
        	array(
        	       Myshipserv_ReportService_Gateway::REPORT_SPR_ORDERING => array(
        					'direct-order-count-gmv',
        					'competitive-order-count-gmv',
        					'poc-count-gmv-time',
        					'poc-count-gmv-time-total'
        			)
        	),
        	// cycle tab
        	array(
        	    Myshipserv_ReportService_Gateway::REPORT_SPR_CYCLE_TIME => array(
        					'get-rfq-to-poc-avg-cycle-time',
        					'get-rfq-to-ord-avg-cycle-time',
        					'get-req-to-poc-avg-cycle-time',
        					'get-req-to-ord-avg-cycle-time',
        					'get-req-to-qot-avg-cycle-time'
        			)
        	),
        		

        	// Competitiveness tab 
       		array(
       				Myshipserv_ReportService_Gateway::REPORT_SPR_CQS=> array(
       						'get-win-rate-when-cheapest',
       						'get-win-rate-when-fastest'
        			)
        	)
        ),
        // added by Yuriy Akopov on 2017-06-29, S20499
        // these are requests that don't actually need to be cached in memcache (as they depend on the buyer parameter)
        // they need to be run every once in a while to keep Oracle table cache warm
        
		//@todo, if we will separeate report service endpoints, (as above), we have to split this into more tabs REPORT_SPR_????
        'buyer-specific' => array(
        		array(
        		       Myshipserv_ReportService_Gateway::REPORT_SPR_QUOTING => array(
        						'response-rate-su-ignored'
          				),
        		        Myshipserv_ReportService_Gateway::REPORT_SPR_ORDERING => array(
            		        'poc-count-gmv-time'
            		    ),
        		        Myshipserv_ReportService_Gateway::REPORT_SPR_CYCLE_TIME => array(
            		        'get-req-to-qot-avg-cycle-time'
            		    )
        		)

        ),
        'supplier-specific' => array(
        		array(
        		       Myshipserv_ReportService_Gateway::REPORT_SPR_FUNNEL => array(
        					'sent-rfqs'
        				),
        		        Myshipserv_ReportService_Gateway::REPORT_SPR => array(
            		        'declined-rfq-trend'
            		    ),
        		        Myshipserv_ReportService_Gateway::REPORT_SPR_CYCLE_TIME => array(
        		            'get-req-to-qot-avg-cycle-time'
        		        )
        		    
        		)
        )
    );

    /**
	 * Singleton class entry point, create single instance
	 *
	 * @return Myshipserv_Spr_WarmupManager
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
     *
	 * @return Myshipserv_Spr_WarmupManager
	 */
	protected function __construct()
	{
	    $this->sessionId = uniqid();
	    $this->reportService = new Myshipserv_Spr_DbCachedForwarder(Myshipserv_ReportService_Gateway::getInstance(Myshipserv_ReportService_Gateway::REPORT_SPR));
	}

	/**
	 * Hide clone, protect creating another instance
     *
	 * @return Myshipserv_Spr_WarmupManager
	 */
	private function __clone()
	{
	}

    /**
     * Output given message with meta-information decorations
     *
     * @param   string  $message
     */
	public function output($message)
    {
        $now = new DateTime();
        print("[" . $this->sessionId . "][" . $now->format('H:i:s') . "] " . $message . PHP_EOL);
    }

    /**
     * Outputs CLI usage information
     */
    public function printUsageInfo()
    {
        print(PHP_EOL . "Usage:" . PHP_EOL);
        print(
            implode(
                PHP_EOL,
                array(
                    "/warmup.php {env} -> Refresh all",
                    "/warmup.php {env} {group name} -> Refresh all services in the group",
                    "/warmup.php {env} {group name} {service name} -> Refresh service in the group",
                    ""
                )
            )
        );
    }

	/**
	 * It will manage warming up, or keep caches hot for SPR report, all tabs
     *
	 * @param   array   $arguments
	 * @param   bool    $rewriteCache
     *
	 * @return Myshipserv_Spr_WarmupManager
	 */
	public function manage($arguments, $rewriteCache)
	{
	    $this->output(__METHOD__ . " started...");

        $serviceList = array();

	    switch (count($arguments)) {
            case 2:
                // execute all pre-defined service calls
                foreach (self::$warmServices as $groupName => $groupServices) {
                    foreach ($groupServices as $serviceTabs) {
                    	foreach ($serviceTabs as $serviceTabType => $serviceNames) {
                    		foreach ($serviceNames as $serviceName) {
                    			$serviceList[$serviceTabType][] = $groupName . '.' . $serviceName;
                    		}
                    	}
                    }
                }

                break;

            case 3:
                // group specified, but not a service - execute everything in the group
                $groupName = $arguments[2];

                // validate group name parameter
                if (!array_key_exists($groupName, self::$warmServices)) {
                    $this->output(
                        $groupName . " is an invalid group name, expected to be one of these: " .
                        implode(", ", array_keys(self::$warmServices))
                    );

                    $this->printUsageInfo();
                    exit;
                }

                foreach (self::$warmServices[$groupName] as $serviceTabs) {
                	foreach ($serviceTabs as $serviceTabType => $serviceNames) {
                		foreach ($serviceNames as $serviceName) {
                			$serviceList[$serviceTabType][] = $groupName . '.' . $serviceName;
                		}
                	}
                }

                break;

            case 4:
                // both the group and the service specified - execute that service in that group
                $selectedGroupName   = $arguments[2];
                $selectedServiceName = $arguments[3];

                // validate group and service name
                $tabType = null;
                $allServices = array();
                    
                foreach (self::$warmServices as $groupName => $groupServices) {
                	foreach ($groupServices as  $serviceTabs) {
                		foreach ($serviceTabs as $serviceTabType => $serviceNames) {
                    		foreach ($serviceNames as $serviceName) {
	                    		$allServices[] = $groupName . '.' . $serviceName;
	                    		if ($groupName === $selectedGroupName && $serviceName === $selectedServiceName) {
	                    			$tabType = $serviceTabType;
	                    		}
                    		}
                    	}
                    }
                }
                    
    
                if ($tabType === null) {
	            	$this->output(
	                	$groupName . " and/or " . $serviceName . " are invalid, expected to be one of these: " . PHP_EOL .
	                    implode(PHP_EOL, $allServices)
	                );
	
	                $this->printUsageInfo();
	                exit;
                }

                $serviceList[$tabType][] = $selectedGroupName. '.' . $selectedServiceName;

                break;

            default:
                $this->output("Invalid number of parameters received");
                $this->printUsageInfo();
        }

		// warming up requested services

        $warmCount = 0;
        foreach ($serviceList as $serviceTabType => $serviceTab) {
        	$this->reportService->setServiceUrl($serviceTabType);
        	foreach ($serviceTab as $serviceSignature) {
            	$serviceBits = explode('.', $serviceSignature);
            	$groupName = $serviceBits[0];
            	$serviceName = $serviceBits[1];

            	$status = $this->warmCache(
                	__FILE__,
                	$groupName,
                	$serviceName,
                	$rewriteCache
            	);

				$warmCount += $status;
			}
        }

		// if service parameter was passed, and no warming up, then possible the service name incorrect, display message for the user
		if ($warmCount === 0) {
			$this->output("Nothing warmed up... eligible service names are:");

			foreach (self::$warmServices as $groupName => $groupServices) {
				$aggregatedServiceNames = array();
				foreach ($groupServices as $serviceTabType => $serviceTabs) {
					foreach ($serviceTabs as $serviceNames) {
						$aggregatedServiceNames = array_merge($aggregatedServiceNames, $serviceNames);
					}
					$this->output("Service group " . $groupName . ": " . implode(", ", $aggregatedServiceNames));
				}
			}

			$this->printUsageInfo();
			exit;
		}

		$this->output(__METHOD__ . " finished");
	}

	/**
	 * Warming the cache for SPR services It is used by a CLI script, so that is why you can turn on ECHOING the result
	 * This function was originally repeating in the tab object(s), moved here, avoiding redundant code
     *
	 * @param   string  $scriptName
     * @param   string  $groupName
     * @param   string  $serviceName
     * @param   bool    $rewriteCache	force to rewrite cache, always read from database
     *
	 * @return  int
     * @throws  Exception
	 */
	protected function warmCache($scriptName, $groupName, $serviceName, $rewriteCache = true)
	{
		$logger = new Myshipserv_Logger_File('scripts');
		$logger->log('[<' . $scriptName. '>] [<cron>] [<start>]');

		$serviceParams = array();

		switch ($groupName) {
            case 'shipserv-average':
                // infinite timeout for most generic figures
                $memcacheTtl =  Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE;
                break;

            case 'buyer-specific':
                $memcacheTtl = Myshipserv_ReportService_Gateway::HOUR_MEMCACHE_TTL;
                // shorter timeout for more specific queries (current buyer)
                $serviceParams['byb'] = self::getSprWarmUpDefaultBuyerId();
                break;

            case 'supplier-specific':
                $serviceParams['byb']  = self::getSprWarmUpDefaultBuyerId();
                $serviceParams['tnid'] = self::getSprWarmUpDefaultSupplierId();
                // timeout is even shorter for those most specific (buyer + supplier) figures
                $memcacheTtl = Myshipserv_ReportService_Gateway::MIN5_MEMCACHE_TTL;

                break;

            default:
                throw new Exception("Unexpected SPR webservice group name " . $groupName);
        }

        $errorCount  = 0;
		$warmedCount = 0;

		/*
		 * A note by Yuriy Akopov on 2017-08-08, BUY-392:
		 *
		 * TODO: 'period' parameter usage is inconsistent betweeen different webservices in the API - sometimes it is not
		 * TODO: supplied, sometimes it is provided empty (which means monthly breakdown), sometimes it is supplied explicitly
		 * TODO: as 'month'. All those options result in the same queries but generate a different cache key
		 *
		 * TODO: In order to correct that Pages API, front ent JS and Report Service need to be refactored - in the meanwhile
		 * TODO: we will simply run all possible variations of it to cache everything (performing calculations which aren't necessary
		 */

		$sessions = array();

        // loop through available date periods
		foreach (self::$periodList as $curPeriod) {
            $sessionParams = $serviceParams;

            // add date interval values to parameters
			$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($curPeriod);
            $sessionParams['period']    = $curPeriod;
            $sessionParams['lowerdate'] = $periodList['lowerdate'];
            $sessionParams['upperdate'] = $periodList['upperdate'];

            $sessions[] = $sessionParams;
		}

		foreach ($sessions as $sessionInfo) {
            $paramPrint = array();
            foreach ($sessionInfo as $key => $value) {
                $paramPrint[] = $key . ": " . $value;
            }

            $this->output(
                "Running [" . $serviceName . "] in [" . $groupName. "] mode for [" .
                (isset($sessionInfo['period']) ? (is_null($sessionInfo['period']) ? "NULL" : $sessionInfo['period']) : "N/A") . "], " .
                "params: " . implode(", ", $paramPrint)
            );

            $result = $this->reportService->forward($serviceName, $sessionInfo, $memcacheTtl, $rewriteCache);

            if ($this->reportService->getStatus() === false) {
                $logger->log("[<" . $scriptName. ">] [" . $serviceName . "] [" . $groupName. "] [<cron>] [<error: " . str_replace("\n", "<br>", print_r($result, true)) . ">]");
                $errorCount++;
            } else {
                $warmedCount++;
            }
        }

		$logger->log("[<" . $scriptName. ">] [<cron>] [<finished " . $errorCount . " errors>]");
		return $warmedCount;
	}

    /**
     * Returns default buyer branch ID to use for Oracle table cache warming in Report Service
     *
     * @author  Yuriy Akopov
     * @date    2017-06-29
     * @story   S20499
     *
     * @return  int
     * @throws  Exception
     */
    public static function getSprWarmUpDefaultBuyerId()
    {
        $buyerBranchId = Myshipserv_Config::getIni()->shipserv->spr->warmUp->default->buyerBranchId;

        try {
            Shipserv_Buyer_Branch::getInstanceById($buyerBranchId);
        } catch (Exception $e) {
            throw new Exception("Invalid buyer branch ID " . $buyerBranchId . " configured for SPR table cache warm-up");
        }

        return $buyerBranchId;
    }

    /**
     * Returns default supplier branch ID to use for Oracle table cache warming in Report Service
     *
     * @author  Yuriy Akopov
     * @date    2017-07-03
     * @story   S20499
     *
     * @return  int
     * @throws  Exception
     */
    public static function getSprWarmUpDefaultSupplierId()
    {
        $supplierBranchId = Myshipserv_Config::getIni()->shipserv->spr->warmUp->default->supplierBranchId;

        try {
            $supplier = Shipserv_Supplier::getInstanceById($supplierBranchId);
            if (!$supplier->tnid) {
                throw new Exception("Invalid supplier ID");
            }
        } catch (Exception $e) {
            throw new Exception("Invalid supplier branch ID " . $supplierBranchId . " configured for SPR table cache warm-up");
        }

        return $supplierBranchId;
    }
}
