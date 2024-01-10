<?php
/**
 * Membership Authorisation
 *
 * @author Uladzimir Maroz
 */
class Shipserv_MembershipAuthorisation
{
	/**
	 * @var int
	 */
	public $companyId;

	/**
	 * @var int
	 */
	public $membershipId;

	/**
	 * @var bool
	 */
	public $isAuthorised;

	/**
	 * @var null
	 */
	public $certificateNumber;

	/**
	 * @var null
	 */
	public $expiryDate;

	/**
	 * @var
	 */
	protected static $daoAdapter;

	/**
	 * @param   int     $companyId
	 * @param   int     $membershipId
	 * @param   bool    $isAuthorised
	 * @param   null    $certificateNumber
	 * @param   null    $expiryDate
	 */
	public function __construct($companyId, $membershipId, $isAuthorised, $certificateNumber = null, $expiryDate = null)
	{
		$this->companyId         = $companyId;
		$this->membershipId      = $membershipId;
		$this->isAuthorised      = $isAuthorised;
		$this->certificateNumber = $certificateNumber;
		$this->expiryDate        = $expiryDate;
	}

	/**
	 *
	 * @param   int     $companyId
	 * @param   int     $membershipId
	 * @param   bool    $isAuthorised
	 *
	 * @return  Shipserv_MembershipAuthorisation
	 */
	public static function fetch($companyId, $membershipId, $isAuthorised)
	{
		$isAuth = null;
		if ($isAuthorised === true) $isAuth = "Y";
		if ($isAuthorised === false) $isAuth = "N";

		if ($result = self::getDao()->fetch(
			array(
				'SM_SUP_BRANCH_CODE'    => $companyId,
				'SM_QO_ID'              => $membershipId,
				'SM_IS_AUTHORISED'      => $isAuth
			)
		)) {
			return self::createObjectFromDBRow($result[0]);
		}

		return null;
	}

	/**
	 * @param   array   $filters
	 *
	 * @return array
	 */
	public static function search($filters = array())
	{
		if ($result = self::getDao()->fetch($filters)) {
			if (count($result) > 0) {
				$results = array();
				foreach ($result as $resultRow) {
					$results[] = self::createObjectFromDBRow($resultRow);
				}

				return $results;
			}

			return null;
		}

		return null;
	}

	/**
	 * Creates Membership Authorisation
	 *
	 * @param   int     $companyId
	 * @param   int     $membershipId
	 * @param   bool    $isAuthorised
	 * @param   null    $certificateNumber
	 * @param   null    $expiryDate
	 *
	 * @return Shipserv_MembershipAuthorisation
	 */
	public static function create($companyId, $membershipId, $isAuthorised, $certificateNumber = null, $expiryDate = null)
	{

		$isAuth = null;
		if ($isAuthorised === true) $isAuth = "Y";
		if ($isAuthorised === false) $isAuth = "N";

		self::getDao()->store($companyId, $membershipId, $isAuth, $certificateNumber, $expiryDate);

		return new self($companyId, $membershipId, $isAuth, $certificateNumber, $expiryDate);
	}

	/**
	 * Removes a Membership Authorisation
	 *
	 * @return  bool
	 */
	public function remove()
	{
		return self::getDao()->remove(
			array(
				'SM_SUP_BRANCH_CODE' => $this->membershipId,
				'SM_QO_ID'           => $this->companyId,
				'SM_IS_AUTHORISED'   => $this->isAuthorised
			)
		);
	}

	/**
	 * Authorises the request
	 *
	 * @return  bool
	 */
	public function authorise()
	{
		$this->isAuthorised = 'Y';
		return self::getDao()->authorise($this->companyId, $this->membershipId, $this->isAuthorised);
	}

	/**
	 * @return Zend_Db_Adapter_Oracle
	 */
	protected static function getDb()
	{
		return Shipserv_Helper_Database::getDb();
	}

	/**
	 * @return Shipserv_Oracle_MembershipAuthorisation
	 */
	protected static function getDao()
	{
		if (!self::$daoAdapter) {
			self::$daoAdapter = new Shipserv_Oracle_MembershipAuthorisation(self::getDb());
		}

		return self::$daoAdapter;
	}

	/**
	 * Instantiate class using array returned from DB adapter
	 *
	 * @param   array   $dbRow
	 * @return  Shipserv_MembershipAuthorisation
	 */
	public static function createObjectFromDBRow($dbRow)
	{
		return new Shipserv_MembershipAuthorisation(
			$dbRow['SM_SUP_BRANCH_CODE'],
			$dbRow['SM_QO_ID'],
			$dbRow['SM_IS_AUTHORISED'],
			$dbRow['SM_CERTIFICATE_NUMBER'],
			$dbRow['SM_EXPIRY_DATE']
		);
	}

	/**
	 * Should be rewritten to return proper company object later
	 *
	 * @return array
	 */
	public function getCompanyInfo()
	{
		// $profileDao = new Shipserv_Oracle_Profile(self::getDb());
        // $company = $profileDao->getSuppliersByIds(array($this->companyId));

        // changed by Yuriy Akopov on 2016-11-23, DE7116
        $spbDao = new Shipserv_Oracle_Suppliers(self::getDb());
        $company = $spbDao->fetchSuppliersByIds(array($this->companyId));

		$countriesDAO = new Shipserv_Oracle_Countries(self::getDb());
		$country = $countriesDAO->fetchCountryByCode($company[0]['SPB_COUNTRY']);

		if (count($country) == 1) {
			$company[0]['CNT_NAME']     = $country[0]['CNT_NAME'];
			$company[0]['CNT_CON_CODE'] = $country[0]['CNT_CON_CODE'];
		}

		return $company[0];
	}

