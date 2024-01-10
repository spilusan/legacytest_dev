<?php
/**
 * Parameters received by Shipserv_PriceBenchmark calculator methods
 * 
 * @author  Yuriy Akopov
 * @date    2016-07-01
 * @story   S16903
 */
class Shipserv_PriceBenchmark_Parameters
{
	/**
	 * Only used by SpendTracker
	 * 
	 * @var bool
	 */
	public $savings = null;
	/**
	 * Only used by SpendTracker
	 * 
	 * @var array
	 */
	public $productWords = array();

	/**
	 * @var array
	 */
	public $products = array();

	/**
	 * @var DateTime
	 */
	public $dateFrom = null;
	/**
	 * @var DateTime
	 */
	public $dateTo = null;

	/**
	 * @var string
	 */
	public $vesselName = null;
	/**
	 * @var array
	 */
	public $lineItemWords = array();
	/**
	 * @var array
	 */
	public $supplierCountryCodes = array();

	/**
	 * Expected for order line items context
	 * 
	 * @var array
	 */
	public $exclude = array();

	/**
	 * Expected for quote line items context
	 *
	 * @var array
	 */
	public $excludeRight = array();
	
	/**
	 * @var int
	 */
	public $pageNo = null;
	/**
	 * @var int
	 */
	public $pageSize = null;
	/**
	 * @var string
	 */
	public $sortBy = null;
	/**
	 * @var string
	 */
	public $sortDir = null;
}