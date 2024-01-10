<?php

class Myshipserv_UserCompany_Management extends Shipserv_Object
{
	public function __construct()
	{
		$this->db = $this->getDb();
	}

	public function setParams($params)
	{
		$this->params = $params;
	}

	public function updateUser()
	{

		$sql = "
			UPDATE pages_user
			SET
				PSU_FIRSTNAME=:PSU_FIRSTNAME
 				, PSU_LASTNAME=:PSU_LASTNAME
				, PSU_EMAIL=:PSU_EMAIL
				, PSU_COMPANY=:PSU_COMPANY
				, PSU_COMPANY_ADDRESS=:PSU_COMPANY_ADDRESS
				, PSU_COMPANY_ZIP_CODE=:PSU_COMPANY_ZIP_CODE
				, PSU_COMPANY_PHONE=:PSU_COMPANY_PHONE
				, PSU_COMPANY_WEBSITE=:PSU_COMPANY_WEBSITE
				, PSU_COMPANY_NO_VESSEL=:PSU_COMPANY_NO_VESSEL
				, PSU_EMAIL_CONFIRMED=:PSU_EMAIL_CONFIRMED
			WHERE
				PSU_ID=:PSU_ID
		";

		$params = $this->_cleanParamsForInsertion($this->params, 'PSU_');

		try {
			$this->db->query($sql, $params);
		} catch(Exception $e) {
			return false;
		}
		$sql = "
			UPDATE users
				SET usr_sts=:USR_STS
			WHERE
				usr_user_code=:USR_USER_CODE
		";
		$params = $this->_cleanParamsForInsertion($this->params, 'USR_');

		try {
			$this->db->query($sql, $params);
		} catch(Exception $e){
			return false;
		}

		$user = Shipserv_User::getInstanceById($params['USR_USER_CODE'], 'P', true);
		$user->purgeMemcache();



		return true;
	}

	private function _cleanParamsForInsertion($params, $onlyAccept)
	{
		foreach ($params as $name => $value ) {
			if (strstr($name, $onlyAccept) !== false) {
                $new[ $name ] = $value;
			}
		}

		return $new;
	}



	public function search($keyword, $type, $limit)
	{

		$sql = $this->_getSqlForSearch($type, $limit);
		$params = array(
			'query' => '%' . strtolower($keyword) . '%'
			, 'query2' => $keyword
		);

		return $this->db->fetchAll($sql, $params);
	}

	/**
	 * Getting user detail from the DB
	 * @param unknown $id
	 */
	public function getUserDetail($id)
	{
		$sql = "
			SELECT 
			    psu_id,
			    psu_email,
			    psu_firstname,
			    psu_lastname,
			    psu_company,
			    psu_pct_id,
			    psu_created_by,
			    psu_creation_date,
			    psu_last_update_by,
			    psu_last_update_date,
			    psu_pjf_id,
			    psu_other_comp_type, 
			    psu_other_job_function, 
			    psu_is_mktg_updated,
			    psu_alert_status,
			    psu_alias, 
			    psu_email_confirmed,
			    psu_last_review_email, 
			    psu_svr_access, 
			    psu_is_decision_maker, 
			    psu_company_address, 
			    psu_company_zip_code, 
			    psu_company_country_code, 
			    psu_company_phone, 
			    psu_company_website, 
			    psu_company_spending, 
			    psu_company_no_vessel, 
			    psu_anonymity_flag,
			    psu_company_type, 
			    psu_rfq_deadline_mgr, 
			    usr_user_code,
			    usr_name,
			    usr_type,
			    usr_role,
			    usr_display_flash_page,
			    usr_created_by,
			    usr_created_date,
			    usr_updated_by,
			    usr_updated_date,
			    usr_md5_code,
			    usr_md5_date,
			    usr_sts, 
			    usr_is_view_buyer_org,
			    usr_is_pages_enquiry_blocked, 
			    usr_pages_enquiry_sent_limit, 
			    usr_pages_enquiry_status,
			    usr_announcement_suppressed, 
			    usr_is_view_under_contract,
			    usr_integration_code,
			    usr_invoice_integration_code, 
			    usr_delivery_mode,
			    usr_upload_encoding_type,
			    usr_download_encoding_type,
			    usr_integration_url, 
			    usr_purchasing_plugin,
			    usr_purchasing_profile, 
			    usr_logistics_plugin,
			    usr_logistics_url,
			    usr_logistics_connection_id, 
			    usr_logistics_password,
			    usr_logistics_profile, 
			    usr_inv_mtml_version 
			 FROM
			 	pages_user JOIN users ON (psu_id=usr_user_code)
			 WHERE psu_id=:id";
		$params = array(
			'id' => $id
		);
		return $this->db->fetchAll($sql, $params);
	}

