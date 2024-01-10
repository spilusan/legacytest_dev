<?php
/**
 * Brand management dashnoard report, Added by Attila O 21/07/2015
 */
class Shipserv_Report_Dashboard_Brandmanagement extends Shipserv_Report_Dashboard
{

   protected $baseSql; 

   function __construct() {
       //parent::__construct();
    //oem_total + oem_pending + rep_pending  claims_pending_verification, //REPLACED
       $this->baseSql = "
        WITH base AS 
        (
          SELECT
            id,
            brand_name,
            synonym_list,
            owner_list,
            is_actively_managed,
            listed_only_count+auth_agent_total+oem_total+rep_total total_supplier_brand_links,
            auth_agent_total+oem_total+rep_total total_authorised_claims,
            listed_only_count,
            auth_agent_verified+oem_verified+rep_verified verified_claims,
            auth_agent_verified + auth_agent_pending + oem_verified + oem_pending + rep_verified + rep_pending claims_where_brand_is_owned,
            auth_agent_pending + oem_pending + rep_pending  claims_pending_verification,
            case when (auth_agent_total+oem_total+rep_total)<>0 then round((auth_agent_verified+oem_verified+rep_verified) / (auth_agent_total+oem_total+rep_total)* 100) else null end percent_claims_verified,
            auth_agent_total,
            auth_agent_verified,
            auth_agent_pending,
            auth_agent_not_verified,
            oem_total,
            oem_verified,
            oem_pending,
            oem_not_verified,
            rep_total,
            rep_verified,
            rep_pending,
            rep_not_verified,
            related_category_count,
            related_category_list
          FROM 
          (
          SELECT  
            id, 
            name brand_name, 
            synonym_list, 
            owner_list, 
            ( 
              SELECT
               decode(count(1), 0, 'N', 'Y')  
              FROM
               sservdba.pages_company_brands 
              WHERE
                  pcb_auth_level = 'OWN' 
                  AND pcb_is_deleted = 'N' 
                  AND pcb_is_authorised ='Y' 
                  AND pcb_brand_id = id 
            ) is_actively_managed, 
            ( 
               SELECT
                 count(distinct pcb_company_id) 
               FROM
                sservdba.pages_company_brands 
               WHERE
                pcb_auth_level = 'AGT' 
                  AND pcb_is_deleted = 'N' 
                  AND pcb_is_authorised ='Y' 
                  AND pcb_brand_id = id 
                  AND pcb_date_approved is NOT NULL 
                  AND pcb_date_requested is NOT NULL 
            ) auth_agent_verified, 
            ( 
               SELECT
                count(distinct pcb_company_id) 
               FROM
                sservdba.pages_company_brands 
               WHERE
                pcb_auth_level = 'AGT' 
                  AND pcb_is_deleted = 'N' 
                  AND pcb_is_authorised ='N' 
                  AND pcb_brand_id = id 
            ) auth_agent_not_verified, 
            ( 
               SELECT
                 count(distinct pcb_company_id) 
               FROM
                sservdba.pages_company_brands 
               WHERE
                pcb_auth_level = 'AGT' 
                  AND pcb_is_deleted = 'N' 
                  AND pcb_date_approved is NULL 
                  AND pcb_is_authorised='N'
                  AND pcb_brand_id = id 
            ) auth_agent_pending,  
            
            ( 
               SELECT
                 count(distinct pcb_company_id) 
               FROM
                sservdba.pages_company_brands 
               WHERE
                pcb_auth_level = 'AGT' 
                  AND pcb_is_deleted = 'N' 
                  AND pcb_brand_id = id 
            ) auth_agent_total, 
            ( 
               SELECT
                count(distinct pcb_company_id) 
              FROM
                sservdba.pages_company_brands 
              WHERE
                pcb_auth_level = 'OEM' 
                  AND pcb_is_deleted = 'N' 
                  AND pcb_is_authorised ='Y' 
                  AND pcb_brand_id = id 
                  AND pcb_date_approved is NOT NULL 
                  AND pcb_date_requested is NOT NULL 
            ) oem_verified, 
            ( 
               SELECT
                count(distinct pcb_company_id) 
              FROM
                sservdba.pages_company_brands 
              WHERE
                pcb_auth_level = 'OEM' 
                  AND pcb_is_deleted = 'N' 
                  AND pcb_is_authorised ='N' 
                  AND pcb_brand_id = id 
            ) oem_not_verified,   
            ( 
               SELECT
                count(distinct pcb_company_id) 
              FROM
                sservdba.pages_company_brands 
              WHERE
                pcb_auth_level = 'OEM' 
                  AND pcb_is_deleted = 'N' 
                  AND pcb_date_approved is NULL
                  AND pcb_is_authorised ='N'
                  AND pcb_brand_id = id 
            ) oem_pending,  
            
            ( 
               SELECT
                count(distinct pcb_company_id) 
               FROM
                sservdba.pages_company_brands 
               WHERE
                  pcb_auth_level = 'OEM' 
                  AND pcb_is_deleted = 'N' 
                  AND pcb_brand_id = id 
            ) oem_total, 
            ( 
                 SELECT
                  count(distinct pcb_company_id) 
                 FROM
                  sservdba.pages_company_brands 
                 WHERE
                  pcb_auth_level = 'REP' 
                    AND pcb_is_deleted = 'N' 
                    AND pcb_is_authorised ='Y' 
                    AND pcb_brand_id = id 
                    AND pcb_date_approved is NOT NULL 
                    AND pcb_date_requested is NOT NULL 
            ) rep_verified, 
            ( 
                 SELECT
                  count(distinct pcb_company_id) 
                 FROM
                  sservdba.pages_company_brands 
                 WHERE
                    pcb_auth_level = 'REP' 
                    AND pcb_is_deleted = 'N' 
                    AND pcb_is_authorised ='N' 
                    AND pcb_brand_id = id 
            ) rep_not_verified, 
            ( 
                 SELECT
                  count(distinct pcb_company_id) 
                 FROM
                  sservdba.pages_company_brands 
                 WHERE
                    pcb_auth_level = 'REP' 
                    AND pcb_is_deleted = 'N' 
                    AND pcb_date_approved is NULL
                    AND pcb_is_authorised  = 'N'
                    AND pcb_brand_id = id 
            ) rep_pending, 
            ( 
                 SELECT
                  count(distinct pcb_company_id) 
                 FROM sservdba.pages_company_brands 
                 WHERE
                    pcb_auth_level = 'REP' 
                    AND pcb_is_deleted = 'N' 
                    AND pcb_brand_id = id 
            ) rep_total, 
            ( 
              SELECT
               count(distinct pcb_company_id) 
              FROM
               sservdba.pages_company_brands pcb1 
              WHERE
                  pcb_auth_level = 'LST' 
                    AND pcb_is_deleted = 'N' 
                    AND pcb_brand_id = id 
                    AND not exists ( 
                      SELECT null from sservdba.pages_company_brands pcb2 
                      WHERE (pcb_auth_level = 'REP' or pcb_auth_level = 'OEM' or pcb_auth_level = 'AGT') 
                      AND pcb_is_deleted = 'N' 
                      AND pcb1.pcb_company_id = pcb2.pcb_company_id 
                    ) 
            ) listed_only_count, 
            ( 
              SELECT
               count(distinct category_id)
              FROM
               sservdba.brand_category
              WHERE brand_id = id 
            ) related_category_count, 
            related_category_list 
          FROM  
            sservdba.brand,  
            ( 
              SELECT
               bra_syn_brand_id, 
               LTRIM(MAX(SYS_CONNECT_BY_PATH(REPLACE(bra_syn_brand_synonym, ',', '.'), ',')) KEEP (DENSE_RANK LAST ORDER BY curr),',') AS synonym_list 
              FROM
                 (SELECT bra_syn_brand_id, 
                             bra_syn_brand_synonym, 
                             ROW_NUMBER() OVER (PARTITION BY bra_syn_brand_id ORDER BY bra_syn_brand_synonym) AS curr, 
                             ROW_NUMBER() OVER (PARTITION BY bra_syn_brand_id ORDER BY bra_syn_brand_synonym) -1 AS prev 
                      FROM
                         sservdba.brand_synonym) 
                  GROUP BY
                   bra_syn_brand_id 
                  CONNECT BY
                   prev = PRIOR curr AND bra_syn_brand_id = PRIOR bra_syn_brand_id 
                  START WITH curr = 1 
            ) brand_syn, 
            ( 
              SELECT brand_id, 
                     LTRIM(MAX(SYS_CONNECT_BY_PATH(REPLACE(cat_name, '|', '!'), '|')) 
                     KEEP (DENSE_RANK LAST ORDER BY curr),'|') AS RELATED_CATEGORY_LIST 
              FROM   
                (SELECT brand_id, 
                             (select name from sservdba.product_category where id = category_id) cat_name, 
                             ROW_NUMBER() OVER (PARTITION BY brand_id ORDER BY category_id) AS curr, 
                             ROW_NUMBER() OVER (PARTITION BY brand_id ORDER BY category_id) -1 AS prev 
                  FROM
                     sservdba.brand_category) 
                GROUP BY
                 brand_id 
                CONNECT BY
                 prev = PRIOR curr AND brand_id = PRIOR brand_id 
                START WITH curr = 1 
            ) brand_cat, 
            ( 
              SELECT pcb_brand_id, 
                   LTRIM(MAX(SYS_CONNECT_BY_PATH(REPLACE(supplier_name, ' | ', ' ! '), ' | ')) 
                   KEEP (DENSE_RANK LAST ORDER BY curr),' | ') AS owner_list 
            FROM   
              (SELECT pcb_brand_id, 
                           supplier_name, 
                           ROW_NUMBER() OVER (PARTITION BY pcb_brand_id ORDER BY supplier_name) AS curr, 
                           ROW_NUMBER() OVER (PARTITION BY pcb_brand_id ORDER BY supplier_name) -1 AS prev 
                 FROM    
                      ( 
                        SELECT
                         pcb_brand_id, pcb_company_id || '-' || spb_name  supplier_name  
                        FROM
                         sservdba.pages_company_brands, sservdba.supplier_branch 
                        WHERE pcb_auth_level = 'OWN' 
                          AND pcb_is_deleted = 'N' 
                          AND pcb_is_authorised ='Y' 
                          AND pcb_company_id = spb_branch_code 
                      ) 
                    ) 
            GROUP BY
             pcb_brand_id 
            CONNECT BY
             prev = PRIOR curr AND pcb_brand_id = PRIOR pcb_brand_id 
            START WITH curr = 1 
            ) bra_owner 
          WHERE  
          id = brand_syn.bra_syn_brand_id(+) 
          AND id = brand_cat.brand_id(+) 
          AND id = bra_owner.pcb_brand_id(+)  
          )
        )
        ";
   }
		
