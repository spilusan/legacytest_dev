<?php
/*
* Gateway connecting to report service
* and aggregate, compose data for qoute tab
*/

class Myshipserv_Spr_QuoteGateway
{
	private static $_instance;
	protected $reporService;
	protected $declineReasonColors;
    
    /**
    * Singleton class entry point, create single instance
    *  
    * @return object
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
	* and sets some initial values, like report service gateway, and decline reason colours
	*/
    protected function __construct()
    {
        
        $this->reporService = new Myshipserv_Spr_DbCachedForwarder(Myshipserv_ReportService_Gateway::getInstance(Myshipserv_ReportService_Gateway::REPORT_SPR_QUOTING));
        	//TODO waiting 5 more colours from Steve
        	$this->declineReasonColors = array(
                1 => '#777777',
                2 => '#0F5BA2',
                3 => '#2980D0',
                4 => '#13B5EA',
                5 => '#90DCF7',
                6 => '#F5A623',
                7 => '#BAB8B8',
                8 => '#ED2B7C',
                9 => '#002A55',
                10 => '#FF7F04'
        	);
	}

	/**
	* Hide clone, protect creating another instance
	* @return Myshipserv_Spr_QuoteGateway
	*/
    private function __clone()
    {
    }

    /**
     * Get the actual report data
     *
     * @param   string $reportType
     * @param   array   $params
     *
     * @return array|null
     */
    public function getReport($reportType, $params)
    {
    	switch ($reportType) {
    		case 'transactions':
    			return $this->getChartDataForTransactions($params);
    			break;
    		case 'response-rate':
    			return $this->getChartDataForResponseRate($params);
    			break;
    		case 'response-rate-trend':
    			return $this->getChartDataForResponseRateTrend($params);
    			break;
    		case 'response-time':
    			return $this->getChartDataResponseTime($params);
    			break;
    		case 'response-time-trend':
    			return $this->getChartDataResponseTimeTrend($params);
    			break;
    		case 'quote-completeness':
    			return $this->getChartQuoteCompleteness($params);
    			break;
    		case 'quote-completeness-trend':
    			return $this->getChartDataQuoteCompletenessTrend($params);
    			break;
    		case 'quote-variance':
    			return $this->getChartQuoteVariance($params);
    			break;
    		case 'quote-variance-trend':
    			return $this->getChartDataQuoteVarianceTrend($params);
    			break;
    		case 'decline-reason':
    			return $this->getChartDataDeclineReason($params);
    			break;
    		case 'decline-reason-su':
    			return $this->getChartDataDeclineReasonSu($params);
    			break;
    		case 'decline-reason-all':
    			return $this->getChartDataDeclineReasonAll($params);
    			break;
    		case 'rfq-and-quote-summary':
    			return $this->getRfqAndQuoteSummary($params);
    			break;
    		default:
    			return null;
    			break;
    	}
    }
    
    /**
     * Get the report data for the SPR Transaction Chart
     *
     * @param   array   $params
     *
     * @return  array
     */
    protected function getChartDataForTransactions($params)
    {
    	$reportServiceRequests = array(
            'rfq-trend'                  => 'RFQs sent',
            'quotes-received-trend'      => 'Quotes received',
            'declined-rfq-trend'         => 'RFQs declined',
            'ignored-shipserv-rfq-trend' => 'RFQs not responded to via ShipServ',  // changed by Yuriy Akopov on 2017-08-04, BUY-392
            'pending-rfq-trend'          => 'RFQs pending'
    	);
    	
    	return $this->getCustomChart($params, $reportServiceRequests);
    }
  
