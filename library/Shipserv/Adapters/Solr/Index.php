<?php
/**
 * Base Solarium-based class to implement services around particular Solr indices we have
 *
 * @author  Yuriy Akopov
 * @date    2013-12-02
 * @story   S8855
 */

// require_once('Solarium/Autoloader.php'); /* Should be autoloded via composer now since all vendor was moved there */
Solarium_Autoloader::register();

class Shipserv_Adapters_Solr_Index extends Solarium_Client
{
    /**
     * A blank function for the caller to include this class and thus to initialise Solarium Autoloader
     */
    public static function initSolariumAutoloader()
    {
    }

    const
        FIELD_SCORE = 'score'
    ;

    const
        SORT_DIR_ASC    = 'asc',
        SORT_DIR_DESC   = 'desc'
    ;

    /**
     * @var Memcache
     */
    protected $memcache = null;

    /**
     * @var bool
     */
    protected $lastQueryFromCache = null;

    /**
     * How many times to retry the query if it times out (1 for no retries, 2 for 1 extra attempt etc.)
     *
     * @var int
     */
    protected $timeoutAttempts = 1;

    /**
     * @return bool
     */
    public function wasLastQueryCached()
    {
        return $this->lastQueryFromCache;
    }

    /**
     * Helper function to convert Solr date strings into PHP DateTime objects
     * @todo: could be it's implemented in Solarium publicly and could be reused, but I couldn't find it
     *
     * @param   string  $solrDate
     * @return  DateTime|null
     */
    public static function dateTimeFromSolr($solrDate)
    {
        if (strlen($solrDate) === 0) {
            return null;
        }

        // $dateTime = DateTime::createFromFormat(DateTime::ISO8601, $solrDate);
        // simple way above is only available in PHP 5.3+, so there is a way around
        $solrDate = preg_replace(array('/T/', '/Z/'), array(' ', ''), $solrDate);
        $dateTime = new DateTime($solrDate);

        return $dateTime;
    }

    /**
     * Helper function to convert PHP DateTime objects into Solr dates
     * @todo: could be it's implemented in Solarium publicly and could be reused, but I couldn't find it
     *
     * @param   DateTime    $dateTime
     * @param   bool        $dayTime
     * @return  null|string
     */
    public static function dateTimeToSolr(DateTime $dateTime = null, $dayTime = false)
    {
        if (is_null($dateTime)) {
            return null;
        }

        if (is_null($dayTime)) {
            $timePart = $dateTime->format('H:i:s');
        } else if ($dayTime) {
            $timePart = '23:59:59';
        } else {
            $timePart = '00:00:00';
        }

        $solrDate = $dateTime->format('Y-m-d') . 'T' . $timePart . 'Z';

        return $solrDate;
    }

    /**
     * Initialises Solarium client with the URL read from Pages application.ini file,
     * pings the service and aborts on ping failure is requested
     *
     * @param   string  $iniFileUrl
     * @param   array   $options
     * @param   int     $timeoutAttempts
     *
     * @throws  Shipserv_Adapters_Solr_Exception
     * @throws  Solarium_Exception
     */
    public function __construct($iniFileUrl, array $options = null, $timeoutAttempts = 1)
    {
        if (strlen($iniFileUrl) === 0) {
            throw new Shipserv_Adapters_Solr_Exception("No line items Solr URL specified in config for " . __CLASS__);
        }

        if (($urlComponents = parse_url($iniFileUrl)) === false) {
            throw new Shipserv_Adapters_Solr_Exception("Invalid line items index URL specified in config for " . __CLASS__);
        }

        $core = null;
        $path = $urlComponents['path'];
        if (preg_match('/^\/(.*)\/(.*)$/', $path, $matches)) {
            $path = '/' . $matches[1];
            $core = $matches[2];
        }

        parent::__construct();
        $this->getPlugin('postbigrequest'); // switch to POST requests on long queries

        // replace default adapter using file_get_contents as it doesn't always work
        // reverted to default adapter on 2015-08-07 as some valid Solr query URIs didn't pass Zend_Http query validation
        // $this->setAdapter('Solarium_Client_Adapter_ZendHttp');

        $adapter = $this->getAdapter(); /** @var Solarium_Client_Adapter_ZendHttp $adapter */

        $defaultOptions = array(
            'host'  => $urlComponents['host'],
            'port'  => $urlComponents['port'],
            'path'  => $path
        );

        if (!is_null($core)) {
            $defaultOptions['core'] = $core;
        }

        if (!is_null($options)) {
            $adapter->setOptions(array_merge($defaultOptions, $options));
        } else {
            $adapter->setOptions($defaultOptions);
        }

        $this->memcache = Shipserv_Memcache::getMemcache();

        $this->timeoutAttempts = $timeoutAttempts;
    }

    /**
     * Pings index and throws an exception if it fails
     *
     * @throws Solarium_Exception
     * @throws Shipserv_Adapters_Solr_Exception
     */
    public function ping($query)
    {
        $pingQuery = $this->createPing();

        try {
            $this->ping($pingQuery);

        } catch (Solarium_Exception $e) {
            switch ($e->getCode()) {
                case 404:
                    throw new Shipserv_Adapters_Solr_Exception('Solr ping failed - server or path not found');

                case 500:
                    throw new Shipserv_Adapters_Solr_Exception('Solr ping failed because of server error. Check your solrconfig.xml for default ping handler and field settings.');

                default:
                    throw $e;
            }
        }
    }

    /**
     * Allows to specify request HTTP method
     *
     * @param   Solarium_Query $query
     * @param   string $method
     *
     * @return  Solarium_Client_Request
     */
    /*
    public function createRequest(Solarium_Query $query, $method = Solarium_Client_Request::METHOD_POST) {
        $request = parent::createRequest($query);
        $request->setMethod($method);

        return $request;
    }
    */

