<?php
/**
 * Class responsible to pull response 
 */
class Shipserv_Report_Dashboard_Supplier_ResponseRate extends Shipserv_Report_Dashboard
{
	function __construct()
	{
		Shipserv_DateTime::monthsAgo(3, $this->startDate, $this->endDate);
	}
	
	public function setParams( $params = null)
	{
		if( $params !== null )
		{
			if( $params['fromDate'] != "" )
			{
				$tmp = explode("/", $params['fromDate']);
				$this->startDate = new DateTime();
				$this->startDate->setDate($tmp[2], (int)$tmp[1], (int)$tmp[0]);
	
			}
				
			if( $params['toDate'] != "" )
			{
				$tmp = explode("/", $params['toDate']);
				$this->endDate = new DateTime();
				$this->endDate->setDate($tmp[2],(int) $tmp[1], (int)$tmp[0]);
			}
				
			if( $params['id'] != "" )
			{
				$this->tnid = $params['id'];
			}
		}
	}
	
	public function getData()
	{
		$sql = "
			WITH suppliers AS ( 
			  SELECT 
				* 
			  FROM 
				supplier 
			  WHERE  
				  spb_is_test_account=0 
				  AND spb_is_inactive_account=0 
				  AND spb_is_deleted_account=0 
				  AND spb_interface!='STARTSUPPLIER'
			) 
			, 
			base_data AS ( 
				SELECT 
				  r.spb_branch_code
				  , s.spb_name
				  , r.rfq_internal_ref_no 
				  , r.rfq_event_hash 
				  , CASE WHEN rr.rfq_internal_ref_no IS NOT null THEN r.rfq_event_hash || rr.spb_branch_code ELSE null END is_declined 
				  , CASE WHEN r.rfq_linkable_qot=q.rfq_internal_ref_no THEN r.rfq_event_hash || r.spb_branch_code ELSE null END is_quoted 
				FROM 
				  rfq r  JOIN supplier s ON (r.spb_branch_code=s.spb_branch_code)
				    LEFT OUTER JOIN rfq_resp rr ON 
				      ( 
				        rr.rfq_internal_ref_no=r.rfq_internal_ref_no  
				        AND r.spb_branch_code=rr.spb_branch_code  
				        AND rr.rfq_resp_sts='DEC' 
				      ) 
				    LEFT OUTER JOIN qot q ON ( 
				      r.rfq_linkable_qot=q.rfq_internal_ref_no 
				      AND q.qot_total_cost_usd>0 
				    ) 
				WHERE 
				  EXISTS ( 
				    SELECT null FROM suppliers s WHERE s.spb_branch_code = r.spb_branch_code  
				  )
				  AND rfq_is_latest=1 
				  AND rfq_submitted_date BETWEEN TO_DATE(:startDate) AND TO_DATE(:endDate) 
			) 
			, 
			data_per_supplier AS ( 
			  SELECT  
			    spb_branch_code 
				, spb_name
			    , COUNT(DISTINCT rfq_event_hash) total_rfq_event
				, COUNT(DISTINCT rfq_internal_ref_no) total_rfq 
			    , COUNT(DISTINCT is_declined) total_declined 
			    , COUNT(DISTINCT is_quoted) total_quoted 
			    , COUNT(DISTINCT is_declined) + COUNT(DISTINCT is_quoted) total_response 
			  FROM  
			    base_data 
			  GROUP BY 
			    spb_branch_code, spb_name
			) 
			, 
			summary_data AS ( 
			  SELECT  
			    dps.* 
			    , dps.total_response/dps.total_rfq*100 response_rate 
			    , dps.total_quoted/dps.total_rfq*100 quote_rate 
			  FROM 
			    data_per_supplier dps 
			)
			
			SELECT 
				* 
			FROM
			(
				SELECT 
					s.* 
				FROM 
					summary_data s 
				WHERE 
					s.response_rate <= 100	
		        ORDER BY
					total_rfq_event DESC
			)
		";
		$db = $this->getDbByType('ssreport2');
		
		$params = array(
			'startDate' => $this->startDate->format('d-M-Y')
			, 'endDate' => $this->endDate->format('d-M-Y')
		);
		$key = "Shipserv_Report_Dashboard_Supplier_ResponseRate" . md5($sql) . print_r($this->params, true) . print_r($params, true);
		$result = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');
		
		return $result;
	}	
}
