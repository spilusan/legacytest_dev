<?php
/**
 * Implementation of search queries related to Market Sizing Tool
 *
 * /extends Shipserv_Object to access its memcache functions solely/
 *
 * @author  Yuriy Akopov
 * @date    2014-11-24
 * @story   S12169
 */
class Myshipserv_Search_MarketSizing extends Shipserv_Object
{
    use Shipserv_Helper_Solr;

    const
        FILTER_LOCATIONS    = 'locations',
        FILTER_DATE_TO      = 'dateTo',
        FILTER_DATE_FROM    = 'dateFrom',
        FILTER_VESSEL_GMV   = 'vesselGmv',
        FILTER_VESSEL_TYPE  = 'vesselType',
        FILTER_ORDER_VALUE  = 'orderValue',
        FILTER_VESSEL_IMO   = 'vesselImo'
    ;

    const
        FILTER_IMO_PREFIX = 'filterImo' // IMO filter query key
    ;

    const
        LOCK_FILE_FOLDER        = 'Pages_Market_Sizing_Tool',
        LOCK_FILE_EXTENSION     = 'lock',
        LOCK_FILE_TIMEOUT_SEC   = 60
    ;

    /***
     * @var string
     */
    protected $fileSessionLock = null;

    /**
     * @var string
     */
    protected $keywordInclude = null;

    /**
     * @var array
     */
    protected $keywordsExclude = null;

    /**
     * @var array
     */
    protected $filters = null;

    /*
     *  @var bool
     */
    protected $isTransactionOpen = false;

    /**
     * @param   string  $keywordInclude
     * @param   array   $keywordsExclude
     * @param   array   $filters
     * @param   int     $timeoutAttempts
     *
     * @throws Myshipserv_Search_MarketSizing_Exception_Session
     */
    public function __construct($keywordInclude, array $keywordsExclude = array(), array $filters = array(), $timeoutAttempts = 10)
    {
        $this->client = new Shipserv_Adapters_Solr_LineItems_Index($timeoutAttempts);

        $this->keywordInclude = $keywordInclude;
        $this->keywordsExclude = $keywordsExclude;
        $this->filters = $filters;

        $this->_createSessionLock();
    }

    /**
     * @throws Myshipserv_Search_MarketSizing_Exception_Session
     */
    public function __destruct()
    {

        if ($this->isTransactionOpen) {
            Shipserv_Helper_Database::getSsreport2Db()->rollBack();
        }
        $this->_removeSessionLock();
    }

    /**
     * @return Solarium_Query_Select
     */
    protected function _getQuery()
    {
        $query = $this->client->createSelect();
        return $query;
    }

    /**
     * Returns path to the folder with session lock files and creates it, if necessary
     *
     * @todo: Is not going to work with load balancing and/or containerisation, but according to Attila it is safe to
     * expect this all application to continue to function on a single host like this
     *
     * @story   DEV-2563
     * @date    2018-02-21
     *
     * @return string
     * @throws Myshipserv_Search_MarketSizing_Exception_Session
     */
    protected static function _getSessionLockPath()
    {
        $path = implode(DIRECTORY_SEPARATOR, array(sys_get_temp_dir(), self::LOCK_FILE_FOLDER));
        if (file_exists($path)) {
            if (is_dir($path)) {
                return $path;
            } else {
                throw new Myshipserv_Search_MarketSizing_Exception_Session(
                    "Market Sizing lock folder " . $path . " seems to be a file"
                );
            }
        }

        if (mkdir($path)) {
            return $path;
        }

        throw new Myshipserv_Search_MarketSizing_Exception_Session(
            "Failed to find or create Market Sizing lock folder " . $path
        );
    }

    /**
     * Creates a lock file for the current session
     *
     * @todo: Is not going to work with load balancing and/or containerisation, but according to Attila it is safe to
     * expect this all application to continue to function on a single host like this
     *
     * @story   DEV-2563
     * @date    2018-02-21
     *
     * @throws Myshipserv_Search_MarketSizing_Exception_Session
     */
    protected function _createSessionLock()
    {
        if (!is_null($this->fileSessionLock)) {
            throw new Myshipserv_Search_MarketSizing_Exception_Session(
                "Marking Sizing lock file already exists and cannot be re-created"
            );
        }

        // build a file name for the lock
        // start with the unique alphanumeric identifier
        $uniqueId = md5(Myshipserv_Helper_Session::getGuid());
        // then add an extension
        $filename = $uniqueId . '.' . self::LOCK_FILE_EXTENSION;
        // finally, join with the path
        $filePath = implode(DIRECTORY_SEPARATOR, array(self::_getSessionLockPath(), $filename));

        if (!touch($filePath)) {
            throw new Myshipserv_Search_MarketSizing_Exception_Session(
                "Failed to touch Market Sizing lock file " . $filePath
            );
        }

        $this->fileSessionLock = $filePath;
        return $this->fileSessionLock;
    }

    /**
     * Removes the current session lock file
     *
     * @todo: Is not going to work with load balancing and/or containerisation, but according to Attila it is safe to
     * expect this all application to continue to function on a single host like this
     *
     * @story   DEV-2563
     * @date    2018-02-21
     *
     * @throws Myshipserv_Search_MarketSizing_Exception_Session
     */
    protected function _removeSessionLock()
    {
        if (!is_null($this->fileSessionLock)) {
            if (!unlink($this->fileSessionLock)) {
                throw new Myshipserv_Search_MarketSizing_Exception_Session(
                    "Failed to remove Market Sizing lock file " . $this->fileSessionLock
                );
            }
        }
    }

