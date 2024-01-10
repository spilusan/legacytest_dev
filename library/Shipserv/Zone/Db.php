<?php

class Shipserv_Zone_Db extends Shipserv_Object {

	public static function getUrlToZone()
	{
		$sql = "SELECT pgz_system_name, pgz_url FROM pages_zone";

		foreach( parent::getDb()->fetchAll($sql) as $row )
		{
			$data[$row['PGZ_SYSTEM_NAME']] = $row['PGZ_URL'];
		}

		return $data;
	}

	public static function getZoneXmlObject( $name )
	{
		 $sql = "
				SELECT
				  pz.pgz_xml_content,
				  pz.pgz_is_active
				FROM
				  pages_zone pz
				LEFT JOIN
				  pages_zone_mapping pzm
				  ON pzm.pzm_pgz_system_name = pz.pgz_system_name
				WHERE
				  pgz_system_name=:name";
		
		foreach( (array) parent::getDb()->fetchAll($sql, array('name' => $name)) as $row )
		{
			$result = simplexml_load_string($row['PGZ_XML_CONTENT']);
			
			//Modified for returning valut to: DE7297 Directly accessing deactivated zone should raise 404
			if ($result) {
				$result->addChild('zoneIsActive', $row['PGZ_IS_ACTIVE']);
			}
			
			return $result;
		}
	}

	public static function getZoneData()
	{
		$sql = "SELECT pgz_system_name, pgz_name, pgz_xml_content FROM pages_zone";

		foreach( parent::getDb()->fetchAll($sql) as $row )
		{
			$data[$row['PGZ_SYSTEM_NAME']] = array('name' => $row['PGZ_NAME']);
			if( $row['PGZ_XML_CONTENT'] == "" )
			{
				$config = parent::getConfig();
				$sql = "UPDATE pages_zone SET pgz_xml_content=:xml WHERE pgz_system_name='" . $row['PGZ_SYSTEM_NAME'] . "'";
				$contentXML = file_get_contents($config->includePaths->library  . "/zones/" . $row['PGZ_SYSTEM_NAME'] . ".xml");
				parent::getDb()->query($sql, array('xml' => $contentXML));
			}
		}

		return $data;
	}

	public static function getZoneDataForHomepage()
	{
		$sql = "SELECT pgz_system_name, pgz_url, pgz_homepage_image, pgz_homepage_style, pgz_homepage_title, pgz_homepage_text
				 FROM pages_zone WHERE pgz_is_active=1";

		foreach( parent::getDb()->fetchAll($sql) as $row )
		{
			if( $row['PGZ_HOMEPAGE_IMAGE'] != "" ) $d['image'] = $row['PGZ_HOMEPAGE_IMAGE'];
			if( $row['PGZ_HOMEPAGE_TITLE'] != "" ) $d['title'] = $row['PGZ_HOMEPAGE_TITLE'];
			if( $row['PGZ_HOMEPAGE_STYLE'] != "" ) $d['style'] = $row['PGZ_HOMEPAGE_STYLE'];
			if( $row['PGZ_URL'] != "" ) $d['zoneUrl'] = $row['PGZ_URL'];
			if( $row['PGZ_HOMEPAGE_TEXT'] != "" ) $d['text'] = $row['PGZ_HOMEPAGE_TEXT'];

			$data[$row['PGZ_SYSTEM_NAME']] = $d;
		}
		return $data;

	}

	public static function getZoneMapping()
	{
		$sql = "
				SELECT
				  pzm.pzm_pgz_system_name,
				  pzm.pzm_map_to
				FROM
				  pages_zone_mapping pzm
				JOIN
				  pages_zone pz
				  ON pzm.pzm_pgz_system_name = pz.pgz_system_name
				WHERE
				  pz.pgz_is_active = 1";

		foreach( parent::getDb()->fetchAll($sql) as $row )
		{
			$data[$row['PZM_MAP_TO']] = $row['PZM_PGZ_SYSTEM_NAME'];
		}

		return $data;
	}

	public static function getZoneKeyword()
	{
		$sql = "
			SELECT DISTINCT
				pzk.pzk_pgz_system_name,
				pzk.pzk_keyword,
				pzk.pzk_auto_redirect 
			FROM
			    pages_zone_keyword pzk
			    JOIN pages_zone pz
			    ON pz.pgz_system_name = pzk.pzk_pgz_system_name
			WHERE
			    pz.pgz_is_active = 1
				";

		foreach (parent::getDb()->fetchAll($sql) as $row) {
			$data[] = array(
				'keyword' => $row['PZK_KEYWORD'],
				'system_name' => $row['PZK_PGZ_SYSTEM_NAME'],
				'auto_redirect' => (int)$row['PZK_AUTO_REDIRECT'],
			);
		}

		return $data;
	}
}
