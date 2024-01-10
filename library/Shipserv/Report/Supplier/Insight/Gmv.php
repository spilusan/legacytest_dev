<?php

class Shipserv_Report_Supplier_Insight_Gmv extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'get-gmv-breakdown';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}