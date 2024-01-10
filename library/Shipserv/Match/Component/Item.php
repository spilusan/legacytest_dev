<?php
/**
 * A parent class for all the entities related to stored searches represented by "entry point" class Shipserv_Match_Component_Search
 *
 * @author  Yuriy Akopov
 * @date    2013-08-13
 * @story   S7924
 */
abstract class Shipserv_Match_Component_Item extends Shipserv_Object implements Shipserv_Helper_Database_Object {
    /**
     * Constructor is hidden from public - use factory static methods
     * as the search items are supposed to be accessed via the 'parent' search, not directly
     */
    protected function __construct(array $fields = array()) {
        $this->fromDbRow($fields);
    }

    /**
     * @var int
     */
    protected $id = null;

    /**
     * @var float
     */
    protected $score = null;

    /**
     * @var int
     */
    protected $searchId = null;

    /**
     * @return int|null
     */
    public function getId() {
        return (int) $this->id;
    }

    /**
     * @return float|null
     */
    public function getScore() {
        return (float) $this->score;
    }

    /**
     * @return int|null
     */
    public function getSearchId() {
        return (int) $this->searchId;
    }

    /**
     * @var Shipserv_Match_Component_Search
     */
    protected $search = null;

    /**
     * Returns the search this results was received for
     *
     * @return  Shipserv_Match_Component_Search
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function getSearch() {
        if (is_null($this->searchId)) {
            throw new Shipserv_Helper_Database_Exception('No search defined for this search item');
        }

        if (!is_null($this->search)) {
            return $this->search;
        }

        $this->search = Shipserv_Match_Component_Search::getInstanceById($this->searchId);

        return $this->search;
    }

    /**
     * Performs the check for the mandatory search id foreign key field value
     *
     * WARNING: this method does not save the data as our table names are constants and we're on PHP 5.2 yet without
     * the late binding feature
     *
     * @throws Shipserv_Helper_Database_Exception
     */
    public function save() {
        if (is_null($this->searchId)) {
            throw new Shipserv_Helper_Database_Exception('A search meta data item cannot be saved without being connected to a search');
        }
    }

    /**
     * Converter function to map associative arrays available from the match engine into the object fields
     *
     * @todo: should be replaced when the match engine is rewritten to produce the corresponding objects instead of raw arrays
     *
     * @param   Shipserv_Match_Component_Search $search
     * @param   mixed                           $data
     *
     * @return  Shipserv_Match_Component_Item
     */
    // abstract public static function fromMatchEngine(Shipserv_Match_Component_Search $search, $data);

    /**
     * Converter function to map initialised object loaded from DB into the associative array operated by the match engine
     *
     * @todo: should be replaced when the match engine is rewritten to accept the corresponding objects instead of raw arrays
     *
     * @return  array
     */
    abstract public function toMatchEngine();
}