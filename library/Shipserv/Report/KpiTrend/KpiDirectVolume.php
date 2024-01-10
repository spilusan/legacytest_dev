<?php
class Shipserv_Report_KpiTrend_KpiDirectVolume extends Shipserv_Report
{

	/**
	* Return the result of direct volues
	* @param string  $startDate Start date range
	* @param string  $endDate   End date range
	* @param integer $tnid      Tradenet ID
	* @param boolean $showChild Show child branches
	* @return array
	*/
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

		$tnidList = "";
		foreach ($childSuppliers as $key => $value) {
			$tnidList .= ($key == 0)  ? ":tnid".$key : ",:tnid".$key;
		}

		$sql = "
			WITH rfqtable AS 
			(
				SELECT 
					  count(*) doc_count
					, EXTRACT(YEAR FROM r.rfq_submitted_date) doc_year
					, EXTRACT(MONTH FROM r.rfq_submitted_date) doc_month
				FROM
					  rfq r
					, buyer b
					, supplier s
				WHERE
					r.byb_branch_code = b.byb_branch_code
					and r.spb_branch_code = s.spb_branch_code
					and b.byb_is_test_account = 0
					-- and b.byb_is_inactive_account = 0
					and s.spb_branch_code in (".$tnidList.")\n";
		if ($startDate != null) {
			$sql .= "and r.rfq_submitted_date between to_date(:startDate,'yyyymmdd') and to_date(:endDate,'yyyymmdd')+.99999\n";
		}
		$sql .= "and r.rfq_count = 1
				 and r.rfq_pages_rfq_id is null
				GROUP BY
					  EXTRACT(YEAR FROM r.rfq_submitted_date)
					, EXTRACT(MONTH FROM r.rfq_submitted_date)
			)
			,pagesRfqTable AS
			(
				SELECT 
					  COUNT(*) doc_count
					, EXTRACT(YEAR FROM pin_sent_date) doc_year
					, EXTRACT(MONTH FROM pin_sent_date) doc_month
				FROM
					pages_inquiry_stats
				WHERE
					pin_spb_branch_code in(".$tnidList.")\n";
		if ($startDate != null) {
			$sql .= "and pin_sent_date between to_date(:startDate,'yyyymmdd') and to_date(:endDate,'yyyymmdd')+.99999\n";
		}
		$sql .= "GROUP BY
					  EXTRACT(YEAR FROM pin_sent_date)
					, EXTRACT(MONTH FROM pin_sent_date)
			)
			,ordtable as
			(
				SELECT 
					  count(*) doc_count
					, EXTRACT(YEAR FROM o.ord_submitted_date) doc_year
					, EXTRACT(MONTH FROM o.ord_submitted_date) doc_month
				FROM
					  ord o
					, buyer b
					, supplier s
				WHERE
					o.byb_branch_code = b.byb_branch_code
					and o.spb_branch_code = s.spb_branch_code
					and b.byb_is_test_account = 0
					-- and b.byb_is_inactive_account = 0
					and s.spb_branch_code in (".$tnidList.")\n";
		if ($startDate != null) {
			$sql .= "and o.ord_submitted_date between to_date(:startDate,'yyyymmdd') and to_date(:endDate,'yyyymmdd')+.99999\n";
		}
		$sql .= "and o.ord_count = 1
				GROUP BY
					  EXTRACT(YEAR FROM o.ord_submitted_date) 
					, EXTRACT(MONTH FROM o.ord_submitted_date)
			)
			, qottable as
			(
				SELECT 
					  count(*) doc_count
					, EXTRACT(YEAR FROM q.qot_submitted_date) doc_year
					, EXTRACT(MONTH FROM q.qot_submitted_date) doc_month
				FROM
					  qot q
					, buyer b
					, supplier s
				WHERE
					q.byb_branch_code = b.byb_branch_code
					and q.spb_branch_code = s.spb_branch_code
					and b.byb_is_test_account = 0
					-- and b.byb_is_inactive_account = 0
					and s.spb_branch_code in (".$tnidList.")\n";
		if ($startDate != null) {
			$sql .= "and q.qot_submitted_date between to_date(:startDate,'yyyymmdd') and to_date(:endDate,'yyyymmdd')+.99999\n";
		}
		$sql .= "and q.qot_count = 1
				GROUP BY
					  EXTRACT(YEAR FROM q.qot_submitted_date)
					, EXTRACT(MONTH FROM q.qot_submitted_date)
			)
			, poctable as
			(
				SELECT 
					  count(*) doc_count
					, EXTRACT(YEAR FROM p.poc_submitted_date) doc_year
					, EXTRACT(MONTH FROM p.poc_submitted_date) doc_month 
				FROM
					poc p
					, buyer b
					, supplier s
				WHERE
					p.byb_branch_code = b.byb_branch_code
					and p.spb_branch_code = s.spb_branch_code
					and b.byb_is_test_account = 0
					-- and b.byb_is_inactive_account = 0
					and s.spb_branch_code in (".$tnidList.")\n";
		if ($startDate != null) {
			$sql .= "and p.poc_submitted_date between to_date(:startDate,'yyyymmdd') and to_date(:endDate,'yyyymmdd')+.99999\n";
		}
		$sql .= "and p.poc_count = 1
				GROUP BY 
					  EXTRACT(YEAR FROM p.poc_submitted_date) 
					, EXTRACT(MONTH FROM p.poc_submitted_date) 
			)
			,summarytable AS
			(
				SELECT
					'RFQ' doc_type
					, doc_year
					, doc_month
					, doc_count
				FROM
					rfqtable
			union
				SELECT
					'PRFQ' doc_type
					, doc_year
					, doc_month
					, doc_count
				FROM
					pagesRfqTable
			union
				SELECT
					'ORD' doc_type
					, doc_year
					, doc_month
					, doc_count
				FROM
					ordtable
			union
				SELECT
					'QOT' doc_type
					, doc_year
					, doc_month
					, doc_count
				FROM
					qottable
			union
				SELECT
					'POC' doc_type
					, doc_year
					, doc_month
					, doc_count
				FROM
					poctable
			)

