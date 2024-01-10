<?php
class Shipserv_Report_KpiTrend_KpiQot extends Shipserv_Report
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

		//TOD use this linked_rfq_qot_po (Deprecated, this calculation is replaced by a different one)
		/* 
		$sql = "
			SELECT
				  EXTRACT(YEAR FROM q.qot_submitted_date) doc_year
				, EXTRACT(MONTH FROM q.qot_submitted_date) doc_month
				, sum(lrqp.qot_price) qot_total_cost_usd
				, sum(lrqp.po_price) total_cost_ordered_usd
			FROM 
				linked_rfq_qot_po lrqp JOIN qot q ON lrqp.qot_internal_ref_no = q.qot_internal_ref_no
			WHERE 
				lrqp.qot_internal_ref_no is not null
				and lrqp.is_competitive = 1 
  				and lrqp.spb_branch_code in ("; 
  				foreach ($childSuppliers as $key => $value) {
	       				$sql .= ($key == 0)  ? ":tnid".$key : ",:tnid".$key;
	           		}	
  			$sql .=")";
			if ($startDate != null) {
				$sql .= " and q.qot_submitted_date BETWEEN to_date(:startDate, 'yyyymmdd') AND to_date(:endDate, 'yyyymmdd') +0.999999";
			}
		$sql .= " GROUP BY
				  EXTRACT(YEAR FROM q.qot_submitted_date)
				, EXTRACT(MONTH FROM q.qot_submitted_date)
  		";
  		*/
  		$sql = "
  			SELECT
			    EXTRACT(YEAR FROM ADD_MONTHS(TO_DATE(:startDate,'YYYYMMDD'),level-1)) doc_year
				, EXTRACT(MONTH FROM ADD_MONTHS(TO_DATE(:startDate,'YYYYMMDD'),level-1)) doc_month
			FROM
  				dual
				CONNECT BY TO_DATE(:endDate,'YYYYMMDD') >= ADD_MONTHS(TO_DATE(:startDate,'YYYYMMDD'),level-1) +0.999999
  		";
		//$params = ($startDate != null) ? array('startDate' => $from, 'endDate' => $from) : array();
		$params =  array('startDate' => $from, 'endDate' => $from);

		/*
		//We do not need to populate the TNID's as the calculation was replaced by funcion , and the quiery provides only a list of dates
		foreach ($childSuppliers as $key => $value) {
              		$params[":tnid".$key] = $value;
        }
        */
        
        /*
        echo $sql;  print_r($params);  die();
        */

		$db = $this->getDbByType('ssreport2');
		$key = "Shipserv_Report_KpiTrend_KpiVolume" . md5($sql) . print_r($params, true);
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
		
			
			$data[] = new Shipserv_Report_KpiTrend_KpiQotContainer($yearFrom, $monthFrom, $res);
			while (!($yearFrom == $yearTo && $monthFrom == $monthTo))
			{
				$monthFrom++;
				if ($monthFrom == 13) {
					$monthFrom = 1;
					$yearFrom++;
				}
				$data[] = new Shipserv_Report_KpiTrend_KpiQotContainer($yearFrom, $monthFrom, $res);
			} 

			foreach ($data as $dataRec) {
				$qotData = $this->getQot($childSuppliers, $dataRec->getShortDate());
				$dataRec->sir2Po = $qotData['ORD_TOTAL_VALUE']; 
				$dataRec->sir2QOT = $qotData['QOT_TOTAL_VALUE'];
				$dataRec->Conversion = ($dataRec->sir2QOT != 0) ? round(($dataRec->sir2Po  / $dataRec->sir2QOT) * 100 , 2) : 0;
			}

		return $data;

	}


	public function getQot($tnid, $truncDate)
	{

		$sql = "
			SELECT
    			 sum(qot_total_value) qot_total_value
				,sum(ord_total_value) ord_total_value
  			FROM
		    (
		      SELECT
				sum(q.qot_total_cost_discounted_usd) qot_total_value
		        ,(
		          SELECT
		            sum(ord_total_cost_discounted_usd) from ord o
		           WHERE
		            o.qot_internal_ref_no = q.qot_internal_ref_no
		            AND o.ord_is_latest = 1
		            and o.ord_original is null
					and o.ord_submitted_date > q.qot_submitted_date
                	and o.byb_branch_code = q.byb_branch_code
		          ) ord_total_value
				FROM
					qot q
	        		JOIN buyer b ON (b.byb_branch_code = q.byb_branch_code)
					JOIN supplier s ON (s.spb_branch_code = q.spb_branch_code)
				WHERE
					qot_is_latest = 1
					AND b.byb_is_test_account = 0
					AND s.spb_is_test_account = 0
	        
					AND q.spb_branch_code IN (".$this->getTnidList($tnid).")
					AND q.qot_submitted_date BETWEEN TO_DATE(:yearAndMonth,'yyyymm') and ADD_MONTHS(TO_DATE(:yearAndMonth,'yyyymm'),1) - 1/86400 
					AND q.qot_original is null
			    GROUP BY 
			    	qot_internal_ref_no
			    	, qot_submitted_date
			    	, q.byb_branch_code
		      )
		";

		$params = array(
			'yearAndMonth' => $truncDate
		);

		foreach ($tnid as $key => $value) {
      		$params["tnid".$key] = $value;
	    }

		$key = "Shipserv_Report_KpiTrend_getQot" . md5($sql) . print_r($params, true);
		$rec = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');

		return (count($rec) > 0) ? $rec[0] : array('QOT_TOTAL_VALUE' => 0,'ORD_TOTAL_VALUE' => 0);

	}

	protected function getTnidList( $childSuppliers )
	{
		$sql = '';
		foreach ($childSuppliers as $key => $value) {
			$sql .= ($key == 0)  ? ":tnid".$key : ",:tnid".$key;
		}
		return $sql;
	}

}