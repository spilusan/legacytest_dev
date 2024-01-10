<?php
/**
 * Implements access to the Oracle table with Consortia buyer-supplier relationships
 *
 * @author  Yuriy Akopov
 * @date    2018-01-02
 * @story   DEV-1602
 */
class Shipserv_Oracle_Consortia_BuyerSupplier extends Shipserv_Oracle_Consortia_Abstract
{
    const
        TABLE_NAME = 'CONSORTIA_BUYER_SUPPLIER',

        COL_ID = 'CBS_INTERNAL_REF_NO',
        COL_SALESFORCE_ID = 'CBS_SF_SOURCE_ID',

        COL_CONSORTIA_ID = 'CBS_CON_INTERNAL_REF_NO',
        COL_SUPPLIER_ID  = 'CBS_SPB_BRANCH_CODE',
        COL_BUYER_ID     = 'CBS_BYB_BRANCH_CODE',

        COL_VALID_FROM   = 'CBS_VALID_FROM',
        COL_VALID_TILL   = 'CBS_VALID_TILL',

        COL_CREATED_BY   = 'CBS_CREATED_BY',
        COL_CREATED_DATE = 'CBS_CREATED_DATE',
        COL_UPDATED_BY   = 'CBS_UPDATED_BY',
        COL_UPDATED_DATE = 'CBS_UPDATED_DATE',

        SEQUENCE_NAME = 'SQ_CBS_INTERNAL_REF_NO';


    /**
     * Returns the list of table fields to select when a record is requested
     *
     * @param string $prefix
     *
     * @return array
     */
    public static function getSelectFieldList($prefix = 'cbs')
    {
        return array(
            self::COL_ID => $prefix . '.' . self::COL_ID,
            self::COL_SALESFORCE_ID => $prefix . '.' . self::COL_SALESFORCE_ID,

            self::COL_CONSORTIA_ID  => $prefix . '.' . self::COL_CONSORTIA_ID,
            self::COL_SUPPLIER_ID   => $prefix . '.' . self::COL_SUPPLIER_ID,
            self::COL_BUYER_ID      => $prefix . '.' . self::COL_BUYER_ID,

            self::COL_VALID_FROM => new Zend_Db_Expr('TO_CHAR(' . $prefix .  '.' . self::COL_VALID_FROM . ", 'YYYY-MM-DD HH24:MI:SS')"),
            self::COL_VALID_TILL => new Zend_Db_Expr('TO_CHAR(' . $prefix .  '.' . self::COL_VALID_TILL . ", 'YYYY-MM-DD HH24:MI:SS')"),

            self::COL_CREATED_BY    => $prefix . '.' . self::COL_CREATED_BY,
            self::COL_CREATED_DATE  => new Zend_Db_Expr('TO_CHAR(' . $prefix . '.' . self::COL_CREATED_DATE . ", 'YYYY-MM-DD HH24:MI:SS')"),
            self::COL_UPDATED_BY    => $prefix . '.' . self::COL_UPDATED_BY,
            self::COL_UPDATED_DATE  => new Zend_Db_Expr('TO_CHAR(' . $prefix . '.' . self::COL_UPDATED_DATE . ", 'YYYY-MM-DD HH24:MI:SS')")
        );
    }

    /**
     * Returns all the relationships for the given consortia
     *
     * @param   int $consortiaId
     *
     * @return  array
     */
    public static function getRelationshipsForConsortia($consortiaId)
    {
        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('cbs' => self::TABLE_NAME),
                self::getSelectFieldList('cbs')
            )
            ->where(self::COL_CONSORTIA_ID . ' = ?', $consortiaId)
            ->order(
                array(
                    'cbs.' . self::COL_BUYER_ID,
                    'cbs.' . self::COL_SUPPLIER_ID,
                    'cbs.' . self::COL_VALID_FROM . ' DESC'
                )
            );

        $records = $select->getAdapter()->fetchAll($select);