	public function getData( $rolledUp = false )
	{
		$data = $this->getResultFromSservdbaDb();

		foreach($data as $row)
		{
			$new[] = $row;
		}
				
		return $new;
		
	}
	
	protected function getResultFromSservdbaDb()
	{

	  $params = array();

    $sql = $this->baseSql." SELECT * FROM base";

		$key = __CLASS__ . md5($sql) . print_r($params, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'sservdba');
		
		return $data;
	}

  public function getSummaryData()
  {

    $data = $this->getSummaryResultFromSservdbaDb();


    foreach($data as $row)
    {
      $new[] = $row;
    }
        
    return $new;

  }


  protected function getSummaryResultFromSservdbaDb()
  {

    $params = array();

    $sql = $this->baseSql . " SELECT 

      sum(CASE WHEN brand_name is NOT NULL THEN 1 ELSE 0 END) brand_name,
      sum(CASE WHEN synonym_list is NOT NULL THEN 1 ELSE 0 END) synonym_list,
      sum(CASE WHEN owner_list is NOT NULL THEN 1 ELSE 0 END) owner_list,
      sum(CASE WHEN is_actively_managed = 'Y' THEN 1 ELSE 0 END) is_actively_managed,
      sum(total_supplier_brand_links) total_supplier_brand_links,
      sum(total_authorised_claims) total_authorised_claims,
      sum(listed_only_count) listed_only_count,
      sum(verified_claims) verified_claims,
      sum(claims_where_brand_is_owned) claims_where_brand_is_owned,
      sum(claims_pending_verification) claims_pending_verification,
      sum(CASE WHEN total_supplier_brand_links = 0 THEN 1 ELSE 0 END) total_supplier_brand_links_0,
      avg(total_supplier_brand_links) total_supplier_brand_links_avg,
      count(id) data_count
    FROM
      base";
          
    $key = __CLASS__ . md5($sql) . print_r($params, true);
    $data = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'sservdba');
    
    return $data;
  }
  
}
