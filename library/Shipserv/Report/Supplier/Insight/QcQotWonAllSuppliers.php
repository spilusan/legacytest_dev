<?php

class Shipserv_Report_Supplier_Insight_QcQotWonAllSuppliers extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'quickest-cheapest-qot-won-all-suppliers';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}
