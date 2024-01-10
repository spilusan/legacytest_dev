<?php
/**
 * Controller to house Price Benchmarking webservices
 *
 * Initial idea was to put them in Buyer module, but that module was created in a different branch, so a new one has been
 * created for time being. In future after the branches are merged into release 4.0 this module might be removed
 *
 * @author  Yuriy Akopov
 * @date    2013-12-11
 * @story   S8855
 */
require_once('AbstractController.php');

class PriceBenchmark_ServiceController extends PriceBenchmark_AbstractController {
    /**
     * Returns a structure of selected IMPA codes and UOMs supplied with the request
     *
     * @return array
     * @throws Myshipserv_Exception_MessagedException
     */
    protected function _getProductsParameter()
    {
        $productParams = $this->_getParam('products', null);

        if (is_null($productParams) or !is_array($productParams) or empty($productParams)) {
            throw new Myshipserv_Exception_MessagedException("At least one IMPA code is expected");
        }

        $products = array();
        foreach ($productParams as $param) {
            if (!array_key_exists($param['impa'], $products)) {
                $products[$param['impa']] = array();
            }

            $products[$param['impa']][] = $param['unit'];
        }

        return $products;
    }

	/**
	 * Reads and validates DateFrom parameter from user filters
	 *
	 * @return DateTime
	 * @throws Myshipserv_Exception_MessagedException
	 */
	protected function _getDateFromParameter()
	{
		$filters = $this->_getParam('filter');

		if (!array_key_exists('dateFrom', $filters) or (strlen($filters['dateFrom']) === 0)) {
			throw new Myshipserv_Exception_MessagedException("No start data supplied for price benchmarking");
		}

		$dateFrom = new DateTime($filters['dateFrom']);
		$minDateFrom = Shipserv_PriceBenchmark::getDefaultFromDate();

		$dateStr = $dateFrom->format('Y-m-d');
		$minDateStr = $minDateFrom->format('Y-m-d');
		if ($dateStr < $minDateStr) {
			throw new Myshipserv_Exception_MessagedException("Supplied from date is beyound the allowed threshold");
		}

		return $dateFrom;
	}

	/**
	 * @return DateTime
	 */
	protected function _getDateToParameter()
	{
		$filters = $this->_getParam('filter');

		if (!array_key_exists('dateTo', $filters) or (strlen($filters['dateTo']) === 0)) {
			return new DateTime();
		}

		return new DateTime($filters['dateTo']);
	}

	/**
	 * @return null
	 */
	protected function _getVesselNameParameter()
	{
		$filters = $this->_getParam('filter');

		if (!array_key_exists('vessel', $filters)) {
			return null;
		}

		if (strlen($filters['vessel']) === 0) {
			return null;
		}

		return $filters['vessel'];
	}

	/**
	 * @return array
	 */
	protected function _getLocationsParameter() {
		$filters = $this->_getParam('filter');

		if (!array_key_exists('location', $filters) or (!is_array($filters['location']) and (strlen($filters['location']) === 0))) {
			return array();
		}

		$countryCodes = $filters['location'];
		if (!is_array($countryCodes)) {
			$countryCodes = array($countryCodes);
		}

		return $countryCodes;
	}

	/**
	 * @return array
	 */
	protected function _getRefineQueryParameter()
	{
		$filters = $this->_getParam('filter');

		if (!array_key_exists('refineQuery', $filters) or (strlen($filters['refineQuery']) === 0)) {
			return array();
		}

		$wordBits = explode(" ", $filters['refineQuery']);

		$words = array();
		foreach ($wordBits as $bit) {
			if (strlen($bit)) {
				$words[] = $bit;
			}
		}

		return $words;
	}

	/**
	 * @param object &params (Not mandantory, if set, it must be an instance of Shipserv_PriceBenchmark_Parameters, and the proper exclude type will be set
	 * @return array
	 */
	protected function _getExcludeDescriptionsParameter(&$params = null)
	{
		$filters = $this->_getParam('filter');
		
		if (!array_key_exists('excludeRight', $filters) or (!is_array($filters['excludeRight']) and (strlen($filters['excludeRight']) === 0))) {
			return array();
		}
		
		if ($params instanceof  Shipserv_PriceBenchmark_Parameters) {
			$params->excludeRight = $filters['excludeRight'];
		}
		return $filters['excludeRight'];
	}

