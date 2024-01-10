<?php
/**
 * Represents a search performed by the match engine - its results (matched suppliers) and terms (extracted from RFQ or
 * modified by user)
 *
 * @author  Yuriy Akopov
 * @date    2013-08-13
 * @story   S7924
 */
class Shipserv_Match_Component_Search extends Shipserv_Object implements Shipserv_Helper_Database_Object {
    const
        TABLE_NAME      = 'MATCH_RFQ_SEARCH',
        SEQUENCE_NAME   = 'SQ_MATCH_RFQ_SEARCH_ID',
        // column names
        COL_ID              = 'MRS_ID',
        COL_RFQ_ID          = 'MRS_RFQ_INTERNAL_REF_NO',
        COL_DATE            = 'MRS_DATE',
        COL_MODIFIED        = 'MRS_TERMS_MODIFIED',
        COL_USER_ID         = 'MRS_PSU_ID',
        COL_MATCH_VERSION   = 'MRS_MATCH_VERSION',
        COL_SENDER_TYPE     = 'MRS_SENDER_TYPE',
        COL_SENDER_ID       = 'MRS_SENDER_ID'
    ;

    // database fields

    /**
     * Column field
     *
     * @var int
     */
    protected $id = null;

    /**
     * Column field
     *
     * @var int
     */
    protected $rfqId = null;

    /**
     * Column field
     *
     * @var string
     */
    protected $date = null;

    /**
     * Column field
     *
     * @var bool
     */
    protected $modified = null;

    /**
     * Column field
     *
     * @var string
     */
    protected $type = null;

    /**
     * Column field
     *
     * @var int
     */
    protected $userId = null;

    /**
     * Column field
     *
     * @var string
     */
    protected $matchVersion = null;

    /**
     * Column field
     *
     * @var string
     */
    protected $senderType = null;

    /**
     * Column field
     *
     * @var int
     */
    protected $senderId = null;

    // loaded connected data fields

    /**
     * @var Shipserv_Rfq
     */
    protected $rfq = null;

    /**
     * @var Shipserv_User
     */
    protected $user = null;

    /**
     * @var array
     */
    protected $originalSupplierIds = null;

    /**
     * All result items (as opposed to paginated items which aren't cached in the member variable)
     *
     * @var array
     */
    protected $results = array(
        Shipserv_Match_Component_Result::FEED_TYPE_MATCHES  => null,
        Shipserv_Match_Component_Result::FEED_TYPE_AT_RISK  => null,
        Shipserv_Match_Component_Result::FEED_TYPE_AUTO_KEYWORDS => null,
        Shipserv_Match_Component_Result::FEED_TYPE_AUTO_DYNAMIC  => null
    );

    /**
     * All brand search terms
     *
     * @var Shipserv_Match_Component_Term_Brand
     */
    protected $brands = null;

    /**
     * All category search terms
     *
     * @var Shipserv_Match_Component_Term_Category
     */
    protected $categories = null;

    /**
     * All tag search terms
     *
     * @var Shipserv_Match_Component_Term_Tag
     */
    protected $tags = null;

    /**
     * All location search terms
     *
     * @var null
     */
    protected $locations = null;

    public function getId() {
        return $this->id;
    }

    public function getRfqId() {
        return $this->rfqId;
    }

    /**
     * @param   bool    $asDateTime
     *
     * @return  DateTime|string
     * @throws Shipserv_Helper_Database_Exception
     */
    public function getDate($asDateTime = false) {
        if ($asDateTime) {
            if (is_null($this->date)) {
                throw new Shipserv_Helper_Database_Exception('Date not initialised');
            }

            try {
                return new DateTime($this->date);
            } catch (Exception $e) {
                throw new Shipserv_Helper_Database_Exception($e->getMessage());
            }
        }

        return $this->date;
    }

    /**
     * @return bool|null
     */
    public function isModified() {
        if (is_null($this->modified)) {
            return null;    // so we have a difference between uninitialised and false
        }

        return (bool) $this->modified;
    }

    /**
     * @return null|string
     */
    public function getType() {
        return $this->type;
    }

    /**
     * @return int|null
     */
    public function getUserId() {
        return $this->userId;
    }

    /**
     * @return null|string
     */
    public function getMatchEngineVersion() {
        return $this->matchVersion;
    }

    /**
     * @return int|null
     */
    public function getBuyerId() {
        return $this->buyerId;
    }

