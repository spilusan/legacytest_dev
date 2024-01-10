<?php
/**
 * Tests RFQ event hash calculated on line item insertion
 *
 * @author  Yuriy Akopov
 * @date    2014-04-11
 * @story   S10029
 */
class RfqEventsTriggerTest extends PHPUnit_Framework_Testcase {
    // keys for $this->_rfqData items
    const
        RFQ_ID       = 'rfqId',
        SENDER       = 'sender',
        EVENT_HASH   = 'hash',
        LI_COUNT     = 'lineItemCount'
    ;

    /**
     * IDs of the RFQ to re-insert with their line items to test the trigger
     *
     * @var array
     */
    protected static $_rfqData = null;

    /**
     * IDs of the RFQs created during testing
     *
     * @var array
     */
    protected $_createdRfqIds = array();

    /**
     * IDs of buyer branch proxies to temporarily ignore during testing
     *
     * @var array
     */
    protected static $_buyerProxyIds = null;

    /**
     * @var Zend_Db_Adapter_Oracle
     */
    protected $_db = null;

    /**
     * @var array
     */
    protected static $_elapsedTrigger = array();

    /**
     * @var array
     */
    protected static $_elapsedRebuild = array();

    /**
     * Initialises test data
     * If requested, starts a DB transaction before testing so all the changes could be rolled back at the end
     */
    public function setUp() {
        if (is_null(self::$_rfqData)) {
            self::$_rfqData = $this->_getConfigRfqData();
        }

        if (is_null(self::$_buyerProxyIds)) {
            $config = Zend_Registry::get('config');

            self::$_buyerProxyIds = array(
                $config->shipserv->pagesrfq->buyerId,
                $config->shipserv->match->buyerId
            );
        }

        $this->_db = Shipserv_Helper_Database::getDb();

        global $dbCleanUp;
        if ($dbCleanUp) {
            $this->_db->beginTransaction();
        }
    }

    /**
     * Removes the DB records created during testing if requested or prints out their IDs to tester
     */
    public function tearDown() {
        global $dbCleanUp;
        if (!$dbCleanUp) {
            if (!empty($this->_createdRfqIds)) {
                print
                    "IDs of the RFQs generated in the process are:" . PHP_EOL .
                    implode(',' . PHP_EOL, $this->_createdRfqIds)
                ;
            }

            print PHP_EOL;
            return;
        }

        $this->_db->rollBack();
    }

    /**
     * Returns array of test RFQ details from XML config
     *
     * @return  array
     */
    protected function _getConfigRfqData() {
        $rfqData = array();

        foreach ($GLOBALS as $var => $value) {
            if (preg_match('/^rfqId(\d+)$/', $var, $matches)) {
                $rfqData[$value] = array(
                    self::RFQ_ID        => $value,
                    self::SENDER  => $GLOBALS['rfqSender' . $matches[1]]
                );
            }
        }

        return $rfqData;
    }

    /**
     * Standard Zend INSERT function uses prepared queries and may generate an identifier for a field which is too long
     * and breaks the code.
     *
     * Here is a dumber version which prepares SQL in a form of a string with values already embedded into it.
     *
     * @param   string  $tableName
     * @param   array   $row
     *
     * @return  string
     */
    protected function _getInsertSql($tableName, array $row) {
        foreach ($row as $field => $value) {
            $row[$field] = $this->_db->quote($value);
        }

        $sql =
            'INSERT INTO ' . $tableName .
            ' (' . implode(', ', array_keys($row)) . ')' .
            'VALUES(' . implode(', ', $row) . ')'
        ;

        return $sql;
    }