	/**
	 * @param object &params (Not mandantory, if set, it must be an instance of Shipserv_PriceBenchmark_Parameters, and the proper exclude type will be set
	 * @return array
	 */
	protected function _getExcludeIdsParameter(&$params = null)
	{
		$filters = $this->_getParam('filter');

		if (array_key_exists('excludeRight', $filters)) {
			
			$filters['exclude'] = $filters['excludeRight'];
			unset($filters['excludeRight']);
			$this->_setParam('filter', $filters);
			
			return $this->_getExcludeDescriptionsParameter($params);
			
		}
		
		if (!array_key_exists('exclude', $filters) or (!is_array($filters['exclude']) and (strlen($filters['exclude']) === 0))) {
			return array();
		}
		
		$toExclude = array();
		foreach($filters['exclude'] as $mergedIds) {
			$ids = explode('_', $mergedIds);
			$orderId = $ids[0];
			$lineItemNo = $ids[1];
			
			if (!array_key_exists($orderId, $toExclude)) {
				$toExclude[$orderId] = array();
			}
			
			$toExclude[$orderId][] = $lineItemNo;
		}

		if ($params instanceof  Shipserv_PriceBenchmark_Parameters) {
			$params->exclude = $toExclude;
		}
		
		return $toExclude;
	}

	/**
	 * @param   string  $transactionType
	 *
	 * @return  Shipserv_PriceBenchmark_Parameters 
	 * @throws  Myshipserv_Exception_MessagedException
	 */
	protected function _getPriceBenchmarkingParameters($transactionType)
	{
		$params = new Shipserv_PriceBenchmark_Parameters();

		$params->products = $this->_getProductsParameter();

		$params->dateFrom = $this->_getDateFromParameter();
		$params->dateTo   = $this->_getDateToParameter();

		$params->vesselName            = $this->_getVesselNameParameter();
		$params->supplierCountryCodes  = $this->_getLocationsParameter();
		$params->lineItemWords         = $this->_getRefineQueryParameter();
		
		switch ($transactionType) {
			case Shipserv_ProductLineItem::TRANSACTION_TYPE_ORDER:
				$this->_getExcludeIdsParameter($params);
				break;
			
			case Shipserv_ProductLineItem::TRANSACTION_TYPE_QUOTE:
				$this->_getExcludeDescriptionsParameter($params);
				break;
			
			default:
				throw new Myshipserv_Exception_MessagedException("Invalid context provided for price benchmarking parameters");
		}

		$params->pageNo     = $this->_getParam('pageNo', 1);
		$params->pageSize   = $this->_getParam('pageSize', 10);
		$params->sortBy     = $this->_getNonEmptyParam('sortBy');
		$params->sortDir    = $this->_getNonEmptyParam('sortDir', 'asc');
		
		return $params;
	}

    /**
     * Returns information about line items from everyone's quotes matching user criteria
     *
     * @throws Exception
     */
    public function quotedAction() {
	    $params = $this->_getPriceBenchmarkingParameters(Shipserv_ProductLineItem::TRANSACTION_TYPE_QUOTE);
	    $params->exclude = $params->excludeRight;

	    $t = new Shipserv_Helper_Stopwatch();
        $priceBenchmark = new Shipserv_PriceBenchmark($this->buyerOrg);

        $oldTimeout = ini_set('max_execution_time', 0);

	    $t->click();
	    $averageUnitCost = $priceBenchmark->getQuotedAveragePrice($params);
		$t->click("Average unit price across all the quotes");

	    $t->click();
	    $lineItemRows = $priceBenchmark->getQuotedLineItemsBreakdown($params);

	    $t->click("Average unit price grouped by LI description");

        ini_set('max_execution_time', $oldTimeout);

		$response = array();
	    foreach ($lineItemRows as $row) {
		    $response[] = array(
				'description' => $row['LI_DESC'],
		        'descriptionHash' => $row['LI_DESC_HASH'],
			    'unitCost'    => (float) $row['UNIT_COST'],
			    'count'       => (int) $row['LI_COUNT'],

			    'averageUnitCost' => $averageUnitCost,
			    'totalCost'     => (float) $row['TOTAL_COST'],
			    'totalQuantity' => (float) $row['TOTAL_QTY']
		    );
	    }

        $this->_helper->json((array)$response);
    }

