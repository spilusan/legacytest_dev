<?php
class Shipserv_Match_Report_Buyer_Rfq extends Shipserv_Object 
{
	protected $rfqId;	
	
	function __construct($rfqId, $buyerId, $useArchive = false)
	{
		$this->rfqId = $rfqId;
		$this->buyerId = $buyerId;
		$this->db = Shipserv_Helper_Database::getDb();
		$this->reporting = Shipserv_Helper_Database::getSsreport2Db();
		$this->rfq = Shipserv_Rfq::getInstanceById($rfqId, null, $useArchive);
	}
	
	function getRfq()
	{
		return $this->rfq;
	}
	
	function getData()
	{
		return array_merge($this->getRfqSentToNonMatchSupplier(), $this->getRfqSentToMatchSupplier());
	}
	
	
	function getRfqSentToNonMatchSupplier()
	{
		$sql = "
			SELECT 
				t.*
				, get_actual_saving_by_rfq_v2(:rfqId) ACTUAL_SAVINGS
  				, get_potential_saving_by_rfq_v2(:rfqId) POTENTIAL_SAVINGS
				, CASE WHEN t.total_qot_li != 0 AND  t.total_rfq_li != 0 THEN
						CASE WHEN
							ROUND(t.total_qot_li / t.total_rfq_li * 100) > 100
						THEN
							100
						ELSE
							ROUND(t.total_qot_li / t.total_rfq_li * 100)
						END
					ELSE
						0 
					END
				  AS QOT_COMPLETENESS 
				, (
					SELECT 
						ord_total_cost_usd
					FROM
						ord
					WHERE
						ord_internal_ref_no=t.ord_internal_ref_no
				) ORD_TOTAL_PRICE_IN_USD
				
			FROM
			(	
				SELECT 
					s.spb_branch_code tnid
					, s.spb_name
					, s.spb_monetization_percent
					, get_dollar_value(q.qot_currency, q.qot_total_cost, q.qot_submitted_date) QOT_TOTAL_PRICE_IN_USD
					, r.rfq_submitted_date
					, r.rfq_ref_no
					, q.qot_submitted_date
					, q.qot_internal_ref_no
					, (				
		              	SELECT 
							po.ord_internal_ref_no 
		              	FROM 
							match_b_ord_from_byb_s_spb po
			            WHERE 
							po.rfq_sent_to_match=:rfqId
							AND po.spb_branch_code=s.spb_branch_code
							AND rownum=1 
				
					) ord_internal_ref_no
					, 'BUYER_SELECTED' RFQ_DESTINATION 
					, r.rfq_internal_ref_no
					, r.is_declined
			        , (
			            SELECT 
			              COUNT(*) 
			            FROM 
			              quote_line_item@livedb_link
			            WHERE 
			              qli_qot_internal_ref_no=q.qot_internal_ref_no
			              AND qli_total_line_item_cost > 0
			              AND 
			                ( qli_sts !='DEC' OR qli_sts IS null )
			          ) TOTAL_QOT_LI
					
			         , (
			            SELECT 
			              COUNT(*) 
			            FROM 
			              rfq_line_item@livedb_link 
			            WHERE 
			              rfl_rfq_internal_ref_no=r.rfq_internal_ref_no
			          ) TOTAL_RFQ_LI
              		 , row_number() OVER (PARTITION BY s.spb_branch_code, s.spb_name, r.rfq_ref_no ORDER BY q.qot_internal_ref_no DESC NULLS LAST) as qot_row_num
              		 , (
              		 	SELECT
              		 		qot_delivery_lead_time
              		 	FROM
              		 		quote@livedb_link liveqout
              		 	WHERE liveqout.qot_internal_ref_no = q.qot_internal_ref_no and rownum = 1
              		 	) qot_delivery_lead_time
              		 , (
              		 	SELECT
              		 		qot_is_genuine_spare
              		 	FROM
              		 		quote@livedb_link liveqout
              		 	WHERE liveqout.qot_internal_ref_no = q.qot_internal_ref_no and rownum = 1
              		 	) qot_is_genuine_spare
				FROM 
					match_b_rfq_also_sent_to_buyer r, 
					rfq r2,
					supplier s,
					match_b_qot_from_byb_spb q
				WHERE 
					r.byb_branch_code=:buyerId 
					AND r2.rfq_event_hash = HEXTORAW(:hash)
					AND r.rfq_internal_ref_no = r2.rfq_internal_ref_no  
					AND s.spb_branch_code!=999999
					AND s.spb_branch_code=r.spb_branch_code				
					AND r.rfq_internal_ref_no=q.rfq_internal_ref_no (+)
					AND r.spb_branch_code=q.spb_branch_code (+)
				    AND (
							(
			                    q.qot_internal_ref_no NOT IN (SELECT qot_internal_ref_no FROM match_b_imported_qot_on_match)
			                    AND q.qot_internal_ref_no NOT IN (SELECT qot_internal_ref_no FROM match_b_qot_match)
							)
							OR q.qot_internal_ref_no IS NULL
		          		)
				) t
        WHERE           
          qot_row_num=1				
		";
		$new = array();
		$params = array('buyerId'	=> $this->rfq->rfqBybBranchCode,
						'rfqId'		=> $this->rfq->rfqInternalRefNo, 
						'hash' 	    => $this->rfq->rfqEventHash);

		/* Added memcahing by Attila O  */
        $cacheKey = __CLASS__ . __METHOD__ . '_'. md5($sql.implode($params));
        $data = $this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);
		
