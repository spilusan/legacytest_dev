<?php
class Shipserv_Report_KpiTrend_KpiVolume extends Shipserv_Report
{

	/**
	* Returns the result of KPI trend volumes
	* @param string  $startDate The beginning of date range
	* @param string  $endDate   The end of the date range
	* @param integer $tnid      The Tradenet ID
	* @param boolean $showChild Flag to aggregate the report with child TNID's 
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

		$sql = "
		SELECT 
			 EXTRACT(YEAR FROM RFQ_SUBMITTED_DATE) volume_year
			,EXTRACT(MONTH FROM RFQ_SUBMITTED_DATE) volume_month
			,count(*) as rfq_count
			,sum(CASE WHEN has_qot = 1 or has_po = 1 THEN 1 else 0 END) as qot_count
			,sum(has_po) as po_count
			,sum(has_poc) as poc_count
			,sum(CASE WHEN rfq_is_declined = 1 and has_qot=0 and has_po=0 and IS_COMPETITIVE=1 THEN 1 END) as declined_rfq_count
		FROM 
		(
			SELECT 
				 po.*
				,CASE WHEN po.ORD_INTERNAL_REF_NO is not NULL THEN (select count(*) from POC where ORD_INTERNAL_REF_NO = po.ORD_INTERNAL_REF_NO) ELSE 0 END has_poc
			FROM
				linked_rfq_qot_po po
			WHERE
				SPB_BRANCH_CODE IN (";
		foreach ($childSuppliers as $key => $value) {
			$sql .= ($key == 0)  ? ":tnid".$key : ",:tnid".$key;
        }
		$sql .= ") AND IS_COMPETITIVE = 1";
		if ($startDate != null) {
			$sql .= " and RFQ_SUBMITTED_DATE between to_date(:startDate, 'yyyymmdd') and to_date(:endDate, 'yyyymmdd') +0.9999999";
		}
			
		$sql .= ") 
			GROUP BY EXTRACT(YEAR FROM RFQ_SUBMITTED_DATE), EXTRACT(MONTH FROM RFQ_SUBMITTED_DATE)
			ORDER BY EXTRACT(YEAR FROM RFQ_SUBMITTED_DATE), EXTRACT(MONTH FROM RFQ_SUBMITTED_DATE)
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
				$yearFrom = (int)$res[0]['VOLUME_YEAR'];
				$monthFrom = (int)$res[0]['VOLUME_MONTH'];
			} else {
				$yearFrom = $yearTo;
				$monthFrom = $monthTo;
			}
		}
		
			
		$data[] = new Shipserv_Report_KpiTrend_KpiVolumeContainer($yearFrom, $monthFrom, $res);
		while (!($yearFrom == $yearTo && $monthFrom == $monthTo)) {
			$monthFrom++;
			if ($monthFrom == 13) {
				$monthFrom = 1;
				$yearFrom++;
			}
			$data[] = new Shipserv_Report_KpiTrend_KpiVolumeContainer($yearFrom, $monthFrom, $res);
		} 
		
		return $data;
	}

}
