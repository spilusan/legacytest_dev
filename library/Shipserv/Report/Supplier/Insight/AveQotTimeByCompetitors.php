<?php

class Shipserv_Report_Supplier_Insight_AveQotTimeByCompetitors extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'ave-qot-time-by-competitors';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}
