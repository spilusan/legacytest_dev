<?php

class Shipserv_Report_Supplier_Insight_NotQuotedRfq extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'not-quoted-rfq-worth';
	
	public function getData()
	{
		return $this->getDataFromReportService(self::ADAPTER_NAME);
	}
}
