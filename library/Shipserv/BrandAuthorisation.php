<?php
/**
 * Brand Authorisation
 *
 * Refactored by Yuriy Akopov on 2016-08-25, DE6813
 *
 * @author Uladzimir Maroz
 */
class Shipserv_BrandAuthorisation
{
	const
		AUTH_LEVEL_OWNER              = 'OWN',
		AUTH_LEVEL_AGENT              = 'AGT',
		AUTH_LEVEL_INSTALLER_REPAIRER = 'REP',
		AUTH_LEVEL_OEM                = 'OEM',
		AUTH_LEVEL_LISTED             = 'LST'
	;

	const
		AUTH_YES = 'Y',
		AUTH_NO  = 'N'
	;

	/**
	 * @var array
	 */
	public static $displayAuthNames = array(
		self::AUTH_LEVEL_OWNER              => "Owner",
		self::AUTH_LEVEL_AGENT              => "Authorised Agent",
		self::AUTH_LEVEL_INSTALLER_REPAIRER => "Authorised Installer/Repairer",
		self::AUTH_LEVEL_OEM                => "Certified Genuine/Original Spares"
	);

	/**
	 * @var array
	 */
	public static $facetAuthNames = array(
		'AABrandId'  => "Authorised Agent",
		'AIRBrandId' => "Authorised Installer/Repairer",
		'OEMBrandId' => "Certified Genuine/Original Spares"
	);

	/**
	 * @var int
	 */
	protected $companyId;

	/**
	 * @var int
	 */
	protected $brandId;

	/**
	 * @var string
	 */
	protected $authLevel;

	/**
	 * @var bool
	 */
	protected $isAuthorised;

	/**
	 * @var
	 */
	protected $dateRequested;

	/**
	 * @var Shipserv_Oracle_BrandAuthorisation
	 */
	protected static $daoAdapter;

	/**
	 * Comment by Yuriy Akopov:
	 * As I understand the purpose of this method, this is to allow blanket reading of protected fields, but not writing
	 *
	 * Magic method to access object's fields
	 *
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function __get($name)
	{
		return $this->{$name};
	}

	/**
	 * @param   int     $companyId
	 * @param   int     $brandId
	 * @param   string  $authLevel
	 * @param   bool    $isAuthorised
	 */
	public function __construct($companyId, $brandId, $authLevel, $isAuthorised)
	{
		$this->companyId = $companyId;
		$this->brandId = $brandId;
		$this->authLevel = $authLevel;
		$this->isAuthorised = $isAuthorised;
	}

	/**
	 *
	 * @param   int     $companyId
	 * @param   int     $brandId
	 * @param   string  $authLevel
	 * @param   bool    $isAuthorised
	 *
	 * @return  Shipserv_BrandAuthorisation
	 */
	public static function fetch($companyId, $brandId, $authLevel, $isAuthorised)
	{
		$isAuth = null;

		if ($isAuthorised === true) {
			$isAuth = 'Y';
		} else if ($isAuthorised === false) {
			$isAuth = 'N';
		} else {
			// @todo: should we throw an Exception (Yuriy Akopov after reformatting)
		}

		$result = self::_getDao()->fetch(
			array(
				"PCB_COMPANY_ID"    => $companyId,
				"PCB_BRAND_ID"      => $brandId,
				"PCB_AUTH_LEVEL"    => $authLevel,
				"PCB_IS_AUTHORISED" => $isAuth
			)
		);

		if ($result) {
			return self::createObjectFromDBRow($result[0]);
		}
		return null;
	}

	/**
	 * Retrieves and instantiates brand authentication records that fit the provided criteria
	 *
	 * @param   array   $filters
	 *
	 * @return  Shipserv_BrandAuthorisation[]
	 */
	public static function search(array $filters = array())
	{
		if ($result = self::_getDao()->fetch($filters)) {
			if (count($result) > 0) {
				$results = array();

				foreach ($result as $resultRow) {
					$results[] = self::createObjectFromDBRow($resultRow);
				}

				return $results;
			}
		}

		return null;
	}

