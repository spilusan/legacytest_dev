<?php
class Myshipserv_View_Helper_String extends Zend_View_Helper_Abstract
{
	/**
	 * Sets up the string helper
	 *
	 * @access public
	 * @return Myshipserv_View_Helper_String
	 */
	public function String()
	{
		return $this;
	}

	/**
	 * Not sure why is it here, but suspect a helper needs it (Yuriy Akopov)
	 */
	public function init()
	{

	}

	/**
	 * Shortens a string
	 *
	 * @param   string      $string         The full string to shorten
	 * @param   int         $length         The maximum length the string should be
	 * @param   bool        $addEllipses    Set to true to add '...' to the end of the string (default true)
	 * @param   bool        $stripTags
	 *
	 * @return  string
	 */
	public function shorten($string, $length, $addEllipses = true, $stripTags = true)
	{
		if (strlen($string) <= $length) {
			return $string;
		}

		$string = substr($string, 0, $length);

		if ($addEllipses) {
			$string.= "...";
		}

		if ($stripTags) {
			$string = strip_tags($string);
		}

		return $string;
	}

	/**
	 * Shortens a string to the last full word within the string, up to $length
	 *
	 * If the string is already shorter than $length, the string is returned as normal
	 *
	 * @param   string  $string         The full string to shorten
	 * @param   int     $length         The maximum length the string should be
	 * @param   bool    $addEllipses    Set to true to add '...' to the end of the string (default true)
	 * @param   bool    $stripTags      Set to true to run the output through strip_tags (default true)
	 *
	 * @return  string
	 */
	public function shortenToLastWord($string, $length, $addEllipses = true, $stripTags = true)
	{
		if (strlen($string) <= $length) {
			return $string;
		}

		$string = substr($string, 0, strrpos(substr($string, 0, $length), ' '));

		if ($addEllipses) {
			$string.= "...";
		}

		if ($stripTags) {
			$string = strip_tags($string);
		}

		return $string;
	}

	/**
	 * Sanitises a string for display
	 *
	 * @param   string  $string
	 * @param   bool    $nl2br
	 *
	 * @return  string
	 */
	public function sanitise($string, $nl2br = false)
	{
		$string = htmlentities($string, ENT_XHTML | ENT_QUOTES, "UTF-8");

		if ($nl2br === true) {
			$string = nl2br($string);
		}

		return $string;
	}

	/**
	 * Takes a search string, e.g. "alfal laval purifiers", and creates all
	 * appropriate search phrases: alfa, laval, purifiers, alfa_laval, laval_purifiers,
	 * and alfa_laval_purifiers
	 *
	 * @param   string  $string             The search string to split
	 * @param   string  $separator
	 * @param   bool    $skipTrimChars
	 *
	 * @return array An array of keywords
	 */
	public function createKeywordPhrases($string, $separator = '_', $skipTrimChars = false)
	{
		$phrases = array();

		$string = html_entity_decode($string);

		if ($skipTrimChars === false) {
			// convert hyphens into spaces
			$string = str_replace('-', ' ', $string);
			// now remove non-alphas
			$string = preg_replace("/[^a-zA-Z0-9\s]/", "", $string);
		}

		// convert multiple spaces into one space
		$string = trim($string);
		$string = preg_replace('/[ \t\n\r]+/', ' ', $string); // ereg_replace("[ \t\n\r]+", " ", $string);

		// split the words
		$words = explode(' ', $string);
		if (!$words) {
			return $phrases;
		}

		// now loop through the words and create the phrases
		// (DS updated 01/10/2010 - only uses first 4 words)
		$n = count($words);
		if ($n > 4) {
			$n = 4;
		}

		for ($i = 0; $i < $n; $i++) {
			for ($j = 0; $j < $n - $i; $j++) {
				$tmp = array_slice($words, $i, $n - ($i + $j));

				$phrases[] = implode($separator, $tmp);
			}
		}

		return $phrases;
	}

	/**
	 * Returns the string length of only the alpha characters of a string
	 *
	 * @param   bool   $string
	 *
	 * @return  int
	 */
	public function alphaStringLength($string)
	{
		return strlen(preg_replace("/[^a-zA-Z\s]/", "", $string));
	}

	/**
	 * Transforms timestamp from past in beautiful text
	 *
	 * @param   string  $date
	 *
	 * @return  string
	 */
	public function ago($date)
	{
		$days = floor((time() - strtotime($date)) / 86400);
		if ($days < 1) {
			return "Today";
		} elseif ($days < 7) {
			return $days . " day". (($days > 1) ? "s" : "") ." ago";
		} elseif ($days <= 30) {
			$weeks = floor($days / 7);
			return $weeks . " week". (($weeks > 1) ? "s" : "") ." ago";
		} elseif ($days < 360) {
			$months = floor($days / 30);
			return $months . " month". (($months > 1) ? "s" : "") ." ago";
		} else {
			return "Over a year ago";
		}
	}

