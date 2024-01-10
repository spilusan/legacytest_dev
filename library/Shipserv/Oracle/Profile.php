<?php
class Shipserv_Oracle_Profile extends Shipserv_Oracle
{

	/**
	 * Adapter for fetching a supplier profile. Will optionally cache the result
	 * if a memcache adapter is passed
	 *
 	 * @access public
	 * @param int $tnid TradeNet ID of the supplier profile to fetch
	 * @param boolean $useMemcache Optional memcache object used to cache the result
	 * @param int $timeout Memcache key timeout (default 12 hours)
	 * @return array An array of supplier data
	 */
	public function fetch ($tnid, $useMemcache = true, $timeout = 43200, $skipPagesCheck = false )
	{
		$parameters = array($tnid);
		$supplierProfile = false;

		if ($useMemcache)
		{
			$memcacheInstance = $this::getMemcache();
			if ($memcacheInstance)
			{
				$key = $this->memcacheConfig->client->keyPrefix . 'PROFILETNID_'.$tnid. $skipPagesCheck . $this->memcacheConfig->client->keySuffix;
				$supplierProfile = $memcacheInstance->get($key);
			}
		}

		if (!$supplierProfile)
		{
			$supplierProfile = $this->fetchMainProfile ($tnid, false, 86400, $skipPagesCheck);
			$orgCode =$supplierProfile["ORGCODE"] ?? null ;

			$supplierProfile["brands"]        = $this->getBrands($tnid);
			$supplierProfile["ownedBrands"]   = $this->getOwnedBrands($tnid);
			if ($orgCode !== null) {
				$supplierProfile["attachments"]   = $this->getAttachments($tnid, $orgCode);
				$supplierProfile["maAttachments"] = $this->getMAAttachments($tnid, $orgCode);
				$supplierProfile["categories"]    = $this->getCategories($tnid, $orgCode);
				$supplierProfile["contacts"]      = $this->getContacts($tnid, $orgCode);
				$supplierProfile["ports"]         = $this->getPorts($tnid, $orgCode);
			}
			$supplierProfile["memberships"]   = $this->getMemberships($tnid);
			$supplierProfile["certifications"]= $this->getCertifications($tnid);
			$supplierProfile["catalogues"]    = $this->getCatalogues($tnid);
			$supplierProfile["videos"]        = $this->getVideos($tnid);

			if ($useMemcache && $memcacheInstance)
			{
				$memcacheInstance->set($key, $supplierProfile, false, $timeout);
			}
		}

		return $supplierProfile;
	}

