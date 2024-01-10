<?php

/**
 * Helper class that encapsulates logic for selecting list of options of given
 * type (categories, memberships, brands) in the search box on result pages.
 *  *
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 */
class Myshipserv_Controller_Action_Helper_SearchBoxOptions extends Zend_Controller_Action_Helper_Abstract
{
    
	//search result
	private $result;

	//zone filters
	private $zoneFilters;

	//zone options
	private $zoneOptions;

	//search parameters
	private $searchParams;

	//config
	private $config;

	public function initEnv ($result, $zoneFilters, $zoneOptions, $searchParams)
	{
		
		$this->config = $this->_actionController->getInvokeArg('bootstrap')->getOptions();

		$this->result = $result;
		$this->zoneFilters = $zoneFilters;
		$this->zoneOptions = $zoneOptions;
		$this->searchParams = $searchParams;
	}

	/**
	 * Returns array of options to be displayed for given filter type
	 *
	 * @param array $typenames Associative array with names to use for filter type . Example: array ('singular'=>'category','plural' => 'categories')
	 *
	 * @return array
	 */
	public function getTypeOptions($typeNames)
	{
	    if (!$typeNames || !isset($typeNames['plural'])) {
	        return array();
	    }
		$this->config = $this->_actionController->getInvokeArg('bootstrap')->getOptions();

		//initialize output array
		$options = array();
		//get number of options to be displayed for given type
		$maxOptions = $this->config['shipserv']['search'][$typeNames['plural']]['maximum'];
		
		//lets loop through options returned in the result
		foreach ((Array) $this->result[$typeNames['plural']] as $resultOption) {
			//we should not include options that are zone filters
			if (!isset($this->zoneFilters[$typeNames['singular'].'Id'][$resultOption['id']])) {
				//lets see if zone has some setting for options for this filter type
				if (isset($this->zoneOptions[$typeNames['singular']]) and count($this->zoneOptions[$typeNames['singular']])>0) {
					//we have specific options for this filter type, so we will add only deined option or this option was selected in search previously
					if ((is_array($this->searchParams['filters'][$typeNames['singular'].'Id']) and in_array($resultOption['id'], $this->searchParams['filters'][$typeNames['singular'].'Id'])) or (isset($this->zoneOptions[$typeNames['singular']][$resultOption['id']]) and count($options) < $maxOptions)) {
						$options[] = $resultOption;
					}
				} else {
					// we do not have specific seach filter options, so lets append option if we below maximum treshhold or this option was selected in search previously
					if (count($options) < $maxOptions or (is_array($this->searchParams['filters'][$typeNames['singular'].'Id']) and in_array($resultOption['id'], $this->searchParams['filters'][$typeNames['singular'].'Id']))) {
						$options[] = $resultOption;
					}
				}
			}
		}
		
		return $options;
	}
	
	
	/**
	 * Factory for appropriate option type's adapter
	 *
	 * @param string $typeName
	 */
	private function getTypeAdapter ($typeName)
	{
		$db = $this->_actionController->getInvokeArg('bootstrap')->getResource('db');
		switch ($typeName)
		{
			case 'categories':
				return new Shipserv_Oracle_Categories($db);
				break;
			case 'memberships':
				return new Shipserv_Oracle_Memberships($db);
				break;
			case 'brands':
				return new Shipserv_Oracle_Brands($db);
				break;
			case 'certifications':
				return new Shipserv_Oracle_Certifications($db);
				break;
			default:
				break;
		}
	}

	/**
	 * Cache results
	 *
	 */
	public function cacheOptions ()
	{
		
		$cacheOptionsId = mt_rand();
		$cacheTTL = 1800;
		$memcache = new Memcache();
		$memcache->connect($this->config['memcache']['server']['host'],
						   $this->config['memcache']['server']['port']);

		$memcache->set("autocompete_categories_".$cacheOptionsId, $this->result['categories'], false, $cacheTTL);
		$memcache->set("autocompete_brands_".$cacheOptionsId, $this->result['brands'], false, $cacheTTL);
		$memcache->set("autocompete_memberships_".$cacheOptionsId, $this->result['memberships'], false, $cacheTTL);
		$memcache->set("autocompete_certifications_".$cacheOptionsId, $this->result['certifications'], false, $cacheTTL);

		return $cacheOptionsId;

	}

	/**
	 * Prepares array for autocomplete search - adds "value" element to results array
	 *
	 * @return array
	 */

	public function getArrayForAutoComplete ($typeName,$searchTerm,$optionsCacheId)
	{

		$this->config = $this->_actionController->getInvokeArg('bootstrap')->getOptions();
		
		//initialize output array
		$results = array();

		//prepare search words
		$words   = split(' ', trim($searchTerm));

		//retrieve array of options from memcache
		$memcache = new Memcache();
		$memcache->connect($this->config['memcache']['server']['host'],
						   $this->config['memcache']['server']['port']);

		$options = $memcache->get('autocompete_'.$typeName.'_'.$optionsCacheId);


		//lets loop through options returned in the result
		foreach ((array)$options as $resultOption)
		{


			$includeOption = true;
			if (!empty($searchTerm))
			{
				foreach ($words as $word)
				{
					if (stripos($resultOption['name'],$word)===FALSE)
					{
						$includeOption = false;
					}
				}
			}
			
			if ($includeOption)
			{
				$display = $resultOption['name'];
				if (!empty($searchTerm))
				{
					foreach ($words as $word)
					{
						$display = preg_replace('/('.$word.')/i', '<em>$1</em>', $display);
					}
				}
				$resultOption['display'] = $display .' ('. $resultOption['documentsFound'] .')';
				$resultOption['value'] = $resultOption['name'];
				$results[] = $resultOption ;
			}
			
		}

		return $results;
	}

}