    /**
     * Returns the list of session filenames with parsed time objects, if they exist
     *
     * Removes sessions that were created too long ago and must have expired prior to that
     *
     * @story   DEV-2563
     * @date    2018-02-21
     *
     * @return  DateTime[]
     * @throws  Myshipserv_Search_MarketSizing_Exception_Session
     */
    public static function getActiveSessions()
    {
        $path = self::_getSessionLockPath();

        $files = scandir($path);
        $sessions = array();

        // loop through the files in the folder
        foreach ($files as $fname) {
            $fpath = implode(DIRECTORY_SEPARATOR, array($path, $fname));

            // ignore the files with unexpected extensions
            if (pathinfo($fpath, PATHINFO_EXTENSION) !== self::LOCK_FILE_EXTENSION) {
                continue;
            }

            // remove and then ignore the files with correct extensions that were created too long ago
            $timestamp = filemtime($fpath);
            $now = time();

            if ($now - $timestamp > self::LOCK_FILE_TIMEOUT_SEC) {
                unlink($fpath);
                continue;
            }

            $timeObj = new DateTime();
            $timeObj->setTimestamp($timestamp);
            $sessions[$fname] = $timeObj;
        }

        return $sessions;
    }

    /**
     * Adds user-requested filters to the given Solr query
     *
     * @param   Solarium_Query_Select   $query
     * @param   array                   $filters
     *
     * @return  Solarium_Query_Select
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    protected function _applyFilters(Solarium_Query_Select $query, array $filters)
    {
        if (empty($filters)) {
            return $query;
        }

        // remove empty filters
        foreach ($filters as $key => $value) {
            if (is_object($value)) {
                continue;
            }

            if (is_array($value) and !empty($value)) {
                continue;
            }

            if (!is_array($value) and strlen($value) > 0) {
                continue;
            }

            unset($filters[$key]);
        }

        if (empty($filters)) {
            return $query;
        }

        $helper = $query->getHelper();

        foreach ($filters as $filterType => $filterValue) {
            $filterQuery = new Solarium_Query_Select_FilterQuery();
            $filterQuery->setKey('filter' . $filterType);

            switch ($filterType) {
                case self::FILTER_LOCATIONS:
                    if (empty($filterValue)) {
                        break;
                    }

                    $countryQuery = array();
                    foreach ($filterValue as $countryCode) {
                        if (strlen($countryCode)) {
                            $countryQuery[] = Shipserv_Adapters_Solr_LineItems_Index::FIELD_SUPPLIER_BRANCH_COUNTRY . ':' . $helper->escapePhrase($countryCode);
                        }
                    }

                    if (empty($countryQuery)) {
                        break;
                    }

                    $filterQuery->setQuery(
                        '(' . implode(') OR (', $countryQuery) . ')'
                    );

                    $query->addFilterQuery($filterQuery);
                    break;

                case self::FILTER_DATE_FROM:
                    // date filter may be applied to different field depending on the context (RFQ or order, so for now only save a string without a field)
                    $filterQuery->dateFilterQueryStr = '[' . Shipserv_Adapters_Solr_LineItems_Index::dateTimeToSolr($filterValue) . ' TO *]';
                    $query->addFilterQuery($filterQuery);
                    break;

                case self::FILTER_DATE_TO:
                    // date filter may be applied to different field depending on the context (RFQ or order, so for now only save a string without a field)
                    $filterQuery->dateFilterQueryStr = '[* TO ' . Shipserv_Adapters_Solr_LineItems_Index::dateTimeToSolr($filterValue) . ']';
                    $query->addFilterQuery($filterQuery);
                    break;

                case self::FILTER_VESSEL_TYPE:
                    $vesselTypeFields = array(
                        Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_VESSEL_TYPE,
                        Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_VESSEL_TYPE
                    );

                    $queryBits = array();
                    foreach ($vesselTypeFields as $vtField) {
                        $queryBits[] = $vtField . ':' . $helper->escapeTerm($filterValue) . '*';
                    }

                    $filterQuery->setQuery('(' . implode(') OR (', $queryBits) . ')');
                    $query->addFilterQuery($filterQuery);
                    break;

                case self::FILTER_VESSEL_GMV:
                    // this filter may be too long to apply in go, so there is a separate procedure for it
                    if (!filter_var($filterValue, FILTER_VALIDATE_FLOAT)) {
                        throw new Myshipserv_Exception_MarketSizing("Invalid vessel GMV filter: " . $filterType);
                    }
                    break;

                case self::FILTER_ORDER_VALUE:
                    // this filter is only applicable to order-related figures so it is also set separately
                    if (!filter_var($filterValue, FILTER_VALIDATE_FLOAT)) {
                        throw new Myshipserv_Exception_MarketSizing("Invalid max order value filter: " . $filterType);
                    }
                    break;

                case self::FILTER_VESSEL_IMO:
                    if (!is_array($filterValue)) {
                        $filterValue = array($filterValue);
                    }

                    $vesselImos = array();
                    foreach ($filterValue as $vesselImo) {
                        $vesselImos[] = $helper->escapePhrase($vesselImo);
                    }

                    $vesselImoFields = array(
                        Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_VESSEL_IMO,
                        Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_VESSEL_IMO
                    );

                    $queryBits = array();
                    foreach ($vesselImoFields as $imoField) {
                        $queryBits[] = $imoField . ':(' . implode(' OR ', $vesselImos) .')';
                    }

                    $filterQuery->setQuery('(' . implode(') OR (', $queryBits) . ')');
                    $query->addFilterQuery($filterQuery);

                    break;

                default:
                    throw new Myshipserv_Exception_MarketSizing("Unknown filter " . $filterType . " supplied");
            }
        }

        return $query;
    }

    /**
     * Sorts (roughly) given keywords from most to least common
     *
     * @param   array   $phrases
     *
     * @return  array
     */
    protected static function _sortKeywords(array $phrases)
    {
        if (empty($phrases)) {
            return $phrases;
        }

        // group by word count
        $byWordCount = array();
        foreach ($phrases as $kw) {
            $kw = preg_replace('/[ \t]+/', " ", $kw);
            $wordCount = count(explode(" ", $kw));

            if (!array_key_exists($wordCount, $byWordCount)) {
                $byWordCount[$wordCount] = array();
            }

            $byWordCount[$wordCount][] = $kw;
        }

        // within every group sort by average word length
        $sortByWordLen = function ($a, $b) {
            $getAverageWordLen = function (array $words) {
                $totalLen = 0;
                foreach ($words as $w) {
                    $totalLen += strlen($w);
                }

                return $totalLen / (count($words));
            };

            $wordsA = explode(" ", $a);
            $wordsB = explode(" ", $b);

            $averageLenA = $getAverageWordLen($wordsA);
            $averageLenB = $getAverageWordLen($wordsB);

            if ($averageLenA < $averageLenB) {
                return 1;
            } else if ($averageLenA > $averageLenB) {
                return -1;
            }

            return 0;
        };

        // sort keys (sentence lengths) DESC
        $keys = array_keys($byWordCount);
        sort($keys);
        $sortedLengths = array_reverse($keys);

        // built final array sorted by 1) sentence length DESC 2) averaged word length DESC
        $sortedKeywords = array();
        foreach ($sortedLengths as $length) {
            $phrases = $byWordCount[$length];
            usort($phrases, $sortByWordLen);

            $sortedKeywords = array_merge($sortedKeywords, $phrases);
        }

        return $sortedKeywords;
    }