    /**
     * Returns information about line items from buyer's orders matching user criteria
     *
     * @throws Exception
     */
    public function purchasedAction() {
    	$isAnonymReport = Myshipserv_Impa_Anonymize::getStatus();
    	
	    $params = $this->_getPriceBenchmarkingParameters(Shipserv_ProductLineItem::TRANSACTION_TYPE_ORDER);

	    $t = new Shipserv_Helper_Stopwatch();
	    $priceBenchmark = new Shipserv_PriceBenchmark($this->buyerOrg);

	    $oldTimeout = ini_set('max_execution_time', 0);

	    $t->click();
	    $averageUnitCost = $priceBenchmark->getOrderedAveragePrice($params);
		$t->click("Average unit price across all the orders");

	    $t->click();
	    $lineItemRows = $priceBenchmark->getOrderedLineItemsBreakdown($params);
	    $t->click("Average unit price by line item");

	    ini_set('max_execution_time', $oldTimeout);

	    $response = array();
	    foreach ($lineItemRows as $lineItemId => $row) {
		    $supplier = Shipserv_Supplier::getInstanceById($row['SUPPLIER_ID']);
		    if ($supplier->tnid) {
			    $supplierUrl = $supplier->getUrl();
		    } else {
			    $supplierUrl = null;
		    }
		    
		    $order = Shipserv_PurchaseOrder::getInstanceById($row['ORD_ID']);
		    if ($order->ordInternalRefNo) {
			    $orderUrl = $order->getUrl();
		    } else {
			    $orderUrl = null;
		    }

		    $response[] = array(
			    'id'       => implode('_', array($row['ORD_ID'], $row['LI_NO'])),
		    	'orderRefNo' => ($isAnonymReport) ? '***********' : $row['ORD_REF_NO'],
			    'orderDate'  => $row['ORD_DATE'],
		    	'orderUrl'   => ($isAnonymReport) ? '' : $orderUrl,

			    'supplierId'   => (int) $row['SUPPLIER_ID'],
		    	'supplierName' => ($isAnonymReport) ? 'Supplier ' . ($lineItemId + 1) : $row['SUPPLIER_NAME'],
		    	'supplierUrl'  => ($isAnonymReport) ? '' : $supplierUrl,
			    
			    'description' => $row['LI_DESC'],
			    'quantity'    => (float) $row['LI_QTY'],

			    'unit'        => $row['UOM'],
			    'unitCost'    => (float) $row['UNIT_COST'],
			    'fullCost'    => (float) $row['TOTAL_COST'],

			    'averageUnitCost' => $averageUnitCost
		    );
	    }

	    $this->_helper->json((array)$response);
    }

    /**
     * Builds ordered quantities broken down by supplier country
     */
    public function orderLocationsAction() {
	    $params = $this->_getPriceBenchmarkingParameters(Shipserv_ProductLineItem::TRANSACTION_TYPE_ORDER);

	    $priceBenchmark = new Shipserv_PriceBenchmark($this->buyerOrg);
	    $t = new Shipserv_Helper_Stopwatch();

	    $oldTimeout = ini_set('max_execution_time', null);

	    $t->click();
	    $rows = $priceBenchmark->getOrderedCountryBreakdown($params);
	    $t->click("Grouping ordered data by country");

	    ini_set('max_execution_time', $oldTimeout);

	    $response = array();
	    foreach ($rows as $row) {
		    if (!array_key_exists($row['COUNTRY_CODE'], $response)) {
			    $response[$row['COUNTRY_CODE']] = array(
				    'name' => $row['COUNTRY_NAME'],
				    'continent' => $row['CONTINENT_CODE'],
				    'units' => array()
			    );
		    }

		    $response[$row['COUNTRY_CODE']]['units'][$row['UNIT']] = (float) $row['TOTAL_QTY'];
	    }

        $this->_helper->json((array)
	        array(
	            'countries' => $response,
	            '_debug' => array(
		            'elapsed' => $t->getLoops()
	            )
	        )
        );
    }

