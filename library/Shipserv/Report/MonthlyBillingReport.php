<?php
/**
 * Class that responsible for VBP Billing Report
 *
 * add more explaination
 * 
 * @author Elvir <eleonard@shipserv.com>
 *
 */
class Shipserv_Report_MonthlyBillingReport extends Shipserv_Report
{
	protected $supplier;
	
	const NOT_TRANSITIONED = 222222222220;
	const NO_DATA = 11111111110;

	/**
	 * get the instance of the report
	 * @param Shipserv_Supplier $supplier 
	 * @param int $month month in integer
	 * @param int $year year in integer : 2012, 2013
	 */ 
	public static function getInstance($supplier, $month, $year, $type = "summary")
	{
		$lowerDate = new DateTime();
		$upperDate = new DateTime();
		
		$object = new self;
		$object->supplier = $supplier;
		
		
		$periodForLastMonth = $object->getPreviousMonthPeriods($month, $year);
		$periodForLast2Months = $object->getPreviousMonthPeriods($periodForLastMonth['start']->format('m'), $periodForLastMonth['start']->format('Y'));
		
		if( $object->supplier->getVBPTransitionDate() === null )
		{
			throw new Myshipserv_Exception_MessagedException("Supplier that you have selected is not on VBP pricing: " . $object->supplier->tnid . " - " . $object->supplier->name, 400);
		}
		
		$data['transitionDate'] 	= $object->supplier->getVBPTransitionDate()->format('d M Y');
		
		// check for transition date - null the data if transition date is after the date of the report
		$transitionDate = $supplier->getVBPTransitionDate();

		// if supplier isn't on VBP pricing yet
		if( $periodForLastMonth['start']->format('U') < $transitionDate->format('U') )
		{
			$data['gmv'] 				= self::NOT_TRANSITIONED;
			$data['unactioned'] 		= self::NOT_TRANSITIONED;
			$data['uniqueContactView'] 	= self::NOT_TRANSITIONED;
			$data['searchImpression'] 	= self::NOT_TRANSITIONED;
		}
		else
		{
			
			if( $type == "summary" )
			{
				$responseData['gmv-p1si'] 	= $object->getDataFromAdapter( 'gmv-p1si', $object->supplier->tnid, $periodForLastMonth['start']->format('Ymd'), $periodForLastMonth['end']->format('Ymd') );
				$responseData['ucv-urfq'] 	= $object->getDataFromAdapter( 'ucv-urfq', $object->supplier->tnid, $periodForLast2Months['start']->format('Ymd'), $periodForLast2Months['end']->format('Ymd') );
	
				$data['supplier']			= $object->supplier;
				$data['gmv'] 				= $responseData['gmv-p1si']['po-total-value']['count'];
				$data['searchImpression'] 	= $responseData['gmv-p1si']['top-search-impression']['count'];
				$data['unactioned'] 		= $responseData['ucv-urfq']['unactioned-rfq']['count'];
				$data['uniqueContactView'] 	= $responseData['ucv-urfq']['unique-contact-view']['count'];
				$data['gmvP1siDate']		= $periodForLastMonth['start']->format('d M Y') . " to " . $periodForLastMonth['end']->format('d M Y');
				$data['ucvUrfqDate']		= $periodForLast2Months['start']->format('d M Y') . " to " . $periodForLast2Months['end']->format('d M Y');
			}
			else
			{
				$responseData['gmv-detail'] = $object->getDataFromAdapter( 'gmv-detail', $object->supplier->tnid, $periodForLastMonth['start']->format('Ymd'), $periodForLastMonth['end']->format('Ymd') );
				$data['gmv-detail'] 		= $responseData['gmv-detail']['po-total-value']['purchase-orders'];
				$data['gmv-detail-total'] 	= $responseData['gmv-detail']['po-total-value']['count'];
				
			}
			
			// if transition month is the same as the report month, then we cannot get the data for previous month
			if( $periodForLastMonth['start']->format("m") == $transitionDate->format("m") && $periodForLastMonth['start']->format("Y") == $transitionDate->format("Y") )
			{
				$data['unactioned'] = self::NO_DATA;
				$data['uniqueContactView'] = self::NO_DATA;
				$data['startDate'] = $data['transitionDate'];	
			}
		}
/*
		echo
		$object->supplier->tnid . " ::: " .
		$month . "==" . $transitionDate->format("n") .
		" >>> " . $year . "==" . $transitionDate->format("Y") .
		"<br />";
*/
		if( $month == $transitionDate->format("n") && $year == $transitionDate->format("Y") )
		{
			$lowerDate->setDate($year, $month, $transitionDate->format("d"));
		}
		else
		{
			$lowerDate->setDate($year, $month, 1);
		}
			
		$upperDate->setDate($year, $month, cal_days_in_month(CAL_GREGORIAN, $month, $year));
		
		$data['startDate'] = $lowerDate->format("d M Y");
		$data['endDate'] = $upperDate->format("d M Y");
		
		$data['startDate'] = $periodForLastMonth['start']->format('d M Y');
		$data['endDate'] = $periodForLastMonth['end']->format('d M Y');
		
		$data['startDateYMD'] = $periodForLastMonth['start']->format('Y-m-d');
		$data['endDateYMD'] = $periodForLastMonth['end']->format('Y-m-d');
		
		
		$data['billingPeriod'] = str_pad($month, 2, "0", STR_PAD_LEFT) . "-" . $year;
		
		$object->data = $data;
		return $object;
	}
	
	/**
	 * Convert the report to array
	 */
	public function toArray()
	{
		return $this->data;
	}
	
	/**
	 * Calculate the previous month based on the month and year provided
	 * @param int $month
	 * @param int $year
	 * @return multitype:DateTime
	 */
	private function getPreviousMonthPeriods($month, $year)
	{
		//echo "INPUT: " . $month . "-" . $year . "<br />";
		$upperDate = new DateTime();
		$lowerDate = new DateTime();
				
		// for january
		if($month == 1)
		{
			$upperDate->setDate($year-1, $month+11, cal_days_in_month(CAL_GREGORIAN, $month+11, $year-1));
			$lowerDate->setDate($year-1, $month+11, 1);
		}
		else
		{
			$upperDate->setDate($year, $month-1, cal_days_in_month(CAL_GREGORIAN, $month-1, $year));
			$lowerDate->setDate($year, $month-1, 1);
		}

		$transitionDate = $this->supplier->getVBPTransitionDate();
		
		if( $transitionDate !== null )
		{
			//echo $lowerDate->format("d M Y") . " to ";
			//echo $upperDate->format("d M Y");
			//echo "---<br />";
			if( $transitionDate->format("d") != 1 && $transitionDate->format('m') == $lowerDate->format('m') && $transitionDate->format('Y') == $lowerDate->format('Y') )
			{
				$lowerDate->setDate($lowerDate->format('Y'), $lowerDate->format('m'), $transitionDate->format('d'));
				//echo $lowerDate->format("d M Y") . " to ";
				//echo $upperDate->format("d M Y");
			}
		}

		return array('start' => $lowerDate, 'end' => $upperDate );
	}	
}