	/**
	 * Replaces spaces with -s and cleans up the name
	 *
	 * @param   string  $string
	 *
	 * @return  string Formatted, URL-safe string
	 */
	public function sanitiseForURI($string)
	{
		$temp   = preg_replace('/(\W){1,}/', '-', $string);
		$string = strtolower(preg_replace('/-$/', '', $temp));

		return $string;
	}

	/**
	 * @param   string  $city
	 * @param   string  $state
	 * @param   string  $country
	 *
	 * @return  string
	 */
	public function formatAddress($city, $state, $country)
	{
		$city    = trim($city);
		$state   = trim($state);
		$country = trim($country);

		$city    = str_replace("--", "", $city);
		$state   = str_replace("--", "", $state);
		$country = str_replace("--", "", $country);

		$loc = array();

		if ($city != "" && $city != "-") $loc[] = $city;
		if ($state != "" && $state != "-") $loc[] = $state;
		if ($country != "" && $country != "-") $loc[] = $country;

		return (count($loc) > 0) ? implode(", ", $loc) : "";
	}

    /**
     * Converts given number of seconds into a readable string
     *
     * @author  Yuriy Akopov
     * @date    2014-05-22
     *
     * @param   float   $seconds
     *
     * @return  string
     */
    public static function secondsToString($seconds)
    {
        $seconds = sprintf('%.2f', $seconds);

        $units = array(
            'day'       => 24 * 60 * 60,
            'hour'      => 60 * 60,
            'minute'    => 60,
            'second'    => 1
        );

        $strBits = array();
        foreach ($units as $label => $secInUnit) {
            $count = $seconds / $secInUnit;

            if ($label !== 'second') {
                $count = floor($count);
            }

            if ($count > 0) {
                $bit = $count . ' ' . $label;

                if (($count > 1) or ($label === 'second')) {
                    $bit .= 's';
                }

                $strBits[] = $bit;
            }

            $seconds -= $count * $secInUnit;
        }

        return implode(' ', $strBits);
    }

    /**
     * Truncates given string to the nearest word
     *
     * @author  Yuriy Akopov
     * @date    2015-03-16
     * @story   S12888
     *
     * @param   string  $string
     * @param   int     $wrapAt
     * @param   string  $suffix
     *
     * @return  string
     */
    public static function truncateToWord($string, $wrapAt = 100, $suffix = "...")
    {
        if (strlen($string) <= $wrapAt) {
            return $string;
        }

        $wrapped = wordwrap($string, $wrapAt);
        $truncated =  substr($wrapped, 0, strpos($wrapped, PHP_EOL));

        return $truncated . $suffix;
    }

	/**
	 * Encodes given array into a CSV string, opposite to what the standard function str_getcsv function does
	 * Based on this StackOverflow example: http://stackoverflow.com/a/16353448/454266
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-08-18
	 * @story   DE6906
	 *
	 * @param   array   $input
	 * @param   string  $delimiter
	 * @param   string  $enclosure
	 *
	 * @return  string
	 */
	public static function csvRowToString(array $input, $delimiter = ',', $enclosure = '"')
	{
		// open a memory "file" for read/write
		$fp = fopen('php://temp', 'r+');
		// write the $input array to the "file" using the standard function which exists for file context only
		fputcsv($fp, $input, $delimiter, $enclosure);
		// rewind the "file" so we can read what we just wrote
		rewind($fp);
		// read the entire line into a variable
		$row = stream_get_contents($fp);
		// close the "file"
		fclose($fp);

		return rtrim($row, "\n");
	}

	/**
	 * Removes double quotation marks, line breaks and possibly (in the future) other characters that break SalesForce
	 * upload even when they are properly escaped
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-09-14
	 * @story   DE6937
	 *
	 * @param   array|string    $input
	 *
	 * @return  array|string
	 */
	public static function simplifyForCsv($input)
	{
		$returnScalar = !is_array($input);

		if ($returnScalar) {
			$input = array($input);
		}

		$charsToRemove = array("\r", "\n", "\\");
		foreach ($charsToRemove as $char) {
			foreach ($input as $index => $value) {
				$input[$index] = str_replace($char, " ", $value);
			}
		}

		if ($returnScalar) {
			return $input[0];
		}

		return $input;
	}
}
