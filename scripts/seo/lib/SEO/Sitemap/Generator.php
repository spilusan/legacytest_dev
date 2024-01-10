<?php
class SEO_Sitemap_Generator
{
	function __construct()
	{
		$this->db = $GLOBALS['application']->getBootstrap()->getResource('db');
		$this->portAdapter = new Shipserv_Oracle_Ports($this->db);
		$this->categoriesAdapter = new Shipserv_Oracle_Categories($this->db);
		$this->brandAdapter = new Shipserv_Oracle_Brands($this->db);
		$this->productAdapter = new Shipserv_Oracle_Product($this->db);
		$this->countryAdapter = new Shipserv_Oracle_Countries($this->db);
		$this->stringHelper = new Myshipserv_View_Helper_String();
		
	}

	public function createSitemapForSupplier($collection)
	{
		Logger::log("-- Creating sitemap for supplier");
		
		$collection->data = array();
		$collection->fileName = "sitemap_supplier.xml";
		$collection->totalUrlPerFile = 10000;
		$collection->splitFile = true;

		$sql = "
		SELECT * FROM (
			SELECT
				spb_branch_code,
				spb_name,
				rownum rn
			FROM
				supplier_branch
			WHERE
				directory_entry_status = 'PUBLISHED'
				AND spb_account_deleted = 'N'
				AND spb_test_account = 'N'
				AND spb_branch_code <= 999999
				AND spb_branch_code NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)
			ORDER BY
				spb_branch_code ASC
		) ";

		foreach($this->db->fetchAll($sql) as $row)
		{
			$data = new SEO_Sitemap_Template_Supplier( Shipserv_Supplier::createUrl(strtolower($row['SPB_NAME']), $row['SPB_BRANCH_CODE']) );
			$collection->data[] = $data->convertToXml();
		}