    /**
     * Returns the RFQ related to the search
     *
     * @return Shipserv_Rfq
     * @throws Shipserv_Helper_Database_Exception
     */
    public function getRfq() {
        if (is_null($this->rfqId)) {
            throw new Shipserv_Helper_Database_Exception('No RFQ specified for the search');
        }

        if (!is_null($this->rfq)) {
            return $this->rfq;
        }

        $this->rfq = Shipserv_Rfq::getInstanceById($this->rfqId);
        return $this->rfq;
    }

    /**
     * @return Shipserv_User|null
     */
    /*
    public function getUser() {
        if (is_null($this->userId)) {
            return null;    // don't throw an exception as that field is legitimately nullable
        }

        if (!is_null($this->user)) {
            return $this->user;
        }

        $this->user = Shipserv_User::getInstanceById($this->userId);

        return $this->user;
    }
    */

    /**
     * Returns brand search terms
     *
     * @return  Shipserv_Match_Component_Term_Brand[]
     */
    public function getBrands() {
        if (!is_null($this->brands)) {
            return $this->brands;
        }

        $this->brands = Shipserv_Match_Component_Term_Brand::retrieveAllForSearch($this);

        return $this->brands;
    }

    /**
     * Returns category search terms
     *
     * @return  Shipserv_Match_Component_Term_Category[]
     */
    public function getCategories() {
        if (!is_null($this->categories)) {
            return $this->categories;
        }

        $this->categories = Shipserv_Match_Component_Term_Category::retrieveAllForSearch($this);

        return $this->categories;
    }

    /**
     * Returns brand search terms
     *
     * @return  Shipserv_Match_Component_Term_Tag[]
     */
    public function getTags() {
        if (!is_null($this->tags)) {
            return $this->tags;
        }

        $this->tags = Shipserv_Match_Component_Term_Tag::retrieveAllForSearch($this);

        return $this->tags;
    }

    /**
     * Returns location search terms
     *
     * @return Shipserv_Match_Component_Term_Location[]
     */
    public function getLocations() {
        if (!is_null($this->locations)) {
            return $this->locations;
        }

        $this->locations = Shipserv_Match_Component_Term_Location::retrieveAllForSearch($this);

        return $this->locations;
    }

    /**
     * Constructor unavailable to public - use static factory methods
     *
     */
    protected function __construct(array $fields = array()) {
        $this->fromDbRow($fields);
    }

    /**
     * Maps supplied database row onto object fields
     *
     * @param   array   $fields
     */
    public function fromDbRow(array $fields) {
        // updating database fields
        $this->id       = $fields[self::COL_ID];

        $date = new DateTime($fields[self::COL_DATE]);
        $this->date     = $date->format('Y-m-d H:i:s');

        $this->rfqId        = $fields[self::COL_RFQ_ID];
        $this->modified     = $fields[self::COL_MODIFIED];
        $this->userId       = $fields[self::COL_USER_ID];
        $this->matchVersion = $fields[self::COL_MATCH_VERSION];
        $this->senderTyoe   = $fields[self::COL_SENDER_TYPE];
        $this->senderId     = $fields[self::COL_SENDER_ID];

        // resetting connected data so it would be reloaded next time it's referenced
        $this->rfq      = null;
        $this->originalSupplierIds = null;

        $this->user     = null;

        $this->results      = null;
        $this->brands       = null;
        $this->categories   = null;
        $this->tags         = null;
        $this->locations    = null;
    }

    /**
     * Returns current field values mapped to database columns
     *
     * @return array
     */
    public function toDbRow() {
        $rfq = $this->getRfq();
        $senderSignature = $rfq->getOriginalSenderSignature();

        $fields = array(
            self::COL_ID            => $this->getId(),
            self::COL_DATE          => new Zend_Db_Expr(Shipserv_Helper_Database::getOracleDateExpr($this->getDate(true))),
            self::COL_RFQ_ID        => $this->getRfqId(),
            self::COL_MODIFIED      => $this->isModified() ? 1 : 0,
            self::COL_USER_ID       => $this->getUserId(),
            self::COL_MATCH_VERSION => $this->getMatchEngineVersion(),
            self::COL_SENDER_TYPE   => $senderSignature[0],
            self::COL_SENDER_ID     => $senderSignature[1]
        );

        return $fields;
    }

