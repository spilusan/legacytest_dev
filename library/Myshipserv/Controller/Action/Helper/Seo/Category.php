<?php
class Myshipserv_Controller_Action_Helper_Seo_Category extends Myshipserv_Controller_Action_Helper_Seo
{
	
	function __construct($params, $rawParams)
	{
		$this->params = $params;
		$this->rawParams = $rawParams;
		$this->adapters['category'] = new Shipserv_Oracle_Categories();
		$this->categoryInfo = $this->adapters['category']->fetchCategory($params['categoryId']);
		$this->category = Shipserv_Category::getInstanceByParams($this->rawParams);
		$this->categoryName = ($this->categoryInfo['BROWSE_PAGE_NAME']) ? $this->categoryInfo['BROWSE_PAGE_NAME'] : $this->categoryInfo['NAME'];
		// breadcrumbs for countries
		if( $this->rawParams['searchWhere'] != "" )
		{
			$this->adapters['country'] = new Shipserv_Oracle_Countries();
			$this->countryCode = $this->rawParams['searchWhere'];
			if( strlen($this->countryCode) > 2 && strstr($this->countryCode, "-") !== false)
			{
				$tmp = explode("-", $this->countryCode);
				$this->portCode = $this->countryCode;
				$this->countryCode = $tmp[0];
				
				$tmp = explode(", ", $this->params['location']);
				$this->portName = $tmp[0];
				
			}
			else 
			{
				$this->countryCode = $this->rawParams['searchWhere'];
			}
			
			$this->countryInfo = $this->adapters['country']->fetchCountryByCode($this->countryCode);
			$this->countryName = $this->countryInfo[0]['CNT_NAME'];
		}
	}
	
	public function getTitle()
	{
		if ($this->categoryInfo['PAGE_TITLE_NAME']) // is the title in the brand overridden
		{
			$title = html_entity_decode($this->categoryInfo['PAGE_TITLE_NAME']).parent::TITLE_SUFFIX;
		}
			
		if ($this->params['location'] && $this->categoryInfo['PAGE_TITLE_NAME_LOCATION']) // is there a location, and an override?
		{
			$title = html_entity_decode(str_replace('%%LOCATION%%', $this->params['location'], $this->categoryInfo['PAGE_TITLE_NAME_LOCATION'])).parent::TITLE_SUFFIX;
		}
			
		if (!$title && $this->params['location'])
		{
			$title = html_entity_decode($this->categoryInfo['NAME']).' '.$this->params['location'].' Marine Supply, all '.html_entity_decode($this->categoryInfo['NAME']).' '.$this->params['location'].' Marine Suppliers'.parent::TITLE_SUFFIX;
		}
			
		$title = html_entity_decode($this->categoryInfo['NAME']);
		
		if($this->params['location'])
		{
			$title .= ' in ' . $this->params['location'] . ' - Marine & Shipping Equipment';
		}
		else
		{
			$title .= ' Shipping & Marine Equipment';
		}
		
		if( $this->countryCode == "" || is_null($this->countryCode) )
		{
			$title = $this->categoryName.' Suppliers for the Marine & Shipping Industry';
		}
		
		if( $this->portName != "" && $this->categoryName != "" )
		{
			$title = $this->categoryName . " Suppliers in " . $this->portName;
		}
		
		return $title;
	}
	
	public function getSerpsTitle()
	{
		if(!preg_match('/\bmarine|ship\b/i', $this->categoryName))
		{
			$serpsTitle = 'Marine ';
		}
		
		$serpsTitle     .= $this->categoryName;
		
		if($this->params['location'])
		{
			$serpsTitle .= ' in ' . $this->params['location'];
		}
		
		if( $this->countryCode == "" || is_null($this->countryCode) )
		{
			$serpsTitle      = 'Marine Suppliers of ' . $this->categoryName;
		}
		
		if( $this->portName != "" && $this->categoryName != "" )
		{
			$serpsTitle = 'Marine Suppliers of ' . $this->categoryName . " in " . $this->portName;				
		}
		
		return $serpsTitle;
	}
	
