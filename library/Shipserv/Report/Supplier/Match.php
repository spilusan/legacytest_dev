<?php
/*
* Supplier Match Report
* Author: Attila O
* 04/09/2015
*/
class Shipserv_Report_Supplier_Match extends Shipserv_Report
{

	const
		  MEMCACHE_TTL = 3600
		, MEMCAHCE_KEY = '_Shipserv_Report_Supplier_Match_'
	;

	/**
	* Return  the list of suppliers, where Match RFQ's were sent
	* @param integer $bybBranchCode Buyer Branch code
	* @param string $vessel Vessel name, (In case of 'All' Vessel filter is not applied )
	* @param integer $date Number of months, from current day
	* @param integer $page Pagination page
	* @param integer $itemsPerPage Item on one pagination
	* @return array
	*/
	public function getSupplierList($bybBranchCode, $vessel, $segmentId, $keyWordId, $quality, $sameq,  $date, $page = 1, $itemsPerPage = 25, $purgeCache = false, $paginated = true, $spbBranchCode = null, $listSegment = false)
	{
		$bybParams = $this->getBybParList($bybBranchCode);
		$sql =  $this->getBaseSql($spbBranchCode, $bybBranchCode, $vessel, $segmentId, $keyWordId, $quality, $sameq, $date, false) .
			"
			SELECT
				DISTINCT
				 sp.spb_branch_code
				,sp.spb_name
				,(
					SELECT
						cnt_name
					FROM
						country@livedb_link
					WHERE
					cnt_country_code = sp.spb_country_code
				) spb_country_code
			    
			    ,(CASE WHEN 
			    	(SELECT spb_branch_code FROM ord o WHERE o.spb_branch_code = sp.spb_branch_code and o.byb_branch_code IN(".$bybParams['sqlParams'].")\n";
			   	if ($date != 0) {	
				   	$sql .= "
				   		and o.ord_submitted_date BETWEEN add_months(SYSDATE, :months) AND SYSDATE
				   		\n";
				   	}
			   	$sql .= "
		   		and rownum = 1) = sp.spb_branch_code THEN 'Yes' ELSE 'No' END
		   		)  has_order
				,COUNT(distinct qot_internal_ref_no) total_quotes
				, (
					MIN(qot_submitted_date) 
				) first_quote_date
				, (
		          ROUND (
		          SUM(CASE WHEN corrected_saving > 0 then 1 else 0 end) / COUNT(corrected_saving) * 100
		          , 0
		          )
		        ) Quote_cheaper
				,sum(corrected_saving) saving_total
			FROM
				match_drilldown_base  dd JOIN supplier sp ON sp.spb_branch_code = dd.match_spb_branch_code
			WHERE
				NOT EXISTS (
					SELECT
						NULL
					FROM 
						buyer_supplier_blacklist@livedb_link
					WHERE
						BSB_BYO_ORG_CODE = :bybOrgCode
						AND BSB_SPB_BRANCH_CODE = sp.spb_branch_code
						AND (BSB_TYPE = 'blacksb' OR BSB_TYPE = 'blacklist')
					)
			GROUP BY
				 sp.spb_branch_code
				,sp.spb_name
				,sp.spb_country_code
			ORDER BY
				saving_total DESC
			";

		$params = $bybParams['params'];

		$sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		$params['bybOrgCode'] = (int)$sessionActiveCompany->id;

		if ($spbBranchCode) {
			$params['spbBranchCode'] = $spbBranchCode;
		}
		
		if ($date != 0) {
			$params['months'] = -(int)$date;
		}
		
		if ($vessel != 'All') {
			$params['vessel'] = $vessel;
		}

		if ($keyWordId) {
			$params['keywordSetId'] = $keyWordId;
		} else if ($segmentId) {
			$params['segmentId'] = $segmentId;
		}
		
		/*
		print($sql); print_r($params); die();
		*/

        $cacheKey = self::MEMCAHCE_KEY . __METHOD__ . '_'. md5($sql . "_" . serialize($params));
        if ($purgeCache === true) {
        	$this->purgeAllFromMemcache();
        }
        
        $recordSet = $this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);
        if ($spbBranchCode) {
        	//We do not use cache if fetching for one supplier, as this fuction is called after update comparibility
        	$db = Shipserv_Helper_Database::getSsreport2Db();
        	$recordSet = $db->fetchAll($sql, $params);
        } else {
        	$recordSet = $this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);
        }
        
        if ($paginated) {
        	$result = $this->camelCaseRecordSetAndPaginate($recordSet, $page, $itemsPerPage, $listSegment = false);
        } else {
        	$result = $this->camelCaseRecordSet($recordSet, $page, $itemsPerPage );
        }

		return array(
			'data' => $result ,
			'count' => ceil(count($recordSet)/$itemsPerPage)
			);
	}


	/**
	* Return  the list of Quotes, where Match RFQ's were sent, for a selected supplier, Returning details, saving
	* @param integer $spbBranchCode Supplier Branch code
	* @param integer $bybBranchCode Buyer Branch code
	* @param string $vessel Vessel name, (In case of 'All' Vessel filter is not applied )
	* @param integer $date Number of months, from current day
	* @param integer $page Pagination page
	* @param integer $itemsPerPage Item on one pagination
	* @return array
	*/
	public function getQuoteList($spbBranchCode, $bybBranchCode, $vessel, $segmentId, $keyWordId, $quality, $sameq, $date, $page = 1, $itemsPerPage = 25, $paginated=true)
	{

		$bybParams = $this->getBybParList($bybBranchCode);

		$sql = $this->getBaseSql($spbBranchCode, $bybBranchCode, $vessel, $segmentId, $keyWordId, $quality, $sameq, $date) . 
		"
		SELECT
			DISTINCT *
		FROM
			match_drilldown_base
		ORDER BY 
	 		  corrected_saving DESC
	 		, CASE WHEN exclude_reason is not null THEN 1 END
			, CASE WHEN quote_completeness < 100 OR quote_completeness IS NULL THEN 1 END
	 		, CASE WHEN best_quote_completeness < 100 OR best_quote_completeness IS NULL THEN 1 END
	 	";
		
		$params = $bybParams['params'];
		$params['spbBranchCode'] = (int)$spbBranchCode;

		if ($vessel != 'All') {
			$params['vessel'] = $vessel;
		}
		
		if ($date != 0) {
			$params['months'] = -(int)$date;
		}
		
		if ($keyWordId) {
			$params['keywordSetId'] = $keyWordId;
		} else if ($segmentId) {
			$params['segmentId'] = $segmentId;
		}
		
		/*
		print $sql; print_r($params); die();
		*/
		
		$cacheKey = self::MEMCAHCE_KEY . __METHOD__ . '_'. md5($sql . "_" . serialize($params));
		$recordSet = $this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

		if ($paginated) {
			$result = $this->camelCaseRecordSetAndPaginate($recordSet, $page, $itemsPerPage );
		} else {
			$result = $this->camelCaseRecordSet($recordSet, $page, $itemsPerPage );
		}

		/*
		* Replacing the values if the spare/brand is genuine or not. Possible values are yes = Y, no = N, unknown = U and not applicable= NA., or using reference table data in case of new data
		*/
		for ($i = 0; $i<count($result );$i++) {
			$result[$i]['qualityQot'] = $this->correctQuality($result[$i]['qualityQot']);
			$result[$i]['qualityCompatitiveQot'] =$this->correctQuality($result[$i]['qualityCompatitiveQot']);
		}

		return array(
			'data' => $result ,
			'count' => ceil(count($recordSet)/$itemsPerPage),
			);
	}


	/**
	* Return transaction details for the supplier, for the specified period of time
	* @param integer $spbBranchCode Supplier Branch code
	* @param integer $bybBranchCode Buyer Branch code
	* @param integer $date Number of months, from current day
	* @return array
	*/
	public function getSupplierData( $spbBranchCode, $bybBranchCode, $date )
	{
		$supplierDetails = $this->getSupplierDetails($spbBranchCode);


		$transactions = array(
				'you' => $this->getTransactionDetails($bybBranchCode ,$spbBranchCode, $date),
				'allMarket' => $this->getMarketTransactionDetails($spbBranchCode, $date),
		);

		return array(
			'supplier' => $supplierDetails,
			'transactions' => $transactions,
			);
	}


	/**
	* Return supplier details for specific TNID
	* @param integer $tnid TNOD
	* @return array
	*/
	protected function getSupplierDetails( $tnid )
	{
		$supplier = Shipserv_Supplier::fetch((int)$tnid);

		$categories = '';
		$certifications = '';

		//Add (max 5) categories
		$catCount = 0;
		foreach ($supplier->categories as $categorie) {
			if ($categorie['primary'] == '1'  && trim($categorie['name']) != '') {
				if ($catCount < 5) {
					$categories .= ($categories == '') ? trim($categorie['name']) : ', '.trim($categorie['name']);
					$catCount++;
				}
			}
		}

		// If there is was not one primary categorie, add max five other.
		if ($categories == '') {
			$catCount = 0;
			foreach ($supplier->categories as $categorie) {
				if ($categorie['primary'] != '1'  && trim($categorie['name']) != '') {
					if ($catCount < 5) {
						$categories .= ($categories == '') ? trim($categorie['name']) : ', '.trim($categorie['name']);
						$catCount++;
					}
				}
			}
		}

		foreach ($supplier->certifications as $certification) {
			if (trim($certification['name']) != '') {
				$certifications .= ($certifications == '') ? trim($certification['name']) : ', '.trim($certification['name']);
			}
		}
		if ($supplier->city == '') {
			$location = $supplier->countryName;
		} else {
			$location = (strtolower($supplier->countryName) == strtolower($supplier->city)) ? $supplier->countryName : $supplier->city.', '.$supplier->countryName;
		}
		
		return array(
			'name' => $supplier->name,
			'countryName' => $supplier->countryName,
			'countryCode' => $supplier->countryCode,
			'tradeRank' => $supplier->tradeRank,
			'tnid' => $supplier->tnid,
			'joinedDate' => $supplier->joinedDate,
			'categories' => $categories,
			'certifications' => $certifications,
			'logoUrl' => $supplier->logoUrl,
			'url' => ($supplier->isPublished())?$supplier->getUrl():"",
			'location' => ucwords($location),
			);
		return $supplier;
	}


	/**
	* Get transactin details for the specified period, for the specified branch
	* @return array
	*/
	protected function getTransactionDetails( $bybBranchCode, $spbBranchCode, $date )
	{
		$bybParams = $this->getBybParList($bybBranchCode);

		$sql = "
			SELECT
				 sum(ord_count) as transaction_count
				,count(DISTINCT byb_branch_code) buyer_count
			FROM
				ORD o
			WHERE
				o.byb_branch_code IN(".$bybParams['sqlParams'].")
				and o.spb_branch_code = :spbBranchCode
				";
			if ($date != 0) {
				$sql .= " AND ord_submitted_date  BETWEEN add_months(sysdate, :months) AND sysdate";
			}

		$params = $bybParams['params'];
		$params['spbBranchCode'] = $spbBranchCode;

		if ($date != 0) {
			$params['months'] = -(int)$date;
		}


		$cacheKey = self::MEMCAHCE_KEY . __METHOD__ . '_'. md5($sql . "_" . serialize($params));
		$result = $this->camelCaseRecordSet($this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2));
		return $result[0];
	}

	/**
	* Get the tranactins for the whole market, for the specified date range, months befor current day
	* @return array
	*/
	protected function getMarketTransactionDetails($spbBranchCode, $date )
	{
		$sql = "
			SELECT
				 COUNT(DISTINCT o.rfq_internal_ref_no) as transaction_count
				,count(DISTINCT o.byb_branch_code) buyer_count
			FROM
				ORD o
			WHERE
				o.spb_branch_code = :spbBranchCode
			";

			if ($date != 0) {
				$sql .= " AND o.ord_submitted_date  BETWEEN add_months(sysdate, :months) AND sysdate";
			}

		$params = array(
				'spbBranchCode' => $spbBranchCode
				 );

		if ($date != 0) {
			$params['months'] = -(int)$date;
		}



		$cacheKey = self::MEMCAHCE_KEY . __METHOD__ . '_'. md5($sql . "_" . serialize($params));
		$result = $this->camelCaseRecordSet($this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2));
		return $result[0];
	}

	/**
	* Camel casing and paginating record set
	* @param array $rec, Record set array
	* @param integer Page to return
	* @param integer Intems per one page
	* @return array camel cased, and filtered recordset according to the selected page
	*/
	protected function camelCaseRecordSetAndPaginate($rec, $page, $itemPerPage, $listSegment)
	{

		$from = ($page-1)*$itemPerPage;
		$count = count($rec);

		$to = (($from + $itemPerPage) < ($count-1) ) ? $from + $itemPerPage : $count;
		$data = array();
		for ($i = $from; $i <= $to-1 ; $i++) {
			$record = $this->camelCase($rec[$i]);
			if ($listSegment === true) {
				$record['category'] = $this->getSegmentList($record['spbBranchCode']);
			}
			$data[] = $record;
		}
		return $data;

	}

	/**
	* Camel case the whole recordset
	* @param array $rec, Record set array
	* @return array camel cased, and filtered recordset according to the selected page
	*/
	protected function camelCaseRecordSet( $rec )
	{
		$data = array();
		foreach ($rec as $value) {
			$data[] = $this->camelCase($value);
		}
		return $data;
	}

	/**
	* Return the base SQL of the queries, It can be aggregeted, If spbBranchCode not set, return all buyers, if Vessel is 'All' no vessel filter, if date is 0, no date filter, otherwise months back from current date
	* @param integer $spbBranchCode Supplier Branch code
	* @param integer $bybBranchCode Buyer Branch code
	* @param string $vessel Vessel name, (In case of 'All' Vessel filter is not applied )
	* @param integer $date Number of months, from current day
	* @return string
	*/
	protected function getBaseSql($spbBranchCode, $bybBranchCode, $vessel, $segmentId, $keyWordId, $quality, $sameq, $date, $full = true)
	{
		// With full=false, try to speed up the query, not executing subqueries, wich we don't need for summary 
		$bybParams = $this->getBybParList($bybBranchCode);
		$sql ="
		WITH base AS
		(
			SELECT
				 q.spb_branch_code match_spb_branch_code
				 \n";
				 if ($full == true) {
					$sql .= "
					, r.rfq_internal_ref_no
					, r.rfq_ref_no
					, r.rfq_subject
					, r.rfq_vessel_name
					, r.has_order
					, sc.spb_name best_byb_spb_name
					, sm.spb_name best_match_spb_name
					\n";
				}
				$sql .= "	
				, qb.qot_total_cost_usd best_Quoted_price
				, qq2.qot_total_cost_usd Quoted_price
				, q.qot_count qot_count
				, q.qot_submitted_date qot_submitted_date
				, q.qot_internal_ref_no qot_internal_ref_no
				, q.potential_saving saving
				,(SELECT qot_is_genuine_spare FROM quote@livedb_link quality WHERE quality.qot_internal_ref_no = r.cheapest_qot_spb_by_byb_100 AND rownum = 1) quality_compatitive_qot_id 
				,(SELECT qot_is_genuine_spare FROM quote@livedb_link quality WHERE quality.qot_internal_ref_no = qq2.qot_internal_ref_no AND rownum = 1) quality_qot_id
				\n";
				
				if ($full == true) {
					$sql .= ", (
						CASE
							WHEN r.rfq_line_count>0 THEN
							CASE WHEN qq.qot_line_count/r.rfq_line_count > 1 THEN 100 ELSE
								ROUND(qq.qot_line_count/r.rfq_line_count*100,0) END
							ELSE
								0
							END
					) best_quote_completeness
					,(
						CASE
							WHEN r.rfq_line_count>0 THEN
							CASE WHEN qq2.qot_line_count/r.rfq_line_count > 1 THEN 100 ELSE
								ROUND(qq2.qot_line_count/r.rfq_line_count*100,0) END
							ELSE
								0
							END
					) quote_completeness
					, (
						SELECT 
							QUA_QUR_ID
						FROM
			              quote_user_action@livedb_link
			            WHERE 
			              QUA_QOT_INTERNAL_REF_NO = q.qot_internal_ref_no
			              AND QUA_ACTION = 'stats_exclude'
			              AND rownum = 1
					  ) exclude_reason
						\n";
				}
				 $sql .=", (
		            CASE WHEN
		            NOT EXISTS
		            (
		            SELECT 
		              NULL
		            FROM
		              quote_user_action@livedb_link
		            WHERE 
		              QUA_QOT_INTERNAL_REF_NO = q.qot_internal_ref_no
		              AND QUA_ACTION = 'stats_exclude'
		            )
		            THEN
		              q.potential_saving
		            ELSE
		              0
		            END
		          ) corrected_saving
		FROM
			match_b_qot_match q JOIN match_b_rfq_to_match r
			ON (q.rfq_sent_to_match=r.rfq_internal_ref_no)
				LEFT OUTER JOIN match_b_qot_from_byb_spb qb
					ON (r.cheapest_qot_spb_by_byb_100=qb.qot_internal_ref_no)
					\n";
				if ($full == true) {
					$sql .= "
						LEFT JOIN qot qq ON (qb.qot_internal_ref_no=qq.qot_internal_ref_no)
							LEFT JOIN supplier sc ON sc.spb_branch_code = qq.spb_branch_code
							\n";
				}
			$sql .= "
			LEFT OUTER JOIN qot qq2
				ON (q.qot_Internal_ref_no=qq2.qot_internal_ref_no)
			 		LEFT JOIN supplier sm ON sm.spb_branch_code = qq2.spb_branch_code\n";
			
		if ($keyWordId) {
			$sql .= "
			JOIN match_supplier_rfq@livedb_link msr1 ON
				msr1.msr_rfq_event_hash = r.rfq_event_hash
				AND msr1.msr_mss_id = :keywordSetId\n";
		} else if ($segmentId) {
			$sql .= "
			JOIN match_supplier_rfq@livedb_link msr1 ON
				msr1.msr_rfq_event_hash = r.rfq_event_hash
				AND msr1.msr_mss_id IN 
				(
					SELECT DISTINCT
						mss.mss_id
					FROM
						match_supplier_keyword_set@livedb_link mss
						JOIN match_supplier_kwd_set_owner@livedb_link mso ON
							mso.mso_mss_id = mss.mss_id
							AND mso.mso_enabled = 1
						JOIN supplier_branch_match@livedb_link sbm ON
							sbm.sbm_spb_branch_code = mso.mso_sbm_spb_branch_code
							AND sbm.sbm_enabled_from <= SYSDATE
							AND (
								sbm.sbm_enabled_till IS NULL
								OR sbm.sbm_enabled_till > SYSDATE
							)
						JOIN match_buyer_kwd_set_owner@livedb_link mbo ON
							mbo.mbo_mss_id = mss.mss_id
							AND mbo.mbo_enabled = 1
					WHERE
						mss.mss_enabled = 1
						AND mss.mss_ms_id = :segmentId
				)\n";
		}
		$sql .= "
		WHERE
			r.byb_branch_code IN(".$bybParams['sqlParams'].")
			AND qb.qot_total_cost_usd > 0
			AND qq2.qot_total_cost_usd > 0
			AND NOT EXISTS (
				  SELECT 1 FROM rfq_resp r2
					WHERE
						r2.rfq_internal_ref_no=r.rfq_internal_ref_no
						AND r2.spb_branch_code=r.spb_branch_code
						AND r2.rfq_resp_sts='DEC'
				)
			AND q.qot_total_cost_usd > 0
		    ";

			if ($spbBranchCode) {
				$sql .= " AND q.spb_branch_code = :spbBranchCode ";
			}

			if ($date != 0) {
				$sql .= " AND r.rfq_submitted_date>add_months(SYSDATE, :months)";
			}

			if ($vessel != 'All') {
				$sql .= " and r.rfq_vessel_name =:vessel";
			}

			$sql .= ") ,match_drilldown_base AS
			(
				SELECT
					base.*
					, (
					CASE WHEN base.best_Quoted_price > 0 THEN
					ROUND(
						base.corrected_saving / base.best_Quoted_price * 100
						,0
						)
					END
					) potential_saving_percent
				";
			
			if ($full == true) {
				$sql .= "
				,(
					CASE WHEN LENGTH(TRIM(TRANSLATE(quality_compatitive_qot_id, ' +-.0123456789',' '))) is null AND quality_compatitive_qot_id is not null
					THEN
						(SELECT ref_value FROM reference@livedb_link WHERE ref_type ='QUOTEQUALITY' and ref_is_active = 1 and ref_id = TO_NUMBER(quality_compatitive_qot_id) and rownum = 1)
					ELSE
						quality_compatitive_qot_id END
				) quality_compatitive_qot 
				,(
					CASE WHEN LENGTH(TRIM(TRANSLATE(quality_qot_id, ' +-.0123456789',' '))) is null AND quality_qot_id is not null
					THEN
						(SELECT ref_value FROM reference@livedb_link WHERE ref_type ='QUOTEQUALITY' and ref_is_active = 1 and ref_id = TO_NUMBER(quality_qot_id) and rownum = 1)
					 ELSE
					 	quality_qot_id END
				) quality_qot
				";
			}
			
			$sql .= "
				FROM
					base";
			if ($quality !== null) {
				$sql .= "
					WHERE base.quality_qot_id IN (" . implode(",", $quality) . ")
					";
				if ($sameq === true) {
					$sql .= "
						AND base.quality_compatitive_qot_id IN (" . implode(",", $quality) . ")
					";
				}
			}
			
			$sql .= "
				)\n
			";

    	return $sql;
	}

	/**
	* Correct quality field, as the Quote table uses values from the old quality definition, and also uses the reference ID
	* @param string $quality, The mixed quality
	* @return string Converted quality name, if old values are in quality field, else the new quality name
	*/
	protected function correctQuality( $quality )
	{
		switch ($quality) {
			case Shipserv_Quote::GENUINE_YES:
				return 'Genuine';
				break;
			case Shipserv_Quote::GENUINE_NO:
				return 'Not Genuine';
				break;
			case Shipserv_Quote::GENUINE_NA:
				return 'Not Applicable';
				break;
			case Shipserv_Quote::GENUINE_UNKNOWN:
				return 'Unknown';
				break;
			case '':
				return 'Not specified';
				break;
			default:
				return $quality;
				break;
		}
	}

	protected function getBybParList( $bybBranch )
	{
		$result = '';
		$values = array();
		$list = explode(",", $bybBranch);
		for ($i = 0 ; $i<count($list);$i++) {
			$result .= ($result == '') ? ':bybBranchCode'.$i : ',:bybBranchCode'.$i;
			$values['bybBranchCode'.$i] = (int)$list[$i];
		}
		return array(
			'params' => $values,
			'sqlParams' => $result,
			);
	}
	
	/**
	 * Get the list of segments
	 * @param integer $spbBranchCode
	 */
	protected function getSegmentList($spbBranchCode)
	{
		$sql = "
		  SELECT
		    SUBSTR(SYS_CONNECT_BY_PATH (ms_notes , ','), 2) csv
		  FROM (
		    SELECT ms_notes,
		      ROW_NUMBER () OVER (ORDER BY ms_notes ) rn,
		      COUNT (*) OVER () cnt
		    FROM
		      match_segment_supplier mss
		      join match_segment ms
		      on ms.ms_id = mss.mss_ms_id
		   WHERE
		    mss.mss_spb_branch_code = :spbBranchCode
		    )
		  WHERE
		    rn = cnt
		  START WITH
		    rn = 1
		  CONNECT BY rn = PRIOR rn + 1";
		
		$params = array(
				'spbBranchCode' => $spbBranchCode
		);
		$cacheKey = self::MEMCAHCE_KEY . __METHOD__ . '_'. md5($sql.implode($params));
		$result = $this->fetchCachedQuery($sql, $params, $cacheKey);
		
		return  (count($result) > 0) ? $result[0]['CSV'] : '';
		
	}

	/**
	* Clear memcache for all Spend Benchmarking related requests
	*/
	public function purgeAllFromMemcache()
	{

		$result = false;
		$caches = $this->peekMemcache( self::MEMCAHCE_KEY );

		foreach( $caches as $key => $cache )
		{
			if( $this->purgeCache( $key ) )
			{
				$result = true;
			}
		}

		return $result;

	}

	/**
	* Export a data list to Excel for Spend Benchmarking report
	* Params, helper, and response is coming from the controller action $this->_helper,  $this->getResponse(), Data is an array of data. 
	* Returns csv to display
	*/
	public function exportToExcel($helper, $response, $data, $type, $supplierBranchCode = null)
	{


		$csv = "";
		$helper->layout->disableLayout();
        $helper->viewRenderer->setNoRender();

        if ($supplierBranchCode) {
        	$supplier =  $supplier = Shipserv_Supplier::getInstanceById($supplierBranchCode, "", true);
        }

        $response->setRawHeader( "Content-Type: application/vnd.ms-excel; charset=UTF-8" )
            ->setRawHeader( "Content-Disposition: attachment; filename=export.csv" )
            ->setRawHeader( "Expires: 0" )
            ->setRawHeader( "Cache-Control: must-revalidate, post-check=0, pre-check=0" )
            ->setRawHeader( "Pragma: public" );

        $header = true;
		
		//Definie titles, If no title name given, the field will not be displayed
         switch ($type) {
            case 'supplier':
                    $headerNames = array(
			            'spbBranchCode' => 'TNID',
			            'spbName' => 'Supplier',
			            'spbCountryCode' => 'Location',
			            'hasOrder' => 'Have you ordered from this supplier before?',
			            'totalQuotes' => 'Comparison Quotes Received',
			            'firstQuoteDate' => 'Date of First Comparison Quote',
		             	'quoteCheaper' => '% of Quotes Cheaper',
			            'savingTotal' => 'Potential Savings (USD)',
			         );
            
                break;
            case 'quote':
					$headerNames = array(
						'rfqRefNo' => 'RFQ Ref.',
						'rfqSubject' => 'RFQ Subject',
						'rfqVesselName' => 'Vessel Name',
						'bestBybSpbName' => 'Company',
						'qualityCompatitiveQot' => 'Quality',
						'bestQuoteCompleteness' => ' Quote Completeness',
						'bestQuotedPrice' => 'Quoted Price USD',
						'qualityQot' => 'Quality',
						'quoteCompleteness' => 'Quote Completeness',
						'quotedPrice' => 'Quoted Price USD',
						'correctedSaving' => 'USD',
			            'potentialSavingPercent' => '%',
			         );
					$csv .= '"RFQ Details",,,"Your Best Performing Supplier",,,,"'.str_replace('"', '""', $supplier->name).'",,,"Potential savings (USD)"'."\n";
                break;
            default:
                $headerNames = array();
                break;
        }

		//Generating CSV

		//First  create a header
		foreach ($headerNames as $key => $name) {
			if ($name != '') {
				$csv .= '"'.str_replace('"', '""', $name).'",';
			}
		}
		$csv .= "\n";

		foreach ($data as $value) {
		 	foreach ($headerNames as $key => $name) {
		 		if ($name != '') {
		 			$csv .= '"'.str_replace('"', '""', $value[$key]).'",';
		 		}
			}
			$csv .= "\n";
		 }

        return $csv;

	}

}
