<?php

class Shipserv_Report_Usage_Data_Root extends Shipserv_Report
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
	* @return array The database result fetched into an array
	*/
	public function getData()
	{
		
		$name = (array_key_exists('name', $this->params)) ?  strtolower($this->params['name']) : '';
		$range = (array_key_exists('range', $this->params)) ? (int)$this->params['range'] : 0;
		$excludeShipmate =  (array_key_exists('excludeSM', $this->params)) ? (strtolower($this->params['excludeSM']) == 'true') ? true : false : false;
		$sortOrder = (array_key_exists('sortOrder', $this->params)) ? (strtolower($this->params['sortOrder']) == 'asc') ? 'ASC' : 'DESC' : 'ASC';
		$timezoneName = (array_key_exists('timezone', $this->params)) ? ($this->params['timezone']) : '';
		//nr_days must be working days only, monday to fri...
		$sql = "
			WITH activeCompanyes AS 
			(
				SELECT
					pco_id
				FROM
					pages_company@livedb_link
				WHERE
					pco_type = 'BYO'
				UNION
				SELECT
					distinct puc_company_id pco_id
				FROM
					pages_user_company@livedb_link
				WHERE
					puc_company_type = 'BYO'
					and puc_status in ('ACT','PEN')
			), nr_days AS
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
      		), pages_rfq AS
			(
				SELECT
					r2.byb_byo_org_code,
					count(distinct r.rfq_internal_ref_no) pages_rfq_cnt 
				FROM
					rfq r join rfq r2 
					ON
					(
						r2.rfq_internal_ref_no = r.rfq_pages_rfq_id
					)
				WHERE
					r.rfq_is_latest=1
					AND r.rfq_submitted_date > ADD_MONTHS(SYSDATE, :range)
					AND r.rfq_pages_rfq_id IS NOT null
					AND NOT EXISTS (
						SELECT 1 FROM match_b_rfq_to_match r2m WHERE r2m.rfq_event_hash=r.rfq_event_hash
					)
				GROUP BY 
					r2.byb_byo_org_code
			), user_account AS 
			(
				SELECT
					puc_company_id,
					count(distinct CASE WHEN psu_email_confirmed = 'Y' THEN puc_psu_id END) verified_user_accounts,
					count(distinct CASE WHEN psu_email_confirmed != 'Y' THEN puc_psu_id END) non_verified_user_accounts
				FROM
					pages_user_company@livedb_link puc JOIN
					pages_user@livedb_link pu
					ON (
						pu.psu_id = puc.puc_psu_id
					)
				WHERE
					puc_company_type = 'BYO'
				GROUP BY
					puc.puc_company_id

			), pending_join_requests AS
			(
				SELECT
					pucr_company_id,
					count(*) pending_join_requests
				FROM
					pages_user_company_request@livedb_link 
				WHERE
					pucr_company_type = 'BYO'
					AND	pucr_status = 'PEN'
				GROUP BY  
					pucr_company_id
			), review AS
			(
				SELECT
					pue_endorser_id,
					count(*) review_requests,
					count(pue_created_date) review_requests_submitted
				FROM
					pages_user_endorsement@livedb_link
				WHERE
					pue_requested_date > ADD_MONTHS(SYSDATE, :range)
				GROUP BY
					pue_endorser_id
			), spend_bm_cnt AS 
			(
				SELECT
					mbs_byo_org_code,
					count(*) spend_benchmark_count
				FROM
					match_buyer_settings@livedb_link
				WHERE
					mbs_automatch = 1
				GROUP BY
					mbs_byo_org_code
			), search_events AS (
				SELECT
					puc_company_id,
					count(*) search_events
				FROM
					pages_search_stats pst
					JOIN pages_user_company@livedb_link puc ON 
						pst.pst_user_code = puc.puc_psu_id
				and puc_company_type='BYO'
				and puc_status='ACT'
				and pst_search_date > ADD_MONTHS(SYSDATE, :range)
				GROUP BY
				puc_company_id
			), automatic_compliance AS
			(
				SELECT
					bos_byo_org_code,
					count(*) automatic_compliance
				FROM
					buyer_org_setting@livedb_link
				WHERE
					bos_approved_supplier_enabled = 1
				GROUP BY
					bos_byo_org_code
			), user_impressions AS 
			(
				SELECT 
					puc_company_id,
					SUM(rui.rui_impression_count) impression_count,
					SUM(rui.rui_contact_view_count) contact_view_count
				FROM
					pages_reg_user_impression rui
				JOIN
					pages_user_company@livedb_link  puc
						on (
							puc.puc_psu_id = rui.rui_user_code
							and puc.puc_company_type = 'BYO'
							and puc.puc_status = 'ACT'
						)
				WHERE
					rui_view_date > TO_NUMBER(TO_CHAR(ADD_MONTHS(SYSDATE, :range), 'YYYYMMDD'))
				GROUP BY
					puc.puc_company_id
			), rfq_wo_imo AS
			(
				SELECT
				  b.byb_byo_org_code,
				  count(DISTINCT r.rfq_internal_ref_no) rfq_wo_imo_count
				FROM
				  rfq r JOIN buyer b on r.byb_branch_code = b.byb_branch_code
				WHERE
				  r.rfq_submitted_date > ADD_MONTHS(SYSDATE, :range)
				  and r.rfq_imo_no is null
				GROUP BY
				 b.byb_byo_org_code
			), ord_wo_imo AS
			(
				SELECT
				  b.byb_byo_org_code,
				  count(DISTINCT o.ord_internal_ref_no) ord_wo_imo_count
				FROM
				  ord o JOIN buyer b on o.byb_branch_code = b.byb_branch_code
				WHERE
				  o.ord_submitted_date > ADD_MONTHS(SYSDATE, :range)
				  and o.ord_imo_no is null
				GROUP BY
				 b.byb_byo_org_code
			), user_events AS
			(
				SELECT 
					 puc.puc_company_id
					,count(distinct CASE WHEN pua_activity = 'USER_LOGIN' THEN  PUA_ID END) successfull_sign_ins
					,count(distinct CASE WHEN pua_activity = 'USER_FAILED_LOGIN' THEN  PUA_ID END) user_failed_login
					,count(distinct CASE WHEN pua_activity = 'BUYER_TAB_CLICKED' THEN  PUA_ID END) buy_tab_clicked
					,count(distinct CASE WHEN pua_activity = 'SPEND_BENCHMARK_LAUNCH' THEN  PUA_ID END) spend_benchmarking_events
					,count(distinct CASE WHEN pua_activity = 'MATCH_DASHBOARD_LAUNCH' THEN  PUA_ID END) match_dashboard_launch
					,count(distinct CASE WHEN pua_activity = 'MATCH_REPORT_LAUNCH' THEN  PUA_ID END) match_report_launch
					,count(distinct CASE WHEN pua_activity = 'TRANSACTION_MONITOR_LAUNCH' THEN  PUA_ID END) txnmon_launch
					,count(distinct CASE WHEN pua_activity = 'WEBREPORTER_LAUNCH' THEN  PUA_ID END) webreporter_launch
					,count(distinct CASE WHEN pua_activity = 'IMPA_PRICE_BENCHMARK_SERVED' THEN  PUA_ID END) impa_price_benchmark_served
					,count(distinct CASE WHEN pua_activity = 'IMPA_SPEND_TRACKER_SERVED' THEN  PUA_ID END) impa_spend_tracker_served
					,count(distinct CASE WHEN pua_activity = 'TOTAL_SPEND_SERVED' THEN  PUA_ID END) total_spend_served
					,count(distinct CASE WHEN pua_activity = 'APPROVED_SUPPLIERS_CLICK' THEN  PUA_ID END) approved_suppliers
					,count(distinct CASE WHEN pua_activity = 'SPR_REPORT_VIEWS' THEN  PUA_ID END) spr_report_views
				    ,count(distinct CASE WHEN TO_NUMBER(TO_CHAR(pua_date_created, 'D')) >= 1 AND TO_NUMBER(TO_CHAR(pua_date_created, 'D')) <=5 THEN TO_CHAR(pua_date_created, 'YYYYMMDD') END) days_with_events
				FROM
					pages_user_activity pua
				JOIN
					pages_user_company@livedb_link  puc
					on (
						puc.puc_psu_id = pua.pua_psu_id
						and puc.puc_company_type = 'BYO'
						and puc.puc_status = 'ACT'
					)
				WHERE
					pua_date_created > ADD_MONTHS(SYSDATE, :range)\n";
				if ($excludeShipmate) {
					$sql .= "and pua_is_shipserv = 'N'\n";
				}
				$sql .= "GROUP BY
					puc.puc_company_id
			), base AS
			(
				SELECT DISTINCT 
					  b.byb_byo_org_code
					, nvl(ue.successfull_sign_ins, 0) successfull_sign_ins
					, nvl(ue.user_failed_login, 0) user_failed_login
					, nvl(ue.buy_tab_clicked, 0) buy_tab_clicked
					, nvl(ue.spend_benchmarking_events, 0) spend_benchmarking_events
					, nvl(ue.match_dashboard_launch, 0) match_dashboard_launch
					, nvl(ue.match_report_launch, 0) match_report_launch
					, nvl(ue.txnmon_launch, 0) txnmon_launch
					, nvl(ue.webreporter_launch, 0) webreporter_launch
					, nvl(ue.impa_price_benchmark_served, 0) impa_price_benchmark_served
					, nvl(ue.impa_spend_tracker_served, 0) impa_spend_tracker_served
					, nvl(ue.total_spend_served, 0) total_spend_served
					, nvl(ue.approved_suppliers, 0) approved_suppliers
					, nvl(ue.days_with_events, 0) days_with_events
					, nvl(ue.spr_report_views, 0) spr_report_views
					, nvl(prfq.pages_rfq_cnt, 0) pages_rfq_cnt
					, nvl(uac.verified_user_accounts, 0) verified_user_accounts
					, nvl(uac.non_verified_user_accounts, 0) non_verified_user_accounts
					, nvl(pjr.pending_join_requests, 0) pending_join_requests
					, nvl(rw.review_requests, 0) review_requests
					, nvl(rw.review_requests_submitted, 0) review_requests_submitted
					, nvl(sbc.spend_benchmark_count, 0) spend_benchmark_count
					, nvl(se.search_events, 0) search_events
					, nvl(ac.automatic_compliance, 0) automatic_compliance
					, nvl(ui.impression_count ,0) supplier_search_impression
					, nvl(ui.contact_view_count ,0) contact_requests
					, nvl(rwi.rfq_wo_imo_count, 0) + nvl(owi.ord_wo_imo_count, 0) rfq_ord_wo_imo_count
					, bo.byo_name
					, TO_CHAR(byo_created_date,'YYYY-MM-DD HH24:MI:SS') byo_created_date
				FROM
					BUYER b
					JOIN buyer_organisation@livedb_link bo
						ON (
							bo.byo_org_code = b.byb_byo_org_code
						)\n";
				if ($timezoneName !== '') {
					$sql .= "JOIN buyer_branch@livedb_link bb
								ON (
									bb.byb_branch_code = b.byb_branch_code
								)\n";
				}

				$sql .= "LEFT JOIN user_events ue
							ON (
						  		ue.puc_company_id = b.byb_byo_org_code
						  	)
						LEFT JOIN pages_rfq prfq
							ON (
								prfq.byb_byo_org_code = b.byb_byo_org_code
							)
						LEFT JOIN automatic_compliance ac
							ON (
								ac.bos_byo_org_code = b.byb_byo_org_code
							)
						LEFT JOIN search_events se 
							ON (
								se.puc_company_id = b.byb_byo_org_code
							)
						LEFT JOIN spend_bm_cnt sbc
							ON (
								sbc.mbs_byo_org_code = b.byb_byo_org_code
							)
						LEFT JOIN review rw
							ON (
								rw.pue_endorser_id = b.byb_byo_org_code
							)
						LEFT JOIN pending_join_requests pjr
							ON (
								pjr.pucr_company_id = b.byb_byo_org_code
							)
						LEFT JOIN user_account uac
							ON (
								uac.puc_company_id = b.byb_byo_org_code
							)
						LEFT JOIN user_impressions ui
							ON (
								ui.puc_company_id = b.byb_byo_org_code
							)
						LEFT JOIN rfq_wo_imo rwi
							ON (
								rwi.byb_byo_org_code = b.byb_byo_org_code
							)
						
						LEFT JOIN ord_wo_imo owi
							ON (
								owi.byb_byo_org_code = b.byb_byo_org_code
							)
				WHERE
					lower(b.byb_byo_org_code || bo.byo_name) like :bname
					and b.byb_is_test_account = 0\n";

				if ($timezoneName !== '') {
					$sql .= "and bb.byb_time_difference_from_gmt = :timeZoneDifference\n";
				}

				$sql .= "and b.byb_byo_org_code in
					(
						SELECT
							pco_id org_code
						FROM
							activeCompanyes
						UNION
						SELECT
							pbn.pbn_norm_byo_org_code org_code
						FROM
							activeCompanyes ac JOIN pages_byo_norm@livedb_link pbn
							ON (
								pbn.pbn_byo_org_code=ac.pco_id
							)
					)
			), aggregated AS
			(  
				SELECT
					b.*
					,(
						SELECT
							nr_days
						FROM
							nr_days
					) nr_days
					,(
						SELECT
							COUNT (*) 
						FROM
							buyer b2
						WHERE
							b2.byb_is_inactive_account = 0
							and b2.byb_is_test_account = 0
						START WITH 
							b2.byb_byo_org_code = b.byb_byo_org_code
						--	or b2.byb_byo_org_code IN (
						--		SELECT pbn_byo_org_code FROM pages_byo_norm@livedb_link WHERE pbn_norm_byo_org_code=b.byb_byo_org_code
						--	)
						CONNECT BY NOCYCLE PRIOR b2.byb_branch_code = b2.parent_branch_code
					) active_accounts
					,nvl((
						SELECT
						(
							CASE WHEN PEP_ANON = '0'  THEN
								'All suppliers'
							WHEN PEP_ANON = '1' and pep_ref_id=0 THEN
								'Full Anonymity'
							WHEN PEP_ANON = '1' and pep_ref_id>0 THEN
								'Exceptions'
							WHEN PEP_ANON = 'T' THEN
								'TradeNet Only'
							ELSE
								null
							END
						) anonimity
						FROM
							PAGES_ENDORSEMENT_PRIVACY@livedb_link pep
						WHERE
							PEP_OWNER_TYPE = 'BYO'
							and PEP_OWNER_ID = b.byb_byo_org_code
							and not (PEP_ANON = '0' and pep_ref_id=0)
							and rownum = 1
					),'All suppliers') anonimity_level
					,(
					SELECT
						COUNT(CASE WHEN bbs_rmdr_rfq_is_enabled = 1 THEN 1 END)
					FROM
						buyer b2 
						JOIN buyer_branch_setting@livedb_link bbs
						on (
							bbs.BBS_BYB_BRANCH_CODE = b2.byb_branch_code
						)
					WHERE
						b2.byb_is_inactive_account = 0
						and b2.byb_is_test_account = 0
						START WITH
							b2.byb_byo_org_code = b.byb_byo_org_code
						--	or b2.byb_byo_org_code IN (
						--		SELECT pbn_byo_org_code FROM pages_byo_norm@livedb_link WHERE pbn_norm_byo_org_code=b.byb_byo_org_code
						--	)
						CONNECT BY NOCYCLE PRIOR b2.byb_branch_code = b2.parent_branch_code
					) rfq_reminder
					,(
					SELECT
						COUNT(CASE WHEN bbs_rmdr_ord_is_enabled = 1 THEN 1 END)
					FROM
						buyer b2 
						JOIN buyer_branch_setting@livedb_link bbs
						on (
							bbs.BBS_BYB_BRANCH_CODE = b2.byb_branch_code
						)
					WHERE
						b2.byb_is_inactive_account = 0
						and b2.byb_is_test_account = 0
						START WITH 
							b2.byb_byo_org_code = b.byb_byo_org_code
						--	or b2.byb_byo_org_code IN (
						--		SELECT pbn_byo_org_code FROM pages_byo_norm@livedb_link WHERE pbn_norm_byo_org_code=b.byb_byo_org_code
						--	)
						CONNECT BY NOCYCLE PRIOR b2.byb_branch_code = b2.parent_branch_code
					) ord_reminder
				FROM 
					base b
			)

			SELECT
				*
			FROM
				aggregated 
			ORDER BY " .  $this->getOrderBy() . " " . $sortOrder;

		$params = array(
				'bname' => '%'.$name.'%',
				'range' => -$range
				);

		if ($timezoneName !== '') {
			$params['timeZoneDifference'] = $this->getTimezoneOffset('UTC', $timezoneName);
		}
		
		//print $sql; print_r($params); die();
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
		$order = 'byb_byo_org_code';
		$sortBy = (array_key_exists('sortBy', $this->params)) ? $this->params['sortBy'] : false;
		if ($sortBy) {
			$fieldDefs = Shipserv_Report_Usage_FieldDefinitions::getInstance();
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