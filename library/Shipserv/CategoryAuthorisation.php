<?php

/**
 * Category Authorisation
 *
 * @author Uladzimir Maroz
 */
class Shipserv_CategoryAuthorisation {

	public $companyId;

	public $categoryId;

	public $isAuthorised;

	public $dateRequested;

	protected static $daoAdapter;

	public function  __construct($companyId, $categoryId, $isAuthorised)
	{
		$this->companyId = $companyId;
		$this->categoryId = $categoryId;
		$this->isAuthorised = $isAuthorised;
	}

	/**
	 *
	 * @param integer $companyId
	 * @param integer $categoryId
	 * @param boolean $isAuthorised
	 * @return Shipserv_CategoryAuthorisation
	 */
	public static function fetch($companyId, $categoryId, $isAuthorised)
	{
		$isAuth = null;
		if ($isAuthorised===true) $isAuth = "Y";
		if ($isAuthorised===false) $isAuth = "N";

		if ($result = self::getDao()->fetch(array(
			"SUPPLIER_BRANCH_CODE"=>$companyId,
			"PRODUCT_CATEGORY_ID"=>$categoryId,
			"IS_AUTHORISED"=>$isAuth
		)))
		{
			return self::createObjectFromDBRow($result[0]);
		}
		else
		{
			return null;
		}
	}

	/**
	 *
	 * @param array $filters
	 * @return array
	 */
	public static function search($filters=array())
	{

		if ($result = self::getDao()->fetch($filters))
		{
			if (count($result) > 0)
			{
				$results = array();
				foreach ($result as $resultRow)
				{
					$results[] = self::createObjectFromDBRow($resultRow);
				}
				return $results;
			}
			else
			{
				return null;
			}
		}
		else
		{
			return null;
		}
	}

	/**
	 * Creates Category Authorisation

	 * @return Shipserv_CategoryAuthorisation
	 */
	public static function create ($companyId, $categoryId, $isAuthorised) {

		$isAuth = null;
		if ($isAuthorised===true) $isAuth = "Y";
		if ($isAuthorised===false) $isAuth = "N";

		self::getDao()->store($companyId, $categoryId, $isAuth);

		return new self($companyId, $categoryId, $isAuth);
	}

	public function remove()
	{
		self::getDao()->remove(array(
			"PRODUCT_CATEGORY_ID" => $this->categoryId,
			"SUPPLIER_BRANCH_CODE" => $this->companyId,
			"IS_AUTHORISED"=> $this->isAuthorised
		));
	}


	public function authorise()
	{
		$this->isAuthorised = 'Y';
		self::getDao()->authorise($this->companyId, $this->categoryId, $this->isAuthorised);
	}

	private static function getDb ()
	{
		return $GLOBALS["application"]->getBootstrap()->getResource('db');
	}

	/**
	 *
	 * @return Shipserv_Oracle_CategoryAuthorisation
	 */
	private static function getDao()
	{
		if (!self::$daoAdapter)
		{
			self::$daoAdapter = new Shipserv_Oracle_CategoryAuthorisation(self::getDb());
		}

		return self::$daoAdapter;
	}

	/**
	 * Instantiate class using array returned from DB adapter
	 * @param array $dbRow
	 * @return Shipserv_CategoryAuthorisation
	 */
	public static function createObjectFromDBRow ($dbRow)
	{
		return new Shipserv_CategoryAuthorisation($dbRow["SUPPLIER_BRANCH_CODE"], $dbRow["PRODUCT_CATEGORY_ID"], $dbRow["IS_AUTHORISED"]);
	}

	/**
	 * Should be rewritten to return proper company object later
	 *
	 * @return array
	 */
	public function getCompanyInfo()
	{

		$profileDao = new Shipserv_Oracle_Profile(self::getDb());
		$company = $profileDao->getSuppliersByIds(array($this->companyId));

		$coutriesDAO = new Shipserv_Oracle_Countries(self::getDb());
		$country = $coutriesDAO->fetchCountryByCode($company[0]["SPB_COUNTRY"]);
		if (count($country)==1)
		{
			$company[0]["CNT_NAME"] = $country[0]["CNT_NAME"];
			$company[0]["CNT_CON_CODE"] = $country[0]["CNT_CON_CODE"];
		}

		return $company[0];
	}

	public function getCategoryInfo ()
	{
		$categoryDao = new Shipserv_Oracle_Categories(self::getDb());
		$category = $categoryDao->fetch($this->categoryId);
		return $category;
	}

	/**
	 * Get all outstanding requests by category
	 *
	 * @param integer $categoryId
	 * @return Shipserv_CategoryAuthorisation[]
	 */
	public static function getRequests ($categoryId)
	{
		$requests = array ();

		foreach (self::getDao()->fetch(array(
			"PRODUCT_CATEGORY_ID"=>$categoryId,
			"IS_AUTHORISED"=>'N'
		)) as $requestRow)
		{
			$requests[] = self::createObjectFromDBRow($requestRow);
		}
		return $requests;
	}

