<?php

/**
 * Support company management on profile tab. Provides methods to:
 * 
 * Fetch buyers / suppliers belonging to user
 * Fetch details for specific buyer / supplier
 * Generate / save privacy setting forms
 */
class Myshipserv_Controller_Action_Helper_Companies extends Zend_Controller_Action_Helper_Abstract
{	
	/**
	 * Fetch company details for user.
	 *
	 * @return array Associative array of company details in the same format regardless of supplier / buyer distinction.
	 */
	public function getMyCompanies (Shipserv_User $user, $skipCheck = false)
	{
		// Fetch DB adapters
		$db = $this->getDb(); //$this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		$ucActions = new Myshipserv_UserCompany_Actions($db, $user->userId, $user->email);
		$profileDb = new Shipserv_Oracle_Profile($db);
		
		// Fetch buyers & suppliers for user
		$ucaCompanies = $ucActions->fetchMyCompanies();
		$buyers = $profileDb->getBuyersByIds($ucaCompanies->getBuyerIds());
		$suppliers = $profileDb->getSuppliersByIds($ucaCompanies->getSupplierIds(), $skipCheck);
		$consortia =  $profileDb->getConsortiaByIds($ucaCompanies->getConsortiaIds());
		
		// Fetch array of IDs for admin-level membership
		$ucaCompanies->getAdminIds($buyerAdminIds, $supplierAdminIds);

		return $this->makeCompanyViewData($buyers, $suppliers, $buyerAdminIds, $supplierAdminIds, $consortia);
	}
	
	
	private function getDb()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
		
	}
	
	
	public function getMyPendingCompanies (Shipserv_User $user)
	{
		// Fetch DB adapters
		$db = $this->getDb(); //$this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		$ucActions = new Myshipserv_UserCompany_Actions($db, $user->userId, $user->email);
		$profileDb = new Shipserv_Oracle_Profile($db);
		
		$ucaReqCompanies = $ucActions->fetchMyRequestedCompanies();
		$ucaReqCompanies->getLatestUniqueRequests(
			Myshipserv_UserCompany_RequestCollection::GET_PENDING,
			$returnBuyerIds, 
	        $returnSupplierIds, 
	        $returnRequests
        );		
		
		$buyers = $profileDb->getBuyersByIds($returnBuyerIds);
		$suppliers = $profileDb->getSuppliersByIds($returnSupplierIds, true);
		$companies = $this->makeCompanyViewData($buyers, $suppliers);
		$companies = $this->addRequestsToCompanies($companies, $returnRequests);
		return $companies;
	}
	
	public function getMyRejectedCompanies (Shipserv_User $user)
	{
		// Fetch DB adapters
		$db = $this->getDb(); //$this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		$ucActions = new Myshipserv_UserCompany_Actions($db, $user->userId, $user->email);
		$profileDb = new Shipserv_Oracle_Profile($db);
		
		$ucaReqCompanies = $ucActions->fetchMyRequestedCompanies();
		$ucaReqCompanies->getLatestUniqueRequests(
			Myshipserv_UserCompany_RequestCollection::GET_REJECTED,
			$returnBuyerIds, $returnSupplierIds, $returnRequests
        );
		
		$buyers = $profileDb->getBuyersByIds($returnBuyerIds);
		$suppliers = $profileDb->getSuppliersByIds($returnSupplierIds);
		
		$companies = $this->makeCompanyViewData($buyers, $suppliers);
		$companies = $this->addRequestsToCompanies($companies, $returnRequests);
		return $companies;
	}
	
	public function makeCompanyFromRequest (Shipserv_User $user, $reqId)
	{
		
		// Fetch DB adapters
		$db = $this->getDb(); //$this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		$ucActions = new Myshipserv_UserCompany_Actions($db, $user->userId, $user->email);
		$profileDb = new Shipserv_Oracle_Profile($db);
		
		// Fetch buyers & suppliers for user
		$ucaReqCompanies = $ucActions->fetchMyRequestedCompanies();
		$request = $ucaReqCompanies->getPendingRequestById($reqId);
		
		$buyers = $suppliers = array();
		if ($request['PUCR_COMPANY_TYPE'] == Shipserv_Oracle_UserCompanyRequest::COMP_TYPE_SPB) {
			$suppliers = $profileDb->getSuppliersByIds(array($request['PUCR_COMPANY_ID']), true);
		} elseif ($request['PUCR_COMPANY_TYPE'] == Shipserv_Oracle_UserCompanyRequest::COMP_TYPE_BYO) {
			$buyers = $profileDb->getBuyersByIds(array($request['PUCR_COMPANY_ID']));
		} else {
			throw new Exception("Unrecognised company type: {$request['PUCR_COMPANY_TYPE']}");
		}
		
		$companies = $this->makeCompanyViewData($buyers, $suppliers);
		$companies = $this->addRequestsToCompanies($companies, array($request));
		if ($companies) {
			return $companies[0];
		} else {
			throw new Exception("Unable to find company & request from request id: $reqId");
		}
	}
	
	
	private function addRequestsToCompanies(array $companies, array $requests)
	{
		$typeMap = array('v' => Shipserv_Oracle_UserCompanyRequest::COMP_TYPE_SPB,
			'b' => Shipserv_Oracle_UserCompanyRequest::COMP_TYPE_BYO);
		
		foreach ($companies as $i => $c) {
			foreach ($requests as $r) {
				if ($r['PUCR_COMPANY_TYPE'] == $typeMap[$c['type']] && $r['PUCR_COMPANY_ID'] == $c['id']) {
					$companies[$i]['joinRequest'] = $r;
				}
			}
		}
		
		return $companies;
	}
	
	
	private function makeCompanyViewData (array $buyers, array $suppliers, array $buyerAdminIds = array(), array $supplierAdminIds = array(), $consortia = array())
	{
		// Form list of companies (an abstraction over buyers & suppliers)
		$companies = array();
		
		foreach ($buyers as $b) {
			$companies[] = array(
				'id'            => $b['BYO_ORG_CODE'],
				'type'          => 'b',
				'name'          => $b['BYO_NAME'],
				'location'      => $b['BYO_CONTACT_CITY'],
				'logoUrl'       => '/images/layout_v2/default_image.gif',
				'amAdmin'       => in_array($b['BYO_ORG_CODE'], $buyerAdminIds),
				'accessCode'    => null,
				'reviewsOptout' => ($b['PCO_REVIEWS_OPTOUT'] == 'Y'),
				
				// Buyer specific
				'anonName'      => $b['PCO_ANONYMISED_NAME'] != '' ? $b['PCO_ANONYMISED_NAME'] : 'A buyer',
				'anonLocation'  => $b['PCO_ANONYMISED_LOCATION'] != '' ? $b['PCO_ANONYMISED_LOCATION'] : $this->getCountryFromCode($b['BYO_COUNTRY']),
			);
		}
		
		$acById = $this->fetchAccessCodes($suppliers);
		foreach ($suppliers as $s) {
		    $logoUrl = $s['SMALL_LOGO_URL']? $s['SMALL_LOGO_URL'] : '/images/layout_v2/default_image.gif';
		    if (preg_match('/http:\/\/.*shipserv\.com/', $logoUrl)) {
                $logoUrl = str_replace('http://', 'https://', $logoUrl);
		    }		    
			$companies[] = array(
				'id'            => $s['SPB_BRANCH_CODE'],
				'type'          => 'v',
				'name'          => $s['SPB_NAME'],
				'location'      => $s['SPB_CITY'],
				'logoUrl'       => $logoUrl,
				'amAdmin'       => in_array($s['SPB_BRANCH_CODE'], $supplierAdminIds),
				'accessCode'    => @$acById[$s['SPB_BRANCH_CODE']],
				'reviewsOptout' => false
			);
		}

        foreach ($consortia as $c) {
            $companies[] = array(
                'id'            => $c['CON_INTERNAL_REF_NO'],
                'type'          => 'c',
                'name'          => $c['CON_CONSORTIA_NAME'],
                'location'      => '',
                'logoUrl'       => '/images/layout_v2/default_image.gif',
                'amAdmin'       => null,
                'accessCode'    => null,
                'reviewsOptout' => null
            );
        }

		usort($companies, array($this, 'sortCompaniesByName'));

		return $companies;				
	}
	
	
	private function getCountryFromCode ($code)
	{
		static $countriesByCode;
		if ($countriesByCode === null) {
			$cDao = new Shipserv_Oracle_Countries($this->getActionController()->getInvokeArg('bootstrap')->getResource('db'));
			foreach ($cDao->fetchAllCountries() as $r) $countriesByCode[$r['CNT_COUNTRY_CODE']] = $r;
		}
		
		$res = (string) @$countriesByCode[$code]['CNT_NAME'];
		if ($res == '') {
			// If you want to add a default location, do it here
		}
		return $res;
	}
	
	/**
	 * Fetch assoc array of access codes by supplier id from array of supplier
	 * branch rows.
	 * 
	 * @return array
	 */
	private function fetchAccessCodes (array $supplierArr)
	{
		// Extract supplier IDs from array of arrays
		$idArr = array();
		foreach ($supplierArr as $s) $idArr[] = $s['SPB_BRANCH_CODE'];
		
		// Fetch access codes for IDs & index by ID
		$acDao = new Shipserv_Oracle_AccessCode($this->getDb());
		$acById = array();
		foreach ($acDao->fetchByTnids($idArr) as $acRow) $acById[$acRow['TNID']] = $acRow['ACCESS_CODE'];
		return $acById;
	}

    /**
     * A helper function for usort
     *
     * @param   $a
     * @param   $b
     *
     * @return  int
     */
    private function sortCompaniesByName ($a, $b) {
        if (is_array($a)) {
            $nameA = $a['name'];
        } else {
            $nameA = $a;
        }

        if (is_array($b)) {
            $nameB = $b['name'];
        } else {
            $nameB = $b;
        }

		$nameA = strtolower($nameA);
		$nameB = strtolower($nameB);

		if ($nameA === $nameB) {
            return 0;
        }

		return ($a < $b) ? -1 : 1;
	}
	
	/**
	 * Fetch supplier branch codes owned by supplied user.
	 * 
	 * @return array of int
	 */
	public function getSupplierIds (Shipserv_User $user)
	{
		$db = $this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		$ucActions = new Myshipserv_UserCompany_Actions($db, $user->userId, $user->email);
		$companies = $ucActions->fetchMyCompanies();
		return $companies->getSupplierIds();
	}
	
	/**
	 * Fetch buyer organisation codes owned by supplied user.
	 * 
	 * @return array of int
	 */
	public function getBuyerIds (Shipserv_User $user)
	{
		$db = $this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		$ucActions = new Myshipserv_UserCompany_Actions($db, $user->userId, $user->email);
		$companies = $ucActions->fetchMyCompanies();
		return $companies->getBuyerIds();
	}
	
       /**
	 * Return company details for company type and id supplied.
	 * 
	 * @param string $type 'v' for vendor, or 'b' for supplier
	 * @param int $id Branch code (for vendors), or organisation code (for buyers)
	 * 
	 * @return array Associative array of company info in the same format irrespective of type.
	 */
	public function getCompanyDetail ($type, $id)
	{
		if ($type == null)
		return false;
		
		// Fetch DB adapters
		$db = $this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		$profileDb = new Shipserv_Oracle_Profile($db);
		
		// Pull out company details from supplier / buyer
		$companyDetail = null;
		if ($type == 'b') {
			// Fetch buyer record from db
			$buyers = $profileDb->getBuyersByIds(array($id));
			if (!$buyers) {
				// "Buyer not found for org code: '$id'"
				//Zend_Controller_Action_HelperBroker::getStaticHelper('Redirector')->gotoSimple('companies', 'profile');
				throw new Myshipserv_Exception_MessagedException("Buyer TNID: " . $id . " not found");
				}
			
			$myCompDetail = $this->makeCompanyViewData(array($buyers[0]), array());
			$companyDetail = $myCompDetail[0];
		} else if ($type == 'c') {
            // Fetch buyer record from db
            $consortia = $profileDb->getConsortiaByIds(array($id));
            if (!$consortia) {
                // "Consortia company not fouund not found for org code: '$id'"
                //Zend_Controller_Action_HelperBroker::getStaticHelper('Redirector')->gotoSimple('companies', 'profile');
                throw new Myshipserv_Exception_MessagedException("Consortia TNID: " . $id . " not found");
            }

            $myCompDetail = $this->makeCompanyViewData(array($consortia[0]), array());
            $companyDetail = $myCompDetail[0];
        } else {
			// Fetch supplier record from db
			$suppliers = $profileDb->getSuppliersByIds(array($id), true);
			if (! $suppliers) {
				// "Supplier not found for branch code: '$id'"
				//Zend_Controller_Action_HelperBroker::getStaticHelper('Redirector')->gotoSimple('companies', 'profile');
				throw new Myshipserv_Exception_MessagedException("Supplier TNID: " . $id . " not found");
			}
			
			$myCompDetail = $this->makeCompanyViewData(array(), array($suppliers[0]));
			$companyDetail = $myCompDetail[0];
		}
		
		return $companyDetail;
	}
	
	/**
	 * Save privacy settings from form against supplied supplier branch code.
	 */
	public function saveSupplierForm ($branchCode, Myshipserv_Form_Endorsement_PrivacySupplier $form)
	{
		// Fetch DB adapters
		$db = $this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		$privacyDb = new Shipserv_Oracle_EndorsementPrivacy($db);
		
		$privacyDb->setSupplierPrivacy(new Myshipserv_Controller_Action_Helper_Companies_SaveableForm($form, $branchCode));
	}
	
	/**
	 * Save privacy settings from form against supplied buyer organisation code.
	 */
	public function saveBuyerForm ($orgCode, Myshipserv_Form_Endorsement_PrivacyBuyer $form)
	{
		// Fetch DB adapters
		$db = $this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		$privacyDb = new Shipserv_Oracle_EndorsementPrivacy($db);
		
		$privacyDb->setBuyerPrivacy(new Myshipserv_Controller_Action_Helper_Companies_SaveableForm($form, $orgCode));
	}
	
	/**
	 * Creates a buyer privacy form & adds supplier-specific exception
	 * list from db.
	 *
	 * @return Myshipserv_Form_Endorsement_PrivacyBuyer
	 */
	private function makeBuyerForm (Shipserv_Oracle_EndorsementPrivacy_Setting $privacy)
	{
		$form = new Myshipserv_Form_Endorsement_PrivacyBuyer();
		
		// Fetch form element representing exception list
		$exceptionEl = $form->getElement(Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_SELECTIVE_ANON);
		
		// Read exception list from privacy object & add options to form element
		// Adapter helps to interrogate privacy object
		$privacyAdapter = new Myshipserv_Controller_Action_Helper_Companies_PrivacyAdapter($privacy);
		
		$exListSupplierIds = $privacyAdapter->getExceptionList();
		$exSuppliers = $this->getSuppliersByIds($exListSupplierIds);
		foreach ($exSuppliers as $exs) {
			$exceptionEl->addMultiOption($exs['SPB_BRANCH_CODE'], $exs['SPB_NAME']);
		}
		
		return $form;
	}
	
	private function getSuppliersByIds (array $ids)
	{
		$db = $this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		$profileDb = new Shipserv_Oracle_Profile($db);
		
		$suppliers = $profileDb->getSuppliersByIds($ids);
		return $suppliers;
	}
	
	/**
	 * Creates a supplier privacy form and populates it with settings from
	 * the DB, or default.
	 *
	 * @param int $id Supplier branch code
	 * @return Myshipserv_Form_Endorsement_PrivacySupplier
	 */
	public function makeSupplierFormFromId ($id)
	{
		$form = new Myshipserv_Form_Endorsement_PrivacySupplier();
		$privacy = $this->readPrivacySetting('v', $id);
		$this->populateForm($form, $privacy);
		
		return $form;
	}
	
	/**
	 * Creates a buyer privacy form and populates it with settings from
	 * the DB, or default.
	 *
	 * @param int $id Buyer organisation code
	 * @return Myshipserv_Form_Endorsement_PrivacyBuyer
	 */
	public function makeBuyerFormFromId ($id)
	{
		$privacy = $this->readPrivacySetting('b', $id);
		$form = $this->makeBuyerForm($privacy);
		$this->populateForm($form, $privacy);
		
		return $form;
	}
	
	/**
	 * Fetch privacy obj from db.
	 *
	 * @param string $type 'v' for supplier, 'b' for buyer
	 * @param int $id Supplier branch code, or buyer organisation code
	 * 
	 * @return Shipserv_Oracle_EndorsementPrivacy
	 */
	private function readPrivacySetting ($type, $id)
	{
		// Fetch DB adapters
		$db = $this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		$privacyDb = new Shipserv_Oracle_EndorsementPrivacy($db);
		
		// Read privacy object
		$privacy = ($type == 'b' ? $privacyDb->getBuyerPrivacy($id) : $privacyDb->getSupplierPrivacy($id));
		
		return $privacy;
	}
	
	/**
	 * Populates form fields from privacy setting object. Works for
	 * supplier and buyer forms.
	 *
	 * @return null
	 */
	private function populateForm (Myshipserv_Form_Endorsement_PrivacyAbstract $form, Shipserv_Oracle_EndorsementPrivacy_Setting $privacy)
	{
		// Help interrogate privacy object
		$privacyAdapter = new Myshipserv_Controller_Action_Helper_Companies_PrivacyAdapter($privacy);
		
		// Map privacy states to form settings
		$globalMap = array(
			Myshipserv_Controller_Action_Helper_Companies_PrivacyAdapter::GLOBAL_ANON_ON => Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON_ON,
			Myshipserv_Controller_Action_Helper_Companies_PrivacyAdapter::GLOBAL_ANON_OFF => Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON_OFF,
			Myshipserv_Controller_Action_Helper_Companies_PrivacyAdapter::GLOBAL_ANON_OFF_EXCEPT => Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON_SELECT,
			Myshipserv_Controller_Action_Helper_Companies_PrivacyAdapter::GLOBAL_ANON_TN_ONLY => Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON_TN_ONLY,
		);
		
		$settings = array();
		
		// Set selection for global anonymization policy
		$settings[Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON] = @$globalMap[$privacyAdapter->getGlobalState()];
		
		// Set selections for exception list (all set if present)
		$settings[Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_SELECTIVE_ANON] = $privacyAdapter->getExceptionList();
		
		// Populate form
		$form->populate($settings);
	}
}
