<?php
/**
 * A client managing Consortia buyer supplier relationships synchronisation from Oracle to Salesforce
 *
 * @author  Yuriy Akopov
 * @date    2018-01-02
 * @story   DEV-1602
 */
class Myshipserv_Salesforce_Consortia_Client_BuyerSupplier extends Myshipserv_Salesforce_Consortia_Client_Abstract
{
    const
        RELATIONSHIP_TYPE = 'Consortia';

    const
        ID_CACHE_CONSORTIA = 'consortia',
        ID_CACHE_SUPPLIER  = 'supplier';

    /**
     * ID of the consortia which relationships are synchronised
     *
     * @var int
     */
    protected $consortiaId = null;

    /**
     * Returns ID of supplier type in Salesforce Account object
     *
     * @todo: it could be that this ID can be learned dynamically from Salesforce rather than set in Pages config
     *
     * @return  string
     */
    public static function getSupplierRecordTypeId()
    {
        $salesforceSettings = Myshipserv_Config::getSalesForceCredentials();
        return $salesforceSettings->supplierRecordTypeId;
    }

    /**
     * Returns ID of buyer type in Salesforce Account object
     *
     * @todo: it could be that this ID can be learned dynamically from Salesforce rather than set in Pages config
     *
     * @return  string
     */
    public static function getBuyerRecordTypeId()
    {
        $salesforceSettings = Myshipserv_Config::getSalesForceCredentials();
        return $salesforceSettings->buyerRecordTypeId;
    }

    /**
     * Initialises the synchronisation session
     *
     * @param   int                     $consortiaId
     * @param   Myshipserv_Logger_File  $logger
     * @param   Shipserv_User           $user
     *
     * @throws  Myshipserv_Consortia_Db_Exception
     */
    public function __construct($consortiaId, Myshipserv_Logger_File $logger, Shipserv_User $user = null)
    {
        // check if the ID provided for consortia is valid
        Shipserv_Oracle_Consortia::getRecord($consortiaId);
        $this->consortiaId = $consortiaId;

        parent::__construct($logger, $user);
    }

    /**
     * Returns Salesforce ID of the provided entity instance in the database, if it exists
     *
     * @param   int     $dbId
     * @param   string  $entityType
     *
     * @return  string
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     */
    protected function getSfIdFromDb($dbId, $entityType)
    {
        if (array_key_exists($entityType, self::$salesforceIdCache)) {
            if (array_key_exists($dbId, self::$salesforceIdCache[$entityType])) {
                return self::$salesforceIdCache[$entityType][$dbId];
            }
        } else {
            self::$salesforceIdCache[$entityType] = array();
        }

        if (!is_numeric($dbId)) {
            throw new Myshipserv_Consortia_Validation_Exception($dbId . " is not a valid TNID");
        }

        $soql = "
            SELECT
                Id
            FROM
                Account
            WHERE
                TNID__c = " . $dbId . "
        ";

        switch ($entityType) {
            case self::ID_CACHE_CONSORTIA:
                $recordTypeId = null;
                $type = Myshipserv_Salesforce_Consortia_Client_Consortia::ACCOUNT_TYPE_CONSORTIA;
                break;

            case self::ID_CACHE_SUPPLIER:
                $recordTypeId = self::getSupplierRecordTypeId();
                $type = null;
                break;

            default:
                throw new Myshipserv_Consortia_Validation_Exception("Unknown entity type " . $entityType);
        }

        if (!is_null($recordTypeId)) {
            $soql .= "
                AND RecordTypeId = '" . $recordTypeId . "'
            ";
        }

        if (!is_null($type)) {
            $soql .= "
                AND Type = '" . $type . "'
            ";
        }

        $operation = function () use ($soql) {
            return $this->querySalesforce($soql);
        };

        $response = $this->runSalesforceOperation(
            $operation,
            "Retrieve Salesforce ID for " . $entityType . " ID " . $dbId
        );

        if ($response->size !== 1) {
            throw new Myshipserv_Salesforce_Consortia_Exception(
                "Failed to find " . $entityType . " ID " . $dbId . " in Salesforce"
            );
        }

        return (self::$salesforceIdCache[$entityType][$dbId] = $response->records[0]->Id);
    }


