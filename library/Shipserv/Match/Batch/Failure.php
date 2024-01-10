<?php
/**
 * Represents records in batch search failures table and provides methods to modify its content
 *
 * Doesn't implement any ActiveRecord elements as they aren't needed yet
 *
 * @author  Yuriy Akopov
 * @date    2013-09-05
 * @story   S8133
 */
class Shipserv_Match_Batch_Failure extends Shipserv_Object {
    const
        TABLE_NAME = 'MATCH_BATCH_FAILURE',

        COL_ID              = 'MBF_ID',
        COL_RFQ_ID          = 'MBF_RFQ_INTERNAL_REF_NO',
        COL_ERROR_TYPE      = 'MBF_ERROR_TYPE',
        COL_ERROR_DATE      = 'MBF_ERROR_DATE',
        COL_ERROR_CLASS     = 'MBF_ERROR_CLASS',
        COL_ERROR_MESSAGE   = 'MBF_ERROR_MESSAGE',
        COL_FIX_ATTEMPT_DATE= 'MBF_FIX_ATTEMPT_DATE',
        COL_FIXED_DATE      = 'MBF_FIXED_DATE'
    ;

    /**
     * Adds an single failure information to the list with meta data extracted from exception
     *
     * @param   Shipserv_Rfq|int            $rfq
     * @param   string                      $type
     * @param   DateTime|string|int|null    $time
     * @param   Exception|null              $e
     *
     * @return  int
     */
    public static function addErrorExceptionToList($rfq, $type, $time = null, Exception $e = null) {
        $errClass = $errMessage = null;

        if (!is_null($e)) {
            $errClass   = get_class($e);
            $errMessage = $e->getMessage();
        }

        return self::addErrorToList($rfq, $type, $time, $errClass, $errMessage);
    }

    /**
     * Adds an single failure information to the list
     *
     * @param   Shipserv_Rfq|int            $rfq
     * @param   string                      $type
     * @param   DateTime|string|int|null    $time
     * @param   string|null                 $errClass
     * @param   string|null                 $errMessage
     *
     * @return  int
     * @throws  Shipserv_Match_Batch_Exception
     */
    public static function addErrorToList($rfq, $type, $time = null, $errClass = null, $errMessage = null) {
        $db = Shipserv_Helper_Database::getDb();

        if ($rfq instanceof Shipserv_Rfq) {
            $rfqId = $rfq->rfqInternalRefNo;
        } else {
            $rfqId = $rfq;
        }

        if (is_null($time)) {
            $time = time();
        }

        $toInsert = array(
            self::COL_RFQ_ID        => $rfqId,
            self::COL_ERROR_TYPE    => $type,
            self::COL_ERROR_DATE    => new Zend_Db_Expr(Shipserv_Helper_Database::getOracleDateExpr($time))
        );

        if (!is_null($errClass)) {
            $toInsert[self::COL_ERROR_CLASS] = self::truncateStringDbValue($errClass, self::TABLE_NAME, self::COL_ERROR_CLASS);
        }

        if (!is_null($errMessage)) {
            $toInsert[self::COL_ERROR_MESSAGE] = self::truncateStringDbValue($errMessage, self::TABLE_NAME, self::COL_ERROR_MESSAGE);
        }

        if (($result = $db->insert(self::TABLE_NAME, $toInsert)) === false) {
            throw new Shipserv_Match_Batch_Exception('Failed to store an RFQ error');
        }

        return $result;
    }

    /**
     * Receives an error information array from batch processing (Shipserv_Match_Batch_Abstract::process())
     * and adds information about the RFQs failed to the queue of failed RFQs in the database
     *
     * @param array $batchErrorInfo
     *
     * @return  int
     * @throws  Shipserv_Match_Batch_Exception
     */
    public static function addBatchErrorsToList(array $batchErrorInfo) {
        $rows = 0;

        $db = Shipserv_Helper_Database::getDb();
        $db->beginTransaction();

        try {
            foreach ($batchErrorInfo as $errorType) {
                if (empty($errorType)) {
                    continue;
                }

                foreach ($errorType as $errorInfo) {
                    $rows += self::addErrorToList(
                        $errorInfo[Shipserv_Match_Batch_Queue_Abstract::ERROR_INFO_RFQID],
                        $errorInfo[Shipserv_Match_Batch_Queue_Abstract::ERROR_INFO_TIME],
                        $errorInfo[Shipserv_Match_Batch_Queue_Abstract::ERROR_INFO_CLASS],
                        $errorInfo[Shipserv_Match_Batch_Queue_Abstract::ERROR_INFO_MESSAGE]
                    );
                }
            }
        } catch (Exception $e) {
            $db->rollBack();
            throw new Shipserv_Match_Batch_Exception('Failed to store RFQ batch processing error info (' . $e->getMessage() . ')');
        }

        $db->commit();

        return $rows;
    }

    /**
     * Helper function that converts the incoming RFQ data into a plain array of RFQ IDs
     *
     * @param   Shipserv_Rfq[]|Shipserv_Rfq|array|int    $rfq
     *
     * @return  array
     */
    protected static function rfqToIds($rfq) {
        if (!is_array($rfq)) {
            $rfq = array($rfq);
        }

        $ids = array();
        foreach($rfq as $r) {
            if ($rfq instanceof ShipservRfq) {
                $ids[] = $r->rfqInternalRefNo;
            } else {
                $ids[] = $r;
            }
        }

        return $ids;
    }

    /**
     * Removes the given RFQ or an array of them from the list of the RFQs failed during the batch processing
     *
     * @param   Shipserv_Rfq[]|Shipserv_Rfq|array|int    $rfq
     * @param   bool                                     $registerAttempt
     * @param   bool                                     $registerFix
     * @param   bool                                     $delete
     *
     * @return  int|bool
     * @throws  Shipserv_Match_Batch_Exception
     */
    public static function updateRfqStatus($rfq, $registerAttempt = true, $registerFix = true, $delete = false) {
        if (!($registerAttempt or $registerFix or $delete)) {
            return false; // no status change requested
        }


        $rfqIds = self::rfqToIds($rfq);

        $db = Shipserv_Helper_Database::getDb();
        $db->beginTransaction();

        $rows = 0;
        $start = 0;
        $step = 999; // Oracle limitation for IN (...)

        try {
            while (count($rfqIdsSlice = array_slice($rfqIds, $start, $step))) {
                $start += $step;

                foreach ($rfqIdsSlice as $key => $id) {
                    $rfqIdsSlice[$key] = $db->quoteInto('?', $id);
                }

                $where = implode(' AND ', array(
                    self::COL_RFQ_ID . ' IN (' . implode(',', $rfqIdsSlice) . ')',
                    self::COL_FIXED_DATE . ' IS NULL'
                ));

                if ($delete) {
                    // deleting the RFQ records not yet marked as resolved
                    $rows += $db->delete(self::TABLE_NAME, $where);
                } else {
                    $updateFields = array();

                    if ($registerAttempt) {
                        $updateFields[self::COL_FIX_ATTEMPT_DATE] = new Zend_Db_Expr('sysdate');
                    }

                    if ($registerFix) {
                        $updateFields[self::COL_FIXED_DATE] = new Zend_Db_Expr('sysdate');
                    }

                    $rows += $db->update(self::TABLE_NAME, $updateFields, $where);
                }
            }

        } catch (Exception $e) {
            $db->rollBack();
            throw new Shipserv_Match_Batch_Exception('Failed to remove ' . count($rfqIds) . ' RFQ errors from the failure queue (' . $e->getMessage() . ')');
        }

        $db->commit();

        return $rows;
    }
}