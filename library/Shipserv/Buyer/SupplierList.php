<?php
/**
 * Implements list of suppliers unwanted by buyer
 *
 * @author  Yuriy Akopov
 * @date    2014-02-14
 * @story   S6152
 */
class Shipserv_Buyer_SupplierList extends Shipserv_Memcache {
    // refer to SSERVDBA_S6152_supplier_blacklist.sql script for definitions
    const
        TABLE_NAME = 'BUYER_SUPPLIER_BLACKLIST',

        COL_ID            = 'BSB_ID',
        COL_BUYER_ID      = 'BSB_BYO_ORG_CODE',
        COL_SUPPLIER_ID   = 'BSB_SPB_BRANCH_CODE',
        COL_USER_ID       = 'BSB_PSU_ID',
        COL_DATE          = 'BSB_DATE',
        COL_TYPE          = 'BSB_TYPE'
    ;

    // supported blacklist types (values of COL_TYPE column)
    const
        TYPE_BLACKLIST    = 'blacklist', // 'match',
        TYPE_WHITELIST    = 'whitelist',  //'forward'
        TYPE_BLACKLIST_SB = 'blacksb' //Exclude from Spend Benchmarking
    ;

    const
        STATUS_TABLE_NAME = 'BUYER_BLACKLIST_STATUS',

        STATUS_COL_ID       = 'BBS_ID',
        STATUS_COL_BUYER_ID = 'BBS_BYO_ORG_CODE',
        STATUS_COL_TYPE     = 'BBS_TYPE',
        STATUS_COL_ENABLED  = 'BBS_ENABLED'
    ;

    const
        PAGE_SIZE = 1000
    ;

    const
        LIST_LENGTH_LIMIT = 500
    ;

    /**
     * @var Shipserv_Buyer
     */
    protected $buyerOrg = null;

    /**
     * @var Shipserv_User
     */
    protected $user = null;

    /**
     * @var Myshipserv_Search_Match
     */
    protected $index = null;

    /**
     * Buyer organisation is supplied separately from user because the logic to derive it involves sessions and thus
     * resides in controllers
     *
     * @param   Shipserv_Buyer  $buyerOrg
     * @param   Shipserv_User   $user
     */
    public function __construct(Shipserv_Buyer $buyerOrg, Shipserv_User $user = null) {
        $this->buyerOrg = $buyerOrg;
        $this->user = $user;
        $this->index = new Myshipserv_Search_Match($this->buyerOrg);
    }

    /**
     * Helper function to extract the list of supplier branches IDs from objects / collections / scalars supplied by user
     *
     * @param  Shipserv_Supplier|Shipserv_Supplier[]|int|array  $supplierBranches
     *
     * @return array
     */
    protected static function _getSupplierBranchIds($supplierBranches) {
        if (!is_array($supplierBranches)) {
            $supplierBranches = array($supplierBranches);
        }

        $supplierBranchIds = array();
        foreach ($supplierBranches as $branch) {
            if ($branch instanceof Shipserv_Supplier) {
                $supplierBranchIds[] = $branch->tnid;
            } else {
                $supplierBranchIds[] = $branch;
            }
        }

        return $supplierBranches;
    }

    /**
     * Helper function to validate the type before they're written into the table
     *
     * @param   string  $type
     *
     * @throws  Shipserv_Buyer_SupplierList_Exception
     */
    protected static function _validateType($type) {
        if (!in_array($type, array(
            self::TYPE_WHITELIST,
            self::TYPE_BLACKLIST,
            self::TYPE_BLACKLIST_SB
        ))) {
            throw new Shipserv_Buyer_SupplierList_Exception("Unknown supplier list type: " . $type);
        }
    }

    /**
     * Clears the blacklist entries of the given type or all of them
     *
     * @param   string  $type
     *
     * @return int
     */
    public function removeAll($type) {
        $db = Shipserv_Helper_Database::getDb();
        $db->beginTransaction();
        $dateTime = new DateTime();

        $result = $db->delete(self::TABLE_NAME, implode(' AND ', array(
            $db->quoteInto(self::COL_BUYER_ID . ' = ?', $this->buyerOrg->id),
            $db->quoteInto(self::COL_TYPE . ' = ?', $type)
        )));

        $db->commit();

        $this->_resetDependencies($type, $dateTime);

        return $result;
    }

