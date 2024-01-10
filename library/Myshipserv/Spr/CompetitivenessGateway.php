<?php
/*
 * Gateway connecting to report service, ordering tab
 * and aggregate, compose data for qoute tab
 */

class Myshipserv_Spr_CompetitivenessGateway
{
	private static $_instance;
	protected $reporService;
	const SPR_REC_SUPPLIER_CNT = 3;
	
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
	 * @return object
	 */
	protected function __construct()
	{
	    $this->reporService = new Myshipserv_Spr_DbCachedForwarder(Myshipserv_ReportService_Gateway::getInstance(Myshipserv_ReportService_Gateway::REPORT_SPR_CQS));
	}
	
	/**
	 * Hide clone, protect createing another instance
	 * @return unknown
	 */
	private function __clone()
	{
	}
	
	/**
	 * Get the actual report data
	 * @param string $reportType
	 * @param array $params
	 * @return array or null in case of missing report
	 */
	public function getReport($reportType, $params)
	{
		switch ($reportType) {
			case 'competitive-quote-situations':
				return $this->getCompetitiveQuoteSituations($params);
				break;
			case 'quote-situation-trend':
				return $this->getQuoteSituationTrend($params);
				break;
			case 'price-sensitivity':
				return $this->getPriceSensitivity($params);
				break;
			case 'time-sensitivity':
				return $this->getTimeSensitivity($params);
				break;
			case 'co-quoters':
				return $this->getCoQuoters($params);
				break;
			case 'table-co-quoters':
				return $this->getTableCoQuoters($params);
				break;
			case 'alternative-suppliers':
				return $this->getAlternativeSuppliers($params);
				break;
			default:
				return null;
				break;
		}
	}

	/**
	 * Get the quote situation values
	 * @param array $params
	 * @return array
	 */
	protected function getCompetitiveQuoteSituations(array $params)
	{
		//Report service provides a separate endpoint for all requests, so looping through
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);

		$services = array(
			'get-cqs-count' => null,
			'get-cheapest-pct' => null,
			'get-fastest-pct' => null,
			'get-avg-pct-cheaper' => null,
			'get-avg-pct-more-expensive' => null,
		);
		
