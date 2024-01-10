<?php
/**
 * Suppliser statistics 
 */
class Shipserv_Oracle_Sir_SupplierStatistics extends Shipserv_Oracle
{
    /**
     * Supplier Statistics 
     * 
     * @param integer $tnid
     * @param string  $startDate
     * @param string  $endDate
     * 
     * @return array
     */
    public function statisticsByTnid($tnid, $startDate, $endDate)
    {
        $sql = "
            SELECT
                pss.pss_spb_branch_code as spb_branch_code,
                pss.pss_view_date as view_date,
                pss.pss_source as source,
                pss.pss_browser as browseer,
                pss.pss_url_of_referrer as referrer,
                (CASE WHEN pss.pss_contact_email_viewed = 1 THEN 'Yes' ELSE 'No' END) as email_viewed,
                (CASE WHEN pss.pss_contact_viewed = 1 THEN 'Yes' ELSE 'No' END) as details_viewed,
                (CASE WHEN pss.pss_tnid_viewed = 1 THEN 'Yes' ELSE 'No' END) as tnid_viewed,
                (CASE WHEN pss.pss_website_clicked = 1 THEN 'Yes' ELSE 'No' END) as website_clicked
            FROM SSREPORT2.PAGES_IMPRESSION_STATS pss
            WHERE
                    pss.pss_spb_branch_code = :tnid
                    AND pss.pss_view_date >= TO_DATE(:startDate, 'DD-Mon-YYYY')
                    AND pss.pss_view_date < TO_DATE(:endDate, 'DD-Mon-YYYY') + 1
                ORDER BY
                    pss.pss_view_date, pss.pss_source";

        $params = array(
            'tnid' => (int)$tnid,
            'startDate' => $startDate,
            'endDate' => $endDate
        );

        $result = $this->getSsreport2Db()->fetchAll($sql, $params);
        
		return $result;
    }
}