    /**
     * Retrieves the stored search by specified id
     *
     * WARNING: unlike other loaders like getForRfq this factory method doesn't constrain by user, so it is possible to load other user's RFQ here
     *
     * @param   int     $id
     * @param   int     $rfqId
     *
     * @return  Shipserv_Match_Component_Search
     * @throws  Exception
     */
    public static function getInstanceById($id, $rfqId = null) {
        $db = self::getDb();

        $params = array(
            'id'    => $id
        );

        $select = new Zend_Db_Select($db);
        $select->from(
            array('t' => self::TABLE_NAME),
            array('t.*')
        )
            ->where('t.' . self::COL_ID . ' = :id')
        ;

        if (!is_null($rfqId)) {
            $select->where('t.' . self::COL_RFQ_ID . ' = :rfq');
            $params['rfq'] = $rfqId;
        }

        $row = $db->fetchRow($select, $params, Zend_Db::FETCH_ASSOC);
        if (!$row) {
            throw new Shipserv_Helper_Database_Exception('Requested search cannot be found');
        }

        $instance = new self($row);

        return $instance;
    }

    /**
     * Helper function to return ID of the current user or null if no user is logged in
     *
     * @return int|null
     */
    protected static function getCurrentUserId() {
        $user = Shipserv_User::isLoggedIn();    /* @var Shipserv_User $user */
        if ($user === false) {
            return null;
        }

        return $user->getUserCode();
    }

    /**
     * Loads the most recent search saved for the given RFQ
     *
     * @param   Shipserv_Rfq|int    $rfq
     * @param   bool|null           $modified
     * @param   bool                $searchEvent
     * @param   bool                $hasFeed
     *
     * @return  Shipserv_Match_Component_Search
     * @throws  Shipserv_Helper_Database_Exception
     */
    public static function getForRfq($rfq, $modified = null, $searchEvent = false, $hasFeed = null) {
        if ($rfq instanceof Shipserv_Rfq) {
            $rfqId = $rfq->rfqInternalRefNo;
        } else {
            $rfqId = $rfq;
        }

        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mrs' => self::TABLE_NAME),
                'mrs.*'
            )
            ->order('mrs.' . self::COL_DATE . ' DESC')
            ->order('mrs.' . self::COL_ID . ' DESC')
        ;

        if ($searchEvent) {
            $rfq = Shipserv_Rfq::getInstanceById($rfqId);

            $selectEventRfqs = new Zend_Db_Select($select->getAdapter());
            $selectEventRfqs
                ->from(
                    array('rfq' => Shipserv_Rfq::TABLE_NAME),
                    'rfq.' . Shipserv_Rfq::COL_ID
                )
                ->where('rfq.' . Shipserv_Rfq::COL_EVENT_HASH . ' = HEXTORAW(?)', $rfq->rfqEventHash)
            ;

            $select->where('mrs.' . self::COL_RFQ_ID . ' IN (' . $selectEventRfqs->assemble() . ')');
        } else {
            $select->where('mrs.' . self::COL_RFQ_ID . ' = ?', $rfqId);
        }

        if (!is_null($hasFeed)) {
            $select
                ->join(
                    array('mrr' => Shipserv_Match_Component_Result::TABLE_NAME),
                    implode(' AND ', array(
                        $db->quoteInto('mrr.' . Shipserv_Match_Component_Result::COL_FEED_TYPE . ' IN (?)', $hasFeed),
                        'mrr.' . Shipserv_Match_Component_Result::COL_SEARCH_ID . ' = mrs.' . self::COL_ID
                    )),
                    array()
                )
            ;
        }

        // print $select->assemble(); exit;

        // removed on 2014-07-18 as we decided to show all the searches
        // $select = self::constrainByUser($select);

        if (!is_null($modified)) {
            $select->where('mrs.' . self::COL_MODIFIED . ' = ' . ($modified ? '1' : '0'));
        }

        $row = $db->fetchRow($select);
        if (empty($row)) {
            throw new Shipserv_Helper_Database_Exception('Requested search cannot be found');
        }

        $instance = new self($row);

