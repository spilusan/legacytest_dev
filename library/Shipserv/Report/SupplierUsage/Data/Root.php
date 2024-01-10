<?php

class Shipserv_Report_SupplierUsage_Data_Root extends Shipserv_Report
{

	protected $params = null;

	/**
	* Constructor, takes parameter which is the controller getparam value
	* @param array $params Parameters
	* @return unknown
	*/
	public function __construct($params = null)
	{
		$this->params = $params;
	}

	/**
	* Fetch the data for the main Buyer Usage Dashboard report
	* @param integer $limit the limit of the rows returnin
	* @return array The database result fetched into an array
	*/
	public function getData($limit = 500)
	{
		$productLevel = (array_key_exists('level', $this->params)) ?  (int)($this->params['level']) : 0;
		$name = (array_key_exists('name', $this->params)) ?  strtolower($this->params['name']) : '';
		$range = (array_key_exists('range', $this->params)) ? (int)$this->params['range'] : 0;
		$excludeShipmate =  (array_key_exists('excludeSM', $this->params)) ? (strtolower($this->params['excludeSM']) == 'true') ? true : false : false;
		$sortOrder = (array_key_exists('sortOrder', $this->params)) ? (strtolower($this->params['sortOrder']) == 'asc') ? 'ASC' : 'DESC' : 'ASC';
		$country =  (array_key_exists('country', $this->params)) ?  ($this->params['country']) : '';
		$timezoneName = (array_key_exists('timezone', $this->params)) ? ($this->params['timezone']) : '';
		$gmvFrom = (array_key_exists('gmvFrom', $this->params)) ? ($this->params['gmvFrom']) : '';
		$gmvTo = (array_key_exists('gmvTo', $this->params)) ? ($this->params['gmvTo']) : '';
		
		//nr_days must be working days only, monday to fri...
		$sql = "
			WITH nr_days AS
      		(
				SELECT 
					SUM(CASE WHEN
							TO_NUMBER(TO_CHAR(ADD_MONTHS(SYSDATE, :range) + LEVEL - 1, 'D')) >= 1 AND TO_NUMBER(TO_CHAR(ADD_MONTHS(SYSDATE, :range) + LEVEL - 1, 'D')) <=5
						THEN 1 ELSE 0 END
					) nr_days
				FROM
					dual
				CONNECT BY
					ADD_MONTHS(SYSDATE, :range) + LEVEL - 1 <= SYSDATE
			), pending_ap AS
			(
				SELECT 
					count(distinct byb_branch_code) all_pending_count
				FROM
					buyer_branch@livedb_link byb
					INNER JOIN (
						SELECT
							byb_branch_code,
							byb_under_contract AS parent_branch_code,
							CONNECT_BY_ROOT(byb_under_contract) AS top_branch_code
						FROM
						(
							SELECT
								byb_branch_code,
								CASE WHEN byb_under_contract IS NULL THEN byb_branch_code
								ELSE byb_under_contract
								END AS byb_under_contract
							FROM
								buyer_branch@livedb_link
						) branches
						START WITH
							byb_branch_code = byb_under_contract
						CONNECT BY PRIOR
							byb_branch_code = byb_under_contract
							AND byb_branch_code <> byb_under_contract
					) buyer ON 
						buyer.byb_branch_code = byb.BYB_BRANCH_CODE
						INNER JOIN buyer_branch@livedb_link parent_byb ON
							parent_byb.byb_branch_code = buyer.top_branch_code
						LEFT JOIN supplier_branch@livedb_link spb ON
							spb.spb_byb_branch_code = byb.byb_branch_code
				WHERE
					(parent_byb.BYB_BRANCH_CODE = byb.BYB_BRANCH_CODE OR parent_byb.BYB_PROMOTE_CHILD_BRANCHES = 1)
					 AND (spb.SPB_BRANCH_CODE IS NULL)
					 AND (byb.BYB_CONTRACT_TYPE IN ('CN3', 'CCP', 'TRIAL', 'STANDARD'))
					 AND (byb.BYB_BRANCH_CODE NOT IN (11107, 11128)) 
					 AND (byb.BYB_STS = 'ACT') AND (byb.BYB_TEST_ACCOUNT = 'N')
			), user_events AS
			(
			      SELECT 
				      puc.puc_company_id
				     ,count(distinct  CASE WHEN pua_activity = 'SIR_VIEW' THEN  PUA_ID END) sir3_report_views
					 ,count(distinct  CASE WHEN pua_activity = 'USER_LOGIN' THEN  PUA_ID END) successfull_sign_ins
				     ,count(distinct  CASE WHEN pua_activity = 'USER_FAILED_LOGIN' THEN  PUA_ID END) user_failed_login
				     ,count(distinct  CASE WHEN pua_activity = 'AP_REPORT_VIEWS' THEN  PUA_ID END) ap_report_views
				     ,count(distinct  CASE WHEN pua_activity = 'CONTACT_REQUESTS' THEN  PUA_ID END) contact_requests
				     ,count(distinct CASE WHEN TO_NUMBER(TO_CHAR(pua_date_created, 'D')) >= 1 AND TO_NUMBER(TO_CHAR(pua_date_created, 'D')) <=5 THEN TO_CHAR(pua_date_created, 'YYYYMMDD') END) days_with_events
						FROM
							pages_user_activity pua
						JOIN
							pages_user_company@livedb_link  puc
							on (
								puc.puc_psu_id = pua.pua_psu_id
								and puc.puc_company_type = 'SPB'
								and puc.puc_status = 'ACT'
							)
						WHERE pua_date_created > ADD_MONTHS(SYSDATE, :range)\n";
		if ($excludeShipmate) {
			$sql .= "and pua_is_shipserv = 'N'\n";
		}
		    $sql .=  "GROUP BY
			        puc.puc_company_id
      		), pages_rfqs AS
			(
				SELECT
					spb_branch_code
					,count(distinct rfq_internal_ref_no) pages_rfq_cnt 
				FROM
					rfq r
				WHERE
					r.rfq_is_latest=1
					AND r.rfq_submitted_date > ADD_MONTHS(SYSDATE, :range)
					AND r.rfq_pages_rfq_id IS NOT null
					AND NOT EXISTS (
						SELECT 1 FROM match_b_rfq_to_match r2m WHERE r2m.rfq_event_hash=r.rfq_event_hash
					)
				GROUP BY 
					spb_branch_code
			), spotlight as (
				SELECT
					psl_spb_branch_code,
					COUNT(*) spotlight_listing
				FROM
					pages_spotlight_listing@livedb_link
				WHERE
					sysdate >= psl_expiration_from_date
					and sysdate <= psl_expiration_to_date
				GROUP BY
					psl_spb_branch_code
			), banner_adv AS 
			(
				SELECT
					pab_tnid,
					COUNT(*) banner_adv
				FROM
					pages_active_banner@livedb_link
				GROUP BY
					pab_tnid

			), catalogue AS 
			(
				SELECT 
					o.tnid,
					count(*) catalogue_uploaded
				FROM
					catalogue_owner@livedb_link o,
					catalogue@livedb_link c
				WHERE
					o.id = c.owner_id
					and c.deleted_on_utc is null
					and c.is_published_in_pages = 1
					and c.valid_from_utc <= sysdate
					and c.valid_to_utc >= sysdate
				GROUP BY
					o.tnid
			), sso_listing AS 
			(
				SELECT
					sso_spb_branch_code
					,COUNT(*) sso_listing
				FROM
					supplier_sso@livedb_link
				WHERE
					sso_year = to_char(SYSDATE,'YYYY')
				GROUP BY
					sso_spb_branch_code
			), brand_owner AS
			(
				SELECT
					pcb_company_id
					,COUNT(*) brand_owner
				FROM
					pages_company_brands@livedb_link
				WHERE
					pcb_auth_level = 'OWN'
					and pcb_is_authorised = 'Y'
					and pcb_is_deleted <> 'Y'
				GROUP BY
					pcb_company_id
			), anonimity AS
			(
				SELECT 
					pep_owner_id,
					(
						CASE WHEN pep_anon = '0'  THEN
							'Show'
						WHEN pep_anon = '1' THEN
							'Anonymise'
						WHEN pep_anon = 'T' THEN
							'TradeNet Only'
						ELSE
							'-'
						END
					) anonimity
				FROM
					pages_endorsement_privacy@livedb_link
				WHERE
					pep_owner_type = 'SPB'
			), ad_network AS
			(
				SELECT 
					pcp_company_tnid,
					COUNT(*) ad_network
				FROM
					pages_company_publisher@livedb_link
				GROUP BY 
					pcp_company_tnid

			), active_promotion AS
			(
				SELECT
					sbr_spb_branch_code,
					(
						CASE WHEN sbr_rate_standard = sbr_rate_target
						THEN 'Silent'
						ELSE 'Active'
						END
					) active_promotion
				FROM
					supplier_branch_rate@livedb_link 
				WHERE
					sbr_rate_target > 0
					AND sbr_valid_from <= SYSDATE
					AND (
						sbr_valid_till IS NULL
						OR sbr_valid_till > SYSDATE
						)
			), ap_counts AS 
			(
				SELECT
					bsr_spb_branch_code,
					COUNT(distinct CASE WHEN bsr_status = 'targeted' THEN bsr_byb_branch_code END) ap_targeted_count,
					COUNT(distinct CASE WHEN bsr_status = 'excluded' THEN bsr_byb_branch_code END) ap_excluded_count
				FROM
					buyer_supplier_rate@livedb_link
				WHERE
					bsr_valid_from <= SYSDATE
					AND (
						bsr_valid_till IS NULL
						OR bsr_valid_till > SYSDATE
					)
				GROUP BY
					bsr_spb_branch_code
			), gmv AS
			(
				SELECT  
				  spb_branch_code,
				  SUM(ord_total_cost_usd) gmv
				FROM 
				  ord_traded_gmv
				WHERE
				  ord_orig_submitted_date  > ADD_MONTHS(SYSDATE, :range)
				GROUP BY
				  spb_branch_code
			), ssi AS (
				SELECT
					puc_company_id,
					count(*) supplier_search_impression
				FROM
					pages_impression_stats pss
					JOIN pages_user_company@livedb_link puc ON 
					pss.pss_user_code = puc.puc_psu_id
					and puc_company_type='SPB'
					and puc_status='ACT'
					and pss_view_date > ADD_MONTHS(SYSDATE, :range)
				GROUP BY
					puc_company_id
			), search_events AS
			(
				SELECT
					puc_company_id,
					count(*) search_events
				FROM
					pages_search_stats pst
					JOIN pages_user_company@livedb_link puc ON 
					pst.pst_user_code = puc.puc_psu_id
					and puc_company_type='SPB'
					and puc_status='ACT'
					and pst_search_date > ADD_MONTHS(SYSDATE, :range)
				GROUP BY
					puc_company_id 
			), join_requests AS 
			(
				SELECT
					pucr_company_id,
					count(*) pending_join_requests
				FROM
					pages_user_company_request@livedb_link 
				WHERE
					pucr_company_type = 'SPB'
					AND pucr_status = 'PEN'
				GROUP BY 
					pucr_company_id 
			), user_a AS 
			(
				SELECT
					puc_company_id,
					COUNT(DISTINCT CASE WHEN pu.psu_email_confirmed = 'Y' THEN puc_psu_id END) verified_user_accounts,
					COUNT(DISTINCT CASE WHEN pu.psu_email_confirmed != 'Y' THEN puc_psu_id END) non_verified_user_accounts
				FROM
					pages_user_company@livedb_link puc JOIN
					pages_user@livedb_link pu
						ON (
							pu.psu_id = puc.puc_psu_id
						)
				WHERE
					puc_company_type = 'SPB'
					and pu.psu_email_confirmed = 'Y'
				GROUP BY
					puc.puc_company_id
			), activeCompanyes AS 
			(
				SELECT distinct
				  ue.days_with_events,
				  ue.sir3_report_views,
				  ue.successfull_sign_ins,
				  ue.user_failed_login,
				  ue.ap_report_views,
				  ue.contact_requests,
				  pr.pages_rfq_cnt,
 		          puc.puc_company_id,
		          s.spb_product_level,
		          s.spb_name,
		          s.spb_country,
		          s.spb_city,
              	  s.spb_created_date,
              	  s.spb_pcs_score,
              	  s.spb_time_difference_from_gmt,
              	  psl.spotlight_listing,
              	  dll.name as directory_listing_level,
              	  pab.banner_adv,
              	  cat.catalogue_uploaded,
              	  bo.brand_owner,
              	  sso.sso_listing,
              	  pep.anonimity,
              	  pcp.ad_network,
              	  active_promotion,
				  ap_targeted_count,
				  ap_excluded_count,
				  gmv,
				  pap.all_pending_count,
				  ssi.supplier_search_impression,
				  se.search_events,
				  jr.pending_join_requests,
				  uac.verified_user_accounts,
				  uac.non_verified_user_accounts,
		    	  s.spb_mtml_supplier,
		    	  s.spb_smart_product_name
				FROM
				  pages_user_company@livedb_link puc
				  -- Change to LEFT join to list all branches even wo event
				  JOIN user_events ue ON
				  (
				  	ue.puc_company_id = puc.puc_company_id
				  )
		          JOIN supplier_branch@livedb_link s 
		            ON (
		              s.spb_branch_code = puc.puc_company_id
		 			  and s.spb_sts in ('ACT', 'PEN')
		              and lower(s.spb_name || s.spb_branch_code) like :bname
		            )
				  LEFT JOIN pages_rfqs pr ON
					  (
					  	pr.spb_branch_code = puc.puc_company_id
					  )
				  LEFT JOIN directory_listing_level@livedb_link dll
	            	ON (
	            		dll.id = s.directory_listing_level_id
	            	)
				  LEFT JOIN spotlight psl ON
				  	(
				  		psl.psl_spb_branch_code = puc.puc_company_id
				  	)
				  LEFT JOIN banner_adv pab ON
				  	(
				  		pab.pab_tnid = puc.puc_company_id
				  	)
				  LEFT JOIN catalogue cat ON
				  	(
				  		cat.tnid = puc.puc_company_id
				  	)
				  LEFT JOIN brand_owner bo ON
				  	(
				  		bo.pcb_company_id = puc.puc_company_id
				  	)
				  LEFT JOIN sso_listing sso ON 
				  	(
				  		sso.sso_spb_branch_code = puc.puc_company_id
				  	)
				  LEFT JOIN anonimity pep ON
				  	(
				  		pep.pep_owner_id = puc.puc_company_id
				  	)
				  LEFT JOIN ad_network pcp ON
				  	(
				  		pcp.pcp_company_tnid = puc.puc_company_id
				  	)
				  LEFT JOIN active_promotion ap ON
				  	(
				  		ap.sbr_spb_branch_code = puc.puc_company_id
				  	)
				  LEFT JOIN ap_counts ac ON 
				  	(
				  		ac.bsr_spb_branch_code = puc.puc_company_id
				  	)
				  LEFT JOIN gmv g ON
				  	(
				  		g.spb_branch_code = puc.puc_company_id
				  	)
				  LEFT JOIN ssi ON
				  	(
				  		ssi.puc_company_id = puc.puc_company_id
				  	)
				  LEFT JOIN search_events se ON
				  	(
				  		se.puc_company_id = puc.puc_company_id
				  	)
				  LEFT JOIN join_requests jr ON
				  	(
				  		jr.pucr_company_id = puc.puc_company_id
				  	)
				  LEFT JOIN user_a uac ON
				  	(
				  		uac.puc_company_id = puc.puc_company_id
				  	)
				,pending_ap pap
				WHERE
				  puc_company_type = 'SPB'
				  and PUC_STATUS in ('ACT', 'PEN')
			), aggregated AS
			(
				SELECT
					nvl(ac.days_with_events, 0) days_with_events,
					nvl(ac.sir3_report_views, 0) sir3_report_views,
					nvl(ac.successfull_sign_ins, 0) successfull_sign_ins,
					nvl(ac.user_failed_login, 0) user_failed_login,
					nvl(ac.ap_report_views, 0) ap_report_views,
					nvl(ac.contact_requests, 0) contact_requests,
					nvl(ac.pages_rfq_cnt, 0) pages_rfq_cnt,
					ac.puc_company_id,
					ac.spb_product_level,
					ac.spb_name,
					ac.spb_country,
					ac.spb_city,
				 	TO_CHAR(ac.spb_created_date,'DD Mon YYYY') spb_created_date,
 					nvl(ac.spb_pcs_score, 0) spb_pcs_score,
					ac.spb_time_difference_from_gmt,
					CASE WHEN ac.spotlight_listing IS NULL THEN 'N' ELSE 'Y' END spotlight_listing,
					ac.directory_listing_level,
					CASE WHEN ac.banner_adv IS NULL THEN 'N' ELSE 'Y' END banner_adv,
					CASE WHEN ac.catalogue_uploaded IS NULL THEN 'N' ELSE 'Y' END catalogue_uploaded,
					CASE WHEN ac.sso_listing IS NULL THEN 'N' ELSE 'Y' END sso_listing,
					CASE WHEN ac.brand_owner IS NULL THEN 'N' ELSE 'Y' END brand_owner,
					nvl(ac.anonimity, 'Show') anonimity,
					CASE WHEN ad_network IS NULL THEN 'N' ELSE 'Y' END ad_network,
					nvl(active_promotion, 'N') active_promotion,
					nvl(ap_targeted_count, 0) ap_targeted_count,
				    nvl(ap_excluded_count, 0) ap_excluded_count,
				    nvl(verified_user_accounts, 0) verified_user_accounts,
				    nvl(non_verified_user_accounts, 0) non_verified_user_accounts,
				    nvl(supplier_search_impression, 0) supplier_search_impression,
				    nvl(search_events, 0) search_events,
				    nvl(pending_join_requests ,0) pending_join_requests,
				    ROUND(nvl(gmv, 0), 0) gmv,
				    all_pending_count - nvl(ap_targeted_count,0) -  nvl(ap_excluded_count,0) ap_pending_count,
		    		(CASE
		    			WHEN ac.spb_mtml_supplier = 'Y' then 'Integrated'
            			WHEN ac.spb_smart_product_name = 'SmartSupplier' OR ac.spb_smart_product_name = 'SmartSupplier Trial'
		    			THEN 'SmartSupplier'
            			ELSE 'StartSupplier'
		    			END
		    		) membership_level

					,(
						SELECT
							nr_days
						FROM
							nr_days
					) nr_days
				FROM
					activeCompanyes ac
			), ordertable AS
			(
				SELECT
					*
				FROM
					aggregated\n";

		//Implement additional filters
		$isWhere = true;

		switch ($productLevel) {
			case 1:
				$sql .= "WHERE membership_level in ('Integrated', 'SmartSupplier')\n";
				$isWhere = false;
				break;
			case 2:
				$sql .= "WHERE membership_level in ('Integrated')\n";
				$isWhere = false;
				break;
			case 3:
				$sql .= "WHERE membership_level in ('SmartSupplier')\n";
				$isWhere = false;
				break;
			case 4:
				$sql .= "WHERE membership_level in ('StartSupplier')\n";
				$isWhere = false;
				break;
			default:
				break;
		}

		//Implement country filter
		if ($country !== '') {
			$operator =  ($isWhere === true) ? "WHERE" : "AND";
			$sql .= $operator." spb_country = :country\n";
			$isWhere = false;
		}

		if ($timezoneName !== '') {
			$operator =  ($isWhere === true) ? "WHERE" : "AND";
			$sql .= $operator." spb_time_difference_from_gmt = :timeZoneDiff\n";
			$isWhere = false;
		}
		
		//Implementing GMV filter
		if ($gmvFrom !== '') {
			$operator =  ($isWhere === true) ? "WHERE" : "AND";
			$sql .= $operator." gmv >= :gmvFrom\n";
			$isWhere = false;
		}
		
		if ($gmvTo !== '') {
			$operator =  ($isWhere === true) ? "WHERE" : "AND";
			$sql .= $operator." gmv <= :gmvTo\n";
			$isWhere = false;
		}

		//Ordering by the selected column
		$sql .= "\nORDER BY " .  $this->getOrderBy() . " " . $sortOrder . ")
			SELECT
				*
			FROM
				ordertable";

		//Implement display limit 500 must be by default, discussed w Stuart
		if ($limit > 0) {
			$sql .= "\nWHERE
					ROWNUM >= :itemFrom
					and ROWNUM < :itemTo";
		}

		$params = array(
				'bname' => '%'.$name.'%',
				'range' => -$range,
			);

		//If we have limit , add these parameters
		if ($limit > 0) {
			$params['itemFrom'] = 0; //Currently it is fiexd 0, if we will do some pagination we can implement it
			$params['itemTo'] = $limit;
		}

		//Add country field if we filtering by country
		if ($country !== '') {
			$params['country'] = $country;
		}

		//Adding tmezone difference
		if ($timezoneName !== '') {
			$params['timeZoneDiff'] = $this->getTimezoneOffset('UTC', $timezoneName);
		}
		
		//implementing GMV  params
		if ($gmvFrom !== '') {
			$params['gmvFrom'] = (int)$gmvFrom;
		}
		
		if ($gmvTo !== '') {
			$params['gmvTo'] = (int)$gmvTo;
		}

		//echo $sql.print_r($params, true); die();
		$cacheKey = __METHOD__ . '_' . md5($sql.print_r($params, true));
        $records =$this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

		return $records;
		
	}

	/**
	* Retrurn the field name for SQL order by statement
	* @return string Order by field name
	*/
	protected function getOrderBy()
	{
		$order = 'spb_name';
		$sortBy = (array_key_exists('sortBy', $this->params)) ? $this->params['sortBy'] : false;
		if ($sortBy) {
			$fieldDefs = Shipserv_Report_SupplierUsage_FieldDefinitions::getInstance();
			$field = $fieldDefs->getRootReportFieldName($sortBy);
			if ($field) {
				$order = $field;
			}
		}

		return $order;
	}

	/**
	* Get time zone difference
	* @param string $remoteTz Remote TimeZone
	* @param string $originTz Original TimeZone
	* @return float TimeZone differenc in days
	*/
	protected function getTimezoneOffset($remoteTz, $originTz)
	{
	    $originDtz = new DateTimeZone($originTz);
	    $remoteDtz = new DateTimeZone($remoteTz);
	    $originDt = new DateTime("now", $originDtz);
	    $remoteDt = new DateTime("now", $remoteDtz);
	    $offset = ($originDtz->getOffset($originDt) - $remoteDtz->getOffset($remoteDt)) / 3600;
	    return $offset;
	}
}