    /**
     * Removes the given suppliers from the blacklist of the given type (or from all of them)
     *
     * @param   Shipserv_Supplier|Shipserv_Supplier[]|int|array  $supplierBranches
     * @param   string  $type
     *
     * @return  int
     */
    public function remove($supplierBranches, $type) {
        $supplierBranchIds = self::_getSupplierBranchIds($supplierBranches);

        $dateTime = new DateTime();

        $db = Shipserv_Helper_Database::getDb();
        $db->beginTransaction();

        $delCount = 0;
        $start = 0;
        $step = Shipserv_Helper_Database::MAX_IN;

        $constraints = array(
            $db->quoteInto(self::COL_BUYER_ID . ' = ?', $this->buyerOrg->id),
            $db->quoteInto(self::COL_TYPE . ' = ?', $type)
        );

        while (count($sliceIn = array_slice($supplierBranchIds, $start, $step))) {
            $start += $step;

            $delConstraints = $constraints;
            $delConstraints[] = $db->quoteInto(self::COL_SUPPLIER_ID . ' IN (?)', $sliceIn);
            $delCount += $db->delete(self::TABLE_NAME, implode(' AND ', $delConstraints));
        }

        $db->commit();

        $this->_resetDependencies($type, $dateTime);

        return $delCount;
    }

    /**
     * Adds the given suppliers to the blacklist of the given type
     *
     * @param   Shipserv_Supplier|Shipserv_Supplier[]|int|array  $supplierBranches
     * @param   string  $type
     *
     * @throws  Shipserv_Buyer_SupplierList_Exception
     */
    public function add($supplierBranches, $type) {
        self::_validateType($type);
        $supplierBranchIds = self::_getSupplierBranchIds($supplierBranches);

        $db = Shipserv_Helper_Database::getDb();

        // first check the current list length as we apply a limit on it
        // since new and old IDs may overlap, we need to read the old list first
        $db->beginTransaction();    // start transaction early so another add() with its length check would queue

        $currentListIds = $this->getListedSuppliers($type);
        $newListIds = array_merge($currentListIds, $supplierBranchIds);
        $newListIds = array_unique($newListIds);

        if (count($newListIds) > self::LIST_LENGTH_LIMIT) {
            throw new Shipserv_Buyer_SupplierList_Exception(
                "Unable to add " . count($supplierBranchIds) .
                " suppliers to the list of " . count($newListIds) .
                ", limit of "  .self::LIST_LENGTH_LIMIT .
                " unique suppliers would be reached"
            );
        }

        // if we are here, the length of the new list is fine

        $dateTime = new DateTime();

        // we will need to insert / update records in the table rather than deleting then, here are the values of the columns as we want them
        $fields = array(
            self::COL_BUYER_ID      => $this->buyerOrg->id,
            self::COL_USER_ID       => (is_null($this->user) ? null : $this->user->userId),
            self::COL_DATE          => new Zend_Db_Expr(Shipserv_Helper_Database::getOracleDateExpr($dateTime)),
            self::COL_TYPE          => $type,
            self::COL_SUPPLIER_ID   => null // to be added later in the loop
        );

        $insertFields = array();
        foreach ($fields as $field => $value) {
            $insertFields[] = 'new.' . $field;
        }

        // rather ugly code below because of Oracle MERGE statement syntax and the lack of support for it in Zend

        // prepare drafts for the final SQL
        $sql = array(
            "MERGE INTO " . self::TABLE_NAME . " old USING (",
            null, // we will put SELECTs for values to be upserted there
            ") new ON (",
            "old." . self::COL_BUYER_ID . " = new." . self::COL_BUYER_ID,
            "AND old." . self::COL_SUPPLIER_ID . " = new." . self::COL_SUPPLIER_ID,
            "AND old." . self::COL_TYPE . " = new." . self::COL_TYPE,
            ")",
            // insert a new record if there is no match on constraints above
            "WHEN NOT MATCHED THEN INSERT (" . implode(', ', array_keys($fields)) . ") VALUES (",
            implode(', ', $insertFields),
            ")",
            // if the supplier is already blacklisted, update user and date fields
            "WHEN MATCHED THEN UPDATE SET",
            "old." . self::COL_USER_ID . " = new." . self::COL_USER_ID . ",",
            "old." . self::COL_DATE . " = new." . self::COL_DATE
        );

        $start = 0;
        $step = 100;    // maximal number of SELECTs to unionise at once

        while (count($sliceIn = array_slice($supplierBranchIds, $start, $step))) {
            $start += $step;

            // generate SELECT statement for every supplier
            $sliceRows = array();
            foreach ($supplierBranchIds as $supplierId) {
                $rowFields = $fields;
                $rowFields[self::COL_SUPPLIER_ID] = $supplierId;
                $rowSelectAs = array();
                foreach ($rowFields as $field => $value) {
                    if (is_null($value)) {
                        $rowSelectAs[] = 'NULL AS ' . $field;
                    } else {
                        $rowSelectAs[] = $db->quoteInto('? AS ' . $field, $value);
                    }
                }

                $sliceRows[] = 'SELECT ' . implode(', ', $rowSelectAs) . ' FROM DUAL';
            }

            // prepare the upsert statement by injecting our prepared unionised SELECTs with values into the upsert statement
            $spliceSql = $sql;
            $spliceSql[1] = implode (PHP_EOL . "UNION" . PHP_EOL, $sliceRows);
            $upsertSql = implode(PHP_EOL, $spliceSql);

            $db->query($upsertSql);
        }

        $db->commit();

        $this->_resetDependencies($type, $dateTime);
    }