        return $instance;

    }

    /**
     * Loads existing stored search from the database by the RFQ
     *
     * @param   Shipserv_Rfq|int    $rfq
     *
     * @return  Shipserv_Match_Component_Search[]
     */
    public static function getAllForRfq($rfq) {
        if ($rfq instanceof Shipserv_Rfq) {
            $rfqId = $rfq->rfqInternalRefNo;
        } else {
            $rfqId = $rfq;
        }

        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mrs' => self::TABLE_NAME),
                'mrs.*'
            )
            ->where('mrs.' . self::COL_RFQ_ID . ' = ?', $rfqId)
            ->order('mrs.' . self::COL_DATE . ' DESC')
            ->order('mrs.' . self::COL_ID . ' DESC')
        ;

        // removed on 2014-07-18 as we decided to show all the searches
        // $select = self::constrainByUser($select);

        $rows = $db->fetchAll($select);

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
     * Adds user constraints to the given search select allowing users to see only theirs or defaul terms based RFQs
     *
     * @param   Zend_Db_Select  $select
     *
     * @return  Zend_Db_Select
     */
    protected static function constrainByUser(Zend_Db_Select $select) {
        $userConstraints = array(
            // include all searches from system user regardless of their modified status
            'mrs.' . self::COL_USER_ID . ' IS NULL',
            // included non-modified searches from all users
            '(mrs.' . self::COL_USER_ID . ' IS NOT NULL AND mrs.' . self::COL_MODIFIED . ' = 0)'
        );

        $userId = self::getCurrentUserId();
        if (!is_null($userId)) {
            // when user is specified, also include all searches by that user
            $userConstraints[] = $select->getAdapter()->quoteInto('mrs.' . self::COL_USER_ID . ' = ?', $userId);
        }

        $select->where(implode(' OR ', $userConstraints));

        return $select;
    }

    /**
     * Saves or updates the search data into the database
     *
     * @throws Shipserv_Helper_Database_Exception
     */
    public function save() {
        if (is_null($this->rfqId)) {
            throw new Shipserv_Helper_Database_Exception('A search without an RFQ assigned cannot be saved');
        }

        $db = $this->getDb();
        $dbRow = $this->toDbRow();

        if (strlen($this->id)) {
            // database ID assigned, attempting to update the existing record
            $db->update(self::TABLE_NAME, $dbRow, $db->quoteInto(self::COL_ID . ' = ?', $this->id));
        } else {
            // no database ID, inserting a new record
            $db->insert(self::TABLE_NAME, $dbRow);
            $this->id = $db->lastSequenceId(self::SEQUENCE_NAME);
        }
    }

    /**
     * Returns all the matches in one chunk (no pagination)
     *
     * @param   string  $feedType
     * @param   bool    $nonRfqSuppliersOnly
     *
     * @return  Shipserv_Match_Component_Result[]
     */
    public function getAllResults($feedType = Shipserv_Match_Component_Result::FEED_TYPE_MATCHES, $nonRfqSuppliersOnly = true) {
        if (!is_null($this->results[$feedType])) {
            return $this->results[$feedType];
        }

        $this->results[$feedType] = Shipserv_Match_Component_Result::getAllForSearch($this, $feedType, $nonRfqSuppliersOnly);

        return $this->results[$feedType];
    }

    /**
     * Returns the paginated search results
     *
     * @param   string  $feedType
     * @param   bool    $nonRfqSuppliersOnly
     * @param   int     $pageNo
     * @param   int     $pageLen
     *
     * @return Shipserv_Match_Component_Result[]
     */
    public function getResultsPage($feedType  = Shipserv_Match_Component_Result::FEED_TYPE_MATCHES, $nonRfqSuppliersOnly = true, $pageNo = 1, $pageLen = 10) {
        $paginator = Shipserv_Match_Component_Result::getSearchPaginator($this, $feedType, $nonRfqSuppliersOnly);

        $paginator->setItemCountPerPage($pageLen);
        $paginator->setCurrentPageNumber($pageNo);

        $results = Shipserv_Match_Component_Result::getFromPaginator($paginator);
        return $results;
    }

    /**
     * Returns the number of results
     *
     * @param   string  $feedType
     * @param   bool    $nonRfqSuppliersOnly
     *
     * @return int
     */
    public function getResultCount($feedType  = Shipserv_Match_Component_Result::FEED_TYPE_MATCHES, $nonRfqSuppliersOnly = true) {
        $paginator = Shipserv_Match_Component_Result::getSearchPaginator($this, $feedType, $nonRfqSuppliersOnly);

        $count = $paginator->getTotalItemCount();
        return $count;
    }

    /**
     * Initialised the object from the given search engine instance
     *
     * @param   Shipserv_Match_Match    $match
     * @param   bool                    $termsWereModified
     *
     * @return  Shipserv_Match_Component_Search
     * @throws  Shipserv_Helper_Database_Exception
     */
    public static function fromMatchEngine(Shipserv_Match_Match $match, $termsWereModified) {
        $db = self::getDb();
        $db->beginTransaction();

        $instance = new self();
        $instance->rfqId    = $match->getRfq()->getInternalRefNo();
        $instance->date     = date('Y-m-d H:i:s');
        $instance->modified = $termsWereModified;
        $instance->userId   = self::getCurrentUserId();
        $instance->matchVersion = $match->getVersion();

        $instance->save();

        try {
            $instance->saveTermsFromMatch($match);
            $instance->saveResultsFromMatch($match);
        } catch (Exception $e) {
            $db->rollBack();
            $instance->id = null;

            throw new Shipserv_Helper_Database_Exception('Failed to save the search (' . get_class($e) . ': ' . $e->getMessage() . ')');
        }

        $db->commit();

        return $instance;
    }

    /**
     * Stores the search terms from the given match engine
     *
     * WARNING: existing data is not removed, this function is supposed to be called for new searches only!
     *
     * @param Shipserv_Match_Match $match
     */
    protected function saveTermsFromMatch(Shipserv_Match_Match $match) {
        $this->saveBrandsFromMatch($match);
        $this->saveCategoriesFromMatch($match);
        $this->saveTagsFromMatch($match);
        $this->saveLocationsFromMatch($match);
    }

    /**
     * Stores the brands extracted for the search from RFQ or added by user
     *
     * @param Shipserv_Match_Match $match
     */
    protected function saveBrandsFromMatch(Shipserv_Match_Match $match) {
        $brands = $match->getCurrentBrands();
        if (!is_array($brands) or empty($brands)) {
            return;
        }

        foreach ($brands as $brandInfo) {
            $tag = Shipserv_Match_Component_Term_Brand::fromMatchEngine($this, $brandInfo);
            $tag->save();
        }
    }

    /**
     * Stores the categories extracted for the search from RFQ or added by user
     *
     * @param Shipserv_Match_Match $match
     */
    protected function saveCategoriesFromMatch(Shipserv_Match_Match $match) {
        $categories = $match->getCurrentCategories();
        if (!is_array($categories) or empty($categories)) {
            return;
        }

        foreach ($categories as $categoryInfo) {
            $tag = Shipserv_Match_Component_Term_Category::fromMatchEngine($this, $categoryInfo);
            $tag->save();
        }
    }

    /**
     * Stores the tags extracted for the search from RFQ or added by user
     *
     * @param Shipserv_Match_Match $match
     */
    protected function saveTagsFromMatch(Shipserv_Match_Match $match) {
        $tags = $match->getCurrentTags();
        if (!is_array($tags) or empty($tags)) {
            return;
        }

        foreach ($tags as $tagInfo) {
            $tag = Shipserv_Match_Component_Term_Tag::fromMatchEngine($this, $tagInfo);
            $tag->save();
        }
    }

    /**
     * Stores the location filters from the match engine
     *
     * @param Shipserv_Match_Match $match
     */
    protected function saveLocationsFromMatch(Shipserv_Match_Match $match) {
        $locations = $match->getCurrentLocations();
        if (!is_array($locations) or empty($locations)) {
            return;
        }

        foreach ($locations as $locationInfo) {
            $location = Shipserv_Match_Component_Term_Location::fromMatchEngine($this, $locationInfo);
            $location->save();
        }
    }

    /**
     * Stores the result of the given search
     *
     * @param   Shipserv_Match_Match            $match
     *
     * @throws  Shipserv_Helper_Database_Exception
     * @throws  Shipserv_Match_Exception
     */
    protected function saveResultsFromMatch(Shipserv_Match_Match $match) {
        // $feedTypes = $match->getRelevantFeedTypes();
        $feedTypes = array(
            Shipserv_Match_Component_Result::FEED_TYPE_AUTO,
            Shipserv_Match_Component_Result::FEED_TYPE_MATCHES,
            Shipserv_Match_Component_Result::FEED_TYPE_AT_RISK
        );

        foreach ($feedTypes as $feed) {
            switch($feed) {
                case Shipserv_Match_Component_Result::FEED_TYPE_AUTO:
                    $suppliers = $match->getAutoMatchedSuppliers();
                    break;

                case Shipserv_Match_Component_Result::FEED_TYPE_MATCHES:
                    $suppliers = $match->getMatchedSuppliers();
                    break;

                case Shipserv_Match_Component_Result::FEED_TYPE_AT_RISK:
                    $suppliers = $match->getMatchedSuppliersAtRisk();
                    break;

                default:
                    throw new Shipserv_Match_Exception("Unknown feed type supplied for saving: " . $feed);
            }

            if (empty($suppliers)) {
                continue;
            }

            foreach ($suppliers as $supplierInfo) {
                $result = Shipserv_Match_Component_Result::fromMatchEngine($this, $feed, $supplierInfo);
                $result->save();
            }
        }
    }

    /**
     * Returns a match engine initialised with the search terms stored for this saved search
     *
     * @return Shipserv_Match_Match $match
     */
    public function toMatchEngine() {
        $match = new Shipserv_Match_Match($this->getRfqId(), false);

        $this->brandsToMatchEngine($match);
        $this->categoriesToMatchEngine($match);
        $this->tagsToMatchEngine($match);
        $this->locationsToMatchEngine($match);

        return $match;
    }

    /**
     * Loads saved search brands into the given match engine
     *
     * @param Shipserv_Match_Match $match
     */
    protected function brandsToMatchEngine(Shipserv_Match_Match $match) {
        $brands = Shipserv_Match_Component_Term_Brand::retrieveAllForSearch($this);
        $matchBrands = array();

        if (count($brands)) {
            foreach ($brands as $termBrand) {
                $matchBrands[] = $termBrand->toMatchEngine();
            }
        }

        $match->setCustomBrands($matchBrands);
    }

    /**
     * Loads saved search categories into the given match engine
     *
     * @param Shipserv_Match_Match $match
     */
    protected function categoriesToMatchEngine(Shipserv_Match_Match $match) {
        $categories = Shipserv_Match_Component_Term_Category::retrieveAllForSearch($this);
        $matchCategories = array();

        if (count($categories)) {
            foreach ($categories as $termCategory) {
                $matchCategories[] = $termCategory->toMatchEngine();
            }
        }

        $match->setCustomCategories($matchCategories);
    }

    /**
     * Loads saved search tags into the given match engine
     *
     * @param Shipserv_Match_Match $match
     */
    protected function tagsToMatchEngine(Shipserv_Match_Match $match) {
        $tags = Shipserv_Match_Component_Term_Tag::retrieveAllForSearch($this);
        $matchTags = array();

        if (count($tags)) {
            foreach ($tags as $termTag) {
                $matchTags[] = $termTag->toMatchEngine();
            }
        }

        $match->setCustomTags($matchTags);
    }

    /**
     * Loads saved search locations into the given match engine
     *
     * @param Shipserv_Match_Match $match
     */
    protected function locationsToMatchEngine(Shipserv_Match_Match $match) {
        $locations = Shipserv_Match_Component_Term_Location::retrieveAllForSearch($this);
        $matchLocations = array();

        if (count($locations)) {
            foreach ($locations as $locationTag) {
                $matchLocations[] = $locationTag->toMatchEngine();
            }
        }

        $match->setLocations($matchLocations);
    }

    /**
     * Deletes the given search records and its connected records in other tables
     *
     * @param   $searchId
     * @return  int
     */
    public static function removeById($searchId) {
        $db = self::getDb();
        $affected = $db->delete(self::TABLE_NAME, $db->quoteInto(self::COL_ID . ' = ?', $searchId));

        return $affected;
    }

    /**
     * Deletes the given search and its connected entities from DB and unsets the object
     *
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function remove() {
        if (!strlen($this->getId())) {
            throw new Shipserv_Helper_Database_Exception('Search to be deleted is not yet saved or wasn\'t loaded from DB first');
        }

        self::removeById($this->getId());

        $this->id = null;

        $this->rfq      = null;
        $this->results  = null;
        $this->originalSupplierIds = null;

        $this->brands       = null;
        $this->categories   = null;
        $this->tags         = null;
        $this->locations    = null;
    }

    /**
     * Gives supplier ID regardless of the input form
     *
     * @param   Shipserv_Supplier|int|array $supplier
     *
     * @return  int
     * @throws Exception
     */
    protected function getSupplierId($supplier) {
        if ($supplier instanceof Shipserv_Supplier) {
            return $supplier->tnid;
        }

        if (filter_var($supplier, FILTER_VALIDATE_INT)) {
            return (int) $supplier;
        }

        if (is_array($supplier) and array_key_exists('SPB_BRANCH_CODE', $supplier)) {
            return (int) $supplier['SPB_BRANCH_CODE'];
        }

        throw new Exception('Supplier ID appears to be invalid');
    }

    /**
     * Returns number of the given result in the result list or null if it cannot be found
     *
     * @param   string                          $feedType
     * @param   Shipserv_Match_Component_Result $result
     * @param   bool                            $nonRfqSuppliersOnly
     *
     * @return int|null
     */
    public function getResultNo($feedType, Shipserv_Match_Component_Result $result, $nonRfqSuppliersOnly = false) {
        $results = $this->getAllResults($feedType, $nonRfqSuppliersOnly);
        if (empty($results)) {
            return null;
        }

        $resultNo = 1;
        foreach ($results as $listResult) {
            if ($listResult->getId() === $result->getId()) {
                return $resultNo;
            }

            $resultNo++;
        }

        return null;
    }

    /**
     * Returns result record for a given supplier, if any
     *
     * @param   string                  $feedType
     * @param   Shipserv_Supplier|int   $supplier
     *
     * @return  Shipserv_Match_Component_Result|null
     */
    public function getResultBySupplier($feedType, $supplier) {
        $supplierId = $this->getSupplierId($supplier);

        $results = $this->getAllResults($feedType, false);
        foreach ($results as $result) {
            if ($result->getSupplierId() == $supplierId) {
                return $result;
            }
        }

        return null;

        /*
        $supplierId = $this->getSupplierId($supplier);

        $db = self::getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mrr' => Shipserv_Match_Component_Result::TABLE_NAME),
                array('id' => Shipserv_Match_Component_Result::COL_ID)
            )
            ->where("mrr." . Shipserv_Match_Component_Result::COL_SEARCH_ID . " = :search")
            ->where("mrr." . Shipserv_Match_Component_Result::COL_SUPPLIER_ID . " = :supplier")
        ;

        $id = $db->fetchOne($select, array(
            'search'    => $this->getId(),
            'supplier'  => $supplierId
        ));

        if (strlen($id) === 0) {
            throw new Shipserv_Helper_Database_Exception("No supplier in search " . $this->getId() . "results");
        }

        return Shipserv_Match_Component_Result::getInstanceById($id);
        */
    }

    /**
     * Returns the position of the given supplier in the results list or null if not found there
     *
     * @param   string                  $feedType
     * @param   Shipserv_Supplier|int   $supplier
     *
     * @return  int|null
     */
    public function getSupplierPosition($feedType = Shipserv_Match_Component_Result::FEED_TYPE_MATCHES, $supplier) {
        $supplierId = $this->getSupplierId($supplier);

        $results = $this->getAllResults($feedType, false);
        foreach ($results as $index => $result) {
            if ($result->getSupplierId() == $supplierId) {
                return ($index + 1);
            }
        }

        return null;

        /*
        $db = self::getDb();

        $selectAll = new Zend_Db_Select($db);
        $selectAll
            ->from(
                array('mrr' => Shipserv_Match_Component_Result::TABLE_NAME),
                array(
                    'id'            => Shipserv_Match_Component_Result::COL_ID,
                    'supplier_id'   => Shipserv_Match_Component_Result::COL_SUPPLIER_ID,
                    'rank'          => new Zend_Db_Expr(
                        "ROW_NUMBER() OVER (ORDER BY " . Shipserv_Match_Component_Result::COL_SCORE .  " DESC, " . Shipserv_Match_Component_Result::COL_ID . ")"
                    )
                )
            )
            ->where("mrr." . Shipserv_Match_Component_Result::COL_SEARCH_ID . " = :search")
        ;

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('ranks' => $selectAll),
                array('ranks.rank')
            )
            ->where("ranks.supplier_id = :supplier")
        ;

        $rank = $db->fetchOne($select, array(
            'search'    => $this->getId(),
            'supplier'  => $supplierId
        ));

        if (strlen($rank) === 0) {
            return null;
        }

        return (int) $rank;
        */
    }

    /**
     * Adds a supplier to search results
     *
     * @param   string  $feedType
     * @param   array   $supplierInfo
     *
     * @return  Shipserv_Match_Component_Result
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function addToResults($feedType, array $supplierInfo) {
        $supplierId = $supplierInfo[Shipserv_Match_Match::RESULT_SUPPLIER_ID];
        if (!is_null($this->getSupplierPosition($feedType, $supplierId))) {
            throw new Shipserv_Helper_Database_Exception('Supplier ' . $supplierId . ' is already in the list ' . $this->getId());
        }

        $result = Shipserv_Match_Component_Result::fromMatchEngine($this, $feedType, $supplierInfo);
        $result->save();

        $this->results = array();
        return $result;
    }

    /**
     * Removes a supplier from the search results
     *
     * @param   string  $feedType
     * @param   int     $supplierId
     *
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function removeFromResultsBySupplierId($feedType, $supplierId) {
        $db = $this->getDb();

        $affected = $db->delete(
            Shipserv_Match_Component_Result::TABLE_NAME,
            implode(' AND ', array(
                $db->quoteInto(Shipserv_Match_Component_Result::COL_SEARCH_ID . ' = ?', $this->getId()),
                $db->quoteInto(Shipserv_Match_Component_Result::COL_SUPPLIER_ID . ' = ?', $supplierId),
                $db->quoteInto(Shipserv_Match_Component_Result::COL_FEED_TYPE . ' = ?', $feedType)
            ))
        );

        if ($affected === 0) {
            throw new Shipserv_Helper_Database_Exception('Supplier ' . $supplierId . ' was not found in the ' . $feedType . ' list ' . $this->getId());
        }

        $this->results = null;
    }

    /**
     * Returns IDs of suppliers RFQ the search was created for has been sent to
     *
     * @param   bool    $ignoreCache
     *
     * @return array
     */
    public function getOriginalSuppliers($ignoreCache = false) {
        if (!$ignoreCache and !is_null($this->originalSupplierIds)) {
            return $this->originalSupplierIds;
        }

        $rfq = $this->getRfq();
        $originalSuppliers = $rfq->getSuppliers();

        if (empty($originalSuppliers)) {
            return array();
        }

        $this->originalSupplierIds = array();
        foreach ($originalSuppliers as $supplierInfo) {
            $this->originalSupplierIds[] = $supplierInfo[Shipserv_Rfq::RFQ_SUPPLIERS_BRANCH_ID];
        }

        return $this->originalSupplierIds;
    }

    /**
     * Removes stored searches of the specified owner which are older than the given datetime
     *
     * @param   string          $ownerType
     * @param   int             $ownerId
     * @param   DateTime|null   $datetime
     *
     * @return  int
     */
    public static function eraseOlderSeachesOwner($ownerType, $ownerId, DateTime $datetime = null) {
        if (is_null($datetime)) {
            $datetime = new DateTime();
        }

        $db = Shipserv_Helper_Database::getDb();
        return $db->delete(self::TABLE_NAME, implode(' AND ', array(
            self::COL_DATE . ' <= ' . Shipserv_Helper_Database::getOracleDateExpr($datetime),
            $db->quoteInto(self::COL_SENDER_TYPE . ' = ?', $ownerType),
            $db->quoteInto(self::COL_SENDER_ID . ' = ?', $ownerId)
            // @todo: should only unmodified searches be removed?
        )));
    }

    /**
     * Erases older stored searches for RFQs from the given event
     *
     * @param Shipserv_Rfq $rfq
     * @param DateTime $datetime
     */
    public static function eraseOlderSearchesEvent(Shipserv_Rfq $rfq, DateTime $datetime = null) {
        if (is_null($datetime)) {
            $datetime = new DateTime();
        }

        $db = Shipserv_Helper_Database::getDb();
        $selectEvents = new Zend_Db_Select($db);
        $selectEvents
            ->from(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                'rfq.' . Shipserv_Rfq::COL_ID
            )
            ->where('rfq.' . Shipserv_Rfq::COL_EVENT_HASH . ' = HEXTORAW(?)', $rfq->rfqEventHash);
        ;

        return $db->delete(self::TABLE_NAME, implode(' AND ', array(
            self::COL_DATE . ' <= ' . Shipserv_Helper_Database::getOracleDateExpr($datetime),
            self::COL_RFQ_ID . ' IN (' . $selectEvents->assemble() . ')',
            // @todo: should only unmodified searches be removed?
        )));
    }
}