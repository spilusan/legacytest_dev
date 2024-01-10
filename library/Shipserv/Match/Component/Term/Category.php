<?php
/**
 * Match engine search term - category
 *
 * A category to be searched for, either extracted from the RFQ or added manually by user
 *
 * Retains a link to the original category entity that is searched for
 *
 * @author  Yuriy Akopov
 * @date    2013-08-19
 * @story   S7924
 */
class Shipserv_Match_Component_Term_Category extends Shipserv_Match_Component_Term {
    const
        TABLE_NAME      = 'MATCH_RFQ_SEARCH_CATEGORY',
        SEQUENCE_NAME   = 'SQ_MATCH_RFQ_SEARCH_CTGRY_ID',
        // column names
        COL_ID              = 'MRC_ID',
        COL_SEARCH_ID       = 'MRC_MRS_ID',
        COL_CATEGORY_ID     = 'MRC_PC_ID',
        COL_SCORE           = 'MRC_SCORE'
    ;

    /**
     * @var string
     */
    protected $categoryId = null;

    /**
     * @var Shipserv_Category
     */
    protected $category = null;

    /**
     * @return null|string
     */
    public function getCategoryId() {
        return $this->categoryId;
    }

    /**
     * Returns the category entity associated with the search term
     *
     * @return  Shipserv_Category
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function getCategory() {
        if (is_null($this->categoryId)) {
            throw new Shipserv_Helper_Database_Exception('No category connected to this search term (' . __CLASS__ . ', ' . $this->getId() . ')');
        }

        if (!is_null($this->category)) {
            return $this->category;
        }

        $this->category = Shipserv_Category::getInstanceById($this->categoryId);

        return $this->category;
    }

    /**
     * Maps supplied database row onto object fields
     *
     * @param   array   $fields
     */
    public function fromDbRow(array $fields) {
        // updating database fields
        $this->id           = $fields[self::COL_ID];
        $this->searchId     = $fields[self::COL_SEARCH_ID];
        $this->categoryId   = $fields[self::COL_CATEGORY_ID];
        $this->score        = $fields[self::COL_SCORE];

        $this->search   = null;
        $this->category = null;
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
            self::COL_CATEGORY_ID   => $this->getCategoryId(),
            self::COL_SCORE         => self::roundFloatDbValue($this->getScore(), self::TABLE_NAME, self::COL_SCORE)
        );

        return $fields;
    }

    /**
     * Retrieves the stored item by its primary key
     *
     * @param   int $id
     *
     * @return  Shipserv_Match_Component_Term_Category
     * @throws  Shipserv_Helper_Database_Exception
     */
    public static function getInstanceById($id) {
        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mrc' => self::TABLE_NAME),
                array('mrc.*')
            )
            ->where('mrc.' . self::COL_ID . ' = :id')
        ;

        $row = $db->fetchRow($select, array('id' => $id), Zend_Db::FETCH_ASSOC);
        if (!$row) {
            throw new Shipserv_Helper_Database_Exception('Requested search term category cannot be found');
        }

        $instance = new self($row);

        return $instance;
    }

    /**
     * Retrieves all the matched categories for the given search
     *
     * @param   Shipserv_Match_Component_Search $search
     * @return  Shipserv_Match_Component_Term_Category[]
     */
    public static function retrieveAllForSearch(Shipserv_Match_Component_Search $search) {
        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mrc' => self::TABLE_NAME),
                'mrc.*'
            )
            ->where('mrc.' . self::COL_SEARCH_ID . ' = :searchid')
            ->order('mrc.' . self::COL_SCORE . ' DESC')
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
     * Converter from the legacy category information structure returned by the Match engine
     *
     * @todo: should be removed later as the match engine should return results as Component_Results straight away
     *
     * @param   Shipserv_Match_Component_Search $search
     * @param   array                           $categoryInfo
     *
     * @return  Shipserv_Match_Component_Term_Category
     */
    public static function fromMatchEngine(Shipserv_Match_Component_Search $search, array $categoryInfo) {
        $instance = new self();

        $instance->fromDbRow(array(
            self::COL_ID            => null,
            self::COL_SEARCH_ID     => $search->getId(),
            self::COL_CATEGORY_ID   => $categoryInfo[Shipserv_Match_Match::TERM_ID],
            self::COL_SCORE         => $categoryInfo[Shipserv_Match_Match::TERM_SCORE]
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
        $category = $this->getCategory();
        $categoryInfo = Shipserv_Match_TagGenerator::categoryToTag($category);

        $categoryInfo[Shipserv_Match_Match::TERM_SCORE]  = (float) $this->getScore();

        return $categoryInfo;
    }
}