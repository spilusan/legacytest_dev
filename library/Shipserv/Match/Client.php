<?php
/**
 * A wrapper for a stand-alone match engine application webservice API
 *
 * @author  Yuriy Akopov
 * @date    2014-10-29
 * @story   S11748
 */
class Shipserv_Match_Client {
    /**
     * Match engine application URL
     *
     * @var null
     */
    protected $url = null;

    /**
     * @var Zend_Http_Client
     */
    protected $client = null;

    /**
     * @var string
     */
    protected $lastRawResponse = null;

    /**
     * @var float
     */
    protected $lastResponseTime = null;

    /**
     * @var bool
     */
    protected $noCache = false;

    /**
     * @throws Shipserv_Match_Exception
     */
    public function __construct() {
        $url = Myshipserv_Config::getMatchUrl();
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new Shipserv_Match_Exception("Match engine URL " . $url . " is invalid");
        }

        $this->url = trim($url, '/');

        $this->client = new Zend_Http_Client();
    }

    /**
     * @return bool
     */
    public function getNoCache() {
        return $this->noCache;
    }

    /**
     * @param bool $noCache
     */
    public function setNoCache($noCache) {
        $this->noCache = $noCache;
    }


    /**
     * @param   int $timeout
     *
     * @return  Zend_Http_Response
     * @throws  Zend_Http_Client_Exception
     * @throws  Shipserv_Match_Exception
     */
    protected function sendRequest($timeout = null) {
        if (!is_null($timeout)) {
            $this->client->setConfig(array('timeout' => $timeout));
            $oldTimeout = ini_set('max_execution_time', $timeout);
        }

        if ($this->noCache) {
            $this->client->setParameterPost('noCache', 1);
        }

        $timeStart = microtime(true);
        $response = $this->client->request();
        $this->lastResponseTime = microtime(true) - $timeStart;

        if (!is_null($timeout)) {
            ini_set('max_execution_time', $oldTimeout);
        }

        $this->lastRawResponse = $response->getBody();

        if ($response->getStatus() !== 200) {
            throw new Shipserv_Match_Exception("Match engine returned an error: " . $this->getLastRawResponse());
        }

        return $response;
    }

    /**
     * Returns search results for RFQ outbox (running possibly less intensive search than for match operator)
     *
     * @param   int     $rfqId
     * @param   array   $terms
     *
     * @return Shipserv_Match_Client_Response
     * @throws Shipserv_Match_Exception
     */
    public function matchForRfqOutbox($rfqId, array $terms = null) {
        return $this->match($rfqId, 'rfq-outbox', true, true, $terms);
    }

    /**
     * Returns search results for match operator (all relevant result feeds requested)
     *
     * @param   int     $rfqId
     * @param   bool    $useStoredSearch
     * @param   bool    $storeSearch
     * @param   array   $terms
     *
     * @return Shipserv_Match_Client_Response
     * @throws Shipserv_Match_Exception
     */
    public function matchForMatchOperator($rfqId, $useStoredSearch = true, $storeSearch = true, array $terms = null) {
        return $this->match($rfqId, 'match-operator', $useStoredSearch, $storeSearch, $terms);
    }

    /**
     * Loads a specified stored search and returns its results / terms
     *
     * @param   int $searchId
     *
     * @return Shipserv_Match_Client_Response
     * @throws Shipserv_Match_Exception
     * @throws Zend_Http_Client_Exception
     */
    public function loadStoredSearch($searchId) {
        $this->client
            ->setUri($this->url . '/match/load-stored-search')
            ->setMethod(Zend_Http_Client::GET)
            ->setParameterGet(array(
                'searchId' => $searchId
            ))
        ;

        $this->sendRequest();
        $jsonResponse = $this->getLastRawResponse();

        $clientResponse = new Shipserv_Match_Client_Response($jsonResponse);

        return $clientResponse;
    }

    /**
     * Returns a list of stored searches for the RFQ event
     *
     * @param   int $rfqId
     *
     * @return  Shipserv_Match_Client_Response
     * @throws  Zend_Http_Client_Exception
     * @throws  Shipserv_Match_Exception
     */
    public function getStoredSearches($rfqId) {
        $this->client
            ->setUri($this->url . '/match/get-stored-searches')
            ->setMethod(Zend_Http_Client::GET)
            ->setParameterGet(array(
                'rfqId'      => $rfqId,
                'wholeEvent' => 1
                //'context'    => null
            ))
        ;

        $this->sendRequest();

        $json = json_decode($this->getLastRawResponse(), true);
        if (is_null($json)) {
            throw new Shipserv_Match_Exception("Failed to parse match engine response");
        }

        $searches = array();
        foreach ($json['searches'] as $item) {
            $searches[] = Shipserv_Match_Component_Search::getInstanceById($item['id']);
        }

        return $searches;
    }

    /**
     * Fires the match engine for the given RFQ
     *
     * @param   int     $rfqId
     * @param   string  $context
     * @param   bool    $useStoredSearch
     * @param   bool    $storeSearch
     * @param   array   $terms
     *
     * @return Shipserv_Match_Client_Response
     *
     * @throws  Shipserv_Match_Exception
     * @throws  Zend_Http_Client_Exception
     */
    protected function match($rfqId, $context, $useStoredSearch = true, $storeSearch = true, array $terms = null) {
        if (!is_null($terms)) {
            $terms = json_encode($terms);
        }

        $this->client
            ->setUri($this->url . '/match/get-suppliers')
            ->setMethod(Zend_Http_Client::POST)
            ->setParameterPost(array(
                'rfqId'           => $rfqId,
                'context'         => $context,
                'useStoredSearch' => $useStoredSearch ? 1 : 0,
                'storeSearch'     => $storeSearch ? 1 : 0,
                'terms'           => $terms
            ))
        ;

        $this->sendRequest(60 * 10);
        $jsonResponse = $this->getLastRawResponse();
        $clientResponse = new Shipserv_Match_Client_Response($jsonResponse);

        return $clientResponse;
    }

    /**
     * Sends a request to forward an RFQ to more (match selected) suppliers
     *
     * @param   Shipserv_Rfq|int   $rfq
     * @param   Shipserv_Supplier[]|array|int   $suppliers
     * @param   array|float|null    $scores
     * @param   array|string|null   $comments
     *
     * @return array
     * @throws Shipserv_Match_Exception
     * @throws Zend_Http_Client_Exception
     */
    public function forward($rfq, $suppliers, $scores = null, $comments = null) {
        if ($rfq instanceof Shipserv_Rfq) {
            $rfqId = $rfq->rfqInternalRefNo;
        } else {
            $rfqId = $rfq;
        }

        $params = array(
            'rfqId' => $rfqId
        );

        if (!is_array($suppliers)) {
            $suppliers = array($suppliers);
        }

        $supplierIds = array();
        foreach ($suppliers as $supplier) {
            if ($supplier instanceof Shipserv_Supplier) {
                $supplierIds[] = $supplier->tnid;
            } else {
                $supplierIds[] = $supplier;
            }
        }

        $params['suppliers'] = $supplierIds;

        if (!is_null($scores)) {
            if (!is_array($scores)) {
                $scores = array($scores);
            }

            $params['scores'] = $scores;
        }

        if (!is_null($comments)) {
            if (!is_array($comments)) {
                $comments = array($comments);
            }

            $params['comments'] = $comments;
        }

        $this->client
            ->setUri($this->url . '/match/send')
            ->setMethod(Zend_Http_Client::POST)
            ->setParameterPost($params)
        ;

        $this->sendRequest(10 * 60);
        $jsonResponse = $this->getLastRawResponse();

        if (($response = json_decode($jsonResponse, true)) === null) {
            throw new Shipserv_Match_Exception("Forwarding service didn't return a valid response: " . $jsonResponse);
        }

        $fwdIds = array();
        if (!empty($response['forwarded'])) {
            foreach ($response['forwarded'] as $item) {
                $fwdIds[$item['supplierId']] = $item['rfqId'];
            }
        }

        return $fwdIds;
    }

    /**
     * Returns the last response returned by the match engine
     *
     * @return string
     */
    public function getLastRawResponse() {
        return $this->lastRawResponse;
    }

    public function getLastResponseTime() {
        return $this->lastResponseTime;
    }

    /**
     * Asks Match engine to tokenise given text into meaninful words
     *
     * @author  Yuriy Akopov
     * @date    2015-08-03
     * @story   S14313
     *
     * @param   string  $text
     * @param   bool    $nounsOnly
     * @return  array
     *
     * @throws  Shipserv_Match_Exception
     * @throws  Zend_Http_Client_Exception
     */
    public function words($text, $nounsOnly = true) {
        $this->client
            ->setUri($this->url . '/match/words')
            ->setMethod(Zend_Http_Client::POST)
            ->setParameterPost(array(
                'text' => $text,
                'nounsOnly' => ($nounsOnly ? 1 : 0)
            ))
        ;

        $this->sendRequest();
        $jsonResponse = $this->getLastRawResponse();

        // print(nl2br($jsonResponse) . '<hr>');

        if (($response = json_decode($jsonResponse, true)) === null) {
            throw new Shipserv_Match_Exception("Failed to tokenise text: " . $jsonResponse);
        }

        $words = array();
        if (!empty($response['words'])) {
            $words = $response['words'];
        }

        return $words;
    }
}