    /**
     * Copies the given RFQ and its line items into a new one invoking the associated triggers, returns the elapsed time
     *
     * @param   int     $rfqId
     * @param   string  $expectedHash
     *
     * @return  float
     * @throws  Exception
     */
    protected function _cloneRfq($rfqId, &$expectedHash) {
        $elapsed = 0;

        // we can insert from SELECT skipping an extra query, but that won't be the use case we want to test
        // so we will be first reading the data from the database into memory and then inserting it as new data
        // with a separate command

        // start with reading RFQ
        $rfqSelect = new Zend_Db_Select($this->_db);
        $rfqSelect
            ->from(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                '*'
            )
            ->where(Shipserv_Rfq::COL_ID . ' = ?', $rfqId)
        ;

        $rfqRow = $this->_db->fetchRow($rfqSelect);
        if (empty($rfqRow)) {
            throw new Exception("Source RFQ " . $rfqId . " not found");
        }

        // read RFQ line items
        $liSelect = new Zend_Db_Select($this->_db);
        $liSelect
            ->from(
                array('rfl' => 'RFQ_LINE_ITEM'),
                '*'
            )
            ->where('rfl.RFL_RFQ_INTERNAL_REF_NO = ?', $rfqId)
            ->order('rfl.RFL_LINE_ITEM_NO ASC')
        ;
        $liRows = $this->_db->fetchAll($liSelect);
        if (empty($liRows)) {
            throw new Exception("Line items for RFQ " . $rfqId . " not found");
        }

        // insert new RFQ and get back its ID
        $newRfqId = $this->_db->nextSequenceId('RFQ_ID');
        $rfqRow[Shipserv_Rfq::COL_ID] = $newRfqId;
        unset($rfqRow['RFQ_EVENT_HASH']);
        $rfqInsertSql = $this->_getInsertSql(Shipserv_Rfq::TABLE_NAME, $rfqRow);

        $startTime = microtime(true);
        $result = $this->_db->query($rfqInsertSql);
        $elapsed += microtime(true) - $startTime;

        // remember the created RFQ ID so we can remove it when the test is finished
        $this->_createdRfqIds[] = $newRfqId;

        // update line item information with the new RFQ ID
        $liInsertSql = array();
        foreach ($liRows as $row) {
            $row['RFL_RFQ_INTERNAL_REF_NO'] = $newRfqId;
            $liInsertSql[] = $this->_getInsertSql('RFQ_LINE_ITEM', $row);
        }

        // here we can run multi-row insert or separate inserts per line item
        // do the latter as it is slower as we want a pessimistic time metric
        $startTime = microtime(true);
        foreach ($liInsertSql as $sql) {
            $result = $this->_db->query($sql);
        }
        $elapsed += microtime(true) - $startTime;

        // read the generated hash to compare it with what has been generated outside the trigger
        $selectRfqHash = new Zend_Db_Select($this->_db);
        $selectRfqHash
            ->from(
                Shipserv_Rfq::TABLE_NAME,
                '*'
            )
            ->where(Shipserv_Rfq::COL_ID . ' = ?', $newRfqId)
        ;

        $createdRow = $this->_db->fetchRow($selectRfqHash);

        if (in_array($createdRow[Shipserv_Rfq::COL_BUYER_ID], self::$_buyerProxyIds)) {
            // disrepancy might be normal for a forwarded RFQ which source RFQ is a legacy one with no hash calculated so its
            // NULL hash has been copied over
            // also possible for Pages RFQs since they require their inquiry to be cloned as well for in-trigger
            // buyer detection function to work
            // @todo: after the whole RFQ table has hashes, this would be no longer needed and cloning is extended here
            $expectedHash = null;

            return $elapsed;
        }

        $dbHash = strtoupper(bin2hex($createdRow[Shipserv_Rfq::COL_EVENT_HASH]));
        $this->assertEquals($expectedHash, $dbHash);

        return $elapsed;
    }

    /**
     * Re-implementation of DB stored function UTIL_REMOVE_NON_ASCII
     *
     * Removes non-ASCII characters from the given string
     */
    protected function _removeNonAscii($string) {
        return preg_replace('/[^(\x0D,\x0A,\x20-\x7F)]*/', '', $string);
    }

