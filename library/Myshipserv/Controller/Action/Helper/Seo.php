<?php

/**
 * Support reviews management on profile tab. Provides methods to:
 *
 * Fetch requests / reviews belonging to user
 * Fetch details for specific request / review
 */
class Myshipserv_Controller_Action_Helper_Seo extends Zend_Controller_Action_Helper_Abstract
{
	const TITLE_SUFFIX = ' | ShipServ.com';
	const META_KEYWORDS = 'Marine, Maritime, Shipping, Suppliers, Supply, Companies, Directory, Listings, Search, ShipServ, Parts, Equipment, Spares, Services';

	protected function search($array, $key, $value)
	{
	    $results = array();
	
	    if (is_array($array))
	    {
	        if (isset($array[$key]) && $array[$key] == $value)
	            $results[] = $array;
	
	        foreach ($array as $subarray)
	            $results = array_merge($results, $this->search($subarray, $key, $value));
	    }
	
	    return $results;
	}
	
	/**
	 * Generates the SEO content for a search landing page:
	 * meta keywords, description, title, h1, and text block
	 *
	 * @access public
	 * @param array $params The raw search array
	 * @return array
	 */
	public function generateSeoContent (array $params, &$breadcrumbs = NULL, $rawParams = null)
	{
		$serpsTitle      = null;
		$metaDescription = null;
		$metaKeywords    = self::META_KEYWORDS;
		$seoBlock        = array('title' => '',
								 'text'  => '');
		$titleSuffix     = ' | ShipServ.com';
		
		$db = $this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		$breadcrumbs[] = array('name' => 'ShipServ Pages', 'url' => '/search');
		
		$countryAdapter = new Shipserv_Oracle_Countries($db);
		
		 // if there's a brand id sent, fetch the brand from the DB and set appropriate SEO text
		if ($params['brandId'])
		{
			$seo = new Myshipserv_Controller_Action_Helper_Seo_Brand($params, $rawParams);
		}
		// if there's a category id sent, fetch the category from the DB and set appropriate SEO text
		elseif ($params['categoryId'])
		{
			$seo = new Myshipserv_Controller_Action_Helper_Seo_Category($params, $rawParams);
		}
		elseif ($params['productId'] != '')
		{
			$seo = new Myshipserv_Controller_Action_Helper_Seo_Product($params, $rawParams);			
		}
		elseif ($params['modelId'] != '')
		{
			$modelDao = new Shipserv_Oracle_Model($db);
			$model = $modelDao->fetchById($params['modelId']);
			if ($model === null)
			{
				throw new Exception("Unable to find model for ID passed");
			}
			
			if ($model['PAGE_TITLE_NAME'] != '') // is the title in the brand overridden
			{
				$title = html_entity_decode($model['PAGE_TITLE_NAME']).self::TITLE_SUFFIX;
			}
			
			if ($params['location'] != '' && $model['PAGE_TITLE_NAME_LOCATION'] != '') // is there a location, and an override?
			{
				$title = html_entity_decode(str_replace('%%LOCATION%%', $params['location'], $model['PAGE_TITLE_NAME_LOCATION'])).self::TITLE_SUFFIX;
			}
			
			if ($title == '' && $params['location'] != '')
			{
				$title = html_entity_decode($model['NAME']).' '.$params['location'].' Marine Supply, all '.html_entity_decode($model['NAME']).' '.$params['location'].' Marine Suppliers'.self::TITLE_SUFFIX;
			}
			
			if ($title == '')
			{
				$title = html_entity_decode($model['NAME']).' Marine Supply, all '.html_entity_decode($model['NAME']).' Marine Suppliers'.self::TITLE_SUFFIX;
			}
			
			// generate the H1 tag ($serpsTitle), the meta keywords, and meta description
			$modelName = ($model['BROWSE_PAGE_NAME'] != '') ? $model['BROWSE_PAGE_NAME'] : $model['NAME'];
			
			$serpsTitle      = 'Marine Suppliers of ';
			$serpsTitle     .= $modelName;
			$metaKeywords   .= ', '.$modelName;
			
			$metaDescription = $modelName.' Marine Supply';
			if ($params['location'] != '')
			{
				$metaDescription.= ' in '.$params['location'];
			}
			$metaDescription.= ' from ShipServ. Enquire '.$modelName.' Marine Suppliers';
			if ($params['location'] != '')
			{
				$metaDescription.= ' in '.$params['location'];
			}
			$metaDescription.= " on ShipServ Pages, the world's number one marine supply directory";
			
			// check for meta description override
			if ($model['METADESCRIPTION'] != '')
			{
				$metaDescription = $model['METADESCRIPTION'];
			}
			
			// check for location & metadescription override
			if ($model['METADESCRIPTION_LOCATION'] != '')
			{
				$metaDescription = $model['METADESCRIPTION_LOCATION'];
			}
			
			if ($model['SEO_CONTENT'] != '') // update with proper field name
			{
				// $seoBlock['title'] = 'About '.$model['NAME'];
				$seoBlock['title'] = 'About This Model';
				$seoBlock['text']  = $model['SEO_CONTENT'];
			}
		}
		
		if( $seo !== null )
		{
			$title = $seo->getTitle();
			$serpsTitle = $seo->getSerpsTitle();
			$metaKeywords = $seo->getMetaKeywords();
			$metaDescription = $seo->getMetaDescription();
			$seoBlock = $seo->getSeoBlock();
			$breadcrumbs = $seo->getBreadcrumbs();
			$seoCanonical = $seo->getCanonical();
		}
		
		// if we've not set a meta description from a brand or a category, but we have a location, use that
		if (!$metaDescription && $params['location'])
		{
        	//We need a context here, if its set and change the description accordingly. 
            if(isset($params['context'])){
            	$commaInsert = ', ';
                if($params['context']== 'country')
                {
                	$descFormat = '%b%c Marine Supply from ShipServ. Enquire Marine suppliers in %b%c on ShipServ Pages, the world\'s number one marine supply directory.';
                }
                elseif($params['context']=='port')
                {
                	$descFormat = '%b%c Port Marine Supply from ShipServ. Enquire Marine suppliers in %b%c on ShipServ Pages, the world\'s number one marine supply directory.';
                }
                
                $metaDescription = str_replace('%c',  $params['location'], $descFormat);
                
                //If there is a brand
                if($brandName)
                {
                	$metaDescription = str_replace( '%b', $brandName . ', ', $metaDescription);
                }
                elseif($categoryName)
                {
                 	$metaDescription = str_replace('%b', $categoryName . ', ', $metaDescription);  
                }
                else
                {
                	$metaDescription = str_replace('%b', '', $metaDescription);  
                }
			}
			else
			{
	        	$metaDescription = $params['location'].' Chandlers, '.$params['location'].' Provisions and '.$params['location'].' GMDSS Servicing suppliers in '.$params['location'].' from ShipServ. Find and enquire '.$params['location'].' Compressors suppliers, '.$params['location'].' Pumps suppliers and other maritime product suppliers on ShipServ Pages';
	        }
		}
		
		//If there is a hard override of meta description in the params
		if (isset($params['description'])) {
			$metaDescription = $params['description'];
		}
		
		if ($params['location'])
		{
			$metaKeywords.= ', '.$params['location'];
		}
		
		// if we've not set a title 
		if (!$title && $params['location'])
		{
			if ($params['what'])
			{
				$title = html_entity_decode($params['what']);
			}
			
			if($title)
			{
            	$title .= ' ';
            }
                        
			$title.= $params['location'].' Marine Supply, all ';
			
			if ($params['what'])
			{
				$title.= html_entity_decode($params['what']);
			}
			
            if($title && strlen($title) > 1 && $title[strlen($title) - 1] != ' ')
            {
            	$title .= ' ';
            }
			
            $title.= $params['location'].' Marine Suppliers and '.$params['location'].' Ports Suppliers'.self::TITLE_SUFFIX;
		}
		
		if (!$title)
		{
			$title = 'Marine Suppliers';
			if ($params['what'])
			{
				$title.= ' called '.html_entity_decode($params['what']);
			}
			
			if ($params['location'])
			{
				$title.= ' in ' . $params['location'];
			}
			
			$title.= self::TITLE_SUFFIX;
		}

		if( $metaDescription == "" && $params['what'] != '' )
		{
			$metaDescription = 'Marine suppliers called ' . $params['what'] . ' from ShipServ Pages, the world\'s number one marine supply directory';
		}
		
		// if we are in the zone - overwrite title, using zone's title
		if ($params['zone'])
		{
			$title = $params['zone'].self::TITLE_SUFFIX;
		}
		
		// S5472
		if( $params['context'] == 'country' && $params['location'] != "" && $serpsTitle == null)
		{
			// check country code
			$countryName = $params['location'];
			
			$countryCode = $rawParams['searchWhere'];
			$countryCode = strtoupper($countryCode);
			$countryInfo = $countryAdapter->fetchCountryByCode($countryCode);
			
			if( $countryInfo == null )
			{
				//throw new Myshipserv_Exception_MessagedException("Page not found, please check your url and try again.", 404);
			}

			$serpsTitle = "Marine Suppliers in " . $params['location'];
			
			$breadcrumbs[] = array('name' => 'Countries', 'url' => "/supplier/country/");
			$breadcrumbs[] = array('name' => $countryName, 'url' => "");

			$seoCanonical = 'https://'. $_SERVER['HTTP_HOST'] . "/country/" . ( strtolower(preg_replace('/(\W){1,}/', '-', $countryName) ) ) . "/" . $countryCode;
				
		}
		
		// S5473
		if( $params['context'] == 'port' && $params['location'] != "" && $serpsTitle == null )
		{
			$tmp = explode("-", $rawParams['searchWhere']);
			$countryCode = $tmp[0];
			$portCode = $tmp[1];
			$tmp = explode(", ", $params['location']);
			$portName = $tmp[0];
			$countryName = $tmp[1];

			$serpsTitle =(isset($tmp[1])) ? "Marine Suppliers serving " . $tmp[0] . " Port, " . $tmp[1] : "Marine Suppliers serving " . $tmp[0];
			$title = $tmp[0] . " Marine Suppliers, Serving Ports in " . $tmp[1] . " - ShipServ";

			if( $countryCode == "" && $countryName != "" )
			{
				$tmp2 = $countryAdapter->fetchCountriesByName($countryName);
				
				if( $tmp2[0]['CNT_COUNTRY_CODE'] )
				{
					$countryCode = $tmp2[0]['CNT_COUNTRY_CODE'];
				}
			}

			$breadcrumbs[] = array('name' => 'Countries', 'url' => "/supplier/country/");
			$breadcrumbs[] = array('name' => $tmp[1], 'url' => "/country/" . ( strtolower(preg_replace('/(\W){1,}/', '-', $countryName) ) ) . "/" . $countryCode);
			$breadcrumbs[] = array('name' => 'Ports', 'url' => "/supplier/browse-by-port/country/" . ( strtolower(preg_replace('/(\W){1,}/', '-', $countryName) ) ) . "/cnt/" . $countryCode);
			$breadcrumbs[] = array('name' => $tmp[0], 'url' => '');
				
		}
		
		$seoData = array('title'           => $title,
						 'serpsTitle'      => $serpsTitle,
						 'metaKeywords'    => $metaKeywords,
						 'metaDescription' => $metaDescription,
						 'seoBlock'        => $seoBlock);
						
		if (isset($params['canonical'])) {
			$seoData['canonical'] = $params['canonical'];
		}
		
		if (isset($seoCanonical)) {
			$seoData['canonical'] = $seoCanonical;
		}

		return $seoData;
	}

