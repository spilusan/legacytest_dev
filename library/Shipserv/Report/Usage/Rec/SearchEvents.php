<?php
/**
* Record representation of search event list
*/
class Shipserv_Report_Usage_Rec_SearchEvents
{
	//Default field values to make sure they will return something for frontend
	public $dateTime;
	public $userName;
	public $searchText;
	public $location;
	/**
	* Constructor
	* @param array $row One now of database object 
	*/
	public function __construct($row = null)
	{
		
		$fieldDefs = Shipserv_Report_Usage_FieldDefinitions::getInstance();
		foreach ($fieldDefs->getSearchEventFields() as $key => $value) {
			if (array_key_exists($value, $row)) {
				$this->$key = $row[$value];
			}
		}
	}
}
