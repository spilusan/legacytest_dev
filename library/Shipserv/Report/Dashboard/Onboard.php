<?php
/**
 *
 */
class Shipserv_Report_Dashboard_Onboard extends Shipserv_Report_Dashboard
{
	public function setParams( $params = null)
	{
		$this->params = $params;
	}

	public function getData( $rolledUp = false )
	{
		$data = $this->getStatisticFromSservdbaDb();

		foreach($data as $row)
		{
			$new[$row['SUPPLIER'] . ' - ' . $row['TNID'] ] = $row;
		}

		return $new;

	}

	public function getInstallationStatistics()
	{
		$sql = "
		SELECT
		DISTINCT
		  sis_company_name
		  , sis_vessel_name
		  , sis_user_name
		  , sis_email_address
		  , sis_vessel_imo
		  , sis_byb_branch_code
		  , sis_cd_installation_date
		  , sis_no_app_started
		  , sis_no_req_created
		  , sis_machine_name
		FROM
		  sso_installation_statistics
		WHERE
			sis_app_version=:version
		ORDER BY
		  sis_cd_installation_date DESC
		";
		if( $this->params['version'] == "" ) $this->params['version'] = date('Y');
		$params = array('version' => $this->params['version']);
		$key = __CLASS__ . md5($sql) . print_r($this->params, true);
		$data = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'sservdba');
		;
		return $data;

	}

	
	protected function getStatisticFromSServdbaDb()
	{
		$sql = "
		SELECT
		  sss.sss_spb_branch_code TNID
		  , spb.spb_name Supplier
		  , SUM(sss.sss_banner_impressions) banner_impression
		  , SUM(sss.sss_banner_clicks) banner_click
		  , SUM(sss.sss_search_impressions) search_impression
		  , SUM(sss.sss_top_x) top_5
		  , SUM(sss.sss_profile_views) profile_view
		  , SUM(sss.sss_contact_views) contact_view
		  , SUM(sss.sss_catalog_presentations) catalog_presentation
		  , SUM(sss.sss_detail_views) detail_view
		  , SUM(sss.sss_doc_presentations) doc_presentation
		  , SUM(sss.sss_doc_views) doc_view
		  , SUM(sss.sss_supplier_products_in_req) product_in_requisition
		FROM
		  sso_supplier_statistics sss
			JOIN supplier_branch spb ON (spb.spb_branch_code=sss.sss_spb_branch_code)";
		
		if (array_key_exists('paying', $this->params)) {
			$sql .= "
			JOIN (SELECT DISTINCT sso_spb_branch_code FROM supplier_sso) sl ON sl.sso_spb_branch_code = sss.sss_spb_branch_code";
		}
		
		$sql .= "
		GROUP BY
		    sss.sss_spb_branch_code
		  , spb.spb_name
		ORDER BY spb.spb_name, sss.sss_spb_branch_code";
		
		$key = __CLASS__ . md5($sql) . print_r($this->params, true);
		//print "<pre>".$sql."\n".print_r($this->params, true); die();
		$data = $this->fetchCachedQuery ($sql, $params, $key, (60*60*2), 'sservdba');
		;
		return $data;
	}
}