	/**
	 * @param   int     $companyId
	 * @param   int     $brandId
	 * @param   string  $authLevel
	 * @param   bool    $isAuthorised
	 *
	 * @return  Shipserv_BrandAuthorisation
	 */
	public static function create($companyId, $brandId, $authLevel, $isAuthorised)
	{
		$isAuth = null;
		if ($isAuthorised === true) {
			$isAuth = self::AUTH_YES;
		} else if ($isAuthorised === false) {
			$isAuth = self::AUTH_NO;
		} else {
			// @todo: should we throw an Exception (Yuriy Akopov after reformatting)
		}

		self::_getDao()->store($companyId, $brandId, $authLevel, $isAuth);

		return new self($companyId, $brandId, $authLevel, $isAuth);
	}

	/**
	 * @param bool $permanently
	 */
	public function remove($permanently = false)
	{
		self::_getDao()->remove(
			array(
				'PCB_BRAND_ID'      => $this->brandId,
				'PCB_COMPANY_ID'    => $this->companyId,
				'PCB_AUTH_LEVEL'    => $this->authLevel,
				'PCB_IS_AUTHORISED' => $this->isAuthorised
			),
			$permanently
		);
	}

	/**
	 *
	 */
	public function authorise()
	{
		$this->isAuthorised = 'Y';
		self::_getDao()->authorise($this->companyId, $this->brandId, $this->authLevel, $this->isAuthorised);
	}

	/**
	 *
	 */
	public function deauthorise()
	{
		$this->isAuthorised = 'N';
		self::_getDao()->deauthorise($this->companyId, $this->brandId, $this->authLevel, $this->isAuthorised);
	}

	/**
	 * @return Zend_Db_Adapter_Oracle
	 */
	private static function _getDb()
	{
		// return $GLOBALS["application"]->geBootstrap()->getResource('db');
		return Shipserv_Helper_Database::getDb();
	}

	/**
	 *
	 * @return Shipserv_Oracle_BrandAuthorisation
	 */
	private static function _getDao()
	{
		if (!self::$daoAdapter) {
			self::$daoAdapter = new Shipserv_Oracle_BrandAuthorisation(self::_getDb());
		}

		return self::$daoAdapter;
	}

	/**
	 * Instantiate class using array returned from DB adapter
	 *
	 * @param   array   $dbRow
	 *
	 * @return  Shipserv_BrandAuthorisation
	 */
	public static function createObjectFromDBRow($dbRow)
	{
		return new Shipserv_BrandAuthorisation(
			$dbRow['PCB_COMPANY_ID'],
			$dbRow['PCB_BRAND_ID'],
			$dbRow['PCB_AUTH_LEVEL'],
			$dbRow['PCB_IS_AUTHORISED']
		);
	}

	/**
	 * Should be rewritten to return proper company object later
	 * @param bool $skipCheck Do not check against valid, published supplier flags
	 * @return array
	 */
	public function getCompanyInfo($skipCheck = false)
	{
		$profileDao = new Shipserv_Oracle_Profile(self::_getDb());
		$company = $profileDao->getSuppliersByIds(array($this->companyId), $skipCheck);

		$coutriesDAO = new Shipserv_Oracle_Countries(self::_getDb());
		$country = $coutriesDAO->fetchCountryByCode($company[0]['SPB_COUNTRY']);

		if (count($country) == 1) {
			$company[0]['CNT_NAME'] = $country[0]['CNT_NAME'];
			$company[0]['CNT_CON_CODE'] = $country[0]['CNT_CON_CODE'];
		}

		return $company[0];
	}

	/**
	 * @return array
	 */
	public function getBrandInfo()
	{
		$brandDao = new Shipserv_Oracle_Brands(self::_getDb());
		$brand = $brandDao->fetch($this->brandId);

		return $brand;
	}

