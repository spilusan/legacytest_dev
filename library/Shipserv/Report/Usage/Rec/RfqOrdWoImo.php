<?php
/**
* RFQ, PO wo IMO record representation class
*/
class Shipserv_Report_Usage_Rec_RfqOrdWoImo
{
	//Default field values to make sure they will return something for frontend
	public $id;
	public $vesselName;
	public $subject;
	public $printable;
	public $internalRefNo;
	public $printableUrl;

	/**
	* Constructor
	* @param array $row One now of database object 
	*/
	public function __construct($row = null)
	{
		$fieldDefs = Shipserv_Report_Usage_FieldDefinitions::getInstance();
		foreach ($fieldDefs->getRfqOrdWoImoFields() as $key => $value) {
			if (array_key_exists($value, $row)) {
				$this->$key = $row[$value];
			}
		}
		
		if ($row['PRINTABLE'] == 'RFQ') {
			$this->printableUrl = "/user/printable?d=rfq&id=" . $row['INTERNAL_REF_NO'] . "&h=" . md5('rfq' . $row['INTERNAL_REF_NO']);
			// $rfq = Shipserv_Rfq::getInstanceById($row['RFQ_INTERNAL_REF_NO']);
			// $this->printableUrl = $rfq->getUrl();
		} else {
			$this->printableUrl = "/user/printable?d=ord&id=" . $row['INTERNAL_REF_NO'] . "&h=" . md5('ord' . $row['INTERNAL_REF_NO']);
			
		}
	}
}
