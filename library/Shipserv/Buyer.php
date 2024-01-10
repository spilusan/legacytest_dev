<?php

/**
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Buyer extends Shipserv_Object
{
	public $id;
	public $name;
	
	public function __construct($data)
	{
		// populate the supplier object
		if (is_array($data)) {
			foreach ($data as $name => $value) {
				$this->{$name} = $value;
			}
			$this->id = $this->byoOrgCode;
			$this->name = $this->byoName;
		}
	}
	
	private static function _createObjectFromDb($data)
	{
		$object = new self($data);
		return $object;
	}


	/**
	 * Singleton getInstance method
	 *
	 * @param Int $id
	 * @param String $skipNormalisation
	 * @return Shipserv_Buyer
	 */
	public static function getInstanceById($id, $skipNormalisation = false)
	{
        if (!$skipNormalisation) {
            $id = self::_checkNormalisation($id);
        }

		$row = self::getDao()->fetchBuyerOrgById($id, $skipNormalisation);
		$data = parent::camelCase($row);
		return self::_createObjectFromDb($data);
	}
	
	public static function getBuyerBranchInstanceById($id)
	{
		$row = self::getDao('buyer-branch')->fetchBuyerBranchById($id);
		$data = parent::camelCase($row[0]);
		return self::_createObjectFromDb($data);
	}
	
	
	/**
	 * Get the database access object
	 * 
	 * @param String $type
	 * @return object
	 */
	private static function getDao($type = '')
	{
		if ($type == "")
        	return new Shipserv_Oracle_BuyerOrganisations(self::getDb());
		else if ($type == "buyer-branch")
            return new Shipserv_Oracle_BuyerBranch(self::getDb());
	}
	
	public static function getByoOrgCodeByTnid($tnid)
	{
		$sql = "SELECT byb_byo_org_code COMPANY_ID FROM buyer_branch WHERE byb_branch_code=:companyId";
		$rows = self::getDb()->fetchAll($sql, array('companyId' => $tnid));
		return $rows[0]['COMPANY_ID'];
	}
	
	/**
	 * Returned the canonicalized company id
	 * 
	 * @param Int $orgId
	 * @return Int the normalized company id
	 */
	public static function getNormalisedCompanyByOrgId($orgId)
	{
		$sql = "SELECT byb_byo_org_code COMPANY_ID FROM buyer_branch WHERE byb_branch_code=:companyId";
		$sql = "SELECT PBN_NORM_BYO_ORG_CODE COMPANY_ID FROM PAGES_BYO_NORM WHERE PBN_BYO_ORG_CODE=:companyId";
		$rows = self::getDb()->fetchAll($sql, array('companyId' => $orgId));
		return $rows[0]['COMPANY_ID'];
	}
	
	/**
	 * Returns the array of company ids, that the input company $orgId is normalization of 
	 * In other words $orgId is the normalization of the output array. 
	 * All organization ids of ourput array will get reidrected to $orgId when selecting the TNID
	 * 
	 * @param Int $orgId
	 * @return Array the array of ids
	 */
	public static function getNormalingCompaniesByOrgId($orgId)
	{
	    $sql = "SELECT PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM WHERE PBN_NORM_BYO_ORG_CODE=:companyId";
	    $rows = self::getDb()->fetchAll($sql, array('companyId' => $orgId));
	    return array_map(
            function ($row)
            {
                return $row['PBN_BYO_ORG_CODE'];
            }, 
            (Array) $rows
        );
	}
	
	
	/**
	 * Get all the branches of $this->byoOrgCode.
	 * This method support normalisation and branch parent/child hierarchy
	 *
	 * @param Bool $asArray
	 * @param Bool $noInactive
	 * @return Array return and array of Shipserv_Buyer or Array of ids, depending on first param $asArray
	 */
	public function getBranchesTnid($asArray = true, $noInactive = false)
	{
	    $whereCondition = "";
	    if ($noInactive) {
	        $whereCondition .= "WHERE byb_sts <> 'INA'";
	    }
	    $sql = "
			SELECT
				DISTINCT(byb_branch_code) AS TNID, byb_under_contract
			FROM
				buyer_branch LEFT JOIN pages_byo_norm ON byb_byo_org_code = pbn_byo_org_code
            $whereCondition
            START WITH (byb_byo_org_code = :orgCode OR pbn_norm_byo_org_code = :orgCode)
            CONNECT BY NOCYCLE PRIOR byb_branch_code = byb_under_contract
            ORDER BY byb_under_contract
		";
		$rows = self::getDb()->fetchAll($sql, array('orgCode' => $this->byoOrgCode));

		/*
		 * This function previously had a $returnOnlyBuyerUsingMatch param false by default. 
         * When that param was true this query would have been executed in place of the main one. 
         * That would create inconsistencies and does not make sense accordingly to Yuroy and Attila
         * Claudio changed this function completely with S17783 and other [Trading accounts] related stories of sprint 2016.09
		$sql = "
			SELECT DISTINCT byb_branch_code
			FROM
			  rfq 
			WHERE
			  byb_branch_code IN (
			    SELECT byb_branch_code FROM buyer WHERE byb_byo_org_code=:orgCode 
			    AND byb_is_test_account=0 AND  byb_is_inactive_account=0
			  )
		";
		$rows = self::getSsreport2Db()->fetchAll($sql, array('orgCode' => $this->byoOrgCode));
		*/
		
		$output = array();
		foreach ($rows as $data) {
			if ($asArray == false) {
				$output[] = self::getBuyerBranchInstanceById($data['TNID'], true);
			} else {
				$output[] = $data['TNID'];
			}
		}
		return $output;
	}
	
	
	private static function _checkNormalisation($id)
	{
		
		$sql = "
		SELECT * FROM
		(
		-- when user typing TNID - it gets redirected to ORG_ID
		SELECT byb_branch_code ID, byb_byo_org_code ORG_ID, '1' R_TYPE FROM buyer_branch
		WHERE byb_byo_org_code NOT IN (SELECT PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM WHERE PBN_NORM_BYO_ORG_CODE IS NULL)
			
		-- when user trying to put org_id - it gets normalised to org_id
		UNION ALL SELECT PBN_BYO_ORG_CODE ID, PBN_NORM_BYO_ORG_CODE ORG_ID, '2' R_TYPE FROM PAGES_BYO_NORM
			
		-- when user typing org_id and this isn't normalised, then redirect to the same org_id
		UNION ALL SELECT byb_byo_org_code ID, byb_byo_org_code ORG_ID, '3' R_TYPE FROM buyer_branch
		WHERE byb_byo_org_code NOT IN (SELECT PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM WHERE PBN_NORM_BYO_ORG_CODE IS NULL)
		)
		WHERE
		ID=:tnid
		";
		
		$rows = Shipserv_Helper_Database::registryFetchAll(__CLASS__ . '_' . __FUNCTION__, $sql, array('tnid' => $id));

		if (!isset($rows[0]['ORG_ID'])) {
			return false;
		}
		
		return ($rows[0]['ORG_ID'] != "")?$rows[0]['ORG_ID']:false;
	}
	
	public function toArray($dataType = "")
	{
		if ($dataType == "contact-detail") {
			$buyer = $this;
			
			$adapterForCountry = new Shipserv_Oracle_Countries();
			$country = $adapterForCountry->fetchCountryByCode($buyer->byoCountry);

			$data = array();
			$data['tnid'] = $buyer->id;
			$data['name'] = $buyer->name;
			$data['address1'] = $buyer->byoContactAddress1;
			$data['address2'] = $buyer->byoContactAddress2;
			$data['city'] = $buyer->byoContactCity;
			$data['postcode'] = $buyer->byoContactPin;
			$data['state'] = $buyer->byoContactState;
			$data['country'] = $country[0]['CNT_NAME'];
			$data['contactEmail'] = $buyer->byoContactEmail;
			$data['contactName'] = $buyer->byoContactName;
			$data['contactPhone'] = $buyer->byoContactPhone;
			return $data;
		}
	}
	
	
	public function isIntegrated()
	{
		$sql = "
			SELECT 
			  COUNT(*) TOTAL
			FROM 
			  BUYER_BRANCH JOIN BUYER_ORGANISATION
			  ON byb_byo_org_code=byo_org_code
			WHERE 
			  byo_org_code=:tnid
			  AND BYB_MTML_BUYER='Y'		
		";
		$result = self::getDb()->fetchAll($sql, array('tnid' => $this->id));
		return ($result[0]['TOTAL'] > 0)?true:false;
	}
	
	
	public function isEssmEnabled()
	{
		$sql = "
			SELECT 
			  COUNT(*) TOTAL
			FROM 
			  BUYER_BRANCH JOIN BUYER_ORGANISATION
			  ON byb_byo_org_code=byo_org_code
			WHERE 
			  byo_org_code=:tnid
			  AND BYB_MTML_BUYER='N'		
		";
		
		$result = self::getDb()->fetchAll($sql, array('tnid' => $this->id));
		return ($result[0]['TOTAL'] > 0)?true:false;
	}
	
	
	public function saveDetail($type, $value)
	{
		if (true == in_array($type, array('BYB_ACCT_MNGR_NAME', 'BYB_CONTRACT_TYPE', 'BYB_POTENTIAL_UNITS', 'BYB_POTENTIAL_GMV_PER_UNIT', 'BYB_MONTHLY_FEE_PER_UNIT'))) {
			$sql = "UPDATE buyer_branch SET " . $type . "=:value WHERE byb_branch_code=:tnid";
			$this->getDb()->query($sql, array('value' => $value, 'tnid' => $this->bybBranchCode));
			$this->getDb()->commit();
			return true;
		}
		
		throw new Exception('Incorrect type supplied when saving buyer detail');
	}

    /**
     * Returns the maximal buyer organisation ID to help in telling supplier IDs from buyer ones
     *
     * @author  Yuriy Akopov
     * @date    2013-11-29
     *
     * @return  int
     */
    public static function getMaxOrgId() 
    {
        $db = Shipserv_Helper_Database::getDb();

        $select = new Zend_Db_Select($db);
        $select->from(
            'buyer_organisation',
            new Zend_Db_Expr('MAX(byo_org_code)')
        );

        $maxOrgId = $db->fetchOne($select);

        return (int) $maxOrgId;
    }
}