    /**
     * Reimplementing something from Shipserv_Memcache because it is protected there and we cannot inherit
     *
     * @param Solarium_Query_Select $query
     *
     * @return  string
     */
    protected function makeCacheKey(Solarium_Query_Select $query)
    {
        $config = Myshipserv_Config::getIni();

        $querySerialised = print_r($query, true);
        // print '<pre>'; print $querySerialised; print '</pre>'; die;

        $key = implode(
            '_',
            array(
                $config->memcache->client->keyPrefix,
                get_called_class(),
                md5($querySerialised),
                $config->memcache->client->keySuffix
            )
        );

        return $key;
    }

    /**
     * Check cache before running a Solr query
     *
     * @param   Solarium_Query_Select $query
     * @param   bool    $loadFromCache
     * @param   bool    $saveToCache
     * @param   int     $cacheTtl
     *
     * @return Solarium_Result_Select
     */
    public function select($query, $loadFromCache = true, $saveToCache = true, $cacheTtl = 3600)
    {
        if (!($this->memcache instanceof Memcache)) {
            $loadFromCache = false;
            $saveToCache = false;
        }

        $this->lastQueryFromCache = false;

        if (!$loadFromCache) {
            // no need to check the cache, run the query
            return $this->executeWithRetries($query);
        }

        $cacheKey = $this->makeCacheKey($query);
        if (($results = $this->memcache->get($cacheKey)) === false) {
            // failed to find results of this query in cache
            $results = $this->executeWithRetries($query);

            if ($saveToCache) {
                $this->memcache->set($cacheKey, $results, null, $cacheTtl);
            }

        } else {
            $this->lastQueryFromCache = true;
        }

        return $results;
    }

    /**
     * Sends out a request and retries given number of time on timeouts
     *
     * @param   Solarium_Query_Select $query
     *
     * @return  Solarium_Result_Select
     * @throws  Exception
     */
    public function executeWithRetries(Solarium_Query_Select $query)
    {
        $result = null;
        $lastErrorMsg = null;

        for ($attemptNo = 1; $attemptNo <= $this->timeoutAttempts; $attemptNo++) {
            $e = null;

            try {
                $result = $this->execute($query);

                if ($result->getResponse()->getBody() !== false) {
                    $lastErrorMsg = 'Empty response';
                    break;
                }

            } catch (Solarium_Client_HttpException $e) {
                // this is thrown when request fails, not necessarily after timing out
                $lastErrorMsg = get_class($e) . ': ' . $e->getMessage();
            } catch (Zend_Http_Client_Exception $e) {
                // is thrown when it's a timeout, but not only in that case and only when Zend client is used by Solarium
                $lastErrorMsg = get_class($e) . ': ' . $e->getMessage();
            } catch (Exception $e) {
                // this is not very nice because we don't check for the exact timeout case, just for any exception
                // the problem is that the best we can get is catching Zend_Http_Client exceptions and regexp'ing
                // error message for 'time out' which is again vague. And if a different client (not Zend) is used
                // it gets even more blurry
                //
                // anyway, something happened when sending out the request, let's try again now.
                $lastErrorMsg = get_class($e) . ': ' . $e->getMessage();
            }

            // wait a bit between requests
            sleep(1);
        }

        if (!is_null($e)) {
            throw $e;
        }

        if ($result->getResponse()->getBody() === false) {
            throw new Shipserv_Adapters_Solr_Exception("Empty response received from Solr after " . ($attemptNo - 1) . " attempts");
        }

        return $result;
    }

    /**
     * Reads all matches from Solr and runs a function for the documents, page by page
     * Returns the number of documents retrieved from Solr and processed with the provided function
     *
     * @date    2018-02-22
     * @story   DEV-2563
     *
     * @param   Solarium_Query_Select   $queryDraft
     * @param   callable                $pageFunction
     * @param   int                     $solrPageSize
     *
     * @return  int
     */
    public function processAllValues(Solarium_Query_Select $queryDraft, callable $pageFunction, $solrPageSize)
    {
        $query = clone($queryDraft);
        $pageNo = 1;

        $documentCount = 0;

        while (true) {

            $query->setStart(($pageNo - 1) * $solrPageSize);
            $query->setRows($solrPageSize);

            $result = $this->select($query);
            $documents = $result->getDocuments();

            if (empty($documents)) {
                break;
            }

            $documentCount += $pageFunction($documents);
            $pageNo++;
        }

        return $documentCount;
    }

    /**
     * @param   Solarium_Query_Select   $queryDraft
     * @param   string  $field
     * @param   int     $pageSize
     * @param   bool    $loadFromCache
     * @param   bool    $saveToCache
     * @param   float   $elapsed
     *
     * @return  array
     */
    public function getAllGroupValues(Solarium_Query_Select $queryDraft, $field, $pageSize = 1000, $loadFromCache = true, $saveToCache = true, &$elapsed = null)
    {
        $query = clone($queryDraft);
        $query->getGrouping()->addField($field);

        $pageNo = 1;

        $groupValues = array();

        while (true) {
            $query->setStart(($pageNo - 1) * $pageSize);
            $query->setRows($pageSize);

            $result = $this->select($query, $loadFromCache, $saveToCache);
            $group =  $result->getGrouping()->getGroup($field);
            $values = $group->getValueGroups();

            if (empty($values)) {
                break;
            }

            foreach ($values as $value) { /** @var $value Solarium_Result_Select_Grouping_ValueGroup */
                $groupValues[$value->getValue()] += $value->getNumFound();
            }

            $pageNo++;
        }

        return $groupValues;
    }
}

Shipserv_Adapters_Solr_Index::initSolariumAutoloader();
