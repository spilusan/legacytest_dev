<?php
/**
 *
 */
class Shipserv_Match_Report_Usage extends Shipserv_Object
{
	public function setParams($params)
	{
		$this->params = $params;
	}
	
	private function getData($sql)
	{
		$db = Shipserv_Helper_Database::getSsreport2Db();
		
		$key = md5($sql);
		 
		$data = $this->fetchCachedQuery ($sql, null, $key, (60*60*2), 'ssreport2');
		
		foreach($data as $row)
		{
			$x[$row['BUYER_ID']][$row['WEEK']] = $row['TOTAL'];
		}
		return $x;
	}
	
	public function getStatisticOfTotalRfqSentToMatch()
	{
		$sql = "
			SELECT
				*
			FROM
			(
				SELECT
				  rfq.byb_branch_code || ' - ' || byb_name  buyer_id
				  , TO_CHAR(rfq_submitted_date, 'YYYYMON') Week
				  , COUNT(*) TOTAL
				FROM
				  match_b_rfq_to_match rfq JOIN buyer bb ON (rfq.byb_branch_code=bb.byb_branch_code)
				WHERE
				  spb_branch_code=999999
				  AND rfq_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
				GROUP BY
				  rfq.byb_branch_code || ' - ' || byb_name, TO_CHAR(rfq_submitted_date, 'YYYYMON')
			)
			WHERE
			  LOWER(buyer_id) NOT LIKE '%test%'
			  AND LOWER(buyer_id) NOT LIKE '%demo%'
			  AND LOWER(buyer_id) NOT LIKE '%10026%'
			  AND LOWER(buyer_id) NOT LIKE '%10414%'
			ORDER BY buyer_id ASC
    	";
		return $this->getData($sql);
	}
	
	public function getStatisticOfQuoteRate()
	{
		$sql = "
				SELECT
		
				  rfq_forwarded_stats.buyer_id buyer_id
				  , rfq_forwarded_stats.week week
				  , ROUND(quote_received.total/rfq_forwarded_stats.total*100,2) total
				FROM
				(
		
				  SELECT
				    *
				  FROM
				  (
				    SELECT
				      rfq.byb_branch_code || ' - ' || byb_name  buyer_id
				      , TO_CHAR(rfq_forwarded.rfq_submitted_date, 'YYYYMON') Week
				      , COUNT(*) TOTAL
				    FROM
				      match_b_rfq_to_match rfq JOIN buyer bb ON (rfq.byb_branch_code=bb.byb_branch_code)
				            , match_b_rfq_forwarded_by_match rfq_forwarded
				    WHERE
				      rfq.spb_branch_code=999999
				      AND rfq.rfq_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
				            AND rfq_forwarded.rfq_pom_source=rfq.rfq_internal_ref_no
				    GROUP BY
				      rfq.byb_branch_code || ' - ' || byb_name
				            , TO_CHAR(rfq_forwarded.rfq_submitted_date, 'YYYYMON')
				    )
				  WHERE
				    LOWER(buyer_id) NOT LIKE '%test%'
				    AND LOWER(buyer_id) NOT LIKE '%demo%'
				    AND LOWER(buyer_id) NOT LIKE '%10026%'
				    AND LOWER(buyer_id) NOT LIKE '%10414%'
				  ORDER BY buyer_id ASC
				) rfq_forwarded_stats
				,
				(
				  SELECT
				    *
				  FROM
				  (
		
				    SELECT
				      original_rfq.byb_branch_code || ' - ' || byb_name  buyer_id
				      , TO_CHAR(forwarded_rfq.rfq_submitted_date, 'YYYYMON') Week
				      , COUNT(*) TOTAL
		
				    FROM
				      match_b_rfq_to_match original_rfq JOIN buyer bb ON (original_rfq.byb_branch_code=bb.byb_branch_code)
				      , rfq forwarded_rfq
				      , match_b_qot_match qot
				    WHERE
				      original_rfq.spb_branch_code=999999
				      AND forwarded_rfq.rfq_pom_source=original_rfq.rfq_internal_ref_no
				      AND qot.rfq_internal_ref_no=forwarded_rfq.rfq_internal_ref_no
				      AND original_rfq.rfq_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
				    GROUP BY
				      original_rfq.byb_branch_code || ' - ' || byb_name, TO_CHAR(forwarded_rfq.rfq_submitted_date, 'YYYYMON')
				  )
				  WHERE
				    LOWER(buyer_id) NOT LIKE '%test%'
				    AND LOWER(buyer_id) NOT LIKE '%demo%'
				    AND LOWER(buyer_id) NOT LIKE '%10026%'
				    AND LOWER(buyer_id) NOT LIKE '%10414%'
		
				    ORDER BY buyer_id ASC
				) quote_received
				WHERE
				  rfq_forwarded_stats.buyer_id=quote_received.buyer_id
				  AND rfq_forwarded_stats.week=quote_received.week
			";		
		return $this->getData($sql);
	}
	
