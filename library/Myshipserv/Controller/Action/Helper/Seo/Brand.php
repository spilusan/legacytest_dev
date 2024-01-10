<?php
class Myshipserv_Controller_Action_Helper_Seo_Brand extends Myshipserv_Controller_Action_Helper_Seo
{
	
	function __construct($params, $rawParams)
	{
		$this->params = $params;
		$this->rawParams = $rawParams;
		$this->adapters['brand'] = new Shipserv_Oracle_Brands();
		$this->brandInfo = $this->adapters['brand']->fetchBrand($this->params['brandId']);
		$this->brand = Shipserv_Brand::getInstanceById($this->params['brandId']);
		$this->brandName = ($this->brandInfo['BROWSE_PAGE_NAME']) ? $this->brandInfo['BROWSE_PAGE_NAME'] : $this->brandInfo['NAME'];
		
		// breadcrumbs for countries
		if( $this->rawParams['searchWhere'] != "" )
		{
			$this->adapters['country'] = new Shipserv_Oracle_Countries();
			$this->brand = Shipserv_Brand::getInstanceByIdCountryCode($this->params['brandId'], $this->rawParams['searchWhere']);
			
			$this->countryCode = $this->rawParams['searchWhere'];
				
			if( strlen($this->countryCode) > 2 && strstr($this->countryCode, "-") !== false)
			{
				$tmp = explode("-", $this->countryCode);
				$this->portCode = $this->countryCode;
				$this->countryCode = $tmp[0];
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
		if ($this->brandInfo['PAGE_TITLE_NAME']) // is the title in the brand overridden
		{
			$title = html_entity_decode($this->brandInfo['PAGE_TITLE_NAME']).self::TITLE_SUFFIX;
		}
			
		if ($this->params['location'] && $this->brandInfo['PAGE_TITLE_NAME_LOCATION']) // is there a location, and an override?
		{
			$title = html_entity_decode(str_replace('%%LOCATION%%', $this->params['location'], $this->brandInfo['PAGE_TITLE_NAME_LOCATION'])).self::TITLE_SUFFIX;
		}
			
		if (!$title && $this->params['location'])
		{
			$title = html_entity_decode($this->brandInfo['NAME']).' '.$this->params['location'].' Marine Supply, all '.html_entity_decode($this->brandInfo['NAME']).' '.$this->params['location'].' Marine Suppliers'.self::TITLE_SUFFIX;
		}
			
		if (!$title)
		{
			$title = html_entity_decode($this->brandInfo['NAME']).' Marine Supply, all '.html_entity_decode($this->brandInfo['NAME']).' Marine Suppliers'.self::TITLE_SUFFIX;
		}
		
		return $title;
	}
	
	public function getSerpsTitle()
	{
		$serpsTitle      = 'Marine Suppliers of ';
		$serpsTitle     .= $this->brandName;
		
		if ($this->params['location'])
		{
			$serpsTitle     .= ' in '.$this->params['location'];
		}

		return $serpsTitle;
	}
	
	public function getMetaKeywords()
	{
		return parent::META_KEYWORDS . ', ' . $this->brandName;
	}
	
	public function getMetaDescription()
	{
		$metaDescription = $this->brandName.' Marine Supply';
		
		if ($this->params['location'])
		{
			$metaDescription.= ' in '.$this->params['location'];
		}
		$metaDescription.= ' from ShipServ. Enquire '.$this->brandName.' Marine Suppliers';
		
		if ($this->params['location'])
		{
			$metaDescription.= ' in '.$this->params['location'];
		}
		$metaDescription.= " on ShipServ Pages, the world's number one marine supply directory";
		
		// check for meta description override
		if ($this->brandInfo['METADESCRIPTION'])
		{
			$metaDescription = $this->brandInfo['METADESCRIPTION'];
		}
			
		// check for location & metadescription override
		if ($this->brandInfo['METADESCRIPTION_LOCATION'])
		{
			$metaDescription = $this->brandInfo['METADESCRIPTION_LOCATION'];
		}
		return $metaDescription;
	}
	
	public function getSeoBlock()
	{
		if ($this->brandInfo['SEO_CONTENT']) // update with proper field name
		{
			// $seoBlock['title'] = 'About '.$this->brandInfo['NAME'];
			$seoBlock['title'] = 'About This Brand';
			$seoBlock['text']  = $this->brandInfo['SEO_CONTENT'];
			return $seoBlock;
		}
		
		return array();
	}
	
	public function getBreadcrumbs()
	{
		$breadcrumbs[] = array('name' => 'ShipServ Pages', 'url' => '/search');
		$breadcrumbs[] = array('name' => 'Brands', 'url' => '/supplier/brand');
		$breadcrumbs[] = array('name' => (($this->brandInfo['BROWSE_PAGE_NAME'] != "")?$this->brandInfo['BROWSE_PAGE_NAME']:$this->brandInfo['NAME']), 'url' => $this->brand->getUrl('brand-without-country'));
		if( $this->params['location'] != "" )
		{
			$breadcrumbs[] = array('name' => 'Countries', 'url' => $this->brand->getUrl('browse-by-country'));
			$breadcrumbs[] = array('name' => $this->params['location'], 'url' => '');
		}
		
		
		return $breadcrumbs;
	}
	
	public function getCanonical()
	{
		$seoCanonical = $this->brand->getUrl();
		// S7233 - disabling canonical temporarily
		$seoCanonical = false;
		return $seoCanonical;
	}
}