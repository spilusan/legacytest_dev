<?php

/**
 * Class for reading buyer organisations data from Oracle
 *
 * @package Shipserv
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class Shipserv_Oracle_BuyerBranch extends Shipserv_Oracle
{
	public function __construct (&$db = "" )
	{
		if( $db == "" ) $db = $this->getDb();
		parent::__construct($db);
	}

	/**
	 * Fetches a list of organisations.
	 *
	 * NOTE: this seems like a silly return type (a list of rows) for a method that fetches one ID?? Suggest fetchBuyerOrgById() instead.
	 * 
	 * @access public
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchBuyerBranchById($buyerOrganisationId, $useCache = true, $cacheTTL = 3600)
	{
		$sql = "SELECT *";
		$sql.= " FROM BUYER_BRANCH BYB";
		$sql.= " WHERE BYB.BYB_BRANCH_CODE = :buyerOrganisationId";

		$sqlData = array('buyerOrganisationId'	=> $buyerOrganisationId);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'BUYERBRANCH'.$buyerOrganisationId.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}
	
	public function getListOfCompanyByKeyword($input)
	{
		$keyword = strtolower($input);
		$implodedKeywordWithAnd = str_replace(" ", " AND ", $keyword);
		$escapedKeyword = "{" . $keyword . "}";
		$words = explode(" ", $keyword);
		if( count($words) == 0 ) $words[] = $keyword;
		foreach($words as $word)
		{
			$conditionalForOr['spb_name'][] = " LOWER(spb_name) LIKE '%" . $word . "%'";
			$conditionalForOr['byo_name'][] = " LOWER(byo_name) LIKE '%" . $word . "%'";
			$conditionalForOr['byb_name'][] = " LOWER(byb_name) LIKE '%" . $word . "%'";
		}
	
		$memcacheTTL = 3600;
		$sql = "
					SELECT * FROM
					(
			            SELECT
			              DISTINCT
			                RN, VALUE, DISPLAY, NON_DISPLAY, LOCATION, CODE, PK
			            FROM
			            (
							SELECT
								/*+ index(buyer_branch IDX_BYB_FUZZY FIRST_ROWS(30))*/
								rownum rn
								, byb_name AS value
								, byb_branch_code || ': ' || REPLACE(byb_name, :keyword, '<em>' || :keyword || '</em>') || ' (' || byo_contact_city || ', ' || byo_country || ')' as DISPLAY
								, byo_name || ' (' || byo_contact_city || ', ' || byo_country || ', ORG ID: ' || byo_org_code || ', BRANCH ID: ' || byb_branch_code || ')' as NON_DISPLAY
								, byo_contact_city || ', ' || byo_country as LOCATION
								, 'BYB-' || byo_org_code AS code
								, byo_org_code AS parent_tnid
								, byb_branch_code AS tnid
								, byb_branch_code AS pk
							FROM
								buyer_organisation
					            JOIN buyer_branch ON (byb_byo_org_code=byo_org_code)
							WHERE
								--BYO_ORG_CODE NOT IN (SELECT PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM)
								--AND BYO_ORG_CODE NOT IN (SELECT PCO_ID FROM PAGES_COMPANY WHERE PCO_TYPE = 'BYO' AND PCO_IS_JOIN_REQUESTABLE = 'N')
								--AND
								(
									(
							            CONTAINS (byb_name, 'fuzzy(' || :escapedKeyword || ',60,100,weight)', 10) > 0
			            				OR CONTAINS (byb_name, '" . $implodedKeywordWithAnd . "') > 0
			            				OR (" . implode(" AND ", $conditionalForOr['byb_name']) . ")
		            				)
		            				OR TO_CHAR(byb_branch_code)=:keyword
		            			)
			            		--AND LOWER(byb_name) NOT LIKE '%test%'
			            )
			            ORDER BY UPPER(NON_DISPLAY) ASC
				)
				WHERE rn <30
		";
		
		$keyForMemcached = "JOIN_COMPANY_LIST_BYB_" . str_replace(" ", "_", $input) . md5($sql) . "_for_buyer";
	
		return $this->fetchCachedQuery($sql, array('keyword' => $keyword, 'escapedKeyword' => $escapedKeyword), $keyForMemcached, $memcacheTTL);
	
	}
	
	
	
	/**
	 * Check if isRfqDeadlineAllowed for this buyer branch
	 * @param unknown $buyerOrganisationId
	 * @return Bool 
	 */
	public function isRfqDeadlineAllowed($buyerOrganisationId)
	{
	    $sql = "
            SELECT NVL(ccf_rfq_deadline_control, 0) AS rfqdeadlineallowed 
            FROM customer_config
            WHERE ccf_branch_code = :buyerOrganisationId
        ";
        $result = $this->db->fetchOne($sql, array('buyerOrganisationId'	=> $buyerOrganisationId));
        
        if (!$result) {
            return false;
        }
	    return (Bool) $result;
	}
		

}