	public function getMetaKeywords()
	{
		return parent::META_KEYWORDS . ', ' . $this->categoryName;
	}
	
	public function getMetaDescription()
	{
		$metaDescription = $this->categoryName . '  Shipping & Marine Suppliers, World Leading Marine Marketplace for the Shipping Industry, Find, Connect & Trade';
		
		if ($this->params['location'])
		{
			$metaDescription = $this->categoryName . '  Marine & Shipping Equipment in ' . $this->params['location'] . ', World Leading Marine Marketplace for the Shipping Industry, Find, Connect & Trade';
		}
		
		// check for meta description override
		if ($this->categoryInfo['METADESCRIPTION'])
		{
			$metaDescription = $this->categoryInfo['METADESCRIPTION'];
		}
			
		// check for location & metadescription override
		if ($this->categoryInfo['METADESCRIPTION_LOCATION'])
		{
			$metaDescription = $this->categoryInfo['METADESCRIPTION_LOCATION'];
		}
		
		if( $this->countryCode == "" || is_null($this->countryCode) )
		{
			$metaDescription = 'Find 100+ Marine Suppliers of '.$this->categoryName .' on the World Leading Marine Marketplace for the Shipping Industry. Find, Connect & Trade on ShipServ Pages.';
		}
		
		if( $this->portName != "" && $this->categoryName != "" )
		{
			$metaDescription = "Find Marine Suppliers of " . $this->categoryName . " in " . $this->portName . ", " . $this->countryName . ", on the World Leading Marine Marketplace for the Shipping Industry. Find, Connect & Trade.";
		}
		
		return $metaDescription;
	}
	
	public function getSeoBlock()
	{
		if ($this->categoryInfo['SEO_CONTENT']) // update with proper field name
		{
			$seoBlock['title'] = 'About This Category';
			$seoBlock['text']  = $this->categoryInfo['SEO_CONTENT'];
			return $seoBlock;
		}
		return array();
	}
	
	public function getBreadcrumbs()
	{
		$breadcrumbs[] = array('name' => 'ShipServ Pages', 'url' => '/search');
		$breadcrumbs[] = array('name' => 'Categories', 'url' => '/supplier/category');
		
		// go through the hierarchy of the cat and prepare a
		$nestedCategories = $this->adapters['category']->fetchNestedCategories();
		$this->categoryInfo = $this->search($nestedCategories, 'ID', $this->categoryInfo['ID']);
		$nestedId = explode("/",$this->categoryInfo[0]['PATH_ID']);
		foreach($nestedId as $this->categoryId)
		{
			$nestedCategory = Shipserv_Category::getInstanceById($this->categoryId);
			$breadcrumbs[] = array('name' => $nestedCategory->name, 'url' => $nestedCategory->getUrl('category-without-country'));
		}
		
		// breadcrumbs for countries
		if( $this->rawParams['searchWhere'] != "" )
		{
			if( $this->countryName != "" )
			{
				$breadcrumbs[] = array('name' => 'Countries', 'url' => "/supplier/category/browse-by-country/" . ( strtolower(preg_replace('/(\W){1,}/', '-', $this->category->name) ) ) . "/id/" . $this->category->id);
				$breadcrumbs[] = array('name' => $this->countryName, 'url' => $this->category->getUrl('category-by-country'));
			}
			
			if($this->portName != "" )
			{
				$breadcrumbs[] = array('name' => 'Ports', 'url' => $this->category->getUrl('browse-by-port-list'));
				$breadcrumbs[] = array('name' => $this->portName, 'url' => '');
			}
		}
		
		return $breadcrumbs;
	}
	
	public function getCanonical()
	{
		$this->category->getUrl();
	}
}