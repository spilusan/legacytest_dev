<?php
/**
* Record representation of user activity reports
*/
class Shipserv_Report_Usage_Rec_UserActivity
{
	//Default field values to make sure they will return something for frontend
	public $dateTime;
	public $userName;
	public $activityName;

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

			$this->activityName = Shipserv_Report_Usage_Activity::getInstance()->translate($row['PUA_ACTIVITY']);
		}
	}
}
