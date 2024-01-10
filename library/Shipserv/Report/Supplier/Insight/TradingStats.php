<?php

class Shipserv_Report_Supplier_Insight_TradingStats extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'trading-stats';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}