    /**
     * Builds a draft query to get data for a single row to be extended to retrieve a particular row field
     *
     * @return  Solarium_Query_Select[]
     * @throws  Myshipserv_Search_MarketSizing_Exception_NoVessels
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     * @throws  Exception
     */
    public function getRowQueries()
    {
        $query  = $this->_getQuery();
        $helper = $query->getHelper();

        $query
            ->setStart(0)
            ->setRows(0)
            ->setFields(
                array(
                    Shipserv_Adapters_Solr_LineItems_Index::FIELD_SCORE,
                    '*'
                )
            )
            ->setQuery(Shipserv_Adapters_Solr_LineItems_Index::FIELD_TXT_ALL . ':' . $helper->escapePhrase($this->keywordInclude));

        if (!empty($this->keywordsExclude)) {
            // ...but exclude all of these
            foreach ($this->keywordsExclude as $kwIndex => $kwExclude) {
                $filterQuery = new Solarium_Query_Select_FilterQuery();
                $filterQuery
                    ->setKey('filterExcludeRow' . $kwIndex)
                    ->setQuery('-' . Shipserv_Adapters_Solr_LineItems_Index::FIELD_TXT_ALL . ':' . $helper->escapePhrase($kwExclude));

                $query->addFilterQuery($filterQuery);
            }
        }

        $query = $this->_applyFilters($query, $this->filters);

        // now deal with GMV filter because the number of the queries to be produced depends on it
        $queries = array();

        if (array_key_exists(self::FILTER_VESSEL_GMV, $this->filters) and strlen($this->filters[self::FILTER_VESSEL_GMV])) {
            $validVesselImos = $this->_getVesselsOverTargetGmv($this->filters[self::FILTER_VESSEL_GMV]);

            if (empty($validVesselImos)) {
                // nothing qualifies, forcing empty results
                throw new Myshipserv_Search_MarketSizing_Exception_NoVessels("No vessels qualify the requested GMV limit");

            } else {
                // only include qualifying vessels
                foreach ($validVesselImos as $key => $value) {
                    $validVesselImos[$key] = $helper->escapePhrase($value);
                }

                $start = 0;
                $step = 50; // how many IMOs per one query
                while (count($imoSlice = array_slice($validVesselImos, $start, $step))) {
                    $start += $step;

                    $filterQuery = new Solarium_Query_Select_FilterQuery();
                    $filterQuery->setKey(self::FILTER_IMO_PREFIX . $start);

                    $queryStr = '(' . implode(' OR ', $imoSlice) . ')'; // field name will be added to this string later when we know if it should be order or RFQ IMO
                    $filterQuery->imoFilterQueryStr = $queryStr;

                    $querySlice = clone($query);
                    $querySlice->addFilterQuery($filterQuery);

                    $queries[] = $querySlice;
                }
            }

        } else {
            // no GMV filter requested
            $queries[] = $query;
        }

        return $queries;
    }

    /**
     * Adds queries to prepared IMO filters depending on the context (order or RFQ-related figure is calculated)
     *
     * @param   Solarium_Query_Select   $query
     * @param   string                  $field  RFQ or order vessel IMO field in the index (depends on the query context)
     *
     * @return  Solarium_Query_Select
     */
    protected function _applyVesselGmvFilter(Solarium_Query_Select $query, $field)
    {
        $filterQueries = $query->getFilterQueries();

        if (!empty($filterQueries)) {
            foreach ($filterQueries as &$filterQuery) { /** @var $filterQuery Solarium_Query_Select_FilterQuery */
                if (preg_match('/^' . preg_quote(self::FILTER_IMO_PREFIX, '/') . '\d+$/', $filterQuery->getKey())) {
                    if (isset($filterQuery->imoFilterQueryStr) and strlen($filterQuery->imoFilterQueryStr)) {
                        $filterQuery->setQuery($field . ':' . $filterQuery->imoFilterQueryStr);
                    }
                }
            }
        }

        return $query;
    }

    /**
     * Adds a query to prepared date filters depending on the context (order or RFQ-related figure is calculated)
     *
     * @param   Solarium_Query_Select   $query
     * @param   string  $field
     */
    protected function _applyDateFilter(Solarium_Query_Select $query, $field)
    {
        $dateFilterKeys = array(
            'filter' . self::FILTER_DATE_FROM,
            'filter' . self::FILTER_DATE_TO
        );

        foreach ($dateFilterKeys as $key) {
            $filter = $query->getFilterQuery($key);
            if ($filter and isset($filter->dateFilterQueryStr) and strlen($filter->dateFilterQueryStr)) { /** @var $filter Solarium_Query_Select_FilterQuery */
                $filter->setQuery($field . ':' . $filter->dateFilterQueryStr);
                // unset($filter->dateFilterQueryStr);
            }
        }
    }

