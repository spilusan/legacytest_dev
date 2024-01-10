<?php

class Shipserv_Report_Supplier_Insight_QotTimePriceSensitive extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'qot-time-price-sensitive';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}
