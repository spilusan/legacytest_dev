<?php
/**
 * Implements search query functions for price benchmarking based on precalculated data in the DB
 * Unlike the predecessor family of classes in Myshipserv_Search_PriceBenchmark_*, it is simpler so there is not need
 * for complex engineering and inheritances (at least this is the initial idea)
 *
 * @author  Yuriy Akopov
 * @story   S16903
 * @date    2106-06-29
 */

class Shipserv_PriceBenchmark extends Shipserv_Object
{
	const MEMCACHE_TTL = 21600;         // 6 hours memcached timeout

	/**
	 * @var null|Shipserv_Buyer
	 */
	protected $buyerOrg = null;

	/**
	 * @param Shipserv_Buyer $buyerOrg
	 */
	public function __construct(Shipserv_Buyer $buyerOrg)
	{
		$this->buyerOrg = $buyerOrg;
	}

	/**
	 * @return DateTime
	 */
	public static function getDefaultFromDate()
	{
		$dateFrom = new DateTime(date('Y-m-d H:i:s', strtotime('-' . Myshipserv_Config::getPriceBenchmarkDaysRange() . ' day')));
		return $dateFrom;
	}

	/**
	 * Only Admin users and ShipMates are allowed to use Spend Tracker and Price Benchmark
	 * Admin users are only allowed from a list of buyer organisations in the config if the corresponding flag is raised
	 *
	 * @story   DE6832
	 * @author  Yuriy Akopov
	 * @date    2016-07-27
	 *
	 * @param   Myshipserv_Controller_Action    $controller
	 *
	 * @return  bool
	 */
	public static function checkUserAccess(Myshipserv_Controller_Action $controller)
	{
		$user = Shipserv_User::isLoggedIn();

		if ($user instanceof Shipserv_User) {
			if (!$user->canAccessPriceBenchmark()) {
				return false; // user is neither a ShipMate nor a beta access member
			}
		} else {
			return false;   // user is not logged in
		}

		try {
			$controller->getUserBuyerOrg();

		} catch (Exception $e) {
			return false; // user is not a buyer
		}

		return true;
	}

	/**
	 * Returns a base query for purchased products later extended by more targeted calculating functions
	 *
	 * @param   Shipserv_PriceBenchmark_Parameters  $params
	 *
	 * @return  Zend_Db_Select
	 * @throws  Myshipserv_Exception_MessagedException
	 */
	protected function getOrderedLineItemsQuery(Shipserv_PriceBenchmark_Parameters $params)
	{
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('pcl' => Shipserv_ProductLineItem::TABLE_NAME),
				array(
					'ORD_ID'     => 'pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_ID,
					'LI_NO'      => 'pcl.' . Shipserv_ProductLineItem::COL_LINE_ITEM_NO,
					'ORD_DATE'   => new Zend_Db_Expr(
						'TO_CHAR(pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_DATE . ", 'YYYY-MM-DD HH24:MI:SS')"
					),
					'LI_QTY'     => 'pcl.' . Shipserv_ProductLineItem::COL_QUANTITY,
					'UOM'        => 'pcl.' . Shipserv_ProductLineItem::COL_UNIT,
					'TOTAL_COST' => 'pcl.' . Shipserv_ProductLineItem::COL_COST_USD,
					'UNIT_COST'  => new Zend_Db_Expr(
						'ROUND(pcl.' . Shipserv_ProductLineItem::COL_COST_USD . ' / ' .
						'pcl.' . Shipserv_ProductLineItem::COL_QUANTITY . ', 2)'
					)
				)
			)
			->where('pcl.' . Shipserv_ProductLineItem::COL_BUYER_ORG_ID . ' = ?', (int) $this->buyerOrg->id)
			->where(
				'pcl.' . Shipserv_ProductLineItem::COL_CATALOGUE_ID . ' = ?',
				Shipserv_ProductLineItem::CATALOGUE_ID_IMPA
			)
			->where(
				'pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_TYPE . ' = ?',
				Shipserv_ProductLineItem::TRANSACTION_TYPE_ORDER
			)
			->join(
				array('oli' => 'order_line_item'),
				implode(
					' AND ',
					array(
						'oli.oli_order_internal_ref_no = pcl.pcl_transaction_id',
						'oli.oli_order_line_item_no = pcl.pcl_line_item_no'
					)
				),
				array(
					'LI_DESC' => 'oli.oli_desc',
                     'LI_DESC_HASH' => new Zend_Db_Expr('ORA_HASH(oli.oli_desc)')
				)
			)
			->join(
				array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
				'ord.' . Shipserv_PurchaseOrder::COL_ID . ' = pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_ID,
				array(
					'ORD_REF_NO'  => 'ord.' . Shipserv_PurchaseOrder::COL_REF_NO,
					'SUPPLIER_ID' => 'ord.' . Shipserv_PurchaseOrder::COL_SUPPLIER_ID
				)
			)
			->join(
				array('spb' => Shipserv_Supplier::TABLE_NAME),
				'spb.' . Shipserv_Supplier::COL_ID . ' = ord.' . Shipserv_PurchaseOrder::COL_SUPPLIER_ID,
				array(
					'SUPPLIER_NAME' => 'spb.' . Shipserv_Supplier::COL_NAME
				)
			)
		;

		$select = self::addProductsFilter($select, $params->products, 'pcl');
		$select = self::addDateFilter($select, $params->dateFrom, $params->dateTo, 'pcl');
		$select = self::addVesselFilter($select, $params->vesselName, 'ord');
		$select = self::addCountryFilter($select, $params->supplierCountryCodes, 'pcl');
		$select = self::addLineItemDescFilter($select,  $params->lineItemWords, 'oli.oli_desc');
		$select = self::addExcludeFilter($select, Shipserv_ProductLineItem::TRANSACTION_TYPE_ORDER, $params->exclude, $params->excludeRight);

