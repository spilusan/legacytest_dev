<?php
/**
* List timezones
*/
class Shipserv_Report_Usage_Timezones
{

	protected $timezones;

	/**
	* List of PHP timezones
	* @return array List of timezones
	*/
	public function getTimezones() 
	{
		$data = array();

	    if ($this->timezones === null) {
	        $timezones = array();
	        $offsets = array();
	        $now = new DateTime();

	        foreach (DateTimeZone::listIdentifiers() as $timezone) {
	            $now->setTimezone(new DateTimeZone($timezone));
	            $offsets[] = $offset = $now->getOffset();
	            $timezones[$timezone] = '(' . $this->formatGmtOffset($offset) . ') ' . $this->formatTimezoneName($timezone);
	        }
	       
	        array_multisort($offsets, $timezones);

	        foreach ($timezones as $id => $value) {
	        	$data[] = array(
	        			'id' => $id,
	        			'name' => $value
	        		);
	        }
	    }

	    return $data;
	}

	/**
	* Reformat GMT
	* @param string $offset GMT Offset
	* @return string Reformatted GMT offset
	*/
	protected function formatGmtOffset($offset)
	{
	    $hours = intval($offset / 3600);
	    $minutes = abs(intval($offset % 3600 / 60));
	    return 'GMT' . ($offset ? sprintf('%+03d:%02d', $hours, $minutes) : '');
	}

	/**
	* Reformat timezone name
	* @param string $name Timezone Name
	* @return string reformatted timezone name
	*/
	protected function formatTimezoneName($name)
	{
	    $name = str_replace('/', ', ', $name);
	    $name = str_replace('_', ' ', $name);
	    $name = str_replace('St ', 'St. ', $name);
	    return $name;
	}
}