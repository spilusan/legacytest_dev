<?php
class Myshipserv_Controller_Action_Helper_Seo_Product extends Myshipserv_Controller_Action_Helper_Seo
{
	
	function __construct($params, $rawParams)
	{
		$this->params = $params;
		$this->rawParams = $rawParams;
		$this->adapters['product'] = new Shipserv_Oracle_Product();
		$this->productInfo = $this->adapters['product']->fetchById($this->params['productId']);;
		$this->brand = Shipserv_Brand::getInstanceById($this->productInfo['BRAND_ID']);
		$this->productName = ($this->productInfo['BROWSE_PAGE_NAME'] != '') ? $this->productInfo['BROWSE_PAGE_NAME'] : $this->productInfo['NAME'];
		
		if ($this->productInfo === null)
		{
			throw new Exception("Unable to find product by ID passed");
		}
		
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
		if ($this->productInfo['PAGE_TITLE_NAME'] != '') // is the title in the brand overridden
		{
			$title = html_entity_decode($this->productInfo['PAGE_TITLE_NAME']).parent::TITLE_SUFFIX;
		}
			
		if ($this->params['location'] != '' && $this->productInfo['PAGE_TITLE_NAME_LOCATION'] != '') // is there a location, and an override?
		{
			$title = html_entity_decode(str_replace('%%LOCATION%%', $this->params['location'], $this->productInfo['PAGE_TITLE_NAME_LOCATION'])).parent::TITLE_SUFFIX;
		}
			
		if ($title == '' && $this->params['location'] != '')
		{
			$title = html_entity_decode($this->productInfo['NAME']).' '.$this->params['location'].' Marine Supply, all '.html_entity_decode($this->productInfo['NAME']).' '.$this->params['location'].' Marine Suppliers'.parent::TITLE_SUFFIX;
		}
			
		if ($title == '')
		{
			$title = html_entity_decode($this->productInfo['NAME']).' Marine Supply, all '.html_entity_decode($this->productInfo['NAME']).' Marine Suppliers'.parent::TITLE_SUFFIX;
		}		
		return $title;
	}
	
	public function getSerpsTitle()
	{
		$serpsTitle      = 'Marine Suppliers of ';
		$serpsTitle     .= $this->productName;
		
		return $serpsTitle;
	}
	
	public function getMetaKeywords()
	{
		return parent::META_KEYWORDS . ', ' . $this->brandName;
	}
	
	public function getMetaDescription()
	{
		$metaDescription = $this->productName.' Marine Supply';
		if ($this->params['location'] != '')
		{
			$metaDescription.= ' in '.$this->params['location'];
		}
		$metaDescription.= ' from ShipServ. Enquire '.$this->productName.' Marine Suppliers';
		if ($this->params['location'] != '')
		{
			$metaDescription.= ' in '.$this->params['location'];
		}
		$metaDescription.= " on ShipServ Pages, the world's number one marine supply directory";
			
		// check for meta description override
		if ($this->productInfo['METADESCRIPTION'] != '')
		{
			$metaDescription = $this->productInfo['METADESCRIPTION'];
		}
			
		// check for location & metadescription override
		if ($this->productInfo['METADESCRIPTION_LOCATION'] != '')
		{
			$metaDescription = $this->productInfo['METADESCRIPTION_LOCATION'];
		}
		
		return $metaDescription;
	}
	
	public function getSeoBlock()
	{
		$seoBlock = array();
		
		if ($this->productInfo['SEO_CONTENT'] != '')
		{
			// $seoBlock['title'] = 'About '.$this->productInfo['NAME'];
			$seoBlock['title'] = 'About This Product';
			$seoBlock['text']  = $this->productInfo['SEO_CONTENT'];
		}
			
		if ($this->productInfo['SEO_CONTENT_RHS'] != '')
		{
			$seoBlock['rhs']['title'] = 'About This Product';
			$seoBlock['rhs']['text'] = $this->productInfo['SEO_CONTENT_RHS'];
		}
		
		return $seoBlock;
	}
	
	public function getBreadcrumbs()
	{
		$breadcrumbs[] = array('name' => 'ShipServ Pages', 'url' => '/search');
		$breadcrumbs[] = array('name' => 'Brands', 'url' => '/supplier/brand');
		$breadcrumbs[] = array('name' => $this->productInfo['BRAND_NAME'], 'url' => $this->brand->getUrl());
			
		// adding breadcrumb for product
		$breadcrumbs[] = array('name' => "Products", 'url' => "/supplier/brand/browse-by-product/" . strtolower(preg_replace('/(\W){1,}/', '-', $this->productInfo['BRAND_NAME'])) . "/id/" . $this->productInfo['BRAND_ID']);
		$breadcrumbs[] = array('name' => $this->productInfo['NAME'], 'url' => "");
		
		return $breadcrumbs;
	}
	
	public function getCanonical()
	{
		return Shipserv_Brand::getUrlForProduct($this->params['productId'], $this->productInfo['NAME']);
	}
}