<?php
/**
 * A client managing Consortia supplier agreements synchronisation from Salesforce to Oracle
 *
 * @author  Yuriy Akopov
 * @date    2017-12-18
 * @story   DEV-1170
 */
class Myshipserv_Salesforce_Consortia_Client_SupplierAgreement extends Myshipserv_Salesforce_Consortia_Client_Abstract
{
    const
        RATE_PO   = 'Consortia PO Fee',
        RATE_UNIT = 'Consortia Per Unit Fee'
    ;

    /**
     * Matching map for rate types between Salesforce and database supplier agreement representation
     *
     * @var array
     */
    protected static $rateTypeMap = array(
        self::RATE_PO   => Shipserv_Oracle_Consortia_Supplier::RATE_PO,
        self::RATE_UNIT => Shipserv_Oracle_Consortia_Supplier::RATE_UNIT
    );

    /**
     * List of supplier IDs which agreements to synchronise
     *
     * @var array
     */
    protected $supplierIds = array();

    /**
     * Returns database rate type string for the provided Salesforce type
     *
     * @param   string  $sfRateType
     *
     * @return  string
     * @throws  Myshipserv_Consortia_Exception
     */
    public static function getDbRateTypeFromSf($sfRateType)
    {
        if (array_key_exists($sfRateType, self::$rateTypeMap)) {
            return self::$rateTypeMap[$sfRateType];
        }

        throw new Myshipserv_Consortia_Exception("Salesforce rate type " . $sfRateType . " is not valid");
    }

    /**
     * See comment for getDbRateTypeFromSf() above
     *
     * @param   string  $dbRateType
     *
     * @return  string
     * @throws  Myshipserv_Consortia_Exception
     */
    public static function getSfRateTypeFromDb($dbRateType)
    {
        foreach (self::$rateTypeMap as $sfRateType => $dbType) {
            if ($dbType === $dbRateType) {
                return $sfRateType;
            }
        }

        throw new Myshipserv_Consortia_Exception("DB rate type " . $dbRateType . " is not valid");
    }

    /**
     * Converts Salesforce supplier agreement record into its database representation
     *
     * @todo: records of different origin should not be raw arrays but different types so accidental
     * @todo: double conversion is not possible, but right now I am short on time
     * @todo: definitely this is for further refactoring, would improve the safety much
     *
     * @param   array   $sfRecord
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Exception
     */
    public static function getDbRecordFromSf(array $sfRecord)
    {
        $dbRecord = $sfRecord;

        $dbRecord[Shipserv_Oracle_Consortia_Supplier::COL_RATE_TYPE] =
            self::getDbRateTypeFromSf($sfRecord[Shipserv_Oracle_Consortia_Supplier::COL_RATE_TYPE]);

        $dbRecord[Shipserv_Oracle_Consortia_Supplier::COL_VALID_TILL] =
            self::getDbEndDateFromSf($sfRecord[Shipserv_Oracle_Consortia_Supplier::COL_VALID_TILL]);

        return $dbRecord;
    }

    /**
     * Converts database supplier agreement record into its Salesforce representation
     *
     * @todo: see the comment for getDbRecordFromSf() above
     *
     * @param   array   $dbRecord
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Exception
     */
    public static function getSfRecordFromDb(array $dbRecord)
    {
        $sfRecord = $dbRecord;

        $sfRecord[Shipserv_Oracle_Consortia_Supplier::COL_RATE_TYPE] =
            self::getSfRateTypeFromDb($dbRecord[Shipserv_Oracle_Consortia_Supplier::COL_RATE_TYPE]);

        $sfRecord[Shipserv_Oracle_Consortia_Supplier::COL_VALID_TILL] =
            self::getSfEndDateFromDb($dbRecord[Shipserv_Oracle_Consortia_Supplier::COL_VALID_TILL]);

        return $sfRecord;
    }

    /**
     * Initialises the synchronisation session
     *
     * @param   array|int               $supplierIds
     * @param   Myshipserv_Logger_File  $logger
     * @param   Shipserv_User           $user
     */
    public function __construct($supplierIds, Myshipserv_Logger_File $logger, Shipserv_User $user = null)
    {
        if (!is_array($supplierIds)) {
            $supplierIds = array($supplierIds);
        }
        $this->supplierIds = $supplierIds;

        parent::__construct($logger, $user);
    }

    /**
     * @return array
     */
    public function getSupplierIds()
    {
        return $this->supplierIds;
    }

