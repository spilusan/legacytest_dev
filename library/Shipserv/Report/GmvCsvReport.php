<?php
/**
 * GMV Report - fetches GMV report from webservice, and generates a CSV file
 * 05/03/2015
 * Author: Attil;a O
 */
class Shipserv_Report_GmvCsvReport extends Shipserv_Report
{
	protected $startDate = '';
	protected $endDate = '';
	protected $tnid;

	/**
	* Get an instance of the object, and set up initial parameters
	* @param int $tnid TradenetID
	* @param string $dateFrom Date range start value
	* @param string $dateTo Date range end value	
	* @return object
	*/ 
	public static function getInstance($tnid,  $dateFrom, $dateTo) {
    	$object = new self;
    	$object->setTnid($tnid);
    	$object->setStartDate($dateFrom);
    	$object->setEndDate($dateTo);

        return $object;
    }

    /**
    * Set TNID
    * @param int $tnid The tradenet id
    */
	public function setTnid( $tnid ) 
	{
		$this->tnid = (int)$tnid;

	}

    /**
    * Set date range start date
    * @param string $startDate  The string representation of date
    */
	public function setStartDate( $startDate )
	{
		if (preg_match('/^[0-9]{8}$/', $startDate)) {
			$this->startDate = substr($startDate,0,4).'-'.substr($startDate,4,2).'-'.substr($startDate,6,2);
		} else {
			$this->startDate = $startDate;
		}
		
	}

    /**
    * Set date range end date
    * @param strinf $endDate  The string representation of date
    */
	public function setEndDate( $endDate )
	{
		if (preg_match('/^[0-9]{8}$/', $endDate)) {
			$this->endDate = substr($endDate,0,4).'-'.substr($endDate,4,2).'-'.substr($endDate,6,2);
		} else {
			$this->endDate = $startDate;
		}
	}


	/**
	* Create the actual CSV
	* @return string the CSV as string 
	*
	*/
	public function generateReport()
	{

		//createing service
		$o = new Shipserv_Report_Supplier_Insight_Gmv;
        if( $o == null ) {
         	throw new Exception("Incorrect URL. Please check your URL.", 404);  
        }

        //Checning if all fields are filled ok, if not we can get the last qarter
        if( $this->startDate == "" && $this->endDate == "" ) {
        	// get startDate and endDate of previous quarter
        	Shipserv_DateTime::previousQuarter($passStartDate, $passEndDate);
        } else {
			if( $this->startDate != "" ){
            	$passStartDate = Shipserv_DateTime::fromString($this->startDate);
            }
			if( $this->endDate != "" ){
            	$passEndDate = Shipserv_DateTime::fromString($this->endDate);
            }
        }

                   
        // setting up tnid, if not set, raise exception
        if (!$this->tnid) {
        	throw new Exception("TNID not set.", 404); 
		}
		$o->setTnid($this->tnid);
		$o->setDatePeriod($passStartDate, $passEndDate);

		//getting the actual data
		$data = $o->getData();

        //declare CSV data headers
		$csvRowHeader = array(      
			'buyer-tnid' => 'Buyer TNID',
			'buyer-name' => 'Buyer Name',
			'doc-type'=> 'Doc Type',
			'ord-ref-no' => 'PO Ref. No.',
			'ref-no' => 'POC Ref. No.',

			// added by Yuriy Akopov on 2016-01-07
			'internal-ref-no' => 'ShipServ Ref. No.',

			'vessel-name' => 'Vessel name',
			'imo' => 'IMO No.',
			'submitted-date' => 'Submitted Date',
			'total-cost' => 'Total Cost',
			'currency' => 'Currency',
			'currency-rate' => 'Exchange Rate',
			'total-cost-usd' => 'Total Cost USD',
			'adjusted-cost' => 'Adjusted Cost (USD)',
			// '' => 'Group total',
			// '' => 'Median value?'
		);

		//loop through nodes, and generate the CSV itself
        $csv = '';
        if (array_key_exists('po-total-value', $data)) {

		$csv .= '"Total Adjusted GMV,  '.$data['po-total-value']['total-adjusted-gmv'] .'"';
		$csv .= "\n";
		$csv .= '"Total GMV,  '.$data['po-total-value']['total-gmv'] .'"';
		$csv .= "\n";
		if (array_key_exists('parentBuyers', $data['po-total-value'])) {
			foreach ($csvRowHeader as $fieldName => $fieldCaption) {
				$csv .= '"'.str_replace('"','\"',$fieldCaption).'",';
			}
            $csv .= "\n";
			foreach ($data['po-total-value']['parentBuyers'] as $key => $value) {
				$csv .= '"'.str_replace('"','\"',$value['ID']).' Group";';
				$csv .= '"'.str_replace('"','\"',$value['NAME'] ).'",';
				$csv .= '"'.str_replace('"','\"',$value['totalTrans']['total'] ).'";';
				$csv .= "\n";
				if (array_key_exists('CHILDREN', $value)) {
					foreach ($value['CHILDREN'] as $childKey => $childValue) {
						$csv .= '"'.str_replace('"','\"',$childKey ).'",';
						$csv .= '"'.str_replace('"','\"',$childValue['NAME'] ).'",';
						$csv .= "\n";
						if (array_key_exists('DATA', $childValue)) {
							foreach ($childValue['DATA'] as $dataKey => $dataValue) {
								foreach ($csvRowHeader as $fieldName => $fieldCaption) {
									if (array_key_exists($fieldName, $dataValue)) {
										$csv .= '"'.str_replace('"','\"',$dataValue[$fieldName]).'",';
									}
								}
								$csv .= "\n";
							}
						}
					}
					$csv .= "\n";
				}
			}
		}
	}

	//return the csv as string
	return $csv;
	}
}