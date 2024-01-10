<?php
/**
 * A client managing Consortia synchronisation from Salesforce to Oracle
 *
 * @author  Yuriy Akopov
 * @date    2017-12-21
 * @story   DEV-1170
 */
class Myshipserv_Salesforce_Consortia_Client_Consortia extends Myshipserv_Salesforce_Consortia_Client_Abstract
{
    const
        ACCOUNT_TYPE_CONSORTIA = 'Procurement Consortia',

        CONSORTIA_STATUS_ACTIVE = 'Customer',
        CONSORTIA_STATUS_INACTIVE = 'Ex-Customer'
    ;

    /**
     * Returns boolean consortia status from the status string in Salesforce
     *
     * @param   string  $sfStatus
     *
     * @return  bool
     */
    public static function getDbConsortiaStatusFromSf($sfStatus)
    {
        switch ($sfStatus) {
            case self::CONSORTIA_STATUS_ACTIVE:
                return true;

            case self::CONSORTIA_STATUS_INACTIVE:
                return false;

            default:
                return false;   // @todo: check if this is desired behaviour
        }
    }

    /**
     * Returns Salesforce status string for the given boolean consortia activity flag
     *
     * @param   bool  $dbStatus
     *
     * @return  string
     */
    public static function getSfConsortiaStatusFromDb($dbStatus)
    {
        if ($dbStatus) {
            return self::CONSORTIA_STATUS_ACTIVE;
        } else {
            return self::CONSORTIA_STATUS_INACTIVE;
        }
    }

    /**
     * Returns the list of consortia currently registered in Salesforce
     *
     * @param   array   $dbIds
     *
     * @return  array
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     */
    protected function getSalesforceConsortiaRecords(array $dbIds = null)
    {
        $query = "
            SELECT
                Id,
                TNID__c,
                Name,
                Pipelinestatus__c
            FROM
                Account
            WHERE                
                Type = '" . self::ACCOUNT_TYPE_CONSORTIA . "'
        ";

        if (!empty($dbIds)) {
            $query .= " AND TNID__c IN (" . implode(", ", $dbIds) . ")";
        }

        $message =
            "Retrieve consortia data from Salesforce, " .
            (empty($dbIds) ? " all TNIDs" : "TNIDs: " . implode(", ", $dbIds))
        ;

        $operation = function () use ($query) {
            return $this->querySalesforce($query);
        };

        $response = $this->runSalesforceOperation($operation, $message);

        return $response->records;
    }

    /**
     * Removes (logically) Salesforce consortia which TNID no longer found in DB
     * Returns IDs of Salesforce records modified in such way
     *
     * @param   array   $consortiaIds
     *
     * @return  array
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     */
    protected function deleteConsortia(array $consortiaIds)
    {
        if (empty($consortiaIds)) {
            return array();
        }

        $sfRecords = $this->getSalesforceConsortiaRecords($consortiaIds);

        foreach ($sfRecords as $index => $sfRec) {
            if ($sfRec->Pipelinestatus__c === self::CONSORTIA_STATUS_INACTIVE) {
                // only statuses different from that need to be updated
                // this is different with the UPDATE workflow in a sense that status other that Active/Inactive are
                // not treated as Inactive and are changed to Inactive value explicitly
                unset($sfRecords[$index]);
            } else {
                // records are marked as inactive rather than getting physically deleted from Salesforce
                $sfRec->Pipelinestatus__c = self::getSfConsortiaStatusFromDb(false);
            }
        }

        if (empty($sfRecords)) {
            // nothing to update
            return array();
        }

        $operation = function () use ($sfRecords) {
            return $this->runBatchOperation(
                array($this->sfConnection, 'update'), array_values($sfRecords), 'Account'
            );
        };

        $response = $this->runSalesforceOperation($operation, "Mark consortia as deleted");
        $this->checkSalesforceResponseForErrors($response, $sfRecords, "Error while marking consortia as deleted");

        $salesforceIds = array();
        foreach ($sfRecords as $sfRec) {
            $salesforceIds[] = $sfRec->Id;
        }

        return $salesforceIds;
    }

