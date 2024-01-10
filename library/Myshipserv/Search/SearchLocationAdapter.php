<?php

/**
 * Adapter facilitating easy application of request parameters relating
 * to location to wrapped search object. To be used when *not* in a zone.
 * 
 * @author Anthony Powell <apowell@shipserv.com>
 */
class Myshipserv_Search_SearchLocationAdapter
{
	protected $search;
	
	/**
	 * @param Zend_Db_Adapter_Oracle $db DB connection
	 * @param Myshipserv_Search_Search $search Search object
	 */
	public function __construct (Zend_Db_Adapter_Oracle $db, Myshipserv_Search_Search $search)
	{
		$this->db = $db;
		$this->search = $search;
	}
	
	/**
	 * Applies 'searchText' and 'searchWhere' request parameters to
	 * wrapped search object. This is achieved by checking if 'searchWhere'
	 * is a country code, port code, or other, pulling location details
	 * back from the DB and updating multiple underlying search attributes.
	 * 
	 * @param string $searchText
	 * @return string $searchWhere
	 */
	public function setLocation ($searchText, $searchWhere)
	{
		$this->search->setText('');
		$this->search->setCountry('');
		$this->search->setPort('');
		$this->search->setWhere('');
		
		$searchText = trim($searchText);
		$searchWhere = trim($searchWhere);
		$lenSearchWhere = strlen($searchWhere);
		
		// Location field contains something and auto-complete field has 2 digit string: country specified
		if ($searchText != '' && $lenSearchWhere == 2) {
			$this->initByCountryCode($searchWhere);
		}
		
		// Location field contains something and auto-complete field has 6 digit string: port specified
		else if ($searchText != '' && $lenSearchWhere == 6) {
			$this->initByPortCode($searchWhere);
		}

		else if ($searchWhere != '') {
			$this->search->setWhere($searchWhere);
		}
		
		if ($searchText != '') {
			$this->search->setText($searchText);
		}
	}
	
	/**
	 * Helper method to handle case when initializing from country code.
	 * 
	 * @param string $searchCountry
	 */
	protected function initByCountryCode ($searchCountry)
	{
		// Capture country
		$this->search->setCountry($searchCountry);
		
		// Form beautified location string
		$countriesAdapter = new Shipserv_Oracle_Countries($this->db);
		$result = $countriesAdapter->fetchCountryByCode($searchCountry);
		$this->search->setText($result[0]['CNT_NAME']);
	}
	
	/**
	 * Helper method to handle case when initializing from port code.
	 * 
	 * @param string $searchPort
	 */
	protected function initByPortCode ($searchPort)
	{
		// Capture port
		$this->search->setPort($searchPort);
		
		// Capture country
		$whereData     = split('-', $searchPort);
		$this->search->setCountry($searchCountry = $whereData[0]);
		
		// Find the full name of the country (used for vanity only)
		$countriesAdapter = new Shipserv_Oracle_Countries($this->db);
		$result = $countriesAdapter->fetchCountryByCode($searchCountry);
		$searchCountryName = $result[0]['CNT_NAME'];
		
		// Find the full name of the port (used for vanity only)
		$portAdapter = new Shipserv_Oracle_Ports($this->db);		
		$result = $portAdapter->fetchPortByCode($searchPort);		
		$searchPortName = $result[0]['PRT_NAME'];
		
		// Form beautified location string
		$this->search->setText($searchPortName.', '.$searchCountryName);
	}
}
