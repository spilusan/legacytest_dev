<?php

/**
 * Class for reading event-related data from Oracle
 *
 * TO_CHAR(SYSDATE,'dd-Mon-yyyy hh:mi:ss')
 * 
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Oracle_Events extends Shipserv_Oracle
{
	public function __construct (&$db)
	{
		parent::__construct($db);
	}
	
	/**
	 * Fetches a list of events relating to searches performed on the site.
	 * Reads from the analytics table.
	 * 
	 * @access public
	 * @param int $count The number of search events to fetch
	 * @return array
	 */
	public function fetchSearchEvents ($count = 10, $time = 600)
	{
		$sql = 'SELECT a.* FROM (';
		$sql.= '	SELECT pst_search_text, pst_port, pst_country, pst_country_id,';
		$sql.= '       	   pst_full_query, pst_results_returned, pst_geodata';
		$sql.= '  	  FROM pages_statistics';
		$sql.= ' 	 WHERE pst_search_text IS NOT NULL';
		$sql.= "   	   AND pst_browser != 'crawler'";
		$sql.= '       AND pst_search_date_time >= SYSDATE - ('.$this->db->quote($time, 'INTEGER').' * 10/86400)';
		$sql.= ' 	 ORDER BY pst_id DESC';
		$sql.= ' ) a';
		$sql.= ' WHERE rownum <= '. $this->db->quote($count, 'INTEGER');
		
		$sqlData = array();
		
		return $this->db->fetchAll($sql, $sqlData);
	}
	
	/**
	 * Fetches a list of events relating to impressions of suppliers on the site.
	 * Reads from the analytics table.
	 * 
	 * @access public
	 * @param int $count The number of impressions events to fetch
	 * @return array
	 */
	public function fetchImpressionEvents ($count = 10, $time = 600)
	{
		$sql = 'SELECT a.* FROM (';
		$sql.= 'SELECT sb.spb_branch_code AS tnid, sb.spb_name AS name,';
		$sql.= '       sb.spb_branch_address_1, sb.spb_branch_address_2, sb.spb_city,';
		$sql.= '       sb.spb_state_province, sb.spb_zip_code, c.cnt_name,';
		$sql.= '       sb.spb_latitude, sb.spb_longitude';
		$sql.= '  FROM pages_statistics_supplier pss, supplier_branch sb, country c';
		$sql.= " WHERE pss.pss_browser != 'crawler'";
		$sql.= '   AND pss.pss_spb_branch_code = sb.spb_branch_code';
		$sql.= '   AND c.cnt_country_code = sb.spb_country';
		$sql.= '   AND pss.pss_view_date >= SYSDATE - ('.$this->db->quote($time, 'INTEGER').'/86400)';
		$sql.= ' ORDER BY pss_id DESC';
		$sql.= ' ) a';
		$sql.= ' WHERE rownum <= '. $this->db->quote($count, 'INTEGER');
		
		$sqlData = array();
		
		return $this->db->fetchAll($sql, $sqlData);
	}
	
	/**
	 * Fetches a list of events relating to new suppliers
	 * 
	 * @access public
	 * @param int $count The number of new suppliers to fetch
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchNewSupplierEvents ($count = 10, $time = 600)
	{
		$sql = 'SELECT a.* FROM (';
		
		$sql.= 'SELECT spb_branch_code AS tnid, spb_name AS name,';
		$sql.= '       spb_branch_address_1, spb_branch_address_2, spb_city,';
		$sql.= '       spb_state_province, spb_zip_code, c.cnt_name,';
		$sql.= '       spb_latitude, spb_longitude';
		$sql.= '  FROM supplier_branch sb, country c';
		$sql.= ' WHERE c.cnt_country_code = sb.spb_country';
		$sql.= "   AND directory_entry_status = 'PUBLISHED'";
		$sql.= "   AND spb_account_deleted = 'N'";
		$sql.= "   AND spb_test_account = 'N'";
		$sql.= '   AND spb_branch_code <= 999999';
		$sql.= '   AND spb_created_date >= SYSDATE - ('.$this->db->quote($time, 'INTEGER').'/86400)';
		$sql.= ' ORDER BY spb_created_date DESC) a';
		
		$sql.= ' WHERE rownum <= '. $this->db->quote($count, 'INTEGER');
		
		$sqlData = array();
		
		return $this->db->fetchAll($sql, $sqlData);
	}
	
	/**
	 * Fetches a list of events relating to new suppliers
	 * 
	 * @access public
	 * @param int $count The number of new suppliers to fetch
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchUpdatedSupplierEvents ($count = 10, $time = 600)
	{
		$sql = 'SELECT a.* FROM (';
		
		$sql.= 'SELECT spb_branch_code AS tnid, spb_name AS name,';
		$sql.= '       spb_branch_address_1, spb_branch_address_2, spb_city,';
		$sql.= '       spb_state_province, spb_zip_code, c.cnt_name,';
		$sql.= '       spb_latitude, spb_longitude';
		$sql.= '  FROM supplier_branch sb, country c';
		$sql.= ' WHERE c.cnt_country_code = sb.spb_country';
		$sql.= "   AND directory_entry_status = 'PUBLISHED'";
		$sql.= "   AND spb_account_deleted = 'N'";
		$sql.= "   AND spb_test_account = 'N'";
		$sql.= '   AND spb_branch_code <= 999999';
		$sql.= '   AND directory_list_verified_at_utc >= SYSDATE - ('.$this->db->quote($time, 'INTEGER').'/86400)';
		$sql.= ' ORDER BY directory_list_verified_at_utc DESC) a';
		
		$sql.= ' WHERE rownum <= '. $this->db->quote($count, 'INTEGER');
		
		$sqlData = array();
		
		return $this->db->fetchAll($sql, $sqlData);
	}
	
	/**
	 * Fetches a list of events relating to enquiries
	 * 
	 * @access public
	 * @param int $count The number of new suppliers to fetch
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchEnquiryEvents ($count = 10, $time = 600)
	{
		$sql = 'SELECT b.*, pin_r.pir_spb_branch_code, sb.spb_branch_code AS tnid, sb.spb_name AS name,';
		$sql.= '       sb.spb_branch_address_1, sb.spb_branch_address_2, sb.spb_city,';
		$sql.= '       sb.spb_state_province, sb.spb_zip_code, c.cnt_name,';
		$sql.= '       sb.spb_latitude, sb.spb_longitude';
		$sql.= '  FROM (SELECT *';
		
		$sql.= '          FROM (SELECT pin_id, pin_name, pin_geodata';
		$sql.= '                  FROM pages_inquiry';
		$sql.= "                 WHERE pin_status = 'RELEASED'";
		$sql.= '                   AND pin_last_update_date >= SYSDATE - ('.$this->db->quote($time, 'INTEGER').'/86400)';
		$sql.= '              ORDER BY pin_last_update_date desc) a';
		$sql.= '          WHERE rownum <= '.$this->db->quote($count, 'INTEGER').') b,';
		
		$sql.= '       pages_inquiry_recipient pin_r,';
		$sql.= '       supplier_branch sb,';
		$sql.= '       country c';
		$sql.= ' WHERE b.pin_id = pin_r.pir_pin_id';
		$sql.= '   AND sb.spb_branch_code = pin_r.pir_spb_branch_code';
		$sql.= '   AND c.cnt_country_code = sb.spb_country';
		$sql.= "   AND UPPER(sb.spb_name) NOT LIKE 'SHIPSERV%'";
		
		$sqlData = array();
		
		return $this->db->fetchAll($sql, $sqlData);
	}
	
	/**
	 * Fetches a list of events relating to orders
	 *
	 * @access public
	 * @param int $count The number of new suppliers to fetch
	 * @return array
	 */
	public function fetchOrderEvents ($count = 10, $time = 600)
	{
		$sql = 'SELECT a.* FROM (';
		
		$sql.= 'SELECT ord_internal_ref_no, ord_spb_branch_code, ord_byb_buyer_branch_code,';
		$sql.= '       ord_submitted_date, ord_currency, ord_total_cost,';
		
		$sql.= '       supplier.spb_name as supplier_name,';
		$sql.= '       supplier.spb_branch_address_1, supplier.spb_branch_address_2, supplier.spb_city,';
		$sql.= '       supplier.spb_state_province, supplier.spb_zip_code, scountry.cnt_name as supplier_country,';
		$sql.= '       supplier.spb_latitude, supplier.spb_longitude,';
		
		$sql.= '       buyer.byb_name as buyer_name,';
		$sql.= '       buyer.byb_address_1, buyer.byb_address_2, buyer.byb_city,';
		$sql.= '       buyer.byb_state_province, buyer.byb_zip_code, bcountry.cnt_name as buyer_country';
		
		$sql.= '  FROM supplier_branch supplier, buyer_branch buyer,';
		$sql.= '       country scountry, country bcountry, purchase_order';
		$sql.= '       LEFT OUTER JOIN order_response orp ON ord_internal_ref_no = orp_ord_internal_ref_no';
		$sql.= ' WHERE orp_ord_internal_ref_no IS NULL';
		$sql.= '   AND supplier.spb_branch_code = ord_spb_branch_code';
		$sql.= '   AND buyer.byb_branch_code = ord_byb_buyer_branch_code';
		$sql.= '   AND scountry.cnt_country_code = supplier.spb_country';
		$sql.= '   AND bcountry.cnt_country_code = buyer.byb_country';
		$sql.= "   AND ord_sts = 'SUB'";
		$sql.= '   AND ord_submitted_date >= SYSDATE - ('.$this->db->quote($time, 'INTEGER').'/86400)';
		
		$sql.= ' ORDER BY ord_submitted_date DESC) a';
		
		$sql.= ' WHERE rownum <= '. $this->db->quote($count, 'INTEGER');
		
		$sqlData = array();
		
		return $this->db->fetchAll($sql, $sqlData);
	}
	
	/**
	 * Fetches a list of events relating to orders being accepted
	 *
	 * @access public
	 * @param int $count The number of new suppliers to fetch
	 * @return array
	 */
	public function fetchOrderAcceptedEvents ($count = 10, $time = 600)
	{
		$sql = 'SELECT a.* FROM (';
		
		$sql.= 'SELECT ord_internal_ref_no, ord_spb_branch_code, ord_byb_buyer_branch_code,';
		$sql.= '       ord_submitted_date, ord_currency, ord_total_cost,';
		
		$sql.= '       supplier.spb_name as supplier_name,';
		$sql.= '       supplier.spb_branch_address_1, supplier.spb_branch_address_2, supplier.spb_city,';
		$sql.= '       supplier.spb_state_province, supplier.spb_zip_code, scountry.cnt_name as supplier_country,';
		$sql.= '       supplier.spb_latitude, supplier.spb_longitude,';
		
		$sql.= '       buyer.byb_name as buyer_name,';
		$sql.= '       buyer.byb_address_1, buyer.byb_address_2, buyer.byb_city,';
		$sql.= '       buyer.byb_state_province, buyer.byb_zip_code, bcountry.cnt_name as buyer_country';
		
		$sql.= '  FROM purchase_order, order_response, supplier_branch supplier, buyer_branch buyer,';
		$sql.= '       country scountry, country bcountry';
		$sql.= ' WHERE orp_ord_internal_ref_no = ord_internal_ref_no';
		$sql.= '   AND supplier.spb_branch_code = ord_spb_branch_code';
		$sql.= '   AND buyer.byb_branch_code = ord_byb_buyer_branch_code';
		$sql.= '   AND scountry.cnt_country_code = supplier.spb_country';
		$sql.= '   AND bcountry.cnt_country_code = buyer.byb_country';
		$sql.= "   AND ord_sts = 'SUB'";
		$sql.= "   AND orp_ord_sts = 'ACC'";
		$sql.= '   AND ord_submitted_date >= SYSDATE - ('.$this->db->quote($time, 'INTEGER').' * 300/86400)';
		
		$sql.= ' ORDER BY ord_submitted_date DESC) a';
		
		$sql.= ' WHERE rownum <= '. $this->db->quote($count, 'INTEGER');
		
		$sqlData = array();
		
		return $this->db->fetchAll($sql, $sqlData);
	}
	
	/**
	 *
	 *
	 *
	 */
	public function fetchRfqsSent ($count = 10, $time = 600)
	{
		$sql = ' SELECT a.*';
		$sql.= '   FROM (';
		$sql.= '   	  SELECT rfq.rfq_internal_ref_no, rqr.rqr_submitted_date,';
		$sql.= '   			 supplier.spb_name as supplier_name,';
		$sql.= '   			 supplier.spb_branch_address_1, supplier.spb_branch_address_2, supplier.spb_city,';
		$sql.= '   			 supplier.spb_state_province, supplier.spb_zip_code, scountry.cnt_name as supplier_country,';
		$sql.= '   			 supplier.spb_latitude, supplier.spb_longitude,';
		$sql.= '			 buyer.byb_name as buyer_name,';
		$sql.= '			 buyer.byb_address_1, buyer.byb_address_2, buyer.byb_city,';
		$sql.= '			 buyer.byb_state_province, buyer.byb_zip_code, bcountry.cnt_name as buyer_country';
		$sql.= '		FROM request_for_quote rfq, rfq_quote_relation rqr, supplier_branch supplier, ';
		$sql.= '			 buyer_branch buyer, country scountry, country bcountry';
		$sql.= '	   WHERE rfq.rfq_internal_ref_no = rqr.rqr_rfq_internal_ref_no';
		$sql.= '		 AND rqr.rqr_submitted_date >= SYSDATE - ('.$this->db->quote($time, 'INTEGER').'/86400)';
		$sql.= "		 AND rfq.rfq_sts = 'SUB'";
		$sql.= '		 AND supplier.spb_branch_code = rqr.rqr_spb_branch_code';
		$sql.= '		 AND buyer.byb_branch_code = rfq.rfq_byb_branch_code';
		$sql.= '		 AND scountry.cnt_country_code = supplier.spb_country';
		$sql.= '		 AND bcountry.cnt_country_code = buyer.byb_country';
		$sql.= '	   ORDER BY rqr.rqr_submitted_date DESC';
		$sql.= '   ) a';
		$sql.= '   WHERE rownum <= '. $this->db->quote($count, 'INTEGER');
		
		$sqlData = array();
		
		return $this->db->fetchAll($sql, $sqlData);
	}
	
	/**
	 * 
	 * 
	 * 
	 */
	public function fetchSearchEventsCount ($days = 7, $useCache = true, $cacheTTL = 43200 /*half day*/)
	{
		$sql = 'SELECT count(*) AS searchCount';
		$sql.= '  FROM pages_statistics';
		$sql.= " WHERE pst_search_date_time BETWEEN ";
		$sql.= " TO_DATE( TO_CHAR(sysdate-( " . $this->db->quote($days, 'INTEGER') . " + 2), 'dd/mon/yyyy') ) ";
		$sql.= " AND TO_DATE( TO_CHAR(sysdate-2, 'dd/mon/yyyy') )";
		$sql.= "   AND pst_browser <> 'crawler'";
		$sql.= " AND PST_IP_ADDRESS NOT IN (SELECT PSI_IP_ADDRESS FROM PAGES_STATISTICS_IP)";
		
		$sqlData = array();
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'SEARCHEVENTSCOUNT_'.$days.
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
	 *
	 *
	 *
	 */
	public function fetchNewSuppliersCount ($days = 7, $useCache = true, $cacheTTL = 43200 /*half a day*/)
	{
		$sql = 'SELECT count(*) AS newSuppliersCount';
		$sql.= '  FROM supplier_branch';
		$sql.= ' WHERE spb_created_date >= sysdate - '.$this->db->quote($days, 'INTEGER');
		$sql.= "   AND directory_entry_status = 'PUBLISHED'";
		$sql.= "   AND spb_account_deleted = 'N'";
		$sql.= "   AND spb_test_account = 'N'";
		$sql.= '   AND spb_branch_code <= 999999';
		
		$sqlData = array();
		
		if ($useCache) {
			$key = $this->memcacheConfig->client->keyPrefix . 'NEWSUPPLIERSCOUNT_'.$days.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		} else {
			$result = $this->db->fetchAll($sql, $sqlData);
		}
		
		return $result;
	}
	
	/**
	 *
	 *
	 *
	 */
	public function fetchUpdatedSuppliersCount ($days = 7, $useCache = true, $cacheTTL = 43200 /*half a day*/)
	{
		$sql = 'SELECT count(*) AS updatedSuppliersCount';
		$sql.= '  FROM supplier_branch';
		$sql.= ' WHERE directory_list_verified_at_utc >= sysdate - '.$this->db->quote($days, 'INTEGER');
		$sql.= "   AND directory_entry_status = 'PUBLISHED'";
		$sql.= "   AND spb_account_deleted = 'N'";
		$sql.= "   AND spb_test_account = 'N'";
		$sql.= '   AND spb_branch_code <= 999999';
		
		$sqlData = array();
		
		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'UPDSUPPLIERSCOUNT_'.$days.
			       $this->memcacheConfig->client->keySuffix;
			
			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}
		
		return $result;
	}
}