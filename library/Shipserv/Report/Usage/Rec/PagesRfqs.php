<?php
/**
* Record representation of successful sign ins drilldown
*/
class Shipserv_Report_Usage_Rec_PagesRfqs
{
	//Default field values to make sure they will return something for frontend
	public $dateTime;
	public $rfqInternalRefNo;
    public $usrName;
    public $spbBranchCode;
    public $spbName;
    public $printableUrl;

	/**
	* Constructor
	* @param array $row One now of database object 
	*/
	public function __construct($row = null)
	{
		
		$fieldDefs = Shipserv_Report_Usage_FieldDefinitions::getInstance();
		foreach ($fieldDefs->getPagesRfqsFields() as $key => $value) {
			if (array_key_exists($value, $row)) {
				$this->$key = $row[$value];
			}
		}
		
		$rfq = Shipserv_Rfq::getInstanceById($row['RFQ_INTERNAL_REF_NO']);
		$this->printableUrl = $rfq->getUrl();
	}
}
