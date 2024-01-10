<?php

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * String helper functions class
 * @package myshipserv
 * @author Shane O'Connor <soconnor@shipserv.com>
 * @copyright Copyright (c) 2012, ShipServ
 */
class Shipserv_Helper_String {
	/**
	 * Overcomes the bug/limitations in preg_match_all that crashes PHP on long strings. Is considerably faster too despite IO overhead.
     *
     * Refactored by Yuriy Akopov on 2014-07-21
     * @todo: I have doubts we really need that optimisation with shell_exec here...
     *
	 * @param   string  $pattern
	 * @param   string  $haystack
     * @param   string  $delimiter
     *
	 * @return  array
	 * @throws  Exception
	 */
	public function safeMatchAll($pattern, $haystack, $delimiter = '#') {
        // max string size preg_match can process is about 100055 characters
        $strChunks = str_split($haystack, 10000);

        $output = array();
        foreach ($strChunks as $str) {
            $regex = $delimiter . $pattern . $delimiter . 'i';
            if (preg_match_all($regex, $str, $rawMatches) === false) {
                throw new Exception("Failed to match the pattern " . $pattern);
            }

            $output = array_merge($output, $rawMatches[0]);
        }

        foreach ($output as $key => $value) {
            if (strlen($value) === 0) {
                unset($output[$key]);
            }
        }

        return $output;
	}

	public function generateRandomString($length, $type = "standard") {
		// $PwdType can be one of these:
		//    test .. .. .. always returns the same password = "test"
		//    any  .. .. .. returns a random password, which can contain strange characters
		//    alphanum . .. returns a random password containing alphanumerics only
		//    standard . .. same as alphanum, but not including l10O (lower L, one, zero, upper O)
		//
		 $ranges = '';

		if ('test' == $type)
			return 'test';
		elseif ('standard' == $type)
			$ranges = '65-78,80-90,97-107,109-122,50-57';
		elseif ('alphanum' == $type)
			$ranges = '65-90,97-122,48-57';
		elseif ('any' == $type)
			$ranges = '40-59,61-91,93-126';

		if ($ranges <> '') {
			$range = explode(',', $ranges);
			$numRanges = count($range);

			$p = '';
			for ($i = 1; $i <= $length; $i++) {
				$r = mt_rand(0, $numRanges - 1);
				list($min, $max) = explode('-', $range [$r]);
				$p.=chr(mt_rand($min, $max));
			}
			return $p;
		}
	}
	
	/**
   * Translates a camel case string into a string with underscores (e.g. firstName -&gt; first_name)
   * @param    string   $str    String in camel case format
   * @return    string            $str Translated into underscore format
   */
  function from_camel_case($str) {
    $str[0] = strtolower($str[0]);
    $func = create_function('$c', 'return "_" . strtolower($c[1]);');
    return preg_replace_callback('/([A-Z])/', $func, $str);
  }
 
  /**
   * Translates a string with underscores into camel case (e.g. first_name -&gt; firstName)
   * @param    string   $str                     String in underscore format
   * @param    bool     $capitalise_first_char   If true, capitalise the first char in $str
   * @return   string                              $str translated into camel caps
   */
  function to_camel_case($str, $capitalise_first_char = false) {
      $str = strtolower($str);

      if($capitalise_first_char) {
          $str[0] = strtoupper($str[0]);
      }

      $func = create_function('$c', 'return strtoupper($c[1]);');

      return preg_replace_callback('/_([a-z])/', $func, $str);
  }

    /**
     * A simple function to generate 'this long ago' strings, should be made more versatile later
     *
     * @author  Yuriy Akopov
     * @date    2014-07-03
     *
     * @param   DateTime        $dateFrom
     * @param   DateTime|null   $dateTo
     * @param   string          $precision
     *
     * @return  string
     * @throws  Exception
     */
    public static function strTimeDiff(DateTime $dateFrom, DateTime $dateTo = null, $precision = 'h') {
        $tsFrom = strtotime($dateFrom->format('Y-m-d H:i:s')); // PHP 5.2 DateTime doesn't have getTimestamp() :(

        if (is_null($dateTo)) {
            $tsTo = time();
        } else {
            $tsTo = strtotime($dateTo->format('Y-m-d H:i:s'));
        }

        $tsDiff = $tsTo - $tsFrom;
        $bits = array();

        $intervals = array(
            'y'  => 365 * 24 * 60 * 60,
            'm'  => 30 * 24 * 60 * 60,
            'd'  => 24 * 60 * 60,
            'h'  => 60 * 60,
            'mi' => 60
        );

        if (!is_null($precision) and !in_array($precision, array_keys($intervals))) {
            throw new Exception("Unknown precision specified, must be one of " . implode(', ', array_keys($intervals)));
        }

        $index = 0;
        foreach ($intervals as $label => $interval) {
            $index++;

            $num = $tsDiff / $interval;
            if (($index === count($intervals)) or ($label === $precision)) {
                $num = round($num, 2);
            } else {
                $num = floor($num);
            }

            if ($num > 0) {
                $tsDiff -= $num * $interval;
                $bits[] = $num . $label;
            }

            if ($label === $precision) {
                break;
            }
        }

        return implode(' ', $bits);
    }

    /**
     * @author  Yuriy Akopov
     * @date    2015-12-03
     * @story   DE6254
     *
     * @param   string  $string
     * @param   bool    $firstOnly
     *
     * @return  array|string
     * @throws  Exception
     */
    public static function findEmails($string, $firstOnly = true) {
        $parseResult = preg_match_all('/[A-Za-z0-9_-]+@[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)+/', $string, $matches);

        if ($parseResult === false) {
            throw new Exception("An error happened when parsing this string for emails: " . $string);
        }

        if ($parseResult === 0) {
            return ($firstOnly ? null : array());
        }

        return ($firstOnly ? $matches[0][0] : $matches[0]);
    }
}
