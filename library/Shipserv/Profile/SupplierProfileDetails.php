<?php
/**
* This class returns the details,
* used by SPR report, supplier profile page
*/
class Shipserv_Profile_SupplierProfileDetails
{
	private static $_instance;
	
	/**
	 * Singleton class entry point, create single instance
	 * @return object
	 */
	public static function getInstance()
	{
		if (null === static::$_instance) {
			static::$_instance = new static();
		}
	
		return static::$_instance;
	}
	
	/**
	 * Protected classes to prevent creating a new instance
	 * @return object
	 */
	protected function __construct()
	{
	}
	
	/**
	 * Hide clone, protect createing another instance
	 * @return unknown
	 */
	private function __clone()
	{
	}
	
	/**
	 * Return the supplier detais in an array
	 * @param integer $spbBranchCode
	 * @return array
	 */
	public function getSupplierDetails($spbBranchCode)
	{
		$result = array();
		
		$supplier = Shipserv_Supplier::getInstanceById($spbBranchCode, "", true);
		
		if ($supplier->tnid !== null) {
			$exporableParams = array(
				'tnid',
				'name',
				'description',
				'address1',
			    'address2',
			    'city',
			    'state',
			    'zipCode',
			    'countryCode',
			    'countryName',
			    'logoUrl',
			    'categories',
			    'brands',
			    'ports',
			    'tradeRank',
			    'latitude',
		    	'longitude',
		    	'joinedDate',
				'contacts',
				'certifications'
	    	);

			foreach ($exporableParams as $paramName) {
				$result[$paramName] = $supplier->$paramName;
			}
			
			if (!$result['logoUrl']) {
				$result['logoUrl'] = '/images/profile/no_logo_placeholder.svg';
			}

		   	$endorseeList = $this->getEndorsmentInfo($spbBranchCode);
		   	$result['tradedWith'] = $endorseeList; 
		   	$result['fullAddress'] = $supplier->getAddress(true, true);
			
		   	$helper = new Myshipserv_View_Helper_SupplierProfileUrl();
		   	$result['supplierProfileUrl'] = $helper->supplierProfileUrl($supplier);
		}
		
		return $result;
	}
	
	/**
	 * This will return a list of buyers the supplier traded with, considering if the name is anonymised
	 * This was requested by Stuart to use the same logic as in the supplier profile review, so the code was picked
	 * from there, ReviewsController and supplier.phtml and refactored for this use
	 * 
	 * @param integer $tnid The Supplier TNID
	 * @return array
	 */
	protected function getEndorsmentInfo($tnid)
	{
		$result = array();
		$message = '';
		
		$allowSuppliersToList = true;
		
		$user = Shipserv_User::isLoggedIn();
		
		if (is_object($user)) {
			$userSuppliers = $user->fetchCompanies()->getSupplierIds();
			$userBuyers = $user->fetchCompanies()->getBuyerIds();
		} else {
			$userSuppliers = array();
			$userBuyers    = array();
		}
		
		$db = Shipserv_Helper_Database::getDb();
	
		//$profileDao = new Shipserv_Oracle_Profile($db);
		//$endorsee = $profileDao->getSuppliersByIds(array($tnid));
		//$endorseeInfo =  $endorsee[0];
		
		//retrieve list of endorsements for given supplier
		$endorsementsAdapter = new Shipserv_Oracle_Endorsements($db);
		$endorsements = $endorsementsAdapter->fetchEndorsementsByEndorsee($tnid, false);
		$endorseeIdsArray = array ();
		foreach ($endorsements as $endorsement) {
			$endorseeIdsArray[] = $endorsement["PE_ENDORSER_ID"];
		}
		
		$userEndorsementPrivacy = $endorsementsAdapter->showBuyers($tnid, $endorseeIdsArray);
		
		//get supplier's privacy policy
		$dbPriv = new Shipserv_Oracle_EndorsementPrivacy($db);
		$sPrivacy = $dbPriv->getSupplierPrivacy($tnid);
		
		
		if ($sPrivacy->getGlobalAnonPolicy() === Shipserv_Oracle_EndorsementPrivacy::ANON_YES and !(in_array($tnid, $userSuppliers))) {
			$message = 'This supplier has elected to keep their customer names anonymous';
			$allowSuppliersToList = false;
		}
		
		$endorsementList = array (
				"hasReviews" => array (),
				"noReviews"	=> array (
						"anonimized"	=> array (5=>array(),4=>array(),3=>array(),2=>array(),1=>array(),0=>array()),
						"notAnonimized"	=> array (5=>array(),4=>array(),3=>array(),2=>array(),1=>array(),0=>array())
				)
		);
		
		foreach ($endorsements as $endorsement) {
			if ($endorsement["PERSENDCOUNT"]>0) {
				$endorsementList["hasReviews"][] = $endorsement;
			} else {
				
				if ($endorsement["PE_DAYS_TRADED"] >= 182) {
					$frequencyBand = 5;
				} elseif ($endorsement["PE_WEEKS_TRADED"] >= 26) {
					$frequencyBand = 4;
				} elseif ($endorsement["PE_MONTHS_TRADED"] >= 6) {
					$frequencyBand = 3;
				} elseif ($endorsement["PE_ORDERS_NUM"] >= 2) {
					$frequencyBand = 2;
				} elseif ($endorsement["PE_ORDERS_NUM"] == 1) {
					$frequencyBand = 1;
				} else {
					$frequencyBand = 0;
				}
				
				$endorsementList["noReviews"][($userEndorsementPrivacy[$endorsement["PE_ENDORSER_ID"]]===true or in_array($endorsement["PE_ENDORSER_ID"], $userBuyers) or in_array($tnid, $userSuppliers))?"notAnonimized":"anonimized"][$frequencyBand][] = $endorsement;
			}
		}
		
		$tmpArray = array ();
		foreach ($endorsementList["noReviews"]["notAnonimized"] as $subArray) {
			$tmpArray = array_merge($tmpArray, $subArray);
		}
		$endorsementList["noReviews"]["notAnonimized"] = $tmpArray;
		
		$tmpArray = array ();
		foreach ($endorsementList["noReviews"]["anonimized"] as $subArray) {
			$tmpArray = array_merge($tmpArray, $subArray);
		}
		
		$endorsementList["noReviews"]["anonimized"] = $tmpArray;
		$endorsementDisplayList = array_merge($endorsementList["hasReviews"], $endorsementList["noReviews"]["notAnonimized"], $endorsementList["noReviews"]["anonimized"]);


		foreach ($endorsementDisplayList as $endorsement) {
			if ($allowSuppliersToList === true && ($userEndorsementPrivacy[$endorsement["PE_ENDORSER_ID"]]===true or in_array($endorsement["PE_ENDORSER_ID"], $userBuyers) or in_array($tnid, $userSuppliers))) {
				array_push($result, $endorsement["BYO_NAME"]);
			} else {
				if ($endorsement["PCO_ANONYMISED_NAME"]) {
					array_push($result, $endorsement["PCO_ANONYMISED_NAME"]);
				} else {
					array_push($result, "A Buyer");
				}
			}
		}
		
		return array(
				'message' => $message,
				'tradeCount' => count($endorsementDisplayList),
				'names' => $result
				);
	}
}
