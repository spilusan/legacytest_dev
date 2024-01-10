<?php
/**
 * A client managing synchronisation (reading, writing, validation) of Consortia data
 *
 * @author  Yuriy Akopov
 * @date    2017-11-30
 * @story   DEV-1170
 */
abstract class Myshipserv_Salesforce_Consortia_Client_Abstract extends Myshipserv_Salesforce_Base
{
    const WRITE_BATCH_SIZE = 100;   // DEV-2524 the number of records to write at once (Salesforce has a limit on that)

    /**
     * Logger for the session
     *
     * @var Myshipserv_Logger_Base
     */
    protected $logger = null;

    /**
     * Datetime of when the synchronisation has been conducted
     *
     * Is expected to be UTC all the time
     *
     * @var DateTime
     */
    protected $syncDate = null;

    /**
     * User on whose behalf the synchronisation is performed
     *
     * @var Shipserv_User
     */
    protected $user = null;

    /**
     * Session-long simple cache of TNIDs against Salesforce IDs
     *
     * @var array
     */
    protected static $salesforceIdCache = array();

    /**
     * Returns the ID of the user who is performing the synchronisation , if specified
     *
     * @return int|null
     */
    protected function getUserId()
    {
        if (is_null($this->user)) {
            return null;
        }

        return $this->user->userId;
    }

    /**
     * Resets synchronisation date for a new iteration
     *
     * @return DateTime
     * @throws Myshipserv_Salesforce_Consortia_Exception
     */
    protected function resetSyncDate()
    {
        $this->syncDate = new DateTime();

        return $this->getSyncDate();
    }

    /**
     * Returns the date which was used to read the last synchronised data
     *
     * @return  DateTime
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     */
    public function getSyncDate()
    {
        if (is_null($this->syncDate)) {
            throw new Myshipserv_Salesforce_Consortia_Exception(
                "No synchronisation date available, probably no synchronisation conducted successfully yet"
            );
        }

        return $this->syncDate;
    }

    /**
     * Removes time from the datetime returning the date only (more precisely, 00:00:00 am of that date)
     *
     * @param   DateTime    $datetime
     *
     * @return  DateTime
     */
    public static function removeTime(DateTime $datetime)
    {
        return new DateTime($datetime->format('Y-m-d'));
    }

    /**
     * Salesforce end dates are inclusive for user convenience. E.g. an agreement ending on Sep 01 means it lasts
     * for the whole of Sep 1 and stops at midnight Sep 2.
     *
     * In the database, end dates are exclusive to avoid ambiguous gaps, so they are 1 day different
     *
     * @param   DateTime|string    $date
     *
     * @return DateTime
     */
    public static function getDbEndDateFromSf($date = null)
    {
        if (is_null($date)) {
            return null;
        }

        if (!($date instanceof DateTime)) {
            $date = new DateTime($date);
        } else {
            $date = clone($date);
        }

        return $date->modify('+1 day');
    }

    /**
     * See comment for getDbEndDateFromSf() above
     *
     * @param   DateTime|string    $date
     *
     * @return  DateTime
     */
    public static function getSfEndDateFromDb($date = null)
    {
        if (is_null($date)) {
            return null;
        }

        if (!($date instanceof DateTime)) {
            $date = new DateTime($date);
        } else {
            $date = clone($date);
        }

        return $date->modify('-1 day');
    }

    /**
     * Initialises the synchronisation session
     *
     * @param   Myshipserv_Logger_File  $logger
     * @param   Shipserv_User           $user
     */
    public function __construct(Myshipserv_Logger_File $logger, Shipserv_User $user = null)
    {
        $this->logger = $logger;
        $this->user = $user;

        parent::initialiseConnection();
    }