    /**
     * @param Solarium_Query_Select $queryDraft
     *
     * @return Solarium_Query_Select
     * @throws Solarium_Exception
     */
    protected function makeOrderQuery(Solarium_Query_Select $queryDraft)
    {
        $query = clone($queryDraft);
        $query->setDocumentClass('Shipserv_Adapters_Solr_LineItems_Document_Order');

        $filterQuery = new Solarium_Query_Select_FilterQuery();
        $filterQuery
            ->setKey('filterTransaction')
            ->setQuery(Shipserv_Adapters_Solr_LineItems_Index::FIELD_TRANSACTION_TYPE . ':' . Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER);

        $query->addFilterQuery($filterQuery);

        // exclude line items with cost greater than zero
        $filterQuery = new Solarium_Query_Select_FilterQuery();
        $filterQuery
            ->setKey('filterOliUnitCostUsd')
            ->setQuery('' . Shipserv_Adapters_Solr_LineItems_Index::FIELD_UNIT_COST_USD . ':{0 TO *]');

        $query->addFilterQuery($filterQuery);

        // apply filter for max order price
        if (array_key_exists(self::FILTER_ORDER_VALUE, $this->filters) and strlen($this->filters[self::FILTER_ORDER_VALUE])) {
            $filterQuery = new Solarium_Query_Select_FilterQuery();
            $filterQuery
                ->setKey('filterOrderValue')
                ->setQuery(Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_TOTAL_COST_USD . ': [* TO ' . $this->filters[self::FILTER_ORDER_VALUE] . ']');
            ;
        }

        $this->_applyVesselGmvFilter($query, Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_VESSEL_IMO);
        $this->_applyDateFilter($query, Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_DATE);

        return $query;
    }

    /**
     * Prepares a draft query for RFQ-related queries
     *
     * @param   Solarium_Query_Select   $queryDraft
     *
     * @return  Solarium_Query_Select
     * @throws  Solarium_Exception
     */
    protected function makeRfqQuery(Solarium_Query_Select $queryDraft)
    {
        $query = clone($queryDraft);
        $query->setDocumentClass('Shipserv_Adapters_Solr_LineItems_Document_Rfq');

        $filterQuery = new Solarium_Query_Select_FilterQuery();
        $filterQuery
            ->setKey('filterTransaction')
            ->setQuery(
                Shipserv_Adapters_Solr_LineItems_Index::FIELD_TRANSACTION_TYPE . ':'
                . Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ
            );

        $query->addFilterQuery($filterQuery);

        $this->_applyVesselGmvFilter($query, Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_VESSEL_IMO);
        $this->_applyDateFilter($query, Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_DATE);

        return $query;
    }

    /**
     * Returns basic aggregated stats for matched order line items which are expected to be already in the buffer table
     *
     * @date    2018-02-23
     * @story   DEV-2653
     *
     * @return array
     */
    public function getRfqTotalLineItemDbStats()
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();

