<?php
/**
 * Handles pre emptive match searches (which RFQ match which supplier defined keywords)
 */

class Myshipserv_Search_AutoMatch {
    const
        TIMEOUT   = 20,
        PAGE_SIZE = 500
    ;

    const
        FACET_EVENT = 'events',

        FILTER_START_FROM = 'filterStartFrom'
    ;

    /**
     * @var Shipserv_Adapters_Solr_LineItems_Index
     */
    protected $client = null;

    /**
     * @var int|null
     */
    protected $lastProcessedRfqId = null;

    /**
     * @param DateTime $dateFrom
     */
    public function __construct($lastProcessedRfqId = null) {
        $this->client = new Shipserv_Adapters_Solr_LineItems_Index();

        $this->setLastProcessedRfqId($lastProcessedRfqId);
    }

    /**
     * Sets the ID of the RFQ to start matching from, all older RFQs will be excluded from matching
     *
     * @param   int $lastProcessedRfqId
     */
    public function setLastProcessedRfqId($lastProcessedRfqId) {
        $this->lastProcessedRfqId = $lastProcessedRfqId;
    }

    /**
     * Returns a query draft with filters applied
     *
     * @param   bool    $byEvent
     *
     * @return  Solarium_Query_Select
     */
    protected function getQuery($byEvent = true) {
        $query = $this->client->createSelect();
        $query
            ->setRows(0)    // we will be working with facets and don't need documents themselves
            ->setDocumentClass('Shipserv_Adapters_Solr_LineItems_Document_Rfq')
        ;

        $helper = $query->getHelper();

        // filter to search RFQs only
        $filterTransaction = new Solarium_Query_Select_FilterQuery();
        $filterTransaction
            ->setKey('filterTransaction')
            ->setQuery(Shipserv_Adapters_Solr_LineItems_Index::FIELD_TRANSACTION_TYPE . ':' . $helper->escapePhrase(Shipserv_Adapters_Solr_LineItems_Index::TRANSACTION_TYPE_RFQ))
        ;
        $query->addFilterQuery($filterTransaction);

        // filters to search only RFQs newer than the one provided, if requested
        if ($this->lastProcessedRfqId) {
            $filterStartFrom = new Solarium_Query_Select_FilterQuery();
            $filterStartFrom
                ->setKey(self::FILTER_START_FROM)
                ->setQuery(Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_ID . ':[' . $helper->escapeTerm($this->lastProcessedRfqId) . ' TO *]')
            ;
            $query->addFilterQuery($filterStartFrom);
        }

        // faceting matching RFQ line item documents on event hashes to avoid duplication (we potentially have several line items per RFQ, then several RFQs per event)
        if ($byEvent) {
            $facetSet = $query->getFacetSet();
            $facetSet
                ->createFacetField(self::FACET_EVENT)
                ->setField(Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_EVENT_HASH)
                ->setMinCount(1)
            ;
        }

        return $query;
    }

    /**
     * Returns query for the fields in which we're looking for keywords provided by user
     *
     * @param   Solarium_Query_Select   $query
     * @param   array                   $keywords
     *
     * @return  Solarium_Query_Select
     */
    protected function addKeywordsToQuery(Solarium_Query_Select $query, array $keywords) {
        $helper = $query->getHelper();
        $escapedKeywords = array();
        foreach ($keywords as $word) {
            $escapedKeywords[] = $helper->escapePhrase($word);
        }

        $queryStr = implode(' OR ', $escapedKeywords);

        // list of index fields to search for specified keywords in
        $fields = array(
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_TXT_ALL,
            Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_ALL
        );

        $queryBits = array();
        foreach ($fields as $fieldName) {
            // queries are not escaped to allow use of Solr operators such as AND/OR/NOT
            $queryBits[] = $fieldName . ':(' . $queryStr . ')';
        }

        $queryStr = '(' . implode(') OR (', $queryBits) . ')';
        $query->setQuery($queryStr);

        return $query;
    }

    /**
     * Runs a search in Solr returning the matching RFQ information
     * Returns array of matched RFQ event hashes
     *
     * @param   array|string    $keywords
     *
     * @return  array
     */
    public function getMatchingEvents($keywords) {
        if (!is_array($keywords)) {
            $keywords = array($keywords);
        }

        $query = $this->getQuery();
        $query = $this->addKeywordsToQuery($query, $keywords);

        $pageNo = 1;
        $rfqEventHashes = array();

        // a loop to request only so many facets at once
        while(true) {
            // applying pagination settings to $query
            $eventFacetQuery = $query->getFacetSet()->getFacet(self::FACET_EVENT); /** @var  Solarium_Query_Select_Component_Facet_Field $eventFacetQuery */
            $eventFacetQuery
                ->setOffset(($pageNo - 1) * self::PAGE_SIZE)
                ->setLimit(self::PAGE_SIZE)
            ;

            // executing the query
            $results = $this->client->select($query);
            $eventFacetResult = $results->getFacetSet()->getFacet(self::FACET_EVENT); /* @var $eventFacet Solarium_Result_Select_Facet_Field */

            // add found event hashes to the list
            $pageHashes = array_keys($eventFacetResult->getValues());
            $rfqEventHashes = array_merge($rfqEventHashes, $pageHashes);

            if (count($pageHashes) < $eventFacetQuery->getLimit()) { // last page
                break;
            }

            $pageNo++;
        }

        return $rfqEventHashes;
    }

    /**
     * Returns the ID of the newest RFQ indexed
     *
     * @return  int|null
     */
    public function getLastIndexedRfqId() {
        $query = $this->getQuery(false);
        $query
            ->setStart(0)
            ->setRows(1)
            ->setQuery('*:*')
            ->setSorts(array(
                Shipserv_Adapters_Solr_LineItems_Index::FIELD_RFQ_ID => 'desc'
            ))
            ->removeFilterQuery(self::FILTER_START_FROM)
        ;

        $results = $this->client->select($query);
        $documents = $results->getDocuments(); /** @var $documents Shipserv_Adapters_Solr_LineItems_Document_Rfq[] */

        if (empty($documents)) {
            return null;
        }

        $rfqId = $documents[0]->getRfqId();

        return $rfqId;
    }
}