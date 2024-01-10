<?php
class Shipserv_Oracle_Analytics_Brand extends Shipserv_Oracle_Analytics
{
	
	public function getTotalSearchByBrandId($brandId, $period = array())
	{
		$key = $this->memcacheConfig->client->keyPrefix . __CLASS__ . "::" . __FUNCTION__ . $brandId . $this->memcacheConfig->client->keySuffix;
	
		$sql = "
				SELECT
				  COUNT(*) TOTAL
				FROM
				  pages_search_stats
				WHERE
				  pst_search_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
				  AND pst_brand_id = :brandId
			";
		$result = $this->fetchCachedQuery($sql, array('brandId' => $brandId, 'startDate' => $period['start']->format('d M Y'), 'endDate' => $period['end']->format('d M Y')), $key, self::MEMCACHE_TTL_1_MONTH, Shipserv_Oracle::SSREPORT2);
	
		return $result[0]['TOTAL'];
	}
	
	public function getTotalSupplierByBrandId($brandId)
	{
		$key = $this->memcacheConfig->client->keyPrefix . __CLASS__ . "::" . __FUNCTION__ . $brandId . $this->memcacheConfig->client->keySuffix;
		// DE6476: pcb_auth_level constraint added by Yuriy Akopov on 2016-03-08
		// DE6488: owners are not counted as authorised suppliers
		$sql = "
			SELECT
			  COUNT(DISTINCT CASE
			  	WHEN br_own.pcb_id IS NULL THEN br_all.pcb_company_id
			  	ELSE NULL
			  END) AS TOTAL,
			  COUNT(DISTINCT CASE
			  	WHEN br_own.pcb_id IS NOT NULL THEN br_all.pcb_company_id
			  	ELSE NULL
			  END) AS OWNED
			FROM
			  PAGES_COMPANY_BRANDS br_all
			  LEFT JOIN pages_company_brands br_own ON
			  	br_own.pcb_brand_id = br_all.pcb_brand_id
	            AND br_own.pcb_company_id = br_all.pcb_company_id
			  	AND br_own.pcb_auth_level = 'OWN'
			  	AND br_own.pcb_is_authorised = 'Y'
			  	AND br_own.pcb_is_deleted = 'N'
			WHERE
	          br_all.pcb_brand_id = :brandId
	          AND br_all.pcb_is_authorised = 'Y'
	          AND br_all.pcb_is_deleted = 'N'
		";
	
		$result = $this->fetchCachedQuery($sql, array('brandId' => $brandId), $key, self::MEMCACHE_TTL_1_MONTH, Shipserv_Oracle::SSERVDBA);
		// DE6476 by Yuriy Akopov on 2016-03-10
		if ($result[0]['OWNED'] == 0) {
			return 0;
		}

		return (int) $result[0]['TOTAL'];
	}
	
	public function getTotalNewlyUpdatedSupplierByBrandId($brandId, $period)
	{
		$key = $this->memcacheConfig->client->keyPrefix . __CLASS__ . "::" . __FUNCTION__ . $brandId . $period['start']->format('d M Y') . $period['end']->format('d M Y') . $this->memcacheConfig->client->keySuffix;
		$sql = "
				SELECT
					COUNT(distinct pcb_company_id) TOTAL
				FROM
					pages_company_brands
						JOIN supplier_branch
							ON (pcb_company_id=spb_branch_code
								AND DIRECTORY_LIST_VERIFIED_AT_UTC BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999)
				WHERE
					pcb_brand_id = :brandId
		";
	
		$result = $this->fetchCachedQuery($sql, array('brandId' => $brandId, 'startDate' => $period['start']->format('d M Y'), 'endDate' => $period['end']->format('d M Y')), $key, self::MEMCACHE_TTL_1_MONTH, Shipserv_Oracle::SSERVDBA);
		return $result[0]['TOTAL'];
	}
	
	public function getTotalRfqSentToSupplierByBrandId($brandId, $period)
	{
		$key = $this->memcacheConfig->client->keyPrefix . __CLASS__ . "::" . __FUNCTION__ . $brandId . $this->memcacheConfig->client->keySuffix;
		$sql = "
				SELECT
					COUNT(*) TOTAL
				FROM
				  rfq
				WHERE
				  spb_branch_code IN
				  (
					SELECT
					  pcb_company_id
					FROM
					  PAGES_COMPANY_BRANDS@livedb_link
					WHERE
			          pcb_brand_id = :brandId
			          AND pcb_is_authorised='Y'
			          AND pcb_is_deleted='N'				
				  )
				  AND rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) + 0.99999
		";
	
		$result = $this->fetchCachedQuery($sql, array('brandId' => $brandId, 'startDate' => $period['start']->format('d M Y'), 'endDate' => $period['end']->format('d M Y')), $key, self::MEMCACHE_TTL_1_MONTH, Shipserv_Oracle::SSREPORT2);
		return $result[0]['TOTAL'];
	}
	
}