	/**
	 * Getting list of companies that a user is part of from the DB
	 * @param unknown $id
	 */
	public function getListOfUserCompanies($id)
	{
		$sql = "
    			SELECT
		            pages_user_company.*, 
		            (
						CASE
    						WHEN spb_branch_code IS NOT null THEN
    							spb_name
    						WHEN byo_org_code IS NOT null THEN
    							byo_name
							WHEN byb_branch_code IS NOT null THEN
    							byb_name
    						WHEN con_internal_ref_no IS NOT null THEN
                                con_consortia_name
    					END
    				) company_name,
                    byb_sts
    			FROM
    				pages_user_company
    				LEFT OUTER JOIN supplier_branch ON (puc_company_type='SPB' AND puc_company_id=spb_branch_code)
		            LEFT OUTER JOIN buyer_organisation ON (puc_company_type='BYO' AND puc_company_id=byo_org_code)
					LEFT OUTER JOIN buyer_branch ON (puc_company_type='BYB' AND puc_company_id=byb_branch_code)
					LEFT OUTER JOIN consortia ON (puc_company_type='CON' AND puc_company_id=con_internal_ref_no)
    			WHERE
    				puc_psu_id=:id
    		";
		$params = array(
			'id' => $id
		);

		return $this->db->fetchAll($sql, $params);
	}

	
	public function getListOfUserActivity($id)
	{
		$user = Shipserv_User::getInstanceById($id, true);
		return $user->getActivity(Myshipserv_Period::dayAgo(30))->toArray();
	}
	

	public function getListOfUsersFromCompanies($id, $type)
	{
		$sql = "
    			SELECT
		            pages_user_company.*
		            , pages_user.*
   					, (
						CASE
    						WHEN spb_branch_code IS NOT null THEN
    							spb_name
    						WHEN byo_org_code IS NOT null THEN
    							byo_name
    						WHEN con_internal_ref_no IS NOT null THEN
                                con_consortia_name
    					END
    				) company_name
    			FROM
    				pages_user_company
   					JOIN pages_user ON (puc_psu_id=psu_id)
    				LEFT OUTER JOIN supplier_branch ON (puc_company_type='SPB' AND puc_company_id=spb_branch_code)
		            LEFT OUTER JOIN buyer_organisation ON (puc_company_type='BYO' AND puc_company_id=byo_org_code)
		            LEFT OUTER JOIN consortia ON (puc_company_type='CON' AND puc_company_id=con_internal_ref_no)
    			WHERE
    				puc_company_id=:id
    				AND puc_company_type=UPPER(:type)
    		";
		$params = array(
			'id' => $id
			, 'type' => $type
		);
		$rows = $this->db->fetchAll($sql, $params);
		return $rows;
	}


	public function getListOfBuyerBranchByBuyerOrgCode($id)
	{
		$sql = "
   				SELECT
   					byb_name
   					, byb_branch_code
   				FROM
   					buyer_branch
   				WHERE
   					byb_byo_org_code=:id
   			";
		$params = array(
			'id' => $id
		);
		return $this->db->fetchAll($sql, $params);

	}