    /**
     * Updates last synchronisation date and error message in the given agreement in Salesforce
     *
     * @param   Myshipserv_Consortia_Exception $e
     *
     * @return  bool
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     */
    public function updateAgreementError(Myshipserv_Consortia_Exception $e)
    {
        if ($e->getSalesforceId()) {
            $this->updateSupplierAgreement(
                $e->getSalesforceId(),
                array(
                    'LastSyncDate__c' => $this->getSyncDate(),
                    'SyncError__c'    => $e->getMessage()
                )
            );

            return true;
        }

        return false;
    }

    /**
     * Updates last synchronisation date for the given agreement in Salesforce
     *
     * @param   string  $salesforceId
     *
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     */
    public function updateAgreementSyncDate($salesforceId)
    {
        $this->updateSupplierAgreement($salesforceId, array('LastSyncDate__c' => $this->getSyncDate()));
    }

    /**
     * Updates supplier agreements in Salesforce with the given field-value pairs
     *
     * @param   string  $salesforceId
     * @param   array   $values
     *
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     */
    protected function updateSupplierAgreement($salesforceId, array $values)
    {
        self::validateSalesForceId($salesforceId);
        $this->logger->log("Updating supplier agreement " . $salesforceId . " with " . print_r($values, true));

        $operation = function () use ($salesforceId) {
            return $this->querySalesforce(
                "
                  SELECT
                    Id
                  FROM
                    ConsortiaToSupplierAgreement__c
                  WHERE
                    Id = '" . $salesforceId . "'
                "
            );
        };

        $response = $this->runSalesforceOperation($operation, "Read supplier agreement before updating");

        if ($response->size !== 1) {
            throw new Myshipserv_Salesforce_Consortia_Exception(
                "Ambiguous or no result for reading supplier agreement " . $salesforceId
            );
        }

        $sfRecord = $response->records[0];

        // record retrieved - now replace values that need to be updated
        foreach ($values as $field => $value) {
            if (is_null($value)) {
                if (!isset($sfRecord->fieldsToNull)) {
                    $sfRecord->fieldsToNull = array();
                }

                $sfRecord->fieldsToNull[] = $field;

            } else {
                if ($value instanceof DateTime) {
                    $value = $value->format('Y-m-d');
                }

                $sfRecord->{$field} = $value;
            }
        }

        $operation = function () use ($sfRecord) {
            return $this->runBatchOperation(
                array($this->sfConnection, 'update'), array($sfRecord), 'ConsortiaToSupplierAgreement__c'
            );
        };

        $response = $this->runSalesforceOperation($operation, "Update supplier agreement");
        $this->checkSalesforceResponseForErrors(
            $response, array($sfRecord), "Error while updating supplier agreement"
        );
    }

    /**
     * Retrieves the agreements from Salesforce for them to be analysed before saved in the database
     *
     * @param   array       $supplierIds
     * @param   DateTime    $today
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     * @throws  Exception
     */
    protected function getAgreementsFromSalesforce(array $supplierIds, DateTime $today)
    {
        $this->logger->log(
            "Retrieving agreements of suppliers " . implode(", ", $supplierIds) .
            " for " . $today->format('Y-m-d') . " from Salesforce"
        );

        $operation = function () use ($supplierIds, $today) {
            // retrieving agreements that either expired today, are ongoing or are in the future
            return $this->querySalesforce(
                "
                  SELECT
                    Id,
                    SupplierTNID__c,
                    ConsortiaTNID__c,
                    EffectiveStartDate__c,
                    EffectiveEndDate__c,
                    RateType__c,
                    RateValue__c
                  FROM
                    ConsortiaToSupplierAgreement__c
                  WHERE
                    SupplierTNID__c IN (" . implode(", ", $supplierIds) . ")
                    AND (
                      EffectiveEndDate__c >= " . $today->format('Y-m-d') . "
                      OR EffectiveEndDate__c = NULL
                    )
					AND Product__c='Consortia'
                ",
                true
            );
        };

        $response = $this->runSalesforceOperation($operation, "Retrieve supplier agreements to synchronise");

        $agreements = array();
        foreach ($response->records as $sfAgreement) {
            $record = array(
                Shipserv_Oracle_Consortia_Supplier::COL_SALESFORCE_ID  => $sfAgreement->Id,
                Shipserv_Oracle_Consortia_Supplier::COL_SUPPLIER_ID    => $sfAgreement->SupplierTNID__c,
                Shipserv_Oracle_Consortia_Supplier::COL_CONSORTIA_ID   => $sfAgreement->ConsortiaTNID__c,
                Shipserv_Oracle_Consortia_Supplier::COL_VALID_FROM     => $sfAgreement->EffectiveStartDate__c,
                Shipserv_Oracle_Consortia_Supplier::COL_VALID_TILL     => $sfAgreement->EffectiveEndDate__c,
                Shipserv_Oracle_Consortia_Supplier::COL_RATE_TYPE      => $sfAgreement->RateType__c,
                Shipserv_Oracle_Consortia_Supplier::COL_RATE_VALUE     => $sfAgreement->RateValue__c
            );

            $agreements[] = Shipserv_Oracle_Consortia_Supplier::prepareRecord($record, 'sf');
        }

        return $agreements;
    }

