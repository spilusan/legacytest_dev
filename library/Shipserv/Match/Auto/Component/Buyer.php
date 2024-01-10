<?php
/**
 * Stub class representing an entity participating in auto match workflow. One day maybe extended to an ActiveRecord
 *
 * Represents automatch participating buyer relationship records
 *
 * @author  Yuriy Akopov
 * @date    2014-07-01
 * @story   S10311
 */
class Shipserv_Match_Auto_Component_Buyer extends Shipserv_Match_Auto_Component_Abstract {
    const
        TABLE_NAME = 'MATCH_BUYER_AUTO',

        COL_ID        = 'MBA_ID',
        COL_PART_TYPE = 'MBA_PARTICIPANT_TYPE',
        COL_PART_ID   = 'MBA_PARTICIPANT_ID',
        COL_DATE      = 'MBA_DATE',
        COL_CHEAP     = 'MBA_CHEAP_QUOTES_ONLY'
    ;

    const
        TYPE_ORG    = 'BYO',
        TYPE_BRANCH = 'BYB'
    ;
}