	public function fetchMainProfile ($tnid, $useCache = false, $cacheTTL = 86400, $skipCheck = false)
		{
		$sql = "select
			sb.spb_sts													   as tradeNetStatus,
			sb.spb_sup_org_code                                          as orgCode,
			sb.SPB_BRANCH_CODE                                             as tnid,
			sb.spb_name                                                    as name,
			sb.DESCRIPTION_OF_BUSINESS                                     as description,
			sb.spb_branch_address_1                                        as address1,
			sb.spb_branch_address_2                                        as address2,
			sb.SPB_CITY                                                    as city,
			sb.SPB_STATE_PROVINCE                                          as state,
			sb.spb_zip_code                                                as zipCode,
			sb.SPB_COUNTRY                                                 as countryCode,
			sb.spb_account_region                                          as accountRegion,
			cnt.cnt_name                                                   as countryName,
			cnt.is_restricted                                              as countryRestricted,
			sb.spb_phone_no_1                                              as phoneNo,
			sb.spb_fax                                                     as faxNo,
			sb.spb_after_hours_phone                                       as afterHoursNo,
			sb.spb_email			                                       as email,
			sb.public_contact_email										   as publicEmail,
			sb.large_logo_url                                              as logoUrl,
			sb.spb_home_page_url                                           as homePageUrl,
			sb.spb_acct_mngr_name                                          as accountManagerName,
			sb.spb_acct_mngr_email										   as accountManagerEmail,
			sb.spb_acct_mngr_support_hrs								   as accountManagerSupportHrs,
			sb.spb_account_deleted										   as accountIsDeleted,
			sb.spb_pcs_score											   as PROFILE_COMPLETION_SCORE,
			sb.directory_entry_status									   as directoryStatus,
			sb.spb_is_global_delivery									   as global_delivery,
			sb.DIRECTORY_ENTRY_STATUS									   as isPublished,
			sb.SPB_CONTACT_NAME as contactPerson,
			decode(sb.SPB_PUBLIC_BRANCH_CODE, null, sb.SPB_BRANCH_CODE, sb.SPB_PUBLIC_BRANCH_CODE) as publicTnid,
			decode (SPB_PUBLIC_BRANCH_CODE, null, sb.spb_last_tran_date,
				(select spb_last_tran_date
					from supplier_branch where spb_branch_code =
						(select SPB_PUBLIC_BRANCH_CODE
						from supplier_branch
						where spb_branch_code = sb.spb_branch_code)))      as lastTranDate,
			decode(sb.SPB_ESERVICE_LEVEL,1,1,0)                            as smartSupplier,
			decode(sb.spb_eservice_level,2,1,0)                            as expertSupplier,
			decode(sb.directory_listing_level_id,4,1,0)                    as premiumListing,
			decode(sb.spb_impa_member,'Y',1,0)                             as impaMember,
			decode(sb.spb_issa_member,'Y',1,0)                             as issaMember,
			decode(sb.spb_tradenet_member,'Y',1,0)                         as tradeNetMember,
			decode (SPB_PUBLIC_BRANCH_CODE, null, spb_trade_rank,
			(select spb_trade_rank as public_trade_rank
				from supplier_branch where spb_branch_code =
					(select SPB_PUBLIC_BRANCH_CODE
					from supplier_branch
					where spb_branch_code = sb.spb_branch_code)))          as tradeRank,
			nvl((select 1
				from catalogue_owner o, catalogue c
				where
				  o.tnid = sb.spb_branch_code and
				  o.id = c.owner_id and
				  c.deleted_on_utc is null and
				  c.is_published_in_pages = 1 and
				  c.valid_from_utc <= sysdate and
				  c.valid_to_utc >= sysdate
				group by o.tnid),0)                                        as onlineCatalogue,
			(select ac.access_code
					from access_code ac
					where ac.tnid = sb.spb_branch_code)                        as accessCode,
			sb.spb_latitude												   as latitude,
			sb.spb_longitude											   as longitude,
			case WHEN DIRECTORY_LIST_VERIFIED_AT_UTC > add_months(sysdate, -12)
			  THEN 1 ELSE 0 END 							     			as isVerified,
			sb.spb_created_date												as joinedDate,
			sb.spb_svr_access												as svrAccess,
			sb.spb_pgs_tn_int												as integratedOnTradeNet,
            -- additional fields added by Yuriy Akopov on 2013-08-07
            sb.spb_monetization_percent                                     as monetisationPercent,
            CASE WHEN sb.spb_match_exclude = 'Y' THEN 1 ELSE 0 END          as matchExclude,
			sb.spb_onsite_inspected											as onsiteInspected,
			sb.spb_onsite_ver_rpt_url										as onsiteVerRptUrl
			from supplier_branch sb, country cnt
			where
				sb.SPB_BRANCH_CODE = :tnid and
		";

		if( $skipCheck === false )
		{
			$sql .= "
				sb.DIRECTORY_ENTRY_STATUS = 'PUBLISHED' and
				sb.SPB_ACCOUNT_DELETED = 'N' and
				sb.spb_test_account = 'N' and
				sb.SPB_BRANCH_CODE <= 999999 and
							
			";
		}
		
		$sql .= "				sb.spb_country = cnt.cnt_country_code (+)
		";

		$sqlData = array('tnid' => $tnid);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'PROFILEFOR_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result[0] ?? null;
	}


