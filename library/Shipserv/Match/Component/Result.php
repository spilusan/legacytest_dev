<?php
/**
 * Match engine search result item (matched supplier with related meta data)
 *
 * @author  Yuriy Akopov
 * @date    2013-08-13
 * @story   S7924
 */
class Shipserv_Match_Component_Result extends Shipserv_Match_Component_Item implements Shipserv_Helper_Database_Object {
    const
        TABLE_NAME      = 'MATCH_RFQ_RESULT',
        SEQUENCE_NAME   = 'SQ_MATCH_RFQ_RESULT_ID',
        // database columns
        COL_ID          = 'MRR_ID',
        COL_SEARCH_ID   = 'MRR_MRS_ID',
        COL_SUPPLIER_ID = 'MRR_SPB_BRANCH_CODE',
        COL_COMMENT     = 'MRR_COMMENT',
        COL_SCORE       = 'MRR_SCORE',
        COL_FEED_TYPE   = 'MRR_FEED_TYPE'
    ;

    // values supported for Feed Type column
    const
        FEED_TYPE_MATCHES   = 'matches',
        FEED_TYPE_AT_RISK   = 'at_risk',
        FEED_TYPE_AUTO_DYNAMIC      = 'autoDynamic',
        FEED_TYPE_AUTO_KEYWORDS     = 'autoKeywords'
    ;

    /**
     * @var int
     */
    protected $supplierId = null;

    /**
     * @var string
     */
    protected $comment = null;

    /**
     * @var Shipserv_Supplier
     */
    protected $supplier = null;

    /**
     * @var string
     */
    protected $feedType = null;

    /**
     * Maps supplied database row onto object fields
     *
     * @param   array   $fields
     */
    public function fromDbRow(array $fields) {
        // updating database fields
        $this->id           = $fields[self::COL_ID];
        $this->searchId     = $fields[self::COL_SEARCH_ID];
        $this->supplierId   = $fields[self::COL_SUPPLIER_ID];
        $this->comment      = $fields[self::COL_COMMENT];
        $this->score        = $fields[self::COL_SCORE];
        $this->feedType     = $fields[self::COL_FEED_TYPE];

        $this->supplier     = null;
    }

    /**
     * Returns current field values mapped to database columns
     *
     * @return array
     */
    public function toDbRow() {
        $fields = array(
            self::COL_ID            => $this->getId(),
            self::COL_SEARCH_ID     => $this->getSearchId(),
            self::COL_SUPPLIER_ID   => $this->getSupplierId(),
            self::COL_COMMENT       => self::truncateStringDbValue($this->getComment(), self::TABLE_NAME, self::COL_COMMENT),
            self::COL_SCORE         => self::roundFloatDbValue($this->getScore(), self::TABLE_NAME, self::COL_SCORE),
            self::COL_FEED_TYPE     => $this->getFeedType()
        );

        return $fields;
    }

    public function getSupplierId() {
        return $this->supplierId;
    }

    public function getComment() {
        return $this->comment;
    }

    public function getFeedType() {
        return $this->feedType;
    }

    /**
     * Returns the supplier this search result symbolises
     *
     * @return  Shipserv_Supplier
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function getSupplier() {
        if (is_null($this->supplierId)) {
            throw new Shipserv_Helper_Database_Exception('No supplier for the search match');
        }

        if (!is_null($this->supplier)) {
            return $this->supplier;
        }

        $this->supplier = Shipserv_Supplier::getInstanceById($this->supplierId, '', true);

        return $this->supplier;
    }

    /**
     * Retrieves the stored search result item by specified id
     *
     * @param   int $id
     *
     * @return  Shipserv_Match_Component_Result
     * @throws  Shipserv_Helper_Database_Exception
     */
    public static function getInstanceById($id) {
        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select->from(
            array('mrr' => self::TABLE_NAME),
            array('mrr.*')
        )
            ->where('mrr.' . self::COL_ID . ' = :id')
        ;

        $row = $select->getAdapter()->fetchRow($select, array('id' => $id), Zend_Db::FETCH_ASSOC);
        if (!$row) {
            throw new Shipserv_Helper_Database_Exception('Requested search result item cannot be found');
        }

        $instance = new self($row);

        return $instance;
    }

    /**
     * Converter from the legacy matched supplier information structure returned by the match engine
     *
     * @todo: should be removed later as the match engine should return results as Component_Results straight away
     *
     * @param   Shipserv_Match_Component_Search $search
     * @param   string  $feedType
     * @param   array   $matchedSupplierInfo
     *
     * @return  Shipserv_Match_Component_Result
     */
    public static function fromMatchEngine(Shipserv_Match_Component_Search $search, $feedType, array $matchedSupplierInfo) {
        $instance = new self();

        $instance->fromDbRow(array(
            self::COL_ID            => null,
            self::COL_SEARCH_ID     => $search->getId(),
            self::COL_SUPPLIER_ID   => $matchedSupplierInfo[Shipserv_Match_Match::RESULT_SUPPLIER_ID],
            self::COL_COMMENT       => $matchedSupplierInfo[Shipserv_Match_Match::RESULT_COMMENT],
            self::COL_SCORE         => $matchedSupplierInfo[Shipserv_Match_Match::RESULT_SCORE],
            self::COL_FEED_TYPE     => $feedType
        ));

        return $instance;
    }

