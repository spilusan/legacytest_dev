<?php

class Shipserv_Report_Supplier_Insight_Brand extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'brand-awareness';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}