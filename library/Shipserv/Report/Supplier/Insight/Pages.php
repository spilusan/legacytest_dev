<?php

class Shipserv_Report_Supplier_Insight_Pages extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'pages-stats';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}