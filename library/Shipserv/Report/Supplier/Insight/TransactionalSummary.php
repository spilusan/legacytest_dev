<?php
/*
{
	getGmvPageOneSearchImpression
	
	top-search-impression: {
		count: 183575
	},
	po-total-value: {
		count: 11256554.46
	}
	
	--------
	
	
} 
 */
class Shipserv_Report_Supplier_Insight_TransactionalSummary extends Shipserv_Report_Supplier_Insight
{
	
	const ADAPTER_NAME = 'transactional-summary';
	
	public function getData()
	{
		$adapter = new Shipserv_Adapters_Report();
		return $adapter->getSupplierImpression( $this->tnid, $this->startDate->format('Ymd'), $this->endDate->format('Ymd') );
	}
}