    /**
     * Get the report data for the SPR Response Rate
     *
     * @param   array   $params
     *
     * @return  array
     */
    protected function getChartDataForResponseRate($params)
    {
    	$data = array();
    	$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
    	
    	// fetch all data from micro services, 'All supplier with you'
    	$serviceParams = array(
            'byb'       => $params['byb'],
            'period'    => $params['period'],
            'lowerdate' => $periodList['lowerdate'],
            'upperdate' => $periodList['upperdate']
        );
    	
    	$replyQuoted    = $this->reporService->forward('response-rate-su-quoted', $serviceParams);
    	$replyIgnored   = $this->reporService->forward('response-rate-su-ignored', $serviceParams);
    	$replyDeclined  = $this->reporService->forward('response-rate-su-declined', $serviceParams);
    	$replyPending   = $this->reporService->forward('response-rate-su-pending', $serviceParams);
    	
    	// fetch all data from micro services, 'ShipServ average'
    	unset($serviceParams['byb']);

    	// this is expected to be pre-cached, otherwise we're in trouble
    	$replyAllQuoted     = $this->reporService->forward('response-rate-all-quoted', $serviceParams, Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE);
    	$replyAllIgnored    = $this->reporService->forward('response-rate-all-ignored', $serviceParams, Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE);
    	$replyAllDeclined   = $this->reporService->forward('response-rate-all-declined', $serviceParams, Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE);
    	$replyAllPending    = $this->reporService->forward('response-rate-all-pending', $serviceParams, Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE);
    	
    	// get trend supplier with you, form the existing call
    	$trendSu = $this->getChartDataForResponseRateTrend($params);
    	
		//Summarize data from different detailed format
		//Here we have to be careful, if the structure or name of the fields are changing, this code part must be refactored
		$trendSuList = array();
    	foreach ($trendSu['data'] as $trend) {
    		$sum = 0;
    		foreach ($trend['data'] as $value) {
    			$sum += (int) $value;
    		}

    		$trendSuList[$trend['name']] = $sum;
    	}

    	//Generating the actual report data
    	$data[] = array(
            'name' => 'Quoted',
            'data' => array(
                (int) $trendSuList['Quoted'],
                (int) $replyQuoted['spb-count'],
                (int) $replyAllQuoted['spb-count']
            )
    	);
    	
    	$data[] = array(
            'name' => 'Ignored',
            'data' => array(
                (int) $trendSuList['Ignored'],
                (int) $replyIgnored['spb-count'],
                (int) $replyAllIgnored['spb-count']
            )
    	);
    	
    	$data[] = array(
            'name' => 'Declined',
            'data' => array(
                (int) $trendSuList['Declined'],
                (int) $replyDeclined['spb-count'],
                (int) $replyAllDeclined['spb-count']
            )
    	);
    	
    	$data[] = array(
            'name' => 'Pending',
            'data' => array(
                (int) $trendSuList['Pending'],
                (int) $replyPending['spb-count'],
                (int) $replyAllPending['spb-count']
            )
    	);
    	
    	return array(
    			'data' => $data
    	);
    }
    
    /**
     * Get the report data for the SPR Trend
     *
     * @param   array   $params
     *
     * @return  array
     */
    protected function getChartDataForResponseRateTrend($params)
    {
    	$reportServiceRequests = array(
            'quotes-received-trend' => 'Quoted',
            'ignored-shipserv-rfq-trend'     => 'Ignored',
            'declined-rfq-trend'    => 'Declined',
            'pending-rfq-trend'     => 'Pending'
    	);
    
    	return $this->getCustomChart($params, $reportServiceRequests);
    }
    
    /**
     * Get the report data for a list of requested
     * @param array $params
     * @param array $reportServiceRequests
     * @return array
     */
    protected function getCustomChart($params, $reportServiceRequests)
    {
    	$data = array();
    	$reportData = array();
    	$reportValues = array();
    	
    	$reportParams = $params;
    	$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);

    	$reportParams['lowerdate'] = $periodList['lowerdate'];
    	$reportParams['upperdate'] = $periodList['upperdate'];
    	
    	foreach ($reportServiceRequests as $reportType => $reportTitle) {
    		unset($reportData); 
    		unset($reportValues);

    		$reportData = $periodList['periodlist']; 
    		$reportValues = array();
    		$reply  = $this->reporService->forwardAs(Myshipserv_ReportService_Gateway::REPORT_SPR, $reportType, $reportParams);
    		
    		$reportValues = Myshipserv_Spr_PeriodManager::getSlicedData($reportData, $reply, 'spb-count');

    		$data[] = array(
    			'name' => $reportTitle,
    			'data' => $reportValues
    		);
    	}
    	
