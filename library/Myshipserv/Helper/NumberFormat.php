<?php
/**
 * This class will do a specifik "Banker" number format
 *
 * @author  Attila O
 * @date    25/08/2016
 * @story   DE6888
 */

class Myshipserv_Helper_NumberFormat
{
	/**
	* Return an EVEN, ODD rounded and formatted number called Banker rounding
	* @param float   $number            The number to round
	* @param integer $precision         The precision of rounding
	* @param string  $decimalSeparator  Decimal separator
	* @param string  $thousandSeparator Thousand Separator
	* @return string
	*/
	public static function bankerNumberFormat($number = 0, $precision = 2, $decimalSeparator = '.', $thousandSeparator = ',')
	{
		$prerounded = round($number, $precision, PHP_ROUND_HALF_EVEN);
		return number_format($prerounded, $precision, $decimalSeparator, $thousandSeparator);
	}
}