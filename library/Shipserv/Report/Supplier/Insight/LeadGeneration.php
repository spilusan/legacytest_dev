<?php

class Shipserv_Report_Supplier_Insight_LeadGeneration extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'lead-generation';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}