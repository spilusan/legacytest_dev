<?php
/**
* List of countries
*/
class Shipserv_Report_Usage_Countries
{

	protected $timezones;

	/**
	* List of PHP timezones
	* @return array List of timezones
	*/
	public function getCountries() 
	{

  		$db = Shipserv_Helper_Database::getDb();
        
        $sql = "
			SELECT
				cnt_country_code,
				cnt_name
			FROM
				country
			ORDER BY
				cnt_name";

        $countries = $db->fetchAll($sql);
        //Camel Casing

        $data = array();
        foreach ($countries as $rec) {
        	$data[] = array(
        			'CntCountryCode' => $rec['CNT_COUNTRY_CODE'],
        			'CntName' => $rec['CNT_NAME']
        		);
        }
        
        return $data;

	}

}