    /**
     * Builds order and quote price stats broken down by month
     */
    public function priceHistoryAction() {
	    $paramsQuote = $this->_getPriceBenchmarkingParameters(Shipserv_ProductLineItem::TRANSACTION_TYPE_QUOTE);
	    $paramsOrder = $this->_getPriceBenchmarkingParameters(Shipserv_ProductLineItem::TRANSACTION_TYPE_ORDER);

	    $priceBenchmark = new Shipserv_PriceBenchmark($this->buyerOrg);
	    $t = new Shipserv_Helper_Stopwatch();

        $oldTimeout = ini_set('max_execution_time', null);
		$t->click();
        $quoteMonths = $priceBenchmark->getQuotedMonthsBreakdown($paramsQuote);
	    $t->click("Grouping quoted data by month");

	    $t->click();
	    $orderMonths = $priceBenchmark->getOrderedMonthsBreakdown($paramsOrder);
	    $t->click("Grouping ordered data by month");

        ini_set('max_execution_time', $oldTimeout);

        $response = array();
	    $months = Shipserv_PriceBenchmark::getMonthsList($paramsQuote);

        foreach ($months as $month) {
	        $response[$month] = array(
		        'quoteUnitCost' => 0,
		        'orderUnitCost' => 0,
		        'orderQuantity' => 0
	        );

	        foreach ($quoteMonths as $row) {
		        if ($row['MONTH'] === $month) {
			        $response[$month]['quoteUnitCost'] = (float) $row['UNIT_COST'];
		        }
	        }

	        foreach ($orderMonths as $row) {
		        if ($row['MONTH'] === $month) {
			        $response[$month]['orderUnitCost'] = (float) $row['UNIT_COST'];
			        $response[$month]['orderQuantity'] = (float) $row['TOTAL_QTY'];
		        }
	        }
        }

        $this->_helper->json((array)array(
            'months' => $response,
            '_debug' => array(
                'elapsed' => $t->getLoops()
            )
        ));
    }

    /**
     * Provides a list of recommended suppliers as if the search was run at site's front page
     *
     * This is a simple wrapper where we call front page search action and return its output
     */
    public function recommendedSuppliersAction() {
        $timeStart = microtime(true);

        // build a query string
        $products = $this->_getParam('products', null);
        if (is_null($products) or !is_array($products) or empty($products)) {
            throw new Myshipserv_Exception_MessagedException("At least one IMPA code is expected");
        }

        $solHelper = new Solarium_Query_Helper();
        $escapedImpaCodes = array();

        foreach ($products as $product) {
            $impaCode = $product['impa'];
            $escapedImpaCodes[] = $solHelper->escapePhrase($impaCode);
        }

        // prepare optional search params
        $locationCode = '';
        $locationText = '';
        $userMessage = null;

	    $countryCodes = $this->_getLocationsParameter();
        if (!empty($countryCodes)) {
            if (count($countryCodes) === 1) {
                $locationCode = $countryCodes[0];
                $countries = new Shipserv_Oracle_Countries();
                $locationRow = $countries->getCountryRow($locationCode);
                $locationText = $locationRow[Shipserv_Oracle_Countries::COL_NAME_COUNTRY];

                $userMessage = "Only suppliers from " . $locationText . " are considered";

            } else if (count($countryCodes) > 1) {
                $userMessage = "More than one location selected, supplier location filter is ignored";
            }
        }

        $parameters = array(
            'j'           => 1,
            'newSearch'   => 1,
            'ssrc'        => 32,
            'searchType'  => 'product',
            'searchWhat'  => implode(" OR ", $escapedImpaCodes),
            'searchWhere' => $locationCode,
            'searchText'  => $locationText
        );

        $viewAction = new Zend_View_Helper_Action();

        $responseStr = $viewAction->action('index', 'results', 'search', $parameters);
        $this->_helper->layout()->enableLayout();

        if (($responseJson = json_decode($responseStr, true)) === null) {
            throw new Myshipserv_Search_PriceBenchmark_Exception("Invalid response returned by supplier search action");
        }

        // adding more data that isn't provided by the action we embed
        $supplierIds = array();
        if (array_key_exists('documents', $responseJson) and is_array($responseJson['documents']) and !empty($responseJson['documents'])) {
            foreach ($responseJson['documents'] as $item) {
                $supplierIds[] = $item['tnid'];
            }
        }

        if (!empty($supplierIds)) {
            $reviewCounts = Shipserv_Review::getReviewsCounts($supplierIds);

            foreach ($reviewCounts as $reviewSpbId => $reviewCount) {
                foreach ($responseJson['documents'] as $index => $item) {
                    if (((int) $item['tnid']) === $reviewSpbId) {
                        $responseJson['documents'][$index]['reviewCount'] = (int) $reviewCount;
                    }
                }
            }
        }


        $this->_helper->json((array)array(
            'suppliers'   => $responseJson,
            'userMessage' => $userMessage,
            '_debug' => array(
                'elapsed' => microtime(true) - $timeStart
            )
        ));
    }
}
