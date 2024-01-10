<?php
/**
 * A version of pre-existent Myshipserv_Search_MarketSizing which doesn't do aggregations in Solr,
 * and only uses it for full text search to retrieve the list of document IDs with further
 * calculations happening in the database
 *
 * @author  Yuriy Akopov
 * @date    2018-03-21
 * @story   DEV-2563
 */
class Myshipserv_Search_MarketSizingDb extends Shipserv_Object
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
        LABEL_LOCATION_AVERAGE = 'All selected countries',
        LABEL_LOCATION_GLOBAL = 'Global',
        LABEL_KEYWORD_BRAND = 'Recognised brands'
    ;

    const
        FILTER_IMO_PREFIX = 'filterImo' // IMO filter query key
    ;

    /**
     * @var string|array
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

    /**
     * @var bool
     */
    protected $isBrandsRow = false;

    /**
     * @param   bool $isBrandsRow
     * @param   string|array $keywordInclude
     * @param   array $keywordsExclude
     * @param   array $filters
     * @param   int $timeoutAttempts
     * @throws Shipserv_Search_MarketSizing_Exception
     */
    public function __construct($isBrandsRow, $keywordInclude, array $keywordsExclude = array(), array $filters = array(), $timeoutAttempts = 10)
    {
        $this->client = new Shipserv_Adapters_Solr_LineItems_Index($timeoutAttempts);

        $this->isBrandsRow = $isBrandsRow;
        if ((!$this->isBrandsRow) and (is_array($keywordInclude))) {
            throw new Shipserv_Search_MarketSizing_Exception(
                "Multiple keyword request is only accepted for brand searches"
            );
        }

        $this->keywordInclude = $keywordInclude;
        $this->keywordsExclude = $keywordsExclude;

        $this->filters = $filters;
    }

    /**
     * @param   int $sessionId
     *
     * @throws Zend_Db_Adapter_Exception
     */
    public static function startSession($sessionId)
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $db->update(
            'market_sizing_request',
            array(
                'msr_session_start' => new Zend_Db_Expr('SYSDATE')
            ),
            $db->quoteInto('msr_id = ?', $sessionId)
        );
    }

    /**
     * @param   int $sessionId
     *
     * @throws Zend_Db_Adapter_Exception
     */
    public static function stopSession($sessionId)
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $db->update(
            'market_sizing_request',
            array(
                'msr_session_end' => new Zend_Db_Expr('SYSDATE')
            ),
            $db->quoteInto('msr_id = ?', $sessionId)
        );
    }

    /**
     * Returns the list of sessions which are currently active or abandoned
     *
     * @return array
     */
    public static function getActiveSessions()
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('msr' => 'market_sizing_request'),
                array(
                    'ID'        => 'msr.msr_id',
                    'KEYWORDS'  => 'msr.msr_keywords_include',
                    'PLACED'    => new Zend_Db_Expr("TO_CHAR(msr.msr_request_date, 'YYYY-MM-DD HH24:MI:SS')"),
                    'STARTED'   => new Zend_Db_Expr("TO_CHAR(msr.msr_session_start, 'YYYY-MM-DD HH24:MI:SS')"),
                    'EMAIL'     => 'msr_email'
                )
            )
            ->where('msr.msr_session_start IS NOT NULL')
            ->where('msr.msr_session_end IS NULL');

        $rows = $db->fetchAll($select);

        return $rows;
    }

    /**
     * Returns the list of sessions which are currently active or abandoned
     *
     * @return array
     */
    public static function getPendingRequests()
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('msr' => 'market_sizing_request'),
                array(
                    'ID'        => 'msr.msr_id',
                    'KEYWORDS'  => 'msr.msr_keywords_include',
                    'PLACED'    => new Zend_Db_Expr("TO_CHAR(msr.msr_request_date, 'YYYY-MM-DD HH24:MI:SS')"),
                    'EMAIL'     => 'msr_email'
                )
            )
            ->where('msr.msr_session_start IS NULL')
            ->where('msr.msr_session_end IS NULL');

        $rows = $db->fetchAll($select);

        return $rows;
    }

    /**
     * Removes keywords that appear to be brand names and adds them to the list of keywords as a separate item
     *
     * @param   array $keywords
     * @param Myshipserv_Logger_Base $logger
     *
     * @return  array
     */
    public static function sanitiseBrandsInKeywords(array $keywords, Myshipserv_Logger_Base $logger)
    {
        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('b' => Shipserv_Brand::TABLE_NAME),
                'b.' . Shipserv_Brand::COL_ID
            )
            ->joinLeft(
                array('bs' => Shipserv_Brand::SYNONYM_TABLE_NAME),
                'bs.' . Shipserv_Brand::SYNONYM_COL_ID . ' = b.' . Shipserv_Brand::COL_ID,
                array()
            )
            ->where(
                'LOWER(:keyword) IN (LOWER(b.' . Shipserv_Brand::COL_NAME . '), LOWER(' . Shipserv_Brand::SYNONYM_COL_VALUE . '))'
            );


        $brands = array();
        foreach ($keywords as $index => $value) {
            $brandMatches = $db->fetchCol($select, array('keyword' => $value));

            if (($brandMatches !== false) and (!empty($brandMatches))) {
                $logger->log('Found keyword "' . $value . '" to be a brand ID ' . implode(",", $brandMatches));
                // print('Found keyword "' . $value . '" to be a brand ID ' . implode(",", $brandMatches));

                $brands[] = $value;
            }
        }

        if (!empty($brands)) {
            // remove keywords that were recognised as brands
            foreach ($keywords as $index => $value) {
                if (in_array($value, $brands)) {
                    unset($keywords[$index]);
                }
            }

            if (count($brands) > 2) {
                // add brands as a consolidated list
                $keywords[] = $brands;
            }
        }

        $logger->log("Found and 'silenced' " . count($brands) . " brands");

        return array_values($keywords);
    }

    /**
     * Returns the list of SELECTable fields for session getter functions
     *
     * @param   string  $alias
     *
     * @return  array
     */
    protected static function getSessionSelectFields($alias = 'msr')
    {
        return array(
            'ID'         => $alias . '.msr_id',
            'REQUESTED'  => new Zend_Db_Expr("TO_CHAR(" . $alias . ".msr_request_date, 'YYYY-MM-DD HH24:MI:SS')"),
            'STARTED'    => new Zend_Db_Expr("TO_CHAR(" . $alias . ".msr_session_start, 'YYYY-MM-DD HH24:MI:SS')"),
            'ENDED'      => new Zend_Db_Expr("TO_CHAR(" . $alias . ".msr_session_end, 'YYYY-MM-DD HH24:MI:SS')"),

            'INCLUDE'       => $alias . '.msr_keywords_include',
            'EXCLUDE'       => $alias . '.msr_keywords_exclude',
            'VESSEL_TYPE'   => $alias . '.msr_ist_code',
            'LOCATIONS'     => $alias . '.msr_cnt_code',
            'DATE_FROM'     => new Zend_Db_Expr("TO_CHAR(" . $alias . ".msr_date_from, 'YYYY-MM-DD HH24:MI:SS')"),
            'DATE_TILL'     => new Zend_Db_Expr("TO_CHAR(" . $alias . ".msr_date_till, 'YYYY-MM-DD HH24:MI:SS')"),#

            'EMAIL'         => $alias . '.msr_email'
        );
    }

    /**
     * Returns the oldest unprocessed request (FIFO)
     *
     * @return array
     */
    public static function getNextRequest()
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('msr' => 'market_sizing_request'),
                self::getSessionSelectFields('msr')
            )
            ->where('msr.msr_session_start IS NULL')
            ->order('msr.msr_id');

        return $db->fetchRow($select);
    }

    /**
     * Returns request by the given ID regardless of whether it is (un)processed
     *
     * @param   int $sessionId
     *
     * @return  array
     */
    public static function getRequestById($sessionId)
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('msr' => 'market_sizing_request'),
                self::getSessionSelectFields('msr')
            )
            ->where('msr.msr_id = ?', $sessionId);

        return $db->fetchRow($select);
    }

    /**
     * Creates a request in the database to be picked up by cron job
     * Returns the ID of the session created
     *
     * @param   string  $email
     *
     * @return  int
     * @throws  Myshipserv_Search_MarketSizing_Exception_Session
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function createSessionRequest($email)
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();
        try {
            $db->insert(
                'market_sizing_request',
                array(
                    'msr_keywords_include' => $this->keywordInclude,
                    'msr_keywords_exclude' => (empty($this->keywordsExclude) ? null : implode(PHP_EOL, $this->keywordsExclude)),

                    'msr_ist_code' => $this->filters[self::FILTER_VESSEL_TYPE],
                    'msr_cnt_code' => implode(',', $this->filters[self::FILTER_LOCATIONS] ?? []),

                    'msr_date_from' => new Zend_Db_Expr(
                        Shipserv_Helper_Database::getOracleDateExpr($this->filters[self::FILTER_DATE_FROM], true)
                    ),
                    'msr_date_till' => new Zend_Db_Expr(
                        Shipserv_Helper_Database::getOracleDateExpr($this->filters[self::FILTER_DATE_TO], true)
                    ),

                    'msr_email' => $email
                )
            );
        } catch (Zend_Db_Exception $e) {

            throw new Myshipserv_Search_MarketSizing_Exception_Session(
                "Failed to created Market Sizing request: " . $e->getMessage()
            );
        }

        return $db->lastSequenceId('sq_market_sizing_request');
    }

    /**
     * Returns array of filtering parameters from the saved session request data
     *
     * @param array $row
     * @return array
     */
    public static function getFiltersFromSessionRow(array $row)
    {
        return array(
            self::FILTER_VESSEL_TYPE =>
                (strlen($row['VESSEL_TYPE']) ? $row['VESSEL_TYPE'] : null),
            self::FILTER_LOCATIONS =>
                (strlen($row['LOCATIONS']) ? explode(',', $row['LOCATIONS']) : array()),
            self::FILTER_DATE_FROM => new DateTime($row['DATE_FROM']),
            self::FILTER_DATE_TO => new DateTime($row['DATE_TILL'])
        );
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
                /*
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
                */

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
     * Splits keyphrase into separate words so they can be searched in any order
     *
     * @return  array
     */
    protected function _getKeywordsInclude()
    {
        $words = explode(" ", $this->keywordInclude);
        $keywords = array();

        foreach ($words as $value) {
            $kwd = trim($value);

            if (strlen($kwd) > 0) {
                $keywords[] = $kwd;
            }
        }

        return $keywords;
    }

    /**
     * @param callable  $escape
     *
     * @return string
     * @throws Shipserv_Search_MarketSizing_Exception
     */
    protected function _prepareQueryString(callable $escape)
    {
        $keywords = $this->keywordInclude;
        if (!is_array($keywords)) {
            $keywords = array($keywords);
        }

        $terms = array();
        foreach ($keywords as $keyphrase) {
            if ($this->isBrandsRow) {
                // there is no need to search for each word in a brand name separately, so the phrase is escaped as it is
                $terms[] = $escape($keyphrase);
            } else {
                // if this is not a brand, then we need to search for all the words in the phrase, but not necessarily
                // in the same order as in the phrase itself, so every word is escaped separately
                $phraseWords = explode(" ", $keyphrase);
                $escapedWords = array();
                foreach ($phraseWords as $word) {
                    if (strlen(trim($word)) > 0) {
                        $escapedWords[] = $escape($word);
                    }
                }

                if (!empty($phraseWords)) {
                    $terms[] = implode(' AND ', $escapedWords);
                }
            }
        }

        if (empty($terms)) {
            throw new Shipserv_Search_MarketSizing_Exception(
                "No search terms generated from " .
                (is_array($this->keywordInclude) ? implode(" ", $this->keywordInclude) : $this->keywordInclude)
            );
        }

        return "(" . implode(") OR (", $terms) . ")";
    }

    /**
     * Builds a draft Solr query which then can be used to retrieve transactions matching the filters for further
     * aggregation
     *
     * @return  Solarium_Query_Select
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     * @throws  Exception
     */
    public function getRowQuery()
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
            );

        $textSearchField = Shipserv_Adapters_Solr_LineItems_Index::FIELD_TXT_ALL;

        $escape = function ($string) use ($helper) {
            return $helper->escapePhrase($string);
        };

        $solrSearchQuery = $this->_prepareQueryString($escape);
        $query->setQuery($textSearchField . ':' . $solrSearchQuery);

        // print($solrSearchQuery) . PHP_EOL;

        if (!empty($this->keywordsExclude)) {
            // ...but exclude all of these
            foreach ($this->keywordsExclude as $kwIndex => $kwExclude) {
                $filterQuery = new Solarium_Query_Select_FilterQuery();
                $filterQuery
                    ->setKey('filterExcludeRow' . $kwIndex)
                    ->setQuery('-' . $textSearchField . ':' . $escape($kwExclude));

                $query->addFilterQuery($filterQuery);
            }
        }

        $query = $this->_applyFilters($query, $this->filters);

        return $query;
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
     * @param   bool    $groupByCountry
     *
     * @return array
     * @throws Shipserv_Search_MarketSizing_Exception
     */
    public function getRfqLineItemDbStats($groupByCountry = false)
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();

        // no caching because the content of the buffer is unique and only lasts for one session only
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('buf' => 'market_sizing_tmp_buffer'),
                array(
                    'RFQ_LI_COUNT' => new Zend_Db_Expr("COUNT(DISTINCT buf.transaction_id || '-' || buf.line_item_no)")
                    // 'RFQ_COUNT'    => new Zend_Db_Expr('COUNT(DISTINCT buf.transaction_id)')
                )
            )
            ->where('buf.transaction_type = ?', 'rfq');

        if ($groupByCountry) {
            // results in specific locations requested, need to facet on countries
            if (!$this->isLocationFilterOn()) {
                throw new Shipserv_Search_MarketSizing_Exception("No location filter to group by");
            }

            $select
                ->join(
                    array('rfq' => 'rfq'),
                    'rfq.rfq_internal_ref_no = buf.transaction_id',
                    array()
                )
                ->join(
                    array('spb' => 'supplier'),
                    'spb.spb_branch_code = rfq.spb_branch_code',
                    array()
                )
                ->join(
                    array('cnt' => 'country'),
                    'cnt.cnt_code = spb.spb_country_code',
                    'cnt.cnt_name'
                )
                // technically we have already filtered in Solr, but let's double check
                ->where('cnt.cnt_code IN (?)', $this->filters[self::FILTER_LOCATIONS])
                ->group('cnt.cnt_name')
                ->order('cnt.cnt_name');
        }

        if (!($response = $db->fetchAll($select))) {
            $response = array();
        }

        return $response;
    }

    /**
     * Returns basic aggregated stats for matched RFQs which are expected to be already in the buffer table
     *
     * @date    2018-02-23
     * @story   DEV-2653
     *
     * @param   bool    $groupByCountry
     *
     * @return array
     * @throws Shipserv_Search_MarketSizing_Exception
     */
    public function getRfqHeaderDbStats($groupByCountry = false)
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();

        $selectEvents = new Zend_Db_Select($db);
        $selectEvents
            ->from(
                array('rfq' => 'rfq'),
                array(
                    'rfq.rfq_event_hash'
                )
            )
            ->join(
                array('buf' => 'market_sizing_tmp_buffer'),
                'rfq.rfq_internal_ref_no = buf.transaction_id',
                array()
            )
            ->where('buf.transaction_type = ?', 'rfq')
            ->distinct();

        // no caching because the content of the buffer is unique and only lasts for one session only
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('rfq' => 'rfq'),
                array(
                    'RFQ_EVENT_COUNT'   => new Zend_Db_Expr('COUNT(DISTINCT rfq.rfq_event_hash)'),
                    'RFQ_BYB_COUNT'     => new Zend_Db_Expr(
                        'COUNT(DISTINCT CASE
                            WHEN rfq.orig_byb_branch_code IS NULL THEN rfq.byb_branch_code
                            ELSE rfq.orig_byb_branch_code
                        END)'
                    ),
                    'RFQ_BYB_TOP_COUNT' => new Zend_Db_Expr('COUNT(DISTINCT byb_top.top_branch_code)'),
                    'RFQ_SPB_COUNT'     => new Zend_Db_Expr('COUNT(DISTINCT rfq.spb_branch_code)'),
                    'RFQ_COUNT'         => new Zend_Db_Expr('COUNT(rfq.rfq_internal_ref_no)')
                )
            )
            ->join(
                array('event' => $selectEvents),
                'rfq.rfq_event_hash = event.rfq_event_hash',
                array()
            )
            ->join(
                array('byb_top' => new Zend_Db_Expr('(' . Shipserv_Buyer_Branch::getTopBranchIdQuery(true)) . ')'),
                implode(
                    ' OR ',
                    array(
                        '(' .
                        implode(
                            ' AND ',
                            array(
                                'rfq.orig_byb_branch_code IS NULL',
                                'byb_top.byb_branch_code = rfq.byb_branch_code',
                            )
                        ) .
                        ')',
                        '(' .
                        implode(
                            ' AND ',
                            array(
                                'rfq.orig_byb_branch_code IS NOT NULL',
                                'byb_top.byb_branch_code = rfq.orig_byb_branch_code',
                            )
                        ) .
                        ')'
                    )
                ),
                array()
            );

        if ($groupByCountry) {
            // results in specific locations requested, need to facet on countries
            if (!$this->isLocationFilterOn()) {
                throw new Shipserv_Search_MarketSizing_Exception("No location filter to group by");
            }

            $select
                ->join(
                    array('spb' => 'supplier'),
                    'spb.spb_branch_code = rfq.spb_branch_code',
                    array()
                )
                ->join(
                    array('cnt' => 'country'),
                    'cnt.cnt_code = spb.spb_country_code',
                    'cnt.cnt_name'
                )
                // technically we have already filtered in Solr, but let's double check
                ->where('cnt.cnt_code IN (?)', $this->filters[self::FILTER_LOCATIONS])
                ->group('cnt.cnt_name')
                ->order('cnt.cnt_name');
        }

        if (!($response = $db->fetchAll($select))) {
            $response = array();
        }

        return $response;
    }

    /**
     * Returns basic aggregated stats for matched RFQ line items which are expected to be already in the buffer table
     *
     * @date    2018-02-23
     * @story   DEV-2653
     *
     * @param   bool    $groupByCountry
     *
     * @return  array
     * @throws  Shipserv_Search_MarketSizing_Exception
     */
    public function getOrderLineItemUnitDbStats($groupByCountry = false)
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();

        // no caching because the content of the buffer is unique and only lasts for one session only
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('oli' => 'ord_line_item'),
                array(
                    'ORD_UNIT'            => 'oli.oli_unit',
                    'ORD_UNIT_COUNT'      => new Zend_Db_Expr('COUNT(oli.oli_line_item_no)'),
                    'ORD_UNIT_QUANTITY'   => new Zend_Db_Expr('SUM(oli.oli_quantity)'),
                    'ORD_UNIT_COST'       => new Zend_Db_Expr('SUM(oli.oli_total_line_item_cost_usd)')
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
            ->where('oli.ord_is_latest = 1')
            ->group('oli.oli_unit');

        if ($groupByCountry) {
            // results in specific locations requested, need to facet on countries
            if (!$this->isLocationFilterOn()) {
                throw new Shipserv_Search_MarketSizing_Exception("No location filter to group by");
            }

            $select
                ->join(
                    array('spb' => 'supplier'),
                    'spb.spb_branch_code = oli.spb_branch_code',
                    array()
                )
                ->join(
                    array('cnt' => 'country'),
                    'cnt.cnt_code = spb.spb_country_code',
                    'cnt.cnt_name'
                )
                // technically we have already filtered in Solr, but let's double check
                ->where('cnt.cnt_code IN (?)', $this->filters[self::FILTER_LOCATIONS])
                ->group('cnt.cnt_name')
                ->order(
                    array(
                        'cnt.cnt_name',
                        'ORD_UNIT_COUNT DESC'
                    )
                );
        } else {
            $select->order('ORD_UNIT_COUNT DESC');
        }

        if (!($response = $db->fetchAll($select))) {
            $response = array();
        }

        $collapseUnits = function (array $rows) {
            // assuming it is sorted by unit count;
            $mostCommonUnit = $rows[0]['ORD_UNIT'];
            $mostCommonUnitCount = $rows[0]['ORD_UNIT_COUNT'];

            $totalLiCount = 0;
            $totalQuantity = 0;
            $averageUnitCosts = array();
            foreach ($rows as $row) {
                $totalLiCount += $row['ORD_UNIT_COUNT'];
                $totalQuantity += $row['ORD_UNIT_QUANTITY'];

                if ($row['ORD_UNIT_QUANTITY'] > 0) {
                    $averageUnitCosts[] = $row['ORD_UNIT_COST'] / $row['ORD_UNIT_QUANTITY'] * $row['ORD_UNIT_COUNT'];
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
                'ORD_UNIT'          => $mostCommonUnit,
                'ORD_UNIT_SHARE'    => $mostCommonUnitShare,
                'ORD_UNIT_COST'     => $meanUnitCost
            );
        };

        if ($groupByCountry) {
            $countryRows = array();
            foreach ($response as $responseRow) {
                if (!array_key_exists($responseRow['CNT_NAME'], $countryRows)) {
                    $countryRows[$responseRow['CNT_NAME']] = array();
                }

                $countryRows[$responseRow['CNT_NAME']][] = $responseRow;
            }

            $collapsedResponse = array();
            foreach ($countryRows as $cntName => $countryUnitRows) {
                $collapsedRow = $collapseUnits($countryUnitRows);
                $collapsedRow['CNT_NAME'] = $cntName;

                $collapsedResponse[] = $collapsedRow;
            }

        } else {
            $collapsedResponse = array($collapseUnits($response));
        }

        return $collapsedResponse;
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

    /**
     * Returns the list of country names requested in location filter
     *
     * @return array
     */
    protected function getRequestedCountryNames()
    {
        if (!$this->isLocationFilterOn()) {
            return array();
        }

        $select = new Zend_Db_Select(Shipserv_Helper_Database::getSsreport2Db());
        $select
            ->from(
                array('cnt' => 'country'),
                'cnt.cnt_name'
            )
            ->where('cnt.cnt_code IN (?)', $this->filters[self::FILTER_LOCATIONS])
            ->order('cnt.cnt_name');

        return $select->getAdapter()->fetchCol($select);
    }

    /**
     * Returns basic aggregated stats for matched order line items which are expected to be already in the buffer table
     *
     * @date    2018-02-23
     * @story   DEV-2653
     *
     * @param   bool $groupByCountry
     *
     * @return array
     * @throws  Shipserv_Search_MarketSizing_Exception
     */
    public function getOrderLineItemDbStats($groupByCountry = false)
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();

        // no caching because the content of the buffer is unique and only lasts for one session only
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('oli' => 'ord_line_item'),
                array(
                    'ORD_LI_COUNT'        => new Zend_Db_Expr('COUNT(oli.oli_line_item_no)'),
                    'ORD_LI_COST'         => new Zend_Db_Expr('SUM(oli.oli_total_line_item_cost_usd)'),
                    'ORD_LI_QUANTITY'     => new Zend_Db_Expr('SUM(oli.oli_quantity)'),
                    'ORD_BYB_COUNT'       => new Zend_Db_Expr('COUNT(DISTINCT oli.byb_branch_code)'),
                    'ORD_BYB_TOP_COUNT'   => new Zend_Db_Expr('COUNT(DISTINCT byb_top.top_branch_code)'),
                    'ORD_SPB_COUNT'       => new Zend_Db_Expr('COUNT(DISTINCT oli.spb_branch_code)'),
                    'ORD_COUNT'           => new Zend_Db_Expr('COUNT(DISTINCT oli.ord_internal_ref_no)')
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
            ->join(
                array('byb_top' => new Zend_Db_Expr('(' . Shipserv_Buyer_Branch::getTopBranchIdQuery(true)) . ')'),
                'byb_top.byb_branch_code = oli.byb_branch_code',
                array()
            )
            ->where('buf.transaction_type = ?', 'ord')
            ->where('oli.ord_is_latest = 1');

        if ($groupByCountry) {
            // results in specific locations requested, need to facet on countries
            if (!$this->isLocationFilterOn()) {
                throw new Shipserv_Search_MarketSizing_Exception("No location filter to group by");
            }

            $select
                ->join(
                    array('spb' => 'supplier'),
                    'spb.spb_branch_code = oli.spb_branch_code',
                    array()
                )
                ->join(
                    array('cnt' => 'country'),
                    'cnt.cnt_code = spb.spb_country_code',
                    'cnt.cnt_name'
                )
                // technically we have already filtered in Solr, but let's double check
                ->where('cnt.cnt_code IN (?)', $this->filters[self::FILTER_LOCATIONS])
                ->group('cnt.cnt_name')
                ->order('cnt.cnt_name');
        }

        if (!($response = $db->fetchAll($select))) {
            $response = array();
        }

        return $response;
    }

    /**
     * Returns total cost of the matching orders basing on line items in the buffer table
     *
     * @param   bool    $groupByCountry
     *
     * @return  array
     * @throws  Shipserv_Search_MarketSizing_Exception
     */
    public function getOrderTotalDbCost($groupByCountry = false)
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
                    'ORD_TOTAL_COST'  => new Zend_Db_Expr('SUM(gmv.final_total_cost_usd)')
                )
            )
            ->join(
                array('buf' => $selectUniqueOrdIds),
                'gmv.ord_internal_ref_no = buf.ord_id',
                array()
            );


        if ($groupByCountry) {
            // results in specific locations requested, need to facet on countries
            if (!$this->isLocationFilterOn()) {
                throw new Shipserv_Search_MarketSizing_Exception("No location filter to group by");
            }

            $select
                ->join(
                    array('spb' => 'supplier'),
                    'spb.spb_branch_code = gmv.spb_branch_code',
                    array()
                )
                ->join(
                    array('cnt' => 'country'),
                    'cnt.cnt_code = spb.spb_country_code',
                    'cnt.cnt_name'
                )
                // technically we have already filtered in Solr, but let's double check
                ->where('cnt.cnt_code IN (?)', $this->filters[self::FILTER_LOCATIONS])
                ->group('cnt.cnt_name')
                ->order('cnt.cnt_name');
        }

        if (!($response = $db->fetchAll($select))) {
            $response = array();
        }

        return $response;
    }

    /**
     * Runs provided queries in Solr and writes down matched line items in to a temp table in Oracle
     *
     * @param   array|Solarium_Query_Select   $queryDrafts
     * @param   string  $transactionType
     *
     * @return  int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    protected function _loadLineItemsToDb($queryDrafts, $transactionType)
    {
        if (!is_array($queryDrafts)) {
            $queryDrafts = array($queryDrafts);
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
     * @param   Solarium_Query_Select   $queryDraft
     *
     * @return  int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function loadRfqLineItemsToDb(Solarium_Query_Select $queryDraft)
    {
        return $this->_loadLineItemsToDb($queryDraft, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ);
    }

    /**
     * Matches in Solr and stores in the database quote line items
     *
     * @param   Solarium_Query_Select   $queryDraft
     *
     * @return  int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function loadQuoteLineItemsToDb(Solarium_Query_Select $queryDraft)
    {
        return $this->_loadLineItemsToDb($queryDraft, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_QUOTE);
    }

    /**
     * Matches in Solr and stores in the database order line items
     *
     * @param   Solarium_Query_Select   $queryDraft
     *
     * @return  int
     * @throws  Myshipserv_Exception_MarketSizing
     * @throws  Solarium_Exception
     */
    public function loadOrderLineItemsToDb(Solarium_Query_Select $queryDraft)
    {
        return $this->_loadLineItemsToDb($queryDraft, Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_ORDER);
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
     * Returns the number of mentions of keywords in Pages Searches
     *
     * @param bool $groupByCountry
     * 
     * @return  int
     * @throws  Shipserv_Helper_Database_Exception
     * @throws Shipserv_Search_MarketSizing_Exception
     */
    public function getPagesSearchCount($groupByCountry = false)
    {
        $db = Shipserv_Helper_Database::getSsreport2Db();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                'pages_search_stats pst',
                array(
                    'COUNT' => new Zend_Db_Expr('COUNT(*)')
                )
            )
            ->where(
                'pst.pst_search_date >= ' .
                Shipserv_Helper_Database::getOracleDateExpr($this->filters[self::FILTER_DATE_FROM], true)
            )
            ->where(
                'pst.pst_search_date <= ' .
                Shipserv_Helper_Database::getOracleDateExpr($this->filters[self::FILTER_DATE_TO], true) . '+0.999999'
            );

        $escape = function ($string) use ($db) {
            return $db->quoteInto('CONTAINS(pst_search_text, ?) > 0', trim(strtolower($string)));
        };

        $searchConstraint = $this->_prepareQueryString($escape);
        $select->where($searchConstraint);

        // print($searchConstraint) . PHP_EOL;

        if (!empty($this->keywordsExclude)) {
            $whereExclude = array();
            foreach ($this->keywordsExclude as $kwd) {
                $whereExclude[] = $escape($kwd);
            }
            $select->where('NOT(' . implode(' OR ', $whereExclude) . ')');
        }

        if ($groupByCountry) {
            // results in specific locations requested, need to facet on countries
            if (!$this->isLocationFilterOn()) {
                throw new Shipserv_Search_MarketSizing_Exception("No location filter to group by");
            }

            $select
                 ->join(
                    array('cnt' => 'country'),
                    'cnt.cnt_code = pst.pst_country_code',
                    'cnt.cnt_name'
                )
                ->where('cnt.cnt_code IN (?)', $this->filters[self::FILTER_LOCATIONS])
                ->group('cnt.cnt_name')
                ->order('cnt.cnt_name');
        }

        $sql = $select->assemble();

        $cacheKey = __METHOD__ . '_' . md5($sql);
        $result = $this->fetchCachedQuery($sql, null, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

        return $result;

    }

    /**
     * Makes country name array keys and makes sure every key is listed
     *
     * @param array $rows
     * @param string $countryField
     *
     * @return array
     */
    protected function repackCountryResults(array $rows, $countryField = 'CNT_NAME')
    {
        $result = array();
        $countryNames = $this->getRequestedCountryNames();

        foreach ($countryNames as $countryName) {
            foreach ($rows as $row) {
                $currentCountry = $row[$countryField];
                unset($row[$countryField]);

                if ($currentCountry === $countryName) {
                    $result[$currentCountry] = $row;
                    continue(2);
                }
            }

            $result[$countryName] = array();
        }

        return $result;
    }

    /**
     * @param Myshipserv_Logger_Base $logger
     *
     * @return array
     * @throws Shipserv_Search_MarketSizing_Exception
     */
    protected function getMergedRfqRows(Myshipserv_Logger_Base $logger)
    {
        $rows = array();

        if ($this->isLocationFilterOn()) {
            $headerStats = $this->repackCountryResults($this->getRfqHeaderDbStats(true));
            $logger->log("Calculating RFQ header stats grouped by countries");

            $liStats = $this->repackCountryResults($this->getRfqLineItemDbStats(true));
            $logger->log("Calculating RFQ line items stats grouped by countries");

            foreach ($this->getRequestedCountryNames() as $cntName) {
                $rows[$cntName] = array_merge(
                    $headerStats[$cntName],
                    $liStats[$cntName]
                );
            }

            $headerAvgStats = $this->getRfqHeaderDbStats(false);
            $liAvgStats = $this->getRfqLineItemDbStats(false);

            $rows[self::LABEL_LOCATION_AVERAGE] = array_merge(
                $headerAvgStats[0],
                $liAvgStats[0]
            );
            $logger->log("Calculated RFQ header and line item average results");

        } else {
            $headerStats = $this->getRfqHeaderDbStats(false);
            $liStats = $this->getRfqLineItemDbStats(false);

            $rows[self::LABEL_LOCATION_GLOBAL] = array_merge(
                $headerStats[0],
                $liStats[0]
            );

            $logger->log("Calculated RFQ header and line item global results");
        }

        return $rows;
    }

    /**
     * @param Myshipserv_Logger_Base $logger
     *
     * @return array
     * @throws Shipserv_Search_MarketSizing_Exception
     */
    protected function getMergedOrderRows(Myshipserv_Logger_Base $logger)
    {
        $rows = array();

        if ($this->isLocationFilterOn()) {
            $liStats = $this->repackCountryResults($this->getOrderLineItemDbStats(true));
            $logger->log("Calculated order line items results grouped by country");

            $costStats = $this->repackCountryResults($this->getOrderTotalDbCost(true));
            $logger->log("Calculated order cost global results grouped by country");

            $unitStats = $this->repackCountryResults($this->getOrderLineItemUnitDbStats(true));
            $logger->log("Calculated order UOM global results grouped by country");

            foreach ($this->getRequestedCountryNames() as $cntName) {
                $rows[$cntName] = array_merge(
                    $liStats[$cntName],
                    $costStats[$cntName],
                    $unitStats[$cntName]
                );
            }

            $liAvgStats = $this->getOrderLineItemDbStats(false);
            $logger->log("Calculated order line items global results");

            $costAvgStats = $this->getOrderTotalDbCost(false);
            $logger->log("Calculated order cost global results");

            $unitAvgStats = $this->getOrderLineItemUnitDbStats(false);
            $logger->log("Calculated order UOM global results");

            $rows[self::LABEL_LOCATION_AVERAGE] = array_merge(
                $liAvgStats[0],
                $costAvgStats[0],
                $unitAvgStats[0]
            );

        } else {
            $liStats = $this->getOrderLineItemDbStats(false);
            $logger->log("Calculated order line items global results");

            $costStats = $this->getOrderTotalDbCost(false);
            $logger->log("Calculated order cost global results");

            $unitStats = $this->getOrderLineItemUnitDbStats(false);
            $logger->log("Calculated order UOM global results");

            $rows[self::LABEL_LOCATION_GLOBAL] = array_merge(
                $liStats[0],
                $costStats[0],
                $unitStats[0]
            );
        }

        return $rows;
    }


    /**
     * @param Myshipserv_Logger_Base $logger
     *
     * @return array
     * @throws Shipserv_Search_MarketSizing_Exception
     */
    public function getMergedSearchCountRows(Myshipserv_Logger_Base $logger)
    {
        $rows = array();

        if ($this->isLocationFilterOn()) {
            $rows = $this->repackCountryResults($this->getPagesSearchCount(true));
            $sum = 0;
            
            foreach($rows as $row) {
                $sum = $sum + (int)$row['COUNT'];
            }
            $rows[self::LABEL_LOCATION_AVERAGE]['COUNT'] = $sum;
            $logger->log("Calculating Search Count grouped by countries");
        } else {
            $pagesSearchCount = $this->getPagesSearchCount();
            $rows[self::LABEL_LOCATION_GLOBAL] = (is_array($pagesSearchCount) && count($pagesSearchCount) > 0) ? $pagesSearchCount[0] : array('COUNT' => 0);
            $logger->log("Calculated Pages Search Count for global results");
        }

        return $rows;
    }

    /**
     * @param   Myshipserv_Logger_Base $logger
     * @return  array
     *
     * @throws Exception
     * @throws Myshipserv_Exception_MarketSizing
     * @throws Shipserv_Helper_Database_Exception
     * @throws Solarium_Exception
     */
    public function getReportRow(Myshipserv_Logger_Base $logger)
    {
        $mergedPagesSearchCount = $this->getMergedSearchCountRows($logger);
        $logger->log("Pages search count done");

        $queryDraft = $this->getRowQuery();
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $db->beginTransaction();

        $rfqSolrQuery = clone($queryDraft);
        $loadedRfqLiCount = $this->loadRfqLineItemsToDb($rfqSolrQuery);
        $logger->log("Loaded " . $loadedRfqLiCount . " RFQ line items into the database");

        $orderSolrQuery = clone($queryDraft);
        $loadedOrderLiCount = $this->loadOrderLineItemsToDb($orderSolrQuery);
        $logger->log("Loaded " . $loadedOrderLiCount . " order line items into the database");

        $orderRows = $this->getMergedOrderRows($logger);
        $rfqRows = $this->getMergedRfqRows($logger);

        $db->commit();

        $rowData = array(
            'keywords' => $this->getKeywordsIncludeLabel(),
            'pagesSearchCount' => $mergedPagesSearchCount,

            'rfqs' => $rfqRows,
            'orders' => $orderRows
        );

        return $rowData;
    }

    /**
     * @return string
     */
    public function getKeywordsIncludeLabel()
    {
        if ($this->isBrandsRow) {
            return self::LABEL_KEYWORD_BRAND;
        }

        if (is_array($this->keywordInclude)) {
            return implode(", ", $this->keywordInclude);
        }

        return $this->keywordInclude;
    }
}