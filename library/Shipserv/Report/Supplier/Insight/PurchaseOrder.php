<?php
class Shipserv_Report_Supplier_Insight_PurchaseOrder extends Shipserv_Report_Supplier_Insight
{
	const ADAPTER_NAME = 'po-value-by-buyer';
	
	
	protected $supportedGroupBy = array('buyer');
	
	public function setGroupBy( $groupBy ) 
	{
		if( in_array($groupBy, $this->supportedGroupBy) === false )
		{
			throw new Exception('Group by "' . $groupBy . '" is not supported at the moment.');
		}
		
		$this->groupBy = $groupBy;
	}
	
	public function getData()
	{
		if( $this->groupBy == 'buyer' )
		{
			return $this->getDataFromReportService();
		}	
		
		throw new Exception('Group by isn\'t supplied.');
	}
}