	public function getStatisticOfTotalForwardedRfq()
	{
		$sql = "
				SELECT
					*
				FROM
				(
		
					SELECT
					  rfq.byb_branch_code || ' - ' || byb_name  buyer_id
					  , TO_CHAR(rfq_forwarded.rfq_submitted_date, 'YYYYMON') Week
					  , COUNT(*) TOTAL
					FROM
					  match_b_rfq_to_match rfq JOIN buyer bb ON (rfq.byb_branch_code=bb.byb_branch_code)
		               JOIN match_b_rfq_forwarded_by_match rfq_forwarded ON (rfq.rfq_internal_ref_no=rfq_forwarded.rfq_sent_to_match)
					WHERE
					  rfq.spb_branch_code=999999
					  AND rfq.rfq_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
		              AND rfq_forwarded.rfq_pom_source=rfq.rfq_internal_ref_no
					GROUP BY
					  rfq.byb_branch_code || ' - ' || byb_name
            		  , TO_CHAR(rfq_forwarded.rfq_submitted_date, 'YYYYMON')
    			)
				WHERE
				  LOWER(buyer_id) NOT LIKE '%test%'
				  AND LOWER(buyer_id) NOT LIKE '%demo%'
				  AND LOWER(buyer_id) NOT LIKE '%10026%'
				  AND LOWER(buyer_id) NOT LIKE '%10414%'
				ORDER BY buyer_id ASC
	    	";
		return $this->getData($sql);
	}
	
	public function getStatisticOfTotalQuoteReceived()
	{
		$sql = "
			SELECT
				*
			FROM
			(
			  SELECT
			    original_rfq.byb_branch_code || ' - ' || byb_name  buyer_id
			    , TO_CHAR(original_rfq.rfq_submitted_date, 'YYYYMON') Week
			    , COUNT(*) TOTAL
			  FROM
			    match_b_rfq_to_match original_rfq JOIN buyer bb ON (original_rfq.byb_branch_code=bb.byb_branch_code)
			    	JOIN match_b_qot_match qot ON (original_rfq.rfq_internal_ref_no=qot.rfq_sent_to_match)
			  WHERE
			    original_rfq.rfq_submitted_date BETWEEN TO_DATE('01-JAN-" . $this->params['yyyy'] . "') AND TO_DATE('01-JAN-" . $this->params['yyyy'] . "') + 365
			  GROUP BY
			    original_rfq.byb_branch_code || ' - ' || byb_name, TO_CHAR(original_rfq.rfq_submitted_date, 'YYYYMON')
			)
			WHERE
			  LOWER(buyer_id) NOT LIKE '%test%'
			  AND LOWER(buyer_id) NOT LIKE '%demo%'
			  AND LOWER(buyer_id) NOT LIKE '%10026%'
			  AND LOWER(buyer_id) NOT LIKE '%10414%'
	
    		ORDER BY buyer_id ASC
    	";
		return $this->getData($sql);
	}
	
	public function getStatisticAverageOfQuoteRate()
	{
		// getting the stats of the forwarded RFQ
		$forwarded = $this->getStatisticOfTotalForwardedRfq();
		
		// getting stats of quote received
		$quoted = $this->getStatisticOfTotalQuoteReceived();
		
		// processing it
		foreach ( (array) $forwarded as $buyerId => $d)
		{
			foreach( $d as $week => $total )
			{
				$x['forwarded'][$week] += $total;
			}
		}
		foreach ( (array) $quoted as $buyerId => $d)
		{
			foreach( $d as $week => $total )
			{
				$x['quoted'][$week] += $total;
			}
		}
		$output = array();
		foreach( (array) $x['forwarded'] as $week => $total)
		{
			if( $total == null || $x['quoted'][$week] == null )
			{
				$output[$week] = null;
			}
			else
			{
				$output[$week] =
				($total==0 || $x['quoted'][$week] == 0 )
				? 0: round($x['quoted'][$week]/$total * 100);
				
			}
		}
		
		return $output;
	}
}
