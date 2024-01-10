<?php
class Shipserv_Report_KpiTrend_KpiGmv extends Shipserv_Report
{

	public function getResult($startDate, $endDate, $tnid, $showChild = false)
	{
		if ($startDate != null) {
			if ($startDate > $endDate) {
				throw new Myshipserv_Exception_MessagedException("Start date cannot be later then end date", 500);
			}
		}

		if ($showChild) {
			$pagesUserSupplier = new Shipserv_Oracle_PagesUserSupplier();
			$childSuppliers = $pagesUserSupplier->getChildSupplierIds($tnid);
			array_push($childSuppliers, $tnid);
		} else {
			$childSuppliers = array($tnid);
		}


				if ($startDate == null) {
				$sql = "
				SELECT 
				  TO_CHAR(MIN(qot_submitted_date),'YYYYMMDD') start_date
				FROM
				  qot 
				WHERE
				  spb_branch_code IN (".$this->getTnidList($childSuppliers).")
				";
				
				$dParams = array();
				
				foreach ($childSuppliers as $key => $value) {
              		$dParams[":tnid".$key] = $value;
        		}

				$key = "Shipserv_Report_KpiTrend_StartDate" . md5($sql) . print_r($dParams, true);
				$res = $this->fetchCachedQuery ($sql, $dParams, $key, (60*60*2), 'ssreport2');
				$from = ($res[0]['START_DATE'] == null) ? date('Ymd') : $res[0]['START_DATE'];
				$to = date('Ymd');
			} else {
				$from = $startDate;
				$to = $endDate;
			}

		$sql = "
  			SELECT
			    EXTRACT(YEAR FROM ADD_MONTHS(TO_DATE(:startDate,'YYYYMMDD'),level-1)) doc_year
				, EXTRACT(MONTH FROM ADD_MONTHS(TO_DATE(:startDate,'YYYYMMDD'),level-1)) doc_month
			FROM
  				dual
				CONNECT BY TO_DATE(:endDate,'YYYYMMDD') >= ADD_MONTHS(TO_DATE(:startDate,'YYYYMMDD'),level-1) +0.999999
  		";

		$params =  array('startDate' => $from, 'endDate' => $from);

		$db = $this->getDbByType('ssreport2');
		$key = "Shipserv_Report_KpiTrend_KpiGmvNew" . md5($sql) . print_r($params, true);
		$res = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');

		$data = array();
		
			if ($startDate != null) {
				$yearFrom = (int)substr($startDate,0,4);
				$monthFrom = (int)substr($startDate,4,2);
				$yearTo = (int)substr($endDate,0,4);
				$monthTo = (int)substr($endDate,4,2);
			} else {
				$now = time();
				$yearTo = Date('Y',$now);
				$monthTo = Date('m', $now);
				if (count($res) >  0) {
					$yearFrom = (int)$res[0]['DOC_YEAR'];
					$monthFrom = (int)$res[0]['DOC_MONTH'];
				} else {
					$yearFrom = $yearTo;
					$monthFrom = $monthTo;
				}
			}

		
			$data[] = new Shipserv_Report_KpiTrend_KpiGmvContainer($yearFrom, $monthFrom);
			while (!($yearFrom == $yearTo && $monthFrom == $monthTo))
			{
				$monthFrom++;
				if ($monthFrom == 13) {
					$monthFrom = 1;
					$yearFrom++;
				}
				$data[] = new Shipserv_Report_KpiTrend_KpiGmvContainer($yearFrom, $monthFrom);
			} 

			//Correct GMV valus according to new GMV calc
			foreach ($data as $dataRec) {
				/*
				$dataRec->sir2GMV = $this->getPo($childSuppliers, $dataRec->getShortDate());
				$dataRec->sir2ORD = $this->getOrd($childSuppliers, $dataRec->getShortDate());
				*/
				
				$dataRec->sir2ORD = $this->getQot($childSuppliers, $dataRec->getShortDate());
				//$dataRec->sir2GMV = $this->getOrd($childSuppliers, $dataRec->getShortDate());
				//DE6901 Use SIR2 calculation, relacing the line above
				$dataRec->sir2GMV = $this->getPo($childSuppliers, $dataRec->getShortDate());
				$dataRec->sir2Conversion = ($dataRec->sir2ORD != 0) ? round(($dataRec->sir2GMV  / $dataRec->sir2ORD) * 100 , 2) : 0;
			}

		
		return $data;
	}

