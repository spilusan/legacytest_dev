<?php
/**
 * A class managing buyer branch and / or organisation dependent match settings
 *
 * @author  Yuriy Akopov
 * @date    2014-07-02
 * @story   S10313
 */
class Shipserv_Match_Buyer_Settings {
    const
        TABLE_NAME = 'MATCH_BUYER_SETTINGS',
        SEQUENCE_NAME = 'SQ_MATCH_BUYER_SETTINGS_ID',

        COL_ID = 'MBS_ID',

        COL_ORG_ID    = 'MBS_BYO_ORG_CODE',
        COL_BRANCH_ID = 'MBS_BYB_BRANCH_CODE',

        COL_DATE = 'MBS_DATE',

        COL_AUTOMATCH       = 'MBS_AUTOMATCH',
        COL_AUTOMATCH_CHEAP = 'MBS_CHEAP_AUTOMATCH_QUOTES',
        COL_MAX_SUPPLIERS   = 'MBS_MAX_MATCH_SUPPLIERS',
        COL_HIDE_CONTACTS   = 'MBS_HIDE_CONTACT_DETAILS'
    ;

    /**
     * @var int|null
     */
    protected $buyerOrgId = null;

    /**
     * @var int|null
     */
    protected $buyerBranchId = null;

    /**
     * @return int
     */
    public function getBuyerOrgId() {
        return $this->buyerOrgId;
    }

    /**
     * @return int
     */
    public function getBuyerBranchId() {
        return $this->buyerBranchId;
    }

    /**
     * List of table columns which represent settings themselves and not metadata
     *
     * @var array
     */
    protected static $settingsColumns = array(
        self::COL_AUTOMATCH,
        self::COL_AUTOMATCH_CHEAP,
        self::COL_MAX_SUPPLIERS,
        self::COL_HIDE_CONTACTS
    );

    protected static $cache = array();

    /**
     * Converts buyer organisation / branch objects and IDS into a pair of buyer organisation and buyer branch ID
     *
     * @param   Shipserv_Rfq|Shipserv_Quote|Shipserv_Buyer|Shipserv_Buyer_Branch|int    $entity
     * @param   Shipserv_Buyer_Branch|int|null              $buyerBranch (can only be not null of $buyerOrg is not Shipserv_Buyer_Branch
     *
     * @throws  Shipserv_Match_Buyer_Settings_Exception
     */
    public function __construct($entity, $buyerBranch = null) {
        $buyerBranchId = null;
        $buyerOrgId = null;

        $sender = null;

        // check if the first parameter is transaction
        if ($entity instanceof Shipserv_Rfq) {
            $sender = $entity->getOriginalSender();
            $buyerBranchId = $entity->rfqBybBranchCode;

        } else if ($entity instanceof Shipserv_Quote) {
            $sender = $entity->getOriginalRfq()->getOriginalSender();
            $buyerBranchId = $entity->qotBybBranchCode;
        }

        if ($sender) {
            if (!is_null($buyerBranch)) {
                throw new Shipserv_Match_Buyer_Settings_Exception("Transaction supplied to read buyer match settings, buyer branch parameters is not expected");
            }

            if ($sender instanceof Shipserv_Buyer) {
                $buyerOrgId = $sender->id;
            } else {
                throw new Shipserv_Match_Buyer_Settings_Exception("Transaction sender is not a buyer, match buyer settings not applicable");
            }
        } else {
            // first parameter is not a transaction document, check other possibilities
            $buyerOrg = $entity;

            // deal with input parameters by converting them to raw IDs whatever they are
            if ($buyerOrg instanceof Shipserv_Buyer_Branch) {
                if (!is_null($buyerBranch)) {
                    throw new Shipserv_Match_Buyer_Settings_Exception("If buyer branch is supplied as a first parameter for match settings, second parameter does not make sense");
                }

                $buyerOrgId = $buyerOrg->getOrgId();
                $buyerBranchId = $buyerOrg->getId();

            } else {
                if ($buyerOrg instanceof Shipserv_Buyer) {
                    $buyerOrgId = $buyerOrg->id;
                } else {
                    $buyerOrgId = $buyerOrg;
                }

                if ($buyerBranch instanceof Shipserv_Buyer_Branch) {
                    $buyerBranchId = $buyerBranch->getId();
                } else {
                    $buyerBranchId = $buyerBranch;
                }
            }

            // validate branch, if provided
            if (!is_null($buyerBranchId) and ($buyerBranch != Myshipserv_Config::getProxyPagesBuyer())) {
                if (!($buyerOrg instanceof Shipserv_Buyer)) {
                    $buyerOrg = Shipserv_Buyer::getInstanceById($buyerOrgId);
                }

                $branchIds = $buyerOrg->getBranchesTnid();
                if (!in_array($buyerBranchId, $branchIds)) {
                    throw new Shipserv_Match_Buyer_Settings_Exception("Supplied buyer branch " . $buyerBranchId . " doesn't not belong to the given buyer " . $buyerOrgId . " and is not a proxy one either");
                }
            }
        }

        $this->buyerOrgId    = $buyerOrgId;
        $this->buyerBranchId = $buyerBranchId;
    }