	/**
	 * @return string
	 */
	public function getAuthLevelDisplayName()
	{
		return self::$displayAuthNames[$this->authLevel];
	}

	/**
	 * Get authLevel description by using the key
	 *
	 * @param   string  $input example: REP, OEM, LST
	 * @return  string eg: authorised agent
	 * @author Elvir <eleonard@shipserv.com>
	 */
	public static function getAuthLevelKeyByName($input)
	{
		foreach (self::$displayAuthNames as $key => $name) {
			if ($input == $name) {
				return $key;
			}
		}

		return false;
	}

	/**
	 * @param   string  $input
	 *
	 * @return  bool|string
	 */
	public static function getAuthLevelNameByKey($input)
	{
		foreach (self::$displayAuthNames as $key => $name) {
			if ($key == $input) {
				return $name;
			}
		}

		return false;
	}

	/**
	 * Get all outstanding requests by brand
	 *
	 * @param   int     $brandId
	 *
	 * @return  Shipserv_BrandAuthorisation[]
	 */
	public static function getRequests($brandId)
	{
		$records = self::_getDao()->fetch(
			array(
				'PCB_BRAND_ID'      => $brandId,
				'PCB_IS_AUTHORISED' => 'N',
				'PCB_AUTH_LEVEL'	=> array(
					self::AUTH_LEVEL_AGENT,
					self::AUTH_LEVEL_INSTALLER_REPAIRER,
					self::AUTH_LEVEL_OEM
				)
			)
		);

		$requests = array ();
		foreach ($records as $requestRow) {
			$requests[] = self::createObjectFromDBRow($requestRow);
		}

		return $requests;
	}

	/**
	 * Retrieve list of authorisations granted for given brand
	 *
	 * @param int $brandId
	 * @return Shipserv_BrandAuthorisation[]
	 */
	public static function getAuthorisations($brandId)
	{
		$records = self::_getDao()->fetch(
			array(
				'PCB_BRAND_ID'      => $brandId,
				'PCB_IS_AUTHORISED' => 'Y',
				'PCB_AUTH_LEVEL'	=> array(
					self::AUTH_LEVEL_AGENT,
					self::AUTH_LEVEL_INSTALLER_REPAIRER,
					self::AUTH_LEVEL_OEM
				)
			)
		);

		$authorisations = array ();
		foreach ($records as $authRow) {
			$authorisations[] = self::createObjectFromDBRow($authRow);
		}

		return $authorisations;
	}

	/**
	 * Changed and refactored by Yuriy Akopov on 2016-08-25, DE6813
	 *
	 * @param   int     $companyId
	 * @param   int     $brandId
	 *
	 * @return Shipserv_BrandAuthorisation[]
	 */
	public static function getCompanyBrandAuthLevels($companyId, $brandId)
	{
		$records = self::_getDao()->fetch(
			array(
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_BRAND_ID'      => $brandId,
				'PCB_AUTH_LEVEL'    => array(
					self::AUTH_LEVEL_AGENT,
					self::AUTH_LEVEL_INSTALLER_REPAIRER,
					self::AUTH_LEVEL_OEM,
					self::AUTH_LEVEL_LISTED
				)
			)
		);

		$all = array();
		foreach ($records as $authRow) {
			$all[] = self::createObjectFromDBRow($authRow);
		}

		return $all;
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
		$records = self::_getDao()->fetch(
			array(
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_IS_AUTHORISED' => 'Y',
				'PCB_AUTH_LEVEL'	=> array(
					self::AUTH_LEVEL_AGENT,
					self::AUTH_LEVEL_INSTALLER_REPAIRER,
					self::AUTH_LEVEL_OEM
				)
			)
		);

		$authorisations = array ();
		foreach ($records as $authRow) {
			$authorisations[] = self::createObjectFromDBRow($authRow);
		}

		return $authorisations;
	}

