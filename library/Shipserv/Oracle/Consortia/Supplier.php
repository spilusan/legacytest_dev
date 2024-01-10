<?php
/**
 * Implements access to the Oracle table with Consortia supplier agreements
 *
 * @author  Yuriy Akopov
 * @date    2017-11-30
 * @story   DEV-1170
 */
class Shipserv_Oracle_Consortia_Supplier extends Shipserv_Oracle_Consortia_Abstract
{
    const
        TABLE_NAME = 'CONSORTIA_SUPPLIER',

        COL_ID              = 'CSB_INTERNAL_REF_NO',
        COL_SALESFORCE_ID   = 'CSB_SF_SOURCE_ID',

        COL_SUPPLIER_ID     = 'CSB_SPB_BRANCH_CODE',
        COL_CONSORTIA_ID    = 'CSB_CON_INTERNAL_REF_NO',

        COL_RATE_TYPE       = 'CSB_RATE_TYPE',
        COL_RATE_VALUE      = 'CSB_RATE_VALUE',

        COL_VALID_FROM      = 'CSB_VALID_FROM',
        COL_VALID_TILL      = 'CSB_VALID_TILL',

        COL_CREATED_BY      = 'CSB_CREATED_BY',
        COL_CREATED_DATE    = 'CSB_CREATED_DATE',
        COL_UPDATED_BY      = 'CSB_UPDATED_BY',
        COL_UPDATED_DATE    = 'CSB_UPDATED_DATE',

        SEQUENCE_NAME = 'SQ_CSB_INTERNAL_REF_NO'
    ;

    const
        RATE_PO   = 'ORD',
        RATE_UNIT = 'UNIT'
    ;

    const
        // precision of the Oracle rate value field: NUMBER(12, 8)
        // this information can be dynamically read from Oracle meta data, but it seems like an overkill here
        ORACLE_RATE_PRECISION_WHOLE   = 4,
        ORACLE_RATE_PRECISION_DECIMAL = 8
    ;

    /**
     * Returns the list of table fields to select when a record is requested
     *
     * @param string $prefix
     *
     * @return array
     */
    public static function getSelectFieldList($prefix = 'csb')
    {
        return array(
            self::COL_ID              => $prefix . '.' . self::COL_ID,
            self::COL_SALESFORCE_ID   => $prefix . '.' . self::COL_SALESFORCE_ID,

            self::COL_SUPPLIER_ID     => $prefix . '.' . self::COL_SUPPLIER_ID,
            self::COL_CONSORTIA_ID    => $prefix . '.' . self::COL_CONSORTIA_ID,

            self::COL_RATE_TYPE       => $prefix . '.' . self::COL_RATE_TYPE,
            self::COL_RATE_VALUE      => $prefix . '.' . self::COL_RATE_VALUE,

            self::COL_VALID_FROM      => new Zend_Db_Expr('TO_CHAR(' . $prefix . '.' . self::COL_VALID_FROM . ", 'YYYY-MM-DD HH24:MI:SS')"),
            self::COL_VALID_TILL      => new Zend_Db_Expr('TO_CHAR(' . $prefix . '.' . self::COL_VALID_TILL . ", 'YYYY-MM-DD HH24:MI:SS')"),

            self::COL_CREATED_BY      => $prefix . '.' . self::COL_CREATED_BY,
            self::COL_CREATED_DATE    => new Zend_Db_Expr('TO_CHAR(' . $prefix . '.' . self::COL_CREATED_DATE . ", 'YYYY-MM-DD HH24:MI:SS')"),
            self::COL_UPDATED_BY      => $prefix . '.' . self::COL_UPDATED_BY,
            self::COL_UPDATED_DATE    => new Zend_Db_Expr('TO_CHAR(' . $prefix . '.' . self::COL_UPDATED_DATE . ", 'YYYY-MM-DD HH24:MI:SS')")
        );
    }

