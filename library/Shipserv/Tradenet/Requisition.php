<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

class Shipserv_Tradenet_Requisition extends Shipserv_Object {
    
    public static function fetchForBuyerInbox($buyerbranch, $sortfield = 'req_preparation_date', $sortDirection = 'ASC', $pageStart=0, $pageEnd=50) {
        
		$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
		$db = $resource->getDb('standbydb');
        
        if (!in_array($sortfield, array('req_internal_ref_no', 'vessel_name'))) {
            $sortfield = 'req_internal_ref_no';
        }
        
        $query = "SELECT to_char(REQ_PREPARATION_DATE,'Mon dd, yyyy') AS REQ_PREPARATION_DATE,
                         REQ_INTERNAL_REF_NO, 
                         REQ_VESSEL_NAME,
                         REQ_STS,
                         REQ_SS_TRACKING_NO,
                         DECODE(REQ_PRIORITY,'Y','High','Low'), 
                         REQ_SUBJECT, 
                         REQ_REF_NO,
                         BYB_NAME 
                         
                FROM     REQUISITION, BUYER_BRANCH
                
                WHERE    REQUISITION.REQ_BYB_BRANCH_CODE=BUYER_BRANCH.BYB_BRANCH_CODE 
                AND      BUYER_BRANCH.BYB_BRANCH_CODE=:buyerbranch
                AND      REQ_STS IN ('SUB','OPN') 
                AND      'A'||REQ_ARCHIVAL_STS||'A'<>'AAA' 
                
                ORDER BY REQ_PREPARATION_DATE DESC";
                
        return $db->fetchAll($query, array(
            ':buyerbranch' => $buyerbranch
        ));
    }
}