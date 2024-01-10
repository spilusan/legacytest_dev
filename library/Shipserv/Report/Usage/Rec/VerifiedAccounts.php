<?php
/**
* Verified acounts record representation class
*/
class Shipserv_Report_Usage_Rec_VerifiedAccounts
{
	//Default field values to make sure they will return something for frontend
	public $email;
	public $firstName;
	public $lastName;
	public $creationDate;
	public $level;
	public $status;
	public $anonimity;

	/**
	* Constructor
	* @param array $row One now of database object 
	*/
	public function __construct($row = null)
	{
		$fieldDefs = Shipserv_Report_Usage_FieldDefinitions::getInstance();
		foreach ($fieldDefs->getVerificationFields() as $key => $value) {
			if (array_key_exists($value, $row)) {
				$this->$key = $row[$value];
			}
		}
	}
}