		$collection->write();
		Logger::log("-- End");
	}

	public function createSitemapForNewSupplier($collection)
	{
		Logger::log("-- Creating sitemap for supplier");

		$collection->data = array();
		$collection->fileName = "sitemap_supplier.xml";
		$collection->splitFile = false;
		$collection->new = true;

		$sql = "
		SELECT * FROM (
			SELECT
				spb_branch_code,
				spb_name,
				rownum rn
			FROM
				supplier_branch
			WHERE
				directory_entry_status = 'PUBLISHED'
				AND spb_account_deleted = 'N'
				AND spb_test_account = 'N'
				AND spb_branch_code <= 999999
				AND spb_branch_code NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)
				AND spb_created_date > :dateInitialised
			ORDER BY
				spb_branch_code ASC
		) ";

		foreach($this->db->fetchAll($sql, array('dateInitialised' => $this->getInitialisedDate() )) as $row)
		{
			$data = new SEO_Sitemap_Template_Supplier( Shipserv_Supplier::createUrl(strtolower($row['SPB_NAME']), $row['SPB_BRANCH_CODE']) );
			$collection->data[] = $data->convertToXml();
		}

		$collection->write();
		Logger::log("-- End");
	}

	public function getInitialisedDate()
	{
		return "01-JAN-2010";
		return date("d-M-Y");
	}

	public function createSitemapForCategory($collection)
	{
		Logger::log("-- Creating sitemap for category");

		$collection->fileName = "sitemap_category.xml";
		$collection->splitFile = false;
		$collection->data = array();

		foreach($this->categoriesAdapter->fetchNestedCategories() as $row)
		{
			$data = new SEO_Sitemap_Template_Category( 'https://'. $_SERVER['HTTP_HOST'] .'/category/' . strtolower(preg_replace('/(\W){1,}/', '-', $row['DISPLAYNAME'])) . '/' . $row['ID'] );
			$collection->data[] = $data->convertToXml();
		}

		$collection->write();
		Logger::log("-- End");
	}
	
	public function createSitemapForCategoryByCountry($collection)
	{
		Logger::log("-- Creating sitemap for category by country");
	
		$collection->fileName = "sitemap_category-by-country.xml";
		$collection->totalUrlPerFile = 20000;
		$collection->splitFile = true;
		$collection->data = array();
		
		foreach($this->categoriesAdapter->fetchNestedCategories() as $row)
		{
			$continents = $this->categoriesAdapter->fetchCountriesForCategory($row['ID']);

			foreach( $continents as $continent )
			{
				foreach ($continent['countries'] as $cntCode => $country)
				{
					$data = new SEO_Sitemap_Template_Category( 'https://'. $_SERVER['HTTP_HOST'] .'/category/' . strtolower(preg_replace('/(\W){1,}/', '-', $row['DISPLAYNAME'])) . '/' . strtolower(preg_replace('/(\W){1,}/', '-', $country['name'])) . '/' . $cntCode . '/' . $row['ID'] );
					$collection->data[] = $data->convertToXml();
				}
			}
		}
	
		$collection->write();
		Logger::log("-- End");
	}
	
	public function createSitemapForBrand($collection)
	{
		Logger::log("-- Creating sitemap for brand");

		$collection->fileName = "sitemap_brand.xml";
		$collection->data = array();

		foreach($this->brandAdapter->search() as $brand)
		{
				
			$data = new SEO_Sitemap_Template_Brand( 'https://'. $_SERVER['HTTP_HOST'] .'/brand/' . ($this->stringHelper->sanitiseForURI(($brand['BROWSE_PAGE_NAME']) ? $brand['BROWSE_PAGE_NAME'] : $brand['NAME'])) . '/' . $brand['ID']);
			$collection->data[] = $data->convertToXml();
		}

		$collection->write();
		Logger::log("-- End");
	}
	
	public function createSitemapForProduct($collection)
	{
		Logger::log("-- Creating sitemap for product");
	
		$collection->fileName = "sitemap_product.xml";
		$collection->data = array();
		
		
		foreach($this->brandAdapter->search() as $brand)
		{
			foreach( (array) $this->productAdapter->fetchByBrandId($brand['ID']) as $product )
			{
				$data = new SEO_Sitemap_Template_Product( 'https://'. $_SERVER['HTTP_HOST'] .'/product/' . ($this->stringHelper->sanitiseForURI(($product['NAME']) ? $product['NAME'] : $product['NAME'])) . '/' . $product['ID']);
				$collection->data[] = $data->convertToXml();
			}
		}
	
		$collection->write();
		Logger::log("-- End");
	}
	
	public function createSitemapForBrandByCountry($collection)
	{
		
		Logger::log("-- Creating sitemap for Brand by Country");
		
		$collection->fileName = "sitemap_brand-by-country.xml";
		$collection->totalUrlPerFile = 20000;
		$collection->splitFile = true;
		$collection->data = array();
		
		foreach($this->brandAdapter->search() as $brand)
		{
			$continents = $this->brandAdapter->fetchCountriesForBrand($brand['ID']);
			
			foreach( $continents as $continent )
			{
				foreach ($continent['countries'] as $cntCode => $country)
				{
					$data = new SEO_Sitemap_Template_Brand( 'https://'. $_SERVER['HTTP_HOST'] .'/brand/' . strtolower($this->stringHelper->sanitiseForURI(($brand['BROWSE_PAGE_NAME']) ? $brand['BROWSE_PAGE_NAME'] : $brand['NAME'])) . '/' . (preg_replace('/(\W){1,}/', '-', strtolower($country['name']))) . '/' . $cntCode . '/' . $brand['ID']);
					$collection->data[] = $data->convertToXml();
				}
			}
				
		}
		
		$collection->write();
		Logger::log("-- End");
		
	}
	
	public function createSitemapForCountry($collection)
	{
		Logger::log("-- Creating sitemap for country");

		$collection->fileName = "sitemap_country.xml";
		$collection->data = array();

		foreach($this->countryAdapter->fetchAllCountries() as $row)
		{

			$data = new SEO_Sitemap_Template_Country( 'https://'. $_SERVER['HTTP_HOST'] .'/country/' . (preg_replace('/(\W){1,}/', '-', strtolower($row['CNT_NAME']))) . '/' . $row['CNT_COUNTRY_CODE']);
			$collection->data[] = $data->convertToXml();
		}

		$collection->write();
		Logger::log("-- End");
	}

	public function createSitemapForPort($collection)
	{
		Logger::log("-- Creating sitemap for port");

		$collection->fileName = "sitemap_port.xml";
		$collection->data = array();

		foreach($this->portAdapter->fetchAllPortsGroupedByCountry() as $row)
		{
			if( $row['type'] == "port" )
			{
				$data = new SEO_Sitemap_Template_Port( 'https://'. $_SERVER['HTTP_HOST'] .'/port/' . (preg_replace('/(\W){1,}/', '-', strtolower($row['name']))) . '/' . $row['id']);
				$collection->data[] = $data->convertToXml();
			}
		}

		$collection->write();
		Logger::log("-- End");
	}
	
	public function createSitemapForHomepage($collection)
	{
		Logger::log("-- Creating sitemap for Homepage");
	
		$collection->fileName = "sitemap_homepage.xml";
		$collection->data = array();
		$data = new SEO_Sitemap_Template_Home( 'https://'. $_SERVER['HTTP_HOST'] .'/search');
		$collection->data[] = $data->convertToXml();
		$collection->write();
		Logger::log("-- End");
	}
	
}