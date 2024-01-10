<?php
/*
* Gateway connecting to report funnel service
* and aggregate, compose data for funnel tab
*/

class Myshipserv_Spr_FunnelGateway
{
	private static $_instance;
	protected $reporService;

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
	    $this->reporService = new Myshipserv_Spr_DbCachedForwarder(Myshipserv_ReportService_Gateway::getInstance(Myshipserv_ReportService_Gateway::REPORT_SPR_FUNNEL));
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
			case 'data':
				return $this->getChartDataForFunnelAll($params);
				break;
			case 'order':
				return $this->getChartDataForFunnelOrder($params);
				break;
			case 'quote':
				return $this->getChartDataForFunnelQuote($params);
				break;
			case 'rfq':
				return $this->getChartDataForFunnelRfq($params);
				break;
			default:
				return null;
				break;
		}
	}

	/**
	 * Get the actual report data for the SPR Funnel
	 * @param array $params
	 * @return array
	 */
	protected function getChartDataForFunnelAll($params)
	{
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		 
		//Fetch all data from micro services. All supplier with you
		$serviceParams = array(
			'tnid' => $params['tnid'],
			'byb' => $params['byb'],
			'lowerdate' => $periodList['lowerdate'],
			'upperdate' => $periodList['upperdate']
		);
		
		$reportServiceRequests = array(
			'sent-rfqs',
			'pending-rfqs',
			'ignored-rfqs',
			'declined-rfqs',
			'quotes-received',
			'quotes-unknown',
			'quotes-lost',
			//'competitive-orders', //DUPLICATE
			'competitive-order-gmv',
			'direct-orders',
			'direct-order-gmv',
			'assumed-quotes'
		);
		
		foreach ($reportServiceRequests as $reportType) {
			$reply  = $this->reporService->forward($reportType, $serviceParams);
			$data[$reportType] = $reply;
		}

		//Calculate percentages
		$rfqCount = (int)$data['sent-rfqs']['spb-count'];
		$quoteCount = (int)$data['quotes-received']['spb-count'];

		// added by Yuriy Akopov on 2017-07-21, S20542
		// all real quotes + assumed quotes
		$quoteCountAll = $quoteCount + (int)$data['assumed-quotes']['spb-count'];
		
		$quotedPercent = 0;
		$winPercent = 0;
		$rfqsDeclinedPercent = 0;
		$rfqsIgnoredPercent = 0;
		$rfqsPendingPercent = 0;
		$rfqsQuotedUnknownPercent = 0;
		$quotedLostPercent = 0;
		
		if ($rfqCount > 0) {
			$rfqsDeclinedPercent = (int)$data['declined-rfqs']['spb-count'] / $rfqCount * 100;
			$rfqsIgnoredPercent = (int)$data['ignored-rfqs']['spb-count'] / $rfqCount * 100;
			$rfqsPendingPercent = (int)$data['pending-rfqs']['spb-count'] / $rfqCount * 100;
			$rfqsQuotedUnknownPercent = (int)$data['quotes-unknown']['qot-count'] / $rfqCount * 100;

            // changed by Yuriy Akopov on 2017-07-21 to include assumed quotes, S20542
			$quotedPercent = $quoteCountAll / $rfqCount * 100;
		} 		

		if ($quoteCountAll > 0) {
            // changed by Yuriy Akopov on 2017-07-21 to include assumed quotes, S20542
			$quotedLostPercent = (int)$data['quotes-lost']['qot-count'] / $quoteCountAll * 100;
            $winPercent = (int) $data['competitive-order-gmv']['spb-count'] / $quoteCountAll * 100;
            // BUY-641: prevent multiple latest-in-the-chain orders from the same quote to send win rate through the roof
            // not sure though if this value is used on the front end or is recalculated again... correcting in both places
            $winPercent = min(100, $winPercent);
		}
		
		//generate result array
		return array(
			'directOrdersCount' => (int)$data['direct-orders']['ord-count'],
			'directOrdersValue' => (float)$data['direct-order-gmv']['ord-total-cost-discounted-usd'],
			
			'rfqsDeclinedCount'     => (int)$data['declined-rfqs']['spb-count'],
			'rfqsDeclinedValue'     => (float)$data['declined-rfqs']['rfq-estimated-cost-usd'],
			'rfqsDeclinedPercent'   => round($rfqsDeclinedPercent, 2),
			
			'rfqsIgnoredCount'      => (int)$data['ignored-rfqs']['spb-count'],
			'rfqsIgnoredValue'      => (float)$data['ignored-rfqs']['rfq-estimated-cost-usd'],
			'rfqsIgnoredPercent'    => round($rfqsIgnoredPercent, 2),

            // changed by Yuriy Akopov on 2017-07-21 to include assumed quotes, S20542
			'quotesReceived'    => $quoteCountAll,
			'quotedLostCount'   => (int)$data['quotes-lost']['qot-count'],
			'quotedLostValue'   => (float)$data['quotes-lost']['qot-cost'],
			'quotedLostPercent' =>  round($quotedLostPercent, 2),
			
			'rfqsSentCount'         => (int)$data['sent-rfqs']['spb-count'],
			'rfqsPendingCount'      => (int)$data['pending-rfqs']['spb-count'],
			'rfqsPendingPercent'    => round($rfqsPendingPercent, 2),
	
			'rfqsQuotedUnknownCount'    => (int)$data['quotes-unknown']['qot-count'],
			'rfqsQuotedUnknownValue'    => (float)$data['quotes-unknown']['qot-cost'],
			'rfqsQuotedUnknownPercent'  => round($rfqsQuotedUnknownPercent, 2),
			
			'competitiveOrdersSentCount'  => (int)$data['competitive-order-gmv']['spb-count'],
			'competitiveOrdersSentValue'  => (float)$data['competitive-order-gmv']['ord-total-cost-discounted-usd'],
			
			'quotedPercent' => round($quotedPercent, 2),
			'winPercent'    => round($winPercent, 2),
	
			'totalOrdersSentCount'  => (int)$data['direct-order-gmv']['ord-count'] + (int)$data['competitive-order-gmv']['spb-count'],
			'totalOrdersSentValue'  => (float)$data['direct-order-gmv']['ord-total-cost-discounted-usd'] + (float)$data['competitive-order-gmv']['ord-total-cost-discounted-usd']
		);
	}

	
	/**
	 * Get the acutal report data for the SPR Funnel, orders
	 * @param array $params
	 * @return array
	 */
	protected function getChartDataForFunnelOrder($params)
	{
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		//Fetch all data from micro services. All supplier with you
		$serviceParams = array(
			'tnid' => $params['tnid'],
			'byb' => $params['byb'],
			'lowerdate' => $periodList['lowerdate'],
			'upperdate' => $periodList['upperdate']
		);
		
		$reportServiceRequests = array(
			'direct-orders',
			'competitive-order-gmv',
			'direct-order-gmv'
		);
		
		foreach ($reportServiceRequests as $reportType) {
			$reply  = $this->reporService->forward($reportType, $serviceParams);
			$data[$reportType] = $reply;
		}
		
		//generate result array
		return array(
			'directOrdersCount' => (int)$data['direct-orders']['ord-count'],
			'directOrdersValue' => (float)$data['direct-order-gmv']['ord-total-cost-discounted-usd'],
			'competitiveOrdersSentCount'  => (int)$data['competitive-order-gmv']['spb-count'],
			'competitiveOrdersSentValue'  => (float)$data['competitive-order-gmv']['ord-total-cost-discounted-usd'],
			'totalOrdersSentCount'  => (int)$data['direct-orders']['ord-count']  + (int)$data['competitive-order-gmv']['spb-count'],
			'totalOrdersSentValue'  => (float)$data['direct-order-gmv']['ord-total-cost-discounted-usd'] + (float)$data['competitive-order-gmv']['ord-total-cost-discounted-usd']
		);
	}
	
	
	/**
	 * Get the acutal report data for the SPR Funnel quotes
	 * @param array $params
	 * @return array
	 */
	protected function getChartDataForFunnelQuote($params)
	{
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		//Fetch all data from micro services. All supplier with you
		$serviceParams = array(
			'tnid' => $params['tnid'],
			'byb' => $params['byb'],
			'lowerdate' => $periodList['lowerdate'],
			'upperdate' => $periodList['upperdate']
		);
		
		$reportServiceRequests = array(
			'quotes-received',
			'quotes-unknown',
			'quotes-lost',
			'assumed-quotes'
		);
		
		foreach ($reportServiceRequests as $reportType) {
			$reply  = $this->reporService->forward($reportType, $serviceParams);
			$data[$reportType] = $reply;
		}
		
		//generate result array
		$data = array(
			'quotesReceived' => (int)$data['quotes-received']['spb-count'],
			'quotesAssumed'  =>  (int)$data['assumed-quotes']['spb-count'],
			'quotedUnknownCount'  => (int)$data['quotes-unknown']['qot-count'],
			'quotedUnknownValue'  => (float)$data['quotes-unknown']['qot-cost'],
			'quotedLostCount'  => (int)$data['quotes-lost']['qot-count'],
			'quotedLostValue'  => (float)$data['quotes-lost']['qot-cost']
			);

        // added by Yuriy Akopov on 2017-07-21, S20542 to include assumed quotes
		$data['quotesReceivedTotal'] = $data['quotesReceived'] + $data['quotesAssumed'];

		return $data;
	}
	
	/**
	 * Get the acutal report data for the SPR Funnel RFQ
	 * @param array $params
	 * @return array
	 */
	protected function getChartDataForFunnelRfq($params)
	{
		$data = array();
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($params['period']);
		
		//Fetch all data from micro services. All supplier with you
		$serviceParams = array(
			'tnid' => $params['tnid'],
			'byb' => $params['byb'],
			'lowerdate' => $periodList['lowerdate'],
			'upperdate' => $periodList['upperdate']
		);
		
		$reportServiceRequests = array(
			'sent-rfqs',
			'pending-rfqs',
			'ignored-rfqs',
			'declined-rfqs'
		);
		
		foreach ($reportServiceRequests as $reportType) {
			$reply  = $this->reporService->forward($reportType, $serviceParams);
			$data[$reportType] = $reply;
		}
		
		//generate result array
		return array(
			'rfqsSentCount'  => (int)$data['sent-rfqs']['spb-count'],
			'rfqsPendingCount'  => (int)$data['pending-rfqs']['spb-count'],
			'rfqsIgnoredCount'  => (int)$data['ignored-rfqs']['spb-count'],
			'rfqsIgnoredValue'  => (float)$data['ignored-rfqs']['rfq-estimated-cost-usd'],
			'rfqsDeclinedCount' => (int)$data['declined-rfqs']['spb-count'],
			'rfqsDeclinedValue' => (float)$data['declined-rfqs']['rfq-estimated-cost-usd']
		);
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
