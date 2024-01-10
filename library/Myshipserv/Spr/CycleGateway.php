<?php
/*
 * Gateway connecting to report cycle service
 */

class Myshipserv_Spr_CycleGateway
{
	private static $_instance;
	protected $reporService;
	
	/**
	 * Singleton class entry point, create single instance
	 *
	 * @return Myshipserv_Spr_CycleGateway
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
	 * @return Myshipserv_Spr_CycleGateway
	 */
	protected function __construct()
	{
	    $this->reporService = new Myshipserv_Spr_DbCachedForwarder(Myshipserv_ReportService_Gateway::getInstance(Myshipserv_ReportService_Gateway::REPORT_SPR_CYCLE_TIME));
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
			case 'cycle-time-data':
				return $this->getCycleTimeData($params);
				break;
			case 'avg-cycle-data':
				return $this->getAvgCycleTimeData($params);
				break;
			default:
				return null;
				break;
		}
	}
	
	/**
	 * Get the acutal cycle time chart data
	 * 
	 * @param array $params
	 * @return array
	 */
	protected function getCycleTimeData($params)
	{
	    
		//Currently Dummy data, TODO repoace with call(s) from report-servie
	    $period = $params['period'];
	    $periodList = Myshipserv_Spr_PeriodManager::getPeriodList($period);
	    $periodList = $periodList['periodlist'];
		$serviceParams = $params;
		$serviceParams['lowerdate'] = Myshipserv_Spr_PeriodManager::getLowerDate($period);
		$serviceParams['upperdate'] = Myshipserv_Spr_PeriodManager::getUpperDate($period);
		$avgRfqToOrdConf= array();
		
		$avgRfqToOrdConf = $this->reporService->forward('get-rfq-to-poc-avg-cycle-time-su-by-period', $serviceParams);
		$avgRfqToOrdConfData = Myshipserv_Spr_PeriodManager::getSlicedData($periodList, $avgRfqToOrdConf, 'average');
		
		$avgRfqToOrd = $this->reporService->forward('get-rfq-to-ord-avg-cycle-time-su-by-period', $serviceParams);
		$avgRfqToOrdData = Myshipserv_Spr_PeriodManager::getSlicedData($periodList, $avgRfqToOrd, 'average');
		
		$data = array(
			array(
				'name' => 'Average RFQ to order confirmation',
				'connectNulls' => true,
				'data' => $avgRfqToOrdConfData
			),
				
			array(
				'name' => 'Average RFQ to order',
				'connectNulls' => true,
				'data' => $avgRfqToOrdData
			),
		);
		
		return array(
			'axis' => Myshipserv_Spr_PeriodManager::getPeriodKeys($periodList, $period),
			'data' => $data
		);
		
	}
	
	/**
	 * Get the acutal report data for the SPR Cycle
	 * @param array $params
	 * @return array
	 */
	protected function getAvgCycleTimeData($params)
	{
		$serviceParams = array();

		// BUY-392: split into separate all/asu/su blocks by Yuriy Akopov to avoid different cache keys from unnecessary parameters

		$serviceParams['lowerdate'] = Myshipserv_Spr_PeriodManager::getLowerDate($params['period']);
		$serviceParams['upperdate'] = Myshipserv_Spr_PeriodManager::getUpperDate($params['period']);

		$reqQuoteAll = $this->reporService->forward('get-req-to-qot-avg-cycle-time', $serviceParams, Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE);
		$reqOrdAll = $this->reporService->forward('get-req-to-ord-avg-cycle-time', $serviceParams, Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE);
		$rfqOrdAll = $this->reporService->forward('get-rfq-to-ord-avg-cycle-time', $serviceParams, Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE);
		$reqPocAll = $this->reporService->forward('get-req-to-poc-avg-cycle-time', $serviceParams, Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE);
		$rfqPocAll = $this->reporService->forward('get-rfq-to-poc-avg-cycle-time', $serviceParams, Myshipserv_ReportService_Gateway::SPR_DATABASE_CACHE);

        $serviceParams['byb'] = $params['byb'];

        $reqQuoteAsu = $this->reporService->forward('get-req-to-qot-avg-cycle-time', $serviceParams);
        $reqOrdAsu = $this->reporService->forward('get-req-to-ord-avg-cycle-time', $serviceParams);
        $reqPocAsu = $this->reporService->forward('get-req-to-poc-avg-cycle-time', $serviceParams);
        $rfqOrdAsu = $this->reporService->forward('get-rfq-to-ord-avg-cycle-time', $serviceParams);
        $rfqPocAsu = $this->reporService->forward('get-rfq-to-poc-avg-cycle-time', $serviceParams);

        $serviceParams['tnid'] = $params['tnid'];

        $reqQuote = $this->reporService->forward('get-req-to-qot-avg-cycle-time', $serviceParams);
	    $reqOrd = $this->reporService->forward('get-req-to-ord-avg-cycle-time', $serviceParams);
	    $reqPoc = $this->reporService->forward('get-req-to-poc-avg-cycle-time', $serviceParams);
	    $rfqOrd = $this->reporService->forward('get-rfq-to-ord-avg-cycle-time', $serviceParams);
	    $rfqPoc = $this->reporService->forward('get-rfq-to-poc-avg-cycle-time', $serviceParams);

		return array(
				'req-quote' => array(
					'txn' => (Int) $reqQuote['count'],
					'days' => (Float) $reqQuote['average'],
				),
				'req-quote-asu' => array(
					'txn' => (Int) $reqQuoteAsu['count'],
					'days' => (Float) $reqQuoteAsu['average'],
				),
				'req-quote-all' => array(
					'txn' => (Int) $reqQuoteAll['count'],
					'days' => (Float) $reqQuoteAll['average'],
				),
				'req-ord' => array(
					'txn' => (Int) $reqOrd['count'],
					'days' => (Float) $reqOrd['average'],
				),
				'req-ord-asu' => array(
					'txn' => (Int) $reqOrdAsu['count'],
					'days' => (Float) $reqOrdAsu['average'],
				),
				'req-ord-all' => array(
					'txn' => (Int) $reqOrdAll['count'],
					'days' => (Float) $reqOrdAll['average'],
				),
				'req-poc' => array(
					'txn' => (Int) $reqPoc['count'],
					'days' => (Float) $reqPoc['average'],
				),
				'req-poc-asu' => array(
					'txn' => (Int) $reqPocAsu['count'],
					'days' => (Float) $reqPocAsu['average'],
				),
				'req-poc-all' => array(
					'txn' => (Int) $reqPocAll['count'],
					'days' => (Float) $reqPocAll['average'],
				),
				'rfq-ord' => array(
					'txn' => (Int) $rfqOrd['count'],
					'days' => (Float) $rfqOrd['average'],
				),
				'rfq-ord-asu' => array(
					'txn' => (Int) $rfqOrdAsu['count'],
					'days' => (Float) $rfqOrdAsu['average'],
				),
				'rfq-ord-all' => array(
					'txn' => (Int) $rfqOrdAll['count'],
					'days' => (Float) $rfqOrdAll['average'],
				),
				'rfq-poc' => array(
					'txn' => (Int) $rfqPoc['count'],
					'days' => (Float) $rfqPoc['average'],
				),
				'rfq-poc-asu' => array(
					'txn' => (Int) $rfqPocAsu['count'],
					'days' => (Float) $rfqPocAsu['average'],
				),
				'rfq-poc-all' => array(
					'txn' => (Int) $rfqPocAll['count'],
					'days' => (Float) $rfqPocAll['average'],
				),
				'_debug' => $params
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
