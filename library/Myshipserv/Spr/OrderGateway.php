<?php
/*
* Gateway connecting to report service, ordering tab
* and aggregate, compose data for qoute tab
*/

class Myshipserv_Spr_OrderGateway
{
	private static $_instance;
	protected $reporService;
	
    /**
    * Singleton class entry point, create single instance
    *  
    * @return Myshipserv_Spr_OrderGateway
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
	* @return Myshipserv_Spr_OrderGateway
	*/
    protected function __construct()
    {
        $this->reporService = new Myshipserv_Spr_DbCachedForwarder(Myshipserv_ReportService_Gateway::getInstance(Myshipserv_ReportService_Gateway::REPORT_SPR_ORDERING));
	}

	/**
	* Hide clone, protect createing another instance
	* @return Myshipserv_Spr_OrderGateway
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
    		case 'total-spend':
    			return $this->getTotalSpend($params);
    			break;
    		case 'spend':
    			return $this->getSpend($params);
    			break;
       		case 'total-average-order-value':
    			return $this->getTotalAverageOrderValue($params);
    			break;
       		case 'average-order-value':
       			return $this->getAverageOrderValue($params);
       			break;
       		case 'total-number-of-orders':
       			return $this->getTotalNumberOfOrders($params);
       			break;
       		case 'number-of-orders':
       			return $this->getNumberOfOrders($params);
       			break;
       		case 'total-orders-confirmed':
       			return $this->getTotalOrdersConfirmed($params);
       			break;
       		case 'orders-confirmed':
       			return $this->getOrdersConfirmed($params);
       			break;
       		case 'total-avg-time-to-confirm-order':
       			return $this->getTotalAvgTimeToConfirmOrder($params);
       			break;
       		case 'avg-time-to-confirm-order':
       			return $this->getAvgTimeToConfirmOrder($params);
       			break;
       		//Total spend by vessel type can use the same
       		case 'spend-by-vessel-type':
       			return $this->getSpendByVesselType($params);
       			break;
       		case 'common-items':
       			return $this->getCommonItems($params);
       			break;
       		case 'spend-by-vessel-items':
       			return $this->getSpendByVesselItems($params);
       			break;
       		case 'spend-by-purchaser-items':
       			return $this->getSpendByPurchaserItems($params);
       			break;
    		default:
    			return null;
    			break;
    	}
    }

    /**
     * Total spend
     * @param array $params
     * @return array
     */
    protected function getTotalSpend($params)
	{
    	
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$params = array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				// 'period' => $params['period'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
		);

        // changed by Yuriy Akopov on 2017-07-11, S20557: using dedicated Total query instead of summing the trend
        // @todo: trends are now supposed to add up, so it isn't strictly necessary to calculate total separately
        // @todo: but for now we need to see if discrepancies arise between the two
		// $directSpendreply = $this->reporService->forward('direct-order-count-gmv', $params);
		// $directSpend = $this->dataSum($directSpendreply, 'ord-total-cost-discounted-usd');
		// $compSpendreply = $this->reporService->forward('competitive-order-count-gmv', $params);
		// $comSpend = $this->dataSum($compSpendreply, 'ord-total-cost-discounted-usd');

        $directSpendreply = $this->reporService->forward('direct-order-count-gmv-total', $params);
        $compSpendreply = $this->reporService->forward('competitive-order-count-gmv-total', $params);

		$data = array(
            'direct' => $directSpendreply[0]['ord-total-cost-discounted-usd'],
            'competitive' => $compSpendreply[0]['ord-total-cost-discounted-usd'],
        );

		$data['total'] = $data['direct'] + $data['competitive'];
	
