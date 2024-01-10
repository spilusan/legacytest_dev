<?php

/**
 * Copies a search, removing zone-specific attributes.
 * 
 * @author Anthony Powell <apowell@shipserv.com>
 */
class Myshipserv_Search_SearchUnzoner
{
	private $zone;
	
	/**
	 * @param array $zone Zone definition
	 */
	public function __construct ($zone)
	{
		$this->zone = $zone;
	}
	
	/**
	 * Copies search object, removing zone-specific attributes.
	 * 
	 * @param Myshipserv_Search_Search $search
	 * @return Myshipserv_Search_Search
	 */
	public function cloneUnzonedSearch (Myshipserv_Search_Search $search)
	{
		$newSearch = clone $search;

		if (is_array($this->zone['content']['search']['filters']))
		{
			foreach ($this->zone['content']['search']['filters'] as $fieldName => $filter)
			{
				if ($fieldName == "searchWhere")
				{
					$newSearch->setWhere('');
					$newSearch->setText('');
					$newSearch->setCountry('');
					$newSearch->setPort('');
				}
				else
				{
					if (is_array($filter))
					{
						foreach($filter as $fieldValue)
						{
							$newSearch->removeFilter($fieldName, $fieldValue);
						}
					}
					else
					{
						$newSearch->removeFilter($fieldName, $filter);
					}
				}
			}
			
			//remove brand authorisations
			foreach ($newSearch->filters as $fieldName=>$fieldValue)
			{
				foreach ($fieldValue as $value=>$rubbish)
				{
					if ($fieldName == "brandAuth")
					{
						$newSearch->removeFilter($fieldName, $value);
					}
				}
				
			}

		}
		
		return $newSearch;
	}
}