	/**
	 * Retrieve all company requests for specific brand
	 *
	 * @param   int $companyId
	 * @param   int $brandId
	 *
	 * @return  array
	 */
	public static function getCompanyRequestsForBrand($companyId, $brandId)
	{
		$records = self::_getDao()->fetch(
			array(
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_BRAND_ID'      => $brandId,
				'PCB_IS_AUTHORISED' => 'N',
				'PCB_AUTH_LEVEL'	=> array(
					self::AUTH_LEVEL_AGENT,
					self::AUTH_LEVEL_INSTALLER_REPAIRER,
					self::AUTH_LEVEL_OEM
				)
			)
		);

		$requests = array ();
		foreach ($records as $authRow) {
			$requests[] = self::createObjectFromDBRow($authRow);
		}

		return $requests;
	}

	/**
	 * @param   int $companyId
	 * @param   int $brandId
	 *
	 * @return array
	 */
	public static function getCompanyRequestForBrandOwnership($companyId, $brandId)
	{
		$records = self::_getDao()->fetch(
			array(
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_BRAND_ID'      => $brandId,
				'PCB_IS_AUTHORISED' => 'N',
				'PCB_AUTH_LEVEL'	=> array(
					self::AUTH_LEVEL_OWNER
				)
			)
		);

		$requests = array ();
		foreach ($records as $authRow) {
			$requests[] = self::createObjectFromDBRow($authRow);
		}

		return $requests;
	}

	/**
	 * Retrieve brands that company is allowed to manage
	 *
	 * @param   int     $companyId
	 *
	 * @return  array
	 */
	public static function getManagedBrands($companyId)
	{
		$brands = array();
		$brandsDAO = new Shipserv_Oracle_Brands(self::_getDb());

		$records = self::_getDao()->fetch(
			array(
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_AUTH_LEVEL'    => self::AUTH_LEVEL_OWNER,
				'PCB_IS_AUTHORISED' => 'Y'
			)
		);

		foreach ($records as $brandRow) {
			$brands[$brandRow['PCB_BRAND_ID']] = $brandsDAO->fetch($brandRow['PCB_BRAND_ID']);
		}

		return $brands;
	}

	/**
	 * @param   int $brandId
	 *
	 * @return  array
	 */
	public static function getBrandRequestsByCompany($brandId)
	{
		$requests = Shipserv_BrandAuthorisation::getRequests($brandId);

		$requestsByCompany = array ();
		foreach ($requests as $request) {
			if (!isset($requestsByCompany[$request->companyId])) {
				$requestsByCompany[$request->companyId] = array();
			}

			$requestsByCompany[$request->companyId][] = $request;
		}

		return $requestsByCompany;
	}

	/**
	 * Retrieve list of brand owners
	 *
	 * @param   int     $brandId
	 * @param   bool    $useCache
	 *
	 * @return array
	 */
	public static function getBrandOwners($brandId, $useCache = false)
	{
		$records = self::_getDao()->fetch(
			array(
				'PCB_BRAND_ID'      => $brandId,
				'PCB_AUTH_LEVEL'    => self::AUTH_LEVEL_OWNER,
				'PCB_IS_AUTHORISED' => self::AUTH_YES
			),
			0, 20, $useCache
		);

		$brandOwners = array ();
		foreach ($records as $rec) {
			$brandOwners[] = $rec['PCB_COMPANY_ID'];
		}

		return $brandOwners;
	}

