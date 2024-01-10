<?php
/**
 * Match engine search term - brand
 *
 * A location to filter suppliers search by using other terms
 *
 * @author  Yuriy Akopov
 * @date    2013-11-22
 * @story   S8766
 */
class Shipserv_Match_Component_Term_Location extends Shipserv_Match_Component_Term {
    const
        TABLE_NAME      = 'MATCH_RFQ_SEARCH_LOCATION',
        SEQUENCE_NAME   = 'SQ_MATCH_RFQ_SEARCH_LOCTN_ID',
        // column names
        COL_ID          = 'MRL_ID',
        COL_SEARCH_ID   = 'MRL_MRS_ID',
        COL_COUNTRY_ID  = 'MRL_CNT_COUNTRY_CODE',
        COL_SCORE       = 'MRL_SCORE'
    ;

    /**
     * @var int
     */
    protected $countryId = null;

    /**
     * @var array
     */
    protected $location = null;

    /**
     * @return int|null
     */
    public function getCountryId() {
        return $this->countryId;
    }

    /**
     * Returns the location data associated with the search term
     *
     * @return  array
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function getLocation() {
        if (is_null($this->countryId)) {
            throw new Shipserv_Helper_Database_Exception('No location connected to this search term (' . __CLASS__ . ', ' . $this->getId() . ')');
        }

        if (!is_null($this->location)) {
            return $this->location;
        }

        $countries = new Shipserv_Oracle_Countries();
        $this->location = $countries->getCountryRow($this->getCountryId());

        return $this->location;
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
        $this->countryId    = $fields[self::COL_COUNTRY_ID];
        $this->score        = $fields[self::COL_SCORE];

        $this->search = null;
        $this->location = null;
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
            self::COL_COUNTRY_ID    => $this->getCountryId(),
            self::COL_SCORE         => $this->getScore()
        );

        return $fields;
    }

    /**
     * Retrieves the stored item by its primary key
     *
     * @param   int $id
     *
     * @return  Shipserv_Match_Component_Term_Location
     * @throws  Shipserv_Helper_Database_Exception
     */
    public static function getInstanceById($id) {
        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mrl' => self::TABLE_NAME),
                array('mrl.*')
            )
            ->where('mrl.' . self::COL_ID . ' = :id')
        ;

        $row = $db->fetchRow($select, array('id' => $id), Zend_Db::FETCH_ASSOC);
        if (!$row) {
            throw new Shipserv_Helper_Database_Exception('Requested search term location cannot be found');
        }

        $instance = new self($row);

        return $instance;
    }

    /**
     * Retrieves all the filter location for the given search
     *
     * @param   Shipserv_Match_Component_Search $search
     *
     * @return  Shipserv_Match_Component_Term_Location[]
     */
    public static function retrieveAllForSearch(Shipserv_Match_Component_Search $search) {
        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mrl' => self::TABLE_NAME),
                'mrl.*'
            )
            ->where('mrl.' . self::COL_SEARCH_ID . ' = :searchid')
            ->order('mrl.' . self::COL_SCORE . ' DESC')
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
     * Converter from the location information structure returned by the Match engine
     *
     * @todo: should be removed later as the match engine should return results as Component_Results straight away
     *
     * @param   Shipserv_Match_Component_Search $search
     * @param   array                           $locationInfo
     *
     * @return  Shipserv_Match_Component_Term_Brand
     */
    public static function fromMatchEngine(Shipserv_Match_Component_Search $search, array $locationInfo) {
        $instance = new self();

        $instance->fromDbRow(array(
            self::COL_ID            => null,
            self::COL_SEARCH_ID     => $search->getId(),
            self::COL_COUNTRY_ID    => $locationInfo[Shipserv_Match_Match::TERM_ID],
            self::COL_SCORE         => $locationInfo[Shipserv_Match_Match::TERM_SCORE]
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
        $location = $this->getLocation();
        $locationInfo = Shipserv_Match_TagGenerator::locationToTag($location);

        $locationInfo[Shipserv_Match_Match::TERM_SCORE] = (float) $this->getScore();

        return $locationInfo;
    }
}