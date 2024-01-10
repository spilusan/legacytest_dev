<?php

class Shipserv_Report_Supplier_Insight_HowQuickestCheapestSupplierQuotes extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'how-quickest-cheapest-supplier-quotes';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}


