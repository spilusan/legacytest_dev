<?php
/**
 * Internal Supplier KPI dashboard report, Added by Attila O 14/07/2015
 */
class Shipserv_Report_Dashboard_InternalSupplierKpi extends Shipserv_Report_Dashboard
{

    /**
    * Retunrs with the result of the KPI queries
    * @return array
    */
  	public function getResult()
  	{

  		$today = time();
     // $today = mktime(0, 0, 0, 1, 1, 2015);

      $thisDay = date('Ymd',$today);
      $dateFrom = date('Ym01',strtotime("-12 months",$today));
      $dateFromFilter = date('Ym01',strtotime("-24 months",$today));
      $dateTo = date('Ym01',$today);
      $dateTo = date('Ymd',strtotime("-1 second",strtotime($dateTo)));
      $reportData = $this->getGmv($dateFromFilter, $dateTo);
      $result = array();
      $startYear = (int)substr($dateFrom, 0,4);
      $startMonth = (int)substr($dateFrom, 4,2);
      $endYear = (int)substr($dateTo, 0,4);
      $endMonth = (int)substr($dateTo, 4,2);
      $trailing = array();

      while (!($startYear == $endYear && $startMonth == $endMonth))
      {
        array_push( $result, $this->getRecordByDate($reportData , $startYear, $startMonth));
        if ($startMonth == 12)
        {
          $startMonth = 1;
          $startYear++;
        } else {
          $startMonth++;
        }
      }

      array_push( $result, $this->getRecordByDate($reportData , $startYear, $startMonth));

      foreach ($result as  $kpiRec) {
        $trailingFrom = date('Y-m',strtotime("-13 months",strtotime($kpiRec->dat.'-01')));
        $trailingTo = date('Y-m',strtotime("-1 months",strtotime($kpiRec->dat.'-01')));
        $runRateFrom = date('Y-m',strtotime("-3 months",strtotime($kpiRec->dat.'-01')));

        $trailing[] = array(
          'dat' => $kpiRec->dat,
          'dispDat' => $kpiRec->dispDat,
          'totalGmv' => $this->sumField($reportData, $trailingFrom, $trailingTo, 'totalGmv'),
          'payingTotalGmv' => $this->sumField($reportData, $trailingFrom, $trailingTo, 'payingTotalGmv'),
          'runRate' => $this->sumField($reportData, $runRateFrom, $kpiRec->dat, 'totalGmv')*4,
          'avgMonetisation' => round($this->sumField($reportData, $runRateFrom, $kpiRec->dat, 'avgMonetisation') / 12,2),
          'avgPayingMonetisation' => round($this->sumField($reportData, $runRateFrom, $kpiRec->dat, 'avgPayingMonetisation') / 12,2),
        );
       }

      return array(
          'summary' => $summary,
          'gmv' => $result,
          'trailing' => $trailing,
          //'debug' => $reportData,
        );
      ;  		
  	}

    /**
    * Get the GMV, Runrate, Monetisation... data
    * @return array of Shipserv_Report_Dashboard_InternalSupplierKpiRec
    */
    protected function getGmv($dateFrom, $dateTo = null)
    {
      
      if ($dateTo === null) {
          $dateTo = $dateFrom;
      }

      $sql = "
        SELECT 
            total_gmv
          , total_revenue
          , paying_total_gmv
          , paying_total_revenue
          , dat
          , CASE WHEN total_gmv != 0 THEN total_revenue / total_gmv * 100 ELSE 0 END avg_monetisation
          , CASE WHEN paying_total_gmv != 0 THEN paying_total_revenue / paying_total_gmv * 100 ELSE 0 END avg_paying_monetisation
          , (select count(SPB_CREATED_DATE) spb_count from supplier_branch@livedb_link where SPB_CREATED_DATE <= to_date(dat, 'YYYY-MM')+ INTERVAL '1' MONTH AND spb_account_deleted = 'N' 
        AND spb_test_account = 'N' AND spb_branch_code <= 999999 AND spb_sts='ACT' AND directory_entry_status = 'PUBLISHED') spb_count
          , (select  sum(case
                            when s1.spb_mtml_supplier = 'Y' then 1
                            when s1.spb_smart_product_name = 'SmartSupplier' or s1.spb_smart_product_name = 'SmartSupplier Trial' then 1
                            else 0 end) spb_interface from supplier_branch@livedb_link s1 where SPB_CREATED_DATE <= to_date(dat, 'YYYY-MM')+ INTERVAL '1' MONTH AND spb_account_deleted = 'N' 
        AND spb_test_account = 'N' AND spb_branch_code <= 999999 AND spb_sts='ACT' AND directory_entry_status = 'PUBLISHED') spb_interface
        FROM 
        (
              SELECT
                SUM(tg.final_total_cost_usd) total_gmv
                , SUM(tg.final_total_vbp_usd) total_revenue
                , SUM(CASE WHEN spb_interface != 'STARTSUPPLIER' THEN tg.final_total_cost_usd  ELSE 0 END)  paying_total_gmv
                , SUM(CASE WHEN spb_interface != 'STARTSUPPLIER' THEN tg.final_total_vbp_usd  ELSE 0 END)  paying_total_revenue
                , to_char(tg.ord_orig_submitted_date, 'YYYY-MM') dat
              FROM
                ord_traded_gmv tg JOIN supplier sp ON (sp.spb_branch_code=tg.spb_branch_code)
              WHERE  tg.ord_orig_submitted_date BETWEEN TO_DATE(:startDate, 'yyyymmdd') AND TO_DATE(:endDate, 'yyyymmdd')+ 0.99999
              GROUP BY
                    to_char(tg.ord_orig_submitted_date, 'YYYY-MM')
              ORDER BY 
                    to_char(tg.ord_orig_submitted_date, 'YYYY-MM')
        )
      ";

      $params = array(
          'startDate' =>$dateFrom,
          'endDate' =>$dateTo
        );
      
      $key = __CLASS__ . md5($sql) . print_r($params, true);
      return $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'ssreport2');;

    }

    protected function getRecordByDate($data, $startYear, $startMonth)
    {
      foreach ($data as $record) {
        if ($record['DAT'] == sprintf('%d-%02d', $startYear, $startMonth)) {
          return new Shipserv_Report_Dashboard_InternalSupplierKpiRec($record);
        }
      }
      $result = new Shipserv_Report_Dashboard_InternalSupplierKpiRec();
      $result->dispDat = $result->getConvertedMonthName(sprintf('%d-%02d', $startYear, $startMonth));
      $result->dat = sprintf('%d-%02d', $startYear, $startMonth);
      return $result;
    }

    protected function sumField($reportData, $fromDate, $toDate, $fieldName){
      $result = 0;

      $startYear = (int)substr($fromDate, 0,4);
      $startMonth = (int)substr($fromDate, 5,2);
      $endYear = (int)substr($toDate, 0,4);
      $endMonth = (int)substr($toDate, 5,2);

      while (!($startYear == $endYear && $startMonth == $endMonth))
      {
        $rec =  $this->getRecordByDate($reportData , $startYear, $startMonth);
        $result +=  $rec->$fieldName;
        if ($startMonth == 12)
        {
          $startMonth = 1;
          $startYear++;
        } else {
          $startMonth++;
        }
      }
      $rec =  $this->getRecordByDate($reportData , $startYear, $startMonth);
      $result +=  $rec->$fieldName;

      return $result;
    }




}
