<?php
/**
 * Webservices for price tracker tool
 *
 * @author  Yuriy Akopov
 * @date    2015-05-06
 */
require_once('AbstractController.php');

class PriceBenchmark_PriceTrackerController extends PriceBenchmark_AbstractController
{
    const
        TRACKED_PRODUCTS_DEFAULT = 50
    ;

    const
        TRACKED_DATE_RANGE_DEFAULT = '-6 month',
        TRACKED_DATE_RANGE_MIN = '-12 month'
    ;


    /**
     * @param integer $impaCode
     * @return array
     * @throws Myshipserv_Search_PriceBenchmark_Exception
     */
    protected function _getProductFromImpa($impaCode)
    {
        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('pci' => Shipserv_Oracle_ImpaCatalogue::TABLE_NAME),
                array(
                    'pci.'. Shipserv_Oracle_ImpaCatalogue::COL_ID,
                    'pci.' . Shipserv_Oracle_ImpaCatalogue::COL_DESC
                )
            )
            ->where('pci.' . Shipserv_Oracle_ImpaCatalogue::COL_ID . ' = ?', $impaCode);

        $details = $db->fetchRow($select);
        if (empty($details)) {
            throw new Myshipserv_Search_PriceBenchmark_Exception("Invalid IMPA code " . $impaCode . " supplied");
        }

        return $details;
    }

	/**
	 * @return Shipserv_PriceBenchmark_Parameters
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public function _getSpendTrackerParameters()
	{
		$params = new Shipserv_PriceBenchmark_Parameters();

		// purchases how old are considered
		$dayRange = $this->_getParam('dayRange');
		if (strlen($dayRange) === 0) {
			$dateFrom = $this->_getDateTimeParam('dateFrom');
			if (is_null($dateFrom)) {
				$dateFrom = Shipserv_PriceBenchmark::getDefaultFromDate();
			}
		} else {
			$dateFrom = new DateTime();
			$dateFrom->modify('-' . (int) $dayRange . ' days');
		}

		$dateFromMin = Shipserv_PriceBenchmark::getDefaultFromDate();
		if ($dateFrom < $dateFromMin) {
			throw new Myshipserv_Exception_MessagedException(
				"Date range supplied for price tracking is too long, only " .
				Myshipserv_Config::getPriceBenchmarkDaysRange() . " days range is allowed"
			);
		}

		$params->dateFrom = $dateFrom;

		$refineQuery = $this->_getParam('refine');
		if (strlen($refineQuery)) {
			$productKeywords = explode(' ', str_replace(',', ' ', $refineQuery));
			foreach ($productKeywords as $wordKey => $word) {
				if (strlen($word) === 0) {
					unset($productKeywords[$wordKey]);
				}
			}
		} else {
			$productKeywords = array();
		}

		$params->productWords = $productKeywords;

		$params->pageNo = null;
		$productCount = $this->_getParam('productCount', null);

		if ($productCount === 'overspend') {
			$params->savings = false;

		} else if ($productCount === 'savings') {
			$params->savings = true;

		} else if ($productCount === 'all') {

		} else if (($productCount > 0) and ($productCount <= 200)) {
			$params->pageNo = 1;
			$params->pageSize = $productCount;

		} else {
			$params->pageNo = 1;
			$params->pageSize = 50;
		}
		
		return $params;
	}

    /**
     * Returns products bought recently by the current buyer sorted by potential savings
     *
     * @author  Yuriy Akopov
     * @date    2015-05-26
     * @story   S13638
     *
     * @return mixed
     *
     * @throws  Shipserv_Adapters_Solr_Exception
     * @throws  Myshipserv_Exception_MessagedException
     */
    public function getSavingsAction()
    {

        // DEV-2447 Speed up report(s)
        Myshipserv_CAS_CasRest::getInstance()->sessionWriteClose();

        $params = $this->_getSpendTrackerParameters();

	    $priceBenchmark = new Shipserv_PriceBenchmark($this->buyerOrg);

	    $oldTimeout = ini_set('max_execution_time', null);
	    $timeStart = microtime(true);

	    $total = null;
	    $productRows = $priceBenchmark->getTrackedProducts($params, $total);

	    ini_set('max_execution_time', $oldTimeout);

	    $processedRows = array();
	    foreach ($productRows as $row) {
		    $priceBenchmarkUrl = '/reports/price-benchmark?' . http_build_query(
			    array(
				    'priceTracker'       => 1,
				    'priceTrackerImpa'   => $row['PART_NO'],
				    'priceTrackerUnit'   => $row['UOM'],
				    'priceTrackerDate'   => $params->dateFrom->format('Y-m-d'),
				    'priceTrackerRefine' => $this->_getParam('refine')
			    )
		    );

			$processedRows[] = array(
				'impaCode' => $row['PART_NO'],
				'desc'     => $row['PART_DESC'],    // @todo: S20510: for further boost part description can be read here instead of supplied by the query (Yuriy)
				'unit'     => $row['UOM'],

				'priceBenchmarkUrl' => $priceBenchmarkUrl,

				'order' => array(
					'lineItemCount'     => (int) $row['ORD_LI_COUNT'],
					'totalQuantity'     => (float) $row['ORD_TOTAL_QTY'],
					'totalSpend'        => (float) $row['ORD_TOTAL_COST'],
					'averageUnitCost'   => (float) $row['ORD_UNIT_COST']
				),

				'quote' => array(
					'averageUnitCost' => (float) $row['QOT_UNIT_COST']
				),

				'savings' => array(
					'totalBySpent' => (float) $row['SAVINGS']
				)
			);
	    }

        return $this->_helper->json((array)
	        array(
                'products' => $processedRows,
                'totalCount' => count($processedRows),
                '_debug' => array(
	                'elapsed' => microtime(true) - $timeStart
                )
            )
        );
    }
}