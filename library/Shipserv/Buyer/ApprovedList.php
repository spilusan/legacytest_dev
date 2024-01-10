<?php
/**
 * Implements list of suppliers approved by buyer
 *
 * @author  Attila O, rewritten the code by Yuriy Akopov
 * @date    2014-02-14
 * @story   S6152
 */
class Shipserv_Buyer_ApprovedList extends Shipserv_Memcache {
    // refer to SSERVDBA_S6152_supplier_blacklist.sql script for definitions
    const
        TABLE_NAME = 'BUYER_SUPPLIER_APPROVEDLIST',
        COL_ID            = 'BSA_ID',
        COL_BUYER_ID      = 'BSA_BYO_ORG_CODE',
        COL_SUPPLIER_ID   = 'BSA_SPB_BRANCH_CODE',
        COL_USER_ID       = 'BSA_PSU_ID',
        COL_DATE          = 'BSA_DATE';

    const
        PAGE_SIZE = 1000;

    const
        LIST_LENGTH_LIMIT = 500;

    const
        REPORTDB = false;
        //DB = 'sservdba'
        
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
     * Clears the approvedlist entries 
     * @return int
     */
    public function removeAll() {
        $db = (self::REPORTDB) ? Shipserv_Helper_Database::getSsreport2Db() : Shipserv_Helper_Database::getDb();
        $db->beginTransaction();
        $dateTime = new DateTime();

        $result = $db->delete(self::TABLE_NAME, implode(' AND ', array(
            $db->quoteInto(self::COL_BUYER_ID . ' = ?', $this->buyerOrg->id)
        )));

        $db->commit();

        return $result;
    }

    /**
     * Removes the given suppliers from the approvedlist  
     *
     * @param   Shipserv_Supplier|Shipserv_Supplier[]|int|array  $supplierBranches
     *
     * @return  int
     */
    public function remove($supplierBranches) {
        $supplierBranchIds = self::_getSupplierBranchIds($supplierBranches);

        $dateTime = new DateTime();

        $db = (self::REPORTDB) ? Shipserv_Helper_Database::getSsreport2Db() : Shipserv_Helper_Database::getDb();
        $db->beginTransaction();

        $delCount = 0;
        $start = 0;
        $step = Shipserv_Helper_Database::MAX_IN;

        $constraints = array(
            $db->quoteInto(self::COL_BUYER_ID . ' = ?', $this->buyerOrg->id)
        );

        while (count($sliceIn = array_slice($supplierBranchIds, $start, $step))) {
            $start += $step;

            $delConstraints = $constraints;
            $delConstraints[] = $db->quoteInto(self::COL_SUPPLIER_ID . ' IN (?)', $sliceIn);
            $delCount += $db->delete(self::TABLE_NAME, implode(' AND ', $delConstraints));
        }

        $db->commit();

        return $delCount;
    }

    /**
     * Adds the given suppliers to the approved list 
     *
     * @param   Shipserv_Supplier|Shipserv_Supplier[]|int|array  $supplierBranches
     *
     * @throws  Shipserv_Helper_Database_Exception
     */
    public function add($supplierBranches) {

        $supplierBranchIds = self::_getSupplierBranchIds($supplierBranches);

        $db = (self::REPORTDB) ? Shipserv_Helper_Database::getSsreport2Db() : Shipserv_Helper_Database::getDb();

        // first check the current list length as we apply a limit on it
        // since new and old IDs may overlap, we need to read the old list first
        $db->beginTransaction();    // start transaction early so another add() with its length check would queue

        $currentListIds = $this->getListedSuppliers();
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
    }

    /**
     * Returns paginator for blacklisted suppliers 
     * All the blacklist and supplier branch fields are returned
     *
     * @return  Zend_Paginator
     */
    public function getListPaginator() {
        $db = (self::REPORTDB) ? Shipserv_Helper_Database::getSsreport2Db() : Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
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
            ->order('spb.' . Shipserv_Supplier::COL_NAME)
        ;

        //print $select->assemble(); exit;

        $paginator = Zend_Paginator::factory($select);

        return $paginator;
    }

    /**
     * Returns all the suppliers from the approved list
     *
     * @return  Shipserv_Supplier[]|array
     */
    public function getListedSuppliers() {
        $cached = $this->memcacheGet(__CLASS__, __FUNCTION__, $this->_getMemcacheKey('approvedList'));
        if ($cached !== false) {
            return $cached;
        }

        $paginator = $this->getListPaginator();
        $paginator->setItemCountPerPage(self::PAGE_SIZE);

        $supplierIds = array();

        for ($pageNo = 1; $pageNo <= count($paginator); $pageNo++) {
            $paginator->setCurrentPageNumber($pageNo);

            $rows = $paginator->getCurrentItems();
            foreach ($rows as $row) {
                $supplierIds[] = (int) $row[self::COL_SUPPLIER_ID];
            }
        }

        $result = $this->memcacheSet(__CLASS__, __FUNCTION__, $this->_getMemcacheKey('approvedList'), $supplierIds);

        return $supplierIds;
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

}