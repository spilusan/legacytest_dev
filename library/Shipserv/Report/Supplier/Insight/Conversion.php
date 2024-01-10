<?php

class Shipserv_Report_Supplier_Insight_Conversion extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'lead-conversion';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}