<?php

/**
 * Class for reading supplier data from Oracle. Heavy queries for things like the competitors list are retreived from Standby rather than
 * live to ensure lower load during Search Engine indexing. 
 * 
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Oracle_Suppliers extends Shipserv_Oracle
{
	private $standbyDb;
	public function __construct (&$db)
	{
		parent::__construct($db);
		$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
		$this->standbyDb = $resource->getDb('standbydb');
	}
	
	/**
	 * Takes array of raw branch codes and returns array of normalized branch codes.
	 * Non-canonicals are removed.
	 * 
	 * @return array
	 */
	private function normalize (array $rawBranchCodes)
	{
		$normalizer = new Shipserv_Oracle_UserCompanies_NormSupplier($this->db);
		foreach ($rawBranchCodes as $id)
		{
			$normalizer->addId($id);
		}
		$nMap = $normalizer->canonicalize();
		
		$nIds = array();
		foreach ($rawBranchCodes as $nId)
		{
			if ($nId !== null)
		{
				$nIds[] = $nId;
			}
		}
		return $nIds;
	}
	
	/**
     * Changed by Yuriy Akopov on 2016-11-23, DE7116
     *
	 * @param   array   $ids
     * @param   bool    $skipCheck
     *
	 * @return  array
	 */
	public function fetchSuppliersByIds(array $ids, $skipCheck = false )
	{
        $db = self::getDb();
        $select = new Zend_Db_Select($db);

        $select
            ->from(
                array('spb' => Shipserv_Supplier::TABLE_NAME),
                'spb.*'
            )
            ->joinLeft(
                array('pco' => 'pages_company'),
                implode(
                    ' AND ',
                    array(
                        'spb.' . Shipserv_Supplier::COL_ID . ' = pco.pco_id',
                        $db->quoteInto('pco.pco_type = ?', 'SPB')
                    )
                ),
                'pco.*'
            )
            ->where('spb.' . Shipserv_Supplier::COL_ID . ' IN (?)', $ids)
        ;

		if (!$skipCheck) {
            // validity constraints replaced older normalisation check below:
            // AND SPB_BRANCH_CODE NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)

            $select->where(Shipserv_Supplier::getValidSupplierConstraints('spb'));
		}
		
		return $this->db->fetchAll($select);
	}
	
	/**
	 * Returns all inactive suppliers
	 */
	public function fetchUnverifiedSupplierIds( $total = "" )
	{
	    $total = (Int) $total;
		$sqlInner = "
			SELECT 
			a.SPB_BRANCH_CODE, 
		    a.SPB_INT_VERIFIED_DATE, 
		    CAST( a.DIRECTORY_LIST_VERIFIED_AT_UTC AS DATE) AS DIRECTORY_LIST_VERIFIED_AT_UTC,
		    (
		      CASE 
		        WHEN a.DIRECTORY_LIST_VERIFIED_AT_UTC IS NOT NULL 
		          THEN SYSDATE - CAST( a.DIRECTORY_LIST_VERIFIED_AT_UTC AS DATE )
		          		        
		        WHEN a.DIRECTORY_LIST_VERIFIED_AT_UTC IS NULL 
		          THEN SYSDATE - CAST( a.SPB_CREATED_DATE AS DATE )
		      END
		    ) AS Q_AGE
		  	FROM SUPPLIER_BRANCH a";	
		$sqlInner .= " WHERE ";
			$sqlInner.= " directory_entry_status = 'PUBLISHED'";
			$sqlInner.= " AND directory_listing_level_id IN( 1,2,3,5 )";
			$sqlInner.= " AND spb_account_deleted = 'N'";
			$sqlInner.= " AND spb_test_account = 'N'";
			$sqlInner.= " AND spb_branch_code <= 999999";
			$sqlInner.= " AND spb_branch_code NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)";

		$sql = "SELECT SPB_BRANCH_CODE FROM ($sqlInner)"; 
		$sql .= " WHERE Q_AGE > 365";
		if( $total != "" )
		$sql .= " AND rownum <= " . $total;
		$sql .= " ORDER BY Q_AGE ASC";

	 	return $this->db->fetchAll($sql);
	}

	
	public function isVerifiedById( $id )
	{
		$sqlInner = "
			SELECT 
			rownum AS rn,
			a.SPB_BRANCH_CODE, 
		    a.SPB_INT_VERIFIED_DATE, 
		    CAST( a.DIRECTORY_LIST_VERIFIED_AT_UTC AS DATE) AS DIRECTORY_LIST_VERIFIED_AT_UTC,
		    (
		      CASE 
		        WHEN a.DIRECTORY_LIST_VERIFIED_AT_UTC IS NOT NULL 
		          THEN SYSDATE - CAST( a.DIRECTORY_LIST_VERIFIED_AT_UTC AS DATE )
		          		        
		        WHEN a.DIRECTORY_LIST_VERIFIED_AT_UTC IS NULL 
		          THEN SYSDATE - CAST( a.SPB_CREATED_DATE AS DATE )
		      END
		    ) AS Q_AGE
		  	FROM SUPPLIER_BRANCH a";	
		$sqlInner .= " WHERE ";
			$sqlInner.= " directory_entry_status = 'PUBLISHED'";
			$sqlInner.= " AND spb_account_deleted = 'N'";
			$sqlInner.= " AND spb_test_account = 'N'";
			$sqlInner.= " AND spb_branch_code <= 999999";
			$sqlInner.= " AND spb_branch_code NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)";
			$sqlInner .= " AND spb_branch_code = :id";
		$sql = "SELECT SPB_BRANCH_CODE FROM ($sqlInner)"; 
		$sql .= " WHERE Q_AGE > 365";
		$sql .= " ORDER BY Q_AGE ASC";
		
		$sqlData = array( "id" => $id );

		$results = $this->db->fetchAll($sql, $sqlData);
		//return $results;
		
		
		
		// if found then return false meaning that id isn't verified otherwise return true
		return ( count( $results ) > 0 ) ? false:true;
	}
	
	public function getLastUpdatedProfile( $id )
	{
		$sqlInner = "
			SELECT 
		    a.SPB_INT_VERIFIED_DATE, 
		    CAST( a.DIRECTORY_LIST_VERIFIED_AT_UTC AS DATE) AS DIRECTORY_LIST_VERIFIED_AT_UTC,
		    (
		      CASE 
		        WHEN a.DIRECTORY_LIST_VERIFIED_AT_UTC IS NOT NULL 
		          THEN SYSDATE - CAST( a.DIRECTORY_LIST_VERIFIED_AT_UTC AS DATE )
		          		        
		        WHEN a.DIRECTORY_LIST_VERIFIED_AT_UTC IS NULL 
		          THEN SYSDATE - CAST( a.SPB_CREATED_DATE AS DATE )
		      END
		    ) AS Q_AGE
		    FROM SUPPLIER_BRANCH a";	
		$sqlInner .= " WHERE ";
			$sqlInner .= "  a.spb_branch_code = :id";
		
		$sqlData = array( "id" => $id );

		$results = $this->db->fetchAll($sqlInner, $sqlData);

		// if found then return false meaning that id isn't verified otherwise return true
		return floor($results[0]['Q_AGE']);
		
	}
	
	/**
	 * @param int $id branch code
	 * @param bool $normalize If true, method attempts to canonicalize ID first (be preapred for return row's ID not to match requested ID!).
	 * @return array
	 * @throws Exception if ID not found
	 */
	public function fetchSupplierById ($id, $normalize = false, $skipCheck = false)
	{
		// If instructed to normalize, attempt to replace $id with canoncial ID.
		if ($normalize)
		{
			$rawId = $id;
			$nIds = $this->normalize(array($id));
			if (!$nIds)
			{
				throw new Exception("Unable to normalize ID");
			}
			$id = $nIds[0];
		}
		
		$sArr = $this->fetchSuppliersByIds(array($id), $skipCheck);
		if (!$sArr) {
			//var_dump(debug_backtrace());
			//die();
			throw new Exception("Supplier branch not found for code: '$id'");
		}
		return $sArr[0];
	}
	
	/**
	 * Transforms values of input array into a quoted list suitable for
	 * a SQL in clause: e.g. 3, 'str val', ...
	 *
     * @param   array   $arr
     *
	 * @return string
	 */
	protected function arrToSqlList ($arr)
	{
		$sqlArr = array();
		foreach ($arr as $item) {
			$sqlArr[] = $this->db->quote($item);
		}

		if (!$sqlArr) {
            $sqlArr[] = 'NULL';
        }

		return join(', ', $sqlArr);
	}
	
	/**
	 * Fetches a list of coquoters for a given supplier, ranked by how many
	 * shared RFQs they've received
	 * 
	 * @access public
	 * @param int $tnid The TradeNet ID of the supplier for which coquoters should be fetched
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchCoquoters ($tnid, $useCache = true, $cacheTTL = 86400)
	{
		$sql = 'SELECT psc.competitor_tnid AS tnid, psc.rfq_count AS rank';
		$sql.= '  FROM pages_supplier_coquoter psc, supplier_branch sb,';
		$sql.= '       directory_listing_level dll';
		$sql.= ' WHERE supplier_tnid = :supplier_tnid';
		$sql.= '   AND sb.spb_branch_code = psc.competitor_tnid';
		$sql.= '   AND sb.directory_listing_level_id = dll.id';
		
		// order by listing level (i.e. premiums first),
		// then by rank (which is a count of how many matching categories there are)
		// then by TradeRank
		$sql.= ' ORDER BY dll.level_of_importance DESC,';
		$sql.= '          rank DESC,';
		$sql.= '          sb.spb_trade_rank DESC';
		
		$sqlData = array('supplier_tnid' => $tnid);
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'COQUOTERS_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->standbyDb->fetchAll($sql, $sqlData);
		}
		
		return $result;
	}
	
	/**
	 * This will pull out all suppliers who have the same country as the TNID,
	 * and rank them by how many matching categories there are. 
	 * It will only return published, non-deleted. non-test accounts.
	 * 
	 * @access public
	 * @param int $tnid The TradeNet ID of the supplier for which category/country
	 *                  matches should be fetched
	 * @param array $excludeTnids Array of TNIDs that should be excluded, so as to
	 * 				              prevent previous matches being selected
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched
	 *                          from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchCategoryCountryMatches ($tnid, $excludeTnids,
												 $useCache = true,
												 $cacheTTL = 86400)
	{
		$excludeTnids[] = $tnid;
		
		$sql.= 'SELECT sb.spb_branch_code AS tnid,';
		// the rank is based on a percentage of the matching categories to the
		// total categories of the matched supplier
		$sql.= '       ( count(sb.spb_branch_code) /
					     (select count(*) from supply_category where supplier_branch_code = sb.spb_branch_code)
					   ) AS rank';
		
		$sql.= '  FROM supplier_branch sb, supply_category sc,';
		$sql.= '       product_category pc, supplier_branch origSb,';
		$sql.= '       directory_listing_level dll';
		$sql.= ' WHERE sb.directory_listing_level_id = dll.id';
		$sql.= '   AND sc.supplier_org_code = sb.spb_sup_org_code';
		$sql.= '   AND sc.supplier_branch_code = sb.spb_branch_code';
		$sql.= '   AND pc.id = sc.product_category_id';
		
		// only fetch if they have a product category that falls within the
		// product categories of the original supplier
		$sql.= '   AND sc.product_category_id in (';
		$sql.= '	     SELECT p.id';
		$sql.= '		   FROM product_category p, supply_category s';
		$sql.= '	      WHERE s.supplier_branch_code = :supplier_tnid';
		$sql.= '		    AND s.product_category_id = p.id';
		$sql.= '       )';
		
		// match the country of the original supplier to the other suppliers
		$sql.= '   AND sb.spb_country = origSb.spb_country';
		$sql.= '   AND origSb.spb_branch_code = :supplier_tnid';
		
		// standard 'valid supplier' logic - should ultimately be moved into a view
		$sql.= '   AND sb.directory_entry_status = :directory_entry_status';
		$sql.= '   AND sb.spb_account_deleted = :account_deleted';
		$sql.= '   AND sb.spb_test_account = :test_account';
		$sql.= '   AND sb.spb_branch_code <= 999999';
		$sql.= '   AND sb.spb_branch_code NOT IN ('.implode(',', $excludeTnids).')';
		$sql.= '   AND sb.directory_list_verified_at_utc >= add_months(sysdate, -12)';
		$sql.= ' GROUP BY sb.spb_branch_code,';
		$sql.= '          dll.level_of_importance,';
		$sql.= '          sb.spb_trade_rank';
		
		// order by listing level (i.e. premiums first),
		// then by rank (which is a count of how many matching categories there are)
		// then by TradeRank
		$sql.= ' ORDER BY dll.level_of_importance DESC,';
		$sql.= '          rank DESC,';
		$sql.= '          sb.spb_trade_rank DESC';
		
		$sqlData = array('supplier_tnid'          => $tnid,
						 'directory_entry_status' => 'PUBLISHED',
						 'account_deleted'        => 'N',
						 'test_account'           => 'N');
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CATCOUNTRYMATCH_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->standbyDb->fetchAll($sql, $sqlData);
		}
		
		return $result;
	}
	
	/**
	 * This will pull out all suppliers who have the same continent as the TNID,
	 * and rank them by how many matching categories there are. 
	 * It will only return published, non-deleted. non-test accounts.
	 * 
	 * @access public
	 * @param int $tnid The TradeNet ID of the supplier for which category/continent
	 *                  matches should be fetched
	 * @param array $excludeTnids Array of TNIDs that should be excluded, so as to
	 * 				              prevent previous matches being selected
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched
	 *                          from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchCategoryContinentMatches ($tnid, $excludeTnids,
												   $useCache = true,
												   $cacheTTL = 86400)
	{
		$excludeTnids[] = $tnid;
		
		$sql.= 'SELECT sb.spb_branch_code AS tnid,';
		// the rank is based on a percentage of the matching categories to the
		// total categories of the matched supplier
		$sql.= '       ( count(sb.spb_branch_code) /
					     (select count(*) from supply_category where supplier_branch_code = sb.spb_branch_code)
					   ) AS rank';
		
		$sql.= '  FROM supplier_branch sb, supply_category sc,';
		$sql.= '       product_category pc, supplier_branch origSb,';
		$sql.= '       country c, country origCountry,';
		$sql.= '       continent cont, continent origCont,';
		$sql.= '       directory_listing_level dll';
		$sql.= ' WHERE sb.directory_listing_level_id = dll.id';
		$sql.= '   AND sc.supplier_org_code = sb.spb_sup_org_code';
		$sql.= '   AND sc.supplier_branch_code = sb.spb_branch_code';
		$sql.= '   AND pc.id = sc.product_category_id';
		
		// only fetch if they have a product category that falls within the
		// product categories of the original supplier
		$sql.= '   AND sc.product_category_id in (';
		$sql.= '	     SELECT p.id';
		$sql.= '		   FROM product_category p, supply_category s';
		$sql.= '	      WHERE s.supplier_branch_code = :supplier_tnid';
		$sql.= '		    AND s.product_category_id = p.id';
		$sql.= '       )';
		
		// match the continent of the original supplier to the other suppliers
		// (requires joining to the country then the continent table - todo check
		// how efficient this is)
		$sql.= '   AND sb.spb_country = c.cnt_country_code';
		$sql.= '   AND cont.con_code = c.cnt_con_code';
		$sql.= '   AND origSb.spb_country = origCountry.cnt_country_code';
		$sql.= '   AND origCont.con_code = origCountry.cnt_con_code';
		$sql.= '   AND origCont.con_code = cont.con_code';
		$sql.= '   AND origSb.spb_branch_code = :supplier_tnid';
		
		// standard 'valid supplier' logic - should ultimately be moved into a view
		$sql.= '   AND sb.directory_entry_status = :directory_entry_status';
		$sql.= '   AND sb.spb_account_deleted = :account_deleted';
		$sql.= '   AND sb.spb_test_account = :test_account';
		$sql.= '   AND sb.spb_branch_code <= 999999';
		$sql.= '   AND sb.spb_branch_code NOT IN ('.implode(',', $excludeTnids).')';
		$sql.= '   AND sb.directory_list_verified_at_utc >= add_months(sysdate, -12)';
		$sql.= ' GROUP BY sb.spb_branch_code,';
		$sql.= '          dll.level_of_importance,';
		$sql.= '          sb.spb_trade_rank';
		
		// order by listing level (i.e. premiums first),
		// then by rank (which is a count of how many matching categories there are)
		// then by TradeRank
		$sql.= ' ORDER BY dll.level_of_importance DESC,';
		$sql.= '          rank DESC,';
		$sql.= '          sb.spb_trade_rank DESC';
		
		$sqlData = array('supplier_tnid'          => $tnid,
						 'directory_entry_status' => 'PUBLISHED',
						 'account_deleted'        => 'N',
						 'test_account'           => 'N');
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CATCONTMATCH_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->standbyDb->fetchAll($sql, $sqlData);
		}
		
		return $result;
	}
	
	/**
	 * This will pull out all suppliers regardless of location,
	 * and rank them by how many matching categories there are. 
	 * It will only return published, non-deleted. non-test accounts.
	 * 
	 * @access public
	 * @param int $tnid The TradeNet ID of the supplier for which category/continent
	 *                  matches should be fetched
	 * @param array $excludeTnids Array of TNIDs that should be excluded, so as to
	 * 				              prevent previous matches being selected
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched
	 *                          from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchCategoryWorldwideMatches ($tnid, $excludeTnids,
												   $useCache = true,
												   $cacheTTL = 86400)
	{
		$excludeTnids[] = $tnid;
		
		$sql.= 'SELECT sb.spb_branch_code AS tnid,';
		// the rank is based on a percentage of the matching categories to the
		// total categories of the matched supplier
		$sql.= '       ( count(sb.spb_branch_code) /
					     (select count(*) from supply_category where supplier_branch_code = sb.spb_branch_code)
					   ) AS rank';
		
		$sql.= '  FROM supplier_branch sb, supply_category sc,';
		$sql.= '       product_category pc, directory_listing_level dll';
		$sql.= ' WHERE sb.directory_listing_level_id = dll.id';
		$sql.= '   AND sc.supplier_org_code = sb.spb_sup_org_code';
		$sql.= '   AND sc.supplier_branch_code = sb.spb_branch_code';
		$sql.= '   AND pc.id = sc.product_category_id';
		
		// only fetch if they have a product category that falls within the
		// product categories of the original supplier
		$sql.= '   AND sc.product_category_id in (';
		$sql.= '	     SELECT p.id';
		$sql.= '		   FROM product_category p, supply_category s';
		$sql.= '	      WHERE s.supplier_branch_code = :supplier_tnid';
		$sql.= '		    AND s.product_category_id = p.id';
		$sql.= '       )';
		
		// standard 'valid supplier' logic - should ultimately be moved into a view
		$sql.= '   AND sb.directory_entry_status = :directory_entry_status';
		$sql.= '   AND sb.spb_account_deleted = :account_deleted';
		$sql.= '   AND sb.spb_test_account = :test_account';
		$sql.= '   AND sb.spb_branch_code <= 999999';
		$sql.= '   AND sb.spb_branch_code NOT IN ('.implode(',', $excludeTnids).')';
		$sql.= '   AND sb.directory_list_verified_at_utc >= add_months(sysdate, -12)';
		$sql.= ' GROUP BY sb.spb_branch_code,';
		$sql.= '          dll.level_of_importance,';
		$sql.= '          sb.spb_trade_rank';
		
		// order by listing level (i.e. premiums first),
		// then by rank (which is a count of how many matching categories there are)
		// then by TradeRank
		$sql.= ' ORDER BY dll.level_of_importance DESC,';
		$sql.= '          rank DESC,';
		$sql.= '          sb.spb_trade_rank DESC';
		
		$sqlData = array('supplier_tnid'          => $tnid,
						 'directory_entry_status' => 'PUBLISHED',
						 'account_deleted'        => 'N',
						 'test_account'           => 'N');
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CATWORLDMATCH_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->standbyDb->fetchAll($sql, $sqlData);
		}
		
		return $result;
	}
	
	/**
	 * Fetches supplier that matches the country of a supplied TNID
	 *
	 * @access public
	 * @param int $tnid The TradeNet ID of the supplier for which category/country
	 *                  matches should be fetched
	 * @param array $excludeTnids Array of TNIDs that should be excluded, so as to
	 * 				              prevent previous matches being selected
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched
	 *                          from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchCountryMatches ($tnid, $excludeTnids, $useCache = true,
										 $cacheTTL = 86400)
	{
		$excludeTnids[] = $tnid;
		
		$sql.= 'SELECT sb.spb_branch_code AS tnid, sb.spb_trade_rank AS rank';
		
		$sql.= '  FROM supplier_branch sb, supplier_branch origSb,';
		$sql.= '       directory_listing_level dll';
		$sql.= ' WHERE sb.directory_listing_level_id = dll.id';
		
		// match the country of the original supplier to the other suppliers
		$sql.= '   AND sb.spb_country = origSb.spb_country';
		$sql.= '   AND origSb.spb_branch_code = :supplier_tnid';
		
		// standard 'valid supplier' logic - should ultimately be moved into a view
		$sql.= '   AND sb.directory_entry_status = :directory_entry_status';
		$sql.= '   AND sb.spb_account_deleted = :account_deleted';
		$sql.= '   AND sb.spb_test_account = :test_account';
		$sql.= '   AND sb.spb_branch_code <= 999999';
		$sql.= '   AND sb.spb_branch_code NOT IN ('.implode(',', $excludeTnids).')';
		$sql.= '   AND sb.directory_list_verified_at_utc >= add_months(sysdate, -12)';
		$sql.= ' GROUP BY sb.spb_branch_code,';
		$sql.= '          dll.level_of_importance,';
		$sql.= '          sb.spb_trade_rank';
		
		// order by listing level (i.e. premiums first),
		// then by rank (which is a count of how many matching categories there are)
		// then by TradeRank
		$sql.= ' ORDER BY dll.level_of_importance DESC,';
		$sql.= '          sb.spb_trade_rank DESC';
		
		$sqlData = array('supplier_tnid'          => $tnid,
						 'directory_entry_status' => 'PUBLISHED',
						 'account_deleted'        => 'N',
						 'test_account'           => 'N');
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'COUNTRYMATCH_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->standbyDb->fetchAll($sql, $sqlData);
		}
		
		return $result;
	}
	
	
	/**
	 * Fetches supplier that matches the continent of a supplied TNID
	 *
	 * @access public
	 * @param int $tnid The TradeNet ID of the supplier for which category/country
	 *                  matches should be fetched
	 * @param array $excludeTnids Array of TNIDs that should be excluded, so as to
	 * 				              prevent previous matches being selected
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched
	 *                          from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchContinentMatches ($tnid, $excludeTnids, $useCache = true,
										   $cacheTTL = 86400)
	{
		$excludeTnids[] = $tnid;
		
		$sql.= 'SELECT sb.spb_branch_code AS tnid, sb.spb_trade_rank AS rank';
		
		$sql.= '  FROM supplier_branch sb, supplier_branch origSb,';
		$sql.= '       country c, country origCountry,';
		$sql.= '       continent cont, continent origCont,';
		$sql.= '       directory_listing_level dll';
		$sql.= ' WHERE sb.directory_listing_level_id = dll.id';
		
		// match the continent of the original supplier to the other suppliers
		// (requires joining to the country then the continent table - todo check
		// how efficient this is)
		$sql.= '   AND sb.spb_country = c.cnt_country_code';
		$sql.= '   AND cont.con_code = c.cnt_con_code';
		$sql.= '   AND origSb.spb_country = origCountry.cnt_country_code';
		$sql.= '   AND origCont.con_code = origCountry.cnt_con_code';
		$sql.= '   AND origCont.con_code = cont.con_code';
		$sql.= '   AND origSb.spb_branch_code = :supplier_tnid';
		
		// standard 'valid supplier' logic - should ultimately be moved into a view
		$sql.= '   AND sb.directory_entry_status = :directory_entry_status';
		$sql.= '   AND sb.spb_account_deleted = :account_deleted';
		$sql.= '   AND sb.spb_test_account = :test_account';
		$sql.= '   AND sb.spb_branch_code <= 999999';
		$sql.= '   AND sb.spb_branch_code NOT IN ('.implode(',', $excludeTnids).')';
		$sql.= '   AND sb.directory_list_verified_at_utc >= add_months(sysdate, -12)';
		$sql.= ' GROUP BY sb.spb_branch_code,';
		$sql.= '          dll.level_of_importance,';
		$sql.= '          sb.spb_trade_rank';
		
		// order by listing level (i.e. premiums first),
		// then by rank (which is a count of how many matching categories there are)
		// then by TradeRank
		$sql.= ' ORDER BY dll.level_of_importance DESC,';
		$sql.= '          sb.spb_trade_rank DESC';
		
		$sqlData = array('supplier_tnid'          => $tnid,
						 'directory_entry_status' => 'PUBLISHED',
						 'account_deleted'        => 'N',
						 'test_account'           => 'N');
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'COUNTRYMATCH_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->standbyDb->fetchAll($sql, $sqlData);
		}
		
		return $result;
	}
	
	/**
	 * New procedure to avoid unnesscessary overhead on DB by fetching competitors 
	 * from a periodic cache, rather than the degrading algorithm in Competitorlist 
	 * cache (The Oracle proc does esentially the same thig, but for every supplier 
	 * and dumps everything into the cache table. Reduces wait time for a 
	 * competitors first-access cache from .5 of a second to .002!
	 * 
	 * @param integer $tnid
	 * @param bool $useCache
	 * @param integer $cacheTTL 
	 */
	public function fetchSupplierCompetitorsFromCache($tnid,$count = 10,$useCache = true,
										   $cacheTTL = 86400 )
	{
		$sql = "SELECT * FROM (Select pcc_competitor_branch_code as TNID, pcc_rank from pages_competitor_cache where pcc_supplier_branch_code = :tnid) WHERE ROWNUM <= :count";
		$params = array('tnid' => $tnid, 'count' => $count);
		
		if($useCache){
			$key = $this->memcacheConfig->client->keyPrefix . 'SupplierCompetitorsFromCache_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $params, $key, $cacheTTL);
		}else{
			$result = $this->db->fetchAll($sql, $params);
		}
		return $result;
	}
	
	
	
	/**
	 * Fetch suppliers by name.
	 * Updated: fetches only canonical supplier branch codes.
	 */
	public function fetchSuppliersByName ($name)
	{
		$sql = 'SELECT SB.*';
		$sql.= '  FROM SUPPLIER_BRANCH SB';
		$sql.= ' WHERE UPPER(SB.SPB_NAME) LIKE :name';
		$sql.= '   AND SB.SPB_BRANCH_CODE NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM) ORDER BY UPPER(SB.SPB_NAME)';
		
		$sqlData = array('name'        => strtoupper($name).'%');
		
		return $this->db->fetchAll($sql, $sqlData);
	}

	/**
	 * Search all suppliers by email and return their info & access codes
	 *
	 * @param string $email Email to use for search
	 * @return <type>
	 */
	public function fetchAccessCodesByEmail ($email)
	{
		$sql  = 'SELECT SB.*,AC.* FROM SUPPLIER_BRANCH SB, ACCESS_CODE AC';
		$sql .= ' WHERE AC.TNID = SB.SPB_BRANCH_CODE AND (lower(SB.SPB_EMAIL) = :email OR lower(SPB_REGISTRANT_EMAIL_ADDRESS) = :email OR lower(PUBLIC_CONTACT_EMAIL) = :email)';
		$sql .= ' AND SB.SPB_TEST_ACCOUNT = :testaccount';
		$sql .= ' AND SB.SPB_ACCOUNT_DELETED = :accountdeleted';
		$sql .= ' AND SB.DIRECTORY_ENTRY_STATUS LIKE :entrystatus';

		$sqlData = array('email'        => strtolower($email),
						 'entrystatus' => 'PUBLISHED',
						 'accountdeleted' => 'N',
						 'testaccount' => 'N');

		return $this->db->fetchAll($sql, $sqlData);
	}
	
	public function fetchAccessCodesByBranchCode( $branchCode )
	{
		$sql  = 'SELECT SB.*,AC.* FROM SUPPLIER_BRANCH SB, ACCESS_CODE AC';
		$sql .= ' WHERE AC.TNID = SB.SPB_BRANCH_CODE AND SB.SPB_BRANCH_CODE = :branchCode';
		$sql .= ' AND SB.SPB_TEST_ACCOUNT = :testaccount';
		$sql .= ' AND SB.SPB_ACCOUNT_DELETED = :accountdeleted';
		$sql .= ' AND SB.DIRECTORY_ENTRY_STATUS LIKE :entrystatus';

		$sqlData = array('branchCode' => $branchCode,
						 'entrystatus' => 'PUBLISHED',
						 'accountdeleted' => 'N',
						 'testaccount' => 'N');

		return $this->db->fetchAll($sql, $sqlData);
		
	}
	
	public function updateDirectoryListingDate( $tnid)
	{
		$sql  = 'UPDATE supplier_branch SET DIRECTORY_LIST_VERIFIED_AT_UTC = SYSDATE WHERE SPB_BRANCH_CODE = :tnid';
		$sqlData = array('tnid' => $tnid);
		return $this->db->query($sql, $sqlData);
	}
	
	public function updateEmailReminderSentDate( $tnid)
	{
		return true;
		
		// @note jason will test the impact of adding new field to spb table
		$sql  = 'UPDATE supplier_branch SET DIRECTORY_LIST_VERIFIED_AT_UTC = SYSDATE WHERE SPB_BRANCH_CODE = :tnid';
		$sqlData = array('tnid' => $tnid);
		return $this->db->query($sql, $sqlData);
	}
	
	public function disableSVRAccess( $tnid )
	{
		$sql  = "UPDATE supplier_branch SET SPB_SVR_ACCESS = '' WHERE SPB_BRANCH_CODE = :tnid";
		$sqlData = array('tnid' => $tnid);
		return $this->db->query($sql, $sqlData);
	}
	
	public function enableSVRAccess( $tnid )
	{
		$sql  = "UPDATE supplier_branch SET SPB_SVR_ACCESS = 'Y' WHERE SPB_BRANCH_CODE = :tnid";
		$sqlData = array('tnid' => $tnid);
		return $this->db->query($sql, $sqlData);
	}	
	
	public function enableSVRAccessForAllBasicLister()
	{
		$sql = "
				UPDATE supplier_branch
				SET spb_svr_access=1 
			    WHERE 
			        DIRECTORY_LISTING_LEVEL_ID!=4
			        AND SPB_SVR_ACCESS IS NULL
			        AND directory_entry_status = 'PUBLISHED'
			        AND spb_account_deleted = 'N'
			        AND spb_test_account = 'N'
			        AND spb_branch_code <= 999999
			        AND spb_branch_code NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)		
		";
		return $this->db->query($sql);
	}
	
	/**
	 * Get enquiry statistic
	 * @param unknown_type $tnid
	 * @param unknown_type $period
	 */
	public function getEnquiriesStatistic( $tnid, $period )
	{
		// sent
		$sql = "
			SELECT 
			  result.*,
			  CASE
		          WHEN result.sent - result.read - result.declined - result.replied < 0 THEN 0
		        ELSE
		          result.sent - result.read - result.declined - result.replied
		        END
		         NOT_CLICKED,
					  			  
			  CASE 
			  	WHEN ( result.read > 0 OR result.declined > 0 OR result.replied > 0 ) AND result.sent > 0 THEN
			 	 	ROUND((result.read + result.declined + result.replied ) / result.sent * 100)
			 	WHEN result.read = 0 OR result.sent = 0 THEN
			 	 	0
			  	END AS OPEN_RATE,
			  CASE 
			  	WHEN result.read > 0 AND result.sent > 0 THEN
		            ROUND((result.sent - result.read - result.declined - result.replied) / result.sent * 100)
	        	WHEN result.read = 0 OR result.sent = 0 THEN
		            0
				END AS IGNORED_RATE
			  			  
			FROM
			(
			  SELECT
			    (
			    SELECT
			      COUNT(*)
			    FROM
			      PAGES_INQUIRY,
			      PAGES_INQUIRY_RECIPIENT
			    WHERE
			      PIR_SPB_BRANCH_CODE = :tnid
			      AND PIR_PIN_ID = PIN_ID
			      AND PIR_RELEASED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
			      AND not exists (
					select 1 from pages_statistics_email
					where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
					)
			      
			    ) SENT,
			    
			    (
			    SELECT
			      COUNT(*)
			    FROM
			      PAGES_INQUIRY,
			      PAGES_INQUIRY_RECIPIENT
			    WHERE
			      PIR_SPB_BRANCH_CODE = :tnid
			      AND PIR_PIN_ID = PIN_ID
			      AND PIR_RELEASED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
			      AND PIR_IS_READ IS NOT NULL
			      AND PIR_IS_REPLIED IS NULL
			      AND PIR_IS_DECLINED IS NULL
			      AND not exists (
					select 1 from pages_statistics_email
					where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
					)
			      
			    ) READ,
			    
			    (
			    SELECT
			      COUNT(*)
			    FROM
			      PAGES_INQUIRY,
			      PAGES_INQUIRY_RECIPIENT
			    WHERE
			      PIR_SPB_BRANCH_CODE = :tnid
			      AND PIR_PIN_ID = PIN_ID
			      AND PIR_RELEASED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
			      AND PIR_IS_REPLIED IS NOT NULL
			      AND PIR_IS_READ IS NOT NULL
			      AND not exists (
					select 1 from pages_statistics_email
					where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
					)
			      
			    ) REPLIED,
			    
			    (
			    SELECT
			      COUNT(*)
			    FROM
		    	  PAGES_INQUIRY,
			      PAGES_INQUIRY_RECIPIENT
			    WHERE
			      PIR_SPB_BRANCH_CODE = :tnid
			      AND PIR_PIN_ID = PIN_ID
			      AND PIR_RELEASED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
			      AND PIR_IS_DECLINED IS NOT NULL
			      AND not exists (
					select 1 from pages_statistics_email
					where lower(pin_email) like  '%' || lower(pse_email_phrase) || '%'
					)
			    ) DECLINED
			  
			  FROM DUAL
			) result
		";
		//var_dump(array('tnid' => $tnid, 'startDate' => $period['start']->format('d M Y'), 'endDate' => $period['end']->format('d M Y')));
		$result = $this->db->fetchAll($sql, array('tnid' => $tnid, 'startDate' => $period['start']->format('d M Y'), 'endDate' => $period['end']->format('d M Y')));
		return $result;
		
	}
	
	public function getActiveUsersByTnid( $tnid )
	{
		$sql = "SELECT
				  PUC_PSU_ID,
				  PUC_LEVEL
				FROM
				  PAGES_USER_COMPANY
				WHERE
				  PUC_COMPANY_ID=:tnid
				  AND PUC_STATUS='ACT'
				  AND PUC_COMPANY_TYPE='SPB'
		";
		$result = $this->db->fetchAll($sql, array('tnid' => $tnid));
		return $result;
		
	}

	public function getPublishedStatus( $tnid )
	{
		$sql = "
			SELECT
			DIRECTORY_ENTRY_STATUS
			FROM supplier_branch
			WHERE spb_branch_code=:tnid
  		";		
		$result = $this->db->fetchAll($sql, array('tnid' => $tnid));
		return $result[0];		
	}
	
	public function getEnquiryAccountType( $tnid )
	{
		$sql = "
			SELECT
			PEA_ACCOUNT_TYPE
			FROM pages_enquiry_account
			WHERE PEA_SPB_BRANCH_CODE=:tnid
	  	";
		$result = $this->db->fetchAll($sql, array('tnid' => $tnid));
		return $result[0];		
	}
	
	
	/**
	 * Pull total of transaction of a supplier
	 * @param unknown_type $tnid
	 * @param unknown_type $period
	 */
	public function getTotalTransaction( $tnid, $period )
	{
		$sql = "
			SELECT
			  COUNT(*) total
			FROM
			  POC
			WHERE
			  SPB_BRANCH_CODE=:tnid
      		  AND POC_SUBMITTED_DATE BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
			  
	    ";
		
		$result = $this->getSSReport2Db()->fetchRow($sql, array('tnid' => $tnid, 'startDate' => $period['start']->format('d M Y'), 'endDate' => $period['end']->format('d M Y')));
		return $result['TOTAL'];		
	}

	public function getTotalMAAttachments( $tnid )
	{
		$sql = "
			SELECT
			  COUNT(*) total
			FROM
			  DIRECTORY_ENTRY_ATTACHMENT
			WHERE
			  SUPPLIER_BRANCH_CODE=:tnid
      		  AND IS_MA=1
			  AND DELETED_ON_UTC IS NULL
	    ";
		$result = $this->db->fetchRow($sql, array('tnid' => $tnid));
		return $result['TOTAL'];		
	}
	
	public function hasEInvoicing( $tnid )
	{
		$sql = "
			SELECT
			  spb_einvoicing_install_date,
			  spb_einvoicing_downgraded
			FROM
			  supplier_branch
			WHERE
			  spb_branch_code=:tnid
	    ";
		$result = $this->db->fetchRow($sql, array('tnid' => $tnid));
		if( $result['SPB_EINVOICING_INSTALL_DATE'] != "" )
		{
			if( $result['SPB_EINVOICING_DOWNGRADED'] != "N" && $result['SPB_EINVOICING_DOWNGRADED'] != "" )
			{
				return false;
			}
			else
			{
				return true;
			}
		}
	}
	
	public function getPagesRFQIntegrationWithTradeNetStatus($tnid)
	{
		$sql = "SELECT spb_pgs_tn_int status FROM supplier_branch WHERE spb_branch_code=:tnid";
		$result = $this->db->fetchAll($sql, compact('tnid'));
		if( $result[0]['STATUS'] == 'N')
		{
			return false;
		}
		else
		{
			return true;
		}
	}
	
	public function setPagesRFQIntegrationWithTradeNetStatus($tnid, $status)
	{
		$status = ( $status === true ) ? "Y":"N"; 
		$sql = "UPDATE supplier_branch SET spb_pgs_tn_int=:status WHERE spb_branch_code=:tnid";
		$this->db->query($sql, compact('tnid', 'status'));
	}

    /**
     * For some reason there are two supplier name autocomplete functions used separately. This new function
     * builds the queries for matching suppliers which can be later extended with fields needed in each particular case
     *
     * @author  Yuriy Akopov
     * @date    2017-03-30
     * @story   S19346
     *
     * @param   string  $keyword
     * @param   int     $rowCount
     * @param   bool    $includeNonPublished
     * @param   bool    $hideTestAccounts     added by Yuriy Akopov on 2017-06-26, S20478
     *
     * @return   Zend_Db_Select
     */
	protected static function getSupplierAutocompleteQueries($keyword, $rowCount = 10, $includeNonPublished = false, $hideTestAccounts = false, $supplierActiveStatus = false)
    {
        $keyword = strtolower($keyword);
        $keyword = trim(preg_replace('/\s+/', ' ', $keyword));

        if (strlen($keyword) === 0) {
            return null;    // a signal empty list of matches can be returned without running the query
        }

        $db = Shipserv_Helper_Database::getDb();
        $autoCompleteSourceQueries = array();

        // check the fuzzy search
        if (
            // if the query contains special characters, fuzzy search is skipped
            // escaped them is a lot of code clutter, and this is quite an edge case
            (strpos($keyword, '{') === false) and
            (strpos($keyword, '}') === false) and
            (strpos($keyword, "'") === false)
        ) {
            $fuzzySearchSelect = new Zend_Db_Select($db);
            $fuzzySearchSelect
                ->from(
                    array('spb' => Shipserv_Supplier::TABLE_NAME),
                    array(
                        'value'       => 'spb.' . Shipserv_Supplier::COL_NAME,
                        'tnid'        => 'spb.' . Shipserv_Supplier::COL_ID,
                        'score' => new Zend_Db_Expr('SCORE(10)')
                    )
                )
                ->where(
                    "CONTAINS(	spb." . Shipserv_Supplier::COL_NAME . ", 'fuzzy({' || ? || '}, 66, 100, weight)', 10) > 0",
                    $keyword
                )
            ;

            $autoCompleteSourceQueries[] = $fuzzySearchSelect;
        }

        // check partial exact match on word is the length is long enough
        if (strlen($keyword) >= 3) {
            $words = explode(" ", $keyword);

            $likeConstraints = array();
            foreach ($words as $word) {
                $likeConstraints[] =
                    'LOWER(spb.' . Shipserv_Supplier::COL_NAME . ') ' . Shipserv_Helper_Database::escapeLike(
                        $db,
                        strtolower($word),
                        Shipserv_Helper_Database::ESCAPE_LIKE_BOTH
                    )
                ;
            }

            $likeSelect = new Zend_Db_Select($db);
            $likeSelect
                ->from(
                    array('spb' => Shipserv_Supplier::TABLE_NAME),
                    array(
                        'value'       => 'spb.' . Shipserv_Supplier::COL_NAME,
                        'tnid'        => 'spb.' . Shipserv_Supplier::COL_ID,
                        'score' => new Zend_Db_Expr('100')
                    )
                )
                ->where(
                    implode(
                        " AND ",
                        $likeConstraints
                    )
                )
            ;

            $autoCompleteSourceQueries[] = $likeSelect;
        }

        // check numeric strings if they are an exact TNID
        if (is_numeric($keyword) and strlen($keyword) >= 3) {
            $idMatchSelect = new Zend_Db_Select($db);
            $idMatchSelect
                ->from(
                    array('spb' => Shipserv_Supplier::TABLE_NAME),
                    array(
                        'value' => 'spb.' . Shipserv_Supplier::COL_NAME,
                        'tnid'  => 'spb.' . Shipserv_Supplier::COL_ID,
                        'score' => new Zend_Db_Expr('1000')
                    )
                )
                ->where('spb.' . Shipserv_Supplier::COL_ID . ' = ?', (int) $keyword)
            ;

            $autoCompleteSourceQueries[] = $idMatchSelect;
        }

        if (empty($autoCompleteSourceQueries)) {
            return null;
        }

        foreach ($autoCompleteSourceQueries as $sourceSelect) {
            $sourceSelect->where(Shipserv_Supplier::getValidSupplierConstraints('spb', $includeNonPublished, $hideTestAccounts, $supplierActiveStatus));
        }

        // all matches in a concatenated list with possible duplicates
        $selectUnion = new Zend_Db_Select($db);
        $selectUnion
            ->union($autoCompleteSourceQueries, Zend_Db_Select::SQL_UNION_ALL)
        ;

        // all matches with duplicates removed and sorted by relevance
        $selectTotal = new Zend_Db_Select($db);
        $selectTotal
            ->from(
                array('src' => $selectUnion),
                array(
                    'tnid'        => 'src.tnid',
                    'value'       => 'src.value',
                    'total_score' => new Zend_Db_Expr('SUM(src.score)')
                )
            )
            ->group(
                array(
                    'src.tnid',
                    'src.value'
                )
            )
            ->order(
                array(
                    'total_score DESC',
                    'value ASC'
                )
            )
        ;

        // top N matches as requested
        $selectTop = new Zend_Db_Select($db);
        $selectTop
            ->from(
                array('total' => $selectTotal),
                array(
                    'tnid'          => 'total.tnid',
                    'value'         => 'total.value',
                    'total_score'   => 'total.total_score',
                    'rn' => new Zend_Db_Expr('ROWNUM')
                )
            )
            ->where('ROWNUM <= ?', $rowCount)
        ;

        // print $selectTop->assemble(); die;

        return $selectTop;
    }

	/**
	 * Reworked by Yuriy Akopov on 2016-06-17 under DE6606 to improve relevance and (as a side effect) safety
	 *
	 * @param   string  $keyword
	 *
	 * @return  array
	 */
	public function getAutoCompleteForSupplier($keyword)
	{
		if (strlen($keyword) === 0) {
			return array();
		}

		$select = self::getSupplierAutocompleteQueries($keyword, 10);
		// print $select->assemble(); die;
		
		if (is_null($select)) {
		    return array();
    }

		$cacheKey = Myshipserv_Config::decorateMemcacheKey(implode(
			'_',
			array(
				__FUNCTION__,
				md5($select->assemble())
			)
		));

		$timeStart = microtime(true);
		$rows = $this->fetchCachedQuery($select->assemble(), array(), $cacheKey, self::MEMCACHE_TTL);
		// var_dump(microtime(true) - $timeStart); die;

		return $rows;
	}

	
	/**
	 * Return the list of suppliers by the provided keyword
     * If buyer is provided then it will add a column, if the user traded with this byo in the last 12 months or not
     *
     * Refactored by Yuriy Akopov on 2017-03-30, S19346
	 *
	 * @param   string  $keyword
	 * @param   array   $orderedFromBuyerIds
	 * @param   int     $prevMonths
	 * @param   bool    $showUnpublishedSuppliers
     * @param   bool    $hideTestAccounts        added by Yuriy Akopov on 2017-06-26, S20478
     *
	 * @return  array
     * @throws  Exception
	 */
	public function getListOfCompanyByKeyword($keyword, array $orderedFromBuyerIds = array(), $prevMonths = null, $showUnpublishedSuppliers = false, $hideTestAccounts = false, $supplierActiveStatus = false)
	{
        if (strlen($keyword) === 0) {
            return array();
        }

				$selectMatches = self::getSupplierAutocompleteQueries($keyword, 30, $showUnpublishedSuppliers, $hideTestAccounts, $supplierActiveStatus);

				if (is_null($selectMatches)) {
            return array();
        }

        $keywordEscaped = $selectMatches->getAdapter()->quoteInto('?', $keyword);

        $columns = array(
            // @todo: these concatenations should not probably be a part of the query
            'display'     => new Zend_Db_Expr(
                "REPLACE(spb.spb_name, " . $keywordEscaped . ", '<em>' || " . $keywordEscaped . " || '</em>') || ' (' || spb.spb_city || ', ' || spb.spb_country || ', TNID: ' || spb.spb_branch_code || ')'"
            ),
            'non_display' => new Zend_Db_Expr(
                "spb.spb_name || ' (' || spb.spb_city || ', ' || spb.spb_country || ', TNID: ' || spb.spb_branch_code || ')'"
            ),
            'location'    => new Zend_Db_Expr(
                "spb.spb_city || ', ' || spb.spb_country || ', TNID: ' || spb.spb_branch_code"
            ),

            'value'         => 'spb.' . Shipserv_Supplier::COL_NAME,
            'code'          => new Zend_Db_Expr("'SPB-' || spb." . Shipserv_Supplier::COL_ID),
            'parent_tnid'   => 'spb.' . Shipserv_Supplier::COL_ORG_ID,
            // @todo: why ID is supplied twice?
            'tnid'          => 'spb.' . Shipserv_Supplier::COL_ID,
            'pk'            => 'spb.' . Shipserv_Supplier::COL_ID,
            'country'       => 'spb.' . Shipserv_Supplier::COL_COUNTRY,

            'has_order'     => new Zend_Db_Expr('0')
        );

		if ($orderedFromBuyerIds) {
		    $buyerFilterSelect = new Zend_Db_Select($selectMatches->getAdapter());
		    $buyerFilterSelect
                ->from(
                    array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                    array(
                        'foo' => new Zend_Db_Expr('/*+ FIRST_ROWS(1) */ 1')
                    )
                )
                ->where('ord.' . Shipserv_PurchaseOrder::COL_STATUS . ' = ?', Shipserv_PurchaseOrder::STATUS_SUBMITTED)
                ->where('ord.' . Shipserv_PurchaseOrder::COL_SUPPLIER_ID . ' = spb.' . Shipserv_Supplier::COL_ID)
                ->where('ord.' . Shipserv_PurchaseOrder::COL_BUYER_ID . ' IN (?)', $orderedFromBuyerIds)
            ;

		    if (!is_null($prevMonths)) {
		        $buyerFilterSelect->where(
		            'ord.' . Shipserv_PurchaseOrder::COL_DATE_SUB . ' > ADD_MONTHS(SYSDATE, ?)',
                    '-' . $prevMonths
                );
            }

            $columns['has_order'] = new Zend_Db_Expr("
                CASE
                    WHEN EXISTS(" . $buyerFilterSelect->assemble() . ") THEN 1
                    ELSE 0
                END
            ");
		}

        $selectFinal = new Zend_Db_Select($selectMatches->getAdapter());
        $selectFinal
            ->from(
                array('spb' => Shipserv_Supplier::TABLE_NAME),
                $columns
            )
            ->join(
                array('matches' => $selectMatches),
                'matches.tnid = spb.' . Shipserv_Supplier::COL_ID,
                array()
            )
            ->order('matches.rn')
        ;
        // print $selectFinal->assemble(); die;

		// SPC-3209, Caching removed by request
        // $cacheKey = Myshipserv_Config::decorateMemcacheKey(implode(
        //     '_',
        //     array(
        //         __FUNCTION__,
        //         md5($selectFinal->assemble())
        //     )
        // ));

		$timeStart = microtime(true);
		$rows = self::getDbByName('sservdba')->fetchAll($selectFinal->assemble());
		// SPC-3209, Caching removed by request
        // $rows = $this->fetchCachedQuery($selectFinal->assemble(), array(), $cacheKey, self::MEMCACHE_TTL);
        // print(microtime(true) - $timeStart); die;

        return $rows;
	}
	
	public function isPublishedInShipServOnBoard($spbBranchCode, $version = 2014)
	{
		$sql = "SELECT COUNT(*) TOTAL FROM supplier_sso WHERE sso_spb_branch_code=:spbBranchCode AND sso_year=:version";
		$keyForMemcached = "SPB_IS_IN_SSO_" . $spbBranchCode. "_version_" . $version;
		$memcacheTTL = 3600;
		$result = $this->fetchCachedQuery($sql, array('spbBranchCode' => $spbBranchCode, 'version' => $version), $keyForMemcached, $memcacheTTL);
		return ($result[0]['TOTAL']>0);
	}
}
