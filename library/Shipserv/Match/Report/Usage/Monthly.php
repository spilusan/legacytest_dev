<?php
class Shipserv_Match_Report_Usage_Monthly extends Shipserv_Object
{
	public function __construct()
	{
		$this->startDate = new DateTime();
		$this->startDate->setDate(date('Y')-1, date('n'), date('j'));
	
		$this->endDate = new DateTime();
		$this->endDate->setDate(date('Y'), date('n'), date('j'));
		
		
		Shipserv_DateTime::monthsAgo(3, $startDate, $endDate);
		
		$this->startDate = $startDate;
		$this->endDate = $endDate;
		
	}
	
	public function setBybBranchCode($code)
	{
		$this->bybBranchCode = $code;
	}
	
	public function setStartDate($d, $m, $y) 
    {
    	$dt = new DateTime;
    	$dt->setDate((int)$y, (int)$m, (int)$d);
        $this->startDate = $dt;
    }
    
    public function setEndDate($d, $m, $y)
    {
    	$dt = new DateTime;
    	$dt->setDate((int)$y, (int)$m, (int)$d);
    	$this->endDate = $dt;
    }
    
    public function setAsMonthly()
    {
    	$this->timePeriodDivisor = 'YYYYMM';	
    	$this->timePeriodDivisorReadable = 'MON-YYYY';
    }
    
    public function setAsWeekly()
    {
    	$this->timePeriodDivisor = 'YYYYWW';
    }
    
	private function getData($sql)
	{
		foreach($data as $row)
		{
			$x[$row['TIME_PERIOD']] = $row['TOTAL'];
		}

		return $x;
	}
	
	public function setVesselName($vesselName)
	{
		$this->vesselName = $vesselName;
	}
	
	public function getStatistic()
	{
		$data = [];
		$db = Shipserv_Helper_Database::getSsreport2Db();

		$sql = "
			SELECT
				t1.TIME_PERIOD
				, t1.TIME_PERIOD_READABLE
				, t1.TOTAL_SENT_TO_MATCH
				, t2.TOTAL_FORWARDED_BY_MATCH
				, t3.TOTAL_QUOTE
				, CASE 
					WHEN t3.TOTAL_QUOTE = 0 OR t2.TOTAL_FORWARDED_BY_MATCH = 0 THEN
						0
					ELSE
						ROUND(t3.TOTAL_QUOTE/t2.TOTAL_FORWARDED_BY_MATCH*100) 
				  END QUOTE_RATE
				
			FROM
			(
				SELECT
				  TO_NUMBER(TO_CHAR(rr.rfq_submitted_date, '" . $this->timePeriodDivisor . "')) TIME_PERIOD
				  , TO_CHAR(rr.rfq_submitted_date, '" . $this->timePeriodDivisorReadable . "') TIME_PERIOD_READABLE
				  -- , COUNT(*) TOTAL_SENT_TO_MATCH -- commented by Yuriy Akopov on 2016-01-18, DE6317
				  , COUNT(DISTINCT rfq.rfq_event_hash) TOTAL_SENT_TO_MATCH
				FROM
				  match_b_rfq_to_match rr JOIN rfq ON (rr.rfq_internal_ref_no=rfq.rfq_internal_ref_no)
				WHERE
				  rr.rfq_submitted_date BETWEEN TO_DATE('" . $this->startDate->format("d-M-Y") . "') AND TO_DATE('" . $this->endDate->format("d-M-Y") . "') + 0.99999
				  AND rr.byb_branch_code=" . $db->quote($this->bybBranchCode) . "
				  " . ( ($this->vesselName != "")?"AND rfq.rfq_vessel_name=" . $db->quote($this->vesselName) :"" ) . "
				  		
				GROUP BY
				  TO_CHAR(rr.rfq_submitted_date, '" . $this->timePeriodDivisor . "')
				  , TO_CHAR(rr.rfq_submitted_date, '" . $this->timePeriodDivisorReadable . "')
			) t1
			, (
				SELECT
				  TO_NUMBER(TO_CHAR(rr.rfq_submitted_date, '" . $this->timePeriodDivisor . "')) TIME_PERIOD
				  , TO_CHAR(rr.rfq_submitted_date, '" . $this->timePeriodDivisorReadable . "') TIME_PERIOD_READABLE
				  , COUNT(DISTINCT rr.rfq_internal_ref_no) TOTAL_FORWARDED_BY_MATCH
				FROM
				  match_b_rfq_forwarded_by_match rr JOIN rfq ON (rr.rfq_internal_ref_no=rfq.rfq_internal_ref_no)
				WHERE
				  rr.rfq_submitted_date BETWEEN TO_DATE('" . $this->startDate->format("d-M-Y") . "') AND TO_DATE('" . $this->endDate->format("d-M-Y") . "') + 0.99999
				  AND rr.orig_byb_branch_code=" . $db->quote($this->bybBranchCode) . "
				  " . ( ($this->vesselName != "")?"AND rfq.rfq_vessel_name=" . $db->quote($this->vesselName) : "" ) . "
				GROUP BY
				  TO_CHAR(rr.rfq_submitted_date, '" . $this->timePeriodDivisor . "')
				  , TO_CHAR(rr.rfq_submitted_date, '" . $this->timePeriodDivisorReadable . "')
			) t2
			, (
				SELECT
				  TO_NUMBER(TO_CHAR(rr.rfq_submitted_date, '" . $this->timePeriodDivisor . "')) TIME_PERIOD
				  , TO_CHAR(rr.rfq_submitted_date, '" . $this->timePeriodDivisorReadable . "') TIME_PERIOD_READABLE
				  , COUNT(DISTINCT qq.qot_internal_ref_no) TOTAL_QUOTE
				FROM
					match_b_qot_match qq JOIN match_b_rfq_to_match rr 
						ON ( rr.rfq_internal_ref_no=qq.rfq_sent_to_match)
							JOIN contact c ON ( c.cntc_doc_type='RFQ' AND rr.rfq_internal_ref_no=c.cntc_doc_internal_ref_no AND c.cntc_branch_qualifier='BY')
								JOIN rfq ON (rr.rfq_internal_ref_no=rfq.rfq_internal_ref_no)
				WHERE
				  rr.rfq_submitted_date BETWEEN TO_DATE('" . $this->startDate->format("d-M-Y") . "') AND TO_DATE('" . $this->endDate->format("d-M-Y") . "') + 0.99999
				  AND qq.orig_byb_branch_code=" . $db->quote($this->bybBranchCode) . "
				  AND qq.qot_total_cost_usd>0
				  " . ( ($this->vesselName != "")?"AND rfq.rfq_vessel_name=" . $db->quote($this->vesselName) : "" ) . "
				GROUP BY
				  TO_CHAR(rr.rfq_submitted_date, '" . $this->timePeriodDivisor . "')
				  , TO_CHAR(rr.rfq_submitted_date, '" . $this->timePeriodDivisorReadable . "')
			) t3
			WHERE
				t1.time_period=t2.time_period (+)
        		AND t1.time_period=t3.time_period (+)
			ORDER BY
				TIME_PERIOD ASC
    	";

		// print '<pre>' . $sql . '</pre>'; die;

		foreach((array)$db->fetchAll($sql) as $row)
		{
			$data[] = array(
				$row['TIME_PERIOD_READABLE']
				, (int)$row['TOTAL_SENT_TO_MATCH']
				, (int)$row['TOTAL_FORWARDED_BY_MATCH']
				, (int)$row['TOTAL_QUOTE']
				//, (int)$row['QUOTE_RATE']
			);
		}
		
		return $data;
	}
}
