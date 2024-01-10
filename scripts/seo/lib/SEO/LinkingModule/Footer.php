<?php
class SEO_LinkingModule_Footer
{
	const TOTAL_COUNTRY = 6;
	const TOTAL_TOP_COUNTRY = 10;
	
	function __construct()
	{
		$this->db = $GLOBALS['application']->getBootstrap()->getResource('db');
	}
	
	public function populate( $categoryName = "" )
	{
		$categoriesAdapter = new Shipserv_Oracle_Categories($this->db);
		
		if( $categoryName != "" )
		{
			$categories = $this->getAllCategories( $categoryName );
		}
		else
		{
			$categories = $this->getAllCategories();
		}

		// process all categories availeble	
		foreach($categories as $category)
		{
			$countries = $selectedCountries = $countries2 = array();
			
			Logger::log("----------------------------------------------------------------------------------------");
			Logger::log("Processing category: " . $category['NAME'] . " (ID: " . $category['ID'] . ") -- ALL");
			Logger::log("\tResetting category: " . $category['NAME'] . " (ID: " . $category['ID'] . ")");
			$this->resetFooterLinkingModuleForCategoryId($category['ID']);
			
			// get all related countries
			$relatedCountries = $this->getDataByCountries($category['ID']);

			foreach($relatedCountries as $country)
			{
				$countries[] = $country['CNT_COUNTRY_CODE'];
			}
						
			// storing the result to db
			$this->store($category['ID'], array_slice($countries, 0, 10));
			
			$otherContinents = $this->getDataByContinents($category['ID']);
			
			$relatedCountries = $this->getContinentAndCountryByCategoryId($category['ID']);
			// going through all the related countries for this category
			foreach($relatedCountries as $country)
			{
				$countries = $selectedCountries = $countries2 = array();
				
				Logger::log("\tProcessing country: " . $country['CNT_COUNTRY_CODE'] . " --- continent: " . $country['CON_CODE']);
				
				foreach($this->getDataByContinentsId($category['ID'], $country['CON_CODE']) as $continent)
				{
					if( count($selectedCountries) > self::TOTAL_COUNTRY )
					{
						break;
					}
				
					$top = $lower = 0;
					foreach($this->getDataByContinentAndCategoryId($category['ID'], $continent['CON_CODE']) as $country2)
					{
						// storing all countries that is within the same continents
						if( $country['CON_CODE'] == $continent['CON_CODE'] && $country['CNT_COUNTRY_CODE'] != $country2['CNT_COUNTRY_CODE'])
						{
							$countries[] = $country2['CNT_COUNTRY_CODE'];
							$top++;
						}
					}

					foreach($this->getDataByContinentAndCategoryId($category['ID'], $continent['CON_CODE'], true) as $country2)
					{
						$countries2[] = $country2['CNT_COUNTRY_CODE'];
						$lower++;
					}
					Logger::log("\t\tContinent " . $continent['CON_CODE'] . " = top: " . $top . " lower: " . $lower);
					
				}

				shuffle($countries2);
				
				$selectedCountries = array_merge(
					array_slice($countries, 0, 6),
					array_slice($countries2, 0, 10-count(array_slice($countries, 0, 6)))
				);
					
				Logger::log("\t\tStoring countries: " . implode(", ", $selectedCountries) . "");
				$this->store($category['ID'], $selectedCountries, $country['CNT_COUNTRY_CODE']);
				Logger::log("\tStop Processing country: " . $country['CNT_COUNTRY_CODE'] . "\n");
			}	
			Logger::log("Stop processing category: " . $category['NAME']);
			Logger::log("----------------------------------------------------------------------------------------\n");			
		}
		
	}
	
	public function resetFooterLinkingModuleForCategoryId($categoryId)
	{
		$sql = "DELETE FROM pages_seo_rel_footer WHERE PSF_CATEGORY_ID=:categoryId";
		$this->db->query($sql, array('categoryId' => $categoryId));
	}
	
	public function store($categoryId, $result, $countryId = "")
	{
		$sql = "INSERT INTO pages_seo_rel_footer(PSF_CATEGORY_ID, PSF_CNT_COUNTRY_CODE, PSF_REL_CNT_COUNTRY_CODE, PSF_ORDER) VALUES(:categoryId, :countryId, :countryId2, :orderNum)";
		$order = 0;
		foreach( $result as $countryCode )
		{
			$order++;
			$this->db->query($sql, array('categoryId' => $categoryId, 'countryId' => $countryId, 'countryId2' => $countryCode, 'orderNum' => $order));
		}
	}
	
