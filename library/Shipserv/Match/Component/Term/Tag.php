<?php
/**
 * Match engine search term - tag
 *
 * A tag to be searched for, either extracted from the RFQ or added manually by user
 *
 * Unlike categories and (in future) brands, tags have no connection to existing entities, they're just strings with their scores
 *
 * @author  Yuriy Akopov
 * @date    2013-08-19
 * @story   S7924
 */
class Shipserv_Match_Component_Term_Tag extends Shipserv_Match_Component_Term {
    const
        TABLE_NAME      = 'MATCH_RFQ_SEARCH_TAG',
        SEQUENCE_NAME   = 'SQ_MATCH_RFQ_SEARCH_TAG_ID',
        // column names
        COL_ID          = 'MRT_ID',
        COL_SEARCH_ID   = 'MRT_MRS_ID',
        COL_TAG         = 'MRT_TAG',
        COL_SCORE       = 'MRT_SCORE'
    ;

    /**
     * @var string
     */
    protected $tag = null;

    /**
     * @return null|string
     */
    public function getTag() {
        return $this->tag;
    }

    /**
     * @var int
     */
    protected static $tagLength = null;

    /**
     * @var int
     */
    protected static $scorePrecision = null;


    /**
     * Returns the length of the comment field in the database
     *
     * @return int
     */
    protected function getTagFieldLength() {
        if (!is_null(self::$tagLength)) {
            return self::$tagLength;
        }

        self::$tagLength = $this->getFieldStringLength(self::TABLE_NAME, self::COL_TAG);

        return self::$tagLength;
    }

    /**
     * Returns the length of the comment field in the database
     *
     * @return int
     */
    protected function getScorePrecision() {
        if (!is_null(self::$scorePrecision)) {
            return self::$scorePrecision;
        }

        self::$scorePrecision = $this->getFieldDecimalPrecision(self::TABLE_NAME, self::COL_SCORE);

        return self::$scorePrecision;
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
        $this->tag      = $fields[self::COL_TAG];
        $this->score    = $fields[self::COL_SCORE];

        $this->search = null;
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
            self::COL_TAG       => self::truncateStringDbValue($this->getTag(), self::TABLE_NAME, self::COL_TAG),
            self::COL_SCORE     => self::roundFloatDbValue($this->getScore(), self::TABLE_NAME, self::COL_SCORE)
        );

        return $fields;
    }

    /**
     * Retrieves the stored item by its primary key
     *
     * @param   int $id
     *
     * @return  Shipserv_Match_Component_Term_Tag
     * @throws  Shipserv_Helper_Database_Exception
     */
    public static function getInstanceById($id) {
        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mrt' => self::TABLE_NAME),
                array('mrt.*')
            )
            ->where('mrt.' . self::COL_ID . ' = :id')
        ;

        $row = $db->fetchRow($select, array('id' => $id), Zend_Db::FETCH_ASSOC);
        if (!$row) {
            throw new Shipserv_Helper_Database_Exception('Requested search term tag cannot be found');
        }

        $instance = new self($row);

        return $instance;
    }

    /**
     * Retrieves all the tag terms for the given search
     *
     * @param   Shipserv_Match_Component_Search $search
     * @return  Shipserv_Match_Component_Term_Tag[]
     */
    public static function retrieveAllForSearch(Shipserv_Match_Component_Search $search) {
        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mrt' => self::TABLE_NAME),
                'mrt.*'
            )
            ->where('mrt.' . self::COL_SEARCH_ID . ' = :searchid')
            ->order('mrt.' . self::COL_SCORE . ' DESC')
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
        $dbRow = $this->toDbRow();

        if (strlen($this->id)) {    // we expect is_null to work here, but strlen is safe in case it's '' for some reason
            // database ID assigned, attempting to update the existing record
            $db->update(self::TABLE_NAME, $dbRow, $db->quoteInto(self::COL_ID . ' = ?', $this->id));
        } else {
            // no database ID, inserting a new record
            $db->insert(self::TABLE_NAME, $dbRow);
            $this->id = $db->lastSequenceId(self::SEQUENCE_NAME);
        }
    }

    /**
     * Converter from the legacy tag information structure returned by the Match engine
     *
     * @todo: should be removed later as the match engine should return results as Component_Results straight away
     *
     * @param   Shipserv_Match_Component_Search $search
     * @param   array                           $tagInfo
     *
     * @return  Shipserv_Match_Component_Term_Tag
     */
    public static function fromMatchEngine(Shipserv_Match_Component_Search $search, array $tagInfo) {
        $instance = new self();

        $instance->fromDbRow(array(
            self::COL_ID            => null,
            self::COL_SEARCH_ID     => $search->getId(),
            self::COL_TAG           => $tagInfo[Shipserv_Match_Match::TERM_TAG],
            self::COL_SCORE         => $tagInfo[Shipserv_Match_Match::TERM_SCORE]
        ));

        return $instance;
    }

    /**
     * Converter function from the initialised object into legacy search term tag structure accepted by the match engine
     *
     * @todo: should be removed later as the match engine should accepts terms as Component_Term straight away
     *
     * @return array
     */
    public function toMatchEngine() {
        $tagInfo = array(
            Shipserv_Match_Match::TERM_TAG   => (string) $this->getTag(),
            Shipserv_Match_Match::TERM_SCORE => (float) $this->getScore()
        );

        return $tagInfo;
    }
}