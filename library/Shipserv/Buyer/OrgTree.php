<?php
/**
 * Returns the list of buyer organisations, and their child braches considering normalisation
 *
 * @author  Attila Olbrich
 * @date    2016-06-06
 * @story   S16636
 */

class Shipserv_Buyer_OrgTree extends Shipserv_Memcache
{

    /**
    * Support parent BYO code
    *
    * @param   Shipserv_Buyer  $buyerOrg
    */

    protected $buyerOrg;
    protected $db;

    public function __construct(Shipserv_Buyer $buyerOrg)
    {
        $this->buyerOrg = $buyerOrg;
        $this->db = Shipserv_Helper_Database::getDb();
    }


    /**
    * Returns the tree of the buyer 
    */
    public function getTree()
    {
		
		$result = array();
		$orgList = $this->_getBuyerOrgs($this->buyerOrg->id);
		foreach ($orgList as $org) {
			$result[$org['BYO_ORG_CODE']] = array(
 				 'name' => htmlspecialchars($org['BYO_NAME'], ENT_QUOTES, 'UTF-8')
                ,'type' => $org['TYPE']
				,'branches' => $this->_getBuyerBrancesByOrg($org['BYO_ORG_CODE'])
			);
		}

		return $result;

    }

    /**
	* Returns All BYO after normalisation
	* @param integer $buyerOrgId
	*
	* @return array
	*/
    protected function _getBuyerOrgs($buyerOrgId) 
    {

    	$sql = "
    		SELECT
			   byo_org_code
			  ,byo_name
              ,'org' type 
			FROM
			  buyer_organisation
			WHERE
			  byo_org_code = :orgCode
			  or byo_org_code in (
			    SELECT
			      pbn_byo_org_code
			    FROM
			      pages_byo_norm
			    WHERE
			     pbn_norm_byo_org_code = :orgCode
                )
            ORDER BY
                byo_org_code
			  ";

		return $this->db->fetchAll($sql, array('orgCode' => (int)$buyerOrgId));

    }

    /**
    * Get buyer branches by org codes
    */
    protected function _getBuyerBrancesByOrg($orgCode)
    {

    	$result = array();
    	$sql = "
			SELECT 
				 byb_branch_code
				,byb_name
                ,'branch' type 
			FROM
				buyer_branch
			WHERE
				BYB_BYO_ORG_CODE = :orgCode
                AND byb_test_account = 'N'
                AND byb_sts = 'ACT'
                AND byb_contract_type IN
                (
                 'CN3'
                ,'CCP'
                ,'TRIAL'
                ,'STANDARD'
               )
            ORDER BY
                byb_branch_code
            ";


		$branches =  $this->db->fetchAll($sql, array('orgCode' => (int)$orgCode));

		foreach ($branches as $branch) {
			$result[$branch['BYB_BRANCH_CODE']] = array(
					 'name' => htmlspecialchars($branch['BYB_NAME'], ENT_QUOTES, 'UTF-8')
                    ,'type' => $branch['TYPE']
					,'branches' => $this->_getBuyerBranches($branch['BYB_BRANCH_CODE'])
				);
		}

    	return $result;
            
    }

    /**
    * Returns a list of Buyer Branches recursiveliy as a treee
	* @param integer $buyerBranchId
	*
	* @return array
    */
    protected function _getBuyerBranches($buyerBranchId)
    {
    	$result = array();
    	$sql = "
	    	SELECT
			   byb_branch_code
			  ,byb_under_contract
			  ,byb_name
              ,'branch' type 
			FROM
			  buyer_branch b
			WHERE
			  byb_under_contract = :bybBranchCode
              AND  byb_test_account = 'N'
              AND byb_sts = 'ACT'
              AND byb_contract_type IN
                (
                 'CN3'
                ,'CCP'
                ,'TRIAL'
                ,'STANDARD'
               )
            ORDER BY 
                byb_branch_code
		  ";

		$res = $this->db->fetchAll($sql, array('bybBranchCode' => (int)$buyerBranchId));
		foreach ($res as $rec) {
			if (!($rec['BYB_BRANCH_CODE'] == $rec['BYB_UNDER_CONTRACT'])) {
				$result[$rec['BYB_BRANCH_CODE']] =  array(
						 'name' => htmlspecialchars($rec['BYB_NAME'], ENT_QUOTES, 'UTF-8')
                        ,'type' => $rec['TYPE']
						,'branches' => $this->_getBuyerBranches($rec['BYB_BRANCH_CODE']) 
					);
			}
		}

		return $result;
    }


}