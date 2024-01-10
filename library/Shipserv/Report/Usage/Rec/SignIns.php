<?php
/**
* Record representation of successful sign ins drilldown
*/
class Shipserv_Report_Usage_Rec_SignIns
{
	//Default field values to make sure they will return something for frontend
	public $dateTime;
	public $userName;

	/**
	* Constructor
	* @param array $row One now of database object 
	*/
	public function __construct($row = null)
	{
		
		$fieldDefs = Shipserv_Report_Usage_FieldDefinitions::getInstance();
		foreach ($fieldDefs->getSignInsFields() as $key => $value) {
			if (array_key_exists($value, $row)) {
				$this->$key = $row[$value];
			}
		}
	}
}