    /**
     * Retrieves records existing for the consortia in question in Salesforce
     *
     * @return  array
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     * @throws  Myshipserv_Consortia_Validation_Exception
     */
    protected function getSalesforceBuyerSupplierRecords()
    {
        $consortiaSfId = $this->getSfIdFromDb(
            $this->consortiaId, self::ID_CACHE_CONSORTIA
        );

        $operation = function () use ($consortiaSfId) {

            return $this->querySalesforce(
                "
                SELECT
                    Id,
                    BuyerTNID__c,
                    Supplier__c,
                    Status__c,
                    EffectiveStartDate__c,
                    EffectiveEndDate__c,
                    TypeofRelationship__c
                FROM
                    Supplier_to_Buyer_Relationship__c
                WHERE
                    Consortia__c = '" . $consortiaSfId . "'                    
                    AND TypeofRelationship__c = '" . self::RELATIONSHIP_TYPE . "'
                "
            );
        };

        $response = $this->runSalesforceOperation(
            $operation,
            "Retrieve existing buyer supplier relationships for consortia " . $this->consortiaId
        );

        if ($response->size === 0) {
            return array();
        }

        return $response->records;
    }

    /**
     * Retrieves records existing for the consortia in question in the database
     *
     * @return  array
     * @throws  Exception
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Myshipserv_Consortia_Validation_Exception
     */
    protected function getDbBuyerSupplierRecords()
    {
        $dbRecords = Shipserv_Oracle_Consortia_BuyerSupplier::getRelationshipsForConsortia($this->consortiaId);

        $preparedRecords = array();
        foreach ($dbRecords as $dbRec) {
            $preparedRecords[] = Shipserv_Oracle_Consortia_BuyerSupplier::prepareRecord($dbRec);
        }

        return $preparedRecords;
    }

    /**
     * Returns Salesforce record what matches the given database record or null if it is not found
     *
     * @param   array   $dbRecord
     * @param   array   $sfRecords
     *
     * @return  stdClass|null
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     */
    protected function findMatchingSalesforceRecord($dbRecord, $sfRecords)
    {
        $foundRecord = null;

        foreach ($sfRecords as $sfRec) {
            // compare buyer and supplier IDs (no need to compare consortia ID since that was a part of the query
            // as not all the buyers are found in Salesforce, TNIDs are compared
            if ($sfRec->BuyerTNID__c == $dbRecord[Shipserv_Oracle_Consortia_BuyerSupplier::COL_BUYER_ID]) {
                // for suppliers, compare Salesforce IDs
                $sfSupplierId = $this->getSfIdFromDb(
                    $dbRecord[Shipserv_Oracle_Consortia_BuyerSupplier::COL_SUPPLIER_ID], self::ID_CACHE_SUPPLIER
                );
                if ($sfRec->Supplier__c === $sfSupplierId) {

                    // compare start and then the end dates
                    $sfStartDate = new DateTime($sfRec->EffectiveStartDate__c);
                    if ($sfStartDate == $dbRecord[Shipserv_Oracle_Consortia_BuyerSupplier::COL_VALID_FROM]) {

                        // date intervals are inclusive in Salesforce
                        $sfEndDate = self::getDbEndDateFromSf($sfRec->EffectiveEndDate__c);
                        if ($sfEndDate == $dbRecord[Shipserv_Oracle_Consortia_BuyerSupplier::COL_VALID_TILL]) {

                            if (is_null($foundRecord)) {
                                $foundRecord = $sfRec;
                            } else {
                                throw new Myshipserv_Consortia_Validation_Exception(
                                    "Duplicate matching buyer supplier relationship record " . $sfRec->Id .
                                    " found in Salesforce",
                                    $sfRec->Id
                                );
                            }
                        }
                    }
                }
            }
        }

        if ($foundRecord) {
            return $foundRecord;
        }

        return null;
    }

    /**
     * Deletes buyer supplier relationship records from Salesforce
     *
     * @param   array   $sfRecords
     * @return array
     * @throws Myshipserv_Salesforce_Consortia_Exception
     */
    protected function deleteBuyerSupplierRelationships($sfRecords)
    {
        if (empty($sfRecords)) {
            return array();
        }

        $salesforceIds = array();
        foreach ($sfRecords as $sfRec) {
            $salesforceIds[] = $sfRec->Id;
        }

        $this->logger->log("Attempting to delete buyer supplier relationships " . implode(", ", $salesforceIds));

        $operation = function () use ($salesforceIds) {
            return $this->runBatchOperation(
                array($this->sfConnection, 'delete'), $salesforceIds
            );
        };

        $response = $this->runSalesforceOperation($operation, "Delete buyer supplier relationship records");
        $this->checkSalesforceResponseForErrors($response, $sfRecords, "Error while inserting consortia");

        return $salesforceIds;
    }

