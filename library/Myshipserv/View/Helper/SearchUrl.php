<?php

/**
 * Auxiliary class to provide uniform way to make search URLs.
 *
 * @author Anthony Powell <apowell@shipserv.com>
 */
class _Myshipserv_View_Helper_SearchUrl_Aux
{
    private $searchWhat = '';
    private $searchWhere = '';
    private $searchText = '';
    private $searchType = '';
    private $categoryId = '';
    private $brandId = '';
	private $brandAuth = '';
    private $zone = '';
    private $paginateOffset = null;
    private $paginateRows = null;
    private $sourceKey = '';
    private $filterCategory = array();
    private $filterMembership = array();
    private $filterCertification = array();
    private $filterBrand = array();
    private $filterBrandAuth = array();
    private $filterAABrand = array();
    private $filterAIRBrand = array();
    private $filterOEMBrand = array();
    private $refinedBrandId = '';
    private $sourcePlainKey = '';
    private $withholdURLStem = false;
	private $logSearchId = null;
    /**
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function searchWhat ($str)
    {
        $this->searchWhat = $str;
        return $this;
    }
    
    /**
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function searchWhere ($portOrCountryCode)
    {
        $this->searchWhere = $portOrCountryCode;
        return $this;
    }
    
    /**
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function searchText ($whereStr)
    {
        $this->searchText = $whereStr;
        return $this;
    }
    
    /**
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function searchType ($typeStr)
    {
        $this->searchType = $typeStr;
        return $this;
    }
    
    /**
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function categoryId ($id)
    {
        $this->categoryId = $id;
        return $this;
    }
    
    /**
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function brandId ($id)
    {
        $this->brandId = $id;
        return $this;
    }
	
	public function logSearchId($id){
		$this->logSearchId = $id;
		return $this;
	}

	/**
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function brandAuth ($id)
    {
        $this->brandAuth = $id;
        return $this;
    }
    
    /**
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function zone ($zone)
    {
        $this->zone = $zone;
        return $this;
    }

	/**
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function refinedBrandId ($refinedBrandId)
    {
        $this->refinedBrandId = $refinedBrandId;
        return $this;
    }

    /**
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function paginate ($offset, $nRows)
    {        
        $this->paginateOffset = $offset;
        $this->paginateRows = $nRows;
        return $this;
    }
    
    /**
     * Adds tracking token to URL.
     *
     * @param string $plainKey Plain key, which is automatically obfuscated.
     */
    public function sourceKey ($plainKey)
    {
    	$this->sourcePlainKey = $plainKey;
        $sourceHelper = Zend_Controller_Action_HelperBroker::getStaticHelper('SearchSource');
        $this->sourceKey = $sourceHelper->getObscuredKey($plainKey);
        return $this;
    }
    
