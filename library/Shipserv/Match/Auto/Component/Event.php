<?php
/**
 * Stub class representing an entity participating in auto match workflow. One day maybe extended to an ActiveRecord
 *
 * Represents a connection between an RFQ event and a keyword set it matches
 *
 * @author  Yuriy Akopov
 * @date    2014-06-10
 * @story   S10311
 */
class Shipserv_Match_Auto_Component_Event extends Shipserv_Match_Auto_Component_Abstract {
    // table for relationship between RFQs and sets they match
    const
        TABLE_NAME = 'MATCH_SUPPLIER_RFQ',

        COL_ID          = 'MSR_ID',
        COL_RFQ_EVENT   = 'MSR_RFQ_EVENT_HASH',
        COL_SET_ID      = 'MSR_MSS_ID',
        COL_DATE        = 'MSR_DATE'
    ;
}