    /**
     * Inserts new buyer supplier relationship records in Salesforce
     *
     * @param   array   $dbRecords
     *
     * @return  array
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Zend_Db_Adapter_Exception
     */
    protected function addBuyerSupplierRelationships($dbRecords)
    {
        if (empty($dbRecords)) {
            return array();
        }

        $sfRecords = array();
        $dbIds = array();
        foreach ($dbRecords as $dbRec) {
            $sfRec = new stdClass();
            $sfRec->TypeofRelationship__c = self::RELATIONSHIP_TYPE;

            $sfRec->Consortia__c = $this->getSfIdFromDb(
                $dbRec[Shipserv_Oracle_Consortia_BuyerSupplier::COL_CONSORTIA_ID],
                self::ID_CACHE_CONSORTIA
            );
            $sfRec->BuyerTNID__c =  $dbRec[Shipserv_Oracle_Consortia_BuyerSupplier::COL_BUYER_ID];
            $sfRec->Supplier__c = $this->getSfIdFromDb(
                $dbRec[Shipserv_Oracle_Consortia_BuyerSupplier::COL_SUPPLIER_ID], self::ID_CACHE_SUPPLIER
            );

            $sfRec->EffectiveStartDate__c = $dbRec[Shipserv_Oracle_Consortia_BuyerSupplier::COL_VALID_FROM]->format('Y-m-d');

            $sfEndDate = self::getSfEndDateFromDb($dbRec[Shipserv_Oracle_Consortia_BuyerSupplier::COL_VALID_TILL]);
            if (!is_null($sfEndDate)) {
                $sfRec->EffectiveEndDate__c = $sfEndDate->format('Y-m-d');
            }

            $sfRecords[] = $sfRec;
            $dbIds[] = $dbRec[Shipserv_Oracle_Consortia_BuyerSupplier::COL_ID];
        }

        $this->logger->log("Attempting to add buyer supplier relationships " . implode(", ", $dbIds));

        $operation = function () use ($sfRecords) {
            return $this->runBatchOperation(
                array($this->sfConnection, 'create'), $sfRecords, 'Supplier_to_Buyer_Relationship__c'
            );
        };

        $response = $this->runSalesforceOperation($operation, "Create buyer supplier relationship records");
        $this->checkSalesforceResponseForErrors($response, $sfRecords, "Error while inserting consortia");

        $result = array();
        foreach ($dbIds as $dbIndex => $dbId) {
            $result[$dbId] = $response[$dbIndex]->id;
        }

        Shipserv_Oracle_Consortia_BuyerSupplier::updateSalesforceIds($result);

        return $result;
    }

    /**
     * Starts synchronisation process
     *
     * @return  array
     * @throws  Exception
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Myshipserv_Consortia_Validation_Exception
     */
    public function sync()
    {
        $dbRecords = $this->getDbBuyerSupplierRecords();
        $sfRecords = $this->getSalesforceBuyerSupplierRecords();

        // exclude records already in sync and figure out what to delete and to add (no updates in this workflow)

        $toAdd = array();
        foreach ($dbRecords as $dbIndex => $dbRec) {
            if ($matchingSfRec = $this->findMatchingSalesforceRecord($dbRec, $sfRecords)) {
                // DB relationships record has a matching Salesforce record, no need to change anything
                unset($dbRecords[$dbIndex]);

                foreach (array_keys($sfRecords) as $sfIndex) {
                    if ($sfRecords[$sfIndex]->Id === $matchingSfRec->Id) {
                        unset($sfRecords[$sfIndex]);
                    }
                }
            } else {
                // record is missing from Salesforce
                $toAdd[] = $dbRec;
            }
        }

        // anything left in $dbRecords needs to be added
        $added = $this->addBuyerSupplierRelationships($dbRecords);

        // anything left in $sfRecords is missing from the DB and needs to be deleted
        $deleted = $this->deleteBuyerSupplierRelationships($sfRecords);

        return array(
            'added'   => $added,
            'deleted' => $deleted
        );
    }
}