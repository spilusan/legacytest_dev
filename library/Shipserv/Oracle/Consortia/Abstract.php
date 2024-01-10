<?php
/**
 * Base class for Consortia related data entities
 *
 * @todo: should be expanded into a base for all similar classes, something similar to Application_Model_Object in Match
 * @todo: this classes so far are lazy - mostly static methods juggling records as arrays, not 'instance means record'
 * @todo: unfortunately Pages lacks a standard for DB work, Shipserv_Object being terrible and every object being ad-hoc
 *
 * @author  Yuriy Akopov
 * @date    2017-12-18
 * @story   DEV-1602
 */
abstract class Shipserv_Oracle_Consortia_Abstract
{
    const
        TABLE_NAME = 'redefine me',

        COL_CREATED_BY   = 'redefine me',
        COL_CREATED_DATE = 'redefine me',
        COL_UPDATED_BY   = 'redefine me',
        COL_UPDATED_DATE = 'redefine me',

        SEQUENCE_NAME    = 'redefine me'
    ;

    /**
     * @return Zend_Db_Adapter_Oracle
     */
    public static function getDb()
    {
        return Shipserv_Helper_Database::getDb();
    }

    /**
     * Returns auto-incremented ID of the last inserted record
     *
     * @return  int
     */
    protected static function getLastInsertedId()
    {
        return self::getDb()->lastSequenceId(static::SEQUENCE_NAME);
    }

    /**
     * Validates (if string) and returns the date
     *
     * @param   DateTime|string $datetime
     * @param   bool            $allowNull
     *
     * @return  DateTime|null
     * @throws  Myshipserv_Consortia_Validation_Exception
     */
    protected static function validateDate($datetime, $allowNull)
    {
        if ($allowNull and is_null($datetime)) {
            return null;
        }

        if ($datetime instanceof DateTime) {
            return $datetime;
        }

        try {
            return new DateTime($datetime);
        } catch (Exception $e) {
            throw new Myshipserv_Consortia_Validation_Exception("Invalid date " . $datetime);
        }
    }

    /**
     * Converts given datetime into Oracle date expression unless it's null
     *
     * @param DateTime|null $datetime
     *
     * @return null|string
     * @throws Shipserv_Helper_Database_Exception
     */
    protected static function getOracleDate(DateTime $datetime = null)
    {
        if (is_null($datetime)) {
            return null;
        }

        return Shipserv_Helper_Database::getOracleDateExpr($datetime);
    }

    /**
     * Compares two records to each other, checking for both mutual field presence and value match,
     * with an optional way to skip over some of those fields when checking
     *
     * @param   array               $record1
     * @param   array               $record2
     * @param   string|array|null   $skipFields
     *
     * @return bool
     */
    public static function compareRecords(array $record1, array $record2, $skipFields = null)
    {
        if (is_null($skipFields)) {
            $skipFields = array();
        } else if (!is_array($skipFields)) {
            $skipFields = array($skipFields);
        }

        // meta data not expected to match all the time
        $skipFields[] = static::COL_CREATED_BY;
        $skipFields[] = static::COL_CREATED_DATE;
        $skipFields[] = static::COL_UPDATED_BY;
        $skipFields[] = static::COL_UPDATED_DATE;

        $skipFields = array_unique($skipFields);

        $compare = function (array $rec1, array $rec2, array $skipFields) {
            foreach ($rec1 as $field => $value) {
                if (in_array($field, $skipFields)) {
                    continue;
                }

                if (!array_key_exists($field, $rec2)) {
                    return false;
                }

                if ($value != $rec2[$field]) {
                    return false;
                }
            }

            return true;
        };

        return $compare($record1, $record2, $skipFields) and $compare($record2, $record1, $skipFields);
    }
}