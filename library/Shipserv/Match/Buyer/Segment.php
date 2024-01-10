<?php
/**
 * 
 * @author attilaolbrich
 * Retuning match segments, keyword sets
 */

class Shipserv_Match_Buyer_Segment extends Shipserv_Object
{
	protected $currentDate;
	/**
	 * Setting the default values
	 */
	public function __construct()
	{
		$this->currentDate = date('Ymd');
	}
	/**
	 * Get the list of segments
	 * Returns valid segment count, where the supplier $bybBranchCode has transaction
	 * @param string $bybBranchCode
	 * @return array
	 */
	public function getSegments($bybBranchCode)
	{
		
		$sql = "
				SELECT
				  ms.ms_id,
				  ms.ms_notes
				FROM
				  match_segment@livedb_link ms
				WHERE
				  (
					ms.ms_date_deleted IS NULL
					OR ms.ms_date_deleted > TO_DATE('" . $this->currentDate . "', 'YYYYMMDD')
				  )
				  AND ms.ms_date_start <= TO_DATE('" . $this->currentDate . "', 'YYYYMMDD')
				ORDER BY
				  ms.ms_notes";

		$key = md5(__CLASS__ . '_' . __FUNCTION__ . '_'. $sql);
		$result = $this->fetchCachedQuery($sql, array(), $key, Shipserv_Object::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);
		
		//DE7386 Oracle defect, sometimes referencing tables in subqueries brakes using DBLink, Instead of using "hack" proppsed by oracle, separeating the queries
		foreach (array_keys($result) as $key) {
			$result[$key]['VALID_SEGMENT_COUNT'] = $this->getKeywordCountBySegment($result[$key]['MS_ID'], $bybBranchCode);
		}
		
		return $this->camelCaseRecordSet($result);
	}
	
	/**
	 * Get All keywords by segment Id
	 * @param integer $segmentId
	 * @return array
	 */
	public function getAllKeywordsBySegment($segmentId)
	{
		$sql = "
			SELECT
				mss.mss_id,
				mss.mss_name
			FROM
				match_supplier_keyword_set mss
				JOIN match_segment_keyword_set msk ON
					mss.mss_id = msk.msk_mss_id
					AND msk.msk_date_added <= TO_DATE('" . $this->currentDate . "', 'YYYYMMDD')
					AND (
						msk.msk_date_removed IS NULL
						OR msk.msk_date_removed > TO_DATE('" . $this->currentDate . "', 'YYYYMMDD')
						)
			WHERE
				msk.msk_ms_id = :segmentId";
		
		$params = array(
				'segmentId' => (int)$segmentId
		);
		
		$key = md5(__CLASS__ . '_' . __FUNCTION__ . '_' . serialize($params) . '_' . $sql);
		$result = $this->fetchCachedQuery($sql, $params, $key);
		
		return $this->camelCaseRecordSet($result);
	}

	/**
	 * Get keywords by segment Id
	 * ‘only enabled keyword sets’ (or ‘only segment linked to at least one enabled keyword set’).
	 * Enabled keyword set is the set which is valid for being considered by AutoSource.
	 * Returns only segments where the supplier buyer brach code(s) has transaction
	 * @param integer $segmentId	The ID of the segment
	 * @param string $bybBranchCode	Can be a TNID or a comma separated list of TNIDs
	 * @return array
	 */
	public function getKeywordsBySegment($segmentId, $bybBranchCode)
	{
		$this->bybParams = $this->getBybParList($bybBranchCode);
		
		/*
		 * mso.mso_enabled = 1       
		 * on/off switch for supplier ownership of the set is on
		 */
		$sql = "
			SELECT DISTINCT
				mss.mss_id,
				mss.mss_name
			FROM
				" . $this->getKeywordBySegmentSql(':segmentId') . "
			ORDER BY
				mss.mss_name,
				mss.mss_id";
	
		$params = array(
				'segmentId' => (int)$segmentId
		);
	
		$params = array_merge($this->bybParams['params'], $params);
		
		$key = md5(__CLASS__ . '_' . __FUNCTION__ . '_' . serialize($params) . '_' . $sql);
		$result = $this->fetchCachedQuery($sql, $params, $key, Shipserv_Object::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);
	
		return $this->camelCaseRecordSet($result);
	}
	