	/**
	 * Retrieve list of passive brand owners with or without given brandId
	 *
	 * @param   int     $brandId
	 * @param   bool    $returnOnlyCompanyId
	 *
	 * @return array
	 */
	public static function getPassiveBrandOwners($brandId = null, $returnOnlyCompanyId = false)
	{
		$brandOwners = array();

		// if brandId specified, then return all auth request for that particular brandId
		if (strlen($brandId)) {
			$brandOwnerRows = self::_getDao()->fetch(
				array(
					'PCB_BRAND_ID'      => $brandId,
					'PCB_AUTH_LEVEL'    => self::AUTH_LEVEL_OWNER,
					'PCB_IS_AUTHORISED' => 'N'
				),
				0, 20, true
			);
		} else {
			// if not, then return all de-authorise brand auth request
			$brandOwnerRows = self::_getDao()->fetch(
				array(
					'PCB_AUTH_LEVEL'    => self::AUTH_LEVEL_OWNER,
					'PCB_IS_AUTHORISED' => 'N'
				)
			);
		}

		foreach ($brandOwnerRows as $brandOwnerRow) {
			if ($returnOnlyCompanyId === true) {
				$brandOwners[] = $brandOwnerRow['PCB_COMPANY_ID'];
			} else {
				$brandOwners[] = array(
					'companyId' => $brandOwnerRow['PCB_COMPANY_ID'],
					'brandId' => $brandOwnerRow['PCB_BRAND_ID']
				);
			}
		}

		return $brandOwners;
	}

	/**
	 * Remove all requests by company for specific brand authorisation
	 *
	 * @param   int   $companyId
	 * @param   int   $brandId
	 *
	 * @return  bool
	 */
	public static function removeCompanyRequestsForBrand($companyId, $brandId)
	{
		return self::_getDao()->remove(
			array(
				'PCB_BRAND_ID'      => $brandId,
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_IS_AUTHORISED' => 'N',
				'PCB_AUTH_LEVEL'	=> array(
					self::AUTH_LEVEL_AGENT,
					self::AUTH_LEVEL_INSTALLER_REPAIRER,
					self::AUTH_LEVEL_OEM,
					self::AUTH_LEVEL_LISTED
				)
			), true
		);
	}

	/**
	 * @param   int     $companyId
	 * @param   int     $brandId
	 * @param   bool    $permanently
	 *
	 * @return bool
	 */
	public static function removeCompanyAuthsForBrand($companyId, $brandId, $permanently = false)
	{
		self::_getDao()->remove(
			array(
				'PCB_BRAND_ID'      => $brandId,
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_IS_AUTHORISED' => 'Y',
				'PCB_AUTH_LEVEL'	=> array(
					self::AUTH_LEVEL_AGENT,
					self::AUTH_LEVEL_INSTALLER_REPAIRER,
					self::AUTH_LEVEL_OEM,
					self::AUTH_LEVEL_LISTED
				)
			), $permanently
		);

		self::_getDao()->remove(
			array(
				'PCB_BRAND_ID'      => $brandId,
				'PCB_COMPANY_ID'    => $companyId,
				'PCB_IS_AUTHORISED' => null,
				'PCB_AUTH_LEVEL'	=> array(
					self::AUTH_LEVEL_AGENT,
					self::AUTH_LEVEL_INSTALLER_REPAIRER,
					self::AUTH_LEVEL_OEM,
					self::AUTH_LEVEL_LISTED
				)
			), true
		);

		return true;
	}

	/**
	 * Returns true is the given Auth level is not an exception 'Listing' type
	 *
	 * A note by Yuriy Akopov, 2014-09-17, DE5017
	 *
	 * From Allan:
	 *    "Technically, it’s expected to have LST for every brand request (controlled brand or not) by not only Pages Admin but other apps as well like crawler.
	 *    This is the default mechanism to link a brand to supplier. So, I would suggest to retain this linking structure.
	 *    But on contrary to the sending of notification email to the owner when it’s just LST alone (i.e. no other auth level request at all), we shouldn’t be sending email.
	 *
	 * @param   string  $authLevel
	 * @return  bool
	 */
	public static function isAuthLevelManaged($authLevel)
	{
		return !($authLevel === self::AUTH_LEVEL_LISTED);
	}

	/**
	 * Returns true if there is at least authorised owner for the brand
	 *
	 * @author  Yuriy Akopov
	 * @date    2014-09-17
	 * @story   DE5017
	 *
	 * @param   int $brandId
	 *
	 * @return  bool
	 */
	public static function isBrandOwned($brandId)
	{
		$owners = self::getBrandOwners($brandId);

		return (count($owners) > 0);
	}
}