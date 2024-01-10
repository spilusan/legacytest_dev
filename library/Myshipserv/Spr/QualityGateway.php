<?php
/*
 * Gateway connecting to report quality service
 */

class Myshipserv_Spr_QualityGateway
{
	private static $_instance;
	protected $reporService;
	
	/**
	 * Singleton class entry point, create single instance
	 *
	 * @return Myshipserv_Spr_QualityGateway
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
	    $this->reporService = new Myshipserv_Spr_DbCachedForwarder(Myshipserv_ReportService_Gateway::getInstance(Myshipserv_ReportService_Gateway::REPORT_SPR_QPD));
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
			case 'get-avg-quote-lead-time-summary':
				return $this->getAvgQuoteLeadTimeSummaryData($params);
				break;
			case 'get-avg-quote-lead-time':
				return $this->getAvgQuoteLeadTimeData($params);
				break;
			case 'get-quality-level-quoted':
				return $this->getQualityLevelQuotedData($params);
				break;
			case 'get-payment-terms-quoted':
				return $this->getPaymentTermsQuotedData($params);
				break;
			default:
				return null;
				break;
		}
	}
	
	/**
	 * Get the acutal quality chart data
	 * Average quoted lead time summary
	 * 
	 * @param array $params
	 * @return array
	 */
	protected function getAvgQuoteLeadTimeSummaryData($params)
	{

		$period = $params['period'];
	    $periodList = Myshipserv_Spr_PeriodManager::getPeriodList($period);
	    $periodList = $periodList['periodlist'];
		$serviceParams = $params;
		$serviceParams['lowerdate'] = Myshipserv_Spr_PeriodManager::getLowerDate($period);
		$serviceParams['upperdate'] = Myshipserv_Spr_PeriodManager::getUpperDate($period);
		
		$avgQuoteLeadTimeSummary= $this->reporService->forward('get-avg-quote-lead-time-summary', $serviceParams);
		$getAvgQuoteLeadTimeSummaryCompeting = $this->reporService->forward('get-avg-quote-lead-time-summary-competing', $serviceParams);
			
		$data = array(
			array(
				'This supplier<br>with you',
				$avgQuoteLeadTimeSummary['average']
			),
			array(
				'Competing suppliers',
				$getAvgQuoteLeadTimeSummaryCompeting['average']
			)
		);
		
		return array(
			'data' => array(
						array(
							'name' => 'Average lead time',
							'data' => $data
						)
			)
		);
		
	}
	
	
	/**
	 * Get the acutal quality chart data
	 * Average quoted lead time summary
	 *
	 * @param array $params
	 * @return array
	 */
	protected function getAvgQuoteLeadTimeData($params)
	{
		
		$period = $params['period'];
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($period);
		$periodList = $periodList['periodlist'];
		$serviceParams = $params;
		$serviceParams['lowerdate'] = Myshipserv_Spr_PeriodManager::getLowerDate($period);
		$serviceParams['upperdate'] = Myshipserv_Spr_PeriodManager::getUpperDate($period);

		
		$avgQuoteLeadTime= $this->reporService->forward('get-avg-quote-lead-time', $serviceParams);
		$getAvgQuoteLeadTimeCompeting = $this->reporService->forward('get-avg-quote-lead-time-competing', $serviceParams);
		
		$data = array(
				array(
						'name' => 'This supplier with you',
						'data' => Myshipserv_Spr_PeriodManager::getSlicedData($periodList, $avgQuoteLeadTime, 'average')
				),
				array(
						'name' => 'Competing suppliers',
						'data' => Myshipserv_Spr_PeriodManager::getSlicedData($periodList, $getAvgQuoteLeadTimeCompeting, 'average')
				)
		);
		
		return array(
				'axis' => Myshipserv_Spr_PeriodManager::getPeriodKeys($periodList, $period),
				'data' => $data
		);
		
	}
	
	/**
	 * Fetching data for Quality Level Quoted chart
	 * @param array $params
	 * @return array[]
	 */
	protected function getQualityLevelQuotedData($params)
	{
		$period = $params['period'];
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($period);
		$periodList = $periodList['periodlist'];
		$serviceParams = $params;
		$serviceParams['lowerdate'] = Myshipserv_Spr_PeriodManager::getLowerDate($period);
		$serviceParams['upperdate'] = Myshipserv_Spr_PeriodManager::getUpperDate($period);
		
		$data = $this->reporService->forward('get-quality-level-quoted', $serviceParams);
		
		return array(
				'data' => $data
		);
	}
	
	/**
	 * Fetching data from report service for Payment Terms Quoted
	 * @param array $params
	 * @return array[]
	 */
	protected function getPaymentTermsQuotedData($params)
	{
		$period = $params['period'];
		$periodList = Myshipserv_Spr_PeriodManager::getPeriodList($period);
		$periodList = $periodList['periodlist'];
		$serviceParams = $params;
		$serviceParams['lowerdate'] = Myshipserv_Spr_PeriodManager::getLowerDate($period);
		$serviceParams['upperdate'] = Myshipserv_Spr_PeriodManager::getUpperDate($period);
		
		$data = $this->reporService->forward('get-payment-terms-quoted', $serviceParams);

		return array(
				'data' => $data
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
		