<?php
/**
 * Stub class representing an entity participating in auto match workflow. One day maybe extended to an ActiveRecord
 *
 * Represents a keyword RFQs are matched against
 *
 * @author  Yuriy Akopov
 * @date    2014-06-10
 * @story   S10311
 */
class Shipserv_Match_Auto_Component_Keyword extends Shipserv_Match_Auto_Component_Abstract {
    const
        TABLE_NAME = 'MATCH_SUPPLIER_KEYWORD',

        COL_ID      = 'MSK_ID',
        COL_SET_ID  = 'MSK_MSS_ID',
        COL_KEYWORD = 'MSK_KEYWORD',
        COL_DATE    = 'MSK_DATE'
    ;
}