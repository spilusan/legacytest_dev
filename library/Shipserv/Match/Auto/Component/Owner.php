<?php
/**
 * Stub class representing an entity participating in auto match workflow. One day maybe extended to an ActiveRecord
 *
 * Represents a connection between a keyword set and its owner which can be both buyer branch and organisation
 *
 * @author  Yuriy Akopov
 * @date    2014-06-10
 * @story   S10311
 */
class Shipserv_Match_Auto_Component_Owner extends Shipserv_Match_Auto_Component_Abstract {
    // table for relationships between keyword sets and their owners
    const
        TABLE_NAME = 'MATCH_SUPPLIER_KEYWORD_OWNER',

        COL_ID              = 'MSO_ID',
        COL_OWNER_TYPE      = 'MSO_OWNER_TYPE',
        COL_OWNER_ID        = 'MSO_OWNER_ID',
        COL_SET_ID          = 'MSO_MSS_ID'
    ;

    const
        OWNER_BRANCH = 'SPB',
        OWNER_ORG    = 'SUP'
    ;
}