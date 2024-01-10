<?php
/**
 * Implements match-related Solr searches
 *
 * @author  Yuriy Akopov
 * @date    2014-02-11
 * @story   S8493
 */
class Myshipserv_Search_Match {
    const
        PAGE_SIZE = 100
    ;

    const
        SOLR_STAT_TAG   = 'tag',
        SOLR_STAT_TIME  = 'time'
    ;

    /**
     * @var null|Shipserv_Buyer
     */
    protected $buyerOrg = null;

    /**
     * @var Shipserv_Adapters_Solr_Suppliers_Index
     */
    protected $client = null;

    /**
     * Holds stats on performed Solr queries, if initialised
     *
     * @var null|array
     */
    protected static $stats = null;

    /**
     * Turns on stats logging and resets previously collected stats (or turns off if requested)
     *
     * @param   bool    $turnOff
     *
     */
    public static function initStats($turnOff = false) {
        if ($turnOff) {
            self::$stats = null;
        } else {
            self::$stats = array();
        }
    }

    /**
     * Returns collected Solr stats, if any
     *
     * @param   Shipserv_Buyer  $buyerOrg
     *
     * @return array|null
     */
    public static function getStats() {
        return self::$stats;
    }

    public function __construct(Shipserv_Buyer $buyerOrg) {
        $this->buyerOrg = $buyerOrg;
        $this->client = new Shipserv_Adapters_Solr_Suppliers_Index();
    }

    /**
     * Searches supplier index for tags. At the moment recreates legacy behavior by searching for one tag at time
     * (and thus requiring one search per tag). The only addition it makes comparing to Shipserv_Match_Search::search
     * is optional location filtering.
     *
     * @todo: This needs to be optimised later by supporting searching for many tags with different weights in one go greatly
     * saving time required to find matches
     *
     * @param   array   $tag
     * @param   array   $locations
     * @param   float   $elapsed
     *
     * @return  Solarium_Result_Select
     */
    public function searchTag(array $tag, array $locations = null, &$elapsed = null) {
        $query = $this->client->createSelect();

        $query
            ->setDocumentClass('Shipserv_Adapters_Solr_Suppliers_Document')
            ->setStart(0)
            ->setRows(self::PAGE_SIZE)
            ->setFields(array(
                // this is the list of fields requested by legacy implementation:
                // id, tnid, score, name, country, ispremiumlisting, continent, type
                Shipserv_Adapters_Solr_Suppliers_Index::FIELD_ID,
                Shipserv_Adapters_Solr_Suppliers_Index::FIELD_SCORE,
                Shipserv_Adapters_Solr_Suppliers_Index::FIELD_NAME,
                Shipserv_Adapters_Solr_Suppliers_Index::FIELD_COUNTRY
            ))
            ->addSort(Shipserv_Adapters_Solr_Suppliers_Index::FIELD_SCORE, 'desc')
        ;

        // this is how the legacy query has been built:
        // return 'description:"' . str_replace('"', '\"', $searchStr)  . '" orders:"' . str_replace('"', '\"', $searchStr) . '" catalog:"' . str_replace('"', '\"', $searchStr) . '"';
        $term = $query->getHelper()->escapePhrase($tag[Shipserv_Match_Match::TERM_TAG]);
        $searchableFields = array(
            Shipserv_Adapters_Solr_Suppliers_Index::FIELD_NAME,
            Shipserv_Adapters_Solr_Suppliers_Index::FIELD_DESCRIPTION,
            Shipserv_Adapters_Solr_Suppliers_Index::FIELD_ORDERS,
            Shipserv_Adapters_Solr_Suppliers_Index::FIELD_CATALOG
        );

        $queries = array();
        foreach ($searchableFields as $field) {
            $queries[] = $field . ':' . $term;
        }

        $queryStr = implode(' OR ', $queries);
        $query->setQuery($queryStr);

        if (!is_null($locations)) {
            if (Shipserv_Match_Settings::get(Shipserv_Match_Settings::LOCATION_WEIGHTS_ENABLED)) {
                $query = $this->addLocationFilterQuery($query, $locations);
            } else {
                $query = $this->addSimpleLocationFilterQuery($query, $locations);
            }
        }

        $query = $this->addSupplierFilterQuery($query);

        $timeStart = microtime(true);
        $result = $this->client->select($query);
        $elapsed = microtime(true) - $timeStart;

        if (!is_null(self::$stats)) {
            self::$stats[] = array(
                self::SOLR_STAT_TAG  => $tag,
                self::SOLR_STAT_TIME => $elapsed
            );
        }

        return $result;
    }