    	return array(
            'axis' => Myshipserv_Spr_PeriodManager::getPeriodKeys($periodList['periodlist'], $params['period']),
            'data' => $data
        );
    }
    
    /**
     * Returns the actual data for the response time  chart
     *
     * @param   array   $params
     *
     * @return  array
     */
    protected function getChartDataResponseTime($params)
    {
    	$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
    	
    	//Fetch all data from micro services
    	$replySu = $this->reporService->forward(
    		'get-avg-response-time-summary',
    		array(
	    		'lowerdate' => $periodList['lowerdate'],
	    		'upperdate' => $periodList['upperdate'],
	    		'tnid'      => $params['tnid'],
	    		'byb'       => $params['byb']
	    	)
    	);
    	
    	$replyAsu = $this->reporService->forward(
    		'get-avg-response-time-summary',
    		array(
    			'lowerdate' => $periodList['lowerdate'],
    			'upperdate' => $periodList['upperdate'],
    			'byb'       => $params['byb']
    		)
    	);
    	
    	$replyAll = $this->reporService->forward(
    		'get-avg-response-time-summary',
    		array(
	    		'lowerdate' => $periodList['lowerdate'],
	    		'upperdate' => $periodList['upperdate']
    		),
    	    Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE
    	);
    	
    	$data = array();
    	
		$average = $replySu['average'] ?? '';
    	$data[] = array(
            'name' => 'Response time (hours)',
            'data' => [
                ["This supplier<br>with you", $average],
                ["All suppliers<br>with you", $average],
                ["ShipServ<br>average", $average]
            ]
    	);
    	
    	return array(
            'data' => $data
    	);
    }
    
    /**
     * Returns the actual data for the response time trend chart
     *
     * @param   array   $params
     *
     * @return  array
     */
    protected function getChartDataResponseTimeTrend($params)
    {
    	$data = array();
    	 
    	$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
    	$reportParams = array(
            'period'    => $params['period'],
            'tnid'      => $params['tnid'],
            'byb'       => $params['byb'],
            'lowerdate' => $periodList['lowerdate'],
            'upperdate' => $periodList['upperdate']
    	);
    	 
    	$reportData = $periodList['periodlist'];
    	$reportValues = array();
    	$reply  = $this->reporService->forward('avg-response-time-su', $reportParams);
    	 
    	$reportValues = Myshipserv_Spr_PeriodManager::getSlicedData($reportData, $reply, 'average');
    	 
    	$data[] = array(
            'name' => 'Response time (hours)',
            'data' => $reportValues
    	);
    	 
    	return array(
            'axis' => Myshipserv_Spr_PeriodManager::getPeriodKeys($periodList['periodlist'], $params['period']),
            'data' => $data
    	);
    }
    
    /**
     * Returns the actual data for the response time chart
     *
     * @param   array   $params
     *
     * @return  array
     */
    protected function getChartQuoteCompleteness($params)
    {
    	 
    	$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);

    	// fetch all data from micro services
    	$reply = $this->reporService->forward(
    		'quote-completeness',
    		array(
	    		'period'    => $params['period'],
	    		'lowerdate' => $periodList['lowerdate'],
	    		'upperdate' => $periodList['upperdate'],
	    		'tnid'      => $params['tnid'],
	    		'byb'       => $params['byb']
    		)
    	);
    	 
    	$replySu = $this->reporService->forward(
    		'quote-completeness',
    		array(
	    		'period'    => $params['period'],
	    		'lowerdate' => $periodList['lowerdate'],
	    		'upperdate' => $periodList['upperdate'],
	    		'byb'       => $params['byb']
    		)
    	);
    	 
    	$replyAll = $this->reporService->forward(
    		'quote-completeness',
    		array(
	    		'period'    => $params['period'],
	    		'lowerdate' => $periodList['lowerdate'],
	    		'upperdate' => $periodList['upperdate']
    		),
    	    Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE
    	);
    
    	// summarize all data
    	$sum = 0;
    	foreach ($reply as $rec) {
    		$sum += $rec['average'];
    	}
    	 
    	$sumSu = 0;
    	foreach ($replySu as $rec) {
    		$sumSu += $rec['average'];
    	}
    	 
    	$sumAll = 0;
    	foreach ($replyAll as $rec) {
    		$sumAll += $rec['average'];
    	}

        $percentSwu = (count($reply) > 0) ? round($sum / count($reply) * 100, 2) : 0;
        $percentAsu =  (count($replySu) > 0) ? round($sumSu / count($replySu) * 100, 2) : 0;
        $percentAll = (count($replyAll) > 0) ? round($sumAll / count($replyAll) * 100, 2) : 0;

    	$data = array();
    	$data[] = array(
            'name' => 'Quotes complete',
            'data' => [
                ["This supplier<br>with you", $percentSwu],
                ["All suppliers<br>with you", $percentAsu],
                ["ShipServ<br>average", $percentAll]
            ]
        );
    	 
    	return array(
            'data' => $data
    	);
    }

    /**
     * Returns the actual data for the response time trend chart
     *
     * @param   array   $params
     *
     * @return  array
     */
    protected function getChartDataQuoteCompletenessTrend($params)
    {
    	$data = array();
    	 
    	$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
    	$reportParams = array(
    			'period'    => $params['period'],
    			'tnid'      => $params['tnid'],
    			'byb'       => $params['byb'],
    			'lowerdate' => $periodList['lowerdate'],
    			'upperdate' => $periodList['upperdate']
    	);
    	 
    	$reportData = $periodList['periodlist'];
    	$reportValues = array();
    	$reply  = $this->reporService->forward('quote-completeness', $reportParams);
    	 
    	$reportValues = Myshipserv_Spr_PeriodManager::getSlicedData(
    		$reportData,
    		$reply,
    		'average',
    		function ($data) {
    			return round($data * 100, 2);
    		}
    	);

    	$data[] = array(
            'name' => 'Quotes complete',
            'data' => $reportValues
    	);
    	 
    	return array(
            'axis' => Myshipserv_Spr_PeriodManager::getPeriodKeys($periodList['periodlist'], $params['period']),
            'data' => $data
    	);
    }
    
    /**
     * Returns the actual data for quote variance
     *
     * @param   array   $params
     *
     * @return  array
     */
    protected function getChartQuoteVariance($params)
    {
    
    	$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);

    	//Fetch all data from micro services
    	$reply = $this->reporService->forward(
    		'quote-variance',
    		array(
                'period'    => $params['period'],
                'lowerdate' => $periodList['lowerdate'],
                'upperdate' => $periodList['upperdate'],
                'tnid'      => $params['tnid'],
                'byb'       => $params['byb']
            )
    	);
    
    	$replySu = $this->reporService->forward(
    		'quote-variance',
    		array(
                'period'    => $params['period'],
                'lowerdate' => $periodList['lowerdate'],
                'upperdate' => $periodList['upperdate'],
                'byb'       => $params['byb']
            )
        );
    
    	$replyAll = $this->reporService->forward(
    		'quote-variance',
    		array(
                'period'    => $params['period'],
                'lowerdate' => $periodList['lowerdate'],
                'upperdate' => $periodList['upperdate']
            ),
    	    Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE
    	);
    
    	// summarise all data
    	$sum = 0;
    	foreach ($reply as $rec) {
    		$sum += $rec['percentage'];
    	}
    
    	$sumSu = 0;
    	foreach ($replySu as $rec) {
    		$sumSu += $rec['percentage'];
    	}
    
    	$sumAll = 0;
    	foreach ($replyAll as $rec) {
    		$sumAll += $rec['percentage'];
    	}

        $percentSwu = (count($reply) > 0) ? round($sum / count($reply), 2) : 0;
        $percentAsu =  (count($replySu) > 0) ? round($sumSu / count($replySu), 2) : 0;
        $percentAll = (count($replyAll) > 0) ? round($sumAll / count($replyAll), 2) : 0;

    	$data = array();
    	$data[] = array(
            'name' => 'Quotes exactly as RFQ',
            'data' => [
                ["This supplier<br>with you", $percentSwu],
                ["All suppliers<br>with you", $percentAsu],
                ["ShipServ<br>average", $percentAll]
            ]
    	);
    
    	return array(
            'data' => $data
    	);
    }
    
    /**
     * Returns the actual quote variance trend
     *
     * @param   array   $params
     *
     * @return  array
     */
    protected function getChartDataQuoteVarianceTrend($params)
    {
    	$data = array();
    
    	$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
    	$reportParams = array(
            'period'    => $params['period'],
            'tnid'      => $params['tnid'],
            'byb'       => $params['byb'],
            'lowerdate' => $periodList['lowerdate'],
            'upperdate' => $periodList['upperdate']
    	);
    
    	$reportData   = $periodList['periodlist'];
    	$reportValues = array();

    	$reply = $this->reporService->forward('quote-variance', $reportParams);
    
    	$reportValues = Myshipserv_Spr_PeriodManager::getSlicedData(
    		$reportData,
    		$reply,
    		'percentage',
    		function ($data) {
    			return round($data, 2);
    		}
    	);
    	
    	$data[] = array(
            'name' => 'Quotes exactly as RFQ',
            'data' => $reportValues
    	);
    
    	return array(
            'axis' => Myshipserv_Spr_PeriodManager::getPeriodKeys($periodList['periodlist'], $params['period']),
            'data' => $data
    	);
    }
    
    /**
     * Return Decline Reasons Selected Suppliers with you
     *
     * @param   array   $params
     *
     * @return  array
     */
    protected function getChartDataDeclineReason($params)
    {
    	$data    = array();
    	$reasons = array();
    	$colors  = array();
    
    	$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
    	
    	$reply = $this->reporService->forward(
    		'decline-reason',
    		array(
                'tnid'      => $params['tnid'],
                'byb'       => $params['byb'],
                'lowerdate' => $periodList['lowerdate'],
                'upperdate' => $periodList['upperdate']
            )
    	);
    	 
    	foreach ($reply as $rec) {
    		$reasons[] = array($rec['reason'], $rec['decline-count']);
    		$colors[]  = $this->declineReasonColor($rec['reason-id']);
    	}
    	 
    	$data[] = array(
            'name'   => 'Decline Reason',
            'data'   => $reasons,
            'colors' => $colors
    	);
    	 
    	return array(
            'data' => $data
    	);
    }
    
    /**
     * Return Decline Reasons All suppliers with you
     *
     * @param   array   $params
     *
     * @return  array
     */
    protected function getChartDataDeclineReasonSu($params)
    {
    	$data    = array();
    	$reasons = array();
    	$colors  = array();
    
    	$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
    	
    	$reply = $this->reporService->forward(
    		'decline-reason',
    		array(
    			'byb'       => $params['byb'],
    			'lowerdate' => $periodList['lowerdate'],
    			'upperdate' => $periodList['upperdate']
            )
    	);
    	 
    	foreach ($reply as $rec) {
    		$reasons[] = array($rec['reason'], $rec['decline-count']);
    		$colors[]  = $this->declineReasonColor($rec['reason-id']);
    	}
    	 
    	$data[] = array(
            'name'   => 'Decline Reason',
            'data'   => $reasons,
            'colors' => $colors
    	);
    	 
    	return array(
            'data' => $data
    	);
    }

    /**
     * Return Decline Reasons Shipserv global data as Trend
     *
     * @param   array   $params
     * @return  array
     */
    protected function getChartDataDeclineReasonAll($params)
    {
    	$data    = array();
    	$reasons = array();
    	$colors  = array();
    	 
    	$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
    	
    	$reply = $this->reporService->forward(
    		'decline-reason',
    		array(
     			'lowerdate' => $periodList['lowerdate'],
    			'upperdate' => $periodList['upperdate'],
    		),
    	    Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE
    	);
    	
    	foreach ($reply as $rec) {
    		$reasons[] = array($rec['reason'], $rec['decline-count']);
    		$colors[]  = $this->declineReasonColor($rec['reason-id']);
       	}
    	
    	$data[] = array(
            'name'   => 'Decline Reason',
            'data'   => $reasons,
            'colors' => $colors
    	);
    	
    	return array(
            'data' => $data
    	);
    }
    
    /**
     * Get quote summary
     *
     * @param   array   $params
     * @return  array
     */
    protected function getRfqAndQuoteSummary($params)
    {
    	
    	$data = array();
    	$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
    	
    	// fetch all data from micro services. All supplier with you
    	$serviceParams = array(
            'tnid' => $params['tnid'],
            'byb' => $params['byb'],
            'lowerdate' => $periodList['lowerdate'],
            'upperdate' => $periodList['upperdate']
    	);
    	
    	$reportServiceRequests = array(
    	    'sent-rfqs' => Myshipserv_ReportService_Gateway::REPORT_SPR_FUNNEL,
    	    'pending-rfqs' => Myshipserv_ReportService_Gateway::REPORT_SPR_FUNNEL,
    	    'ignored-shipserv-rfqs' => Myshipserv_ReportService_Gateway::REPORT_SPR,    // changed by Yuriy Akopov on 2017-08-04, BUY-392
    	    'declined-rfqs' => Myshipserv_ReportService_Gateway::REPORT_SPR_FUNNEL,
    	    'quotes-received' => Myshipserv_ReportService_Gateway::REPORT_SPR_FUNNEL
     	);
    	
    	foreach ($reportServiceRequests as $reportType => $facadeType) {
    	    $reply = $this->reporService->forwardAs($facadeType, $reportType, $serviceParams);
    		$data[$reportType] = $reply;
    	}

    	return array(
            'sentRfqs'       => $data['sent-rfqs']['spb-count'],
            'quotesReceived' =>  $data['quotes-received']['spb-count'],
            'declinedRfqs'   => $data['declined-rfqs']['spb-count'],
            'ignoredRfqs'    => $data['ignored-shipserv-rfqs']['spb-count'],
            'pendingRfqs'    => $data['pending-rfqs']['spb-count']
    	);
    }
    
    /**
     * Returns the decline reason colour, for the case, we add a new reason, and the color is not added here, if the colour does not exist, it will generate one 
     * for the ID, which still be consequent
     *
     * @param   int     $reasonId
     * @return  string
     */
    protected function declineReasonColor($reasonId)
    {
    	if (array_key_exists((int)$reasonId, $this->declineReasonColors)) {
    		return $this->declineReasonColors[(int)$reasonId];
    	} else {
    		return '#' . substr(md5($reasonId), 0, 6);
    	}
    }
    
    /**
     * Get the status of the last sent request to report service,
     * successful or not
     *
     * @return bool
     */
    public function getStatus()
    {
    	return $this->reporService->getStatus();
    }
}