    /**
     * Removes match settings for the given buyer
     *
     * @param   bool                                        $removeAllBranches  if only org is specified, we can ask to remove all its branches' settings as well
     *
     * @throws  Shipserv_Match_Buyer_Settings_Exception
     */
    public function eraseSettings($removeAllBranches = false) {

        if ($removeAllBranches and !is_null($this->buyerBranchId)) {
            throw new Shipserv_Match_Buyer_Settings_Exception("Removing all buyer branches match settings is only possible when an organisation is specified, not a particular branch");
        }

        $db = Shipserv_Helper_Database::getDb();
        $constraints = array(
            $db->quoteInto(self::COL_ORG_ID . ' = ?', $this->buyerOrgId)
        );

        if (!$removeAllBranches) {
            if (is_null($this->buyerBranchId)) {
                $constraints[] = self::COL_BRANCH_ID . ' IS NULL';
            } else {
                $constraints[] = $db->quoteInto(self::COL_BRANCH_ID . ' = ?', $this->buyerBranchId);
            }
        }

        $db->delete(self::TABLE_NAME, implode(' AND ', $constraints));

        // remove the cached settings as well
        if (array_key_exists($this->buyerOrgId, self::$cache)) {
            if ($removeAllBranches) {
                unset(self::$cache[$this->buyerOrgId]);
            } else {
                if (array_key_exists($this->buyerBranchId, self::$cache[$this->buyerOrgId])) {
                    unset(self::$cache[$this->buyerOrgId][$this->buyerBranchId]);
                }
            }
        }

        // @todo: buyer branch column values are not reset!
    }

    /**
     * Because of data architecture clashes with other apps some settings from this table should also be
     * copied to other tables when updated because they will be read from there
     *
     * @param array $settings
     *
     * @return  bool|int
     */
    protected function updateBuyerTableSettings(array $settings) {
        $db = Shipserv_Helper_Database::getDb();

        if (array_key_exists(self::COL_HIDE_CONTACTS, $settings)) {
            $updateWhere = array(
                $db->quoteInto(Shipserv_Buyer_Branch::COL_ORG_ID . ' = ?', $this->buyerOrgId)
            );

            if ($this->buyerBranchId) {
                 $updateWhere[] = $db->quoteInto(Shipserv_Buyer_Branch::COL_ID . ' = ?', $this->buyerBranchId);
            }

            return $db->update(Shipserv_Buyer_Branch::TABLE_NAME,
                array(
                    Shipserv_Buyer_Branch::COL_HIDE_CONTACTS => ($settings[self::COL_HIDE_CONTACTS] ? 1 : 0)
                ),
                implode(' AND ', $updateWhere)
            );
        }

        return false;
    }