    /**
     * Converter function from the initialised object into legacy matched supplier information returned by the match engine
     *
     * @todo: should be removed later as the match engine should return results as Component_Results straight away
     *
     * @return array
     */
    public function toMatchEngine() {
        $match = new Shipserv_Match_Match(null);
        $supplier = $this->getSupplier();

        $matchedSupplierInfo = array(
            Shipserv_Match_Match::RESULT_SUPPLIER_ID   => $supplier->tnid,
            Shipserv_Match_Match::RESULT_CATEGORIES    => $match->getSupplierCategories($this->getSupplierId()),
            Shipserv_Match_Match::RESULT_COMMENT       => $this->getComment(),
            Shipserv_Match_Match::RESULT_SCORE         => $this->getScore()
            // Shipserv_Match_Match::RESULT_ORIGINAL      => !($this->isNew())
        );

        return $matchedSupplierInfo;
    }

    /**
     * Saves or updates the search data into the database
     *
     * @throws Exception
     */
    public function save() {
        if (is_null($this->supplierId)) {
            throw new Exception('A search match without a supplier assigned cannot be saved');
        }

        if (is_null($this->searchId)) {
            throw new Exception('A search match cannot be saved without being connected to a search');
        }

        $db = $this->getDb();

        if (strlen($this->id)) {    // we expect is_null to work here, but strlen is safe in case it's '' for some reason
            // database ID assigned, attempting to update the existing record
            $db->update(self::TABLE_NAME, $this->toDbRow(), $db->quoteInto(self::COL_ID . ' = ?', $this->id));
        } else {
            // no database ID, inserting a new record
            $db->insert(self::TABLE_NAME, $this->toDbRow());
            $this->id = $db->lastSequenceId(self::SEQUENCE_NAME);
        }
    }

    /**
     * Builds a query to retrieve results of the given search, including or excluding original and "sent to" suppliers
     *
     * @param   Shipserv_Match_Component_Search $search
     * @param   string  $feedType
     * @param   bool    $nonRfqSuppliersOnly    if true, includes only suppliers the RFQ hasn't been sent to yet
     *
     * @return  Zend_Db_Select
     */
    protected static function getSearchSelect(Shipserv_Match_Component_Search $search, $feedType = self::FEED_TYPE_MATCHES, $nonRfqSuppliersOnly = true) {
        $db = self::getDb();

        $select = new Zend_Db_Select($db);

        $select->from(
            array('mrr' => self::TABLE_NAME),
            array('mrr.*')
        )
            ->where('mrr.' . self::COL_SEARCH_ID . ' = ?', $search->getId())
            ->where('mrr.' . self::COL_FEED_TYPE . ' = ?', $feedType)
            ->order('mrr.' . self::COL_SCORE . ' DESC')
            ->order('mrr.' . self::COL_ID . ' ASC')
        ;

        if ($nonRfqSuppliersOnly) {
            $originalSupplierIds = $search->getOriginalSuppliers();
            if (count($originalSupplierIds)) {
                $select->where('mrr.' . self::COL_SUPPLIER_ID . ' NOT IN (?)', $originalSupplierIds);
            }
        }

        return $select;
    }

    /**
     * Returns results for the given search in one chunk without pagination
     *
     * @param   Shipserv_Match_Component_Search     $search
     * @param   string  $feedType
     * @param   bool    $nonRfqSuppliersOnly
     *
     * @return  Shipserv_Match_Component_Result[]
     */
    public static function getAllForSearch(Shipserv_Match_Component_Search $search, $feedType = self::FEED_TYPE_MATCHES, $nonRfqSuppliersOnly = true) {
        $select = self::getSearchSelect($search, $feedType, $nonRfqSuppliersOnly);

        $db = $select->getAdapter();
        $rows = $db->fetchAll($select, array(), Zend_Db::FETCH_ASSOC);
        if (!$rows) {
            return array();
        }

        $instances = array();
        foreach ($rows as $row) {
            $instances[] = new self($row);
        }

        return $instances;
    }

    /**
     * Instantiates and returns search results object on the current page in the provided paginator
     * Is it expected that the paginator was earlier obtained from ::getSearchPaginator()
     *
     * @param   Zend_Paginator  $paginator
     *
     * @return  Shipserv_Match_Component_Result[]
     */
    public static function getFromPaginator(Zend_Paginator $paginator) {
        $rows = $paginator->getCurrentItems();
        if (!$rows) {
            return array();
        }

        $instances = array();
        foreach ($rows as $row) {
            $instances[] = new self($row);
        }

        return $instances;
    }

    /**
     * Returns paginator for given search result items data (not instantiated as objects!)
     *
     * That paginator should be set to the page size and number required and instantiated objects should be
     * then obtained by passing it back to getFromPaginator()
     *
     * @param Shipserv_Match_Component_Search $search
     * @param   string      $feedType
     * @param   bool        $nonRfqSuppliersOnly
     *
     * @return Zend_Paginator
     */
    public static function getSearchPaginator(Shipserv_Match_Component_Search $search, $feedType = self::FEED_TYPE_MATCHES, $nonRfqSuppliersOnly = true) {
        $select = self::getSearchSelect($search, $feedType, $nonRfqSuppliersOnly);

        //print $select->assemble(); exit;

        $paginator = Zend_Paginator::factory($select);

        return $paginator;
    }

    /**
     * Checks if the RFQ search has been run for was sent to the particular supplier
     *
     * @return  bool
     */
    public function isNew() {
        return in_array($this->getSupplier(), $this->getSearch()->getOriginalSuppliers());
    }
}