    /**
     * Validates values in the record and converts dates to objects
     *
     * @param   array   $record
     * @param   string  $origin     @todo: when records are represented by different classes would no longer be needed
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Exception
     */
    public static function prepareRecord(array $record, $origin)
    {
        if (!in_array($origin, array('db', 'sf'))) {
            throw new Exception("Supplier agreement record origing is not valid for conversion");
        }

        foreach ($record as $field => $value) {
            switch ($field) {
                case self::COL_ID:
                    // @todo: lazy check here yet without checking the actual record presence
                    if (!(is_null($value) or is_numeric($value))) {
                        throw new Myshipserv_Consortia_Validation_Exception(
                            "Supplier agreement DB ID " . $value . " is not valid", $record[self::COL_SALESFORCE_ID]
                        );
                    }

                    break;

                case self::COL_SUPPLIER_ID:
                    $supplier = Shipserv_Supplier::getInstanceById($value, null, true);
                    if (strlen($supplier->tnid) === 0) {
                        throw new Myshipserv_Consortia_Validation_Exception(
                            "Agreement supplier ID " . $value . " not found", $record[self::COL_SALESFORCE_ID]
                        );
                    }
                    break;

                case self::COL_CONSORTIA_ID:
                    Shipserv_Oracle_Consortia::getRecord($value);
                    break;

                case self::COL_SALESFORCE_ID:
                    if (!is_null($value) and !Myshipserv_Salesforce_Base::validateSalesForceId($value, true)) {
                        throw new Myshipserv_Consortia_Db_Exception(
                            "Supplier agreement Salesforce ID " . $value . " is not valid",
                            $record[self::COL_SALESFORCE_ID]
                        );
                    }
                    break;

                case self::COL_RATE_TYPE:
                    switch ($origin) {
                        case 'db':
                            Myshipserv_Salesforce_Consortia_Client_SupplierAgreement::getSfRateTypeFromDb($value);
                            break;

                        case 'sf':
                            Myshipserv_Salesforce_Consortia_Client_SupplierAgreement::getDbRateTypeFromSf($value);
                            break;

                        default:
                            throw new Exception(
                                "Unexpected record origin - should never happen as it has been just checked really"
                            );
                    }

                    break;

                case self::COL_RATE_VALUE:
                    if (!filter_var($value, FILTER_VALIDATE_FLOAT)) {
                        throw new Myshipserv_Consortia_Validation_Exception(
                            "Consortia agreement rate " . $value . " is not valid", $record[self::COL_SALESFORCE_ID]
                        );
                    }

                    if ($value < 0) {
                        throw new Myshipserv_Consortia_Validation_Exception(
                            "Consortia agreement rate " . $value . " is negative", $record[self::COL_SALESFORCE_ID]
                        );
                    }

                    // validate precision of the whole and decimal parts
                    if (
                        (strlen((int) floor($value)) > self::ORACLE_RATE_PRECISION_WHOLE) or
                        (
                            (strlen($value - floor($value)) - strlen('0.')) >
                            self::ORACLE_RATE_PRECISION_DECIMAL
                        )
                    ) {
                        throw new Myshipserv_Consortia_Validation_Exception(
                            "Consortia agreement rate " . $value . " precision is not supported",
                            $record[self::COL_SALESFORCE_ID]
                        );
                    }

                    break;

                case self::COL_CREATED_BY:
                case self::COL_UPDATED_BY:
                    // @todo: silenced because Admin Gateway puts strings there instead of user IDs
                    /*
                    if (!is_null($value)) {
                        try {
                            Shipserv_User::getInstanceById($value);
                        } catch (Shipserv_Oracle_User_Exception_NotFound $e) {
                            throw new Myshipserv_Consortia_Db_Exception(
                                $record[self::COL_SALESFORCE_ID],
                                "User ID " . $value . " not found"
                            );
                        }
                    }
                    */
                    break;

                case self::COL_VALID_FROM:
                    try {
                        $record[$field] = self::validateDate($value, false);

                    } catch (Myshipserv_Consortia_Validation_Exception $e) {
                        $e->setSalesforceId($record[self::COL_SALESFORCE_ID]);
                        throw $e;
                    }

                    break;

                case self::COL_VALID_TILL:
                case self::COL_CREATED_DATE:
                case self::COL_UPDATED_DATE:
                    try {
                        $record[$field] = self::validateDate($value, true);

                    } catch (Myshipserv_Consortia_Validation_Exception $e) {
                        $e->setSalesforceId($record[self::COL_SALESFORCE_ID]);
                        throw $e;
                    }

                    break;

                default:
                    throw new Myshipserv_Consortia_Validation_Exception(
                        "Unexpected field " . $field . " found", $record[self::COL_SALESFORCE_ID]
                    );
            }
        }

        self::validateTimeInterval($origin, $record);

        return $record;
    }