    /**
     * Updates buyer org or branch pages with the given settings or creates a new record, also updates cache
     *
     * @param   array                                       $settings
     * @param   bool                                        $overrideAllBranches    if only organisation is specified, it is possible to override all branch settings as well
     *
     * @throws  Shipserv_Match_Buyer_Settings_Exception
     * @throws  Exception
     */
    public function updateSettings(array $settings, $overrideAllBranches = false) {
        if (empty($settings)) {
            throw new Shipserv_Match_Buyer_Settings_Exception("No buyer match settings specified for updating");
        }

        // validate updated data
        foreach ($settings as $column => $data) {
            if (!in_array($column, self::$settingsColumns)) {
                throw new Shipserv_Match_Buyer_Settings_Exception("Invalid buyer match settings field: " . $column);
            }

            switch ($column) {
                case self::COL_AUTOMATCH:
                case self::COL_AUTOMATCH_CHEAP:
                case self::COL_HIDE_CONTACTS:
                    $settings[$column] = ($data ? 1 : 0);
                    break;

                case self::COL_MAX_SUPPLIERS:
                    $settings[$column] = is_null($data) ? new Zend_Db_Expr('NULL') : (int) $data;
                    break;
            }
        }

        $settings[self::COL_DATE] = new Zend_Db_Expr('SYSDATE');

        if ($overrideAllBranches and !is_null($this->buyerBranchId)) {
            throw new Shipserv_Match_Buyer_Settings_Exception("Overriding all buyer branches match settings is only possible when an organisation is specified, not a particular branch");
        }

        $meta[self::COL_ORG_ID] = $this->buyerOrgId;
        $meta[self::COL_BRANCH_ID] = is_null($this->buyerBranchId) ? new Zend_Db_Expr('NULL') : $this->buyerBranchId;

        // instead of dealing with clumsy Oracle MERGE syntax first try to insert, if fails on unique index, update
        $db = Shipserv_Helper_Database::getDb();

        // begin a transaction because we might perform more than one query and want them to appear atomic
        $db->beginTransaction();

        try {
            if ($overrideAllBranches) {
                self::eraseSettings(true);
            }

            try {
                $insertRow = array_merge($settings, $meta);
                $db->insert(self::TABLE_NAME, $insertRow);
            } catch (Zend_Db_Exception $e) {
                // record already exists, replace it
                $db->update(self::TABLE_NAME, $settings, implode(' AND ', array(
                    $db->quoteInto(self::COL_ORG_ID . ' = ?', $this->buyerOrgId),
                    self::COL_BRANCH_ID . (is_null($this->buyerBranchId) ? ' IS NULL' : $db->quoteInto(' = ?', $this->buyerBranchId))
                )));
            }

            // removed by Yuriy Akopov on 2015-01-13 following talk with Erwin / Elvir / Jeffrey
            // $this->updateBuyerTableSettings($settings);

            $db->commit();

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        // erase cached version so it will be re-read on the next access attempt
        if (array_key_exists($this->buyerOrgId, self::$cache)) {
            if ($overrideAllBranches) {
                unset(self::$cache[$this->buyerOrgId]);
            } else {
                if (array_key_exists($this->buyerBranchId, self::$cache[$this->buyerOrgId])) {
                    unset(self::$cache[$this->buyerOrgId][$this->buyerBranchId]);
                }
            }
        }
    }

    /**
     * Returns default match settings if there is no record for the required buyer yet
     *
     * @return  array
     */
    public static function getDefaultSettings() {
        return array(
            self::COL_AUTOMATCH       => 0,
            self::COL_AUTOMATCH_CHEAP => 1,
            self::COL_MAX_SUPPLIERS   => null,
            self::COL_HIDE_CONTACTS   => false
        );
    }

    /**
     * Returns given buyer branch or organisation settings (which are just a database row array, at least for now) or null if no settings stored
     *
     * @param   bool                                        $fallbackToOrg  if true, then settings for organisation will be returned if no settings for requested branch
     * @param   bool                                        $fallbackToDefault
     *
     * @return  array|null
     */
    public function getSettings($fallbackToOrg = true, $fallbackToDefault = true, $buyerOrgId = null) {
        // check if we have settings cached already
        $byoId = ($buyerOrgId === null) ? $this->buyerOrgId : $buyerOrgId;
        if (array_key_exists($byoId, self::$cache)) {
            // null $buyerBranchId will be converted to '' when used as array key, but that's still okay
            if (array_key_exists($this->buyerBranchId, self::$cache[$byoId])) {
                return self::$cache[$byoId][$this->buyerBranchId];
            }
        }

        // if we are here, cached version hasn't been found and we need to load it from DB

        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);

        $select
            ->from(
                array('mbs' => self::TABLE_NAME),
                self::$settingsColumns
            )
            ->where('mbs.' . self::COL_ORG_ID . ' = ?', $byoId)
        ;

        if (is_null($this->buyerBranchId)) {
            $select->where('mbs.' . self::COL_BRANCH_ID . ' IS NULL');
        } else {
            $select->where('mbs.' . self::COL_BRANCH_ID . ' = ?', $this->buyerBranchId);
        }

        $settings = $db->fetchRow($select);
        if (empty($settings)) {
            if ($fallbackToOrg and !is_null($this->buyerBranchId)) {
                // no branch-specific settings, return organisation settings instead
                $buyerOrgSettings = new self($byoId, null);
                return $buyerOrgSettings->getSettings();
            }

            $settings = null;
        }

        if (is_null($settings) and $fallbackToDefault) {
            $settings = self::getDefaultSettings();
        }

        // cache settings
        self::$cache[$byoId][$this->buyerBranchId] = $settings;

        return $settings;
    }

