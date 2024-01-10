<?php
/**
 * A controller for buyer blacklist related webservices
 *
 * @author  Yuriy Akopov
 * @date    2014-02-17
 * @story   S6152
 */
class Buyer_BlacklistController extends Myshipserv_Controller_Action {
    const
        PARAM_SUPPLIER_ID   = 'supplierId',
        PARAM_SUPPLIER_ID_FILE = 'supplierIdFile',
        PARAM_TYPE          = 'type',
        PARAM_REASON          = 'reason'
    ;


    const
        RESULT_RESULT = 'result'
    ;

    /**
     * Helper function to retrieve blacklist type parameter
     *
     * @param   bool    $allowNoType
     *
     * @return  string|null
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _getType($allowNoType = false) {
        $type = $this->_getParam(self::PARAM_TYPE);

        if (strlen($type) === 0) {
            if ($allowNoType) {
                $type = null;
            } else {
                throw new Myshipserv_Exception_MessagedException("No blacklist type specified");
            }
        }

        return $type;
    }

    /**
     * Helper function to retrieve the list of supplier branch IDs
     *
     * @param   bool    $allowNoSuppliers
     *
     * @return  int|array|null
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _getSupplierIds($allowNoSuppliers = false) {
        $supplierIds = $this->_getParam(self::PARAM_SUPPLIER_ID);

        if (
            (!is_array($supplierIds) and (strlen($supplierIds) === 0))
            or (is_array($supplierIds) and empty($supplierIds))
        ) {
            if ($allowNoSuppliers) {
                $supplierIds = null;
            } else {
                throw new Myshipserv_Exception_MessagedException("No suppliers IDs specified for the list");
            }
        }

        return $supplierIds;
    }

    /**
     * Returns supplier IDs parsed from the uploaded file
     * Supported format - comma/new line separated/mixed IDs, non-integers ignored
     *
     * @return  array
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _getSupplierIdsFromFile() {

        $filename = $data = $_FILES[self::PARAM_SUPPLIER_ID_FILE]['tmp_name'];
        if ($filename !== null) {
            if (($fh = fopen($filename, 'r')) === false) {
                throw new Myshipserv_Exception_MessagedException("No supplier IDs file specified for the list");
            }
        }

        $supplierIds = array();
        if ($filename !== null) {
            while (($row = fgetcsv($fh)) !== false) {
                foreach ($row as $cell) {
                    $id = trim($cell);

                    if (filter_var($id, FILTER_VALIDATE_INT) and ($id > 0)) {
                        if ($this->supplierIdEsists($id)) {
                            $supplierIds[] = $id;
                        }
                        if (count($supplierIds) === Shipserv_Buyer_SupplierList::LIST_LENGTH_LIMIT) {
                            return $supplierIds;
                        }
                    }
               }
            }
        }

        return $supplierIds;
    }

    /**
     * Helper function returning blacklist manager object for the current buyer / user
     *
     * @return  Shipserv_Buyer_SupplierList
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _getBlacklist() {
        $buyerOrg = $this->getUserBuyerOrg();
        $user = Shipserv_User::isLoggedIn();
        if (!$user) {
            Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        }

        $blacklist = new Shipserv_Buyer_SupplierList($buyerOrg, $user);
        return $blacklist;
    }

    /**
     * Adds one or more supplier to current buyer's blacklist
     *
     * @return  mixed
     * @throws  Shipserv_Buyer_SupplierList_Exception
     */
    public function addAction() {
        $blacklist = $this->_getBlacklist();
        $type = $this->_getType();
        $supplierIds = $this->_getSupplierIds(true);

        if (is_null($supplierIds)) {
            $supplierIds = $this->_getSupplierIdsFromFile();
            $fileUpload = true;
        } else {
            $fileUpload = false;
        }

        if ($fileUpload) {
            if (count(array_unique($supplierIds)) > Shipserv_Buyer_SupplierList::LIST_LENGTH_LIMIT) {
                throw new Shipserv_Buyer_SupplierList_Exception("Uploaded list contains more than " . Shipserv_Buyer_SupplierList::LIST_LENGTH_LIMIT . " allowed IDs");
            }

            // if we are here than there is a case of mass upload so we need to remove the current content
            $blacklist->removeAll($type);
        }

        $blacklist->add($supplierIds, $type);

        return $this->_helper->json((array)array(
            'result' => true
        ));
    }