    /**
     * Adds a category filter.
     * Call multiple times to add n filters.
     * 
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterCategory ($id)
    {
        if ($id != '') {
            $this->filterCategory[$id] = 1;
        }
        return $this;
    }
    
    public function addFilterCategoryArr ($idArr)
    {
        foreach ($idArr as $id) $this->addFilterCategory($id);
        return $this;
    }
    
    /**
     * Adds a membership filter.
     * Call multiple times to add n filters.
     * 
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterMembership ($id)
    {
        if ($id != '') {
            $this->filterMembership[$id] = 1;
        }
        return $this;
    }
    
    public function addFilterMembershipArr ($idArr)
    {
        foreach ($idArr as $id) $this->addFilterMembership($id);
        return $this;
    }

	/**
     * Adds a certification filter.
     * Call multiple times to add n filters.
     *
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterCertification ($id)
    {
        if ($id != '') {
            $this->filterCertification[$id] = 1;
        }
        return $this;
    }

    public function addFilterCertificationArr ($idArr)
    {
        foreach ($idArr as $id) $this->addFilterCertification($id);
        return $this;
    }
    
    /**
     * Adds a brand filter.
     * Call multiple times to add n filters.
     * 
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterBrand ($id)
    {
        if ($id != '') {
            $this->filterBrand[$id] = 1;
        }
        return $this;
    }

	/**
     * Adds a brand auth filter.
     * Call multiple times to add n filters.
     *
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterBrandAuth ($id)
    {
        if ($id != '') {
            $this->filterBrandAuth[$id] = 1;
        }
        return $this;
    }

	/**
     * Adds a brand auth filter.
     * Call multiple times to add n filters.
     *
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterAABrand ($id)
    {
        if ($id != '') {
            $this->filterAABrand[$id] = 1;
        }
        return $this;
    }

	/**
     * Adds a brand auth filter.
     * Call multiple times to add n filters.
     *
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterAIRBrand ($id)
    {
        if ($id != '') {
            $this->filterAIRBrand[$id] = 1;
        }
        return $this;
    }


	/**
     * Adds a brand auth filter.
     * Call multiple times to add n filters.
     *
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterOEMBrand ($id)
    {
        if ($id != '') {
            $this->filterOEMBrand[$id] = 1;
        }
        return $this;
    }
    
    /**
     * Adds an array of brand filters
     *
     * @access public
     * @param array $idArr
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterBrandArr ($idArr)
    {
        foreach ($idArr as $id) $this->addFilterBrand($id);
        return $this;
    }

	/**
     * Adds an array of brand auth filters
     *
     * @access public
     * @param array $idArr
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterBrandAuthArr ($idArr)
    {
        foreach ($idArr as $id) $this->addFilterBrandAuth($id);
        return $this;
    }

	/**
     * Adds an array of brand auth filters
     *
     * @access public
     * @param array $idArr
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterAABrandArr ($idArr)
    {
        foreach ($idArr as $id) $this->addFilterAABrand($id);
        return $this;
    }

	/**
     * Adds an array of brand auth filters
     *
     * @access public
     * @param array $idArr
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterAIRBrandArr ($idArr)
    {
        foreach ($idArr as $id) $this->addFilterAIRBrand($id);
        return $this;
    }


	/**
     * Adds an array of brand auth filters
     *
     * @access public
     * @param array $idArr
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function addFilterOEMBrandArr ($idArr)
    {
        foreach ($idArr as $id) $this->addFilterOEMBrand($id);
        return $this;
    }
    
    /**
     * Sets the withholdURLStem to prevent the initial URL stem being output. This is used primarily to override the output URL in cases where there is already an SEO or other URL generated.
     * 
     * "access public
     * @param bool $withhold
     */
    public function withholdURLStem ($withhold){
        $this->withholdURLStem = $withhold;
        return $this;
    }
    