			SELECT
				  doc_type
				, doc_year
				, doc_month
				, doc_count
			FROM
				summarytable
			ORDER BY
				doc_year,doc_month,doc_type
		";

		$params = ($startDate != null) ? array('startDate' => $startDate, 'endDate' => $endDate) : array();
		
		foreach ($childSuppliers as $key => $value) {
			$params[":tnid".$key] = $value;
		}

		$key = "Shipserv_Report_KpiTrend_KpiVolume" . md5($sql) . print_r($params, true);
		$res = $this->fetchCachedQuery($sql, $params, $key, (60*60*2), 'ssreport2');

		$data = array();
		
		if ($startDate != null) {
			$yearFrom = (int)substr($startDate, 0, 4);
			$monthFrom = (int)substr($startDate, 4, 2);
			$yearTo = (int)substr($endDate, 0, 4);
			$monthTo = (int)substr($endDate, 4, 2);
		} else {
			$now = time();
			$yearTo = Date('Y', $now);
			$monthTo = Date('m', $now);
			if (count($res) >  0) {
				$yearFrom = (int)$res[0]['DOC_YEAR'];
				$monthFrom = (int)$res[0]['DOC_MONTH'];
			} else {
				$yearFrom = $yearTo;
				$monthFrom = $monthTo;
			}
		}
		
		$data[] = new Shipserv_Report_KpiTrend_KpiDirectVolumeContainer($yearFrom, $monthFrom, $res);
		while (!($yearFrom == $yearTo && $monthFrom == $monthTo)) {
			$monthFrom++;
			if ($monthFrom == 13) {
				$monthFrom = 1;
				$yearFrom++;
			}
			$data[] = new Shipserv_Report_KpiTrend_KpiDirectVolumeContainer($yearFrom, $monthFrom, $res);
		} 

		return $data;

	}

}