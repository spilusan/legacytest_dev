<?php
/**
 * Stub class representing an entity participating in auto match workflow. One day maybe extended to an ActiveRecord
 *
 * Represents a set of keywords RFQs are matched against
 *
 * @author  Yuriy Akopov
 * @date    2014-06-10
 * @story   S10311
 */
class Shipserv_Match_Auto_Component_Set extends Shipserv_Match_Auto_Component_Abstract {
    // table for keyword sets
    const
        TABLE_NAME = 'MATCH_SUPPLIER_KEYWORD_SET',

        COL_ID      = 'MSS_ID',
        COL_NAME    = 'MSS_NAME',
        COL_ENABLED = 'MSS_ENABLED'
    ;
}