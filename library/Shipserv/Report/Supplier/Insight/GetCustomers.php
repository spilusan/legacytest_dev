<?php

class Shipserv_Report_Supplier_Insight_GetCustomers extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'get-customers';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}