	/**
	 * Retrieve list of authorisations granted for given category
	 *
	 * @param int $categoryId
	 * @return Shipserv_CategoryAuthorisation[]
	 */
	public static function getAuthorisations ($categoryId, $page = 0, $region = "", $name = "")
	{
		$authorisations = array ();

		foreach (self::getDao()->fetchAuths(array(
			"PRODUCT_CATEGORY_ID"=>$categoryId
		), $page, $region, $name) as $authRow)
		{
			$authorisations[] = self::createObjectFromDBRow($authRow);
		}

		return $authorisations;
	}

	/**
	 * Retrieve count of authorisations granted for given category
	 *
	 * @param int $categoryId
	 * @return Shipserv_CategoryAuthorisation[]
	 */
	public static function getAuthorisationsCount ($categoryId, $region = "", $name = "")
	{
		$authorisations = self::getDao()->fetchAuths(array(
			"PRODUCT_CATEGORY_ID"=>$categoryId
		), 1, $region, $name,1);
		
		if (count($authorisations)>0)
		{
			return $authorisations[0]["TOTALCOUNT"];
		}
		else return 0;

	}

	/**
	 *	Retrieve all company authorisations
	 *
	 * @param integer $companyId
	 * @return array
	 */
	public static function getSupplierAuthorisations($companyId)
	{
		$authorisations = array ();

		foreach (self::getDao()->fetchAuths(array("SUPPLIER_BRANCH_CODE"=>$companyId,"IS_AUTHORISED"=>'Y')) as $authRow)
		{
			$authorisations[] = self::createObjectFromDBRow($authRow);
		}

		return $authorisations;

	}

	/**
	 *	Retrieve all company requests for specific category
	 *
	 * @param integer $companyId
	 * @param integer $categoryId
	 * @return array
	 */
	public static function getCompanyRequestsForCategory($companyId, $categoryId)
	{
		$requests = array ();

		foreach (self::getDao()->fetch(array("SUPPLIER_BRANCH_CODE"=>$companyId,"PRODUCT_CATEGORY_ID"=>$categoryId,"IS_AUTHORISED"=>'N')) as $authRow)
		{
			$requests[] = self::createObjectFromDBRow($authRow);
		}

		return $requests;

	}

	/**
	 *	Retrieve categories that user is allowed to manage
	 *
	 * @param integer $userId
	 * @return array
	 */
	public static function getManagedCategories($userId)
	{
		$categories = array ();

		$categoriesDAO = new Shipserv_Oracle_Categories(self::getDb());

		$categoryEditorsDao = new Shipserv_Oracle_CategoryEditors(self::getDb());

		foreach ($categoryEditorsDao->fetch(array("PCE_USER_ID"=>$userId)) as $categoryRow)
		{
			$categories[$categoryRow["PCE_CATEGORY_ID"]] = $categoriesDAO->fetch($categoryRow["PCE_CATEGORY_ID"]);
		}

		return $categories;

	}

	/**
	 *	Retrieve list of category editors
	 *
	 * @return array
	 */
	public static function getCategoryEditors($categoryId)
	{
		$categoryEditors = array ();

		$categoryEditorsDao = new Shipserv_Oracle_CategoryEditors(self::getDb());

		foreach ($categoryEditorsDao->fetch(array(
			"PCE_CATEGORY_ID"=>$categoryId
		)) as $categoryEditorRow)
		{
			$categoryEditors[] = $categoryEditorRow["PCE_USER_ID"];
		}

		return $categoryEditors;

	}

	/**
	 * Remove all requests by company for specific category authorisation
	 *
	 * @param integer $companyId
	 * @param integer $categoryId
	 * @return boolean
	 */
	public static function removeCompanyRequestsForCategory ($companyId, $categoryId)
	{
		return self::getDao()->remove(array(
			"PRODUCT_CATEGORY_ID"=>$categoryId,
			"SUPPLIER_BRANCH_CODE"=>$companyId,
			"IS_AUTHORISED"=>'N'
		));
	}

	public static function removeCompanyAuthsForCategory ($companyId, $categoryId)
	{
		self::getDao()->remove(array(
			"PRODUCT_CATEGORY_ID"=>$categoryId,
			"SUPPLIER_BRANCH_CODE"=>$companyId,
			"IS_AUTHORISED"=>'Y'
		));

		self::getDao()->remove(array(
			"PRODUCT_CATEGORY_ID"=>$categoryId,
			"SUPPLIER_BRANCH_CODE"=>$companyId,
			"IS_AUTHORISED"=>null
		));
		return true;
	}
}
?>