    /**
     * Checks file interval FROM and TILL fields for contradictions
     *
     * @param   string  $origin
     * @param   array   $record
     *
     * @throws Myshipserv_Consortia_Validation_Exception
     */
    protected static function validateTimeInterval($origin, array $record)
    {
        if (!is_null($record[self::COL_VALID_TILL])) {
            switch ($origin) {
                // in the DB end dates are exclusive so start and end dates cannot be the same
                case 'db':
                    if ($record[self::COL_VALID_TILL] <= $record[self::COL_VALID_FROM]) {
                        throw new Myshipserv_Consortia_Validation_Exception(
                            "Agreement date interval is not valid", $record[self::COL_SALESFORCE_ID]
                        );
                    }
                    break;

                // in Salesforce end date are inclusive, so start and end dates can be the same
                case 'sf':
                    if ($record[self::COL_VALID_TILL] < $record[self::COL_VALID_FROM]) {
                        throw new Myshipserv_Consortia_Validation_Exception(
                            "Agreement date interval is not valid", $record[self::COL_SALESFORCE_ID]
                        );
                    }
                    break;

                default:
                    // we have validated above this is never going to be the case
            }
        }
    }

    /**
     * Creates a new supplier agreement which is in the future
     *
     * @param   array       $sfRecord
     * @param   DateTime    $syncDate
     * @param   int|null    $userId
     *
     * @return  int|null
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Shipserv_Helper_Database_Exception
     * @throws  Exception
     */
    public static function addFutureAgreement(array $sfRecord, DateTime $syncDate, $userId = null)
    {
        $dbRecord = Myshipserv_Salesforce_Consortia_Client_SupplierAgreement::getDbRecordFromSf($sfRecord);

        $today = Myshipserv_Salesforce_Consortia_Client_SupplierAgreement::removeTime($syncDate);
        $existingDbRecords = self::getFutureAgreements($sfRecord[self::COL_SUPPLIER_ID], $today);

        foreach ($existingDbRecords as $exDbRecord) {

            if (self::compareRecords($dbRecord, $exDbRecord, array(self::COL_ID))) {
                return null;    // agreement already exists in the database
            }
        }

        $createdId = self::addNewAgreement($dbRecord, $syncDate, $userId);

        return $createdId;
    }

