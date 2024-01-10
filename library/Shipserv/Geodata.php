<?php

/**
 * Geodata object
 * 
 * @package Shipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ Ltd
 */
class Shipserv_Geodata extends Shipserv_Object
{
	/**
	 *
	 * @var string
	 */
	protected $continent_code;
	
	/**
	 *
	 * @var string
	 */
	protected $country_code;
	
	/**
	 *
	 * @var string
	 */
	protected $region;
	
	/**
	 *
	 * @var string
	 */
	protected $region_name;
	
	/**
	 *
	 * @var string
	 */
	protected $city;
	
	/**
	 *
	 * @var string
	 */
	protected $dma_code;
	
	/**
	 *
	 * @var string
	 */
	protected $area_code;
	
	/**
	 *
	 * @var string
	 */
	protected $latitude;
	
	/**
	 *
	 * @var string
	 */
	protected $longitude;
	
	/**
	 *
	 * @var string
	 */
	protected $postal_code;
	
	/**
	 * Constructor for the geodata object. Pulls data from Apache.
	 * 
	 * @access public
	 */
    public function __construct ()
    {
		$unicode = new Shipserv_Unicode();

		$this->continent_code = $unicode->UTF8entities(apache_note('GEOIP_CONTINENT_CODE'));
		$this->country_code   = $unicode->UTF8entities(apache_note('GEOIP_COUNTRY_CODE'));
		$this->region         = $unicode->UTF8entities(apache_note('GEOIP_REGION'));
		$this->region_name    = $unicode->UTF8entities(apache_note('GEOIP_REGION_NAME'));
		$this->city           = $unicode->UTF8entities(apache_note('GEOIP_CITY'));
		$this->dma_code       = $unicode->UTF8entities(apache_note('GEOIP_DMA_CODE'));
		$this->area_code      = $unicode->UTF8entities(apache_note('GEOIP_AREA_CODE'));
		$this->latitude       = $unicode->UTF8entities(apache_note('GEOIP_LATITUDE'));
		$this->longitude      = $unicode->UTF8entities(apache_note('GEOIP_LONGITUDE'));
		$this->postal_code    = $unicode->UTF8entities(apache_note('GEOIP_POSTAL_CODE'));
	
	}
} 