    /**
     * Adds one or more supplier to current buyer's blacklist
     *
     * @return  mixed
     * @throws  Shipserv_Buyer_SupplierList_Exception
     */
    public function addAndRemoveAction() {
        $blacklist = $this->_getBlacklist();
        $type = $this->_getType();
        $supplierIds = $this->_getSupplierIds(true);

        if (is_null($supplierIds)) {
            $supplierIds = $this->_getSupplierIdsFromFile();
            $fileUpload = true;
        } else {
            $fileUpload = false;
        }

        if ($fileUpload) {
            if (count(array_unique($supplierIds)) > Shipserv_Buyer_SupplierList::LIST_LENGTH_LIMIT) {
                throw new Shipserv_Buyer_SupplierList_Exception("Uploaded list contains more than " . Shipserv_Buyer_SupplierList::LIST_LENGTH_LIMIT . " allowed IDs");
            }
            
        }
        $blacklist->removeAll($type);

        $blacklist->add($supplierIds, $type);

        //If we pass the plainText parameter, the result will be plain text. This fix was needed, as there is a bug in IE, and when using the jQuery file upload plugin, IE wants to download the response if it is in json
        if ($this->_getParam('resultType') == 'plainText') {
            $this->_helper->viewRenderer->setNoRender(true);
            $this->_helper->layout->disableLayout();
            $this->_response->setHeader('Content-Type', 'text/xml; charset=utf-8')->setBody('true');
        } else {
            return $this->_helper->json((array)array(
                'result' => true,
                'type' => $this->_getParam('whitelist'),
            ));
        }
       
    }

    /**
     * Retrieves all the suppliers from the blacklist of the given type
     *
     * @return mixed
     * @throws Myshipserv_Exception_MessagedException
     */
    public function getAllAction() {
        $blacklist = $this->_getBlacklist();
        $type = $this->_getType();

        $supplierIds = $blacklist->getListedSuppliers($type, false);

        $data = array();
        foreach ($supplierIds as $id) {
            $supplier = Shipserv_Supplier::getInstanceById($id, null, true);

            $data[] = array(
                'id'   => (int) $supplier->tnid,
                'name' => $supplier->name,
                'url'  => $supplier->getUrl()
            );
        }

        return $this->_helper->json((array)$data);
    }

    /**
     * Removes all the supplies from the given blacklist
     *
     * @return mixed
     * @throws Myshipserv_Exception_MessagedException
     */
    public function removeAction() {
        $blacklist = $this->_getBlacklist();
        $type = $this->_getType();
        $supplierIds = $this->_getSupplierIds(true);

        if (is_null($supplierIds)) {
            $result = $blacklist->removeAll($type);
        } else {
            $result = $blacklist->remove($supplierIds, $type);
        }

        return $this->_helper->json((array)array(
            self::RESULT_RESULT => $result
        ));
    }

