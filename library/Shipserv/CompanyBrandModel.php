<?php

/**
 * Entity Class to deal with brand models listed for company
 *
 * @author uladzimirmaroz
 */
class Shipserv_CompanyBrandModel {

	protected $companyId;

	protected $brandId;

	protected $modelName;

	protected static $daoAdapter;

	/**
	 *
	 * Magic method to access object's fields
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get ($name)
	{
		return $this->{$name};
	}

	public function  __construct($companyId, $brandId, $modelName)
	{
		$this->companyId = $companyId;
		$this->brandId = $brandId;
		$this->modelName = $modelName;
	}

	/**
	 * Store new brand's model for company
	 *
	 * @param integer $companyId
	 * @param integer $brandId
	 * @param string $modelName
	 * @return Shipserv_CompanyBrandModel
	 */
	public static function create ($companyId, $brandId, $modelName) {


		self::getDao()->store($companyId, $brandId, $modelName);

		return new Shipserv_CompanyBrandModel($companyId, $brandId, $authLevel, $isAuth);
	}
	
	/**
	 * Remove record
	 */
	public function remove()
	{
		self::getDao()->remove(array(
			"CBM_BRAND_ID" => $this->brandId,
			"CBM_COMPANY_ID" => $this->companyId,
			"CBM_MODEL_NAME"=> $this->modelName
		));
	}

	private static function getDb ()
	{
		return $GLOBALS["application"]->getBootstrap()->getResource('db');
	}

	/**
	 *
	 * @return Shipserv_Oracle_CompanyBrandModel
	 */
	private static function getDao()
	{
		if (!self::$daoAdapter)
		{
			self::$daoAdapter = new Shipserv_Oracle_CompanyBrandModel(self::getDb());
		}

		return self::$daoAdapter;
	}

	/**
	 * Instantiate class using array returned from DB adapter
	 * @param array $dbRow
	 * @return Shipserv_CompanyBrandModel
	 */
	public static function createObjectFromDBRow ($dbRow)
	{
		return new Shipserv_CompanyBrandModel($dbRow["CBM_COMPANY_ID"], $dbRow["CBM_BRAND_ID"], $dbRow["CBM_MODEL_NAME"]);
	}
	
	/**
	 *	Removes all models for given brand for given company
	 *
	 * @param integer $companyId
	 * @param integer $brandId
	 * @return boolean
	 */
	public static function removeAllModels ($companyId,$brandId)
	{
		return self::getDao()->remove(array(
			"CBM_BRAND_ID"=>$brandId,
			"CBM_COMPANY_ID"=>$companyId
		));
	}

}
?>
