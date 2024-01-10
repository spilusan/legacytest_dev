<?php
/**
 * Match engine search term - brand
 *
 * A brand to be searched for, either extracted from the RFQ or added manually by user
 *
 * @author  Yuriy Akopov
 * @date    2013-08-19
 * @story   S7924
 */
class Shipserv_Match_Component_Term_Brand extends Shipserv_Match_Component_Term {
    const
        TABLE_NAME      = 'MATCH_RFQ_SEARCH_BRAND',
        SEQUENCE_NAME   = 'SQ_MATCH_RFQ_SEARCH_BRAND_ID',
        // column names
        COL_ID          = 'MRB_ID',
        COL_SEARCH_ID   = 'MRB_MRS_ID',
        COL_BRAND_ID    = 'MRB_BRAND_ID',
        COL_SCORE       = 'MRB_SCORE'
    ;

    /**
     * @var int
     */
    protected $brandId = null;

    /**
     * @var Shipserv_Brand
     */
    protected $brand = null;

    /**
     * @return int|null
     */
    public function getBrandId() {
        return $this->brandId;
    }

    /**
     * Returns the brand entity associated with the search term
     *
     * @return  Shipserv_Brand
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function getBrand() {
        if (is_null($this->brandId)) {
            throw new Shipserv_Helper_Database_Exception('No brand connected to this search term (' . __CLASS__ . ', ' . $this->getId() . ')');
        }

        if (!is_null($this->brand)) {
            return $this->brand;
        }

        $this->brand = Shipserv_Brand::getInstanceById($this->brandId);

        return $this->brand;
    }

    /**
     * Maps supplied database row onto object fields
     *
     * @param   array   $fields
     */
    public function fromDbRow(array $fields) {
        // updating database fields
        $this->id       = $fields[self::COL_ID];
        $this->searchId = $fields[self::COL_SEARCH_ID];
        $this->brandId  = $fields[self::COL_BRAND_ID];
        $this->score    = $fields[self::COL_SCORE];

        $this->search = null;
        $this->brand = null;
    }

    /**
     * Returns current field values mapped to database columns
     *
     * @return array
     */
    public function toDbRow() {
        $fields = array(
            self::COL_ID        => $this->getId(),
            self::COL_SEARCH_ID => $this->getSearchId(),
            self::COL_BRAND_ID  => $this->getBrandId(),
            self::COL_SCORE     => self::roundFloatDbValue($this->getScore(), self::TABLE_NAME, self::COL_SCORE)
        );

        return $fields;
    }

    /**
     * Retrieves the stored item by its primary key
     *
     * @param   int $id
     *
     * @return  Shipserv_Match_Component_Term_Brand
     * @throws  Shipserv_Helper_Database_Exception
     */
    public static function getInstanceById($id) {
        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mrb' => self::TABLE_NAME),
                array('mrb.*')
            )
            ->where('mrb.' . self::COL_ID . ' = :id')
        ;

        $row = $db->fetchRow($select, array('id' => $id), Zend_Db::FETCH_ASSOC);
        if (!$row) {
            throw new Shipserv_Helper_Database_Exception('Requested search term brand cannot be found');
        }

        $instance = new self($row);

        return $instance;
    }

    /**
     * Retrieves all the matched brands for the given search
     *
     * @param   Shipserv_Match_Component_Search $search
     * @return  Shipserv_Match_Component_Term_Brand[]
     */
    public static function retrieveAllForSearch(Shipserv_Match_Component_Search $search) {
        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mrb' => self::TABLE_NAME),
                'mrb.*'
            )
            ->where('mrb.' . self::COL_SEARCH_ID . ' = :searchid')
            ->order('mrb.' . self::COL_SCORE . ' DESC')
        ;

        $rows = $select->getAdapter()->fetchAssoc($select, array('searchid' => $search->getId()));
        if (empty($rows)) {
            return array();
        }

        $instances = array();
        foreach ($rows as $row) {
            $instances[] = new self($row);
        }

        return $instances;
    }

    /**
     * Saves or updates the search data into the database
     */
    public function save() {
        parent::save();

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
     * Converter from the legacy brand information structure returned by the Match engine
     *
     * @todo: should be removed later as the match engine should return results as Component_Results straight away
     *
     * @param   Shipserv_Match_Component_Search $search
     * @param   array                           $brandInfo
     *
     * @return  Shipserv_Match_Component_Term_Brand
     */
    public static function fromMatchEngine(Shipserv_Match_Component_Search $search, array $brandInfo) {
        $instance = new self();

        $instance->fromDbRow(array(
            self::COL_ID            => null,
            self::COL_SEARCH_ID     => $search->getId(),
            self::COL_BRAND_ID      => $brandInfo[Shipserv_Match_Match::TERM_ID],
            self::COL_SCORE         => $brandInfo[Shipserv_Match_Match::TERM_SCORE]
        ));

        return $instance;
    }

    /**
     * Converter function from the initialised object into legacy search term brand structure accepted by the match engine
     *
     * @todo: should be removed later as the match engine should accepts terms as Component_Term straight away
     *
     * @return array
     */
    public function toMatchEngine() {
        $brand = $this->getBrand();
        $brandInfo = Shipserv_Match_TagGenerator::brandToTag($brand);

        $brandInfo[Shipserv_Match_Match::TERM_SCORE] = (float) $this->getScore();

        return $brandInfo;
    }
}