<?php
/**
 * Active trading acounts record representation class
*/
class Shipserv_Report_Usage_Rec_ActiveTradingAccounts
{
	//Default field values to make sure they will return something for frontend
	public $bybBranchCode;
	public $bybName;

	/**
	 * Constructor
	 * @param array $row One now of database object
	 */
	public function __construct($row = null)
	{
		$fieldDefs = Shipserv_Report_Usage_FieldDefinitions::getInstance();
		foreach ($fieldDefs->getActiveTradingAccountsFields() as $key => $value) {
			if (array_key_exists($value, $row)) {
				$this->$key = $row[$value];
			}
		}
	}
}