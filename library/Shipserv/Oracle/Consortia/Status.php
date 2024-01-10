<?php
/**
 * Implements access to Oracle table with Consortia statuses
 *
 * @author  Yuriy Akopov
 * @date    2017-12-18
 * @story   DEV-1602
 */
class Shipserv_Oracle_Consortia_Status extends Shipserv_Oracle_Consortia_Abstract
{
    const
        TABLE_NAME = 'CONSORTIA_STATUS',

        COL_ID              = 'CST_INTERNAL_REF_NO',
        COL_CONSORTIA_ID    = 'CST_CON_INTERNAL_REF_NO',
        COL_VALID_FROM      = 'CST_VALID_FROM',
        COL_VALID_TILL      = 'CST_VALID_TILL',
        COL_CREATED_BY      = 'CST_CREATED_BY',
        COL_CREATED_DATE    = 'CST_CREATED_DATE',
        COL_UPDATED_BY      = 'CST_UPDATED_BY',
        COL_UPDATED_DATE    = 'CST_UPDATED_DATE',

        SEQUENCE_NAME = 'SQ_CST_INTERNAL_REF_NO'
    ;
}