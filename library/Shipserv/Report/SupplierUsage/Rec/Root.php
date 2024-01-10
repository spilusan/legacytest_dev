<?php
/**
* Convert main report data to main report object rec structure
*/
class Shipserv_Report_SupplierUsage_Rec_Root
{

	protected $params = null;
	protected $dataSet = null;
	protected $extended = false;

	/**
	* Construtor (Sets params, and create dataset)
	* @param array $params  URL params
	* @param bool $extended Full text return for engagement level
	* @return unknown
	*/
	public function __construct($params = null, $extended = false)
	{
		$this->params = $params;
		$this->extended = $extended; 
		$this->dataSet = new Shipserv_Report_SupplierUsage_Data_Root($params);
	}

	/**
	* Retrives the main report data as an array of row objects
	* @param boolean $hasLimit If the result must be limitied to a certain (500) records 
	* @return array Array of row objects
	*/
	public function getData($hasLimit = true)
	{
		$result = array();

		if ($hasLimit) {
			$records = $this->dataSet->getData();
		} else {
			$records = $this->dataSet->getData(0);
		}

		foreach ($records as $record) {
			array_push($result, new Shipserv_Report_SupplierUsage_Rec_RootRow($record, $this->extended));
		}
		return $result;
	}

}