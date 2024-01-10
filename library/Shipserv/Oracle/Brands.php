<?php

/**
 * Class for reading the brand data from Oracle
 * 
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Oracle_Brands extends Shipserv_Oracle
{
    const
        CACHE_KEY_ALL_BRANDS = 'ALLBRANDS'
    ;

	public function __construct (&$db = null)
	{
		if( $db === null )
			$db = $this->getDb();
				
		parent::__construct($db);
	}
	
	public function fetchBrand ($id, $useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT B.*, B.NAME as displayname,';
		$sql .= " (select count(*) from pages_company_brands where pcb_brand_id = B.id and pcb_auth_level='OWN' and pcb_is_authorised='Y') ownersCount ";
		$sql.= '  FROM BRAND B';
		$sql.= ' WHERE B.ID = :id';
		$sql.= ' ORDER BY B.NAME ASC';
		
		$sqlData = array('id' => $id);
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'BRANDID_'.$id.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}
		
		return $result[0];
	}
	
	public function fetch ($id, $useCache = true, $cacheTTL = 86400)
	{
		return $this->fetchBrand($id, $useCache, $cacheTTL);
	}
	
	public function fetchBrandByName ($name)
	{
		$sql = 'SELECT B.NAME, B.ID, B.NAME as displayname, B.BROWSE_PAGE_NAME,';
		$sql.= '       B.PAGE_TITLE_NAME, B.REFINED_SEARCH_DISPLAY_NAME';
		$sql.= '  FROM BRAND B';
		$sql.= ' WHERE B.NAME LIKE :name';
		$sql.= ' ORDER BY B.NAME ASC';
		
		$sqlData = array('name' => $name);
		
		$result = $this->db->fetchAll($sql, $sqlData);
		
		return current($result);
	}
	
	/**
	 * Performs a CATSEARCH of names in the brand table
	 * 
	 * @access public
	 * @param string $name
	 * @return array
	 */
	public function search ($search = "")
	{
		
		$key = $this->memcacheConfig->client->keyPrefix . 'SVR_BRAND_SEARCH_' . $search . $this->memcacheConfig->client->keySuffix;
						
		$sql = 'SELECT B.ID, (B.NAME),';
		$sql .= " (select count(*) from pages_company_brands where pcb_brand_id = b.id and pcb_auth_level='OWN' and pcb_is_authorised='Y') ownersCount ";
		$sql.= '  FROM BRAND B';
		
		if( $search != "" )
		{
			$sql.= ' WHERE CATSEARCH(B.NAME, :name, null) > 0';
		}
		$sql.= ' ORDER BY UPPER(B.NAME) ASC';
		
		$sqlData = array();
		
		if( $search != "" )
		{
			// format the search string so each word is split and appended with a * wildcard
			$name = '';
			$searchTerms = explode(' ', $search);
			foreach ($searchTerms as $term)
			{
				$name.= $term.'* ';
			}
			
			$sqlData = array('name' => $name);
			
		}
		
		return $data = $this->fetchCachedQuery($sql, $sqlData, $key, 86400);;
	}
	
	/**
	 *
	 *
	 *
	 *
	 */
	public function fetchBrandsWildcarded ($name)
	{
		$sql = 'SELECT B.ID, B.NAME';
		$sql.= '  FROM BRAND B';
		$sql.= ' WHERE UPPER(B.NAME) LIKE :name';
		$sql.= ' ORDER BY B.NAME ASC';
		
		$sqlData = array('name' => '%'.strtoupper($name).'%');
		
		return $this->db->fetchAll($sql, $sqlData);
	}
	
	/**
	 * Fetches brands from Oracle beginning with $letter
	 *
	 * Adds to table columns:
	 * 	HAS_PRODUCTS Y|N
	 * 
	 * @access public
	 * @param string $letter
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchBrands ($letter, $startsWithNonAlpha, $useCache = true, $cacheTTL = 86400)
	{
		$sql = "SELECT b.*, b.name as displayname, " . $this->hasProductsSql('b.ID') . ' HAS_PRODUCTS,';
		$sql .= " (select count(*) from pages_company_brands where pcb_brand_id = b.id and pcb_auth_level='OWN' and pcb_is_authorised='Y') ownersCount ";
		$sql.= '  FROM brand b';
		
		$sql.= ' WHERE UPPER(b.name) like :atozpattern';
        $sql.= '   AND REGEXP_SUBSTR(LOWER(SUBSTR(TRIM(b.name), 0, 1)), :regexnonalphapattern) IS NOT NULL';
		
		$sql.= ' ORDER BY B.NAME ASC';
		
		if ($startsWithNonAlpha) {
			$sqlData = array('atozpattern'          => '%',
							 'regexnonalphapattern' => '^[^a-z]');
		} 
		else
		{
			$sqlData = array('atozpattern'          => strtoupper(trim($letter)).'%',
							 'regexnonalphapattern' => '^[a-z]');
		}
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'BRANDSBYLETTER_'.$letter.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}
		
		return $result;
	}
	
	/**
	 * Fetches a list of countries for which there are valid search matches for a specific brand
	 *
	 * @access public
	 * @param int $brandId The database ID of the brand
	 * @return array
	 */
	public function fetchCountriesForBrand ($brandId, $useCache = false, $cacheTTL = 86400)
	{
		$sql = 'SELECT DISTINCT(pbl_cnt_country_code) as cnt_code,';
		$sql.= '       CON.CON_CODE, CON.CON_NAME,';
		$sql.= '       C.CNT_COUNTRY_CODE, C.CNT_NAME';
		$sql.='   FROM PAGES_BROWSE_LINK PBL, BRAND B, COUNTRY C, CONTINENT CON';
		$sql.= ' WHERE PBL.PBL_BRAND_ID = B.ID';
		$sql.= '   AND PBL.PBL_CNT_COUNTRY_CODE = C.CNT_COUNTRY_CODE';
		$sql.= '   AND C.CNT_CON_CODE = CON.CON_CODE';
		$sql.= '   AND PBL_BROWSE_TYPE = :browseType';
		$sql.= '   AND PBL_BRAND_ID = :brandId';
		$sql.= ' ORDER BY CON.CON_NAME ASC, C.CNT_NAME ASC';
		
		$sqlData = array('browseType' => 'brand',
						 'brandId'    => $brandId);
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CNTBRAND_'.$brandId.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
			
			
			$continents = array();
			foreach ($result as $country)
			{
				$continents[$country['CON_CODE']]['name'] = $country['CON_NAME'];
				$continents[$country['CON_CODE']]['countries'][$country['CNT_COUNTRY_CODE']] = array('name' => $country['CNT_NAME']);
			}
			
			$result = $continents;
			
		}
		
		return $result;
	}
	
	/**
	 * Fetches a list of countries for which there are valid search matches for a specific brand
	 * 
	 * @access public
	 * @param int $brandId The database ID of the brand
	 * @param string $countryCode The country code
	 * @return array
	 */
	public function fetchPortsForBrand ($brandId, $countryCode, $useCache = false, $cacheTTL = 86400)
	{
		$sql = 'SELECT DISTINCT(pbl_prt_port_code) as port_code,';
		$sql.= '       B.NAME as displayname, B.NAME, B.ID,';
		$sql.= '       C.CNT_NAME as country,';
		$sql.= '       P.PRT_NAME AS port_name';
		$sql.='   FROM PAGES_BROWSE_LINK PBL, BRAND B, COUNTRY C, PORT P';
		$sql.= ' WHERE PBL.PBL_BRAND_ID = B.ID';
		$sql.= '   AND PBL.PBL_CNT_COUNTRY_CODE = C.CNT_COUNTRY_CODE';
		$sql.= '   AND PBL.PBL_PRT_PORT_CODE = P.PRT_PORT_CODE';
		$sql.= '   AND PBL_BROWSE_TYPE = \'brand\'';
		$sql.= '   AND PBL_BRAND_ID = :brandId';
		$sql.= '   AND PBL_CNT_COUNTRY_CODE = :countryCode';
		
		$sqlData = array('brandId'     => $brandId,
						 'countryCode' => $countryCode);
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'PRTBRAND_'.$brandId.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}
		
		return $result;
	}
	
	/**
	 * Adds to table columns:
	 *	HAS_PRODUCTS Y|N
	 */
	public function fetchPopularBrands ($useCache = false, $cacheTTL = 86400)
	{
		$sql = 'SELECT B.NAME, B.ID, B.NAME as displayname, B.BROWSE_PAGE_NAME, B.LOGO_FILENAME, ';
		
		$sql.= "       B.PAGE_TITLE_NAME, B.REFINED_SEARCH_DISPLAY_NAME, " . $this->hasProductsSql('b.ID') . ' HAS_PRODUCTS';
		$sql.= '  FROM BRAND B';
		$sql.= ' WHERE B.IS_POPULAR = 1';
		$sql.= ' ORDER BY B.NAME ASC';
		
		$sqlData = array();
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'POPULARBRANDS_'.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}
		
		return $result;
	}
	
	/**
	 * Fetches the top brands from Oracle (as defined in the PAGES_TOP_BRAND table)
	 * 
	 * @access public
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchTopBrands ($useCache = false, $cacheTTL = 86400)
	{
	    $sql = "SELECT
                  PTB.PTB_RANK,
                  PTB.PTB_DISPLAY_TITLE as displayname,
                  PTB.PTB_URL,
                  B.BROWSE_PAGE_NAME,
                  B.PAGE_TITLE_NAME,
                  B.REFINED_SEARCH_DISPLAY_NAME,
                  B.NAME,
                  B.ID,
                    (
                    CASE WHEN PTB.PTB_URL IS NULL THEN
                        CASE WHEN B.BROWSE_PAGE_NAME IS NOT NULL THEN
                          '/brand/' || utl_url.escape(LOWER(REGEXP_REPLACE(REGEXP_REPLACE(B.BROWSE_PAGE_NAME,'(\W){1,}','-'),'/-$/',''))) || '/' || B.ID
                        ELSE
                          '/brand/' || utl_url.escape(LOWER(REGEXP_REPLACE(REGEXP_REPLACE(B.NAME,'(\W){1,}','-'),'/-$/',''))) || '/' || B.ID
                        END
                    ELSE
                      PTB.PTB_URL
                    END
                    ) BRAND_URL
                FROM
                  PAGES_TOP_BRAND PTB, BRAND B
                WHERE
                  PTB.PTB_BRAND_ID = B.ID
                ORDER BY
                  PTB.PTB_RANK ASC";

		if ($useCache) {
			$key = $this->memcacheConfig->client->keyPrefix . 'TOPBRANDS_'.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
		} else {
			$result = $this->db->fetchAll($sql);
		}
		
		return $result;
	}
	
	/**
	 * Makes SQL column expression indicating whether products are present for
	 * given brand.
	 * 
	 * e.g.
	 * SELECT a.*, hasProductsSql('a.ID') FROM BRAND a
	 *
	 * Note, $brandId is NOT escaped (so that a SQL expression may be passed).
	 * 
	 * @param string $brandId
	 * @return string
	 */
	private function hasProductsSql ($brandId)
	{
		$expr = "SELECT COUNT(*) FROM PAGES_PRODUCT p INNER JOIN PAGES_BRAND_PRODUCT bp ON p.ID = bp.PRODUCT_ID WHERE bp.BRAND_ID = $brandId";
		return "DECODE(($expr), 0, 'N', 'Y')";
	}

	/**
	 * Updates Logo Image
	 *
	 * @param string $imageName
	 * @param integer $membershipId
	 * @return boolean
	 */
	public function updateImage ($imageName,$id)
	{
		$sql = "UPDATE BRAND SET";
		$sql.= " LOGO_FILENAME = :imageName";
		$sql.= " WHERE ID=:id";

		$sqlData = array(
			'imageName'	=> $imageName,
			'id' => $id
		);
		$this->db->query($sql,$sqlData);



		$key = $this->memcacheConfig->client->keyPrefix . 'BRANDID_'.$id.
			       $this->memcacheConfig->client->keySuffix;

		$this->purgeCache($key);

		return true;
	}
	
	public function fetchAuthLevelAnalytics( $brandId, $useCache = false, $cacheTTL = 86400 )
	{
		// get quick statistic of this brand
		$sql = "
			SELECT
			  pcb_auth_level as AUTH_LEVEL 
			  , count(*) as TOTAL
			FROM 
			  pages_company_brands
			WHERE
			  pcb_brand_id = :brandId
			GROUP BY
			  pcb_auth_level
		";
		$sqlData = array(
			"brandId" => $brandId
		);
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'BRANDANALYTICS_'. $brandId .
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}
		
		foreach( $result  as $row ){
			$data[ $row["AUTH_LEVEL"] ] = $row["TOTAL"];
		}
		
		return $data;
	}
	
	public function fetchSuppliers( $brandId, $random = true, $rowNum = null, $authorised = true, $useCache = false, $cacheTTL = 86400 )
	{
		
		
		
		
		$sql = "
		

SELECT 
  * 
FROM 
  pages_company_brands
WHERE
  pcb_company_id IN
  ( 
    SELECT pcb_company_id
    FROM
    (
  
      SELECT * FROM ( 
          SELECT
            pcb_company_id
             ,ROW_NUMBER() OVER (ORDER BY dbms_random.random) rn
           
          FROM 
            pages_company_brands
          WHERE
            pcb_brand_id = 1
            AND pcb_auth_level IS NOT NULL 
            AND pcb_auth_level NOT IN ('LST','OWN')   
          GROUP BY 
            pcb_company_id
          ORDER BY dbms_random.random 
      
      ) 
      WHERE 
        rn BETWEEN 0 AND 3
    )
  )
  AND pcb_brand_id = 1
  AND pcb_auth_level IS NOT NULL 
  AND pcb_auth_level NOT IN ('LST','OWN')   
		
		
		";
		
		$sql = "
		SELECT 
		  * 
		FROM 
		  pages_company_brands
		WHERE
		  pcb_company_id IN
		  ( 
				
				SELECT pcb_company_id
			    FROM
			    (
			   	  /* -- grab top 3 (if rownum is specified ) */
			      SELECT * FROM ( 
			          /* -- getting random 3 companies that are claiming to be authorised */
			      	  SELECT
			            pcb_company_id
			            " . ( ( $random === true ) ? ",ROW_NUMBER() OVER (ORDER BY dbms_random.random) rn":",rownum rn") . "
			          FROM 
			            pages_company_brands
			          WHERE
			            pcb_brand_id = :brandId
			            AND pcb_auth_level IS NOT NULL 
			            " . ( ( $authorised === true) ? "AND pcb_auth_level NOT IN ('LST','OWN')":"") . "
			          GROUP BY 
			            pcb_company_id
			          " . ( ( $random === true ) ? "ORDER BY dbms_random.random":"" ) . "
			      ) 
			      " . ( ( $rowNum !== null ) ? "WHERE rn BETWEEN 0 AND " . $rowNum:"" ) . "
			    )
		  )
		  AND pcb_company_id NOT IN( SELECT psn_spb_branch_code FROM pages_spb_norm )
		  AND pcb_brand_id = :brandId
		  AND pcb_auth_level IS NOT NULL 
		  AND pcb_auth_level NOT IN ('LST','OWN')   
			    
		";
				
		$sqlData = array(
			"brandId" => $brandId
		);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'BRANDANALYTICS_'. $brandId .
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}
		return $result;
		
	}

    /**
     * Fetches base information about all the available brands in one chunk
     * Based on the similar legacy function in Shipserv_Oracle_Categories
     *
     * @author  Yuriy Akopov
     * @date    2013-09-25
     * @story   S7903
     *
     * @param   bool    $useCache
     * @param   int     $cacheTTL
     *
     * @return  array
     */
    public function fetchAllBrands($useCache = true, $cacheTTL = 86400) {
        $select = new Zend_Db_Select($this->db);
        $select
            ->from(
                array('b' => Shipserv_Brand::TABLE_NAME),
                array(
                    'id'    => 'b.' . Shipserv_Brand::COL_ID,
                    'name'  => 'b.' . Shipserv_Brand::COL_NAME
                )
            )
            ->order('b.' . Shipserv_Brand::COL_NAME)
        ;

        // cached query function expects text queries (hasn't been rewritten yet)
        $sql = $select->assemble();

        if ($useCache) {
            $key = $this->makeKey(__CLASS__, __FUNCTION__);
            $rows = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
        } else {
            $rows = $this->db->fetchAll($sql, array());
        }

        return $rows;
    }

}