		/* 	$data = $this->reporting->fetchAll($sql, $params); */
		
		foreach($data as $row)
		{
			// get links
			$new[] = $this->getLinkToAllDocumentsByRow($row);
		}
		
		return $new;
		
	}

	private function getLinkToAllDocumentsByRow($row)
	{
		$row['URL_QOT'] = '';
		$row['URL_ORD'] = '';
		$row['SUPPLIER_URL'] = '';
		
		if( $row['TNID'] != "" )
		{
			try
			{
				$supplier = Shipserv_Supplier::getInstanceById($row['TNID']);
				$row['SUPPLIER_URL'] = $supplier->getUrl();
			}catch(Exception $e){};
		}
		if( $row['QOT_INTERNAL_REF_NO'] != "" )
		{
			try
			{
				$quote = Shipserv_Quote::getInstanceById($row['QOT_INTERNAL_REF_NO']);
				$row['URL_QOT'] = $quote->getUrl();
			}catch(Exception $e){};
		}
		
		if( $row['ORD_INTERNAL_REF_NO'] != "" )
		{
			try
			{
				$order = Shipserv_Order::getInstanceById($row['ORD_INTERNAL_REF_NO']);
				$row['URL_ORD'] = $order->getUrl();
			}catch(Exception $e){};
		}
		return $row;
	}
	
	function getRfqSentToMatchSupplier()
	{
		$sql = "
			SELECT
				t.*
				, CASE WHEN t.total_qot_li != 0 AND  t.total_rfq_li != 0 THEN
					CASE WHEN
						ROUND(t.total_qot_li / t.total_rfq_li * 100) > 100
					THEN
						100
					ELSE
						ROUND(t.total_qot_li / t.total_rfq_li * 100)
					END
				ELSE
					0 
				END
				AS QOT_COMPLETENESS
				, (
					SELECT 
						ord_total_cost_usd
					FROM
						ord
					WHERE
						ord_internal_ref_no=t.ord_internal_ref_no
				) ORD_TOTAL_PRICE_IN_USD
			FROM
			(
				SELECT
					--r.*
					s.spb_branch_code tnid
					, s.spb_name
				    , s.spb_monetization_percent
					, get_dollar_value(q.qot_currency, q.qot_total_cost, q.qot_submitted_date) QOT_TOTAL_PRICE_IN_USD
					, q.qot_internal_ref_no
					, q.qot_submitted_date
					, ( SELECT COUNT(*) FROM match_b_imported_qot_on_match WHERE qot_sent_to_match=q.qot_internal_ref_no ) is_imported
					, rf.rfq_submitted_date
					, rf.rfq_ref_no
					, 'MATCH_SELECTED' RFQ_DESTINATION
					, rf.is_declined
					, rf.rfq_internal_ref_no
			        , (
			            SELECT
			              COUNT(*)
			            FROM
			              quote_line_item@livedb_link
			            WHERE
			              qli_qot_internal_ref_no=q.qot_internal_ref_no
			              AND qli_total_line_item_cost > 0
			              AND
			                ( qli_sts !='DEC' OR qli_sts IS null )
			          ) TOTAL_QOT_LI
			
			         , (
			            SELECT
			              COUNT(*)
			            FROM
			              rfq_line_item@livedb_link
			            WHERE
			              rfl_rfq_internal_ref_no=rf.rfq_internal_ref_no
			          ) TOTAL_RFQ_LI
				      , row_number() OVER (PARTITION BY s.spb_branch_code, s.spb_name, r.rfq_ref_no ORDER BY q.qot_internal_ref_no DESC NULLS LAST) as qot_row_num
					, (
              		 	SELECT
              		 		qot_delivery_lead_time
              		 	FROM
              		 		quote@livedb_link liveqout
              		 	WHERE liveqout.qot_internal_ref_no = q.qot_internal_ref_no and rownum = 1
              		 	) qot_delivery_lead_time
              		 , (
              		 	SELECT
              		 		qot_is_genuine_spare
              		 	FROM
              		 		quote@livedb_link liveqout
              		 	WHERE liveqout.qot_internal_ref_no = q.qot_internal_ref_no and rownum = 1
              		 	) qot_is_genuine_spare

					 , (
					 	-- successfully import
		              	SELECT 
							po.ord_internal_ref_no 
		              	FROM 
							match_b_order_by_match_spb po
							, match_b_imported_qot_on_match q_m
			            WHERE 
							po.qot_internal_ref_no=q_m.qot_internal_ref_no
							AND q_m.qot_sent_to_match=q.qot_internal_ref_no
		                	AND rownum=1 
			
						UNION 
				
						-- if import didn't happen, but buyer and supplier quoted back
		              	SELECT 
							po.ord_internal_ref_no 
		              	FROM 
							match_b_order_by_match_spb po
			            WHERE 
							po.qot_internal_ref_no=q.qot_internal_ref_no
							AND rownum=1 
				
						UNION 
				
		              	SELECT 
							po.ord_internal_ref_no 
		              	FROM 
							match_b_order_by_match_spb po
			            WHERE 
							po.rfq_sent_to_match=:rfqId
							AND po.spb_branch_code=s.spb_branch_code
							AND rownum=1 
				
					 ) ord_internal_ref_no
					 , (				
		              	SELECT 
							po.ord_submitted_date 
		              	FROM 
							match_b_order_by_match_spb po
							, match_b_imported_qot_on_match q_m
			            WHERE 
							po.qot_sent_to_match=q_m.qot_internal_ref_no
							AND q_m.qot_sent_to_match=q.qot_internal_ref_no
		                	AND rownum=1 
				
						UNION 
				
		              	SELECT 
							po.ord_submitted_date 
		              	FROM 
							match_b_order_by_match_spb po
			            WHERE 
							po.qot_internal_ref_no=q.qot_internal_ref_no
							AND rownum=1 
				
					) ord_submitted_date
					FROM
					supplier s,
					match_b_rfq_to_match r,
			        match_b_rfq_forwarded_by_match rf,
			        match_b_qot_match q,
			        rfq r2
				WHERE
					r2.rfq_event_hash = HEXTORAW(:hash)
					AND r2.rfq_internal_ref_no = r.rfq_internal_ref_no
			        AND r.rfq_internal_ref_no=rf.RFQ_SENT_TO_MATCH
					AND r.rfq_internal_ref_no=:rfqId
          			AND s.spb_branch_code=rf.spb_branch_code
					AND rf.rfq_internal_ref_no=q.rfq_internal_ref_no (+)
					AND r2.rfq_is_latest=1
			) t
        WHERE           
          qot_row_num=1
				
		";
		
		
		$params = array('rfqId'		=> $this->rfq->rfqInternalRefNo,
						'hash' 	    => $this->rfq->rfqEventHash);

		/* Added memcahing by Attila O  */
        $cacheKey = __CLASS__ . __METHOD__ . '_'. md5($sql.implode($params));
        $data = $this->fetchCachedQuery($sql, $params, $cacheKey, self::MEMCACHE_TTL, Shipserv_Oracle::SSREPORT2);

		/* $data = $this->reporting->fetchAll($sql, $params); */
		$new = array();
		foreach($data as $row)
		{
			// get links
			$new[] = $this->getLinkToAllDocumentsByRow($row);
		}
		
		return $new;
			
	}
	
	
	function getQuoteDetail()
	{
		$sql = "SELECT * FROM rfq WHERE spb_branch_code=999999 AND byb_branch_code=:buyerId";
		return $this->reporting->fetchAll($sql, array('buyerId'=> $this->buyerId));
	}
	
	public function getBuyerName()
	{
		$sql = "SELECT byb_name FROM buyer WHERE byb_branch_code=:tnid";
		return $this->reporting->fetchOne($sql, array('tnid' => $this->buyerId));
	}
	public function getSummary()
	{
		$data = array(
			'RFQ_INTERNAL_REF_NO' => $this->rfq->rfqInternalRefNo
			, 'BUYER_REF' => $this->rfq->rfqRefNo
			, 'SUBJECT' => $this->rfq->rfqSubject
			, 'DATE_SENT' => $this->rfq->rfqCreatedDate
			, 'POTENTIAL_SAVING' => $this->rfq->getPotentialSaving()
			, 'REALISED_SAVING' => $this->rfq->getActualSaving()
			, 'URL' => $this->rfq->getUrl()
			, 'URL_FOR_BUYER' => $this->rfq->getUrl('buyer')
			, 'CHEAPEST_BUYER_QOT_VALUE' => $this->getCheapesByyerSelectedQotValue($this->rfq->rfqInternalRefNo)
			, 'RFQ_VESSEL_NAME' => $this->rfq->rfqVesselName
		);
		 
		return $data;
	}

	protected function getCheapesByyerSelectedQotValue( $rfqInternalRefNo )
	{
		$sql = "
		WITH base AS (
			SELECT 
  				cheapest_qot_spb_by_byb_100
  		FROM
  			match_b_rfq_to_match
		WHERE 
			rfq_internal_ref_no = :rfqInternalRefNo
		)
		SELECT
			q.qot_total_cost_usd
		FROM
			qot q JOIN base b 
			ON q.qot_internal_ref_no = b.cheapest_qot_spb_by_byb_100
		WHERE rownum = 1";

		return $this->reporting->fetchOne($sql, array('rfqInternalRefNo' => $rfqInternalRefNo));

	}
	
}