	protected function getDirectOrderQuery( $childSuppliers, $startDate, $endDate )
	{
		$sql = "
			SELECT 
			  EXTRACT(YEAR FROM ord_submitted_date) date_year
			, EXTRACT(MONTH FROM ord_submitted_date) date_month
			, count(1) poCountDirectNoRfq
			, nvl(sum(nvl2(resp_submitted_date, resp_total_cost_discounted_usd, ord_total_cost_discounted_usd)),0) poValueDirectNoRfq
			, 0 qotValueDirectNoRfq
       FROM
			ord_traded_gmv otg
       WHERE 
         spb_branch_code in (".$this->getTnidList($childSuppliers).")
         and ord_is_direct = 1";

		if ($startDate != null) {
			$sql .= " and ord_submitted_date BETWEEN to_date(:startDate , 'yyyymmdd')  and to_date(:endDate, 'yyyymmdd') + 0.99999";
		}

        $sql .= " and  not exists (
         	SELECT
         		null
         	FROM
         		linked_rfq_qot_po lrqp
			WHERE 
				spb_branch_code in (".$this->getTnidList($childSuppliers).") ";
				
				if ($startDate != null) {
					$sql .= " and rfq_submitted_date BETWEEN to_date(:startDate , 'yyyymmdd') and to_date(:endDate, 'yyyymmdd') + 0.99999";
				}

				$sql .= " and lrqp.ord_internal_ref_no = otg.ord_internal_ref_no
            )
		GROUP BY 
			EXTRACT(YEAR FROM ord_submitted_date), EXTRACT(MONTH FROM ord_submitted_date)
		ORDER BY  
			EXTRACT(YEAR FROM ord_submitted_date), EXTRACT(MONTH FROM ord_submitted_date)
		";
		return $sql;
	}

	protected function getCompetitiveOrdersQuery( $childSuppliers, $startDate, $endDate )
	{
		$sql = "
			SELECT 
				  EXTRACT(YEAR FROM rfq_submitted_date) date_year
				, EXTRACT(MONTH FROM rfq_submitted_date) date_month
				, nvl(sum(has_po),0) poCount
				, nvl(sum(po_price), 0) poValue
				, nvl(sum(qot_price), 0) qotValue
			FROM
				linked_rfq_qot_po lrqp
			WHERE
				spb_branch_code in (".$this->getTnidList($childSuppliers).")
				and is_competitive = 1";

				if ($startDate != null) {
					$sql .= " and rfq_submitted_date BETWEEN to_date(:startDate , 'yyyymmdd') and to_date(:endDate, 'yyyymmdd') + 0.99999";
				}

  $sql .= " GROUP BY 
				EXTRACT(YEAR FROM rfq_submitted_date), EXTRACT(MONTH FROM rfq_submitted_date)
			ORDER BY  
				EXTRACT(YEAR FROM rfq_submitted_date), EXTRACT(MONTH FROM rfq_submitted_date)
			";
		return $sql;
	}

	protected function getNonCompetitiveOrdersQuery( $childSuppliers, $startDate, $endDate)
	{
		$sql = "
			WITH direct_po AS 
			(
				SELECT
					ord_original ord_internal_Ref_no
				FROM
					ord_traded_gmv otg
				WHERE
					spb_branch_code in (".$this->getTnidList($childSuppliers).")
					and ord_is_direct = 1";

				if ($startDate != null) {
					$sql .= " and ord_submitted_date BETWEEN to_date(:startDate , 'yyyymmdd') and to_date(:endDate, 'yyyymmdd') + 0.99999";
				}

			$sql .= " and not exists (
						SELECT
							null
						FROM
							linked_rfq_qot_po lrqp
						WHERE 
							spb_branch_code in (".$this->getTnidList($childSuppliers).")";

							if ($startDate != null) {
								$sql .= " and rfq_submitted_date BETWEEN to_date(:startDate , 'yyyymmdd') and to_date(:endDate, 'yyyymmdd') + 0.99999";
							}
							$sql .= " and lrqp.ord_internal_ref_no = otg.ord_internal_ref_no
					)
			)	
			,
			single_rfq AS
			(
				SELECT 
					  rfq_submitted_date
					, ord_internal_ref_no
					, po_price
					, qot_price
				FROM
					linked_rfq_qot_po lrqp
				WHERE
					spb_branch_code in (".$this->getTnidList($childSuppliers).")";

				if ($startDate != null) {
					$sql .= " and rfq_submitted_date BETWEEN to_date(:startDate , 'yyyymmdd') and to_date(:endDate, 'yyyymmdd') + 0.99999";
				}

				$sql .= " AND EXISTS(
					SELECT
						NULL
					FROM
						linked_rfq_qot_po lrqp2
					WHERE
						lrqp2.rfq_event_hash=lrqp.rfq_event_hash
					GROUP BY
						rfq_event_hash
					HAVING COUNT(*)=1
				)
			)
			,
			non_competitive_order AS
			( 
				SELECT 
					  EXTRACT(YEAR FROM rfq_submitted_date) date_year
					, EXTRACT(MONTH FROM rfq_submitted_date) date_month
					, SUM(sr.po_price) sum_price
					, SUM(sr.po_price) sum_qot_price
				FROM 
					single_rfq sr
				WHERE
					NOT EXISTS (
						SELECT
							null
						FROM
							direct_po
						WHERE
							ord_internal_ref_no=sr.ord_internal_ref_no
    				)
			GROUP BY
				  EXTRACT(YEAR FROM rfq_submitted_date) 
				, EXTRACT(MONTH FROM rfq_submitted_date)
			)

		SELECT
			  date_year
			, date_month
			, sum_price
			, sum_qot_price
		FROM
			non_competitive_order
		";
		return $sql;
	}

	protected function getTnidList( $childSuppliers )
	{
		$sql = '';
		foreach ($childSuppliers as $key => $value) {
			$sql .= ($key == 0)  ? ":tnid".$key : ",:tnid".$key;
		}
		return $sql;
	}

	/**
	* Get  PO for a specific year and month, the result have to match SIR2
	*/
	public function getPo( $tnid, $truncDate )
	{
		$sql = "
		WITH get_po_total_value AS
			(

			SELECT
			  nvl(sum(total), 0) po_total_value
			FROM (
			SELECT
			  nvl(sum(ord_total_cost_discounted_usd),0) total
			FROM
			  billable_po_orig orig
			WHERE
			  ord_submitted_date between to_date(:yearAndMonth,'YYYYMM')  and add_months(to_date(:yearAndMonth,'YYYYMM'),1)-1/86400 
			  and spb_branch_code IN (".$this->getTnidList($tnid).")
			  and not exists (
			    SELECT
			      /*+UNNEST*/
			      null
			    FROM
			        billable_po_rep
			    WHERE
			      ord_submitted_date between to_date(:yearAndMonth,'YYYYMM')  and add_months(to_date(:yearAndMonth,'YYYYMM'),1)-1/86400 
			      and spb_branch_code IN (".$this->getTnidList($tnid).")
			      and ORD_ORIGINAL_NO = orig.ORD_INTERNAL_REF_NO
			    )
			UNION ALL
			SELECT
			  nvl(sum(ord_total_cost_discounted_usd),0)
			FROM
			  billable_po_rep rep
			WHERE
			  PRIMARY_ID in (
			    SELECT /*+HASH_SJ*/
			      max(PRIMARY_ID)
			    FROM
			      billable_po_rep
			    WHERE
			      ord_submitted_date between to_date(:yearAndMonth,'YYYYMM')  and add_months(to_date(:yearAndMonth,'YYYYMM'),1)-1/86400 
			      and spb_branch_code IN (".$this->getTnidList($tnid).")
			    GROUP BY
			      ord_original_no
			  )
			)
			) , get_previous_po_total_value AS
			(

			 SELECT
			  nvl(sum(total), 0) previous_po_total_value
			  FROM (
			    SELECT
			      nvl(sum(ord_total_cost_discounted_usd),0) total
			    FROM
			      billable_po_orig orig
			    WHERE
			      ord_submitted_date between add_months(to_date(:yearAndMonth , 'yyyymm'), -12) and to_date(:yearAndMonth , 'yyyymm') - 1/86400 
			      and spb_branch_code IN (".$this->getTnidList($tnid).")
			      and  exists (
			            SELECT
			               /*+HASH_SJ*/
			               null
			            FROM
			              billable_po_rep
			            WHERE
			              ord_submitted_date between to_date(:yearAndMonth,'YYYYMM')  and add_months(to_date(:yearAndMonth,'YYYYMM'),1)-1/86400 
			              and spb_branch_code IN (".$this->getTnidList($tnid).")
			              and ORD_ORIGINAL_NO = orig.ORD_INTERNAL_REF_NO
			                    ) and not exists (
			                      SELECT
			                         /*+UNNEST*/ null
			                      FROM
			                        billable_po_rep repPrev
			                     WHERE
			                        ord_submitted_date between add_months(to_date(:yearAndMonth , 'yyyymm'), -12) and to_date(:yearAndMonth , 'yyyymm') - 1/86400 
			                        and spb_branch_code IN (".$this->getTnidList($tnid).")
			                        and exists (
			                            SELECT
			                              /*+HASH_SJ*/ null
			                            FROM
			                              billable_po_rep repCur
			                            WHERE
			                              repCur.ord_submitted_date between to_date(:yearAndMonth,'YYYYMM')  and add_months(to_date(:yearAndMonth,'YYYYMM'),1)-1/86400 
			                              and repCur.spb_branch_code IN (".$this->getTnidList($tnid).")
			                              and repCur.ORD_ORIGINAL_NO = repPrev.ORD_ORIGINAL_NO
			                              ) and repPrev.ORD_ORIGINAL_NO = orig.ORD_INTERNAL_REF_NO
			                        )
				UNION ALL
				SELECT
				  nvl(sum(ord_total_cost_discounted_usd),0)
				FROM
				  billable_po_rep rep
				WHERE
				  PRIMARY_ID in (
				      SELECT
				        /*+HASH_SJ*/
				        max(PRIMARY_ID)
				      FROM
				        billable_po_rep repPrev
				      WHERE
				        ord_submitted_date between add_months(to_date(:yearAndMonth , 'yyyymm'), -12) and to_date(:yearAndMonth , 'yyyymm') - 1/86400 
				        and spb_branch_code IN (".$this->getTnidList($tnid).")
				        and exists (
				          SELECT
				            /*+HASH_SJ*/ null
				          FROM
				            billable_po_rep repCur
				          WHERE
				            repCur.ord_submitted_date between to_date(:yearAndMonth,'YYYYMM')  and add_months(to_date(:yearAndMonth,'YYYYMM'),1)-1/86400 
				            and repCur.spb_branch_code IN (".$this->getTnidList($tnid).")
				            and repCur.ORD_ORIGINAL_NO = repPrev.ORD_ORIGINAL_NO
				        )
				        GROUP BY
				          ord_original_no
				      )
				  )
				  )
				  
				  select 
				  (
				    (select po_total_value from get_po_total_value) - (select previous_po_total_value from get_previous_po_total_value)
				  ) po_total_value
				  from dual
				";

		$params = array(
				 'yearAndMonth' => $truncDate
			);
		foreach ($tnid as $key => $value) {
      		$params["tnid".$key] = $value;
	    }

		$key = "Shipserv_Report_KpiTrend_getPo" . md5($sql) . print_r($params, true);
		$rec = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');
		return (count($rec) > 0) ? $rec[0]['PO_TOTAL_VALUE'] : 0;

	}

	public function getOrd($tnid, $truncDate)
	{

		$sql = "
			SELECT
    			sum(o.ord_total_cost_discounted_usd) ord_total_value
			FROM
				ord o
				JOIN buyer b ON (b.byb_branch_code = o.byb_branch_code)
				JOIN supplier s ON (s.spb_branch_code = o.spb_branch_code)
			WHERE
				b.byb_is_test_account = 0
				AND s.spb_is_test_account = 0
				AND o.spb_branch_code IN (".$this->getTnidList($tnid).")
				AND o.ord_submitted_date BETWEEN TO_DATE(:yearAndMonth,'yyyymm') and ADD_MONTHS(TO_DATE(:yearAndMonth,'yyyymm'),1) - 1/86400 
				AND ord_is_latest = 1
		";

		$params = array(
			 'yearAndMonth' => $truncDate
		);

		foreach ($tnid as $key => $value) {
      		$params["tnid".$key] = $value;
	    }

		$key = "Shipserv_Report_KpiTrend_getOrd" . md5($sql) . print_r($params, true);
		$rec = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');

		return (count($rec) > 0) ? $rec[0]['ORD_TOTAL_VALUE'] : 0;

	}

	public function getQot($tnid, $truncDate)
	{

		$sql = "
			SELECT
    			sum(q.qot_total_cost_discounted_usd) qot_total_value
			FROM
				qot q
				JOIN buyer b ON (b.byb_branch_code = q.byb_branch_code)
				JOIN supplier s ON (s.spb_branch_code = q.spb_branch_code)
			WHERE
				b.byb_is_test_account = 0
				AND s.spb_is_test_account = 0
				AND q.spb_branch_code IN (".$this->getTnidList($tnid).")
				AND q.qot_submitted_date BETWEEN TO_DATE(:yearAndMonth,'yyyymm') and ADD_MONTHS(TO_DATE(:yearAndMonth,'yyyymm'),1) - 1/86400 
				AND qot_is_latest = 1
		";

		$params = array(
			 'yearAndMonth' => $truncDate
		);

		foreach ($tnid as $key => $value) {
      		$params["tnid".$key] = $value;
	    }

		$key = "Shipserv_Report_KpiTrend_getQot" . md5($sql) . print_r($params, true);
		$rec = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');

		return (count($rec) > 0) ? $rec[0]['QOT_TOTAL_VALUE'] : 0;

	}


}
