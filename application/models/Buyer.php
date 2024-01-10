<?
class Application_Model_Buyer
{
    public function fetchBuyerNamesForSearch($documentType, $buyerBranch, $fromDate, $toDate, $includeChildren = false) {

		$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
		$db = $resource->getDb('ssreport2');

        switch($documentType) {
            case 'all':
                $doctype = "IN ('RFQ','ORD','QOT','POC','INV')";
            break;
            case 'all-req':
                $doctype = "IN ('RFQ','ORD','QOT','POC','INV','REQ')";
            break;
            case 'req':
                $doctype = "= 'REQ'";
            break;
            case 'rfq':
                $doctype = "= 'RFQ'";
            break;
            case 'po':
                $doctype = "= 'ORD'";
            break;
            case 'sent':
                $doctype = "IN ('RFQ','ORD')";
            break;
            case 'qot':
                $doctype = "= 'QOT'";
            break;
            case 'poc':
                $doctype = "= 'POC'";
            break;
            case 'inv':
                $doctype = "= 'INV'";
            break;
            case 'recv':
                $doctype = "IN ('QOT','POC')";
            break;
        }

        $query = "SELECT UPPER( TRIM( REPLACE( REPLACE( cntc.cntc_person_name, '   ', ' ' ), '  ', ' ' ))) AS buyer_contact
                  FROM buyer byb, contact cntc
                  WHERE " . ($includeChildren ? " byb.parent_branch_code = :buyerbranch " : " byb.byb_branch_code = :buyerbranch ") ."
                  AND cntc.cntc_doc_type ". $doctype ."
                  AND cntc.cntc_branch_qualifier = 'BY'
                  AND cntc.byb_branch_code = byb.byb_branch_code
                  AND cntc.cntc_created_date BETWEEN to_date(:fromdate,'DD-MON-RRRR') AND to_date(:todate,'DD-MON-RRRR')+0.99999
                  AND cntc.cntc_person_name IS NOT NULL
                  GROUP BY UPPER( TRIM( REPLACE( REPLACE( cntc.cntc_person_name, '   ', ' ' ), '  ', ' ' )))
                  ORDER BY UPPER( TRIM( REPLACE( REPLACE( cntc.cntc_person_name, '   ', ' ' ), '  ', ' ' )))";

        return $db->fetchAll($query, array(
            ':buyerbranch'      => $buyerBranch,
            ':fromdate'         => strtoupper($fromDate->format('d-M-Y')),
            ':todate'           => strtoupper($toDate->format('d-M-Y'))
        ));
    }

    public function fetchAllBuyerBranchNames() {
		$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
		$db = $resource->getDb('ssreport2');
		$sql = "
			select	BYB_BRANCH_CODE
					, BYB_NAME 
			from 	BUYER 
			where 	BYB_IS_INACTIVE_ACCOUNT = 0 
			ORDER BY UPPER(BYB_NAME)";
        return $db->fetchAll($sql);
    }

    
    public function fetchAllRelatedBranches($branchCode = null, $pagesUserCode = null, $buyerOrgCode = null) {

    	$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
		$db = $resource->getDb('ssreport2');

        if ($pagesUserCode !== null) {
            $params = array('pagesUserCode' => $pagesUserCode);
            $query = "
                SELECT
                    b1.BYB_BRANCH_CODE, 
                    b1.BYB_NAME, 
                    byb_under_contract AS PARENT_BRANCH_CODE,
                    PUC_IS_DEFAULT
                 FROM
                    buyer_branch@livedb_link b1 
                    JOIN pages_user_company@livedb_link ON b1.BYB_BRANCH_CODE=puc_company_id
                WHERE
                    puc_psu_id = :pagesUserCode 
                    AND puc_company_type='BYB' 
                    AND puc_status='ACT' 
                    AND puc_txnmon=1
                ";
            if ($buyerOrgCode !== null) {
                $query .= ' AND BYB_BYO_ORG_CODE = :orgId';
                $params['orgId'] = $buyerOrgCode;
            }
        	$results = $db->fetchAll($query, $params);
        } else if ($buyerOrgCode !== null) {
            $query = "
                select DISTINCT
                    BYB_BRANCH_CODE, 
                    BYB_NAME, 
                    PARENT_BRANCH_CODE,
                    0 AS PUC_IS_DEFAULT,
                    CONNECT_BY_ISCYCLE
                from 
                    BUYER
                connect by nocycle prior
                    BYB_BRANCH_CODE = PARENT_BRANCH_CODE
                start with
                    BYB_BYO_ORG_CODE = :buyerOrgCode
                order by (CASE WHEN BYB_BRANCH_CODE = PARENT_BRANCH_CODE THEN 0 ELSE 1 END),BYB_BRANCH_CODE
             ";
            $results = $db->fetchAll($query, array('buyerOrgCode' => $buyerOrgCode));
            
        } elseif ($branchCode !== null)  {
        	$query = "
        	select
        		BYB_BRANCH_CODE,
        		BYB_NAME,
        		PARENT_BRANCH_CODE,
        	    0 AS PUC_IS_DEFAULT
        	from
        		BUYER
        	where
        		PARENT_BRANCH_CODE = :branchcode or BYB_BRANCH_CODE = :branchcode
        	";
        	$results = $db->fetchAll($query, array(
    			':branchcode' => $branchCode,
        	));
        } else {
            return array();
        }

        //Move parent to top
        if (count($results) > 1) {
            for($i = 0; $i < count($results); $i++) {
                $results[$i]["PUC_IS_DEFAULT"] = (Int) $results[$i]["PUC_IS_DEFAULT"];
                if ($results[$i]["PARENT_BRANCH_CODE"] == $results[$i]["BYB_BRANCH_CODE"]) {
                    $temp = $results[$i];
                    unset($results[$i]);
                    array_unshift($results, $temp);
                    break;
                }
            }
        }
        
        return $results;
    }
}
