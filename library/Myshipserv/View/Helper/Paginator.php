<?php

/**
 * Helper class for creating pagination
 *
 * @package myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Myshipserv_View_Helper_Paginator extends Zend_View_Helper_Abstract
{
	
	/**
	 * Sets up the paginator helper
	 *
	 * @access public
	 * @return Myshipserv_View_Helper_Paginator
	 */
	public function paginator ()
	{
		return $this;
	}
	
	public function init ()
	{
		
	}
	
	public function setView(Zend_View_Interface $view)
    {
        $this->view = $view;
    }
	
	/**
	 * Creates a pagination block
	 * 
	 * @access public
	 */
	public function create ($numResults, $currentOffset = 0, $searchParameters = array(), $options = array())
	{
		$resultsPerPage = (isset($options['resultsPerPage'])) ? $options['resultsPerPage'] : 10;
		$currentPage    = (($currentOffset + $resultsPerPage) / $resultsPerPage) ? (($currentOffset + $resultsPerPage) / $resultsPerPage) : 1;
		$numPagesShown  = (isset($options['numPagesShown'])) ? $options['numPagesShown'] : 5;
		$totalPages     = ceil($numResults / $resultsPerPage);
		
		$pageRangeMin  = $currentPage - floor($numPagesShown / 2);
		$pageRangeMin  = $pageRangeMin < 1 ? 1 : $pageRangeMin;
		
		$pageRangeMax  = $pageRangeMin + $numPagesShown - 1;
		$pageRangeMax  = $pageRangeMax > $totalPages ? $totalPages : $pageRangeMax;
		
		$logSearchId = (isset($options['logSearchId'])) ? $options['logSearchId'] : null;
		
		$searchType = ($searchParameters['searchType'] == 'company') ? 'company' : 'product';
		
		// fetch a URL helper with all the current search terms attached, so we
		// just have to paginate() it for each page
		$url = $this->view->searchUrl()->searchWhat(html_entity_decode($searchParameters['searchWhat']))
									   ->searchWhere($searchParameters['searchWhere'])
									   ->searchText(html_entity_decode($searchParameters['searchText']))
									   ->searchType($searchType)
									   ->zone($searchParameters['zone'])
									   ->categoryId($searchParameters['categoryId'])
									   ->brandId($searchParameters['brandId'])
									   ->brandAuth($searchParameters['brandAuth'])
									   ->logSearchId($logSearchId)
									   ->refinedBrandId($searchParameters['refinedBrandId']);
		
		if (is_array($searchParameters['filters']))
		{
			foreach ($searchParameters['filters'] as $filterType => $filters)
			{
				// this seems hacky
				switch ($filterType)
				{
					case 'categoryId':
						$url->addFilterCategoryArr(array_keys((array) $filters)); 
					break;
					
					case 'membershipId':
						$url->addFilterMembershipArr(array_keys((array) $filters));
					break;
					case 'certificationId':
						$url->addFilterCertificationArr(array_keys((array) $filters));
					break;
					case 'brandId':
						$url->addFilterBrandArr(array_keys((array) $filters));
					break;
					case 'brandAuth':
						$url->addFilterBrandAuthArr(array_keys((array) $filters));
					break;
					case 'AABrandId':
						$url->addFilterAABrandArr(array_keys((array) $filters));
					break;
					case 'AIRBrandId':
						$url->addFilterAIRBrandArr(array_keys((array) $filters));
					break;
					case 'OEMBrandId':
						$url->addFilterOEMBrandArr(array_keys((array) $filters));
					break;
				}
			}
		}
		
		$pages = array();
		if ($totalPages > 1)
		{
			if ($currentPage > 1) // show a previous link
			{
				$pages['previous'] = array('url'   => (string) $url->paginate($currentOffset - $resultsPerPage, $resultsPerPage),
										   'class' => $prev);
			}
			
			if ($pageRangeMin >= 2) // always add a page 1 link if there isn't going to be one naturally
			{
				$pages['pages'][1] = array('url'   => (string) $url->paginate(0, $resultsPerPage),
										   'class' => 'page',
										   'suffixEllipses' => ($pageRangeMin > 2) ? true : false); // add ellipses block if after page 2
			}
			
			for ($page = $pageRangeMin; $page <= $pageRangeMax; $page++)
			{
				$class = ($page == $currentPage) ? 'current' : 'page';
				$pages['pages'][$page] = array('url'   => (string) $url->paginate(($page - 1) * $resultsPerPage, $resultsPerPage),
											   'class' => $class);
			}
			
			if ($pageRangeMax < $totalPages)
			{
				$pages['pages'][$totalPages] = array('url'   => (string) $url->paginate(($totalPages - 1) * $resultsPerPage, $resultsPerPage),
													 'class' => 'page',
													 'prefixEllipses' => ($pageRangeMax < $totalPages - 1) ? true : false);
			}
			
			if ($currentPage < $totalPages)
			{
				$pages['next'] = array('url'   => (string) $url->paginate($currentOffset + $resultsPerPage, $resultsPerPage),
									   'class' => $prev);
			}
		}
		
		return $pages;
	}

	/**
	 * Generates paginator marks from paginator object
	 *
	 * Copied from Match helper trait to use it in Pages as well
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-10-11
	 * @story   S18245
	 *
	 * @param   Zend_Paginator  $paginator
	 * @param   int             $marksAroundCurrent
	 *
	 * @return array
	 */
	public function getPaginatorMarks(Zend_Paginator $paginator, $marksAroundCurrent = 3)
	{
		$current = $paginator->getCurrentPageNumber();
		$total = count($paginator);

		if ($total == 0) {
			return array();
		} else if ($total == 1) {
			return array(1);
		}

		$marks = array(1);

		if (($current - $marksAroundCurrent) > 1) {
			$marks[] = null;
		}

		for ($pageNo = max(($current - $marksAroundCurrent), 2); $pageNo < $current; $pageNo++) {
			$marks[] = $pageNo;
		}

		if (!in_array($current, $marks)) {
			$marks[] = $current;
		}

		for ($pageNo = ($current + 1); $pageNo <= min(($current + $marksAroundCurrent), $total); $pageNo++) {
			$marks[] = $pageNo;
		}

		if (($current + $marksAroundCurrent) < $total) {
			$marks[] = null;
			$marks[] = $total;
		}

		return $marks;
	}
}