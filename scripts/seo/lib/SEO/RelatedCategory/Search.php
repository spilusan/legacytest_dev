<?php
class SEO_RelatedCategory_Search
{

	public function run( $categoryName = "" )
	{
		Logger::log("Start - Initialising");
		$db = $GLOBALS['application']->getBootstrap()->getResource('db');
		
		if( $categoryName != "" )
		{
			Logger::log("Processing category: " . $categoryName);
				
			$sql = "SELECT DISTINCT prl_category_name FROM pages_related_categories WHERE prl_category_name='" . $categoryName . "'";
		}
		else
		{
			$sql = "SELECT DISTINCT prl_category_name FROM pages_related_categories";
		}
				
		$categoriesName = $db->fetchAll($sql);
		
		foreach($categoriesName as $category)
		{
			$sql = "SELECT * FROM pages_related_categories JOIN product_category ON prl_category_id=id WHERE prl_category_name='" . $category['PRL_CATEGORY_NAME'] . "'";
			$result = $db->fetchAll($sql);
			Logger::log("\tSelecting related categories for " . $category['PRL_CATEGORY_NAME']);
			
			foreach( $result as $row )
			{
				
				$sql = "DELETE FROM pages_seo_rel_categories WHERE PSR_PRL_CATEGORY_ID=" . $row['PRL_CATEGORY_ID'];
				$db->query($sql);
				Logger::log("\t\t" . ++$count . ". " . $row['NAME'] . " (ID: " . $row['PRL_CATEGORY_ID'] . ")");
				// get first 6 priority 1 links and 4 priority 2 links
				$sql = "
				SELECT all_data.*, rownum as rn FROM
				(
					SELECT * FROM (SELECT PRL_CATEGORY_ID FROM pages_related_categories WHERE prl_category_name='" . $category['PRL_CATEGORY_NAME'] . "' AND PRL_CATEGORY_ID<>" . $row['PRL_CATEGORY_ID'] . " AND PRL_PRIORITY=1 ORDER BY DBMS_RANDOM.VALUE) WHERE rownum<=6
					UNION
					SELECT * FROM (SELECT PRL_CATEGORY_ID FROM pages_related_categories WHERE prl_category_name='" . $category['PRL_CATEGORY_NAME'] . "' AND PRL_CATEGORY_ID<>" . $row['PRL_CATEGORY_ID'] . " AND PRL_PRIORITY=2 ORDER BY DBMS_RANDOM.VALUE)
				) all_data
				WHERE
					rownum <= 10
				";
			
				$result2 = $db->fetchAll($sql);
				foreach( $result2 as $row2 )
				{
					$sql = "INSERT INTO PAGES_SEO_REL_CATEGORIES(PSR_PRL_CATEGORY_ID, PSR_RELATED_CATEGORY_ID) VALUES(".$row['PRL_CATEGORY_ID'].",".$row2['PRL_CATEGORY_ID'].") ";
					$db->query($sql);
				}
			}
		}
		Logger::log("End");
	}
}