    /**
     * A simple location filter which was in use prior to weighted locations (Pages 4.7.4)
     * Unlike addLocationFilterQuery() this is a strict filter, suppliers from outside the given list of countries
     * are simply removed from the list, not pushed down it.
     *
     * @param   Solarium_Query_Select   $query
     * @param   array                   $locations
     *
     * @return  Solarium_Query_Select
     * @throws  Solarium_Exception
     */
    protected function addSimpleLocationFilterQuery(Solarium_Query_Select $query, array $locations) {
        if (empty($locations)) {
            return $query;
        }

        $locationFilter = new Solarium_Query_Select_FilterQuery();
        $locationFilter->setKey('locationFilter');

        $countries = array();
        foreach ($locations as $locTerm) {
            $countries[] = $query->getHelper()->escapePhrase($locTerm[Shipserv_Match_Match::TERM_ID]);
        }

        $locationFilter->setQuery(
            Shipserv_Adapters_Solr_Suppliers_Index::FIELD_COUNTRY . ':(' . implode(' OR ', $countries) . ')'
        );

        $query->addFilterQuery($locationFilter);

        return $query;
    }

    /**
     * Adds location filters to supplier index query
     *
     * @param   Solarium_Query_Select   $query
     * @param   array                   $locations
     *
     * @return  Solarium_Query_Select
     */
    protected function addLocationFilterQuery(Solarium_Query_Select $query, array $locations) {
        if (empty($locations)) {
            return $query;
        }

        $mainQueryStr = $query->getQuery();

        $relevantLocations = array();
        foreach($locations as $locTerm) {
            $country = $query->getHelper()->escapePhrase($locTerm[Shipserv_Match_Match::TERM_ID]);
            $relevantLocations[] =
                Shipserv_Adapters_Solr_Suppliers_Index::FIELD_COUNTRY . ':' . $country . '^' .
                // in-Solr boost for matching country field
                ceil($locTerm[Shipserv_Match_Match::TERM_SCORE] / Shipserv_Match_Settings::get(Shipserv_Match_Settings::LOCATION_SCORE_DENOMINATOR))
            ;
        }

        $queryStr =
           // still return all the results regardless of locations (so in case we have specified very rare locations our results will still be full)
           '(' . $mainQueryStr . ')' .
           ' OR (' .
                // but there locations match the given list, grant a boost to bring those suppliers on top
                '(' . $mainQueryStr . ')' .
                ' AND (' .
                    '(' . implode(') OR (', $relevantLocations) . ')' .
                ')' .

           ')'
        ;

        // print $queryStr; die;
        /*
        $query->getDisMax()->setQueryFields(
            implode(' ', $searchableFields) . ' ' .
            Shipserv_Adapters_Solr_Suppliers_Index::FIELD_COUNTRY . '^' . self::LOCATION_BOOST
        );
        */

        $query->setQuery($queryStr);

        return $query;
    }

    /**
     * Checks if buyer has black- or whitelist enabled and adds a filter query for Solr to reflect that
     *
     * @param   Solarium_Query_Select $query
     *
     * @return  Solarium_Query_Select
     */
    protected function addSupplierFilterQuery(Solarium_Query_Select $query) {
        $list = new Shipserv_Buyer_SupplierList($this->buyerOrg);

        // limit results to whitelisted suppliers if whitelist is enabled
        if ($list->isEnabled(Shipserv_Buyer_SupplierList::TYPE_WHITELIST)) {
            $whitelistFilter = new Solarium_Query_Select_FilterQuery();
            $whitelistFilter->setKey('whiteList');

            $supplierIds = $list->getListedSuppliers(Shipserv_Buyer_SupplierList::TYPE_WHITELIST);
            if (empty($supplierIds)) {
                // white list is enabled with no suppliers in it, reject all the suppliers (return nothing)
                $whitelistFilter->setQuery('-' . Shipserv_Adapters_Solr_Suppliers_Index::FIELD_ID . ':[0 TO *]');

            } else {
                $whitelistFilter->setQuery(Shipserv_Adapters_Solr_Suppliers_Index::FIELD_ID . ':' . self::arrayToRange($supplierIds, $query));
            }

            $query->addFilterQuery($whitelistFilter);
        }

        // exclude blacklisted suppliers if blacklist is enabled
        if ($list->isEnabled(Shipserv_Buyer_SupplierList::TYPE_BLACKLIST)) {
            $supplierIds = $list->getListedSuppliers(Shipserv_Buyer_SupplierList::TYPE_BLACKLIST);
            if (!empty($supplierIds)) {
                $blacklistFilter = new Solarium_Query_Select_FilterQuery();
                $blacklistFilter->setKey('blackList');
                $blacklistFilter->setQuery('-' . Shipserv_Adapters_Solr_Suppliers_Index::FIELD_ID . ':' . self::arrayToRange($supplierIds, $query));
                $query->addFilterQuery($blacklistFilter);
            }
        }

        return $query;
    }

    /**
     * Converts given values into Solr OR sequence range
     *
     * @param   array                   $values
     * @param   Solarium_Query_Select   $query
     *
     * @return  string
     */
    protected static function arrayToRange(array $values, Solarium_Query_Select $query) {
        $escaped = array();
        $helper = $query->getHelper();

        foreach($values as $val) {
            $escaped[] = $helper->escapePhrase($val);
        }

        return '(' . implode(' OR ', $escaped) . ')';
    }
}