	/**
	 * We need to redirect OLD landing pages (e.g. /search/results/index/searchWhat/ABB/brandId/3?ssrc=286,
	 * 											   /search/results/index/searchWhat/Alfa+Laval/searchWhere/DZ/searchText/Algeria/brandId/28?ssrc=286
	 * 											   )
	 * 
	 * We can do this by checking if $_SERVER['REQUEST_URI'] contains /search/results/ in the string
	 * and also if there's a ssrc parameter attached. The ssrc is checked against specific sources
	 * and redirected appropriately
	 */
	public function redirectOldLandingPage ($params)
	{
		$redirect = false;
		
		if (stristr($_SERVER['REQUEST_URI'], '/search/results/') != FALSE && $params['ssrc'])
		{
			$searchSource = new Myshipserv_Controller_Action_Helper_SearchSource();
			$ko = $searchSource->getKeyObscurer();
			$plainKey = $ko->getPlainKey($params['ssrc']);
			
			$db = $this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
			$stringHelper = new Myshipserv_View_Helper_String();
			
			switch ($plainKey)
			{
				case 'BROWSE_BRAND':
					// fetch brand from DB
					$brandAdapter = new Shipserv_Oracle_Brands($db);
					$brand        = $brandAdapter->fetchBrand($params['brandId']);
					
					$url = '/brand/';
					$url.= $stringHelper->sanitiseForURI(($brand['BROWSE_PAGE_NAME']) ? $brand['BROWSE_PAGE_NAME'] : $brand['NAME']);
					
					if ($params['searchText'] && $params['searchWhere'])
					{
						$url.= '/'.$stringHelper->sanitiseForURI($params['searchText']).'/'.$params['searchWhere'];
					}
					
					$url.= '/'.$brand['ID'];
					
					$redirect = $url;
				break;
				
				case 'BROWSE_CATEGORY':
					// fetch category from DB
					$categoryAdapter = new Shipserv_Oracle_Categories($db);
					$category        = $categoryAdapter->fetchCategory($params['categoryId']);
					
					$url = '/category/';
					$url.= $stringHelper->sanitiseForURI(($category['BROWSE_PAGE_NAME']) ? $category['BROWSE_PAGE_NAME'] : $category['NAME']);
					
					if ($params['searchText'] && $params['searchWhere'])
					{
						$url.= '/'.$stringHelper->sanitiseForURI($params['searchText']).'/'.$params['searchWhere'];
					}
					
					$url.= '/'.$category['ID'];
					
					$redirect = $url;
				break;
				
				case 'BROWSE_COUNTRY':
					// no need to fetch from the DB - we can use searchText
					
					$url = '/country/';
					$url.= $stringHelper->sanitiseForURI($params['searchText']);
					$url.= '/'.$params['searchWhere'];
					
					$redirect = $url;
				break;
				
				case 'BROWSE_PORT':
					// no need to fetch from the DB - we can use searchText
					
					$url = '/port/';
					$url.= $stringHelper->sanitiseForURI($params['searchText']);
					$url.= '/'.$params['searchWhere'];
					
					$redirect = $url;
				break;
			}
		}
		
		return $redirect;
	}
	