	public function getBrands ($tnid, $useCache = false, $cacheTTL = 86400)
	{
		$sql = "select
				b1.*, b2.name name, b2.browse_page_name, b2.logo_filename,
				(select count(*) from pages_company_brands where pcb_brand_id = b1.pcb_brand_id and pcb_auth_level='OWN' and pcb_is_authorised='Y' and pcb_is_deleted='N') ownersCount
			from (
		    select
				*
				from pages_company_brands
				where
					pcb_company_id = nvl((select psn_norm_spb_branch_code
							  from pages_spb_norm
							  where psn_spb_branch_code = :tnid), :tnid) and
					pcb_auth_level <> 'OWN' and
					pcb_is_deleted <> 'Y'
		) b1, brand b2
		where b1.pcb_brand_id = b2.id
		order by upper(b2.name)";

		$sqlData = array('tnid' => $tnid);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'BRANDSFOR_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}
	
	public function getOwnedBrands ($tnid, $useCache = false, $cacheTTL = 86400)
	{
		$sql = "select
				b1.*, b2.name name, b2.browse_page_name, b2.logo_filename,
				(select count(*) from pages_company_brands where pcb_brand_id = b1.pcb_brand_id and pcb_auth_level='OWN' and pcb_is_authorised='Y' and pcb_is_deleted='N') ownersCount
			from (
		    select
				*
				from pages_company_brands
				where
					pcb_company_id = nvl((select psn_norm_spb_branch_code
							  from pages_spb_norm
							  where psn_spb_branch_code = :tnid), :tnid) and
					pcb_auth_level = 'OWN' and
					pcb_is_authorised = 'Y' AND
					pcb_is_deleted <> 'Y'
		) b1, brand b2
		where b1.pcb_brand_id = b2.id
		order by upper(b2.name)";

		$sqlData = array('tnid' => $tnid);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'OWNEDBRANDSFOR_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}
	
	public function getAttachments ($tnid, $orgCode, $useCache = false, $cacheTTL = 86400)
	{
		$sql = "
				select
				id                     as id,
				name                   as name,
				short_description      as description,
				attachment_url         as url
			from directory_entry_attachment
			where
				supplier_org_code = :orgCode and
				supplier_branch_code = :tnid and
				is_ma = 0 and
				deleted_on_utc is null
			order by name

		";


		$sqlData = array('tnid' => $tnid, 'orgCode'	=> $orgCode);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'ATTACHMENTSFOR_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}

	public function getMAAttachments ($tnid, $orgCode, $useCache = false, $cacheTTL = 86400)
	{
		$sql = "
		select
		    id                     as id,
			name                   as name,
			short_description      as description,
			ref_no                 as refNo,
			attachment_url         as url,
			thumbnail_url          as thumbnailUrl
		from directory_entry_attachment
		where
			supplier_org_code = :orgCode and
			supplier_branch_code = :tnid and
			is_ma = 1 and
			deleted_on_utc is null
		order by name
		";


		$sqlData = array('tnid' => $tnid, 'orgCode'	=> $orgCode);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'MAATTACHMENTSFOR_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}

	public function getCategories ($tnid, $orgCode, $useCache = false, $cacheTTL = 86400)
	{
		$sql = "
		select
			p.id,
			p.name,
			p.browse_page_name,
			decode(s.primary_supply_category,1,1,0) as primary,
			(select count(*) from pages_category_editor where pce_category_id = s.product_category_id) ownersCount
		from product_category p, supply_category s
		where
			s.supplier_org_code = :orgCode and
			s.supplier_branch_code = :tnid and
			s.product_category_id = p.id and
			(s.is_authorised='Y' OR s.is_authorised is null)
		order by p.name
		";


		$sqlData = array('tnid' => $tnid, 'orgCode'	=> $orgCode);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CATEGORIESFOR_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}
	
	public function getContacts ($tnid, $orgCode, $useCache = false, $cacheTTL = 86400)
	{
		$sql = "
		select
			id                                 as id,
			first_name                         as firstName,
			middle_name                        as middleName,
			last_name                          as lastName,
			title                              as nameTitle,
			job_title                          as jobTitle,
			telephone                          as phoneNo,
			mobile_phone                       as mobileNo,
			skype_name                         as skypeName,
			directory_entry_status             as status,
			electronic_mail                    as emailAddress,
			department						   as department
		from contact_person
		where
			supplier_org_code = :orgCode and
			supplier_branch_code = :tnid
		order by LOWER(TRIM(first_name) || TRIM(middle_name) || TRIM(last_name)) ASC
				";


		$sqlData = array('tnid' => $tnid, 'orgCode'	=> $orgCode);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CONTACTSFOR_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}

	public function getPorts ($tnid, $orgCode, $useCache = false, $cacheTTL = 86400)
	{
		$sql = '
            SELECT
                p.prt_port_code as code,
                p.prt_name as name,
                p.prt_cnt_country_code as countryCode,
                nvl(sp.primary_delivery_port,0) as primary,
                p.prt_is_restricted as portRestricted,
                cnt.is_restricted as countryRestricted,
                CASE WHEN p.prt_is_restricted = 1 or cnt.is_restricted = 1 THEN 1 else 0 END as isRestricted
            FROM
                supplier_port sp, port p, country cnt
            WHERE
                sp.spp_spb_sup_org_code = :orgCode and
                sp.spp_spb_branch_code = :tnid and
                sp.spp_prt_port_code = p.prt_port_code and
                cnt.cnt_country_code = p.prt_cnt_country_code';

		$sqlData = array('tnid' => $tnid, 'orgCode'	=> $orgCode);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'PORTSFOR_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}

	public function getMemberships ($tnid, $useCache = false, $cacheTTL = 86400)
	{
		$sql = "
		select
			qo_id      id,
			qo_name    name,
			qo_logo_path,
			sm_is_authorised,
			(select count(*) from pages_membership_owner where pmo_qo_id= s.sm_qo_id) ownersCount
		from supplier_membership s, quality_organization q
		where
			s.sm_sup_branch_code = :tnid and
			s.sm_qo_id = q.qo_id
		";


		$sqlData = array('tnid' => $tnid);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'MEMBERSHIPSFOR_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}

	public function getCertifications ($tnid, $useCache = false, $cacheTTL = 86400)
	{
		$sql = "
		select
			co_id      id,
			co_name    name,
			co_logo_path,
			sc_is_authorised
		from supplier_certification s, certification_organization q
		where
			s.sc_sup_branch_code = :tnid and
			s.sc_co_id = q.co_id
		";


		$sqlData = array('tnid' => $tnid);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CERTIFICATIONSFOR_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}

	public function getCatalogues ($tnid, $useCache = false, $cacheTTL = 86400)
	{
		$sql = "
		select
			c.id,
			c.name,
			(select count(*) from catalogue_folder where catalogue_id = c.id and parent_folder_id is null) subfoldersFound
		from catalogue_owner o, catalogue c
		where
			o.tnid = :tnid and
			c.owner_id = o.id and
			c.deleted_on_utc is null and
			c.is_published_in_pages = 1 and
			c.valid_from_utc <= sysdate and
			c.valid_to_utc > sysdate

		";


		$sqlData = array('tnid' => $tnid);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'CATALOGUESFOR_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}

	public function getVideos ($tnid, $useCache = false, $cacheTTL = 86400)
	{
		$sql = "
		select
			vid_id          id,
			vid_name        name,
			vid_description description,
			vid_clip_key    clipKey
		from pages_video
		where
			vid_tnid = :tnid and
			vid_deleted_date is null


		";


		$sqlData = array('tnid' => $tnid);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'VIDEOSFOR_'.$tnid.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}

	/**
	 * Fetch supplier branches by associated e-mail.
	 *
	 * @deprecated re-route to Shipserv_Oracle_Suppliers::fetchSuppliersByIds(array $ids)
	 * @return array of rows as associative arrays
	 */
	public function getSuppliersByIds(array $ids, $shipCheck = false)
	{
	    if (!$ids) {
	        return array();
	    }
		$spbDao = new Shipserv_Oracle_Suppliers($this->db);
		return $spbDao->fetchSuppliersByIds($ids, $shipCheck);
	}
	
	/**
	 * Fetch buyer organisations by associated e-mail.
	 *
	 * @deprecated re-route to Shipserv_Oracle_BuyerOrganisations::fetchBuyerOrganisationsByIds ($idsArray)
	 * @return array of rows as associative arrays
	 */
	public function getBuyersByIds (array $ids)
	{
		$byoDao = new Shipserv_Oracle_BuyerOrganisations($this->db);
		$res = $byoDao->fetchBuyerOrganisationsByIds($ids);
		return $res;
	}

    /**
     * Fetch consortia companies
     *
     * @deprecated re-route to Shipserv_Oracle_Suppliers::fetchSuppliersByIds(array $ids)
     * @return array of rows as associative arrays
     */
    public function getConsortiaByIds(array $ids)
    {
        if (!$ids) {
            return array();
        }
        $spbDao = new Shipserv_Consortia($this->db);
        return $spbDao->getConsortiaInstanceByIds($ids);
    }
	
	/**
	 * Transforms values of input array into a quoted list suitable for
	 * a SQL in clause: e.g. 3, 'str val', ...
	 *
	 * @return string
	 */
	private function arrToSqlList ($arr)
	{
		$sqlArr = array();
		foreach ($arr as $item)
		{
			$sqlArr[] = $this->db->quote($item);
		}
		if (!$sqlArr) $sqlArr[] = 'NULL';
		return join(', ', $sqlArr);
	}
}