	/**
	 * Get keyword count by segment Id
	 * ‘only enabled keyword sets’ (or ‘only segment linked to at least one enabled keyword set’).
	 * Enabled keyword set is the set which is valid for being considered by AutoSource.
	 * Returns only segments where the supplier buyer brach code(s) has transaction
	 * @param integer $segmentId	The ID of the segment
	 * @param string $bybBranchCode	Can be a TNID or a comma separated list of TNIDs
	 * @return integer
	 */
	public function getKeywordCountBySegment($segmentId, $bybBranchCode)
	{
		$this->bybParams = $this->getBybParList($bybBranchCode);
		
		$sql = "
			SELECT DISTINCT
				COUNT(DISTINCT mss.mss_id) valid_segment_count
			FROM
				" . $this->getKeywordBySegmentSql(':segmentId');
		
		$params = array(
				'segmentId' => (int)$segmentId
		);
		
		$params = array_merge($this->bybParams['params'], $params);
		
		$key = md5(__CLASS__ . '_' . __FUNCTION__ . '_' . serialize($params) . '_' . $sql);
		$result = $this->fetchCachedQuery($sql, $params, $key, Shipserv_Object::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);
		
		return (int)$result[0]['VALID_SEGMENT_COUNT'];
	}
	
	/**
	 * This serves a part of the SQL so we do not have to repeat this part
	 * 
	 * @param string $segmentFieldName
	 * @return string
	 */
	protected function getKeywordBySegmentSql($segmentFieldName)
	{
		return "match_supplier_keyword_set@livedb_link mss
				JOIN match_supplier_kwd_set_owner@livedb_link mso ON
					mso.mso_mss_id = mss.mss_id
					AND mso.mso_enabled = 1
				JOIN supplier_branch_match@livedb_link sbm ON
					sbm.sbm_spb_branch_code = mso.mso_sbm_spb_branch_code
					AND sbm.sbm_enabled_from <= TO_DATE('" . $this->currentDate . "', 'YYYYMMDD')
					AND (
						sbm.sbm_enabled_till IS NULL
						OR sbm.sbm_enabled_till > TO_DATE('" . $this->currentDate . "', 'YYYYMMDD')
					)
				JOIN match_buyer_kwd_set_owner@livedb_link mbo ON
					mbo.mbo_mss_id = mss.mss_id
					AND mbo.mbo_enabled = 1
				JOIN (
					SELECT
						DISTINCT temp_msr.msr_mss_id segments
					FROM
						match_supplier_rfq@livedb_link temp_msr
						JOIN 
							match_b_rfq_to_match temp_r 
						ON (
							temp_r.byb_branch_code IN (" . $this->bybParams['sqlParams']. ")
							and temp_msr.msr_rfq_event_hash = temp_r.rfq_event_hash
							AND NOT EXISTS (
							  SELECT 1 FROM rfq_resp r2
								WHERE
									r2.rfq_internal_ref_no=temp_r.rfq_internal_ref_no
									AND r2.spb_branch_code=temp_r.spb_branch_code
									AND r2.rfq_resp_sts='DEC'
							)
						)
						LEFT JOIN qot q1 on q1.qot_internal_ref_no = temp_r.cheapest_qot_spb_by_byb_100
						LEFT JOIN qot q2 on q2.qot_internal_ref_no = temp_r.cheapest_qot_spb_by_match_100
						LEFT JOIN buyer b on b.byb_branch_code = temp_r.byb_branch_code

						WHERE q1.qot_total_cost_usd > 0
						AND   q2.qot_total_cost_usd > 0

			            AND NOT EXISTS (
							SELECT
								NULL
							FROM 
								buyer_supplier_blacklist@livedb_link
							WHERE
								BSB_BYO_ORG_CODE = b.byb_byo_org_code
								AND BSB_SPB_BRANCH_CODE = temp_r.spb_branch_code
								AND (BSB_TYPE = 'blacksb' OR BSB_TYPE = 'blacklist')
						)
				) segmentlist ON
					segmentlist.segments = mso.mso_mss_id
			WHERE
				mss.mss_enabled = 1
				AND mss.mss_ms_id = " . $segmentFieldName;
	}
			
	/**
	 * Camel case the whole recordset
	 * 
	 * @param array $recordSet	Record set array
	 * @return array camel cased, and filtered recordset according to the selected page
	 */
	protected function camelCaseRecordSet($recordSet)
	{
		$data = array();
		foreach ($recordSet as $value) {
			$data[] = $this->camelCase($value);
		}
		return $data;
	}
	
	/**
	 * Create parameters for buyer branches to use parameterized query instead of just injection the list of buyers 
	 * Input can be a branch ID or a coma separated list of branches
	 * @param string $bybBranch
	 * @return array
	 */
	protected function getBybParList($bybBranch)
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
	
}