    /**
     * Checks if the buyer participates in automatch
     *
     * @param   bool    $fallbackToOrg
     * @param   bool    $fallBackToDefault
     *
     * @return  bool
     */
    public function isAutoMatchParticipant($fallbackToOrg = true, $fallBackToDefault = true) {
        $settings = $this->getSettings($fallbackToOrg, $fallBackToDefault);

        return (bool) $settings[self::COL_AUTOMATCH];
    }

    /**
     * Checks if the buyer only wants cheap automatch quotes
     *
     * @param   bool    $fallbackToOrg
     * @param   bool    $fallBackToDefault
     *
     * @return  bool
     */
    public function isAutomatchCheapQuotesMode($fallbackToOrg = true, $fallBackToDefault = true) {
        $settings = $this->getSettings($fallbackToOrg, $fallBackToDefault);

        return (bool) $settings[self::COL_AUTOMATCH_CHEAP];
    }

    /**
     * Returns the maximal number of match suppliers buyer has requested
     *
     * @param   bool    $fallbackToOrg
     * @param   bool    $fallBackToDefault
     *
     * @return  int|null
     */
    public function getMaxMatchSupplierCount($fallbackToOrg = true, $fallBackToDefault = true) {
        $settings = $this->getSettings($fallbackToOrg, $fallBackToDefault);

        return (is_null($settings[self::COL_MAX_SUPPLIERS]) ? null : (int) $settings[self::COL_MAX_SUPPLIERS]);
    }

    /**
     * Returns an array with automatch participant buyers organisation and branch information
     *
     * @return array
     */
    public static function getAutoMatchParticipants() {
        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('mbs' => self::TABLE_NAME),
                array(
                    'mbs.' . self::COL_ORG_ID,
                    'mbs.' . self::COL_BRANCH_ID
                )
            )
            ->where('mbs.' . self::COL_AUTOMATCH . ' = 1')
            ->order('mbs.' . self::COL_BRANCH_ID . ' DESC') // organisations with no branches on top
        ;

        $rows = $db->fetchAll($select);

        $participants = array();
        $pagesProxyBranchId = Myshipserv_Config::getProxyPagesBuyer();

        foreach ($rows as $row) {
            $orgId    = $row[self::COL_ORG_ID];
            $branchId = $row[self::COL_BRANCH_ID];

            if (!is_array($participants[$orgId])) {
                $participants[$orgId] = array();
            }

            if (strlen($branchId) === 0) {
                // organisation with no branch specified, adding all the branches
                $buyerOrg = Shipserv_Buyer::getInstanceById($orgId);
                $allBranchIds = $buyerOrg->getBranchesTnid();
                // add Pages proxy branch to the list
                $allBranchIds[] = $pagesProxyBranchId;

                foreach ($allBranchIds as $id) {
                    $participants[$orgId][] = $id;
                }
            } else {
                // a branch, add it as it is possibly overwriting same added via organisation as well
                $participants[$orgId][] = $branchId;
            }
        }

        return $participants;
    }

}