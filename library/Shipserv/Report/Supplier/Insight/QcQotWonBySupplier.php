<?php

class Shipserv_Report_Supplier_Insight_QcQotWonBySupplier extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'quickest-cheapest-qot-won-by-supplier';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}
