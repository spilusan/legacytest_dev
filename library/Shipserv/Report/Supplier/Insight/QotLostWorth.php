
<?php

class Shipserv_Report_Supplier_Insight_QotLostWorth extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'qot-lost-worth';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}