		$this->runListOfServices(
			$services,
			array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
			)
		);
		
		$data = array(
				'competativeQuoteSituations' => $services['get-cqs-count']['cqs-count'],
				'howOftenCheapest' => $services['get-cheapest-pct']['cheapest-pct'],
				'howOftenFastest' =>  $services['get-fastest-pct']['fastest-pct'],
				'averagePerCheaper' => $services['get-avg-pct-cheaper']['avg-pct-cheaper'],
				'averageMoreExpensive' => $services['get-avg-pct-more-expensive']['avg-pct-more-expensive']
		);
		
		return array(
				'data' => $data
		);
	}
	
	/**
	 * Get the quote situation trend
	 * @param array $params
	 * @return array
	 */
	protected function getQuoteSituationTrend(array $params)
	{
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$services = array(
				'get-trend-cqs-count' => null,
				'get-trend-cqs-won-count' => null,
				'get-trend-cqs-cheapest-count' => null,
				'get-trend-cqs-fastest-count' => null
		);

		$this->runListOfServices(
			$services,
			array(
			        'period' => $params['period'],
					'tnid' => $params['tnid'],
					'byb' => $params['byb'],
					'lowerdate' => $periodList['lowerdate'],
					'upperdate' => $periodList['upperdate'],
			)
		);
		
		$data = array(
					array(
							'name' => 'Number of CQS',
							'data' => Myshipserv_Spr_PeriodManager::getSlicedData($periodList['periodlist'], $services['get-trend-cqs-count'], 'count')
					),
					array(
							'name' => 'Number of CQS where this supplier won',
							'data' => Myshipserv_Spr_PeriodManager::getSlicedData($periodList['periodlist'], $services['get-trend-cqs-won-count'], 'count')
					),
					array(
							'name' => 'Number of CQS where this supplier was cheapest',
							'data' => Myshipserv_Spr_PeriodManager::getSlicedData($periodList['periodlist'], $services['get-trend-cqs-cheapest-count'], 'count')
					),
					array(
							'name' => 'Number of CQS where this supplier was fastest',
							'data' => Myshipserv_Spr_PeriodManager::getSlicedData($periodList['periodlist'], $services['get-trend-cqs-fastest-count'], 'count')
					),
			);
		
		return array(
				'axis' => Myshipserv_Spr_PeriodManager::getPeriodKeys($periodList['periodlist'], $params['period']),
				'data' => $data
				
		);
	}
	
	/**
	 * Price sensitivity
	 * Win rate when cheapest
	 * @param array $params
	 * @return array
	 */	
	protected function getPriceSensitivity(array $params)
	{
		return $this->getReportGroups($params, 'get-win-rate-when-cheapest', 'pct-win-rate-cheapest');
	}
	
	/**
	 * Time sensitivity
	 * * Win rate when fastest
	 * @param array $params
	 * @return array
	 */
	protected function getTimeSensitivity(array $params)
	{
		return $this->getReportGroups($params, 'get-win-rate-when-fastest', 'pct-win-rate-fastest');
	}
	
	/**
	 * Returns with a structured array of a report combinint the variations possible
	 * (This user w/y. All supplier w/w, Shipserv global)
	 * @param array $params
	 * @param string $serviceName
	 * @param string $resultFieldName
	 * @return array
	 */
	protected function getReportGroups(array $params, $serviceName, $resultFieldName)
	{
		
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$paramList = array(
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
				'byb' => $params['byb'],
				'tnid' => $params['tnid']
		);

		$reply = $this->reporService->forward($serviceName, $paramList);
		$replySu = $this->reporService->forward($serviceName, array_slice($paramList, 0, 3));
		$replyAll = $this->reporService->forward($serviceName, array_slice($paramList, 0, 2), Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE);
		
		$data = array(
				'name' => 'Average lead time',
				'data' => array(
						array(
								"This supplier<br>with you",
								$reply[$resultFieldName]
						),
						array(
								"All suppliers<br>with you",
								$replySu[$resultFieldName]
						),
						array(
								"ShipServ average",
								$replyAll[$resultFieldName]
						)
				)
		);
		
		return array(
				'data' => array($data),
		);
		
	}
	
	/**
	 * Return CO quoters
	 * @param array $params
	 * @return array
	 */
	protected function getCoQuoters(array $params)
	{
		
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$reply = $this->reporService->forward(
			'get-co-quoters-pct',
			array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
			)
		);
		
		$numbers = array('zero', 'one', 'two', 'three', 'four');
		
		foreach (array_keys($reply) as $key) {
			$number = (int)$reply[$key]['group-count'];
			if ($reply[$key]['group-count'] === 'Solely') {
				$reply[$key]['group-name'] = 'Solely';
			} else if ($number >= 5) {
				$reply[$key]['group-name'] = '% where there were five or more other suppliers asked to quote';
			}else if($number == 1) {
				$reply[$key]['group-name'] = '% where there was one other supplier asked to quote';
			} else {
				$reply[$key]['group-name'] = '% where there were ' . $numbers[$number] . ' other suppliers asked to quote';
			}
		}
		
		return array(
				'data' => $reply
		);
		
	}
	
	/**
	 * Return CO quoters table
	 * @param array $params
	 * @return array
	 */
	protected function getTableCoQuoters(array $params)
	{
		
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$reply = $this->reporService->forward(
			'get-common-co-quoters',
			array(
					'tnid' => $params['tnid'],
					'byb' => $params['byb'],
					'lowerdate' => $periodList['lowerdate'],
					'upperdate' => $periodList['upperdate'],
                    'pageno' => $params['page'],
                    'pagesize' => $params['pagesize']
			)
		);
		
		Myshipserv_Spr_Anonymize::anonimizeData($reply, array('spb-name' => 'Supplier {X}'));
		
		return array(
				'data' => $reply
		);
		
	}
	
	/**
	 * Fill the empty service list array with the result of report-service call
	 * @param array $services
	 * @param array $params
	 * updata the original array as a result 
	 */
	protected function runListOfServices(array &$services, array $params)
	{
		foreach (array_keys($services) as $serviceKey) {
			$services[$serviceKey] = $this->reporService->forward(
				$serviceKey,
				$params
			);
		}
	}
	
	/**
	 * Reuturn "SPR_REC_SUPPLIER_CNT" Alternative suppliers 
	 * @param array $params
	 * @return array
	 */
	protected function getAlternativeSuppliers($params)
	{
		$tnid = explode(',', $params['tnid']);
		$db = Shipserv_Helper_Database::getDb();
		
		$competitorList = new Shipserv_Supplier_Competitorlist((int)$tnid[0], $db);

		$objArray = array();
		foreach ($competitorList->fetchOrdered(self::SPR_REC_SUPPLIER_CNT) as $tnid) {
			$objArray[] = Shipserv_Supplier::fetch($tnid, $db);
		}
		
		return (array) $objArray;
	}
	
	/**
	 * Get the status of the last sent reqest to report service,
	 * successfull or not,
	 * @return bool
	 */
	public function getStatus()
	{
		//@todo remove this when report service calls are implemented
		return true;
		//return $this->reporService->getStatus();
	}
	
}