    /**
     * Re-implementation of DB stored function RFQ_CALC_EVENT_HASH
     *
     * Builds initial RFQ hash to be then recalculated with every next line item
     *
     * @param   array   $rfqRow
     * @param   string  $senderSignature    [BYO|SPB]:ID expected
     *
     * @return  string
     */
    protected function _calcRfqEventHash(array $rfqRow, $senderSignature) {
        return strtoupper(sha1(strtolower($this->_removeNonAscii(implode('~', array(
            trim($rfqRow[Shipserv_Rfq::COL_SUBJECT]),
            trim($rfqRow[Shipserv_Rfq::COL_VESSEL_NAME]),
            $senderSignature
        ))))));
    }

    /**
     * Re-implementation of DB stored function RFL_CALC_EVENT_HASH
     *
     * Recalculates existing RFQ hash with given line item details
     *
     * @param   string  $prevHash
     * @param   array   $lineItemRow
     *
     * @return string
     */
    protected function _calcLineItemEventHash($prevHash, array $lineItemRow) {
        return strtoupper(sha1(hex2bin($prevHash) . strtolower($this->_removeNonAscii(implode('~', array(
            trim($lineItemRow['RFL_PRODUCT_DESC']),
            trim($lineItemRow['RFL_CONFG_MODEL_NO']),
            trim($lineItemRow['RFL_CONFG_SERIAL_NO']),
            trim($lineItemRow['RFL_CONFG_DESC']),
        ))))));
    }

    /**
     * Checks if DB functions for calculating hashed for RFQs and their line items tally with the PHP implementation
     */
    public function testRfqHash() {
        $selectRfqSrc = new Zend_Db_Select($this->_db);
        $selectRfqSrc
            ->from(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                'rfq.*'
            )
            ->where('rfq.' . Shipserv_Rfq::COL_ID . ' = :' . self::RFQ_ID)
        ;

        $selectRfqHash = new Zend_Db_Select($this->_db);
        $selectRfqHash
            ->from(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                new Zend_Db_Expr('RAWTOHEX(RFQ_CALC_EVENT_HASH(
                    rfq.' . Shipserv_Rfq::COL_SUBJECT . ',
                    rfq.' . Shipserv_Rfq::COL_VESSEL_NAME . ',
                    :' . self::SENDER . '
                ))')
            )
            ->where('rfq.' . Shipserv_Rfq::COL_ID . ' = :' . self::RFQ_ID)
        ;

        $selectLiSrc = new Zend_Db_Select($this->_db);
        $selectLiSrc
            ->from(
                array('rfl' => 'RFQ_LINE_ITEM'),
                'rfl.*'
            )
            ->where('rfl.rfl_rfq_internal_ref_no = :' . self::RFQ_ID)
            ->order('rfl.rfl_line_item_no')
        ;

