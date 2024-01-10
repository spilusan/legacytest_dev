<?php
/**
* Convert main report data to main report object rec structure
*/
class Shipserv_Report_Usage_Rec_Root
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
		$this->dataSet = new Shipserv_Report_Usage_Data_Root($params);
	}

	/**
	* Retrives the main report data as an array of row objects
	* @return array Array of row objects
	*/
	public function getData()
	{
		$result = array();
		$records = $this->dataSet->getData();
		foreach ($records as $record) {
			array_push($result, new Shipserv_Report_Usage_Rec_RootRow($record, $this->extended));
		}
		return $result;
	}

}