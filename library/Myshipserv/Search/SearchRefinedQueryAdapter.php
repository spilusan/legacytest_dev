<?php

/**
 * Adapter facilitating easy application of post-search refined query attributes
 * to wrapped search object.
 * 
 * @author Anthony Powell <apowell@shipserv.com>
 */
class Myshipserv_Search_SearchRefinedQueryAdapter
{
	private $db;
	private $search;
	
	public function __construct (Zend_Db_Adapter_Oracle $db, Myshipserv_Search_Search $search)
	{
		$this->db = $db;
		$this->search = $search;
	}
	
	/**
	 * Applies refined query data to wrapped search object.
	 * 
	 * @param array $refinedQuery Refined query data as returned by search service
	 */
	public function applyRefinedQuery ($refinedQuery)
	{
		// pull out the post-processed search terms
		if (@$refinedQuery['query'] != '') $this->search->setWhat($refinedQuery['query']);
		
		$postProCountryCode = @$refinedQuery['countryCode'];
		$postProPortCode    = @$refinedQuery['portCode'];
		
		// If a country code or port code has been set, it means there's some post-processing going on
		// A country code but no port code is set ...
		if ($postProCountryCode && !$postProPortCode) 
		{
			$countriesAdapter = new Shipserv_Oracle_Countries($this->db);
			$stResult = $countriesAdapter->fetchCountryByCode($postProCountryCode);
			
			$this->search->setText($stResult[0]['CNT_NAME']);
			$this->search->setWhere($postProCountryCode);
			$this->search->setCountry($postProCountryCode);
		}
		else if ($postProCountryCode && $postProPortCode)
		{
			// find the fullname of the country (used for vanity only)
			$countriesAdapter = new Shipserv_Oracle_Countries($this->db);
			$stResult = $countriesAdapter->fetchCountryByCode($postProCountryCode);
			$searchCountryName = $stResult[0]['CNT_NAME'];
			
			// find the full name of the port (used for vanity only)
			$portAdapter = new Shipserv_Oracle_Ports($this->db);
			$stResult = $portAdapter->fetchPortByCode($postProPortCode);
			$searchPortName = $stResult[0]['PRT_NAME'];
			
			$this->search->setText($searchPortName.', '.$searchCountryName);
			$this->search->setWhere($postProPortCode);
			$this->search->setCountry($postProCountryCode);
			$this->search->setPort($postProPortCode);
		}
		// No country or port code in the result, reset the searchText and the searchWhere
		else if (!$postProCountryCode && !$postProPortCode)
		{
			$this->search->setText('');
			$this->search->setWhere('');
		}
		
		$this->search->setType($refinedQuery['type']);
	}
}