    /**
     * Marks the agreement as expired
     *
     * @param   array           $sfRecord
     * @param   DateTime        $syncDate
     * @param   int             $userId
     *
     * @return  int
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Myshipserv_Consortia_Exception
     * @throws  Shipserv_Helper_Database_Exception
     * @throws  Zend_Db_Adapter_Exception
     * @throws  Exception
     */
    public static function expireOngoingAgreement(array $sfRecord, DateTime $syncDate, $userId = null)
    {
        $salesforceId = $sfRecord[self::COL_SALESFORCE_ID];

        $dbRecord = Myshipserv_Salesforce_Consortia_Client_SupplierAgreement::getDbRecordFromSf($sfRecord);
        $dbValidTill = $dbRecord[self::COL_VALID_TILL];

        if (!($dbValidTill instanceof DateTime)) {
            throw new Myshipserv_Consortia_Validation_Exception(
                "Expiration date for supplier agreement " . $salesforceId ." is not specified", $salesforceId
            );
        }

        $existingDbRecords = self::getBySalesforceId($salesforceId);
        if (empty($existingDbRecords)) {
            throw new Myshipserv_Consortia_Db_Exception(
                "No supplier agreement for " . $salesforceId . " to expire", $salesforceId
            );
        }

        $dbRecordsToExpire = array();

        foreach ($existingDbRecords as $exDbRec) {
            // looking for database records where all the field values except for DB ID and expiration date are the
            // same as in the Salesforce record expiring today
            if (self::compareRecords($exDbRec, $dbRecord, array(self::COL_ID, self::COL_VALID_TILL))) {
                if (
                    is_null($exDbRec[self::COL_VALID_TILL]) or
                    $exDbRec[self::COL_VALID_TILL] >= $dbValidTill
                ) {
                    // we expect only one record like this, but will collect all the matching ones to report later, is so
                    $dbRecordsToExpire[] = $exDbRec;

                } else {
                    throw new Myshipserv_Consortia_Db_Exception(
                        "Agreement record " . $exDbRec[self::COL_ID] . " for Salesforce ID " . $salesforceId .
                        " has already expired " . $dbValidTill->format('Y-m-d'),
                        $salesforceId
                    );
                }
            }
        }

        if (count($dbRecordsToExpire) === 0) {
            throw new Myshipserv_Consortia_Db_Exception(
                "No relationships to expire for " . $salesforceId, $salesforceId
            );

        } else if (count($dbRecordsToExpire) > 1) {
            $ids = array();
            foreach ($dbRecordsToExpire as $recExp) {
                $ids[] = $recExp[self::COL_ID];
            }

            throw new Myshipserv_Consortia_Db_Exception(
                "Ambiguous relationships to expire for " . $salesforceId . ": " . implode(", ", $ids),
                $salesforceId
            );
        }

        $record = $dbRecordsToExpire[0];

        if ($record[self::COL_VALID_TILL] == $dbValidTill) {
            // no need to update the record, has been already synchronised earlier today
            return null;
        }

        $db = self::getDb();
        $updatedCount = $db->update(
            self::TABLE_NAME,
            array(
                self::COL_VALID_TILL   => new Zend_Db_Expr(Shipserv_Helper_Database::getOracleDateExpr($dbValidTill)),

                self::COL_UPDATED_BY   => 'synchronisation' . $userId,
                // update is actually re-set by the trigger, but I believe this needs to change in order to be same
                // for all record updated during the same synchronisation
                self::COL_UPDATED_DATE => new Zend_Db_Expr(
                    Shipserv_Helper_Database::getOracleDateExpr($syncDate)
                )
            ),
            $db->quoteInto(self::COL_ID . ' = ?', $record[self::COL_ID])
        );

        if ($updatedCount !== 1) {
            throw new Myshipserv_Consortia_Db_Exception(
                $updatedCount . " supplier agreement records updated, only 1 expected", $salesforceId
            );
        }

        return $record[self::COL_ID];
    }

    /**
     * Locates ongoing relationship in the database, if it exists
     *
     * @param   array       $sfRecord
     *
     * @return  int
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Exception
     */
    public static function findOngoingAgreements(array $sfRecord)
    {
        $salesforceId = $sfRecord[self::COL_SALESFORCE_ID];
        $dbRecord = Myshipserv_Salesforce_Consortia_Client_SupplierAgreement::getDbRecordFromSf($sfRecord);

        $existingRecords = self::getBySalesforceId($salesforceId);

        if (empty($existingRecords)) {
            throw new Myshipserv_Consortia_Db_Exception(
                "Ongoing supplier agreement for " . $salesforceId . " not found in the database", $salesforceId
            );
        }

        foreach ($existingRecords as $exDbRecord) {
            if (self::compareRecords($dbRecord, $exDbRecord, self::COL_ID)) {
                // full match, no need to change anything
                return $exDbRecord[self::COL_ID];
            }
        }

        return null;
    }

