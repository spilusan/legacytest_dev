<?php
/**
 * Class to handle content module on search result page
 * This class will pull statistic for each categories
 * 
 * @author Elvir <eleonard@shipserv.com>
 *
 */
class Myshipserv_Search_BrandStatistic extends Shipserv_Oracle
{
	public function __construct()
	{
		$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');	

		// use different db - as for development environment, we use -1 standby database
		if( $_SERVER['APPLICATION_ENV'] == "development")
		{
			$this->db = $resource->getDb('standbydblocal');
		}
		// otherwise run the query on the standby database
		else
		{
			$this->db = $resource->getDb('standbydb');
		}
				
		// template for the text
		$this->data['view']['text'][] 		= "This brand was visited by [number] users in the last 30 days.";
		$this->data['view']['text'][] 		= "A total of [number] buyers have visited this brand page in the last 30 days.";
		$this->data['view']['text'][] 		= "In the last 30 days this brand page has been viewed [number] times.";
		
		$this->data['supplier']['text'][]  = "This brand has [number] authorised brand suppliers, which means the manufacturer has approved them as authorized agents/suppliers.";
		$this->data['supplier']['text'][]  = "The total number of brand suppliers approved by the manufacturer for this brand is [number].";
		$this->data['supplier']['text'][]  = "[number] suppliers of this brand have been approved by the Manufacturer";
		
		$this->data['newSupplier']['text'][] 	= "[number] suppliers have selected this brand in the last 30 days.";
		$this->data['newSupplier']['text'][] 	= "This brand has had [number] suppliers assigned to it in the last 30 days.";
		$this->data['newSupplier']['text'][] 	= "In the last 30 days this brand has had [number] suppliers assigned to it.";
		
		$this->data['rfqSent']['text'][] 		= "[number] RFQs have been sent to agents & suppliers of [brand_name] in the last 30 days";
		$this->data['rfqSent']['text'][] 		= "A total of [number] RFQs have been sent to agents & suppliers of [brand_name] in the last 30 days.";
		$this->data['rfqSent']['text'][] 		= "Suppliers & Agents of [brand_name] have received a total of [number] RFQs in the last 30 days.";
		
		/*
		$this->data['transaction']['text'][] 	= "$[number] has been ordered from the first 500 suppliers on ShipServ in the last 3 months from this category";
		$this->data['transaction']['text'][] 	= "In the last 3 months $[number] has been ordered from the first 500 suppliers in this category.";
		$this->data['transaction']['text'][] 	= "Buyers on ShipServ have ordered $[number] from the first 500 suppliers in this category in the last 3 months.";
		
		
One of the most popular products made by [brand_name] is [product_name].
[product_name] is one of the most popular products made by this brand.
One of the more popular products made by this brand is [product_name].		 
		 */
	}
	
	/**
	 * Pull all appropriate statistic from the database
	 * 
	 * @param unknown_type $BrandId
	 * @param unknown_type $period
	 */
	public function generateStatisticByBrandId($brandId, $period = array())
	{
		
		// set the default date
		$now = new DateTime();
		$oneMonth = new DateTime();
		$threeMonths = new DateTime();
		
		$now->setDate(date('Y'), date('m'), date('d')+1);
		$oneMonth->setDate(date('Y'), date('m')-1, date('d'));
		$threeMonths->setDate(date('Y'), date('m')-3, date('d'));
			
		// store it to a data structure to be passed around
		$periodForOneMonth 		= array('start' => $oneMonth, 'end' => $now );
		$periodForThreeMonths 	= array('start' => $threeMonths, 'end' => $now );
		
		$analyticAdapter = new Shipserv_Oracle_Analytics_Brand($this->db);
		
		$this->data['view']['total'] 		= number_format( $analyticAdapter->getTotalSearchByBrandId($brandId, 					$periodForOneMonth));
		$this->data['supplier']['total']	= number_format( $analyticAdapter->getTotalSupplierByBrandId($brandId));
		$this->data['newSupplier']['total']	= number_format( $analyticAdapter->getTotalNewlyUpdatedSupplierByBrandId($brandId, 		$periodForOneMonth));
		$this->data['rfqSent']['total']		= number_format( $analyticAdapter->getTotalRfqSentToSupplierByBrandId($brandId, 		$periodForOneMonth));
		
		return $this->data;
	}
	
	public function textify()
	{
		
	}
}