    /**
     * Runs supplied Salesforce operation in a block that would convert any exception into a recognisable Salesforce
     * exception
     *
     * @param   callable  $operation
     * @param   string    $description
     *
     * @return  QueryResult
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     */
    protected function runSalesforceOperation(callable $operation, $description)
    {
        $this->logger->log("Starting Salesforce operation: " . $description);

        $timeStart = microtime(true);
        try {
            $response = $operation();

        } catch (Exception $e) {
            $this->logger->log(
                get_class($e) . "::" .	(strlen($e->faultstring) ? $e->faultstring . "::" : "") . $e->getMessage() .
                "::Last SF request: " . print_r($this->sfConnection->getLastRequest(), true)
            );

            throw new Myshipserv_Salesforce_Consortia_Exception(
                "Salesforce error " . get_class($e) . " while synchronising consortia data: " . $e->getMessage()
            );
        }

        $this->logger->log(
            "Finished " . $description . " in " .
            round(microtime(true) - $timeStart, 2) . " sec"
        );

        return $response;
    }

    /**
     * Returns true if the response record indicates a success of the operation this record represents
     *
     * @param   stdClass    $responseRecord
     *
     * @return  bool
     */
    protected function isSalesforceResponseRecordSuccessful(stdClass $responseRecord)
    {
        return (isset($responseRecord->success) and ($responseRecord->success === true));
    }

    /**
     * Checks Salesforce UPDATE, CREATE and DELETE response for errors and throws an exception when at least one is found
     *
     * @param   QueryResult  $response
     * @param   array        $sfRecords  records that were attempted to delete, remove or insert
     * @param   string       $exceptionMessage
     *
     * @throws  Myshipserv_Salesforce_Consortia_Exception
     */
    protected function checkSalesforceResponseForErrors($response, array $sfRecords, $exceptionMessage)
    {
        $errors = array();

        // collect errors from Salesforce response
        foreach ($response as $recNo => $rec) {
            if ($this->isSalesforceResponseRecordSuccessful($rec)) {
                // successful record
                continue;
            }

            if (isset($rec->errors)) {
                // ID of the record that caused this error
                $salesforceId = $sfRecords[$recNo]->Id;

                // get first error message - it is usually enough to investigate
                $firstErrorMessage = $rec->errors[0]->statusCode;

                if (self::validateSalesForceId($salesforceId)) {
                    $errors[$salesforceId] = $firstErrorMessage;
                } else {
                    // there could be no ID if we were adding records, in that case just add it to the array
                    $errors[] = $firstErrorMessage;
                }
            }
        }

        if (!empty($errors)) {
            $errorMessages = array();

            foreach ($errors as $salesforceId => $errCode) {
                if (self::validateSalesForceId($salesforceId)) {
                    $errorMessage = "ID " . $salesforceId;
                } else {
                    $errorMessage = "Record no." . $salesforceId;
                }

                $errorMessages[] = $errorMessage . ": " . $errCode;
            }

            throw new Myshipserv_Salesforce_Consortia_Exception(
                $exceptionMessage . ": " . implode(", ", $errorMessages)
            );
        }
    }

    /**
     * Initiates the synchronisation process
     *
     * @return array
     */
    abstract function sync();

    /**
     * Runs supplied Salesforce operation (typically, INSERT, UPDATE or DELETE) in batches
     *
     * @story   DEV-2524
     * @date    2018-02-06
     *
     * @param   callable    $callback
     * @param   array       $records
     * @param   string      $object
     *
     * @return  array
     */
    protected function runBatchOperation(callable $callback, array $records, $object = null)
    {
        $results = array();
        $batches = array_chunk($records, self::WRITE_BATCH_SIZE);

        foreach ($batches as $batch) {
            $callbackParams = array($batch);
            if (!is_null($object)) {
                $callbackParams[] = $object;
            }

            $batchResults = call_user_func_array($callback, $callbackParams);
            $results = array_merge($results, $batchResults);

            /*
            foreach ($batchResults as $responseRec) {
                if (!$this->isSalesforceResponseRecordSuccessful($responseRec)) {
                    // no need to run further batches as there was an error
                    return $results;
                }
            }
            */
        }

        return $results;
    }
}