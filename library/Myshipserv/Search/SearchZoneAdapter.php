<?php

/**
 * Adapter facilitating easy application of zone-specific parameters
 * to wrapped search object. To be used when in a zone.
 * 
 * @author Anthony Powell <apowell@shipserv.com>
 */
class Myshipserv_Search_SearchZoneAdapter extends Myshipserv_Search_SearchLocationAdapter
{
	protected $search;
	protected $filters = array();
	protected $orFilters = array();
	protected $facets = array();
	
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
	 * Apply zone filter information to search object. Note that zone filters
	 * are translated into 'search filters' *and* into other search attributes.
	 * 
	 * @param array $filters Zone filter definition
	 */
	public function addZoneFilterDef ($filters)
	{
		if (is_array($filters))
		{
			foreach ($filters as $fieldName => $filter)
			{
				if ($fieldName == "searchWhere")
				{
					if (isset($filter['value']))
					{
						$this->search->setWhere($filter['value']);
					}
					if (isset($filter['searchText']))
					{
						$this->search->setText($filter['searchText']);
					}
					if (isset($filter['port']))
					{
						$this->search->setPort($filter['port']);
					}
					if (isset($filter['country']))
					{
						$this->search->setCountry($filter['country']);
					}
				}
				else
				{
					if (is_array($filter))
					{
						foreach($filter as $fieldValue)
						{
							$this->search->addFilter($fieldName, $fieldValue);
							$this->filters[$fieldName][$fieldValue] = true;
						}
					}
					else
					{
						$this->search->addFilter($fieldName, $filter);
						$this->filters[$fieldName][$filter] = true;
					}					
				}
			}
		}
	}

	/**
	 * Apply zone filter information to search object. Note that zone filters
	 * are translated into 'search filters' *and* into other search attributes.
	 *
	 * @param array $filters Zone filter definition
	 */
	public function addZoneOrFilterDef ($orFilters)
	{	
		if (is_array($orFilters))
		{
			foreach ($orFilters as $fieldName => $orFilter)
			{
				if (is_array($orFilter))
				{

					foreach($orFilter as $fieldValue)
					{
						$this->search->addOrFilter($fieldName, $fieldValue);
						$this->orFilters[$fieldName][$fieldValue] = true;
					}
				}
				else
				{
					$this->search->addOrFilter($fieldName, $orFilter);
					$this->orFilters[$fieldName][$orFilter] = true;
				}
			}
		}
	}

	/**
	 * Apply zone facets information to search object. 
	 *
	 * @param array $facets Zone filter definition
	 */
	public function addZoneFacetDef ($facets)
	{
		if (is_array($facets))
		{
			foreach ($facets as $fieldName => $facet)
			{
				if (is_array($facet))
				{

					foreach($facet as $fieldValue)
					{
						$this->search->addFacet($fieldName, $fieldValue);
						$this->facets[$fieldName][$fieldValue] = true;
					}
				}
				else
				{
					$this->search->addFacet($fieldName, $facet);
					$this->facets[$fieldName][$facet] = true;
				}
			}
		}
	}
	
	/**
	 * Returns search filters applied in easy-to-search format:
	 * 		[<name>][<value>] = true
	 * 		
	 * @return array
	 */
	public function getFilters ()
	{
		return $this->filters;
	}

	/**
	 * Returns search filters applied in easy-to-search format:
	 * 		[<name>][<value>] = true
	 *
	 * @return array
	 */
	public function getOrFilters ()
	{
		return $this->orFilters;
	}

	/**
	 * Returns search filters applied in easy-to-search format:
	 * 		[<name>][<value>] = true
	 *
	 * @return array
	 */
	public function getFacets ()
	{
		return $this->facets;
	}
}