	private function _getSqlForSearch($type, $limit) 
	{
	    $sqlForAll = array();
		$sqlForAll['usr'] = "
    			SELECT
    				psu_id AS id
					, psu_firstname || ' ' || psu_lastname AS title
    				, psu_company AS description
    				, 'user' AS row_type
					, rownum rn
					, psu_email
    			FROM
    				pages_user
    			WHERE
    				LOWER(psu_email) LIKE :query
    				OR LOWER(psu_firstname) LIKE :query
    				OR LOWER(psu_lastname) LIKE :query
		              OR (
		                LENGTH(:query) > 0
		                AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
		                AND psu_id = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
		              )

    		";

		$sqlForAll['spb'] = "
    			SELECT
    				spb_branch_code AS id
    				, spb_name AS title
    				, spb_branch_code || ' - ' || spb_country AS description
    				, 'supplier' AS row_type
					, rownum rn
					, '' psu_email
    			FROM
    				supplier_branch
    			WHERE
    				(
    					LOWER(spb_name) LIKE :query
			              OR (
			                LENGTH(:query) > 0
			                AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
			                AND spb_branch_code = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
			              )


    				)
    				AND spb_branch_code < 5000000
    		";

		$sqlForAll['byo'] = "
    			SELECT
    				byo_org_code AS id
    				, byo_name AS title
    				, byo_org_code 
		              || 
		              (CASE 
		                  WHEN pbn_byo_org_code IS NOT NULL AND pbn_norm_byo_org_code IS NOT NULL THEN ' - CHILD OF ' || pbn_norm_byo_org_code
		                  WHEN pbn_byo_org_code IS NOT NULL AND pbn_norm_byo_org_code IS NULL THEN ' - DELETED'
		                  ELSE ' - COMPANY' 
		              END) 
		              AS description
    				, 'buyer-org' AS row_type
					, rownum rn
					, '' AS psu_email
    			FROM
    				buyer_organisation LEFT JOIN pages_byo_norm ON pbn_byo_org_code=byo_org_code
    			WHERE
    				(
    					LOWER(byo_name) LIKE :query
			              OR (
			                LENGTH(:query) > 0
			                AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
			                AND byo_org_code = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
			              )
    				)
    		";

		$sqlForAll['byb'] = "
    			SELECT
    				byb_branch_code AS id
    				, byb_name AS title
    				, byb_branch_code || ' - ' || byb_country AS description
    				, 'buyer-branch' AS row_type
					, rownum rn
					, '' psu_email
    			FROM
    				buyer_branch
    			WHERE
    				(
    					LOWER(byb_name) LIKE :query
			              OR (
			                LENGTH(:query) > 0
			                AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
			                AND byb_branch_code = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
			              )
    				)
    		";

        $sqlForAll['con'] = "
                SELECT
    				con_internal_ref_no AS id
    				, con_consortia_name AS title
    				, con_internal_ref_no || ' - ' || con_consortia_name AS description
    				, 'consortia' AS row_type
					, rownum rn
					, '' psu_email
    			FROM
    				consortia
    			WHERE
    				(
    					LOWER(con_consortia_name) LIKE :query
			              OR (
			                LENGTH(:query) > 0
			                AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
			                AND con_internal_ref_no = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
			              )
    				)
    		";

        $sqlForAll['con_user'] = "
                SELECT
    				psu_id AS id
					, psu_firstname || ' ' || psu_lastname AS title
    				, psu_company AS description
    				, 'user' AS row_type
					, rownum rn
					, psu_email
    			FROM
    				pages_user
    			WHERE
                    EXISTS (
                      SELECT null FROM pages_user_company puc WHERE puc.puc_psu_id = pages_user.psu_id and puc.puc_company_type='CON' and puc.PUC_STATUS='ACT'
                    )
            
    				AND (
                        LOWER(psu_email) LIKE :query
                        OR LOWER(psu_firstname) LIKE :query
                        OR LOWER(psu_lastname) LIKE :query
                          OR (
                            LENGTH(:query) > 0
                            AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
                            AND psu_id = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
                          )
                    )
    		";


		$sql['spb-0pc'] = "
    			SELECT
    				spb_branch_code AS id
    				, spb_name AS title
    				, spb_branch_code || ' - ' || spb_country AS description
    				, 'supplier' AS row_type
					, rownum rn
					, '' psu_email
    			FROM
    				supplier_branch
    			WHERE
    				(
    					LOWER(spb_name) LIKE :query
			              OR (
			                LENGTH(:query) > 0
			                AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
			                AND spb_branch_code = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
			              )

    				)
    				AND spb_branch_code < 5000000
    				AND SPB_MONETIZATION_PERCENT = 0
    		";

		$sql['spb-non-0pc'] = "
    			SELECT
    				spb_branch_code AS id
    				, spb_name AS title
    				, spb_branch_code || ' - ' || spb_country AS description
    				, 'supplier' AS row_type
					, rownum rn
					, '' psu_email
    			FROM
    				supplier_branch
    			WHERE
    				(
    					LOWER(spb_name) LIKE :query
			              OR (
			                LENGTH(:query) > 0
			                AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
			                AND spb_branch_code = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
			              )

    				)
    				AND spb_branch_code < 5000000
    				AND SPB_MONETIZATION_PERCENT > 0
    		";

		$sql['spb-test'] = "
    			SELECT
    				spb_branch_code AS id
    				, spb_name AS title
    				, spb_branch_code || ' - ' || spb_country AS description
    				, 'supplier' AS row_type
					, rownum rn
					, '' psu_email
    			FROM
    				supplier_branch
    			WHERE
    				(
    					LOWER(spb_name) LIKE :query
			              OR (
			                LENGTH(:query) > 0
			                AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
			                AND spb_branch_code = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
			              )

    				)
    				AND spb_branch_code < 5000000
    				AND spb_test_account = 'Y'
    		";

		$sql['spb-premium'] = "
    			SELECT
    				spb_branch_code AS id
    				, spb_name AS title
    				, spb_branch_code || ' - ' || spb_country AS description
    				, 'supplier' AS row_type
					, rownum rn
					, '' psu_email
    			FROM
    				supplier_branch
    			WHERE
    				(
    					LOWER(spb_name) LIKE :query
			              OR (
			                LENGTH(:query) > 0
			                AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
			                AND spb_branch_code = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
			              )

    				)
    				AND spb_branch_code < 5000000
    				AND DIRECTORY_LISTING_LEVEL_ID = 4
    		";

		$sql['spb-basic'] = "
    			SELECT
    				spb_branch_code AS id
    				, spb_name AS title
    				, spb_branch_code || ' - ' || spb_country AS description
    				, 'supplier' AS row_type
					, rownum rn
					, '' psu_email
    			FROM
    				supplier_branch
    			WHERE
    				(
    					LOWER(spb_name) LIKE :query
			              OR (
			                LENGTH(:query) > 0
			                AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
			                AND spb_branch_code = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
			              )

    				)
    				AND spb_branch_code < 5000000
    				AND DIRECTORY_LISTING_LEVEL_ID != 4
    		";

		$sql['usr-non-activated'] = "

    			SELECT
    				psu_id AS id
					, psu_firstname || ' ' || psu_lastname AS title
    				, psu_company AS description
    				, 'user' AS row_type
					, rownum rn
					, '' psu_email
    			FROM
    				pages_user
    			WHERE
    				(
	    				LOWER(psu_email) LIKE :query
	    				OR LOWER(psu_firstname) LIKE :query
	    				OR LOWER(psu_lastname) LIKE :query
			              OR (
			                LENGTH(:query) > 0
			                AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
			                AND psu_id = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
			              )

    				)
    				AND PSU_EMAIL_CONFIRMED != 'Y'

    		";

		$sql['usr-not-trusted'] = "
    			SELECT
    				psu_id AS id
					, psu_firstname || ' ' || psu_lastname AS title
    				, psu_company AS description
    				, 'user' AS row_type
					, rownum rn
					, '' psu_email
    			FROM
    				pages_user JOIN users ON (usr_user_code=psu_id)
    			WHERE
    				(
	    				LOWER(psu_email) LIKE :query
	    				OR LOWER(psu_firstname) LIKE :query
	    				OR LOWER(psu_lastname) LIKE :query
    			        OR (
			                LENGTH(:query) > 0
			                AND LENGTH(REGEXP_REPLACE(:query2, '[^0-9]+', '')) = LENGTH(:query2)
			                AND psu_id = TO_NUMBER(REGEXP_REPLACE(:query2, '[^0-9]+', ''))
			              )

    				)
    				AND usr_is_pages_enquiry_blocked = 1

    		";


		if ($type == 'all') {
			$sql = implode(" UNION ALL ", $sqlForAll);
		} else {
			if (isset($sqlForAll[$type])) { 
                $sql = $sqlForAll[$type];
			} else { 
			    $sql = $sql[$type];
			}

			if ($limit !== null) {
				$sql = "
					SELECT
						*
					FROM
						(
							" . $sql . "
						)
					WHERE
						rn<=" . $limit . "
				";
			}
		}


		return $sql;
	}

	
	public function processDeleteUserCompany($userId, $companyType, $companyId)
	{

	}

