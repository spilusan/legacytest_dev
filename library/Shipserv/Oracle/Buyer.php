<?php
class Shipserv_Oracle_Buyer extends Shipserv_Oracle
{
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
								/*+ index(buyer_organisation IDX_BYO_FUZZY) FIRST_ROWS(30) */					
								rownum rn
								, byo_name AS value
								, byo_org_code || ': ' || REPLACE(byo_name, :keyword, '<em>' || :keyword || '</em>') || ' (' || byo_contact_city || ', ' || byo_country || ')' as DISPLAY
								, byo_name || ' (' || byo_contact_city || ', ' || byo_country || ', ORG ID: ' || byo_org_code || ')' as NON_DISPLAY
								, byo_contact_city || ', ' || byo_country as LOCATION
								, 'BYO-' || byo_org_code AS code
								, byo_org_code AS parent_tnid
								, null AS tnid
								, byo_org_code AS pk
							FROM
								buyer_organisation
							WHERE
								BYO_ORG_CODE NOT IN (SELECT PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM)
								AND BYO_ORG_CODE NOT IN (SELECT PCO_ID FROM PAGES_COMPANY WHERE PCO_TYPE = 'BYO' AND PCO_IS_JOIN_REQUESTABLE = 'N')
								AND
								( 
									(
										CONTAINS (byo_name, 'fuzzy(' || :escapedKeyword || ',60,100,weight)', 10) > 0
										OR CONTAINS (byo_name, '" . $implodedKeywordWithAnd . "') > 0
										OR (" . implode(" AND ", $conditionalForOr['byo_name']) . ")
									)
									OR TO_CHAR(byo_org_code)=:keyword
								)
                				--AND LOWER(byo_name) NOT LIKE '%test%'
												
					        UNION
	          
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
								, byo_org_code AS pk
							FROM
								buyer_organisation
					            JOIN buyer_branch ON (byb_byo_org_code=byo_org_code)
							WHERE
								BYO_ORG_CODE NOT IN (SELECT PBN_BYO_ORG_CODE FROM PAGES_BYO_NORM)
								AND BYO_ORG_CODE NOT IN (SELECT PCO_ID FROM PAGES_COMPANY WHERE PCO_TYPE = 'BYO' AND PCO_IS_JOIN_REQUESTABLE = 'N')
								AND 
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
		$keyForMemcached = "JOIN_COMPANY_LIST_" . str_replace(" ", "_", $input) . md5($sql) . "_for_buyer";
		
		return $this->fetchCachedQuery($sql, array('keyword' => $keyword, 'escapedKeyword' => $escapedKeyword), $keyForMemcached, $memcacheTTL);
		
	}
}