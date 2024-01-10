<?php
/**
* Record representation Supplier Impressions
*/
class Shipserv_Report_Usage_Rec_SpbImpressions
{
	//Default field values to make sure they will return something for frontend
	public $dateTime;
	public $userName;
	public $spbBranchCode;
	public $spbName;
	/**
	* Constructor
	* @param array $row One now of database object 
	*/
	public function __construct($row = null)
	{
		
		$fieldDefs = Shipserv_Report_Usage_FieldDefinitions::getInstance();
		foreach ($fieldDefs->getSpbImpressionFields() as $key => $value) {
			if (array_key_exists($value, $row)) {
				$this->$key = $row[$value];
			}
		}
	}
}