    /**
     * Returns list of all supplier branches registered in the system except the ones already blacklisted
     *
     * @return mixed
     */
    public function availableAction() {
        $blacklist = $this->_getBlacklist();
        $type = $this->_getType();
        $blacklistedIds = $blacklist->getListedSuppliers($type); /** @var $blacklistedIds array */

        $filter = $this->_getParam('query');
        if (strlen($filter) === 0) {
            $filter = null;
        }

        $suggestions = array();
        $data = array();

        if (is_null($filter)) {
            // no filtering is required, so we can use cached list and remove already blacklisted suppliers manually
            $suppliers = Shipserv_Supplier::getAllBranches();
            foreach ($blacklistedIds as $id) {
                unset($suppliers[$id]);
            }

            foreach ($suppliers as $id => $name) {
                $suggestions[] = $name;
                $data[] = (int) $id;
            }
        } else {
            // filtering is required, so no chances of caching - running an ad-hoc query then
            $buyerOrg = $this->getUserBuyerOrg();

            $db = Shipserv_Helper_Database::getDb();
            $select = new Zend_Db_Select($db);
            $select
                ->from(
                    array('spb' => Shipserv_Supplier::TABLE_NAME),
                    array(
                        'ID'    => 'spb.' . Shipserv_Supplier::COL_ID,
                        'NAME'  => new Zend_Db_Expr('TRIM(spb.' . Shipserv_Supplier::COL_NAME . ')')
                    )
                );
                //nofilter parameter is added to retrieve the entire dataset for Approved Supplier List add actin
                if ($this->_getParam('nofilter') != 1) {
                    $select->joinLeft(
                        array('bsb' => Shipserv_Buyer_SupplierList::TABLE_NAME),
                        implode(' AND ', array(
                            $db->quoteInto('bsb.' . Shipserv_Buyer_SupplierList::COL_TYPE . ' = ?', $type),
                            $db->quoteInto('bsb.' . Shipserv_Buyer_SupplierList::COL_BUYER_ID . ' = ?', $buyerOrg->id),
                            'spb.' . Shipserv_Supplier::COL_ID . ' = bsb.' . Shipserv_Buyer_SupplierList::COL_SUPPLIER_ID
                        )),
                        array()
                    )
                    ->where('bsb.' . Shipserv_Buyer_SupplierList::COL_ID . ' IS NULL');
                }

                $select->where('spb.' . Shipserv_Supplier::COL_TEST . ' <> ?', 'Y')
                ->where('spb.' . Shipserv_Supplier::COL_DELETED . ' <> ?', 'Y')
                ->where('spb.' . Shipserv_Supplier::COL_DIR_ENTRY . ' = ?', Shipserv_Supplier::DIR_STATUS_PUBLISHED)
                ->where('spb.' . Shipserv_Supplier::COL_STATUS . ' = ?', 'ACT')
                ->where('spb.' . Shipserv_Supplier::COL_ID . ' < ?', 1000000)

                ->order(new Zend_Db_Expr('TRIM(spb.' . Shipserv_Supplier::COL_NAME . ')'))
            ;

            $constraints = array(
                'LOWER(spb.' . Shipserv_Supplier::COL_NAME . ')' . Shipserv_Helper_Database::escapeLike($db, strtolower($filter))
            );
            if (is_numeric($filter)) {
                $constraints[] = $db->quoteInto('spb.' . Shipserv_Supplier::COL_ID . ' = ?', $filter);
            }
            $select->where(implode(' OR ', $constraints));
            $rows = $db->fetchAll($select);
            foreach ($rows as $row) {
                $obj  = (object) array('value' => $row['NAME'] . ' - ' . $row['ID'], 'data' => $row['ID']);
                $suggestions[] = $obj;
            }
        }

        $data = array(
            'query'       => $filter,
            'suggestions' => $suggestions
        );

        return $this->_helper->json((array)$data);
    }

    public function enabledAction() {
        $blacklist = $this->_getBlacklist();
        $type = $this->_getType();

        $enabled = $this->_getParam('enabled');
        if (strlen($enabled)) {
            $blacklist->enable($type, $enabled);
        }

        $this->_helper->json((array)array(
            self::RESULT_RESULT => $blacklist->isEnabled($type)
        ));
    }

    protected function supplierIdEsists( $tnid )
    {
        $db = Shipserv_Helper_Database::getDb();
        $sql = 'select count(*) as pc from supplier_branch where spb_branch_code = :thid';
        $result = $db->fetchOne($sql, array('thid' => (int)$tnid));
        return ($result == 1);
    }

    
    /**
    * Store the reason why a supplier is blacklisted in Spend Benchmarking report
    * Store Supplier ID, Blacklist Type, and user ID who did it, and when
    */
    public function storeReasonAction()
    {
        $blacklist = $this->_getBlacklist();

        $type = $this->_getType();
        $reason = $this->_getParam(self::PARAM_REASON);
        $spbBranchCode = $this->_getParam(self::PARAM_SUPPLIER_ID);

        $blacklist->storeReason($spbBranchCode, $type, $reason);

        return $this->_helper->json((array)array(
            'result' => true
        ));
    }

    public function clearSpendBenchmarkCacheAction()
    {
        $report = new Shipserv_Report_Supplier_Match();
        $result = $report->purgeAllFromMemcache();

        return $this->_helper->json((array)array(
            'result' => true,
            'cahceHadToBeCleared' => $result  
        ));

    }
}