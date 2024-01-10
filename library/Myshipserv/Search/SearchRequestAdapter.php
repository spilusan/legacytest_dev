<?php

/**
 * Adapter facilitating easy application of request parameters to
 * wrapped search object.
 *
 * This class does not apply *all* request parameters, only those which behave
 * in the same way under all circumstances. For example, it does not apply
 * 'searchWhere' because this behaves differently when in a zone.
 * 
 * @author Anthony Powell <apowell@shipserv.com>
 */
class Myshipserv_Search_SearchRequestAdapter
{
	private $search;
	
	public function __construct (Myshipserv_Search_Search $search)
	{
		$this->search = $search;
	}
	
	/**
	 * Applies request parameters.
	 * 
	 * @param array $rawParams All, unprocessed request parameters
	 * @param array $cleanFormParams Subset of cleaned, validated parameters derived from search form
	 */
	public function setFromRequestParams ($rawParams, $cleanFormParams)
	{
		$this->search->setWhat(@$cleanFormParams['searchWhat']);
		$this->search->setStart(@$cleanFormParams['searchStart']);
		$this->search->setRows(@$cleanFormParams['searchRows']);
		$this->search->setCategoryRows(@$cleanFormParams['categoryRows']);
		$this->search->setMembershipRows(@$cleanFormParams['membershipRows']);
		$this->search->setCertificationRows(@$cleanFormParams['certificationRows']);
		
		$this->search->setType(@$rawParams['searchType']);
		$this->search->setRefinedBrandId(@$rawParams['refinedBrandId']);

		
		if (isset($rawParams['filters']) && is_array($rawParams['filters'])) {
			foreach ($rawParams['filters'] as $name => $valueArr) {
				if (is_array($valueArr)) {
					foreach ($valueArr as $value) $this->search->addFilter($name, $value);
				}
			}
		}
	}
}