		return $data;
	}
    
	/**
	 * Spend graph
	 * @param array $params
	 * @return array
	 */
	protected function getSpend($params)
	{
	
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
				
		$params = array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'period' => $params['period'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
		);
		
		$directSpendreply = $this->reporService->forward('direct-order-count-gmv', $params);
		$directSpend = $this->periodize($periodList['periodlist'], $directSpendreply, 'slice', 'ord-total-cost-discounted-usd');
		
		$compSpendreply = $this->reporService->forward('competitive-order-count-gmv', $params);
		$comSpend = $this->periodize($periodList['periodlist'], $compSpendreply, 'slice', 'ord-total-cost-discounted-usd');
		
		$totalSpend = array();

		foreach ($directSpend as $key => $value) {
			$totalSpend[] = $value + $comSpend[$key];
		}
		
		$data = array(
					array(
						'name' => 'Total spend',
						'data' => $totalSpend
					),
						
					array(
						'name' => 'Direct spend',
						'data' => $directSpend 
					),
					array(
						'name' => 'Competitive spend',
						'data' => $comSpend
					)
				);
		
		return array(
				'axis' => Myshipserv_Spr_PeriodManager::getPeriodKeys($periodList['periodlist'], $params['period']),
				'data' => $data
		
		);
	}
	
	
	/**
	 * Total average order value
	 * @param array $params
	 * @return array
	 */
	protected function getTotalAverageOrderValue($params)
	{
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$params = array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
		);

        // changed by Yuriy Akopov on 2017-07-11, S20557: using dedicated Total query instead of summing the trend
		$directSpendreply = $this->reporService->forward('direct-order-count-gmv-total', $params);

		$dSum = (float) $directSpendreply[0]['ord-total-cost-discounted-usd'];
		$dCount = (int) $directSpendreply[0]['ord-count'];
		$directSpend = (float) $directSpendreply[0]['ord-average-cost']; // ($dCount > 0) ? $dSum / $dCount : 0;

		$compSpendreply = $this->reporService->forward('competitive-order-count-gmv-total', $params);

		$cSum = (float) $compSpendreply[0]['ord-total-cost-discounted-usd'];
		$cCount = (int) $compSpendreply[0]['ord-count'];
        $comSpend = (float) $compSpendreply[0]['ord-average-cost']; // ($cCount > 0) ? $cSum / $cCount: 0;

		$totalSpend = (($dCount + $cCount) > 0) ? ($dSum + $cSum) / ($dCount + $cCount): 0;
		 
		$data = array(
				'direct' => $directSpend,
				'competitive' => $comSpend,
				'total' => round($totalSpend, 2)
		);
		
		return $data;
	}
	
	/**
	 * average order value graph
	 * @param array $params
	 * @return array
	 */
	protected function getAverageOrderValue($params)
	{
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$params = array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'period' => $params['period'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
		);

		// @todo: here we don't need to calculate averages, they're provided directly from the query similarly to *-total version above

		$directSpendreply = $this->reporService->forward('direct-order-count-gmv', $params);
		$directSpend = $this->periodize($periodList['periodlist'], $directSpendreply, 'slice', 'ord-total-cost-discounted-usd');
		$directCount = $this->periodize($periodList['periodlist'], $directSpendreply, 'slice', 'ord-count');
		
		$compSpendreply = $this->reporService->forward('competitive-order-count-gmv', $params);
		$comSpend = $this->periodize($periodList['periodlist'], $compSpendreply, 'slice', 'ord-total-cost-discounted-usd');
		$comCount = $this->periodize($periodList['periodlist'], $compSpendreply, 'slice', 'ord-count');
		
		$directAvgSpend = array();
		$comAvgSpend = array();
		$totalAvgSpend = array();
		
		foreach (array_keys($directSpend) as $key) {
			$directAvgSpend[] = round(($directCount[$key] > 0) ? $directSpend[$key] / $directCount[$key] : 0, 2);
			$comAvgSpend[] = round(($comCount[$key] > 0) ? $comSpend[$key] / $comCount[$key] : 0, 2);
			$totalAvgSpend[] = round(($directCount[$key] + $comCount[$key] > 0) ? ($directSpend[$key] + $comSpend[$key]) / ($directCount[$key] + $comCount[$key]) : 0, 2);
		}
		
		$data = array(
				array(
						'name' => 'Total order value',
						'data' => $totalAvgSpend
				),
				
				array(
						'name' => 'Direct order value',
						'data' => $directAvgSpend
				),
				array(
						'name' => 'Competitive order value',
						'data' => $comAvgSpend
				)
		);
		
		return array(
				'axis' => Myshipserv_Spr_PeriodManager::getPeriodKeys($periodList['periodlist'], $params['period']),
				'data' => $data
				
		);
	}
	
	/**
	 * Total number of orders
	 * @param array $params
	 * @return array
	 */
	protected function getTotalNumberOfOrders($params)
	{
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$params = array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'period' => $params['period'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
		);

        // changed by Yuriy Akopov on 2017-07-11, S20557: using dedicated Total query instead of summing the trend
        // @todo: trends are now supposed to add up, so it isn't strictly necessary to calculate total separately
        // @todo: but for now we need to see if discrepancies arise between the two
		// $directSpendreply = $this->reporService->forward('direct-order-count-gmv', $params);
		// $directSpend = $this->dataSum($directSpendreply, 'ord-count');
		// $compSpendreply = $this->reporService->forward('competitive-order-count-gmv-total', $params);
		// $comSpend = $this->dataSum($compSpendreply, 'ord-count');

        $directSpendreply = $this->reporService->forward('direct-order-count-gmv-total', $params);
        $compSpendreply = $this->reporService->forward('competitive-order-count-gmv-total', $params);

        $data = array(
            'direct' => $directSpendreply[0]['ord-count'],
            'competitive' => $compSpendreply[0]['ord-count'],
        );

        $data['total'] = $data['direct'] + $data['competitive'];

		return $data;
	}
	
	/**
	 * Number of orders graph
	 * @param array $params
	 * @return array
	 */
	protected function getNumberOfOrders($params)
	{
		
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$params = array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'period' => $params['period'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
		);
		
		$directOrdersReply = $this->reporService->forward('direct-order-count-gmv', $params);
		$directOrders= $this->periodize($periodList['periodlist'], $directOrdersReply, 'slice', 'ord-count');
		
		$compOrdersReply = $this->reporService->forward('competitive-order-count-gmv', $params);
		$comOrders = $this->periodize($periodList['periodlist'], $compOrdersReply, 'slice', 'ord-count');
		
		$totalOrders = array();
		
		foreach ($directOrders as $key => $value) {
			$totalOrders[] = $value + $comOrders[$key];
		}
		
		
		$data = array(
				array(
						'name' => 'Total orders',
						'data' => $totalOrders
				),
				
				array(
						'name' => 'Direct orders',
						'data' => $directOrders
				),
				array(
						'name' => 'Competitive orders',
						'data' => $comOrders
				)
		);
		
		return array(
				'axis' => Myshipserv_Spr_PeriodManager::getPeriodKeys($periodList['periodlist'], $params['period']),
				'data' => $data
				
		);
	}

	/**
	 * Total number of orders confirmed
	 * This logic a bit complicated, as I have to calculate here form the data I reveive from the backend
	 * I am not getting the concrete values, and they are all sliced by period, 
	 * Lot of backend calls must be called, summarized, and end result calculated, taking care of zero divison
	 * 
	 * @param array $params
	 * @return array
	 */
	protected function getTotalOrdersConfirmed($params)
	{

		$data = array();
		$reply = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		// get order confirmation count
		$reportServiceCalls = array(
				'direct-order-count-gmv',
				'competitive-order-count-gmv',
				'poc-count-gmv-time'
		);
		
		foreach ($reportServiceCalls as $reportType) {
			$reply['bsu'][$reportType] = $this->reporService->forward(
				$reportType,
				array(
					'tnid' => $params['tnid'],
					'byb' => $params['byb'],
					'period' => $params['period'],
					'lowerdate' => $periodList['lowerdate'],
					'upperdate' => $periodList['upperdate'],
				)
			);
			
			$reply['su'][$reportType]= $this->reporService->forward(
				$reportType,
				array(
					'byb' => $params['byb'],
					'period' => $params['period'],
					'lowerdate' => $periodList['lowerdate'],
					'upperdate' => $periodList['upperdate'],
				)
			);
			
			$reply['all'][$reportType]= $this->reporService->forward(
				$reportType,
				array(
					'period' => $params['period'],
					'lowerdate' => $periodList['lowerdate'],
					'upperdate' => $periodList['upperdate'],
				),
			    Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE
			);
		}
		
		$bsu = $this->dataSum($reply['bsu']['competitive-order-count-gmv'], 'ord-count') + $this->dataSum($reply['bsu']['direct-order-count-gmv'], 'ord-count');
		$su = $this->dataSum($reply['su']['competitive-order-count-gmv'], 'ord-count') + $this->dataSum($reply['su']['direct-order-count-gmv'], 'ord-count');
		$all = $this->dataSum($reply['all']['competitive-order-count-gmv'], 'ord-count') + $this->dataSum($reply['all']['direct-order-count-gmv'], 'ord-count');

		$bsuValue = ($bsu > 0) ? $this->dataSum($reply['bsu']['poc-count-gmv-time'], 'poc-count') / $bsu * 100 : 0;
		$suValue = ($su > 0) ? $this->dataSum($reply['su']['poc-count-gmv-time'], 'poc-count') / $su * 100 : 0;
		$allValue = ($all > 0) ? $this->dataSum($reply['all']['poc-count-gmv-time'], 'poc-count') / $all * 100 : 0;
		
		$data = array(
					array(
						'name' => '% Orders confirmed',
						'data' => array(
								array(
									"This supplier<br>with you",
										$bsuValue
								),
								array(
									"All suppliers<br>with you",
										$suValue
									
								),
								array(
									"ShipServ<br>Average",
										$allValue
								),
						)
					),
				);
		
				return array(
					'data' => $data
				);
	}
	
	/**
	 * Orders confirmed graph
	 * @param array $params
	 * @return array
	 */
	protected function getOrdersConfirmed($params)
	{
		
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$params = array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'period' => $params['period'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate']
		);
		
		$reply = $this->reporService->forward(
			'poc-count-gmv-time',
			$params
		);

		$replyDirect = $this->reporService->forward(
			'direct-order-count-gmv',
			$params
		);
		
		$replyComp = $this->reporService->forward(
			'competitive-order-count-gmv',
			$params
		);
		
		$comfirmedOrders = $this->periodize($periodList['periodlist'], $reply, 'slice', 'poc-count');
		$comfirmedOrdersDirect = $this->periodize($periodList['periodlist'], $replyDirect, 'slice', 'ord-count');
		$comfirmedOrdersComp = $this->periodize($periodList['periodlist'], $replyComp, 'slice', 'ord-count');
		
		$result = array();
		foreach (array_keys($comfirmedOrders) as $key) {
            $ordCount = (int) $comfirmedOrdersDirect[$key] + (int) $comfirmedOrdersComp[$key];
		    $pocCount = (int) $comfirmedOrders[$key];

			$result[$key] = round(($ordCount > 0) ? ($pocCount / $ordCount) * 100 : 0, 2);
		}
		
		$data = array(
            array(
                'name' => 'Orders confirmed',
                'data' => $result
            )
		);
		
		return array(
				'axis' => Myshipserv_Spr_PeriodManager::getPeriodKeys($periodList['periodlist'], $params['period']),
				'data' => $data
				
		);
	}
	
	/**
	 * Total of average time to Confirm Order chart 
	 * @param array $params
	 * @return array
	 */
	protected function getTotalAvgTimeToConfirmOrder($params)
	{
	
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
				
		$pocCountGmvTime = $this->reporService->forward(
			'poc-count-gmv-time-total',
			array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
			)
		);
		
		$pocCountGmvTimeSu = $this->reporService->forward(
			'poc-count-gmv-time-total',
			array(
				'byb' => $params['byb'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
			)
		);
		
		$pocCountGmvTimeAll = $this->reporService->forward(
			'poc-count-gmv-time-total',
			array(
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
			),
		    Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE
		);
		
		$pocCountGmvTimeSum = (array_key_exists('poc-average-delay-hours', $pocCountGmvTime[0])) ? (float)$pocCountGmvTime[0]['poc-average-delay-hours'] : 0;
		$pocCountGmvTimeSuSum =  (array_key_exists('poc-average-delay-hours', $pocCountGmvTimeSu[0])) ? (float)$pocCountGmvTimeSu[0]['poc-average-delay-hours'] : 0;
		$pocCountGmvTimeAllSum =  (array_key_exists('poc-average-delay-hours', $pocCountGmvTimeAll[0])) ? (float)$pocCountGmvTimeAll[0]['poc-average-delay-hours'] : 0;
		
		$data = array(
					array(
							'name' => 'Average time to confirm order (Hours)',
							'data' => array(
									array(
											"This supplier<br>with you",
											$pocCountGmvTimeSum
									),
									array(
											"All suppliers<br>with you",
											$pocCountGmvTimeSuSum
									),
									array(
											"ShipServ<br>average",
											$pocCountGmvTimeAllSum
									),
							)
					),
		);
		
		return array(
				'data' => $data
		);
	}
	
	
	/**
	 * average time to Confirm Order chart 
	 * @param array $params
	 * @return array
	 */
	protected function getAvgTimeToConfirmOrder($params)
	{
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$reply = $this->reporService->forward(
			'poc-count-gmv-time',
			array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'period' => $params['period'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate']
			)
		);

		$comfirmedOrders = $this->periodize($periodList['periodlist'], $reply, 'slice', 'poc-average-delay-hours');

		$data = array(
					array(
							'name' => 'Average time to confirm',
							'data' => $comfirmedOrders
					)
		);
		
		return array(
				'axis' => Myshipserv_Spr_PeriodManager::getPeriodKeys($periodList['periodlist'], $params['period']),
				'data' => $data
		);
	}
	
	
	/**
	 * Spend by vessel type
	 * @param array $params
	 * @return array
	 */
	protected function getSpendByVesselType($params)
	{
		
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$reply = $this->reporService->forward(
			'spend-by-vessel-type',
			array(
				'tnid' => $params['tnid'],
				'byb'  => $params['byb'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
				'pageno' => $params['page'],
				'pagesize' => $params['pagesize']
			)
		);
		
		$VesselData = array();
		
		foreach ($reply as $vessel) {
			$vesselTypeName = ($vessel['vessel-type-name'] === '') ? 'Unknown' : $vessel['vessel-type-name'];
			$VesselData[] = array($vesselTypeName, $vessel['ord-total-cost-discounted-usd']);
		}
		
		$data = array(
				array(
						'name' => 'Spend By Vessel Type (USD)',
						'data' => $VesselData
				)
		);
		
		return array(
				'data' => $data
		);
	}
	
	/**
	 * List of most commonly bought items
	 * @param array $params
	 * @return array
	 */
	protected function getCommonItems($params)
	{

		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$reply = $this->reporService->forward(
			'spend-by-product',
			array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
				'pageno' => $params['page'],
				'pagesize' => $params['pagesize']
			)
		);
		
		//Unfortunately frontend data table crashes if someting not returned, so I have to make sure that all the fields are passed
		$data = array();
		foreach ($reply as $record) {
			array_push(
				$data,
				array(
					'average-unit-price' => (array_key_exists('average-unit-price', $record)) ? $record['average-unit-price'] : '',
					'description' => (array_key_exists('description', $record)) ? $record['description'] : '',
					'part-no' => (array_key_exists('part-no', $record)) ? $record['part-no'] : '',
					'quantity' => (array_key_exists('quantity', $record)) ? $record['quantity'] : 0,
					'total-spend' => (array_key_exists('total-spend', $record)) ? $record['total-spend'] : 0,
					'uom' => (array_key_exists('uom', $record)) ? $record['uom'] : '',
				)
			);
		}
		
		return array(
				'data' => $data
		);
	}	
	
	/**
	 * List of most spend by vessel 
	 * @param array $params
	 * @return array
	 */
	protected function getSpendByVesselItems($params)
	{
		
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);

		$reply = $this->reporService->forward(
			'spend-by-vessel', 
			array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
				'pageno' => $params['page'],
				'pagesize' => $params['pagesize']
			)
		);
		
		Myshipserv_Spr_Anonymize::anonimizeData($reply, array('vessel-name' => 'Vessel {X}', 'vessel-imo-no' => 'IMO {X}'));
		
		foreach ($reply as &$value) {
		    if ((string)$value['vessel-type-name'] === "") {
		        $value['vessel-type-name'] = 'Unknown';
		    }
		}

		return array(
				'data' => $reply
		);
	}

	/**
	 * List of most Spend by Purchaser
	 * @param array $params
	 * @return array
	 */
	protected function getSpendByPurchaserItems($params)
	{

		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		$reply = $this->reporService->forward(
			'spend-by-purchaser',
			array(
				'tnid' => $params['tnid'],
				'byb' => $params['byb'],
				'lowerdate' => $periodList['lowerdate'],
				'upperdate' => $periodList['upperdate'],
				'pageno' => $params['page'],
				'pagesize' => $params['pagesize']
			)
		);
		
		//rebuild the result, generate purchaser name, email according to, if the email, or the name exist, or both
		//TODO we may clarify the rules here, It might be better to do this login in the SQL
		$data = array();
		
		$anonimization = array(
		    'purchaser-name' => 'Purchaser {X}',
		    'purchaser-email' => 'email {X}',
		);
		Myshipserv_Spr_Anonymize::anonimizeData($reply, $anonimization);
		
		foreach ($reply as $key => $record) {
			$data[$key] = $record;
			if ($record['purchaser-name'] === '' && $record['purchaser-email'] === '') {
				$data[$key]['name-and-email'] = 'Unknown';
			} else if ($record['purchaser-name'] === '') {
				$data[$key]['name-and-email'] = $record['purchaser-email'];
			} else if ($record['purchaser-email'] === '') {
				$data[$key]['name-and-email'] = $record['purchaser-name'];
			} else {
				$data[$key]['name-and-email'] = $record['purchaser-name'] . ' - ' . $record['purchaser-email'];
			}
			
		}
		
		
		
		return array(
				'data' => $data
		);

	}
    
    /**
     * Returns an array, assigning the proper field value to the proper period
     * @param array $periodList
     * @param array $data
     * @param string $sliceField
     * @param string $resultField
     * @return array
     */
    protected function periodize($periodList, $data, $sliceField, $resultField)
    {
    	$result = array();
    	foreach (array_keys($periodList) as $key) {
    		$node = 0;
    		foreach ($data as $rec) {
    			if ($rec[$sliceField] == $key) {
    				$node = $rec[$resultField];
    			}
    		}
    		$result[] = (float)$node;
    	}
    	return $result;
    }
    
    /**
     * Sum values in result sets, where we need summarized data
     * @param array $data
     * @param string $field
     * @return number
     */
    protected function dataSum($data, $field)
    {
    	$result = 0;
    	foreach ($data as $rec) {
    		$result += (float)$rec[$field];
    	}
    	
    	return $result;
    }
    
    /**
     * Get the status of the last sent reqest to report service,
     * successfull or not, 
     * @return bool
     */
    public function getStatus()
    {
    	return $this->reporService->getStatus();
    }
  
}