    /**
     * Returns paginator for blacklisted suppliers of the given type
     * All the blacklist and supplier branch fields are returned
     *
     * @param   string  $type
     *
     * @return  Zend_Paginator
     */
    public function getListPaginator($type) {
        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('bsb' => self::TABLE_NAME),
                'bsb.*'
            )
            ->join(
                array('spb' => Shipserv_Supplier::TABLE_NAME),
                'bsb. ' . self::COL_SUPPLIER_ID . ' = spb.' . Shipserv_Supplier::COL_ID,
                'spb.*'
            )
            ->where('bsb.' . self::COL_BUYER_ID . ' = ?', $this->buyerOrg->id)
            ->where('bsb.' . self::COL_TYPE . ' = ?', $type)
            ->order('spb.' . Shipserv_Supplier::COL_NAME)
        ;

        //print $select->assemble(); exit;

        $paginator = Zend_Paginator::factory($select);

        return $paginator;
    }

    /**
     * Returns all the suppliers from the blacklist of the given type
     *
     * @param   string  $type
     * @param   bool  $useCache
     *
     * @return  Shipserv_Supplier[]|array
     */
    public function getListedSuppliers($type, $useCache = true) {
    	if ($useCache === true) {
	        $cached = $this->memcacheGet(__CLASS__, __FUNCTION__, $this->_getMemcacheKey($type));
	        if ($cached !== false) {
	            return $cached;
	        }
    	}

        $paginator = $this->getListPaginator($type);
        $paginator->setItemCountPerPage(self::PAGE_SIZE);

        $supplierIds = array();

        for ($pageNo = 1; $pageNo <= count($paginator); $pageNo++) {
            $paginator->setCurrentPageNumber($pageNo);

            $rows = $paginator->getCurrentItems();
            foreach ($rows as $row) {
                $supplierIds[] = (int) $row[self::COL_SUPPLIER_ID];
            }
        }

        $result = $this->memcacheSet(__CLASS__, __FUNCTION__, $this->_getMemcacheKey($type), $supplierIds);

        return $supplierIds;
    }

    /**
     * Marks the blacklist of the specified type as enabled or disabled
     *
     * @param   string  $type
     * @param   bool    $enable
     *
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function enable($type, $enable) {
        self::_validateType($type);

        $db = Shipserv_Helper_Database::getDb();
        $fields = array(
            self::STATUS_COL_BUYER_ID   => $this->buyerOrg->id,
            self::STATUS_COL_TYPE       => $type,
            self::STATUS_COL_ENABLED    => ($enable ? 1 : 0)
        );

        // prepare bits to compose MERGE query from
        $dualCols = array();
        foreach ($fields as $col => $val) {
            $val          = $db->quote($val);
            $fields[$col] = $val;   // we will need that quoted later as well, that's why aren't using quoteInto here
            $dualCols[]   = $val . ' AS ' . $col;
        }
        $select = "SELECT " . implode(', ', $dualCols) . " FROM DUAL";

        $sql = array(
            "MERGE INTO " . self::STATUS_TABLE_NAME . " old USING (",
            $select,
            ") new ON (",
            "old." . self::STATUS_COL_BUYER_ID . " = new." . self::STATUS_COL_BUYER_ID,
            "AND old." . self::STATUS_COL_TYPE . " = new." . self::STATUS_COL_TYPE,
            ")",
            // insert a new record if there is no match on constraints above
            "WHEN NOT MATCHED THEN INSERT (" . implode(', ', array_keys($fields)) . ") VALUES (",
            implode(', ', $fields),
            ")",
            // if the supplier is already blacklisted, update blacklist status
            "WHEN MATCHED THEN UPDATE SET",
            "old." . self::STATUS_COL_ENABLED . " = new." . self::STATUS_COL_ENABLED
        );

        $db->beginTransaction();
        $datetime = new DateTime();
        $db->query(implode(PHP_EOL, $sql));
        $db->commit();

        Shipserv_Match_Component_Search::eraseOlderSeachesOwner(Shipserv_Rfq::SENDER_TYPE_BUYER, $this->buyerOrg->id, $datetime);
    }

    /**
     * Checks if the blacklist of the given type is enabled
     *
     * @param   string   $type
     *
     * @return  bool
     */
    public function isEnabled($type) {
        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('bbs' => self::STATUS_TABLE_NAME),
                'bbs.' . self::STATUS_COL_ENABLED
            )
            ->where('bbs.' . self::STATUS_COL_BUYER_ID . ' = ?', $this->buyerOrg->id)
            ->where('bbs.' . self::STATUS_COL_TYPE . ' = ?', $type)
        ;

        $enabled = $select->getAdapter()->fetchOne($select);

        if (is_null($enabled)) {
            // by default blacklists are enabled
            return true;
        }

        return (bool) $enabled;
    }

    /**
     * Returns true is given supplier doesn't contradict buyer's black and white lists
     *
     * @param   Shipserv_Supplier|int   $supplier
     *
     * @return  bool
     */
    public function validateSupplier($supplier) {
        if ($supplier instanceof Shipserv_Supplier) {
            $supplierId = $supplier->tnid;
        } else {
            $supplierId = $supplier;
        }

        $db = Shipserv_Helper_Database::getDb();
        $selectInList = new Zend_Db_Select($db);
        $selectInList
            ->from(
                array('list' => self::TABLE_NAME),
                self::COL_SUPPLIER_ID
            )
            ->where('list.' . self::COL_BUYER_ID . ' = ?', $this->buyerOrg->id)
            ->where('list.' . self::COL_SUPPLIER_ID . ' = ?', $supplierId)
            ->where('list.' . self::COL_TYPE . ' = :listType')
        ;

        // if whitelist is enabled then supplier should be listed in it
        if ($this->isEnabled(self::TYPE_WHITELIST)) {
            $blacklisted = $db->fetchAll($selectInList, array('listType' => self::TYPE_WHITELIST));
            if (empty($blacklisted)) {
                return false;
            }
        }

        // blacklist has higher priority than whitelist so it's checked last

        // if blacklist is enabled then supplier should not be listed in it
        if ($this->isEnabled(self::TYPE_BLACKLIST)) {
            $blacklisted = $db->fetchAll($selectInList, array('listType' => self::TYPE_BLACKLIST));
            if (!empty($blacklisted)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Generated memcached key for list items of the given type
     *
     * @param   string  $type
     *
     * @return  string
     */
    protected function _getMemcacheKey($type) {
        $key = implode('_', array(
            $this->buyerOrg->id,
            $type
        ));

        return $key;
    }

    /**
     * When supplier list is modified dependants (searches, caches etc) should be amended or reset
     *
     * @param   string      $type
     * @param   DateTime    $datetime
     */
    protected function _resetDependencies($type, DateTime $datetime = null) {
        Shipserv_Match_Component_Search::eraseOlderSeachesOwner(Shipserv_Rfq::SENDER_TYPE_BUYER, $this->buyerOrg->id, $datetime);
        $this->memcachePurge(__CLASS__, 'getListedSuppliers', $this->_getMemcacheKey($type));
    }

    public function storeReason($spbBranchCode, $type, $reason)
    {
        $db = Shipserv_Helper_Database::getDb();

        $sql = "
            INSERT INTO
                buyer_supplier_b_reason
            (
                  bsr_byo_org_code
                , bsr_spb_branch_code 
                , bsr_psu_id 
                , bsr_date
                , bsr_type 
                , bsr_reason 
            )
            VALUES
            (
                  :byoOrgCode
                , :spbBranchCode 
                , :psuId 
                , SYSDATE
                , :type 
                , :reason 
            )
        ";

        $db->query($sql,array(
                  'byoOrgCode' => $this->buyerOrg->id
                , 'spbBranchCode' => $spbBranchCode 
                , 'psuId' => $this->user->userId 
                , 'type' => $type 
                , 'reason' => $reason 
            ));

        $db->commit();

    }
}