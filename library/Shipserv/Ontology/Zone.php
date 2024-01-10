<?php

/**
 * Class for reading the ontology within the context of zones
 *
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Ontology_Zone extends Shipserv_Ontology
{
	/**
	 * The URI of the current selected zone
	 * 
	 * @var string
	 * @access private
	 */
	private $uri;
	
	/**
	 * Constructor - just sets the URI
	 * 
	 * @access public
	 * @param string $uri The URI of the zone
	 */
	public function __construct ($uri)
	{
		$this->uri = $uri;
	}
	
	
	
	/**
	 * Matches a keyword to one or more zones, and returns the zone URI, the
	 * zone name, and the zone content XML filename
	 * 
	 * @access public
	 * @param string $searchString The keyword to match to a zone
	 * @todo Update wildcard matching for zone name
	 * @return array
	 */
	public static function matchKeyword ($searchString)
	{
		$keywords = explode(' ', $searchString);
		
		if (is_array($keywords))
		{
			$ontologyAdapter = self::getAdapter();
			$matchedZones    = array();
			
			foreach ($keywords as $keyword)
			{
				$sparql = 'SELECT ?zone ?zoneName ?zoneContent
							WHERE { ?product <http://purl.org/dc/elements/1.1/title> ?title FILTER regex(str(?title), "'.$keyword.'", "i") .
									?product <http://rdf.myshipserv.com/schema/zone> ?zone .
									?zone <http://purl.org/dc/elements/1.1/title> ?zoneName .
									?zone <http://rdf.myshipserv.com/schema/content> ?zoneContent }';
				
				$result = $ontologyAdapter->query($sparql);
				
				if (is_array($result))
				{
					$matchedZones = array_merge($matchedZones, $result);
				}
			}
		}
		
		return $matchedZones;
	}
	
	public static function matchZone ($zone)
	{
		$uri = "http://rdf.myshipserv.com/ontology.rdf#".$zone;
		
		$sparql = 'SELECT ?zoneName ?zoneContent
					WHERE { <'.$uri.'> <http://purl.org/dc/elements/1.1/title> ?zoneName .
							<'.$uri.'> <http://rdf.myshipserv.com/schema/content> ?zoneContent }';
		
		$ontologyAdapter = self::getAdapter();
		$result = $ontologyAdapter->query($sparql);
		
		$matchedZones = array();
		if (is_array($result))
		{
			foreach ($result as $zoneData)
			{
				$matchedZones[$uri] = array('name'       => $zoneData['zoneName'],
											'contentXml' => $zoneData['zoneContent']);
			}
		}
		
		return $matchedZones;
	}
	
	public static function matchSynonymToZone ($synonym)
	{
		$stringHelper = new Myshipserv_View_Helper_String();
		
		$keywords = $stringHelper->createKeywordPhrases($synonym);
		
		if (is_array($keywords))
		{
			$ontologyAdapter = self::getAdapter();
			$matchedZones    = array();
			
			foreach ($keywords as $keyword)
			{
				$keyword = str_replace(',', '', $keyword);
				if (trim($keyword))
				{
					$sparql = 'SELECT ?zone ?zoneName ?zoneContent
								WHERE { ?syn a <http://rdf.myshipserv.com/schema/synonym> .
										?syn <http://purl.org/dc/elements/1.1/title> ?title FILTER regex(str(?title), "^'.$keyword.'$", "i") .
										?syn <http://rdf.myshipserv.com/schema/synonymOf> ?keyword .
										?keyword <http://rdf.myshipserv.com/schema/zone> ?zone .
										?zone <http://purl.org/dc/elements/1.1/title> ?zoneName .
										?zone <http://rdf.myshipserv.com/schema/content> ?zoneContent }';
					
					$result = $ontologyAdapter->query($sparql);
					
					if (is_array($result))
					{
						$matchedZones = array_merge($matchedZones, $result);
					}
				}
			}
			
			// now reformat the array into something a bit more useable
			$zones = array();
			foreach ($matchedZones as $zone)
			{
				$zones[$zone['zone']] = array('name'       => $zone['zoneName'],
											  'contentXml' => $zone['zoneContent']);
			}
		}
		
		return $zones;
	}
	
	/**
	 * Fetch the brands for a specific zone
	 *
	 * @access public
	 * @return array
	 */
	public function fetchBrands ()
	{
		$sparql = 'SELECT *
					WHERE { ?brand a <http://rdf.myshipserv.com/schema/brand> .
							?brand <http://rdf.myshipserv.com/schema/zone> <'.$this->uri.'> .
							?brand <http://purl.org/dc/elements/1.1/title> ?brandname }';
		
		return self::getAdapter()->query($sparql);
	}
	
	/**
	 * Fetch the product types for a specific zone
	 *
	 * @access public
	 * @return array
	 */
	public function fetchProductTypes ()
	{
		$sparql = 'SELECT *
					WHERE { ?producttype a <http://rdf.myshipserv.com/schema/producttype> .
							?producttype <http://rdf.myshipserv.com/schema/zone> <'.$this->uri.'> .
							?producttype <http://purl.org/dc/elements/1.1/title> ?producttypename . } ';
		
		return self::getAdapter()->query($sparql);
	}
	
	/**
	 * Fetch supplier specialisations appropriate to this zone
	 * 
	 * @access public
	 * @return array
	 */
	public function fetchSpecialisations ()
	{
		$sparql = 'SELECT *
					WHERE { ?specialisation a <http://rdf.myshipserv.com/schema/supplierspecialisation> .
							?specialisation <http://rdf.myshipserv.com/schema/zone> <'.$this->uri.'> .
							?specialisation <http://purl.org/dc/elements/1.1/title> ?specialisationname . } ';
		
		return self::getAdapter()->query($sparql);
	}
	
	/**
	 * Fetch supplier certifications appropriate to this zone
	 * 
	 * @access public
	 * @return array
	 */
	public function fetchCertifications ()
	{
		$sparql = 'SELECT *
					WHERE { ?certification a <http://rdf.myshipserv.com/schema/suppliercertification> .
							?certification <http://rdf.myshipserv.com/schema/zone> <'.$this->uri.'> .
							?certification <http://purl.org/dc/elements/1.1/title> ?certificationname . } ';
		
		return self::getAdapter()->query($sparql);
	}
	
	/**
	 * Fetch product attributes (as filters) appropriate to this zone
	 * 
	 * @access public
	 * @return array
	 */
	public function fetchFilters ()
	{
		$sparql = 'SELECT *
					WHERE { ?pfiltertype a <http://rdf.myshipserv.com/schema/productattributetype> .
							?pfiltertype <http://rdf.myshipserv.com/schema/zone> <'.$this->uri.'> .
							?pfiltertype <http://purl.org/dc/elements/1.1/title> ?pfiltertypename .
							?pfilter <http://rdf.myshipserv.com/schema/productattributetype> ?pfiltertype .
							?pfilter <http://purl.org/dc/elements/1.1/title> ?pfiltername }';
		
		return self::getAdapter()->query($sparql);
	}
}