        return $records;
    }

    /**
     * Validates values in the record and converts dates to objects
     *
     * @param   array   $record
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Exception
     */
    public static function prepareRecord(array $record)
    {
        foreach ($record as $field => $value) {
            switch ($field) {
                case self::COL_ID:
                    if (!is_numeric($value)) {
                        throw new Myshipserv_Consortia_Validation_Exception(
                            "Relationship ID " . $value . " not specified or invalid", $record[self::COL_SALESFORCE_ID]
                        );
                    }
                    break;

                case self::COL_SUPPLIER_ID:
                    $supplier = Shipserv_Supplier::getInstanceById($value, null, true);
                    if (strlen($supplier->tnid) === 0) {
                        throw new Myshipserv_Consortia_Validation_Exception(
                            "Relationship supplier ID " . $value . " not found", $record[self::COL_SALESFORCE_ID]
                        );
                    }
                    break;

                case self::COL_BUYER_ID:
                    try {
                        Shipserv_Buyer_Branch::getInstanceById($value);

                    } catch (Exception $e) {
                        throw new Myshipserv_Consortia_Validation_Exception(
                            "Relationship buyer ID " . $value . " not found", $record[self::COL_SALESFORCE_ID]
                        );
                    }
                    break;

                case self::COL_CONSORTIA_ID:
                    Shipserv_Oracle_Consortia::getRecord($value);
                    break;

                case self::COL_SALESFORCE_ID:
                    if (!is_null($value) and !Myshipserv_Salesforce_Base::validateSalesForceId($value, true)) {
                        throw new Myshipserv_Consortia_Db_Exception(
                            "Buyer supplier relationship Salesforce ID " . $value . " is not valid",
                            $record[self::COL_SALESFORCE_ID]
                        );
                    }
                    break;

                case self::COL_CREATED_BY:
                case self::COL_UPDATED_BY:
                    // @todo: silenced because Admin Gateway puts strings there instead of user IDs
                    break;

                case self::COL_VALID_FROM:
                    try {
                        $record[$field] = self::validateDate($value, false);

                    } catch (Myshipserv_Consortia_Validation_Exception $e) {
                        $e->setSalesforceId($record[self::COL_SALESFORCE_ID]);
                        throw $e;
                    }

                    if ($record[$field] != Myshipserv_Salesforce_Consortia_Client_Abstract::removeTime($record[$field])) {
                        throw new Myshipserv_Consortia_Validation_Exception(
                            "Start date of relationship " . $record[self::COL_ID] .
                            " contains time, cannot be synchronised",
                            $record[self::COL_SALESFORCE_ID]
                        );
                    }

                    break;

                case self::COL_VALID_TILL:
                    try {
                        $record[$field] = self::validateDate($value, true);

                    } catch (Myshipserv_Consortia_Validation_Exception $e) {
                        $e->setSalesforceId($record[self::COL_SALESFORCE_ID]);
                        throw $e;
                    }

                    if (!is_null($value)) {
                        if (
                            $record[$field] != Myshipserv_Salesforce_Consortia_Client_Abstract::removeTime($record[$field])
                        ) {
                            throw new Myshipserv_Consortia_Validation_Exception(
                                "End date of relationship " . $record[self::COL_ID] .
                                " contains time, cannot be synchronised",
                                $record[self::COL_SALESFORCE_ID]
                            );
                        }
                    }

                    break;

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

        if (!is_null($record[self::COL_VALID_TILL])) {
            if ($record[self::COL_VALID_TILL] <= $record[self::COL_VALID_FROM]) {
                throw new Myshipserv_Consortia_Validation_Exception(
                    "Relationship date interval is not valid", $record[self::COL_SALESFORCE_ID]
                );
            }
        }

        return $record;
    }

    /**
     * Updates Salesforce IDs in the records after synchronisation
     *
     * @param array $mapping
     *
     * @throws  Myshipserv_Consortia_Validation_Exception
     * @throws  Myshipserv_Consortia_Db_Exception
     * @throws  Zend_Db_Adapter_Exception
     */
    public static function updateSalesforceIds(array $mapping)
    {
        $db = Shipserv_Helper_Database::getDb();

        foreach ($mapping as $dbId => $sfId) {
            if (!Myshipserv_Salesforce_Consortia_Client_Abstract::validateSalesForceId($sfId)) {
                throw new Myshipserv_Consortia_Validation_Exception(
                    "Salesforce ID " . $sfId . " returned for buyer/supplier relationship " . $dbId . " is not valid",
                    $sfId
                );
            }

            $updated = $db->update(
                self::TABLE_NAME,
                array(
                    self::COL_SALESFORCE_ID => $sfId
                ),
                $db->quoteInto(self::COL_ID . ' = ?', $dbId)
            );

            if ($updated !== 1) {
                throw new Myshipserv_Consortia_Db_Exception(
                    "Failed to update buyer supplier relationship " . $dbId . ", " . $updated . " records updated"
                );
            }
        }
    }

    /**
     * Get how many Consortia suppliers belongs to a buyer org
     * 
     * @param int $buyerOrdCode Buyer ORG code
     * 
     * @return int
     */
    public static function getSupplierCountForConsortiaBuyer($buyerOrdCode)
    {
        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('cbs' => self::TABLE_NAME),
                'count(distinct cbs.'. self::COL_SUPPLIER_ID . ') as spb_count'
            )
            ->join(
                array('byb' => 'BUYER_BRANCH'),
                  'cbs.' . self::COL_BUYER_ID . ' = byb.BYB_BRANCH_CODE',
                  array()
            )
            ->where('cbs.' . self::COL_VALID_FROM . ' <= SYSDATE')
            ->where('cbs.' . self::COL_VALID_TILL. ' >= SYSDATE OR cbs.' . self::COL_VALID_TILL. ' is NULL')
            ->where('byb.BYB_BYO_ORG_CODE = ?', (int)$buyerOrdCode);
        $records = $select->getAdapter()->fetchAll($select);

        return (int)$records[0]['SPB_COUNT'];
    }

    /**
     * To get the consortia ID for a Buyer Organizaton
     * 
     * @param int @byo Buyer Org Code
     * 
     * @return int
     */
    public static function getConsortiaIdByByo($byo)
    {
        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('cb' => 'consortia_buyer'),
                array('cb' => 'cbb_con_internal_ref_no as consortia_id')
            )
            ->join(
                array('bb' => 'buyer_branch'),
                  'cb.cbb_byb_branch_code = bb.byb_branch_code',
                  array()
            )
            ->where('cb.cbb_valid_from <= SYSDATE')
            ->where('cb.cbb_valid_till >= SYSDATE OR cb.cbb_valid_till is NULL')
            ->where('bb.byb_byo_org_code = ?', (int)$byo);
        $records = $select->getAdapter()->fetchAll($select);

        return (int)$records[0]['CONSORTIA_ID'];

    }

}