    /**
     * Converts non-unique DB consortia name into a more likely to be unique name for Salesforce
     *
     * @param   int     $dbId
     * @param   string  $dbName
     *
     * @return  string
     */
    protected static function getSfNameFromDb($dbId, $dbName)
    {
        return $dbId . " " . $dbName;
    }

    /**
     * Inserts new records in Salesforce to represent consortia created since the last synchronisation
     * Returns Salesforce IDs created to match the supplie TNIDs
     *
     * @param   array   $consortia
     *
     * @return  array
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     * @throws  Myshipserv_Consortia_Db_Exception
     */
    protected function addConsortia(array $consortia)
    {
        if (empty($consortia)) {
            return array();
        }

        $sfRecords = array();
        foreach ($consortia as $dbId => $dbStatus) {
            $sfRec = new stdClass();
            // TNID of database consortia
            $sfRec->TNID__c = $dbId;
            // current status of that consortia in the database
            $sfRec->Pipelinestatus__c = self::getSfConsortiaStatusFromDb($dbStatus['status']);
            // consortia name
            $sfRec->Name = self::getSfNameFromDb($dbId, $dbStatus['name']);

            // fields identifying it as consortia for Salesforce workflows
            $sfRec->Type         = self::ACCOUNT_TYPE_CONSORTIA;

            $sfRecords[] = $sfRec;
        }

        $operation = function () use ($sfRecords) {
            return $this->runBatchOperation(
                array($this->sfConnection, 'create'), $sfRecords, 'Account'
            );
        };

        $response = $this->runSalesforceOperation($operation, "Create consortia records");
        $this->checkSalesforceResponseForErrors($response, $sfRecords, "Error while inserting consortia");

        $result = array();
        $consortiaIndex = 0;
        foreach (array_keys($consortia) as $dbId) {
            $result[$dbId] = $response[$consortiaIndex++]->id;
        }

        return $result;
    }

    /**
     * Modifies Consortia status in Salesforce
     *
     * @param   array     $consortia
     *
     * @return  array
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     */
    protected function updateConsortia(array $consortia)
    {
        if (empty($consortia)) {
            return array();
        }

        $this->logger->log("Updating " . count($consortia) . " consortia statuses in Salesforce");

        $sfRecords = $this->getSalesforceConsortiaRecords(array_keys($consortia));
        $dbIds = array();

        foreach ($sfRecords as $sfRecNo => $sfRec) {
            $updateNeeded = false;

            $dbId = $sfRec->TNID__c;
            $sfStatus = self::getDbConsortiaStatusFromSf($sfRec->Pipelinestatus__c);

            // since there is more than one way to represent 'inactive' status in Salesforce (different signatures)
            // we only need to update when they're different, otherwise we risk re-writing Salesforce string which
            // is of no value to DB but may be important for Salesforce
            if ($sfStatus !== $consortia[$dbId]['status']) {
                $sfRecords[$sfRecNo]->Pipelinestatus__c = self::getSfConsortiaStatusFromDb($consortia[$dbId]['status']);
                $updateNeeded = true;
            } else {
                unset($sfRecords[$sfRecNo]->Pipelinestatus__c);
            }

            $sfName = self::getSfNameFromDb($dbId, $consortia[$dbId]['name']);
            if ($sfRec->Name !== $sfName) {
                $sfRecords[$sfRecNo]->Name = $sfName;
                $updateNeeded = true;
            } else {
                unset($sfRecords[$sfRecNo]->Name);
            }

            if (!$updateNeeded) {
                unset($sfRecords[$sfRecNo]);
            } else {
                // this field is not going to change - we used it for matching the record so it's fine
                $dbIds[] = $sfRecords[$sfRecNo]->TNID__c;
                unset($sfRecords[$sfRecNo]->TNID__c);
            }
        }

        if (empty($sfRecords)) {
            $this->logger->log("No consortia records require update in Salesforce");
            return array();
        }

        $sfRecords = array_values($sfRecords);

        $operation = function () use ($sfRecords) {
            return $this->runBatchOperation(
                array($this->sfConnection, 'update'), $sfRecords, 'Account'
            );
        };

        $response = $this->runSalesforceOperation($operation, "Update consortia data");
        $this->checkSalesforceResponseForErrors($response, $sfRecords, "Error while updating consortia");

        $result = array();
        foreach ($sfRecords as $recNo => $record) {
            $result[$dbIds[$recNo]] = $record->Id;
        }

        return $result;
    }

