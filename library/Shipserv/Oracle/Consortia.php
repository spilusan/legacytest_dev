<?php
/**
 * Implements access to the Oracle table with Consortia information
 *
 * This is as opposed to Shipserv_Consortia which represents instance/record model (not exactly once instance may
 * currently represent more than 1 record, so this needs to be refactored at some point)
 *
 * This separation is because in Pages there is no agreed model for 'ActiveRecord' like classes, and Shipserv_Object is too
 * cluttered and old, so where there is no strict need for instance-oriented management I create such simpler although
 * also not architecturally elegant static table-level classes
 *
 * @todo: Shipserv_Consortia* and Shipserv_Oracle_Consortia* brought together under the same ideology
 *
 * @author  Yuriy Akopov
 * @date    2017-11-30
 * @story   DEV-1170
 */
class Shipserv_Oracle_Consortia extends Shipserv_Oracle_Consortia_Abstract
{
    const
        TABLE_NAME = 'CONSORTIA',

        COL_ID           = 'CON_INTERNAL_REF_NO',
        COL_NAME         = 'CON_CONSORTIA_NAME',
        COL_BRANDING     = 'CON_BRANDING',
        COL_CREATED_BY   = 'CON_CREATED_BY',
        COL_CREATED_DATE = 'CON_CREATED_DATE',
        COL_UPDATED_BY   = 'CON_UPDATED_BY',
        COL_UPDATED_DATE = 'CON_UPDATED_DATE',

        SEQUENCE_NAME = 'SQ_CON_INTERNAL_REF_NO'
        ;

    /**
     * Returns consortia database record by its unique ID
     *
     * @param   int     $consortiaId
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Db_Exception
     */
    public static function getRecord($consortiaId)
    {
        // a check because Shipserv_Consortia constructor we are going to use will also accept an array, which
        // in this context would be wrong
        if (!is_numeric($consortiaId)) {
            throw new Myshipserv_Consortia_Db_Exception(
                "Consortia ID " . $consortiaId . " cannot be loaded as the ID is not valid"
            );
        }

        try {
            // here we do unnecessary double work because the data is loaded anyway
            $instance = new Shipserv_Consortia($consortiaId);

        } catch (Myshipserv_Exception_MessagedException $e) {
            throw new Myshipserv_Consortia_Db_Exception("Consortia " . $consortiaId . " not found");
        }

        return $instance->getData()[0];
    }

    /**
     * Returns a list of registered consortia TNIDs along with the flag whether it's active at the moment provided
     * Is used for synchronising this information with Salesforce where the data model has no historical statuses
     *
     * @param DateTime|null $today
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Shipserv_Helper_Database_Exception
     */
    public static function getCurrentStatuses(DateTime $today = null)
    {
        if (is_null($today)) {
            $today = new DateTime();
        }

        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('con' => self::TABLE_NAME),
                array(
                    'ID'    => 'con.' . self::COL_ID,
                    'NAME'  => 'con.' . self::COL_NAME
                )
            )
            ->joinLeft(
                array('cst' => Shipserv_Oracle_Consortia_Status::TABLE_NAME),
                implode(
                    ' AND ',
                    array(
                        'cst.' . Shipserv_Oracle_Consortia_Status::COL_CONSORTIA_ID . ' = con.' . Shipserv_Oracle_Consortia::COL_ID,
                        'cst.' . Shipserv_Oracle_Consortia_Status::COL_VALID_FROM . ' <= ' . self::getOracleDate($today),
                        '(' .
                        implode(
                            ' OR ',
                            array(
                                'cst.' . Shipserv_Oracle_Consortia_Status::COL_VALID_TILL . ' IS NULL',
                                'cst.' . Shipserv_Oracle_Consortia_Status::COL_VALID_TILL . ' > ' . self::getOracleDate($today)
                            )
                        ) .
                        ')'
                    )
                ),
                array(
                    'STATUS' => 'cst.' . Shipserv_Oracle_Consortia_Status::COL_ID
                )
            )
            ->order('con.' . self::COL_ID);

        $rows = $select->getAdapter()->fetchAll($select);

        $result = array();
        foreach ($rows as $record) {
            $consortiaId = $record['ID'];

            if (array_key_exists($consortiaId, $result)) {
                throw new Myshipserv_Consortia_Validation_Exception(
                    null, "Overlapping time intervals found for consortia " . $consortiaId
                );
            }

            // there is no dedicated ::prepareRecord() function as in other similar workflows - this is because
            // there are two fields one of which has no direct data representation ('status' is a computed field)
            // and name allows any value, so we're skipping it
            // a little less uniform that way, but hopefully more readable
            $result[$consortiaId] = array(
                'name'   => $record['NAME'],
                'status' => (bool) $record['STATUS']
            );
        }

        return $result;
    }
}