		// DEV-2367 Exclulde pre definied supplier list, Attila O
        $supplierListArray = Myshipserv_Config::getExcludeSuppliersFromImpaReport();

        if ($supplierListArray) {
            $select = self::addExcludeSuppliers($select, $supplierListArray);
        }

		// print $select->assemble(); die;
		
		return $select;
	}

	/**
	 * Returns results for 'Purchases line items' pane of the Price Benchmarking front tab
	 * Which is line item descriptions with the number of matching items and average unit costs
	 *
	 * @param   Shipserv_PriceBenchmark_Parameters  $params
	 *
	 * @return  array
	 */
	public function getOrderedLineItemsBreakdown(Shipserv_PriceBenchmark_Parameters $params)
	{
		$select = self::getOrderedLineItemsQuery($params);
		$select = self::addOrdering($select, $params->sortBy, $params->sortDir);

		// print($select->assemble()); die;

		$cacheKey = Myshipserv_Config::decorateMemcacheKey(
			implode(
				'_',
				array(
					__FUNCTION__,
					md5($select->assemble()),
					$params->pageNo,
					$params->pageSize
				)
			)
		);

		$cache = $this->getMemcache();
		if (($rows = $cache->get($cacheKey)) !== false) {
			return $rows;
		}

		if (is_null($params->pageNo)) {
			$rows = $select->getAdapter()->fetchAll($select);
		} else {
			$paginator = Zend_Paginator::factory($select);
			$paginator->setItemCountPerPage($params->pageSize);
			$paginator->setCurrentPageNumber($params->pageNo);
			$rows = $paginator->getCurrentItems();
		}

		$cache->set($cacheKey, $rows, false, self::MEMCACHE_TTL);

		return $rows;
	}

	/**
	 * @param Shipserv_PriceBenchmark_Parameters $params
	 *
	 * @return array|string
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public function getOrderedCountryBreakdown(Shipserv_PriceBenchmark_Parameters $params)
	{
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('pcl' => Shipserv_ProductLineItem::TABLE_NAME),
				array(
					'COUNTRY_CODE' => 'pcl.' . Shipserv_ProductLineItem::COL_SUPPLIER_COUNTRY,
					'UNIT'         => 'pcl.' . Shipserv_ProductLineItem::COL_UNIT,
					'TOTAL_QTY'    => new Zend_Db_Expr('SUM(pcl.' . Shipserv_ProductLineItem::COL_QUANTITY . ')'),
				)
			)
			->join(
				array('cnt' => Shipserv_Oracle_Countries::TABLE_NAME),
				'cnt.' . Shipserv_Oracle_Countries::COL_CODE_COUNTRY . ' = ' .
				'pcl.' . Shipserv_ProductLineItem::COL_SUPPLIER_COUNTRY,
				array(
					'COUNTRY_NAME' => 'cnt.' . Shipserv_Oracle_Countries::COL_NAME_COUNTRY,
					'CONTINENT_CODE'    => 'cnt.' . Shipserv_Oracle_Countries::COL_CODE_CONTINENT
				)
			)
			->where('pcl.' . Shipserv_ProductLineItem::COL_BUYER_ORG_ID . ' = ?', (int) $this->buyerOrg->id)
			->where(
				'pcl.' . Shipserv_ProductLineItem::COL_CATALOGUE_ID . ' = ?',
				Shipserv_ProductLineItem::CATALOGUE_ID_IMPA
			)
			->where(
				'pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_TYPE . ' = ?',
				Shipserv_ProductLineItem::TRANSACTION_TYPE_ORDER
			)
			->join(
				array('oli' => 'order_line_item'),
				implode(
					' AND ',
					array(
						'oli.oli_order_internal_ref_no = pcl.pcl_transaction_id',
						'oli.oli_order_line_item_no = pcl.pcl_line_item_no'
					)
				),
				array()
			)
			->group(
				array(
					'pcl.' . Shipserv_ProductLineItem::COL_SUPPLIER_COUNTRY,
					'cnt.' . Shipserv_Oracle_Countries::COL_NAME_COUNTRY,
					'cnt.' . Shipserv_Oracle_Countries::COL_CODE_CONTINENT,
					'pcl.' . Shipserv_ProductLineItem::COL_UNIT
				)
			)
		;

        // DEV-2367 Exclulde pre definied supplier list, Attila O
        $supplierListArray = Myshipserv_Config::getExcludeSuppliersFromImpaReport();

        if ($supplierListArray) {

            $select->join(
                array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                'ord.' . Shipserv_PurchaseOrder::COL_ID . ' = pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_ID,
                array()
            );

            $select = self::addExcludeSuppliers($select, $supplierListArray, 'ord', Shipserv_PurchaseOrder::COL_SUPPLIER_ID);
        }

		$select = self::addProductsFilter($select, $params->products, 'pcl');
		$select = self::addDateFilter($select, $params->dateFrom, $params->dateTo, 'pcl');
		$select = self::addVesselFilter($select, $params->vesselName, 'ord');
		$select = self::addCountryFilter($select, $params->supplierCountryCodes, 'pcl');
		$select = self::addLineItemDescFilter($select,  $params->lineItemWords, 'oli.oli_desc');
		$select = self::addExcludeFilter($select, Shipserv_ProductLineItem::TRANSACTION_TYPE_ORDER, $params->exclude, $params->excludeRight);

		// print $select->assemble(); die;
		
		$cache = $this->getMemcache();
		$cacheKey = Myshipserv_Config::decorateMemcacheKey(
			implode(
				'_',
				array(
					__FUNCTION__,
					md5($select->assemble()),
				)
			)
		);

		if (($rows = $cache->get($cacheKey)) !== false) {
			return $rows;
		}

		$rows = $select->getAdapter()->fetchAll($select);

		$cache->set($cacheKey, $rows, false, self::MEMCACHE_TTL);

		return $rows;
	}

	/**
	 * @param   Shipserv_PriceBenchmark_Parameters $params
	 *
	 * @return  array
	 */
	public function getOrderedMonthsBreakdown(Shipserv_PriceBenchmark_Parameters $params)
	{
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('pcl' => Shipserv_ProductLineItem::TABLE_NAME),
				array(
					'MONTH'      => new Zend_Db_Expr(
						'TO_CHAR(pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_DATE . ", 'YYYY-MM')"
					),
					'TOTAL_QTY'  => new Zend_Db_Expr('SUM(pcl.' . Shipserv_ProductLineItem::COL_QUANTITY . ')'),
					'UNIT_COST'  => new Zend_Db_Expr(
						'ROUND(SUM(pcl.' . Shipserv_ProductLineItem::COL_COST_USD . ') / ' .
						'SUM(pcl.' . Shipserv_ProductLineItem::COL_QUANTITY . '), 2)'
					)
				)
			)
			->where('pcl.' . Shipserv_ProductLineItem::COL_BUYER_ORG_ID . ' = ?', (int) $this->buyerOrg->id)
			->where(
				'pcl.' . Shipserv_ProductLineItem::COL_CATALOGUE_ID . ' = ?',
				Shipserv_ProductLineItem::CATALOGUE_ID_IMPA
			)
			->where(
				'pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_TYPE . ' = ?',
				Shipserv_ProductLineItem::TRANSACTION_TYPE_ORDER
			)
			->join(
				array('oli' => 'order_line_item'),
				implode(
					' AND ',
					array(
						'oli.oli_order_internal_ref_no = pcl.pcl_transaction_id',
						'oli.oli_order_line_item_no = pcl.pcl_line_item_no'
					)
				),
				array()
			)
			->group(new Zend_Db_Expr('TO_CHAR(pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_DATE . ", 'YYYY-MM')"))
			->order('MONTH')
		;

        // DEV-2367 Exclulde pre definied supplier list, Attila O
        $supplierListArray = Myshipserv_Config::getExcludeSuppliersFromImpaReport();

        if ($supplierListArray) {

            $select->join(
                array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                'ord.' . Shipserv_PurchaseOrder::COL_ID . ' = pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_ID,
                array()
            );

            $select = self::addExcludeSuppliers($select, $supplierListArray, 'ord', Shipserv_PurchaseOrder::COL_SUPPLIER_ID);
        }

		$select = self::addProductsFilter($select, $params->products, 'pcl');
		$select = self::addDateFilter($select, $params->dateFrom, $params->dateTo, 'pcl');
		$select = self::addVesselFilter($select, $params->vesselName, 'ord');
		$select = self::addCountryFilter($select, $params->supplierCountryCodes, 'pcl');
		$select = self::addLineItemDescFilter($select,  $params->lineItemWords, 'oli.oli_desc');
		$select = self::addExcludeFilter($select, Shipserv_ProductLineItem::TRANSACTION_TYPE_ORDER, $params->exclude, $params->excludeRight);

		// print $select->assemble(); die;
		
		$cache = $this->getMemcache();
		$cacheKey = Myshipserv_Config::decorateMemcacheKey(
			implode(
				'_',
				array(
					__FUNCTION__,
					md5($select->assemble()),
				)
			)
		);

		if (($rows = $cache->get($cacheKey)) !== false) {
			return $rows;
		}

		$rows = $select->getAdapter()->fetchAll($select);

		$cache->set($cacheKey, $rows, false, self::MEMCACHE_TTL);

		return $rows;
	}
	

	/**
	 * Calculates average unit price across the matching orders
	 *
	 * @param   Shipserv_PriceBenchmark_Parameters $params
	 *
	 * @return  float
	 */
	public function getOrderedAveragePrice(Shipserv_PriceBenchmark_Parameters $params)
	{
		$select = self::getOrderedLineItemsQuery($params);

		$selectTotal = new Zend_Db_Select($select->getAdapter());
		$selectTotal
			->from(
				array('total' => $select),
				array(
					'UNIT_COST' => new Zend_Db_Expr('ROUND(SUM(TOTAL_COST) / SUM(LI_QTY), 2)')
				)
			)
		;

		// print($selectTotal->assemble()); die;

		$cache = $this->getMemcache();
		$cacheKey = Myshipserv_Config::decorateMemcacheKey(
			implode(
				'_',
				array(
					__FUNCTION__,
					md5($selectTotal->assemble()),
				)
			)
		);

		if (($averagePrice = $cache->get($cacheKey)) !== false) {
			return $averagePrice;
		}

		$averagePrice = $selectTotal->getAdapter()->fetchOne($selectTotal);
		if (strlen($averagePrice) === 0) {
			$averagePrice = null;
		} else {
			$averagePrice = (float) $averagePrice;
		}

		$cache->set($cacheKey, $averagePrice, false, self::MEMCACHE_TTL);

		return $averagePrice;
	}	

	/**
	 * Returns a base query for quoted products later extended by more targeted calculating functions
	 *
	 * @param   Shipserv_PriceBenchmark_Parameters  $params
	 *
	 * @return  Zend_Db_Select
	 */
	protected function getQuotedLineItemsQuery(Shipserv_PriceBenchmark_Parameters $params)
	{
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('pcl' => Shipserv_ProductLineItem::TABLE_NAME),
				array(
					'LI_COUNT'   => new Zend_Db_Expr('COUNT(pcl.' . Shipserv_ProductLineItem::COL_ID . ')'),
					'TOTAL_QTY'  => new Zend_Db_Expr('SUM(pcl.' . Shipserv_ProductLineItem::COL_QUANTITY . ')'),
					'TOTAL_COST' => new Zend_Db_Expr('SUM(pcl.' . Shipserv_ProductLineItem::COL_COST_USD . ')'),
					'UNIT_COST'  => new Zend_Db_Expr(
						'ROUND(SUM(pcl.' . Shipserv_ProductLineItem::COL_COST_USD . ') / ' .
						'SUM(pcl.' . Shipserv_ProductLineItem::COL_QUANTITY . '), 2)'
					)
				)
			)
			->where('pcl.' . Shipserv_ProductLineItem::COL_BUYER_ORG_ID . ' <> ?', (int) $this->buyerOrg->id)
			->where(
				'pcl.' . Shipserv_ProductLineItem::COL_CATALOGUE_ID . ' = ?',
				Shipserv_ProductLineItem::CATALOGUE_ID_IMPA
			)
			->where(
				'pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_TYPE . ' = ?',
				Shipserv_ProductLineItem::TRANSACTION_TYPE_QUOTE
			)
			->join(
				array('qli' => 'quote_line_item'),
				implode(
					' AND ',
					array(
						'qli.qli_qot_internal_ref_no = pcl.pcl_transaction_id',
						'qli.qli_line_item_number = pcl.pcl_line_item_no'
					)
				),
				array(
					'LI_DESC' => new Zend_Db_Expr('LOWER(qli.qli_desc)'),
				    'LI_DESC_HASH' => new Zend_Db_Expr('ORA_HASH(qli.qli_desc)')
				)
			)
		;


        // DEV-2367 Exclulde pre definied supplier list, Attila O
        $supplierListArray = Myshipserv_Config::getExcludeSuppliersFromImpaReport();

        if ($supplierListArray) {

            $select->join(
                array('qot' => Shipserv_Quote::TABLE_NAME),
                'qot.' . Shipserv_Quote::COL_ID . ' = pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_ID,
                array()
            );

            $select = self::addExcludeSuppliers($select, $supplierListArray, 'qot', Shipserv_Quote::COL_SUPPLIER_ID);
        }

		$select = self::addProductsFilter($select, $params->products, 'pcl');
		$select = self::addDateFilter($select, $params->dateFrom, $params->dateTo, 'pcl');
		$select = self::addCountryFilter($select, $params->supplierCountryCodes, 'pcl');
		$select = self::addLineItemDescFilter($select, $params->lineItemWords, 'qli.qli_desc');
		$select = self::addExcludeFilter($select, Shipserv_ProductLineItem::TRANSACTION_TYPE_QUOTE, $params->exclude, $params->excludeRight);

		// print $select->assemble(); die;
		
		return $select;
	}

	/**
	 * @param   Shipserv_PriceBenchmark_Parameters $params
	 *
	 * @return  array
	 */
	public function getQuotedMonthsBreakdown(Shipserv_PriceBenchmark_Parameters $params)
	{
		$select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$select
			->from(
				array('pcl' => Shipserv_ProductLineItem::TABLE_NAME),
				array(
					'MONTH' => new Zend_Db_Expr(
						'TO_CHAR(pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_DATE . ", 'YYYY-MM')"
					),
					'TOTAL_QTY' => new Zend_Db_Expr('SUM(pcl.' . Shipserv_ProductLineItem::COL_QUANTITY . ')'),
					'UNIT_COST'  => new Zend_Db_Expr(
						'ROUND(SUM(pcl.' . Shipserv_ProductLineItem::COL_COST_USD . ') / ' .
						'SUM(pcl.' . Shipserv_ProductLineItem::COL_QUANTITY . '), 2)'
					)
				)
			)
			->where('pcl.' . Shipserv_ProductLineItem::COL_BUYER_ORG_ID . ' <> ?', (int) $this->buyerOrg->id)
			->where(
				'pcl.' . Shipserv_ProductLineItem::COL_CATALOGUE_ID . ' = ?',
				Shipserv_ProductLineItem::CATALOGUE_ID_IMPA
			)
			->where(
				'pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_TYPE . ' = ?',
				Shipserv_ProductLineItem::TRANSACTION_TYPE_QUOTE
			)
			->join(
				array('qli' => 'quote_line_item'),
				implode(
					' AND ',
					array(
						'qli.qli_qot_internal_ref_no = pcl.pcl_transaction_id',
						'qli.qli_line_item_number = pcl.pcl_line_item_no'
					)
				),
				array()
			)
			->group(new Zend_Db_Expr('TO_CHAR(pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_DATE . ", 'YYYY-MM')"))
			->order('MONTH')
		;

        // DEV-2367 Exclulde pre definied supplier list, Attila O
        $supplierListArray = Myshipserv_Config::getExcludeSuppliersFromImpaReport();

        if ($supplierListArray) {

            $select->join(
                array('qot' => Shipserv_Quote::TABLE_NAME),
                'qot.' . Shipserv_Quote::COL_ID . ' = pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_ID,
                array()
            );

            $select = self::addExcludeSuppliers($select, $supplierListArray, 'qot', Shipserv_Quote::COL_SUPPLIER_ID);
        }

        $select = self::addProductsFilter($select, $params->products, 'pcl');
		$select = self::addDateFilter($select, $params->dateFrom, $params->dateTo, 'pcl');
		$select = self::addCountryFilter($select, $params->supplierCountryCodes, 'pcl');
		$select = self::addLineItemDescFilter($select, $params->lineItemWords, 'qli.qli_desc');
		$select = self::addExcludeFilter($select, Shipserv_ProductLineItem::TRANSACTION_TYPE_QUOTE, $params->exclude, $params->excludeRight);

		// print $select->assemble(); die;

		$cache = $this->getMemcache();
		$cacheKey = Myshipserv_Config::decorateMemcacheKey(
			implode(
				'_',
				array(
					__FUNCTION__,
					md5($select->assemble()),
				)
			)
		);

		if (($rows = $cache->get($cacheKey)) !== false) {
			return $rows;
		}

		$rows = $select->getAdapter()->fetchAll($select);

		$cache->set($cacheKey, $rows, false, self::MEMCACHE_TTL);

		return $rows;
	}

	/**
	 * Calculates average unit price across all the market quotes
	 *
	 * @param   Shipserv_PriceBenchmark_Parameters $params
	 *
	 * @return  float
	 */
	public function getQuotedAveragePrice(Shipserv_PriceBenchmark_Parameters $params)
	{
		$select = self::getQuotedLineItemsQuery($params);
		$select->group(
		    array(
		        new Zend_Db_Expr('LOWER(qli.qli_desc)'),
		        new Zend_Db_Expr('ORA_HASH(qli.qli_desc)')
		    )
		);
		
		$selectTotal = new Zend_Db_Select($select->getAdapter());
		$selectTotal
			->from(
				array('total' => $select),
				array(
					'UNIT_COST' => new Zend_Db_Expr('ROUND(SUM(TOTAL_COST) / SUM(TOTAL_QTY), 2)')
				)
			)
		;

		// print($selectTotal->assemble()); die;

		$cache = $this->getMemcache();
		$cacheKey = Myshipserv_Config::decorateMemcacheKey(
			implode(
				'_',
				array(
					__FUNCTION__,
					md5($selectTotal->assemble()),
				)
			)
		);

		if (($averagePrice = $cache->get($cacheKey)) !== false) {
			return $averagePrice;
		}

		$averagePrice = $selectTotal->getAdapter()->fetchOne($selectTotal);
		if (strlen($averagePrice) === 0) {
			$averagePrice = null;
		} else {
			$averagePrice = (float) $averagePrice;
		}

		$cache->set($cacheKey, $averagePrice, false, self::MEMCACHE_TTL);

		return $averagePrice;
	}

	/**
	 * Returns results for 'Quoted line items' pane of the Price Benchmarking front tab
	 * Which is line item descriptions with the number of matching items and average unit costs
	 *
	 * @param   Shipserv_PriceBenchmark_Parameters $params
	 *
	 * @return  array
	 */
	public function getQuotedLineItemsBreakdown(Shipserv_PriceBenchmark_Parameters $params)
	{
		$select = self::getQuotedLineItemsQuery($params);

		$select->group(
		    array(
		        new Zend_Db_Expr('LOWER(qli.qli_desc)'),
		        new Zend_Db_Expr('ORA_HASH(qli.qli_desc)')
		    )
		);

		$select = self::addOrdering($select, $params->sortBy, $params->sortDir);

		// print($select->assemble()); die;

		$cache = $this->getMemcache();
		$cacheKey = Myshipserv_Config::decorateMemcacheKey(
			implode(
				'_',
				array(
					__FUNCTION__,
					md5($select->assemble()),
					$params->pageNo,
					$params->pageSize
				)
			)
		);

		if (($rows = $cache->get($cacheKey)) !== false) {
			return $rows;
		}

		if (is_null($params->pageNo)) {
			$rows = $select->getAdapter()->fetchAll($select);
		} else {
			$paginator = Zend_Paginator::factory($select);
			$paginator->setCurrentPageNumber($params->pageNo);
			$paginator->setItemCountPerPage($params->pageSize);
			$rows = $paginator->getCurrentItems();
		}

		$cache->set($cacheKey, $rows, false, self::MEMCACHE_TTL);

		return $rows;
	}

	/**
	 * Returns the products ordered by the given buyer in the time interval etc. along with the information about
	 * how much they have (over/under)paid comparing to average quote prices for the same products
	 *
	 * @param   Shipserv_PriceBenchmark_Parameters  $params
	 * @param   int                                 $total
	 *
	 * @return  array
	 */
	public function getTrackedProducts(Shipserv_PriceBenchmark_Parameters $params, &$total = null)
	{
		// selects the products bought by the current buyer
		$selectOrd = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
		$selectOrd
			->from(
				array('pcl_ord' => Shipserv_ProductLineItem::TABLE_NAME),
				array(
					'PART_NO'       => 'pcl_ord.' . Shipserv_ProductLineItem::COL_PART_NO,
					'UOM'           => 'pcl_ord.' . Shipserv_ProductLineItem::COL_UNIT,
					'ORD_LI_COUNT'  => new Zend_Db_Expr('COUNT(pcl_ord.' . Shipserv_ProductLineItem::COL_ID . ')'),
					'ORD_TOTAL_QTY' => new Zend_Db_Expr('SUM(pcl_ord.' . Shipserv_ProductLineItem::COL_QUANTITY . ')'),
					'ORD_UNIT_COST' => new Zend_Db_Expr('CASE
						WHEN SUM(pcl_ord.' . Shipserv_ProductLineItem::COL_QUANTITY . ') > 0 THEN
							ROUND(SUM(pcl_ord.' . Shipserv_ProductLineItem::COL_COST_USD . ') /
							SUM(pcl_ord.' . Shipserv_ProductLineItem::COL_QUANTITY . '), 2)
						ELSE NULL
						END'
					),
					'ORD_TOTAL_COST' => new Zend_Db_Expr('SUM(pcl_ord.' . Shipserv_ProductLineItem::COL_COST_USD . ')')
				)
			)
			->where('pcl_ord.' . Shipserv_ProductLineItem::COL_CATALOGUE_ID . ' = ?', Shipserv_ProductLineItem::CATALOGUE_ID_IMPA)
			->where('pcl_ord.' . Shipserv_ProductLineItem::COL_TRANSACTION_TYPE . ' = ?', Shipserv_ProductLineItem::TRANSACTION_TYPE_ORDER)
			->where('pcl_ord.' . Shipserv_ProductLineItem::COL_BUYER_ORG_ID . ' = ?', (int) $this->buyerOrg->id)
			->group(
				array(
					'pcl_ord.' . Shipserv_ProductLineItem::COL_PART_NO,
					'pcl_ord.' . Shipserv_ProductLineItem::COL_UNIT,
				)
			)
		;

        // DEV-2367 Exclulde pre definied supplier list, Attila O
        $supplierListArray = Myshipserv_Config::getExcludeSuppliersFromImpaReport();

        if ($supplierListArray) {

            $selectOrd->join(
                array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                'ord.' . Shipserv_PurchaseOrder::COL_ID . ' = pcl_ord.' . Shipserv_ProductLineItem::COL_TRANSACTION_ID,
                array()
            );

            $selectOrd = self::addExcludeSuppliers($selectOrd, $supplierListArray, 'ord', Shipserv_PurchaseOrder::COL_SUPPLIER_ID);
        }

		// DE7417: by Yuriy Akopov, 2017-07-27
        $pciJoined = false;
        if (!self::isParamListEmpty($params->productWords)) {
            // since there is a filter of product name we need to JOIN the table with those names
            $selectOrd->join(
                array('pci' => Shipserv_Oracle_ImpaCatalogue::TABLE_NAME),
                'pci.' . Shipserv_Oracle_ImpaCatalogue::COL_ID . ' = pcl_ord.' . Shipserv_ProductLineItem::COL_PART_NO,
                array(
                    // MAXing it might be faster that adding to GROUP BY clause
                    'PART_DESC' => new Zend_Db_Expr('MAX(pci.' . Shipserv_Oracle_ImpaCatalogue::COL_DESC . ')')
                )
            );

            $selectOrd = self::addProductDescFilter($selectOrd, $params->productWords, 'pci');

            // leaving a note to ourselves that description string is already here and we don't need to retrieve it again
            $pciJoined = true;
        }

		$selectOrd = self::addDateFilter($selectOrd, $params->dateFrom, null, 'pcl_ord');

		// retrieve columns returned by subquery
		$ordColumns = $selectOrd->getPart(Zend_Db_Select::COLUMNS);
		$groupColumns = array();
		foreach ($ordColumns as $fieldBits) {
			$groupColumns[] = 'pcl_ord.' . $fieldBits[count($fieldBits) - 1];
		}

        $selectColumns = $groupColumns;

		// add field-level subquery for part number
        // changed from JOIN to field-level subquery for performance by Yuriy Akopov on 2017-07-26, S20510
        if (!$pciJoined) {
            // retrieve the description 'on the fly'
            $selectPartDesc = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
            $selectPartDesc
                ->from(
                    array('pci' => Shipserv_Oracle_ImpaCatalogue::TABLE_NAME),
                    array(
                        'PART_DESC' => 'pci.' . Shipserv_Oracle_ImpaCatalogue::COL_DESC
                    )
                )
                ->where('pci.' . Shipserv_Oracle_ImpaCatalogue::COL_ID . ' = pcl_ord.part_no')
            ;

            $selectColumns[]  = new Zend_Db_Expr('(' . $selectPartDesc->assemble() . ') AS PART_DESC') ;
        }

		$select = new Zend_Db_Select($selectOrd->getAdapter());
		$select
			->from(
				array('pcl_ord' => $selectOrd),
                $selectColumns
			)
			->join(
				array('pcl_qot' => Shipserv_ProductLineItem::TABLE_NAME),
				implode(
					' AND ',
					array(
						'pcl_qot.' . Shipserv_ProductLineItem::COL_PART_NO . ' = pcl_ord.part_no',
						'pcl_qot.' . Shipserv_ProductLineItem::COL_UNIT . ' = pcl_ord.uom'
					)
				),
				array(
					'QOT_UNIT_COST' => new Zend_Db_Expr('CASE
						WHEN SUM(pcl_qot.' . Shipserv_ProductLineItem::COL_QUANTITY . ') > 0 THEN
							ROUND(SUM(pcl_qot.' . Shipserv_ProductLineItem::COL_COST_USD . ') /
							SUM(pcl_qot.' . Shipserv_ProductLineItem::COL_QUANTITY . '), 2)
						ELSE NULL
						END'
					),
					'SAVINGS' => new Zend_Db_Expr('CASE
						WHEN SUM(pcl_qot.' . Shipserv_ProductLineItem::COL_QUANTITY . ') > 0 THEN
						ROUND(
							(
								SUM(pcl_qot.' . Shipserv_ProductLineItem::COL_COST_USD . ') /
								SUM(pcl_qot.' . Shipserv_ProductLineItem::COL_QUANTITY . ') *
								pcl_ord.ord_total_qty
							)
							- pcl_ord.ord_total_cost, 2
						)
						ELSE NULL
						END'
					)
				)
			)
            ->where('pcl_qot.' . Shipserv_ProductLineItem::COL_CATALOGUE_ID . ' = ?', Shipserv_ProductLineItem::CATALOGUE_ID_IMPA)
            ->where('pcl_qot.' . Shipserv_ProductLineItem::COL_TRANSACTION_TYPE . ' = ?', Shipserv_ProductLineItem::TRANSACTION_TYPE_QUOTE)
            ->where('pcl_qot.' . Shipserv_ProductLineItem::COL_BUYER_ORG_ID . ' <> ?', (int) $this->buyerOrg->id)
			->group($groupColumns)
		;

        // DEV-2367 Exclulde pre definied supplier list, Attila O

        if ($supplierListArray) {

            $select->join(
                array('qot' => Shipserv_Quote::TABLE_NAME),
                'qot.' . Shipserv_Quote::COL_ID . ' = pcl_qot.' . Shipserv_ProductLineItem::COL_TRANSACTION_ID,
                array()
            );

            $select = self::addExcludeSuppliers($select, $supplierListArray, 'qot', Shipserv_Quote::COL_SUPPLIER_ID);
        }

		$select = self::addDateFilter($select, $params->dateFrom, null, 'pcl_qot');

		if (is_null($params->savings)) {
			$select->order('savings ASC');
		} else {
			$selectSavings = clone($select);
			$select = new Zend_Db_Select($selectSavings->getAdapter());
			$select
				->from(
					array('stats' => $selectSavings),
					'*'
				)
			;

			if ($params->savings) {
				$select
					->where('savings > 0')
					->order('savings DESC NULLS LAST')
				;
			} else {
				$select
					->where('savings < 0')
					->order('savings ASC NULLS LAST')
				;
			}
		}

		// print $select->assemble(); die;

		$cacheKey = Myshipserv_Config::decorateMemcacheKey(
			implode(
				'_',
				array(
					__FUNCTION__,
					md5($select->assemble()),
					$params->pageNo,
					$params->pageSize
				)
			)
		);

		$cache = $this->getMemcache();
		if (($rows = $cache->get($cacheKey)) !== false) {
			return $rows;
		}

		if (is_null($params->pageNo)) {
			$rows = $select->getAdapter()->fetchAll($select);
			$total = count($rows);
		} else {
			$paginator = Zend_Paginator::factory($select);
			$paginator->setItemCountPerPage($params->pageSize);
			$paginator->setCurrentPageNumber($params->pageNo);

			$rows = $paginator->getCurrentItems();
			$total = $paginator->getTotalItemCount();
		}

		$cache->set($cacheKey, $rows, false, self::MEMCACHE_TTL);

		return $rows;
	}

	/**
	 * Applies (IMPA) product and UOM constraint to the given query
	 *
	 * @param   Zend_Db_Select  $select
	 * @param   array           $products
	 * @param   string          $prefix
	 *
	 * @return  Zend_Db_Select
	 */
	public static function addProductsFilter(Zend_Db_Select $select, array $products, $prefix = 'pcl')
	{
		$where = array();
		foreach ($products as $code => $units) {
			$where[] = implode(
				' AND ',
				array(
					$select->getAdapter()->quoteInto(
						$prefix . '.' . Shipserv_ProductLineItem::COL_PART_NO . ' = ?', (string) $code
					),
					$select->getAdapter()->quoteInto(
						$prefix . '.' . Shipserv_ProductLineItem::COL_UNIT . ' IN (?)', $units
					)
				)
			);
		}

		$select->where('(' . implode(') OR (', $where) . ')');

		return $select;
	}

    /**
     * Returns true if the supplier parameters is NULL or an empty array
     *
     * @author  Yuriy Akopov
     * @date    2017-07-27
     * @story   DE7417
     *
     * @param   array|string    $paramList
     *
     * @return  bool
     */
	public static function isParamListEmpty($paramList)
    {
        return (is_null($paramList) or (is_array($paramList) and empty($paramList)));
    }

	/**
	 * Adds constraints to select IMPA products with specific keywords supplied
	 *
	 * @param   Zend_Db_Select  $select
	 * @param   array|string    $words
	 * @param   string          $prefix
	 *
	 * @return  Zend_Db_Select
	 */
	public static function addProductDescFilter(Zend_Db_Select $select, $words, $prefix = 'pci')
	{
		if (self::isParamListEmpty($words)) {
			return $select;
		}

		if (!is_array($words)) {
			$words = array($words);
		}

		$where = array();
		foreach ($words as $word) {
			$whereBits = array();

			if (is_numeric($word)) {
				if (strlen($word) === 6) {
					// this might to be a full IMPA code
					$whereBits[] = $select->getAdapter()->quoteInto(
						$prefix . '.' . Shipserv_Oracle_ImpaCatalogue::COL_ID . ' = ?', $word
					);
				} else if (strlen($word) < 6) {
					// this might be a partial IMPA code
					$likeStatement = Shipserv_Helper_Database::escapeLike(
						$select->getAdapter(), $word, Shipserv_Helper_Database::ESCAPE_LIKE_RIGHT
					);
					$whereBits[] = $prefix . '.' . Shipserv_Oracle_ImpaCatalogue::COL_ID . ' ' . $likeStatement;
				}
			}

			$likeStatement = Shipserv_Helper_Database::escapeLike(
				$select->getAdapter(), strtoupper($word), Shipserv_Helper_Database::ESCAPE_LIKE_BOTH
			);
			$whereBits[] = 'UPPER(' . $prefix . '.' . Shipserv_Oracle_ImpaCatalogue::COL_DESC . ') ' . $likeStatement;

			$where[] = '(' . implode(' OR ', $whereBits) . ')';
		}

		$select->where(implode(' AND ', $where));

		return $select;
	}

	/**
	 * @param Zend_Db_Select $select
	 * @param DateTime|null $dateFrom
	 * @param DateTime|null $dateTo
	 * @param string $prefix
	 * @return Zend_Db_Select
	 * @throws Shipserv_Helper_Database_Exception
	 */
	public static function addDateFilter(Zend_Db_Select $select, DateTime $dateFrom = null, DateTime $dateTo = null,
	                                     $prefix = 'pcl')
	{
		if ($dateFrom) {
			$select->where(
				'TRUNC(' . $prefix . '.' . Shipserv_ProductLineItem::COL_TRANSACTION_DATE . ') >= TRUNC(' .
				Shipserv_Helper_Database::getOracleDateExpr($dateFrom, true) . ')'
			);
		}

		if ($dateTo) {
			$select->where(
				'TRUNC(' . $prefix . '.' . Shipserv_ProductLineItem::COL_TRANSACTION_DATE . ') < TRUNC(' .
				Shipserv_Helper_Database::getOracleDateExpr($dateTo, true) . ')'
			);
		}

		return $select;
	}

	/**
	 * @param Zend_Db_Select $select
	 * @param array $countryCodes
	 * @param string $prefix
	 * @return Zend_Db_Select
	 */
	public static function addCountryFilter(Zend_Db_Select $select, array $countryCodes, $prefix = 'pcl')
	{
		if (!empty($countryCodes)) {
			$select->where($prefix . '.' . Shipserv_ProductLineItem::COL_SUPPLIER_COUNTRY . ' IN (?)', $countryCodes);
		}

		return $select;
	}

	/**
	 * @param Zend_Db_Select $select
	 * @param $vesselName
	 * @param string $prefix
	 * @return Zend_Db_Select
	 */
	public static function addVesselFilter(Zend_Db_Select $select, $vesselName, $prefix = 'ord')
	{
		if (!is_null($vesselName)) {
			$from = $select->getPart(Zend_Db_Select::FROM);
			if (!array_key_exists($prefix, $from)) {
				$select->join(
					array($prefix => Shipserv_PurchaseOrder::TABLE_NAME),
					$prefix . '.' . Shipserv_PurchaseOrder::COL_ID . ' = pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_ID,
					array()
				);
			}

			$select->where(
				'UPPER(' . $prefix . '.' . Shipserv_PurchaseOrder::COL_VESSEL_NAME . ') = ?', strtoupper($vesselName)
			);
		}

		return $select;
	}

	/**
	 * @param Zend_Db_Select $select
	 * @param array $words
	 * @param $field
	 * @return Zend_Db_Select
	 * @throws Exception
	 */
	public static function addLineItemDescFilter(Zend_Db_Select $select, array $words, $field)
	{
		if (empty($words)) {
			return $select;
		}

		$where = array();
		foreach ($words as $word) {
			$where[] = 'LOWER(' . $field . ') ' . Shipserv_Helper_Database::escapeLike(
					$select->getAdapter(), strtolower($word), Shipserv_Helper_Database::ESCAPE_LIKE_BOTH
				);
		}

		$select->where(implode(' AND ', $where));

		return $select;
	}

	/**
	 * @param Zend_Db_Select $select
	 * @param $transactionType
	 * @param array $toExclude
	 * @return Zend_Db_Select
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public static function addExcludeFilter(Zend_Db_Select $select, $transactionType, array $toExclude, array $toExcludeRight)
	{
		if (empty($toExclude) && empty($toExcludeRight)) {
			return $select;
		}

		switch ($transactionType) {
			
			case Shipserv_ProductLineItem::TRANSACTION_TYPE_QUOTE:
				// quoted items are excluded by line item description
				foreach ($toExclude as $index => $str) {
					$toExclude[$index] = strtolower($str);
				}
				
				$select->where('ORA_HASH(qli.qli_desc) NOT IN (?)', (count($toExclude) > 0) ? $toExclude : $toExcludeRight);
				break;
			
			case Shipserv_ProductLineItem::TRANSACTION_TYPE_ORDER:
				
				if (!empty($toExclude)) {
					// order line items are excluded by transaction item and line item number
					foreach ($toExclude AS $orderId => $lineItemNumbers) {
						$select->where(
							'NOT(' .
							implode(
								' AND ',
								array(
									$select->getAdapter()->quoteInto(
										'pcl.' . Shipserv_ProductLineItem::COL_TRANSACTION_ID . ' = ?', $orderId
									),
									$select->getAdapter()->quoteInto(
										'pcl.' . Shipserv_ProductLineItem::COL_LINE_ITEM_NO . ' IN (?)', $lineItemNumbers
									)
								)
							) .
							')'
						);
					}
				} 
				
				if (!empty($toExcludeRight)) {
					foreach ($toExcludeRight as $index => $str) {
						$toExcludeRight[$index] = strtolower($str);
					}
					
					$select->where('ORA_HASH(oli.oli_desc) NOT IN (?)', $toExcludeRight);
				}
				
				break;

			default:
				throw new Myshipserv_Exception_MessagedException("Failed to apply line item description filters");
		}

		return $select;
	}

    /**
     * Add exclude suppilier list
     *
     * @param Zend_Db_Select $select
     * @param $transactionType
     * @return Zend_Db_Select
     * @throws Myshipserv_Exception_MessagedException
     */
    public static function addExcludeSuppliers(Zend_Db_Select $select, $supplierListArray,  $tableAlias = 'spb', $fieldname = Shipserv_Supplier::COL_ID)
    {
	    if ($supplierListArray) {
            $select->where($tableAlias . '.' . $fieldname . ' NOT IN (?)', $supplierListArray);
        }

        return $select;
    }

	/**
	 * @param Zend_Db_Select $select
	 * @param $sortBy
	 * @param string $sortDir
	 * @return Zend_Db_Select
	 * @throws Myshipserv_Exception_MessagedException
	 * @throws Zend_Db_Select_Exception
	 */
	public static function addOrdering(Zend_Db_Select $select, $sortBy, $sortDir = 'asc')
	{
		if (is_null($sortBy)) {
			return $select;
		}

		$columns = $select->getPart(Zend_Db_Select::COLUMNS);
		$colNames = array();
		foreach ($columns as $bits) {
			$colNames[] = strtolower($bits[count($bits) - 1]);
		}
		
		if (!in_array(strtolower($sortBy), $colNames)) {
			throw new Myshipserv_Exception_MessagedException(
				"Invalid sorting parameter supplier to price benchmarking"
			);
		}
		
		if (is_null($sortDir)) {
			$sortDir = 'asc';
		}
		
		if (!in_array(strtolower($sortDir), array('asc', 'desc'))) {
			throw new Myshipserv_Exception_MessagedException(
				"Invalid sorting direction parameter supplier to price benchmarking"
			);
		}
		
		$select->order($sortBy . ' ' . $sortDir);
		
		return $select;
	}

	/**
	 * @param   Shipserv_PriceBenchmark_Parameters  $params
	 * 
	 * @return  array
	 */
	public static function getMonthsList(Shipserv_PriceBenchmark_Parameters $params)
	{
		$date = $params->dateFrom;
		
		if (is_null($params->dateTo)) {
			$dateTo = new DateTime();
		} else {
			$dateTo = $params->dateTo;
		}
		
		$months = array();
		
		while ($date < $dateTo) {
			$months[] = $date->format('Y-m');
			$date->modify('+1 month');
		}
		
		return $months;
	}

}