    /**
     * Sets up URL from search object.
     *
     * @param Myshipserv_Search_Search $search
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
	public function fromSearchObj(Myshipserv_Search_Search $search)
	{
		$searchArr = $search->exportArr();
		
		$this->searchWhat($searchArr['searchWhat'])
			->searchWhere($searchArr['searchWhere'])
			->searchType($searchArr['searchType'])
			->searchText($searchArr['searchText'])
			->paginate($searchArr['searchStart'], $searchArr['searchRows']);
		
		foreach ($searchArr['filters'] as $fName => $fValArr)
		{
			foreach ($fValArr as $fVal => $useless)
			{
				if ($fName == 'categoryId')
					$this->addFilterCategory($fVal);
				else if ($fName == 'membershipId')
					$this->addFilterMembership($fVal);
				else if ($fName == 'certificationId')
					$this->addFilterCertification($fVal);
				else if ($fName == 'brandId')
					$this->addFilterBrand($fVal);
				else if ($fName == 'brandAuth')
					$this->addFilterBrandAuth($fVal);
				else if ($fName == 'AABrandId')
					$this->addFilterAABrand($fVal);
				else if ($fName == 'AIRBrandId')
					$this->addFilterAIRBrand($fVal);
				else if ($fName == 'OEMBrandId')
					$this->addFilterOEMBrand($fVal);
			}
		}
		
		return $this;
	}
	
    /**
     * @return string Search URL
     */
    public function __toString ()
    {
        $url = '/search/results/index/';
        
        $slashParams = array();

        // fix to create search engine optimised url (requested by Mathieu)
        if( $this->sourcePlainKey == 'RELATED_FROM_SEARCH' && $this->searchType == 'company')
        {
        	$url = '/supplier/called/named/';
        	if ($this->searchWhat != '') $url .= urlencode( $this->searchWhat ) . "/";
        }
        else 
        {
        	if ($this->searchWhat != '') $slashParams[] = self::makeKeyValPair('searchWhat', $this->searchWhat);
        }
        
        if ($this->zone != '') $slashParams[] = self::makeKeyValPair('zone', $this->zone);
        if ($this->searchWhere != '') $slashParams[] = self::makeKeyValPair('searchWhere', $this->searchWhere);
        if ($this->searchText != '') $slashParams[] = self::makeKeyValPair('searchText', $this->searchText);
        if ($this->searchType != '') $slashParams[] = self::makeKeyValPair('searchType', $this->searchType);
        if ($this->categoryId != '') $slashParams[] = self::makeKeyValPair('categoryId', $this->categoryId);
        if ($this->brandId != '') $slashParams[] = self::makeKeyValPair('brandId', $this->brandId);
        if ($this->refinedBrandId != '' and !is_null($this->refinedBrandId)) $slashParams[] = self::makeKeyValPair('refinedBrandId', $this->refinedBrandId);
        
        //If we want to hide the base
        if($this->withholdURLStem){
            $url = '';
        }
        
        $url .= join('/', $slashParams);
        
        $params = array();
        if ($this->sourceKey != '') $params[] =
            Myshipserv_Controller_Action_Helper_SearchSource::PARAM_NAME . '='
            . urlencode($this->sourceKey);
        if (!is_null($this->paginateOffset)) $params[] = 'searchStart=' . urlencode($this->paginateOffset);
        if (!is_null($this->paginateRows)) $params[] = 'searchRows=' . urlencode($this->paginateRows);
		
		if (!is_null($this->logSearchId)) {
			$params[] = 'logSearchId=' . urlencode($this->logSearchId);
		}
		
        foreach ($this->filterCategory as $k => $v) {
            $params[] = 'filters[categoryId][]=' . urlencode($k);
        }
        foreach ($this->filterMembership as $k => $v) {
            $params[] = 'filters[membershipId][]=' . urlencode($k);
        }
		foreach ($this->filterCertification as $k => $v) {
            $params[] = 'filters[certificationId][]=' . urlencode($k);
        }
        foreach ($this->filterBrand as $k => $v) {
            $params[] = 'filters[brandId][]=' . urlencode($k);
        }
		foreach ($this->filterBrandAuth as $k => $v) {
            $params[] = 'filters[brandAuth][]=' . urlencode($k);
        }
		foreach ($this->filterAABrand as $k => $v) {
            $params[] = 'filters[AABrandId][]=' . urlencode($k);
        }
		foreach ($this->filterAIRBrand as $k => $v) {
            $params[] = 'filters[AIRBrandId][]=' . urlencode($k);
        }
		foreach ($this->filterOEMBrand as $k => $v) {
            $params[] = 'filters[OEMBrandId][]=' . urlencode($k);
        }
        if ($params) $url .= '?' . join('&', $params);
        
        return $url;
    }
    
    private static function makeKeyValPair ($key, $val)
    {
    	return urlencode($key) . '/' . urlencode(str_replace('/', '__', $val));
    	//return urlencode($key) . '/' . urlencode(str_replace('/', '%2F', $val));
    }
    
    public function encodeForwardSlash($url)
    {
    	$url = str_replace("%23", "_hash_", $url);
    	$url = str_replace("%2F", "__", $url);
    	return $url;
    }
    public function decodeForwardSlash($url)
    {
    	$url = str_replace("_hash_", "%23", $url);
    	$url = str_replace("__", "/", $url);
    	return $url;
    }
}

/**
 * Helper to form URLs for search.
 * Just a wrapper for _Myshipserv_View_Helper_SearchUrl_Aux.
 * 
 * @author Anthony Powell <apowell@shipserv.com>
 */
class Myshipserv_View_Helper_SearchUrl extends Zend_View_Helper_Abstract
{
    /**
     * @return _Myshipserv_View_Helper_SearchUrl_Aux
     */
    public function searchUrl ()
    {
        return new _Myshipserv_View_Helper_SearchUrl_Aux();
    }
}
