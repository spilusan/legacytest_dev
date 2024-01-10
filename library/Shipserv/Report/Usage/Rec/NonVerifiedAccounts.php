<?php
/**
* Non Verified acounts record representation class
*/
class Shipserv_Report_Usage_Rec_NonVerifiedAccounts
{

	//Dfault field values to make sure they will return something for frontend
	public $email;
	public $firstName;
	public $lastName;
	public $creationDate;
	public $level;
	public $status;

	/**
	* Constructor
	* @param array $row One now of database object 
	*/
	public function __construct($row = null)
	{
		$fieldDefs = Shipserv_Report_Usage_FieldDefinitions::getInstance();
		foreach ($fieldDefs->getNonVerificationFields() as $key => $value) {
			if (array_key_exists($value, $row)) {
				$this->$key = $row[$value];
			}
		}
	}
}