<?php
/**
 * Class to handle content module on search result page
 * This class will pull statistic for each categories
 * 
 * @author Elvir <eleonard@shipserv.com>
 *
 */
class Myshipserv_Search_CategoryStatistic extends Shipserv_Oracle
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
		$this->data['view']['text'][] 		= "[number] users viewed this category in the last 30 days.";
		$this->data['view']['text'][] 		= "A total of [number] buyers viewed this page in the last 30 days.";
		$this->data['view']['text'][] 		= "In the last 30 days this category has been viewed [number] times.";
		$this->data['supplier']['text'][] 	= "[number] suppliers have selected [category_name] as one of their categories.";
		$this->data['supplier']['text'][] 	= "A total of [number] suppliers have [category_name] set as one of their supply categories.";
		$this->data['supplier']['text'][] 	= "Currently [category_name] has [number] suppliers selected as a category.";
		$this->data['newSupplier']['text'][] 	= "[number] new suppliers have selected this category in the last 30 days.";
		$this->data['newSupplier']['text'][] 	= "A total of [number] suppliers have selected this category in the last 30 days.";
		$this->data['newSupplier']['text'][] 	= "In the last 30 days this category has had [number] suppliers assigned to it.";
		$this->data['rfqSent']['text'][] 		= "[number] RFQs have been sent from buyers to marine suppliers of [category_name] in the last 30 days.";
		$this->data['rfqSent']['text'][] 		= "A Total of [number] RFQs have been sent to marine suppliers of [category_name] in the last 30 days.";
		$this->data['rfqSent']['text'][] 		= "In the last 30 days [number] RFQs have been sent to marine suppliers of [category_name].";
		$this->data['transaction']['text'][] 	= "$[number] has been ordered from the first 500 suppliers on ShipServ in the last 3 months from this category";
		$this->data['transaction']['text'][] 	= "In the last 3 months $[number] has been ordered from the first 500 suppliers in this category.";
		$this->data['transaction']['text'][] 	= "Buyers on ShipServ have ordered $[number] from the first 500 suppliers in this category in the last 3 months.";
		
	}
	
	/**
	 * Pull all appropriate statistic from the database
	 * 
	 * @param unknown_type $categoryId
	 * @param unknown_type $period
	 */
	public function generateStatisticByCategoryId($categoryId, $period = array())
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
		
		$analyticAdapter = new Shipserv_Oracle_Analytics_Category($this->db);
		
		$this->data['view']['total'] 		= number_format( $analyticAdapter->getTotalSearchByCategoryId($categoryId, 					$periodForOneMonth));
		$this->data['supplier']['total']	= number_format( $analyticAdapter->getTotalSupplierByCategoryId($categoryId));
		$this->data['newSupplier']['total']	= number_format( $analyticAdapter->getTotalNewlyUpdatedSupplierByCategoryId($categoryId, 	$periodForOneMonth));
		$this->data['rfqSent']['total']		= number_format( $analyticAdapter->getTotalRfqSentToSupplierByCategoryId($categoryId, 		$periodForOneMonth));
		$this->data['transaction']['total']	= number_format( $analyticAdapter->getTotalTransactionInTradenetByCategoryId($categoryId, 	$periodForThreeMonths));
		
		return $this->data;
	}
	
	public function textify()
	{
		
	}
}