    /**
     * Loads all supplier agreements from the database
     *
     * @param   array|int   $supplierIds
     * @param   DateTime    $today
     *
     * @return  array
     * @throws  Shipserv_Helper_Database_Exception
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Exception
     */
    protected static function getFutureAgreements($supplierIds, DateTime $today)
    {
        if (!is_array($supplierIds)) {
            $supplierIds = array($supplierIds);
        }

        $db = self::getDb();

        // get all agreements for the given suppliers set up entirely in the future
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('csb' => self::TABLE_NAME),
                self::getSelectFieldList('csb')
            )
            ->where('csb.' . self::COL_VALID_FROM . ' > ' . Shipserv_Helper_Database::getOracleDateExpr($today))
            ->where('csb.' . self::COL_SUPPLIER_ID . ' IN (?)', $supplierIds);

        $records = $select->getAdapter()->fetchAll($select);

        $preparedRecords = array();
        foreach ($records as $rec) {
            $preparedRecords[] = self::prepareRecord($rec, 'db');
        }

        return $preparedRecords;
    }

    /**
     * Removes all future relationships that are no longer listed as future relationships in Salesforce
     * There are no commitments to future agreements so they can be removed physically
     *
     * @param   array       $supplierIds
     * @param   array       $sfRecords
     * @param   DateTime    $today
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Exception
     */
    public static function deleteUnusedFutureAgreements(array $supplierIds, array $sfRecords, DateTime $today)
    {
        // get database agreements that are defined in the future
        $dbRecords = self::getFutureAgreements($supplierIds, $today);

        // at this point we have read all future agreements from the database
        // now lets delete all of them that don't have a copy just read from Salesforce

        $deletedIds = array();

        foreach ($dbRecords as $dbRec) {
            // look if the database record can be found in the list of Salesforce records
            foreach ($sfRecords as $sfRec) {
                $dbRecFromSf = Myshipserv_Salesforce_Consortia_Client_SupplierAgreement::getDbRecordFromSf($sfRec);

                if (self::compareRecords($dbRec, $dbRecFromSf, self::COL_ID)) {
                    // a copy exists in Salesforce - no need to delete this record, still actual
                    continue(2);
                }
            }

            // no copy of the record read from Salesforce, so the database record needs to be deleted
            $db = Shipserv_Helper_Database::getDb();
            $db->delete(
                self::TABLE_NAME,
                $db->quoteInto(self::COL_ID . ' = ?', $dbRec[self::COL_ID])
            );

            $deletedIds[] = $dbRec[self::COL_ID];
        }

        return $deletedIds;
    }

    /**
     * Returns all the records associated with the provided Salesforce ID, most recent first in the array
     *
     * @param   string  $salesforceId
     * @todo: add constraints by supplier and consortia perhaps?
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Exception
     */
    public static function getBySalesforceId($salesforceId)
    {
        $select = new Zend_Db_Select(self::getDb());
        $select
            ->from(
                array('csb' => self::TABLE_NAME),
                self::getSelectFieldList('csb')
            )
            ->where('csb.' . self::COL_SALESFORCE_ID . ' = ?', $salesforceId)
            ->order('csb.' . self::COL_VALID_FROM . ' DESC');

        $records = $select->getAdapter()->fetchAll($select);

        $preparedRecords = array();
        foreach ($records as $rec) {
            $preparedRecords[] = self::prepareRecord($rec, 'db');
        }

        return $preparedRecords;
    }

    /**
     * Inserts a new record in the table after validating the data to be inserted
     *
     * @param   array       $record
     * @param   DateTime    $syncDate
     * @param   int         $userId
     *
     * @return  int
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Shipserv_Helper_Database_Exception
     * @throws  Exception
     */
    protected static function addNewAgreement(array $record, DateTime $syncDate, $userId = null)
    {
        // the record is already expected to be prepared
        // $record = self::prepareRecord($record, 'db');

        // cannot insert with a predefined ID
        if (!is_null($record[self::COL_ID])) {
            throw new Myshipserv_Consortia_Db_Exception(
                "Unable to insert a new supplier agreement with an pre-defined ID " . $record[self::COL_ID],
                $record[self::COL_SALESFORCE_ID]
            );
        }

        // make sure it doesn't exist already
        $records = self::getBySalesforceId($record[self::COL_SALESFORCE_ID]);
        foreach ($records as $existingRec) {
            if (self::compareRecords($record, $existingRec, self::COL_ID)) {
                return null;
            }
        }

        // constrains to detect overlapping time intervals
        // first, overlapping records need to end either in unrestricted future (NULL), or
        // to end after the current records starts
        $overlappingDateConstraints = array(
            '(' .
            implode(
                ' OR ',
                array(
                    self::COL_VALID_TILL . ' > ' . Shipserv_Helper_Database::getOracleDateExpr($record[self::COL_VALID_FROM]),
                    self::COL_VALID_TILL . ' IS NULL'
                )
            )
            . ')'
        );

        // now if the new interval stretches into limitless future, the condition above would always be true
        // otherwise we need to check if it starts before the new record ends
        $newValidTill = self::getOracleDate($record[self::COL_VALID_TILL]);

        if (!is_null($newValidTill)) {
            $overlappingDateConstraints[] = self::COL_VALID_FROM . ' <= ' . $newValidTill;
        }

        // make sure it doesn't overlap with records that are already in the database
        $select = new Zend_Db_Select(self::getDb());
        $select
            ->from(
                array('csb' => self::TABLE_NAME),
                self::COL_ID
            )
            ->where(self::COL_SUPPLIER_ID . ' = ?', $record[self::COL_SUPPLIER_ID])
            ->where(self::COL_CONSORTIA_ID . ' = ?', $record[self::COL_CONSORTIA_ID])
            ->where(implode(' AND ', $overlappingDateConstraints));

        $overlappingIds = $select->getAdapter()->fetchCol($select);
        if (!empty($overlappingIds)) {
            throw new Myshipserv_Consortia_Db_Exception(
                "Unable to insert a supplier agreement, " . count($overlappingIds) . " overlapping ones found",
                $record[self::COL_SALESFORCE_ID]
            );
        }

        // if we are here, validation is passed and it's fine to create a new record
        $dateFromStr = self::getOracleDate($record[self::COL_VALID_TILL]);
        self::getDb()->insert(
            self::TABLE_NAME,
            array(
                self::COL_SUPPLIER_ID     => $record[self::COL_SUPPLIER_ID],
                self::COL_CONSORTIA_ID    => $record[self::COL_CONSORTIA_ID],
                self::COL_SALESFORCE_ID   => $record[self::COL_SALESFORCE_ID],

                self::COL_RATE_TYPE       => $record[self::COL_RATE_TYPE],
                self::COL_RATE_VALUE      => $record[self::COL_RATE_VALUE],

                self::COL_VALID_FROM      => new Zend_Db_Expr(self::getOracleDate($record[self::COL_VALID_FROM])),
                self::COL_VALID_TILL      => is_null($dateFromStr) ? null : new Zend_Db_Expr($dateFromStr),

                self::COL_CREATED_BY      => 'synchronisation' . $userId, // @todo: back to $userId when DB schema is sorted,
                self::COL_CREATED_DATE    => new Zend_Db_Expr(self::getOracleDate($syncDate)),

                self::COL_UPDATED_BY      => 'synchronisation' . $userId, // $userId,
                self::COL_UPDATED_DATE    => new Zend_Db_Expr(self::getOracleDate($syncDate))
            )
        );

        return self::getLastInsertedId();
    }
}