	public function getAllCategories( $categoryName = "" )
	{
		if( $categoryName != "" )
		{
			$sql = "SELECT * FROM PRODUCT_CATEGORY WHERE ID IN (
				SELECT DISTINCT prl_category_id FROM pages_related_categories WHERE prl_category_name='" . $categoryName . "'
			)";
		}
		else
		{
			$sql = "SELECT * FROM PRODUCT_CATEGORY";
		}
		$result = $this->db->fetchAll($sql);
		return $result;
	}
	
	public function getDataByContinentsId($categoryId, $continentId)
	{
		$sql = "
		SELECT
			CON_CODE,
			COUNT(*)
		FROM
			supply_category JOIN supplier_branch ON (spb_branch_code=SUPPLIER_BRANCH_CODE)
			JOIN COUNTRY ON (spb_country=CNT_COUNTRY_CODE)
			JOIN CONTINENT ON (CON_CODE = CNT_CON_CODE)
		WHERE
			PRODUCT_CATEGORY_ID = :categoryId
			AND con_code = :continentId
		GROUP BY
			CON_CODE
		ORDER BY
			COUNT(*) DESC
		";
		
		return $this->db->fetchAll($sql, array('categoryId' => $categoryId, 'continentId' => $continentId));
	}

	public function getDataByContinents($categoryId)
	{
		$sql = "
		SELECT
			CON_CODE,
			COUNT(*)
		FROM
			supply_category JOIN supplier_branch ON (spb_branch_code=SUPPLIER_BRANCH_CODE)
			JOIN COUNTRY ON (spb_country=CNT_COUNTRY_CODE)
			JOIN CONTINENT ON (CON_CODE = CNT_CON_CODE)
		WHERE
			PRODUCT_CATEGORY_ID = :categoryId
			AND directory_entry_status = 'PUBLISHED'
	        AND spb_account_deleted = 'N'
	        AND spb_test_account = 'N'
		GROUP BY
			CON_CODE
		ORDER BY
			COUNT(*) DESC
		";
		
		return $this->db->fetchAll($sql, array('categoryId' => $categoryId));
		
	}

	public function getDataByCountries($categoryId)
	{
		$sql = "
		SELECT
			CON_CODE,
			CNT_COUNTRY_CODE,
			COUNT(*)
		FROM
			supply_category JOIN supplier_branch ON (spb_branch_code=SUPPLIER_BRANCH_CODE)
			JOIN COUNTRY ON (spb_country=CNT_COUNTRY_CODE)
			JOIN CONTINENT ON (CON_CODE = CNT_CON_CODE)
		WHERE
			PRODUCT_CATEGORY_ID = :categoryId
			AND directory_entry_status = 'PUBLISHED'
	        AND spb_account_deleted = 'N'
	        AND spb_test_account = 'N'
		GROUP BY
			CON_CODE, CNT_COUNTRY_CODE
		ORDER BY
			COUNT(*) DESC
		";
		return $this->db->fetchAll($sql, array('categoryId' => $categoryId));
		
	}
	
	public function getContinentAndCountryByCategoryId($categoryId)
	{
		$sql = "
			SELECT 
			  DISTINCT(pbl_cnt_country_code) as CNT_COUNTRY_CODE, 
			  C.CNT_NAME,       
			  PC.NAME as displayname, PC.NAME, PC.ID,       
			  PC.BROWSE_PAGE_NAME, PC.PAGE_TITLE_NAME, 
			  PC.REFINED_SEARCH_DISPLAY_NAME,       
			  CON.CON_CODE, CON.CON_NAME   
			FROM 
			  PAGES_BROWSE_LINK PBL, 
			  PRODUCT_CATEGORY PC, 
			  COUNTRY C, 
			  CONTINENT CON 
			WHERE 
			  PBL.PBL_CATEGORY_ID = PC.ID   
			  AND PBL.PBL_CNT_COUNTRY_CODE = C.CNT_COUNTRY_CODE   
			  AND C.CNT_CON_CODE = CON.CON_CODE   
			  AND PBL_BROWSE_TYPE = 'category'
			  AND PBL_CATEGORY_ID = :categoryId
			  
			ORDER BY 
			  CON.CON_NAME ASC, C.CNT_NAME ASC
		";
		return $this->db->fetchAll($sql, array('categoryId' => $categoryId));
	
	}
	
		
	public function getDataByContinentAndCategoryId($categoryId, $continentId, $negates = false)
	{
		$sql = "
		SELECT
			CNT_COUNTRY_CODE,
			COUNT(*)
		FROM
			supply_category JOIN supplier_branch ON (spb_branch_code=SUPPLIER_BRANCH_CODE)
			JOIN COUNTRY ON (spb_country=CNT_COUNTRY_CODE)
			JOIN CONTINENT ON (CON_CODE = CNT_CON_CODE)
		WHERE
			PRODUCT_CATEGORY_ID = :categoryId
			AND CON_CODE " . ( ($negates) ? "!=":"=") . " :continentId
		GROUP BY
			CNT_COUNTRY_CODE
		ORDER BY
			COUNT(*) DESC
		";
		return $this->db->fetchAll($sql, array('categoryId' => $categoryId, 'continentId' => $continentId));
		
	}

}