	/**
	 * @return array
	 */
	public function getMembershipInfo()
	{
		$membershipDao = new Shipserv_Oracle_Memberships(self::getDb());
		$membership = $membershipDao->fetch($this->membershipId);

		return $membership;
	}

	/**
	 * Get all outstanding requests by membership
	 *
	 * @param integer $membershipId
	 * @return Shipserv_MembershipAuthorisation[]
	 */
	public static function getRequests($membershipId)
	{
		$requests = array ();

		foreach (self::getDao()->fetch(
			array(
				'SM_QO_ID'         => $membershipId,
				'SM_IS_AUTHORISED' => 'N'
			)
		) as $requestRow) {
			$requests[] = self::createObjectFromDBRow($requestRow);
		}

		return $requests;
	}

	/**
	 * Retrieve list of authorisations granted for given membership
	 *
	 * @param   int     $membershipId
	 * @param   int     $page
	 * @param   string  $region
	 * @param   string  $name
	 *
	 * @return Shipserv_MembershipAuthorisation[]
	 */
	public static function getAuthorisations($membershipId, $page = 0, $region = "", $name = "")
	{
		$authRows = self::getDao()->fetchAuths(array('SM_QO_ID' => $membershipId), $page, $region, $name);

		$authorisations = array();
		foreach ($authRows as $authRow) {
			$authorisations[] = self::createObjectFromDBRow($authRow);
		}

		return $authorisations;
	}

	/**
	 * Retrieve count of authorisations granted for given membership
	 *
	 * @param   int     $membershipId
	 * @param   string  $region
	 * @param   string  $name
	 *
	 * @return  int
	 */
	public static function getAuthorisationsCount($membershipId, $region = "", $name = "")
	{
		$authorisations = self::getDao()->fetchAuths(array('SM_QO_ID' => $membershipId), 1, $region, $name, 1);

		if (count($authorisations) > 0) {
			return $authorisations[0]['TOTALCOUNT'];
		}

		return 0;
	}

	/**
	 * Retrieve all company authorisations
	 *
	 * @param   int $companyId
	 *
	 * @return  array
	 */
	public static function getSupplierAuthorisations($companyId)
	{
		$authRows = self::getDao()->fetchAuths(
			array(
				'SM_SUP_BRANCH_CODE' => $companyId,
				'SM_IS_AUTHORISED'   => 'Y'
			)
		);

		$authorisations = array();
		foreach ($authRows as $authRow) {
			$authorisations[] = self::createObjectFromDBRow($authRow);
		}

		return $authorisations;

	}

	/**
	 *	Retrieve all company requests for specific membership
	 *
	 * @param   int   $companyId
	 * @param   int   $membershipId
	 *
	 * @return  array
	 */
	public static function getCompanyRequestsForMembership($companyId, $membershipId)
	{
		$authRows = self::getDao()->fetch(
			array(
				'SM_SUP_BRANCH_CODE' => $companyId,
				'SM_QO_ID'           => $membershipId,
				'SM_IS_AUTHORISED'   => 'N'
			)
		);

		$requests = array ();
		foreach ($authRows as $authRow) {
			$requests[] = self::createObjectFromDBRow($authRow);
		}

		return $requests;
	}

	/**
	 * Retrieve memberships that company is allowed to manage
	 *
	 * @param   int $companyId
	 *
	 * @return  array
	 */
	public static function getOwnedMemberships($companyId)
	{
		$membershipsDAO = new Shipserv_Oracle_Memberships(self::getDb());
		$membershipOwnersDao = new Shipserv_Oracle_MembershipOwners(self::getDb());

		$membershipRows = $membershipOwnersDao->fetch(array('PMO_COMPANY_ID' => $companyId));

		$memberships = array ();
		foreach ($membershipRows as $membershipRow) {
			$memberships[$membershipRow['PMO_QO_ID']] = $membershipsDAO->fetch($membershipRow['PMO_QO_ID']);
		}

		return $memberships;
	}

	/**
	 * Retrieve list of membership editors
	 *
	 * @param   int $membershipId
	 *
	 * @return array
	 */
	public static function getMembershipOwners($membershipId)
	{
		$membershipOwnersDao = new Shipserv_Oracle_MembershipOwners(self::getDb());
		$membershipRows = $membershipOwnersDao->fetch(array('PMO_QO_ID' => $membershipId));

		$membershipOwners = array ();
		foreach ($membershipRows as $membershipOwnerRow) {
			$membershipOwners[] = $membershipOwnerRow['PMO_COMPANY_ID'];
		}

		return $membershipOwners;
	}

	/**
	 * Remove all requests by company for specific membership authorisation
	 *
	 * @param   int     $companyId
	 * @param   int     $membershipId
	 *
	 * @return  bool
	 */
	public static function removeCompanyRequestsForMembership($companyId, $membershipId)
	{
		return self::getDao()->remove(
			array(
				'SM_QO_ID'           => $membershipId,
				'SM_SUP_BRANCH_CODE' => $companyId,
				'SM_IS_AUTHORISED'   => 'N'
			)
		);
	}

	/**
	 * @param   int     $companyId
	 * @param   int     $membershipId
	 *
	 * @return  bool
	 */
	public static function removeCompanyAuthsForMembership($companyId, $membershipId)
	{
		self::getDao()->remove(
			array(
				'SM_QO_ID'           => $membershipId,
				'SM_SUP_BRANCH_CODE' => $companyId,
				'SM_IS_AUTHORISED'   => 'Y'
			)
		);

		self::getDao()->remove(
			array(
				'SM_QO_ID'           => $membershipId,
				'SM_SUP_BRANCH_CODE' => $companyId,
				'SM_IS_AUTHORISED'   => null
			)
		);

		return true;
	}
}
