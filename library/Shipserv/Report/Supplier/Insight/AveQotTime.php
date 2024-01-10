<?php

class Shipserv_Report_Supplier_Insight_AveQotTime extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'ave-qot-time';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}
