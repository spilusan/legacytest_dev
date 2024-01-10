<?php
/**
 * Manage SPR input periods, Originally it was a redundant function in all classes
 * Separated here for avoid redundant code, reduce complexity
 * @author attilaolbrich
 *
 */

class Myshipserv_Spr_PeriodManager
{

    /**
	 * Return an array for the chart xAxis
	 * @param array $periods sub array of periods
	 * @param string $periodName
	 * @return array Modified array for the chart to display xAxis categories Text
	 */
	public static function getPeriodKeys($periods, $periodName)
	{
		$perodKeys = array();
		foreach (array_keys($periods) as $key) {
			switch ($periodName) {
				case "week":
					$keyParts = explode("-", $key);
					$newKey =  date("d-M y", strtotime($keyParts[0] . "W" . $keyParts[1] . "1"));
					break;
				case "quarter":
					$keyParts = explode("-", $key);
					$newKey =  "Q" . $keyParts[1] . " " .$keyParts[0];
					break;
				default:
					$keyParts = explode("-", $key);
					$newKey =  date("M", mktime(0, 0, 0, (int)$keyParts[1], 1, 0)) . " " .substr($keyParts[0], 2);
					break;
			}

			array_push($perodKeys, $newKey);
		}
		
		return $perodKeys;
	}
	
	/**
	 * Get the lowerdata and upperdate params, and an array of the date periods selected
	 * by the user, like month, weeks, quarters, so we can match with the query
	 * and the data feed will be consequent with the requested data in the chart
	 * @param String $periodName
	 * @return array Multi dimensional array of the above
	 */
	public static function getPeriodList($periodName)
	{
		$periodList = array();
		$dateFromParam = null;
		$dateToParam = null;

		$request = Zend_Controller_Front::getInstance()->getRequest();
		if ($request) {
			$dateFromParam = $request->getParam('startdate');
			$dateToParam = $request->getParam('enddate');

			if ($dateFromParam) {
				if (!preg_match('/^\d{4}(0?[1-9]|1[012])(0?[1-9]|[12][0-9]|3[01])$/', $dateFromParam)) {
					throw new Myshipserv_Exception_MessagedException('Invalid date ' . $dateFromParam, 500);
				}

				if (!preg_match('/^\d{4}(0?[1-9]|1[012])(0?[1-9]|[12][0-9]|3[01])$/', $dateToParam)) {
					throw new Myshipserv_Exception_MessagedException('Invalid date ' . $dateToParam, 500);
				}
			}
		}

		switch ($periodName) {
			case 'week':
                if ($dateFromParam !== null) {
                    $start = strtotime($dateFromParam);
                    $end = strtotime($dateToParam);
                } else {
                    $end = strtotime('this week');
                    $start = strtotime(date('Y-m-d', $end) . ' 12 weeks ago');
                }
				$startTime = new DateTime(date('Y-m-d', $start));
				$endTime = new DateTime(date('Y-m-d', $end));
				$interval = new DateInterval('P7D');
				$period = new DatePeriod($startTime, $interval, $endTime);
				
				foreach ($period as $dt) {
					$periodList[$dt->format("Y") . "-" .$dt->format("W")] = null;
				}
				
				return array(
						'lowerdate' =>  date('Ymd', $start),
						'upperdate' =>  date('Ymd', $end),
						'periodlist' => $periodList
				);
				
			case 'quarter':
                if ($dateFromParam !== null) {
                    $start = $dateFromParam;
                    $end = $dateToParam;
                } else {
                    $curQuarterFirstMonth = (floor((date("m") - 1) / 3)) * 3 + 1;
                    $start = date("Y-m-01", strtotime(date("Y-" . str_pad($curQuarterFirstMonth, 2, "0", STR_PAD_LEFT) . "-01") . " -36 month"));
                    $end = date("Y-m-01", strtotime(date("Y-" . str_pad($curQuarterFirstMonth, 2, "0", STR_PAD_LEFT) . "-01")));
                }
				$startTime = new DateTime($start);
				$endTime = new DateTime($end);
				$interval = new DateInterval('P3M');
				$period = new DatePeriod($startTime, $interval, $endTime);
				
				foreach ($period as $dt) {
					$periodList[$dt->format("Y") . "-" . ceil($dt->format("m") / 3)] = null;
				}
				
				return array(
						'lowerdate' => str_replace("-", "", $start),
						'upperdate' =>  str_replace("-", "", $end),
						'periodlist' => $periodList
				);

            default:
                if ($dateFromParam !== null) {
                    $start = new DateTime($dateFromParam);
                    $end = new DateTime($dateToParam);
                } else {
                    $startDate = date("Y-m-01", strtotime(date("Y-m-01") . " -12 month"));
                    $endDate = date("Y-m-01", strtotime("$startDate +12 month"));
                    $start = new DateTime($startDate);
                    $end = new DateTime($endDate);
                }

                $interval = new DateInterval('P1M');
                $period = new DatePeriod($start, $interval, $end);

                foreach ($period as $dt) {
                    $periodList[$dt->format("Y") . "-" . $dt->format("m")] = null;
                }

                return array(
                    'lowerdate' => $start->format('Ymd'),
                    'upperdate' => $end->format('Ymd'),
                    'periodlist' => $periodList
                );
		}
	}
	
	
	/**
	 * Given a period name, this function returns the lower and thus first date to be consider within this period
	 *   
	 * @param String $periodName
	 * @return String  the date in format yyyymmdd (eg 20170410)
	 */
	public static function getLowerDate($periodName)
	{
	    $periodList = self::getPeriodList($periodName);
	    return $periodList['lowerdate'];
	}
	
	
	/**
	 * Given a period name, this function returns the upper and thus last date to be consider within this period
	 *
	 * @param String $periodName
	 * @return String  the date in format yyyymmdd (eg 20170410)
	 */
	public static function getUpperDate($periodName)
	{
	    $periodList = self::getPeriodList($periodName);
	    return $periodList['upperdate'];
	}
	
	/**
	 * Report service response will matched to the periodlist array
	 * Also possible but not mandatory to add an inline function to do some extra calculation
	 * 
	 * @param array $periodList
	 * @param array $reply
	 * @param string $fieldName
	 * @param callable $func
	 * @return array[]
	 */
	public static function getSlicedData($periodList, $reply, $fieldName, callable $func = null)
	{
		$reportData = $periodList;
				
		foreach ($reply as $rec) {
		    if (array_key_exists($rec['slice'], $reportData)) {
                if (array_key_exists('slice', $rec)) {
                    if ($func) {
                        $reportData[$rec['slice']] = $func($rec[$fieldName]);
                    } else {
                        $reportData[$rec['slice']] = $rec[$fieldName];
                    }
                }
            }
		}

		return array_values($reportData);

	}
}