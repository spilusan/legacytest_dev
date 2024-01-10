<?php
/**
 * Controller to house Price Benchmarking supportive webservices such as autocomplete etc.
 *
 * @author  Yuriy Akopov
 * @date    2015-05-06
 * @story   S13143
 */
require_once('AbstractController.php');

class PriceBenchmark_InputController extends PriceBenchmark_AbstractController {
    const
        MAX_IMPA_SUGGESTIONS = 20
    ;

    /**
     * Splits partial product description into words
     *
     * @param   string  $filterString
     * @return  array
     */
    protected function _impaFilterToWords($filterString) {
        $words = explode(" ", trim(strtoupper($filterString)));
        foreach ($words as $key => $value) {
            $value = trim($value);
            if (strlen($value) === 0) {
                unset($words[$key]);
            }
        }

        return $words;
    }

	public function getOrderedProductUnitsAction() {
		$impaCode = $this->_getParam('impaCode');
		$dateFrom = $this->_getDateTimeParam('dateFrom');
		$onlyMyProducts = (bool) $this->_getParam('onlyMine', false);
		$cached = null;

		$timeStart = microtime(true);
		$productLiObj = new Shipserv_ProductLineItem();
		$units = $productLiObj->getProductUnits($impaCode, $dateFrom, ($onlyMyProducts ? $this->buyerOrg : null));

		$this->_helper->json((array)array(
			'products' => array(
				$impaCode => $units
			),
			'_debug' => array(
				'elapsed' => microtime(true) - $timeStart
			)
		));
	}

    /**
     * Autocompletes an IMPA product which was found in orders using Oracle table of matches
     * (as opposed to a Neo4j graph earlier)
     *
     * @author  Yuriy Akopov
     * @date    2016-01-13
     * @story   S14315
     */
    public function autocompleteOrderedImpaProductAction() {
        $minChars = 3;
        $filterStr = $this->_getParam('filterStr', '');
        if (strlen(preg_replace('/\s/', '', $filterStr)) < $minChars) {
            $this->getResponse()->setHttpResponseCode(500);
            $this->_helper->json((array)array(
                'error' => true,
                'message' => "String provided is not long enough to autocomplete it, please provide at least " .
                    $minChars . " letters or digits"
            ));
        }

        $words = $this->_impaFilterToWords($filterStr);

        $onlyMyProducts = (bool) $this->_getParam('onlyMine', false);
        $dateFrom = $this->_getDateTimeParam('dateFrom');

	    $maxSuggestions = self::MAX_IMPA_SUGGESTIONS;

	    $timeStart = microtime(true);
	    $productLi = new Shipserv_ProductLineItem();
	    $products = $productLi->autocompleteProduct(
		    $words,
		    $dateFrom,
		    ($onlyMyProducts ? $this->buyerOrg : null),
		    1,
		    $maxSuggestions
	    );

	    $response = array();
	    foreach ($products as $partNo => $partDesc) {
		    $response[] = array(
			    'value' => $partNo,
			    'data' => $partNo . ' - ' . $partDesc
		    );
	    }

	    $this->_helper->json((array)array(
		    'products' => $response,
		    '_debug' => array(
			    'elapsed' => microtime(true) - $timeStart
		    )
	    ));
    }