        // no caching because the content of the buffer is unique and only lasts for one session only
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('buf' => 'market_sizing_tmp_buffer'),
                array(
                    'COUNT' => new Zend_Db_Expr('COUNT(buf.line_item_no)'),
                    'RFQ'   => new Zend_Db_Expr('COUNT(DISTINCT buf.transaction_id)')
                )
            )
            ->where('buf.transaction_type = ?', 'rfq');

        if (!($result = $db->fetchRow($select))) {
            return array(
                'count' => 0,
                'rfq'   => 0
            );
        }

        return array(
            'liCount'  => (int) $result['COUNT'],
            'rfqCount' => (int) $result['RFQ']
        );
    }

    /**
     * Returns basic aggregated stats for matched RFQs which are expected to be already in the buffer table
     *
     * @date    2018-02-23
     * @story   DEV-2653
     *
     * @return array
     */
    public function getRfqDbStats()
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();

        // no caching because the content of the buffer is unique and only lasts for one session only
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('rfq' => 'rfq'),
                array(
                    'EVENT' => new Zend_Db_Expr('COUNT(DISTINCT rfq.rfq_event_hash)'),
                    'BYB'   => new Zend_Db_Expr('COUNT(DISTINCT rfq.byb_branch_code)')
                )
            )
            ->join(
                array('buf' => 'market_sizing_tmp_buffer'),
                implode(
                    ' AND ',
                    array(
                        'rfq.rfq_internal_ref_no = buf.transaction_id'
                    )
                ),
                array()
            )
            ->where('buf.transaction_type = ?', 'rfq');

        if (!($result = $db->fetchRow($select))) {
            return array(
                'event' => 0,
                'byb'   => 0
            );
        }

        return array(
            'eventCount' => (int) $result['EVENT'],
            'bybCount'   => (int) $result['BYB']
        );
    }

    /**
     * Returns basic aggregated stats for matched RFQ line items which are expected to be already in the buffer table
     *
     * @date    2018-02-23
     * @story   DEV-2653
     *
     * @return array
     */
    public function getOrderLineItemUnitStats()
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();

        // no caching because the content of the buffer is unique and only lasts for one session only
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('oli' => 'ord_line_item'),
                array(
                    'UNIT'       => 'oli.oli_unit',
                    'COUNT'      => new Zend_Db_Expr('COUNT(oli.oli_line_item_no)'),
                    'QUANTITY'   => new Zend_Db_Expr('SUM(oli.oli_quantity)'),
                    'COST'       => new Zend_Db_Expr('SUM(oli.oli_total_line_item_cost_usd)')
                )
            )
            ->join(
                array('buf' => 'market_sizing_tmp_buffer'),
                implode(
                    ' AND ',
                    array(
                        'oli.ord_internal_ref_no = buf.transaction_id',
                        'oli.oli_line_item_no = buf.line_item_no'
                    )
                ),
                array()
            )
            ->where('buf.transaction_type = ?', 'ord')
            ->group('oli.oli_unit')
            ->order('COUNT DESC');

        if (!($result = $db->fetchAll($select))) {
            return array(
                'mostCommonUnit'      => '',
                'mostCommonUnitShare' => 0,
                'quantity'            => 0,
                'meanUnitCost'        => 0
            );
        }

        $mostCommonUnit = $result[0]['UNIT'];
        $mostCommonUnitCount = $result[0]['COUNT'];

        $totalLiCount = 0;
        $totalQuantity = 0;
        $averageUnitCosts = array();
        foreach ($result as $row) {
            $totalLiCount += $row['COUNT'];
            $totalQuantity += $row['QUANTITY'];

            if ($row['QUANTITY']) {
                $averageUnitCosts[] = $row['COST'] / $row['QUANTITY'] * $row['COUNT'];
            } else {
                $averageUnitCosts[] = 0;
            }
        }


        if ($totalLiCount > 0) {
            $mostCommonUnitShare = $mostCommonUnitCount / $totalLiCount * 100;
            $meanUnitCost = array_sum($averageUnitCosts) / $totalLiCount;
        } else {
            $mostCommonUnitShare = 0;
            $meanUnitCost = 0;
        }

        return array(
            'mostCommonUnit'      => $mostCommonUnit,
            'mostCommonUnitShare' => (float) $mostCommonUnitShare,
            'quantity'            => (float) $totalQuantity,
            'avgUnitCost'         => (float) $meanUnitCost
        );
    }

    /**
     * Returns basic aggregated stats for matched order line items which are expected to be already in the buffer table
     *
     * @date    2018-02-23
     * @story   DEV-2653
     *
     * @return array
     */
    public function getOrderTotalLineItemDbStats()
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();

        // no caching because the content of the buffer is unique and only lasts for one session only
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('oli' => 'ord_line_item'),
                array(
                    'COUNT' => new Zend_Db_Expr('COUNT(oli.oli_line_item_no)'),
                    'COST'  => new Zend_Db_Expr('SUM(oli.oli_total_line_item_cost_usd)'),
                    'BYB'   => new Zend_Db_Expr('COUNT(DISTINCT oli.byb_branch_code)'),
                    'SPB'   => new Zend_Db_Expr('COUNT(DISTINCT oli.spb_branch_code)'),
                    'ORD'   => new Zend_Db_Expr('COUNT(DISTINCT oli.ord_internal_ref_no)')
                )
            )
            ->join(
                array('buf' => 'market_sizing_tmp_buffer'),
                implode(
                    ' AND ',
                    array(
                        'oli.ord_internal_ref_no = buf.transaction_id',
                        'oli.oli_line_item_no = buf.line_item_no'
                    )
                ),
                array()
            )
            ->where('buf.transaction_type = ?', 'ord')
            ->where('oli.ord_is_latest = 1');

        if (!($result = $db->fetchRow($select))) {
            return array(
                'count' => 0,
                'cost'  => 0,
                'byb'   => 0,
                'spb'   => 0,
                'ord'   => 0
            );
        }

        return array(
            'liCount' => (int) $result['COUNT'],
            'liCost'  => (float) $result['COST'],
            'bybCount'   => (int) $result['BYB'],
            'spbCount'   => (int) $result['SPB'],
            'ordCount'   => (int) $result['ORD']
        );
    }

    /**
     * Returns total cost of the matching orders basing on line items in the buffer table
     *
     * @return float
     */
    public function getOrderTotalDbCost()
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();

        $selectUniqueOrdIds = new Zend_Db_Select($db);
        $selectUniqueOrdIds
            ->from(
                array('buf' => 'market_sizing_tmp_buffer'),
                array(
                    'ORD_ID' => 'buf.transaction_id'
                )
            )
            ->where('buf.transaction_type = ?', 'ord')
            ->distinct();

        // no caching because the content of the buffer is unique and only lasts for one session only
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('gmv' => 'ord_traded_gmv'),
                array(
                    'COST'  => new Zend_Db_Expr('SUM(gmv.final_total_cost_usd)')
                )
            )
            ->join(
                array('buf' => $selectUniqueOrdIds),
                'gmv.ord_internal_ref_no = buf.ord_id',
                array()
            );

        $result = $db->fetchOne($select);

        return (float) $result;
    }

    /**
     * Runs provided queries in Solr and writes down matched line items in to a temp table in Oracle
     *
     * @param   array   $queryDrafts
     * @param   string  $transactionType
     *
     * @return  int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    protected function _loadLineItemsToDb(array $queryDrafts, $transactionType)
    {
        if (!$this->isTransactionOpen) {
            Shipserv_Helper_Database::getSsreport2Db()->beginTransaction();
            $this->isTransactionOpen = true;
        }

        $queries = $this->_getTransactionQuery($queryDrafts, $transactionType);
        $lineItemCount = 0;

        /**
         * Inserts order documents matched in Solr into a temporary Oracle buffer for further processing
         *
         * @param   Shipserv_Adapters_Solr_LineItems_Document_Order[]   $documents
         * @param   int                                                 $dbPageSize
         *
         * @return  int
         * @throws  Myshipserv_Exception_MarketSizing
         * @throws  Shipserv_Adapters_Solr_Exception
         */
        $pageFunction = function (array $documents, $dbPageSize = 100) use ($transactionType) {
            $dbTransactionType = null;
            switch ($transactionType) {
                case Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ:
                    $dbTransactionType = 'rfq';
                    break;

                case Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_QUOTE:
                    $dbTransactionType = 'quote';
                    break;

                case Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER:
                    $dbTransactionType = 'ord';
                    break;

                default:
                    throw new Myshipserv_Exception_MarketSizing("Unknown transaction type " . $transactionType);
            }

            $insertUnion = array();
            foreach ($documents as $doc) { /** @var Shipserv_Adapters_Solr_LineItems_Document $doc */
                $insertUnion[] =
                    "SELECT '" . $dbTransactionType . "', " .
                    $doc->getTransactionDocumentId() . ", " .
                    $doc->getNumber() . " FROM dual";
            }

            $db = Shipserv_Helper_Database::getSsreport2Db();
            $dbPages = array_chunk($insertUnion, $dbPageSize);

            foreach ($dbPages as $dbPageQueries) {
                $insertSql =
                    "INSERT INTO market_sizing_tmp_buffer (transaction_type, transaction_id, line_item_no)" . PHP_EOL .
                    implode(" UNION" . PHP_EOL, $dbPageQueries);
                $db->query($insertSql);
            }

            return count($insertUnion);
        };

        foreach ($queries as $query) {
            $query->removeField('*');
            $query->removeField('score');
            $query->addField(Shipserv_Adapters_Solr_LineItems_Index::FIELD_LINE_ITEM_NUMBER);

            switch ($transactionType) {
                case Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER:
                    $query->addField(Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_ID);
                    break;

                case Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_QUOTE:
                    $query->addField(Shipserv_Adapters_Solr_LineItems_Index::FIELD_QUOTE_ID);
                    break;

                case Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ:
                    $query->addField(Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_ID);
                    break;

                default:
                    throw new Myshipserv_Exception_MarketSizing("Unknown transaction type " . $transactionType);
            }

            $lineItemCount += $this->processAllValues($query, $pageFunction);
        }

        return $lineItemCount;
    }

    /**
     * Matches in Solr and stores in the database RFQ line items
     *
     * @param   array   $queryDrafts
     *
     * @return  int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function loadRfqLineItemsToDb(array $queryDrafts)
    {
        return $this->_loadLineItemsToDb($queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ);
    }

    /**
     * Matches in Solr and stores in the database quote line items
     *
     * @param   array   $queryDrafts
     *
     * @return  int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function loadQuoteLineItemsToDb(array $queryDrafts)
    {
        return $this->_loadLineItemsToDb($queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_QUOTE);
    }

    /**
     * Matches in Solr and stores in the database order line items
     *
     * @param   array   $queryDrafts
     *
     * @return  int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function loadOrderLineItemsToDb(array $queryDrafts)
    {
        return $this->_loadLineItemsToDb($queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER);
    }

    /**
     * @param   Solarium_Query_Select[] $queryDrafts
     * @param   int $orderLineItemCount
     *
     * @return  float
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function getOrderTotalLineItemCost(array $queryDrafts, &$orderLineItemCount = null)
    {
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER
        );

        $orderLineItemCount = 0;
        $totalLineItemCost = $this->_getFieldValueSum(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_TOTAL_COST_USD,
            null,
            $orderLineItemCount
        );

        return $totalLineItemCost;
    }

    /**
     * @param   Solarium_Query_Select[] $queryDrafts
     *
     * @return  float
     * @throws  Solarium_Exception
     */
    public function getOrderTotalCost(array $queryDrafts)
    {
        // we need to calculate total cost of unique orders while our documents are line items
        $orderTotalCost = 0;

        $statsField = Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_TOTAL_COST_USD;
        $facetField = Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_ID;

        foreach ($queryDrafts as $queryDraft) {
            // first retrieve all unique orders by faceting on order ID
            $query = $this->makeOrderQuery($queryDraft);

            $query->getStats()->createField($statsField);
            $query->getStats()->addFacet($facetField);

            $results = $this->client->select($query, true, true);

            $statsFacets = $results->getStats()->getResult($statsField)->getFacets();
            $statsFacets = $statsFacets[$facetField];

            if (empty($statsFacets)) {
                continue;
            }

            foreach ($statsFacets as $value) { /** @var Solarium_Result_Select_Stats_FacetValue $value */
                if ($value->getCount() !== 0) {
                    $orderTotalCost += $value->getSum() / $value->getCount();
                }
            }
        }

        return $orderTotalCost;
    }

    /**
     * Returns sums of line item quantity broken down by units
     *
     * @param   Solarium_Query_Select[] $queryDrafts
     *
     * @return  array
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function getOrderLineItemQuantityByUnits(array $queryDrafts)
    {
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER
        );

        $units = $this->_getFieldDistinctValues(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_UNIT
        );

        if (empty($units)) {
            return array();
        }

        // obtain helper instance for escaping queries
        $dummyQuery = new Solarium_Query_Select();
        $helper = $dummyQuery->getHelper();

        $unitCount = array();
        foreach ($units as $unit) {
            // prepare a filter for the unit
            $unitFilter = new Solarium_Query_Select_FilterQuery();
            $unitFilter
                ->setKey('unitFilter_' . $unit)
                ->setQuery(Shipserv_Adapters_Solr_LineItems_Index::FIELD_UNIT . ':' . $helper->escapePhrase($unit));
            ;

            $unitCostStats = $this->_getFieldStats(
                $queries,
                Shipserv_Adapters_Solr_LineItems_Index::FIELD_UNIT_COST_USD,
                $unitFilter
            );

            // calculate total quantity for a particular unit
            $unitCount[$unit] = array(
                'totalQuantity' => $this->_getFieldValueSum(
                    $queries,
                    Shipserv_Adapters_Solr_LineItems_Index::FIELD_QUANTITY,
                    $unitFilter
                ),
                'averageUnitCost' => $unitCostStats['mean'],
                'minUnitCost'     => $unitCostStats['min'],
                'lineItemCount'   => $unitCostStats['count'],
            );
        }

        $sortByFrequency = function (array $a, array $b) {
            if ($a['lineItemCount'] > $b['lineItemCount']) {
                return -1;
            } else if ($a['lineItemCount'] < $b['lineItemCount']) {
                return 1;
            }

            return 0;
        };

        uasort($unitCount, $sortByFrequency);

        return $unitCount;
    }

    /**
     * @param   Solarium_Query_Select[] $queryDrafts
     * @param   int $orderLineItemCount
     *
     * @return  float
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function getOrderTotalLineItemQuantity(array $queryDrafts, &$orderLineItemCount = null)
    {
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER
        );
        $orderLineItemCount = 0;

        $totalLineItemCost = $this->_getFieldValueSum(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_QUANTITY,
            null,
            $orderLineItemCount
        );

        return $totalLineItemCost;
    }

    /**
     * @param   Solarium_Query_Select[] $queryDrafts
     *
     * @return  int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function getRfqEventCount(array $queryDrafts)
    {
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ
        );

        $rfqEventCount = $this->_getFieldDistinctValueCount(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_EVENT_HASH
        );

        return $rfqEventCount;
    }

    /**
     * Returns the number of matching vessels mentioned in RFQs
     *
     * @param Solarium_Query_Select[] $queryDrafts
     *
     * @return  int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function getRfqVesselCount(array $queryDrafts)
    {
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ
        );

        $filterValidImo = new Solarium_Query_Select_FilterQuery();
        $filterValidImo
            ->setKey('filterValidImo')
            ->setQuery(Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_VESSEL_IMO_VALID . ':1');

        $vesselImoCount = $this->_getFieldDistinctValueCount(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_VESSEL_IMO,
            $filterValidImo
        );

        return $vesselImoCount;
    }

    /**
     * Adds filters specific to given transaction type to the given query draft
     *
     * @param   Solarium_Query_Select|Solarium_Query_Select[]   $queryDrafts
     * @param   string  $transactionType
     *
     * @return  Solarium_Query_Select|Solarium_Query_Select[]
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    protected function _getTransactionQuery($queryDrafts, $transactionType)
    {
        if (!is_array($queryDrafts)) {
            $wasArray = false;
            $queryDrafts = array($queryDrafts);
        } else {
            $wasArray = true;
        }

        $queries = array();

        foreach ($queryDrafts as $queryDraft) {
            switch ($transactionType) {
                case Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER:
                    $query = $this->makeOrderQuery($queryDraft);
                    break;

                case Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ:
                    $query = $this->makeRfqQuery($queryDraft);
                    break;

                default:
                    throw new Myshipserv_Exception_MarketSizing("Invalid transaction type supplied: " . $transactionType);
            }

            $queries[] = $query;
        }

        if ($wasArray) {
            return $queries;
        } else {
            return $queries[0];
        }
    }

    /**
     * @param Solarium_Query_Select[] $queryDrafts
     *
     * @return int
     * @throws Myshipserv_Exception_MarketSizing
     * @throws Solarium_Exception
     */
    public function getRfqBuyerBranchCount(array $queryDrafts)
    {
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ
        );

        return $this->_getFieldDistinctValueCount(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_BUYER_BRANCH_ID
        );
    }

    /**
     * @param Solarium_Query_Select[] $queryDrafts
     *
     * @return int
     * @throws Myshipserv_Exception_MarketSizing
     * @throws Solarium_Exception
     */
    public function getRfqBuyerOrgCount(array $queryDrafts)
    {
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ
        );

        return $this->_getFieldDistinctValueCount(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_BUYER_ORG_ID
        );
    }

    /**
     * @param Solarium_Query_Select[] $queryDrafts
     *
     * @return int
     * @throws Myshipserv_Exception_MarketSizing
     * @throws Solarium_Exception
     */
    public function getOrderBuyerBranchCount(array $queryDrafts)
    {
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER
        );

        return $this->_getFieldDistinctValueCount(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_BUYER_BRANCH_ID
        );
    }
    
    /**
     * @param Solarium_Query_Select[] $queryDrafts
     *
     * @return int
     * @throws Myshipserv_Exception_MarketSizing
     * @throws Solarium_Exception
     */
    public function getOrderSupplierBranchCount(array $queryDrafts)
    {
    	$queries = $this->_getTransactionQuery(
    	    $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER
        );
    	
    	return $this->_getFieldDistinctMultipleValuesCount(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_SUPPLIER_BRANCH_ID
        );
    }

    /**
     * @param Solarium_Query_Select[] $queryDrafts
     *
     * @return  int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function getOrderBuyerOrgCount(array $queryDrafts)
    {
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER
        );

        return $this->_getFieldDistinctValueCount(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_BUYER_ORG_ID
        );
    }

    /**
     * Returns the number of matching vessels mentioned in orders
     *
     * @param Solarium_Query_Select[] $queryDrafts
     *
     * @return  int
     * @throws  Solarium_Exception
     * @throws  Myshipserv_Exception_MarketSizing
     */
    public function getOrderVesselCount(array $queryDrafts)
    {
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER
        );

        $filterValidImo = new Solarium_Query_Select_FilterQuery();
        $filterValidImo
            ->setKey('filterValidImo')
            ->setQuery(Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_VESSEL_IMO_VALID . ':1');

        $vesselImoCount = $this->_getFieldDistinctValueCount(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_VESSEL_IMO,
            $filterValidImo
        );

        return $vesselImoCount;
    }

    /**
     * @param   Solarium_Query_Select[]   $queryDrafts
     *
     * @return int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function getRfqCount(array $queryDrafts)
    {
        // since in the line index we index only one RFQ per event, we need to do it in two steps
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ
        );

        // step 1: retrieve a list of matching event hashes from the index
        $eventHashes = $this->_getFieldDistinctValues(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_EVENT_HASH
        );

        if (empty($eventHashes)) {
            return 0;
        }

        // step 2: find the number of RFQs in those events in the given date range in the database
        $db = Shipserv_Helper_Database::getDb();

        foreach ($eventHashes as $key => $value) {
            $eventHashes[$key] = $db->quoteInto('?', $value);
        }

        $selectDraft = new Zend_Db_Select($db);
        $selectDraft
            ->from(
                Shipserv_Rfq::TABLE_NAME,
                array('COUNT' => new Zend_Db_Expr('COUNT(*)'))
            )
            ->where(Shipserv_Rfq::COL_STATUS . ' = ?', Shipserv_Rfq::STATUS_SUBMITTED)
            ->where(
                Shipserv_Rfq::COL_DATE . ' >= ' .
                Shipserv_Helper_Database::getOracleDateExpr($this->filters[self::FILTER_DATE_FROM], true)
            )
            ->where(
                Shipserv_Rfq::COL_DATE . ' <= ' .
                Shipserv_Helper_Database::getOracleDateExpr($this->filters[self::FILTER_DATE_TO], true)
            );

        $count = 0;

        $start = 0;
        $step = 500; // how many event hashes per one query

        $iterations = 0;
        $elapsed = 0;
        while (count($eventSlice = array_slice($eventHashes, $start, $step))) {
            $timeStart = microtime(true);
            $start += $step;

            $select = clone($selectDraft);
            $select->where(Shipserv_Rfq::COL_EVENT_HASH . ' IN (HEXTORAW(' . implode('), HEXTORAW(', $eventSlice) . '))');

            $sql = $select->assemble();
            $cacheKey = __METHOD__ . '_' . md5($sql);

            $result = $this->fetchCachedQuery($sql, null, $cacheKey);
            $count += $result[0]['COUNT'];

            $elapsed += microtime(true) - $timeStart;
            $iterations++;
        }

        return $count;
    }

    /**
     * @param   Solarium_Query_Select[]   $queryDrafts
     *
     * @return  int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function getOrderCount(array $queryDrafts)
    {
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER
        );

        return $this->_getFieldDistinctValueCount(
            $queries,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_ORDER_ID
        );
    }

    /**
     * @param Solarium_Query_Select[] $queryDrafts
     *
     * @return int
     * @throws  Solarium_Exception
     * @throws  Myshipserv_Exception_MarketSizing
     */
    public function getRfqLineItemCount(array $queryDrafts)
    {
        $queries = $this->_getTransactionQuery(
            $queryDrafts, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ
        );
        $lineItemCount = 0;

        foreach ($queries as $query) {
            $results = $this->client->select($query, true, true);
            $lineItemCount += $results->getNumFound();
        }

        return $lineItemCount;
    }

    /**
     * @param   array|string    $keywords
     *
     * @return  int
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function getPagesSearchCount($keywords)
    {
        if (!is_array($keywords)) {
            $keywords = array($keywords);
        }

        $db = Shipserv_Helper_Database::getSsreport2Db();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                'pages_search_stats',
                array('COUNT' => new Zend_Db_Expr('COUNT(*)'))
            );

        $where = array();
        foreach ($keywords as $kw) {
            // $where[] = $db->quoteInto('TRIM(LOWER(pst_search_text)) = TRIM(LOWER(?))', $kw);
            $where[] = $db->quoteInto('CONTAINS(pst_search_text, TRIM(LOWER(?))) > 0', $kw);
        }

        $select
            ->where(implode(' AND ', $where))
            ->where(
                'pst_search_date >= ' .
                Shipserv_Helper_Database::getOracleDateExpr($this->filters[self::FILTER_DATE_FROM], true)
            )
            ->where(
                'pst_search_date <= ' .
                Shipserv_Helper_Database::getOracleDateExpr($this->filters[self::FILTER_DATE_TO], true) . '+0.999999'
            );

        $sql = $select->assemble();

        $cacheKey = __METHOD__ . '_' . md5($sql);
        $result = $this->fetchCachedQuery($sql, null, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

        $count = (int) $result[0]['COUNT'];

        return $count;
    }

    /**
     * Returns IMOs of vessels which have GMV over the given rate
     *
     * @param   float       $annualTargetGmv
     *
     * @return  array
     * @throws  Shipserv_Helper_Database_Exception
     * @throws  Exception
     */
    protected function _getVesselsOverTargetGmv($annualTargetGmv)
    {
        $dateFrom   = $this->filters[self::FILTER_DATE_FROM];
        $dateTo     = $this->filters[self::FILTER_DATE_TO];
        $vesselType = $this->filters[self::FILTER_VESSEL_TYPE];

        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                array(
                    'IMO' => 'ord.' . Shipserv_PurchaseOrder::COL_VESSEL_IMO
                )
            )
            ->join(
                array('isv' => 'ihs_ship_type_vessel@ssreport2.shipserv.com'),
                'isv.isv_imo = ord.' . Shipserv_PurchaseOrder::COL_VESSEL_IMO,
                array()
            )
            ->join(
                array('cur' => Shipserv_Oracle_Currency::TABLE_NAME),
                implode(
                    ' AND ',
                    array(
                        'cur.' . Shipserv_Oracle_Currency::COL_ID . ' = ord.' . Shipserv_PurchaseOrder::COL_CURRENCY,
                        $db->quoteInto(
                            'cur.' . Shipserv_Oracle_Currency::COL_EXCHANGE_WITH . ' = ?',
                            Shipserv_Oracle_Currency::CUR_USD
                        )
                    )
                ),
                array()
            )
            // only valid orders in the time interval
            ->where(
                'ord.' . Shipserv_PurchaseOrder::COL_STATUS . ' = ?', Shipserv_PurchaseOrder::STATUS_SUBMITTED
            )
            ->where(
                'ord.' . Shipserv_PurchaseOrder::COL_DATE . ' >= ' .
                Shipserv_Helper_Database::getOracleDateExpr($dateFrom, true)
            )
            ->where(
                'ord.' . Shipserv_PurchaseOrder::COL_DATE . ' <= ' .
                Shipserv_Helper_Database::getOracleDateExpr($dateTo, true)
            )
            // only vessels of specified types
            ->where(
                'isv.isv_ist_code ' .
                Shipserv_Helper_Database::escapeLike($vesselType, Shipserv_Helper_Database::ESCAPE_LIKE_RIGHT)
            )
            ->group(
                array(
                    'ord.' . Shipserv_PurchaseOrder::COL_VESSEL_IMO,
                )
            );

        if (array_key_exists(self::FILTER_VESSEL_IMO, $this->filters)) {
            // limit to IMOs supplied, if there were any
            $select->where('isv.isv_imo IN (?)', $this->filters[self::FILTER_VESSEL_IMO]);
        }

        // calculate required GMV for the given time interval
        $dayCount = $dateTo->diff($dateFrom)->days;
        $targetGmv = ($annualTargetGmv / 365) * $dayCount; // target GMV in USD in the given interval

        $select->having(
            new Zend_Db_Expr(
                $db->quoteInto(
                    'SUM(ord.' . Shipserv_PurchaseOrder::COL_TOTAL_COST .
                    ' / cur.' . Shipserv_Oracle_Currency::COL_EXCHANGE_RATE . ') > ?', $targetGmv
                )
            )
        );

        $sql = $select->assemble(); // print $sql; die;
        $cacheKey = __METHOD__ . '_' . md5($sql);
        $rows = $this->fetchCachedQuery(
            $sql, null, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSERVDBA
        );

        $vesselImos = array();
        foreach ($rows as $row) {
            $vesselImos[] = $row['IMO'];
        }

        return array_unique($vesselImos);
    }

    /**
     * Returns true if calculates should be grouped by locations
     *
     * @return bool
     */
    public function isLocationFilterOn()
    {
        return (is_array($this->filters[self::FILTER_LOCATIONS]) and (!empty($this->filters[self::FILTER_LOCATIONS])));
    }
}