	/**
	 * Analyses the search terms and overrides if coming from a landing page
	 * 
	 * @access public
	 * @param array $params The raw search array
	 * @return
	 */
	public function overrideSearchTerms ($params)
	{
		$override = array();
		$db       = $this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
		
		if ($params['brandId'])
		{
			$brandAdapter         = new Shipserv_Oracle_Brands($db);
			$brand                = $brandAdapter->fetchBrand($params['brandId']);
			$override['searchWhat'] = $brand['NAME'];
		}
		else if ($params['categoryId'])
		{
			$categoryAdapter      = new Shipserv_Oracle_Categories($db);
			$category             = $categoryAdapter->fetchCategory($params['categoryId']);
			$override['searchWhat'] = $category['NAME'];
		}
		else if ($params['productId'])
		{
			$productDao = new Shipserv_Oracle_Product($db);
			$product = $productDao->fetchById($params['productId']);
			if ($product !== null)
			{
				$override['searchWhat'] = $product['NAME'];
			}
		}
		else if ($params['modelId'])
		{
			$modelDao = new Shipserv_Oracle_Model($db);
			$model = $modelDao->fetchById($params['modelId']);
			if ($model !== null)
			{
				$override['searchWhat'] = $model['NAME'];
			}
		}
		
		return $override;
	}
}