        $selectLiHash = new Zend_Db_Select($this->_db);
        $selectLiHash
            ->from(
                array('rfl' => 'rfq_line_item'),
                new Zend_Db_Expr('RAWTOHEX(RFL_CALC_EVENT_HASH(
                    HEXTORAW(:' . self::EVENT_HASH . '),
                    rfl.rfl_product_desc,
                    rfl.rfl_confg_model_no,
                    rfl.rfl_confg_serial_no,
                    rfl.rfl_confg_desc
                ))')
            )
            ->where('rfl.rfl_rfq_internal_ref_no = :' . self::RFQ_ID)
            ->where('rfl.rfl_line_item_no = :liNo')
        ;

        foreach (self::$_rfqData as $rfqId => $rfqInfo) {
            // test RFQ hash calculation - hash which includes RFQ properties only with no line item data
            // calculated in yet
            $rfqRow = $this->_db->fetchRow($selectRfqSrc, array(self::RFQ_ID => $rfqInfo[self::RFQ_ID]));

            $rfqHash = $this->_calcRfqEventHash($rfqRow, $rfqInfo[self::SENDER]);
            $dbHash  = $this->_db->fetchOne($selectRfqHash, $rfqInfo);

            $this->assertEquals($rfqHash, $dbHash);

            // now test line item hash calculation
            $liRows = $this->_db->fetchAll($selectLiSrc, array(self::RFQ_ID => $rfqInfo[self::RFQ_ID]));
            foreach ($liRows as $liRow) {
                $dbHash  = $this->_db->fetchOne($selectLiHash, array(
                    self::EVENT_HASH => $rfqHash,
                    self::RFQ_ID     => $liRow['RFL_RFQ_INTERNAL_REF_NO'],
                    'liNo'           => $liRow['RFL_LINE_ITEM_NO']
                ));
                $rfqHash = $this->_calcLineItemEventHash($rfqHash, $liRow);

                $this->assertEquals($rfqHash, $dbHash);
            }

            self::$_rfqData[$rfqId][self::EVENT_HASH] = $rfqHash;
            self::$_rfqData[$rfqId][self::LI_COUNT]   = count($liRows);
        }
    }

    /**
     * Re-inserts the RFQs defined in XML config given number of times, records average elapsed time, asserts hashes
     *
     * @depends testRfqHash
     */
    public function testInsertionPerformance() {
        global $iterationCount;
        if (strlen($iterationCount) == 0) {
            $iterationCount = 1;
        }

        for ($i = 0; $i < $iterationCount; $i++) {
            foreach (self::$_rfqData as $rfqId => $rfqInfo) {
                self::$_elapsedTrigger[$rfqInfo[self::RFQ_ID]] += $this->_cloneRfq($rfqInfo[self::RFQ_ID], $rfqInfo[self::EVENT_HASH]);
                // re-read the hash (a crutch, see _cloneRfq @todo comment)
                self::$_rfqData[$rfqId][self::EVENT_HASH] = $rfqInfo[self::EVENT_HASH];
            }
        }
    }

    /**
     * Rebuilds the hashes as if for legacy RFQs and compares them with hashes built by the trigger
     *
     * @depends testInsertionPerformance
     */
    public function testRebuildHashes() {
        $selectRebuildHash = new Zend_Db_Select($this->_db);
        $selectRebuildHash
            ->from(
                'DUAL',
                new Zend_Db_Expr('RAWTOHEX(RFQ_REBUILD_EVENT_HASH(:rfqId))')
            )
        ;

        foreach (self::$_rfqData as $rfqId => $rfqInfo) {
            $startTime = microtime(true);
            $rebuiltHash = $this->_db->fetchOne($selectRebuildHash, array('rfqId' => $rfqId));
            if (!is_null($rfqInfo[self::EVENT_HASH])) {
                $this->assertEquals($rfqInfo[self::EVENT_HASH], $rebuiltHash);
            }

            self::$_elapsedRebuild[$rfqId] = microtime(true) - $startTime;
        }
    }

    /**
     * Creates a CSV showing average time elapsed to clone all the RFQ specified in config
     *
     * @depends testRebuildHashes
     */
    public function testWritePerformanceReport() {
        global $reportFilename;
        if (strlen($reportFilename) === 0) {
            return;
        }

        if (($csv = fopen($reportFilename, 'w')) === false) {
            throw new Exception("Unable to write performance report into " . $reportFilename);
        }

        // report file headers
        fputcsv($csv, array(
            'Cloned RFQ ID',
            'Sender type',
            'Organisation ID',
            'Event hash',
            'Line item count',
            'Iterations',
            'Trigger time total',
            'Average per LI',
            'Hash rebuild time'
        ));

        global $iterationCount;
        foreach (self::$_elapsedTrigger as $rfqId => $elapsed) {
            $rfqInfo = self::$_rfqData[$rfqId];
            $senderInfo = explode(':', $rfqInfo[self::SENDER]);

            fputcsv($csv, array(
                $rfqId,
                $senderInfo[0],
                $senderInfo[1],
                $rfqInfo[self::EVENT_HASH],
                $rfqInfo[self::LI_COUNT],
                $iterationCount,
                $elapsed,
                $elapsed / $iterationCount,
                self::$_elapsedRebuild[$rfqId]
            ));
        }

        fclose($csv);

        print PHP_EOL . "Performance report wrtitten to " . $reportFilename . PHP_EOL;
    }
}