    /**
     * Reads supplier agreements from Salesforce
     *
     * @return  array
     * @throws Exception
     */
    public function sync()
    {
        $supplierIds = $this->getSupplierIds();

        $syncDate = $this->resetSyncDate();             // date of when synchronisation starts
        $today    = self::removeTime($syncDate);        // cut off time as Salesforce only uses dates

        $this->logger->log(
            "Started pulling supplier agreements for " . implode(", ", $supplierIds) .
            ", synchronisation date: " . $syncDate->format('Y-m-d')
        );

        $agreements = $this->getAgreementsFromSalesforce($supplierIds, $today);

        $updated = array();
        $added   = array();
        $deleted = array();

        $db = Shipserv_Oracle_Consortia_Supplier::getDb();
        $db->beginTransaction();

        try {
            // unused future relationships are deleted in bulk
            $deleted = Shipserv_Oracle_Consortia_Supplier::deleteUnusedFutureAgreements($supplierIds, $agreements, $today);

            // other kinds of relations are dealt with one by one
            foreach ($agreements as $sfRecord) {
                $sfRecord = Shipserv_Oracle_Consortia_Supplier::prepareRecord($sfRecord, 'sf');

                $sfId = $sfRecord[Shipserv_Oracle_Consortia_Supplier::COL_SALESFORCE_ID];
                $sfValidFrom = $sfRecord[Shipserv_Oracle_Consortia_Supplier::COL_VALID_FROM];
                $sfValidTill = $sfRecord[Shipserv_Oracle_Consortia_Supplier::COL_VALID_TILL];

                // three possible use cases:
                // 1. relationship expiring today
                // 2. relationship is an ongoing one
                // 3. relationship is in the future

                if (!is_null($sfValidTill) and ($sfValidTill == $today)) {
                    // Salesforce agreement expires today, i.e. it's the last day of it
                    $updatedId = Shipserv_Oracle_Consortia_Supplier::expireOngoingAgreement(
                        $sfRecord, $this->getSyncDate(), $this->getUserId()
                    );

                    if (!is_null($updatedId)) {  // add ID to the list if the actual update happened
                        $updated[] = $updatedId;
                    }

                } else if ($sfValidFrom <= $today and (is_null($sfValidTill) or ($sfValidTill > $today))) {
                    // Salesforce agreement is ongoing
                    if (is_null(Shipserv_Oracle_Consortia_Supplier::findOngoingAgreements($sfRecord))) {
                        throw new Myshipserv_Consortia_Exception(
                            "Ongoing supplier agreement " . $sfId . " not found in the database", $sfId
                        );
                    }

                } else if ($sfValidFrom > $today) {
                    // Salesforce agreement is in the future
                    $createdId = Shipserv_Oracle_Consortia_Supplier::addFutureAgreement(
                        $sfRecord, $this->getSyncDate(), $this->getUserId()
                    );

                    if (!is_null($createdId)) {  // add ID to the list if a new record was added
                        $added[] = $createdId;
                        $this->updateAgreementSyncDate($sfId);
                    }

                } else {
                    throw new Myshipserv_Consortia_Exception(
                        "Unexpected supplier agreement status for " . $sfId, $sfId
                    );
                }

            }

        } catch (Myshipserv_Consortia_Exception $e) {
            $db->rollBack();
            $this->logger->log("Synchronisation error " . get_class($e) . ": " . $e->getMessage());

            throw $e;

        } catch (Exception $e) {
            $db->rollBack();
            $this->logger->log("Unexpected exception occurred: " . get_class($e) . ": " . $e->getMessage());

            throw $e;
        }

        $db->commit();

        return array(
            'added'     => $added,
            'updated'   => $updated,
            'deleted'   => $deleted
        );
    }
}