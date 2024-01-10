<?php

/**
 * Class for reading the category data from Oracle
 *
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Oracle_Categories extends Shipserv_Oracle
{
    const
        TABLE_NAME = 'PRODUCT_CATEGORY',

        COL_ID   = 'ID',
        COL_NAME = 'NAME'
    ;

	public function __construct (&$db = null)
	{
		if( $db === null )
			$db = $this->getDb();
		parent::__construct($db);
	}
	
	/**
	 * Fetches categories from Oracle beginning with $letter
	 *
	 * @access public
	 * @param string $letter
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchCategory ($id, $useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT PC.*, PC.NAME as displayname,';
		$sql .= '  (select count(*) from pages_category_editor where pce_category_id = PC.id) ownersCount ';
		$sql.= '  FROM PRODUCT_CATEGORY PC';
		$sql.= ' WHERE PC.ID = :id';
		$sql.= ' ORDER BY PC.NAME ASC';
		
		$sqlData = array('id' => (int)$id);
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CATID_'.$id.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}
		
		return $result[0];
	}
	
	/**
	 * Fetches categories from Oracle by id
	 *
	 * @access public
	 * @param string $letter
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetch ($id, $useCache = true, $cacheTTL = 86400)
	{
		return $this->fetchCategory ($id, $useCache, $cacheTTL);
	}

	/**
	 * Fetches categories from Oracle beginning with $letter
	 *
	 * @access public
	 * @param string $letter
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchCategories ($letter, $useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT PC.NAME, PC.ID, PC.NAME as displayname,';
		$sql.= '       PC.BROWSE_PAGE_NAME, PC.PAGE_TITLE_NAME, PC.REFINED_SEARCH_DISPLAY_NAME';
		$sql.= '  FROM PRODUCT_CATEGORY PC';
		$sql.= ' WHERE upper(PC.NAME) like :match';
		$sql.= ' ORDER BY PC.NAME ASC';
		
		$sqlData = array('match' => strtoupper($letter).'%');
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CATEGORIESBYLETTER_'.$letter.
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
	 * Performs a CATSEARCH of names in the product table
	 * 
	 * @access public
	 * @param string $search
	 * @return array
	 */
	public function search ($search)
	{
		$sql = 'SELECT PC.ID, PC.NAME';
		$sql.= '  FROM PRODUCT_CATEGORY PC';
		$sql.= ' WHERE CATSEARCH(PC.NAME, :name, null) > 0';
		$sql.= ' ORDER BY PC.NAME ASC';


		// format the search string so each word is split and appended with a * wildcard
		$name = '';
		$searchTerms = explode(' ', $search);
		foreach ($searchTerms as $term)
		{
			$name.= $term.'* ';
		}
		
		$sqlData = array('name' => $name);
		
		return $this->db->fetchAll($sql, $sqlData);
	}

	/**
	 * Perform category search by using synonyms
	 * 
	 * @access public
	 * @param string $search
	 * @return array
	 */
	public function synonymSearch( $search )
	{
		$sql = "
		  SELECT * FROM
			(
			  SELECT 
			    name,
			    cat_syn_category_synonym synonyms
			  FROM 
			    product_category
			    LEFT JOIN category_synonym ON cat_syn_category_id = ID
			  ORDER BY 
			    cat_syn_category_synonym ASC
			) tbl
		  WHERE 
		";
		
		$name = '';
		$searchTerms = split(' ', $search);
		foreach ($searchTerms as $term)
		{
			$conditions[] = "(  LOWER( tbl.name ) LIKE '%$term%' OR LOWER( tbl.synonyms ) LIKE '%$term%' OR SOUNDEX( tbl.name ) = SOUNDEX('$term') OR SOUNDEX( tbl.synonyms ) = SOUNDEX('$term') )";
		}
		
		$sql .= implode(" AND ", $conditions);		
		return $this->db->fetchAll($sql);
		
	}
	
	
	/**
	 * Fetches the category hierarchy
	 *
	 * @access public
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchNestedCategories ($useCache = true, $cacheTTL = 86400)
	{
		$sql = ' SELECT PC.ID, PC.NAME, PC.NAME AS DISPLAYNAME, PC.PARENT_ID, level as depth,';
		$sql.= '        substr(replace(SYS_CONNECT_BY_PATH(decode(PC.BROWSE_PAGE_NAME, null, pc.name, PC.BROWSE_PAGE_NAME), \'>\'), \'>\', \'/\'), 2) path,';
		$sql.= '		substr(replace(SYS_CONNECT_BY_PATH(decode(PC.id, null, pc.name, PC.id), \'>\'), \'>\', \'/\'), 2) path_id,';
		$sql.= '        PC.BROWSE_PAGE_NAME, PC.PAGE_TITLE_NAME, PC.REFINED_SEARCH_DISPLAY_NAME';
		$sql.= '   FROM PRODUCT_CATEGORY PC';
		$sql.= '  START WITH PC.PARENT_ID IS NULL ';
		$sql.= 'CONNECT BY PRIOR PC.ID = PC.PARENT_ID';
		$sql.= '  ORDER BY path ASC';

		$sqlData = array();

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'NESTEDCATS_'.
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
	 * Fetches subcategories
	 *
	 * @access public
	 *
	 * @param integer $categoryId Parent category id
	 *
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchSubCategories ($categoryId, $useCache = true, $cacheTTL = 86400, $exclusionIds = array())
	{
		$sql = ' SELECT PC.ID, PC.NAME, (select count(*) from product_category where PARENT_ID = PC.ID) as childCount ';
		$sql.= ' FROM PRODUCT_CATEGORY PC ';
		if (!is_null($categoryId))
		{
			$sql.= ' WHERE PC.PARENT_ID = :categoryId ';
			$sqlData = array('categoryId' => (int)$categoryId);
			
			if( count($exclusionIds) > 0 )
			{
				$sql .= ' AND pc.ID NOT IN (' . implode(",", $exclusionIds) . ')';
			}

		}
		else
		{
			$sql.= ' WHERE PC.PARENT_ID IS NULL ';
			$sqlData = array();
		}

		$sql.= ' ORDER BY PC.NAME ASC';
		
		
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'SUBCATEGORIES_'.$categoryId.
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
	 * Fetches a list of countries for which there are valid search matches for a specific category
	 *
	 * @access public
	 * @param int $brandId The database ID of the brand
	 * @return array
	 */
	public function fetchCountriesForCategory ($categoryId, $useCache = false, $cacheTTL = 86400)
	{
		$sql = 'SELECT DISTINCT(pbl_cnt_country_code) as cnt_code, C.CNT_NAME,';
		$sql.= '       PC.NAME as displayname, PC.NAME, PC.ID,';
		$sql.= '       PC.BROWSE_PAGE_NAME, PC.PAGE_TITLE_NAME, PC.REFINED_SEARCH_DISPLAY_NAME,';
		$sql.= '       CON.CON_CODE, CON.CON_NAME';
		$sql.='   FROM PAGES_BROWSE_LINK PBL, PRODUCT_CATEGORY PC, COUNTRY C, CONTINENT CON';
		$sql.= ' WHERE PBL.PBL_CATEGORY_ID = PC.ID';
		$sql.= '   AND PBL.PBL_CNT_COUNTRY_CODE = C.CNT_COUNTRY_CODE';
		$sql.= '   AND C.CNT_CON_CODE = CON.CON_CODE';
		$sql.= '   AND PBL_BROWSE_TYPE = :browseType';
		$sql.= '   AND PBL_CATEGORY_ID = :categoryId';
		$sql.= ' ORDER BY CON.CON_NAME ASC, C.CNT_NAME ASC';
		$sqlData = array('browseType' => 'category',
						 'categoryId' => (int)$categoryId);
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CNTCAT_'.$categoryId.
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
				$continents[$country['CON_CODE']]['countries'][$country['CNT_CODE']] = array('name' => $country['CNT_NAME']);
			}
			
			$result = $continents;
		}
		
		return $result;
	}
	
	public function fetchPortsForCategory($categoryId, $countryCode, $useCache = false, $cacheTTL = 86400)
	{
		$sql = "
			SELECT
			  DISTINCT 
			    country.*
			    , port.*
			FROM
			  supply_category
			  , supplier_port
			  , port
			  , country
			WHERE
			  product_category_id=:categoryId
			  AND spp_spb_branch_code=supplier_branch_code
			  AND port.prt_port_code=spp_prt_port_code
			  AND port.prt_cnt_country_code=:countryCode
			  AND cnt_country_code=prt_cnt_country_code
  		";	
		
		$sqlData = array(
			"categoryId" => (int)$categoryId,
			"countryCode" => $countryCode
		);
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CNTCAT_'.$categoryId.
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
	 * Fetches categories from Oracle flagged with IS_POPULAR
	 *
	 * @access public
	 * @param string $letter
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchPopularCategories ($useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT PC.NAME, PC.ID, PC.NAME as displayname,';
		$sql.= '       PC.BROWSE_PAGE_NAME, PC.PAGE_TITLE_NAME, PC.REFINED_SEARCH_DISPLAY_NAME,';
		$sql.= '       decode(PC.BROWSE_PAGE_NAME, null, pc.name, PC.BROWSE_PAGE_NAME) as tmp';
		$sql.= '  FROM PRODUCT_CATEGORY PC';
		$sql.= ' WHERE PC.IS_POPULAR = 1';
		$sql.= ' ORDER BY tmp ASC';
		
		$sqlData = array();
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'POPULARCATEGORIES_'.
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
	 * Fetches the top categories from Oracle (as defined in the PAGES_TOP_CATEGORY table)
	 * 
	 * @access public
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchTopCategories ($useCache = false, $cacheTTL = 86400)
	{
		$sql = 'SELECT PTC.PTC_RANK, PTC.PTC_DISPLAY_TITLE as displayname,';
		$sql.= '       PC.NAME, PC.ID, PC.BROWSE_PAGE_NAME, PC.PAGE_TITLE_NAME,';
		$sql.= '       PC.REFINED_SEARCH_DISPLAY_NAME';
		$sql.= '  FROM PAGES_TOP_CATEGORY PTC, PRODUCT_CATEGORY PC';
		$sql.= ' WHERE PTC.PTC_CATEGORY_ID = PC.ID';
		$sql.= ' ORDER BY PTC.PTC_RANK ASC';

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'TOPCATEGORIES_'.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, array(), $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql);
		}
		
		return $result;
	}
	
	public function showDisplayByPortPage($categoryId)
	{
	
		$config = Zend_Registry::get('config');
		
		$enabledCategories = explode(",", $config->shipserv->seo->browseByPort->category->ids);
		
		if( array_search($categoryId, $enabledCategories) !== false ) return true;
		$sql = "
			SELECT
			  1 + FLOOR(
			    (SYSDATE - TO_DATE(:releaseDate))/30
			  ) different_in_months
			FROM dual
		";
		
		$month = $this->db->fetchOne($sql, array('releaseDate' => $config->shipserv->seo->browseByPort->releaseDate));
		
		$sql = "
			SELECT y.*
			FROM
			  (SELECT x.*
			  FROM
			    (SELECT
			      PC.ID catId,
			      DECODE(PC.BROWSE_PAGE_NAME, NULL, pc.name, PC.BROWSE_PAGE_NAME) AS tmp
			    FROM PRODUCT_CATEGORY PC
			    WHERE PC.IS_POPULAR = 1
			    ORDER BY tmp ASC
			    ) x
			  WHERE rownum<= "  . ( $month * 10) . "
			  ) y
			WHERE y.catId=:categoryId
		";
		
		return ( ($this->db->fetchOne($sql, array('categoryId' => (int)$categoryId) ) != "" ) ? true:false);
	}

    /**
     * Added by Attila's request to power his item selector control
     *
     * @author  Yuriy Akopov
     * @date    2014-06-18
     * @story   S9770
     *
     * @return  array
     */
    public static function fetchAllCategories() {
        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('pc' => self::TABLE_NAME),
                array(
                    self::COL_ID,
                    self::COL_NAME
                )
            )
            ->order(self::COL_NAME)
        ;

        $rows = $db->fetchAll($select);

        return $rows;
    }
}