    /**
     * Takes user input text, returns matching IMPA products limited to ordered by the current buyer or not
     *
     * @throws Shipserv_Adapters_Neo4j_Exception
     * @throws Zend_Controller_Response_Exception
     */
	/*
    public function autocompleteImpaProductAction() {
        $timeStart = microtime(true);

        $minChars = 3;
        $filterStr = $this->_getParam('filterStr', '');
        if (strlen(preg_replace('/\s/', '', $filterStr)) < $minChars) {
            $this->getResponse()->setHttpResponseCode(500);
            $this->_helper->json((array)array(
                'error' => true,
                'message' => "String provided is not long enough to autocomplete it, please provide at least " .
                    $minChars . " letters or digits"
            ));
        }

        $words = $this->_impaFilterToWords($filterStr);

        $onlyMyProducts = (bool) $this->_getParam('onlyMine', false);
        $dateFrom = $this->_getDateTimeParam('dateFrom');

        $maxSuggestions = self::MAX_IMPA_SUGGESTIONS;

        if (!$onlyMyProducts) {
            // look through all the available products - use Oracle
            $source = 'database';
            $products = array();

            $dbPageNo = 1;
            $dbPageSize = $maxSuggestions * 5;

            $cached = array();

            $neo4j = new Shipserv_Adapters_Neo4j();
            while(true) {
                $t = new Shipserv_Helper_Stopwatch();
                $dbCached = false;

                $t->click();
                $dbProducts = Shipserv_Oracle_ImpaCatalogue::completeDescription($words, $dbPageNo, $dbPageSize, $dbCached);
                $t->click("Loading " . count($dbProducts) . " products from DB");

                if (!empty($dbProducts)) {
                    $t->click();
                    $boughtProducts = $neo4j->checkIfOrdered(array_keys($dbProducts), null, $dateFrom, $maxSuggestions);
                    $t->click("Checked if the were ordered, " . count($boughtProducts) . " were");

                    if (!empty($boughtProducts)) {
                        foreach ($boughtProducts as $impaCode) {
                            $products[$impaCode] = $dbProducts[$impaCode];

                            if (count($products) >= $maxSuggestions) {
                                break;
                            }
                        }
                    }
                }

                $cached[] = array(
                    'dbCached'    => $dbCached,
                    'neo4jCached' => $neo4j->wasLastQueryCached(),
                    'elapsed'     => $t->getLoops()
                );

                if ((count($dbProducts) < $dbPageSize) or (count($products) >= $maxSuggestions)) {
                    break;
                }

                $dbPageNo++;
            }

            // $source = 'graph-all';
            // $neo4j = new Shipserv_Adapters_Neo4j();
            // $products = $neo4j->getBoughtProductMatches($words, null, $dateFrom, $maxSuggestions);
            // $cached = $neo4j->wasLastQueryCached();
        } else {
            // look through only products bought recently - use Neo4j
            $source = 'graph-mine';
            $neo4j = new Shipserv_Adapters_Neo4j();
            $products = $neo4j->getBoughtProductMatches($words, $this->buyerOrg, $dateFrom, 1, $maxSuggestions);
            $cached = $neo4j->wasLastQueryCached();
        }

        $results = array();
        if (!empty($products)) {
            foreach ($products as $impaCode => $description) {
                $results[] = array(
                    'value' => (int) $impaCode,
                    'data'  => $impaCode . ' - ' . $description
                );
            }
        }

        $this->_helper->json((array)array(
            'products' => $results,
            '_debug' => array(
                'source'  => $source,
                'cached'  => $cached,
                'elapsed' => microtime(true) - $timeStart
            )
        ));
    }
	*/

    /**
     * Takes product IMPA codes, returns measuring units used in orders of those products, limited by the current buyer or not
     *
     * @throws Shipserv_Adapters_Neo4j_Exception
     */
	/*
    public function getProductUnitsAction() {
        $timeStart = microtime(true);

        $impaCode = $this->_getParam('impaCode');
        $onlyMyProducts = (bool) $this->_getParam('onlyMine', false);
        $dateFrom = $this->_getDateTimeParam('dateFrom');

        if ($onlyMyProducts) {
            $buyer = $this->buyerOrg;
        } else {
            $buyer = null;
        }

        $neo4j = new Shipserv_Adapters_Neo4j();
        $results = $neo4j->getProductUnits($impaCode, $buyer, $dateFrom);

        $this->_helper->json((array)array(
            'products' => $results,
            '_debug' => array(
                'elapsed' => microtime(true) - $timeStart,
                'cached'  => $neo4j->wasLastQueryCached()
            )
        ));
    }
	*/
}
