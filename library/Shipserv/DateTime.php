<?php
/**
 * ShipServ class to handle DateTime
 * @author Elvir
 * @usage 
 * 		Shipserv_DateTime::yearAgo()
 */
class Shipserv_DateTime extends DateTime
{
	
	const INV_DATE_FORMAT_EXCEPTION_TEXT = 'Invalid date format. Currently only support 01-JAN-2014, 01-jan-2014, ';
			
	/**
	 * Return date for 1 year ago
	 * @param number $numberOfYear
	 * @return DateTime
	 */
	public static function yearAgo( $numberOfYear = 1 ){
		$d = new self();
		$d->setDate(date('Y')-(1*$numberOfYear), date('m'), date('d'));
		return $d;
	}
		
	/**
	 * Return DateTime object of current dateTime
	 * @return DateTime
	 */
	public static function now(){
		$d = new self();
		$d->setDate(date('Y'), date('m'), date('d'));
		return $d;
	}
	
	public static function unix(){
		$o = new self;
		return $o->getTimestamp();
	}
	
	private static $mon = array('JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN', 'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC');
	
	private static function getMonthInNumericFromMon($mon){
		return array_search(strtoupper($mon), self::$mon) + 1;
	}
	
	public static function fromString( $string ){
		if( $string != "" ){
			// check delimiter
			if( strstr($string, '-') !== false ){
				$delimiter = '-';
			} 
			else if( strstr($string, '/') !== false ){
				$delimiter = '/';
			}
			
			$parts = explode($delimiter, $string);
			
			// check first and last part 
			foreach( array(0,2) as $partIndex){
				if( ctype_digit($parts[$partIndex]) ) {
					if( strlen($parts[$partIndex]) <= 2 ) {
						$dd = $parts[$partIndex];
					}
					else if( strlen($parts[$partIndex]) == 4 ) {
						$yyyy = $parts[$partIndex];
					}
				}
			}
			
			// check middle part
			if( ctype_alpha($parts[1]) === true ) {
				if( strlen($parts[1]) > 3 ) {
					throw new Exception( self::INV_DATE_FORMAT_EXCEPTION_TEXT );
				}
				
				$mm = self::getMonthInNumericFromMon($parts[1]);
			}
			else if( ctype_digit($parts[1]) === true ) {
				$mm = $parts[1];
			}
			
			if( $dd === null || $mm === null || $yyyy === null ) {
				throw new Exception( self::INV_DATE_FORMAT_EXCEPTION_TEXT );
			}
			
			$d = new self();
			$d->setDate($yyyy, $mm, $dd);
			return $d;
			
		}
		else{
			throw new Exception('No string being supplied to ShipServ_DateTime::fromString; please check your code.');
		}
	}
	
	/**
	 * Return previous quarter dateTime object
	 * @param string $startDate
	 * @param string $endDate
	 */
	public static function previousQuarter(&$startDate = null, &$endDate = null){
		$o = self::_getDatesOfQuarter('previous');
		$startDate = $o['start'];
		$endDate = $o['end'];
	}
	
	public static function monthsAgo($numberOfMonth, &$startDate = null, &$endDate = null){
		$startDate = new DateTime();
		$endDate = new DateTime();
		
		$startDate->modify( '-' . $numberOfMonth . ' month' );
		$startDate->setDate($startDate->format('Y'), $startDate->format('m'), $endDate->format('d'));	
	}
	
	public static function daysAgo( $numberOfDay,  &$startDate = null, &$endDate = null ){
		$startDate = new DateTime();
		$endDate = new DateTime();
		
		$startDate->modify( '-' . $numberOfDay . ' day' );
		//$startDate->setDate($startDate->format('Y'), $startDate->format('m'), $endDate->format('d'));
	}
	
	private static function _getDatesOfQuarter($quarter = 'current', $year = null, $format = null)
	{
		$obj = new self;
		
	    if ( !is_int($year) ) {
	       $year = $obj->format('Y');
	    }
	    $current_quarter = ceil($obj->format('n') / 3);
	    switch (  strtolower($quarter) ) {
	    case 'this':
	    case 'current':
	       $quarter = ceil($obj->format('n') / 3);
	       break;
	
	    case 'previous':
	       $year = $obj->format('Y');
	       if ($current_quarter == 1) {
	          $quarter = 4;
	          $year--;
	        } else {
	          $quarter =  $current_quarter - 1;
	        }
	        break;
	
	    case 'first':
	        $quarter = 1;
	        break;
	
	    case 'last':
	        $quarter = 4;
	        break;
	
	    default:
	        $quarter = (!is_int($quarter) || $quarter < 1 || $quarter > 4) ? $current_quarter : $quarter;
	        break;
	    }
	    if ( $quarter === 'this' ) {
	        $quarter = ceil($obj->format('n') / 3);
	    }
	    $start = new self($year.'-'.(3*$quarter-2).'-1 00:00:00');
	    $end = new self($year.'-'.(3*$quarter).'-'.($quarter == 1 || $quarter == 4 ? 31 : 30) .' 23:59:59');
	
	    return array(
	        'start' => $format ? $start->format($format) : $start,
	        'end' => $format ? $end->format($format) : $end,
	    );
	}
	
	public function getNumOfDay($date1, $date2)
	{
		$tsDiff = abs($date1->getTimestamp() - $date2->getTimestamp()) ;
		return floor($tsDiff/(60*60*24));
		
	}
}