	/**
	 * Merge data to pages_user_company table with some validation prior
	 * 
	 * @param array $row HTTP request params, can be null if "setParams" was called before
	 * @throws Myshipserv_Exception_MessagedException
	 */
	public function processUserJoinCompany($row)
	{
		if ($row) {
			$this->setParams($row);
		}

		$params = $this->_cleanParamsForInsertion($this->params, 'PUC_');

		if ($this->params['PUC_COMPANY_TYPE'] == "") {
			throw new Myshipserv_Exception_MessagedException("Please check your company type", 500);
		} else {
			switch ($this->params['PUC_COMPANY_TYPE']) {
				case 'BYB':
                    $sql = "SELECT COUNT(*) FROM buyer_branch WHERE byb_branch_code=:PUC_COMPANY_ID";
					break;
				case 'BYO':
					$sql = "SELECT COUNT(*) FROM buyer_organisation WHERE byo_org_code=:PUC_COMPANY_ID";
					break;
				case 'SPB':
					$sql = "SELECT 1 FROM supplier_branch WHERE spb_branch_code=:PUC_COMPANY_ID";
					break;
                case 'CON':
                    $sql = "SELECT 1 FROM consortia WHERE con_internal_ref_no=:PUC_COMPANY_ID";
                    break;
			}
			$totalFound = $this->db->fetchOne($sql, array('PUC_COMPANY_ID' => $this->params['PUC_COMPANY_ID']));

			if ($totalFound == 0) {
				throw new Myshipserv_Exception_MessagedException("Please check your company id", 500);
			}
		}

		//DE7251 We should block joining a BYB where we do not have a realationship already established whith its parent BYO (and consider normalisation)
		if ($this->params['PUC_COMPANY_TYPE'] === 'BYB') {
			$sql = "
					SELECT
					  nvl(pbn_norm_byo_org_code, byb_byo_org_code) translated_org_code
					FROM
					  buyer_branch byb LEFT JOIN 
					  pages_byo_norm pbn
					  ON pbn.pbn_byo_org_code = byb.byb_byo_org_code
					WHERE
					  byb.byb_branch_code = :PUC_COMPANY_ID";
			
			$translatedByo = $this->db->fetchOne($sql, array('PUC_COMPANY_ID' => $this->params['PUC_COMPANY_ID']));
			
			$sql = "
					SELECT
					  count(*) byo_count
					FROM
					  pages_user_company
					where
					  puc_psu_id = :PUC_PSU_ID
					  and puc_company_id = :PUC_COMPANY_ID
					  and puc_company_type = 'BYO'
					  and puc_status = 'ACT'";
			
			$hasBybAlreadyAssigned = $this->db->fetchOne(
					$sql,
					array(
						'PUC_PSU_ID' => (int)$this->params['PUC_PSU_ID'],
						'PUC_COMPANY_ID' => (int)$translatedByo
						)
					);
			
			if ((int)$hasBybAlreadyAssigned === 0) {
				throw new Myshipserv_Exception_MessagedException("The Buyer Branch you are trying to add belongs to Buyer Org " .$translatedByo. ", and this buyer org has no relation with the user", 500);
			}
		}
		
		$sql = "
			MERGE INTO pages_user_company USING DUAL ON (puc_psu_id=:PUC_PSU_ID AND PUC_COMPANY_ID=:PUC_COMPANY_ID)
				WHEN MATCHED THEN
					UPDATE SET
						PUC_COMPANY_TYPE=:PUC_COMPANY_TYPE
						, PUC_LEVEL=:PUC_LEVEL
						, PUC_STATUS=:PUC_STATUS
						, PUC_MATCH=:PUC_MATCH
						, PUC_IS_DEFAULT=:PUC_IS_DEFAULT
						, PUC_TXNMON=:PUC_TXNMON
						, PUC_WEBREPORTER=:PUC_WEBREPORTER
						, PUC_BUY=:PUC_BUY
						, PUC_TXNMON_ADM=:PUC_TXNMON_ADM
						, PUC_AUTOREMINDER=:PUC_AUTOREMINDER
				WHEN NOT MATCHED THEN
					INSERT
						(
							PUC_COMPANY_TYPE
							, PUC_COMPANY_ID
							, PUC_LEVEL
							, PUC_STATUS
							, PUC_MATCH
							, PUC_IS_DEFAULT
							, PUC_TXNMON
							, PUC_WEBREPORTER
							, PUC_BUY
							, PUC_TXNMON_ADM
							, PUC_AUTOREMINDER
							, PUC_PSU_ID
						)
					VALUES
						(
							:PUC_COMPANY_TYPE
							, :PUC_COMPANY_ID
							, :PUC_LEVEL
							, :PUC_STATUS
							, :PUC_MATCH
							, :PUC_IS_DEFAULT
							, :PUC_TXNMON
							, :PUC_WEBREPORTER
							, :PUC_BUY
							, :PUC_TXNMON_ADM
							, :PUC_AUTOREMINDER
							, :PUC_PSU_ID
						)
		";

		$params = $this->_cleanParamsForInsertion($this->params, 'PUC_');
		try {
			$this->db->query($sql, $params);
		} catch(Exception $e) {
			throw new Myshipserv_Exception_MessagedException("There is a problem with your input. Please check and try again", 500);
		}

		//If everithing is ok, and we are adding a consortia company, lets add the consortia to pages_company
        if ($this->params['PUC_COMPANY_TYPE'] === 'CON') {
            Shipserv_Consortia::addConsortiaToPagesCompany((int) $this->params['PUC_COMPANY_ID']);
        }

	}

	
	public function processUpdateColumnUserCompany($rows)
	{

		$sql = "
			UPDATE pages_user_company
			SET " . $rows['column'] . " = :value
			WHERE
				puc_psu_id=:userId
				AND puc_company_id=:companyId
		";

		$params = array(
			'value' => $rows['value']
			, 'userId' => $rows['userId']
			, 'companyId' => $rows['companyId']
		);
		$this->db->query($sql, $params);
	}

}