    /**
     * Compresses Salesforce consortia records into simple TNID -> status array
     *
     * @param   array   $sfRecords
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Validation_Exception
     */
    protected function getDbConsortiaStatusesFromSf(array $sfRecords)
    {
        $sfConsortia = array();
        foreach ($sfRecords as $sfRec) {
            $dbId = $sfRec->TNID__c;
            if (is_null($dbId)) {
                continue;   // it is not an error if there are records with no TNIDs, but they are excluded from sync
            }

            $sfId = $sfRec->Id;

            if (array_key_exists($dbId, $sfConsortia)) {
                throw new Myshipserv_Consortia_Validation_Exception(
                    "Duplicate Salesforce record " . $sfId . " found for consortia " . $dbId, $sfId
                );
            }

            // please see the comment in Shipserv_Oracle_Consortia::getCurrentStatuses() for why there is a simple array
            // instead of ::prepareRecord() function here unlike elsewhere
            $sfConsortia[$dbId] = array(
                'name'   => $sfRec->Name,
                // there are more than two possible values for this field, but all but one are treated as 'inactive'
                'status' => self::getDbConsortiaStatusFromSf($sfRec->Pipelinestatus__c)
            );

        }

        return $sfConsortia;
    }

    /**
     * Synchronises consortia lists between database and Salesforce
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     * @throws  Shipserv_Helper_Database_Exception
     * @throws  Myshipserv_Consortia_Db_Exception
     */
    public function sync()
    {
        $now = $this->resetSyncDate();
        $this->logger->log("Synchronising consortia data with the anchor date of " . $now->format('Y-m-d H:i:s'));

        $dbConsortia = Shipserv_Oracle_Consortia::getCurrentStatuses($now);
        $sfConsortia = $this->getDbConsortiaStatusesFromSf($this->getSalesforceConsortiaRecords());

        // now 'forget' records that are in sync already
        $inSync = array();

        foreach ($dbConsortia as $dbId => $dbStatus) {
            if (array_key_exists($dbId, $sfConsortia)) {
                if ($sfConsortia[$dbId]['status'] === $dbStatus['updated']) {
                    // names are unique in Salesforce but not in the database, so to maintain uniqueness we could
                    // have added a TNID as a prefix when the record was created in Salesforce
                    $allowedNames = array(
                        $dbStatus['name'],
                        $dbId . " " . $dbStatus['name']
                    );

                    if (in_array($sfConsortia[$dbId]['name'], $allowedNames)) {
                        // DB and Salesforce representation of the consortia $dbId are in sync
                        $inSync[] = $dbId;
                        unset($dbConsortia[$dbId]);
                        unset($sfConsortia[$dbId]);
                    }
                }
            }
        }

        $this->logger->log(count($inSync) . " consortia records are already in sync");

        // now update and add consortia that are not in Salesforce
        $toAdd = array();
        $toUpdate = array();
        foreach ($dbConsortia as $dbId => $dbStatus) {
            if (array_key_exists($dbId, $sfConsortia)) {
                // record already exists in Salesforce, it needs to be updated
                $toUpdate[$dbId] = $dbStatus;
                unset($sfConsortia[$dbId]);

            } else {
                // record doesn't exist in Salesforce yet
                $toAdd[$dbId] = $dbStatus;
            }
        }

        $added = $this->addConsortia($toAdd);
        $updated = $this->updateConsortia($toUpdate);

        // delete remaining Salesforce consortia that are not in the database
        $deleted = $this->deleteConsortia(array_keys($sfConsortia));

        return array(
            'added'   => $added,
            'updated' => $updated,
            'deleted' => $deleted
        );
    }
}