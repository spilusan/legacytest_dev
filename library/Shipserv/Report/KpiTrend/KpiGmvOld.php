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

		$directOrderSql = $this->getDirectOrderQuery($childSuppliers, $startDate, $endDate); //Here is no Quote, Quote value is 0
		$competitiveOrderSql = $this->getCompetitiveOrdersQuery($childSuppliers, $startDate, $endDate);
		$nonCompetitiveOrderSql = $this->getNonCompetitiveOrdersQuery($childSuppliers, $startDate, $endDate);


		$params = ($startDate != null) ? array('startDate' => $startDate, 'endDate' => $endDate) : array();

		foreach ($childSuppliers as $key => $value) {
              		$params[":tnid".$key] = $value;
              }

		$db = $this->getDbByType('ssreport2');
		$key1 = "Shipserv_Report_KpiTrend_KpiGmv1" . md5($directOrders) . print_r($params, true);
		$key2 = "Shipserv_Report_KpiTrend_KpiGmv2" . md5($competitiveOrders) . print_r($params, true);
		$key3 = "Shipserv_Report_KpiTrend_KpiGmv3" . md5($nonCompetitiveOrders) . print_r($params, true);

		$directOrders = $this->fetchCachedQuery ($directOrderSql, $params, $key1, (60*60*2), 'ssreport2');
		$competitiveOrders = $this->fetchCachedQuery ($competitiveOrderSql, $params, $key2, (60*60*2), 'ssreport2');
		$nonCompetitiveOrders = $this->fetchCachedQuery ($nonCompetitiveOrderSql, $params, $key3, (60*60*2), 'ssreport2');

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
				$yearFrom = $yearTo;
				$monthFrom = $monthTo;

				if (count($directOrders) >  0) {
					$yearFrom = (int)$directOrders[0]['DATE_YEAR'];
					$monthFrom = (int)$directOrders[0]['DATE_MONTH'];
				} 
				
				if (count($competitiveOrders) >  0) {
					$tempYearFrom = (int)$competitiveOrders[0]['DATE_YEAR'];
					$tempMonthFrom = (int)$competitiveOrders[0]['DATE_MONTH'];
					if ((int)$tempYearFrom*12+(int)$tempMonthFrom < (int)$yearFrom*12+(int)$monthFrom) {
						$yearFrom = $tempYearFrom;
						$monthFrom = $tempMonthFrom;
					}
				} 
		
				if (count($nonCompetitiveOrders) >  0) {
					$tempYearFrom = (int)$nonCompetitiveOrders[0]['DATE_YEAR'];
					$tempMonthFrom = (int)$nonCompetitiveOrders[0]['DATE_MONTH'];
					if ((int)$tempYearFrom*12+(int)$tempMonthFrom < (int)$yearFrom*12+(int)$monthFrom) {
						$yearFrom = $tempYearFrom;
						$monthFrom = $tempMonthFrom;
					}
				} 
			}
		
			$data[] = new Shipserv_Report_KpiTrend_KpiGmvContainer($yearFrom, $monthFrom, $directOrders, $competitiveOrders, $nonCompetitiveOrders);
			while (!($yearFrom == $yearTo && $monthFrom == $monthTo))
			{
				$monthFrom++;
				if ($monthFrom == 13) {
					$monthFrom = 1;
					$yearFrom++;
				}
				$data[] = new Shipserv_Report_KpiTrend_KpiGmvContainer($yearFrom, $monthFrom, $directOrders, $competitiveOrders, $nonCompetitiveOrders);
			} 

			//Correct GMV valus according to new GMV calc
			foreach ($data as $dataRec) {
				$dataRec->sir2GMV = $this->getPo($childSuppliers, $dataRec->getShortDate());
				$dataRec->sir2ORD = $this->getOrd($childSuppliers, $dataRec->getShortDate());
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
    			sum(o.ord_total_cost_usd) ord_total_value
			FROM
				ord o
				JOIN buyer b ON (b.byb_branch_code = o.byb_branch_code)
				JOIN supplier s ON (s.spb_branch_code = o.spb_branch_code)
			WHERE
				b.byb_is_test_account = 0
				AND s.spb_is_test_account = 0
				AND o.spb_branch_code IN (".$this->getTnidList($tnid).")
				AND o.ord_submitted_date BETWEEN TO_DATE(:yearAndMonth,'yyyymm') and ADD_MONTHS(TO_DATE(:yearAndMonth,'yyyymm'),1) - 1/86400 
				AND ord_original is null
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



}
