<?php

/**
 * Class for handling zones
 *
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Zone extends Shipserv_Zone_Db
{

    /**
     * Matches a set of keywords to a zone, or a specific zone override, and
     * fetches the content
     *
     * @access public
     * @static
     * @param string $keywords Keywords to match zones to, separated by spaces
     * @param string $zone A zone override
     * @return array
     */
    public static function fetchZoneData($keywords, $zone = null) {
		$zoneData = parent::getZoneData();
		$zoneSynonyms = parent::getZoneKeyword();

        /**
         * Check the ontology for any specific search terms.
         * This will allow us to dump someone in a zone, and also set the
         * search type correctly
         */
        $matchedZones = false;

        if ($zone && $zoneData[$zone]) {
			$matchedZones[$zone] = $zoneData[$zone];
        } else {
            // match the zones
            $stringHelper = new Myshipserv_View_Helper_String();
			
			$keywordsArray = array_map(
				function($keyword){
					return strtolower($keyword);
				},
				$stringHelper->createKeywordPhrases($keywords, " ", true)
			);

            if (is_array($keywordsArray)) {
                $matchedZones = array();
                foreach ($keywordsArray as $keyword) {
                    $keyword = trim(str_replace(',', '', $keyword));

                    if (strlen($keyword) >= 2) {
                        foreach ($zoneSynonyms as $zoneId) {
							$zoneSynonym = strtolower(trim($zoneId['keyword']));
                            if ($zoneSynonym === $keyword) {
                                if (!isset($matchedZones[$zoneId['system_name']]) and count($matchedZones) < 3) {
									$isFullMatch = ($zoneSynonym  === strtolower($keywords)) ? true : false;
									$matchedZones[$zoneId['system_name']] = $zoneData[$zoneId['system_name']];
									$matchedZones[$zoneId['system_name']]['auto_redirect'] = $zoneId['auto_redirect'];
									$matchedZones[$zoneId['system_name']]['full_match'] = $isFullMatch;
                                }
                            }
                        }
                    }
                }
            }
			
			//lets find phrase matches
            foreach ($zoneSynonyms as $zoneId) {
				$zoneSynonym = strtolower(trim($zoneId['keyword']));
                $keywords = strtolower(trim($keywords));
				//phrase always has space
                if (strpos($zoneSynonym, " ") !== false) {
                    if (strpos($keywords, $zoneSynonym) !== false) {
                        if (!isset($matchedZones[$zoneId['system_name']]) and count($matchedZones) < 3) {
							$matchedZones[$zoneId['system_name']] = $zoneData[$zoneId['system_name']];
							$matchedZones[$zoneId['system_name']]['auto_redirect'] = $zoneId['auto_redirect'];
							//$matchedZones[$zoneId['system_name']]['full_match'] = false;
						}
                    }
                }
            }
        }

        /**
         * Now put the matched zones into an array for view consumption.
         * If something exists within multiple zones, we may want to put
         * some logic in here to decide which zone to actually put them in
         *
         * This should probably move into a generic Zone object
         */
        $zones = array();

        // don't forget to comment this out.
       // $matchedZones['chandlers'] = array('name' => 'Chandlers','contentXml' => 'chandlers.xml');
        if (is_array($matchedZones)) {
            foreach ($matchedZones as $zoneName => $zone) {

                //$zoneOntology = new Shipserv_Ontology_Zone($zoneUri);

                /**
                 * Fetch the content XML file for the zone and put it into
                 * an array so we don't need to process it within the
                 * controller. The view can just handle it - nice and
                 * simple
                 */
                if ($contentXML = parent::getZoneXmlObject($zoneName)) {
                    // after integration with the app
                    //$contentXML = simplexml_load_file($config->includePaths->library  . "/zones/" . $zone['contentXml']);
                    //$contentXML = parent::getZoneXmlObject($zoneName);
                    $content = Shipserv_Helper_Xml::simpleXml2Array($contentXML);
                    $zoneUrl = self::returnZoneToCategoryURL($zoneName);
                }

                $zones[$zoneName] = array(
					'name' => $zone['name'],
					'autoRedirect' => $zone['auto_redirect'],
					'fullMatch' => isset($zone['full_match']) ? $zone['full_match'] : false,
                    'content' => $content,
                    'zoneUrl' => $zoneUrl
                );
            }
        }

		return $zones;
    }

    /*
     * This function will return a mapping of a category to a related zone (e.g. ship-chandlery category to the chandlers zone)
     * We dont want to use sysnonyms for this as we need to keep the acessible URLS for a zone to a minimum to reduce duplication of
     * Data
     * @param  $cat string - The category/brand/port we want to translate/map to its zone./
     * @return string. return false if no match
     */

    public static function returnCategoryToZoneMapping($cat) {
        if (!empty($cat)) {
			$mapArray = parent::getZoneMapping();
            return array_key_exists(strtolower($cat), $mapArray) ? $mapArray[strtolower($cat)] : false;
        } else {
            return false;
        }
    }

    public static function returnZoneToCategoryURL($zone) {
        $urlArray = parent::getUrlToZone(); // zoneDb

        if (!empty($zone)) {

            return array_key_exists($zone, $urlArray) ? $urlArray[$zone] : false;
        } else {
            return false;
        }
    }

    public static function returnZoneToCategoryMapping($zone) {
        if (!empty($zone)) {
            $retVal = array_search(strtolower($zone), parent::getZoneMapping());
            return $retVal !== false ? $retval : $zone;
        } else {
            return $zone;
        }
    }

    /**
     * Refactored code to produce 2 random zones with associated data.
     * @param Integer $retCount How many zones to return
     * @return type
     */
    public static function returnRandomZones($retCount = 2){
        //$zoneAds = self::$enabledZones;		// select 2 random zone ads
		$zoneAds = parent::getZoneDataForHomepage();
        //Ensure we dont have too many zones
        if($retCount > count($zoneAds)){
        	$selectNumber = count($zoneAds);
        }else{
        	$selectNumber = $retCount;
        }

		$keys = array_keys($zoneAds);
		shuffle($keys);
		$selectedAdKeys = array_slice($keys, 0, $retCount);
		$selectedAds = array();

		foreach ($selectedAdKeys as $key)
		{
			$selectedAds[$key] = $zoneAds[$key];
        	$selectedAds[$key]['zoneUrl'] = self::returnZoneToCategoryURL($key);
		}

        return $selectedAds;
    }

    public static function showZoneSponsorshipBanner($categoryId)
    {
    	$db = parent::getDb();
    	$sql = "SELECT COUNT(*) FROM pages_zone_sponsorship WHERE pzs_category_id=:categoryId";
    	return ($db->fetchOne($sql, array('categoryId' => $categoryId))>0);
    }

    public static function getZoneSponsorshipBanner($refinedQuery)
    {
    	// add random to each block
    	$db = parent::getDb();
    	$params['categoryId'] = $refinedQuery['id'];

    	$sqlCheckingDate = "
					AND
    				(
    					(
    						pzs_start_date IS null
    						AND pzs_end_date IS null
    						AND pzs_is_active=1
    					)
    					OR
    					(
    						pzs_start_date IS NOT null
    						AND pzs_end_date IS NOT null
    						AND sysdate BETWEEN pzs_start_date AND pzs_end_date
    						AND pzs_is_active=1
    					)
    					OR
    					(
    						pzs_start_date IS null
    						AND pzs_end_date IS NOT null
    						AND sysdate < pzs_end_date
    						AND pzs_is_active=1
    					)
    					OR
    					(
    						pzs_start_date IS NOT null
    						AND pzs_end_date IS null
    						AND sysdate > pzs_start_date
    						AND pzs_is_active=1
    					)
    				)
    	";

    	// getting banners for category globally
    	$sql[] = "
    		SELECT 	pages_zone_sponsorship.*, 4 + dbms_random.value(0.1,1) priority
    		FROM 	pages_zone_sponsorship
    		WHERE 	pzs_category_id=:categoryId
    				AND pzs_tnid IS NOT null
    				AND pzs_prt_port_code IS null
    				AND pzs_cnt_country_code IS null
    	" . $sqlCheckingDate;
    	$debug[] = "GLOBAL";
    	// this won't be executed
        if( $refinedQuery['portCode'] != "" && $refinedQuery['countryCode'] == "" )
    	{
    		$debug[] = "port and empty country";
    		$sql[] = "
    		SELECT 	pages_zone_sponsorship.*, 3 + dbms_random.value(0.1,1) priority
    		FROM 	pages_zone_sponsorship
    		WHERE 	pzs_category_id=:categoryId
    				AND pzs_tnid IS NOT null
    				AND pzs_prt_port_code=:portCode
    				AND pzs_cnt_country_code IS null
    		" . $sqlCheckingDate;
    		$params['countryCode'] = $refinedQuery['countryCode'];
    	}

    	// country level
        else if( $refinedQuery['portCode'] == "" && $refinedQuery['countryCode'] != "" )
    	{
    		$debug[] = "country specified";
    		$sql[] = "
    		SELECT 	pages_zone_sponsorship.*, 2 + dbms_random.value(0.1,1) priority
    		FROM 	pages_zone_sponsorship
    		WHERE 	pzs_category_id=:categoryId
    				AND pzs_tnid IS NOT null
    				AND pzs_prt_port_code IS null
    				AND pzs_cnt_country_code=:countryCode
    		" . $sqlCheckingDate;
    		$params['countryCode'] = $refinedQuery['countryCode'];
    	}

		// port level
        else if( $refinedQuery['portCode'] != "" && $refinedQuery['countryCode'] != "" )
    	{
    		$debug[] = "country and port specified, ";
    		$sql[] = "
    		SELECT 	pages_zone_sponsorship.*, 2 + dbms_random.value(0.1,1) priority
    		FROM 	pages_zone_sponsorship
    		WHERE 	pzs_category_id=:categoryId
    				AND pzs_tnid IS NOT null
    				AND pzs_prt_port_code IS null
    				AND pzs_cnt_country_code=:countryCode
    		" . $sqlCheckingDate;
    		$params['countryCode'] = $refinedQuery['countryCode'];

    		$sql[] = "
    		SELECT 	pages_zone_sponsorship.*, 1 + dbms_random.value(0.1,1) priority
    		FROM 	pages_zone_sponsorship
    		WHERE 	pzs_category_id=:categoryId
    				AND pzs_tnid IS NOT null
    				AND
    				(
    					(	pzs_prt_port_code=:portCode AND pzs_cnt_country_code=:countryCode	)
    					--OR (	pzs_prt_port_code IS null AND pzs_cnt_country_code=:countryCode	)
    				)
    		" . $sqlCheckingDate;
    		$params['portCode'] = $refinedQuery['portCode'];
    		$params['countryCode'] = $refinedQuery['countryCode'];
    	}

    	$query = "SELECT * FROM (" . implode(" UNION ALL ", $sql) . ") ORDER BY priority ASC";
    	//echo "<hr />".implode("<br />", $debug)  . "<hr />";
    	//echo ( $query );
    	$result = $db->fetchAll($query, $params);
    	return $result;
    }

    public function storeXmlToMemcache(){
    	$content = Shipserv_Helper_Xml::simpleXml2Array($contentXML);
    	foreach (self::$zoneData as $zoneName => $zone) {

    		/**
    		 * Fetch the content XML file for the zone and put it into
    		 * an array so we don't need to process it within the
    		 * controller. The view can just handle it - nice and
    		 * simple
    		 */
    		if ($zone['contentXml']) {
    			// after integration with the app
    			$contentXML = simplexml_load_file($config->includePaths->library  . "/zones/" . $zone['contentXml']);
    			$content = Shipserv_Helper_Xml::simpleXml2Array($contentXML);
    		}
    	}
    }

    public static function performCheckOnPorts( $zones, $refinedQuery )
    {
    	$debug = false;
    	if( $debug === true ) print_r($zones);
    	if( $debug === true ) echo "<br /><br /><br /><br />ZONE FOUND: " . count($zones);

    	$portCode = $refinedQuery['portCode'];
    	$countryCode = $refinedQuery['countryCode'];

    	if( $portCode == "" && $countryCode == "" )
    	{
    		return $zones;
    	}

    	foreach($zones as $name => $zone)
    	{
    		if( $zone['content']['supportForPortCheckingForEachSupplierListed'] == "1")
    		{
    			foreach( $zone['content']['search']['orFilters']['id'] as $id )
    			{
    				$supplier = Shipserv_Supplier::getInstanceById($id);

    				foreach( $supplier->ports as $r )
    				{
    					$allowedPort[$r['code']] = true;
    					// getting unique list of countrycode
    					$tmp = explode("-", $r['code']);
    					$allowedCountry[$tmp[0]] = true;
    				}
    			}

    			$allowedPort = array_keys($allowedPort);
    			$allowedCountry = array_keys($allowedCountry);

    			if( $portCode != "" && $countryCode != "" )
    			{
	    			if( in_array($portCode, $allowedPort) !== false )
	    			{
	    				if( $zone['content']['forceToShowSingleZone'] == "1" )
	    				{
	    					$newZones = array($name => $zone);

	    				}
	    				else
	    				{
	    					$newZones[][$name] = $zone;
	    				}
	    			}
    			}
    			else if( $portCode == "" && $countryCode != "" )
    			{
    				if( in_array($countryCode, $allowedCountry) !== false )
    				{
    					if( $zone['content']['forceToShowSingleZone'] == "1" )
	    				{
	    					$newZones = array($name => $zone);
	    				}
	    				else
	    				{
	    					$newZones[][$name] = $zone;
	    				}
    				}
    			}
    		}
    		else
    		{
    			$newZones[$name] = $zone;
    		}

    		if( $zone['content']['forceToShowSingleZone'] == "1" && count($newZones) == 1 )
    		{
    			break;
    		}
    	}

    	if( $debug === true ) echo "<br /><br /><br /><br />CURRENT PORT AND COUNTRY: " . $countryCode . " ______" . $portCode;

    	if( $debug === true ) echo "<br /><br /><br /><br />COUNTRY: ";
    	if( $debug === true ) print_r($allowedCountry);

    	if( $debug === true ) echo "<br /><br /><br /><br />PORT: ";
    	if( $debug === true ) print_r($allowedPort);

    	if( $debug === true ) echo "<br /><br /><br /><br />AFTER: ";
    	if( $debug === true ) print_r($newZones);

    	if( $debug === true ) echo "<br /><br /><br /><br />ZONE FOUND: " . count($newZones);
    	return $newZones;
    }

}
