<?php
class Shipserv_Zone_HighlightBanner extends Shipserv_Object
{
	public function __construct ($data)
	{
		// populate the supplier object
		if (is_array($data))
		{
			foreach ($data as $name => $value)
			{
				$this->{$name} = $value;
			}
		}
	}
	
	private function createObjectFromDb( $data )
	{
		$object = new self($data);
		return $object;
	}
	
	public static function getInstanceById( $id )
	{
		$dao = new Shipserv_Oracle_Zone_HighlightBanner;
		$row = $dao->fetchById( $id );
		$data = parent::camelCase($row);
		return self::createObjectFromDb($data);
	}
	
	public function getUrl( $impressionId )
	{
		if ( $this->pzsOtherUrl != "" )
		{
			$url = $this->pzsOtherUrl;
		}
		else
		{
			$supplier = Shipserv_Supplier::getInstanceById($this->pzsTnid);
			$url = $supplier->getUrl() . "";
			$h = new Myshipserv_View_Helper_SupplierProfileUrl;
			$url = $h->supplierProfileUrl($supplier, 'HIGHLIGHT_BANNER');
			
			// add source
			//$url = $url . ( ( strstr($url, '?') === false ) ? "?":"&" ) . "source=HighlightBanner";
 
		}
		
		$uri = new Myshipserv_View_Helper_Uri();
		$url = $uri->obfuscate($url);
		$url = '/supplier/track?w=h&id=' . $this->pzsId . '&i=' . $impressionId . '&u=' . $url;
		
		return $url;
	}
	
	public function logClick($id)
	{
		$sql = "
			UPDATE PAGES_ZONE_SPONSORSHIP_STATS SET PZT_IS_CLICKED=1 WHERE pzt_id=:id		
		";
		$this->getDb()->query($sql, array('id' => $id));
		
	}
	
	public function logImpression($searchId)
	{
		$config = $this->getConfig();
		$appVersion = Myshipserv_Config::getApplicationReleaseVersion();
		$browser = new Shipserv_Browser();
		$geodata = new Shipserv_Geodata();
		$ipAddress = Shipserv_Adapters_Analytics::getIpAddress();
		
		$user = $this::getUser();
		if( $user !== false )
		{
			$userId = $user->userId;
		}
		// create unique id
		$uniqueId = 
			session_id() 
			. "|||||" . $ipAddress
			. "|||||" . $browser->fetchName() 
			. "|||||" . $browser->agent 
			. '|||||' . $searchId
			. "|||||" . $userId
		;
		//echo "<hr />" . $uniqueId . "<hr />";
		$uniqueId = md5($uniqueId);
		
		$params = array(
			'PZT_USR_USER_CODE' => $userId
			, 'PZS_ID' => $this->pzsId
			, 'PZT_PZS_ID' => $this->pzsId
			, 'PZT_APP_VERSION' => $appVersion
			, 'PZT_VIEWER_IP_ADDRESS' => $ipAddress
			, 'PZT_USER_AGENT' => $browser->agent
			, 'PZT_UNIQUE_ID' => $uniqueId
		);
		
		$sql = "
			MERGE INTO PAGES_ZONE_SPONSORSHIP_STATS 
				USING DUAL ON (PZT_UNIQUE_ID = :PZT_UNIQUE_ID AND PZT_PZS_ID=:PZS_ID)
				WHEN NOT MATCHED THEN
					INSERT
						(
							PZT_USR_USER_CODE,
							PZT_PZS_ID,
							PZT_APP_VERSION,
						  	PZT_VIEWER_IP_ADDRESS,
						  	PZT_USER_AGENT,
						  	PZT_UNIQUE_ID				
						)
					VALUES
						(
							:PZT_USR_USER_CODE,
							:PZT_PZS_ID,
							:PZT_APP_VERSION,
						  	:PZT_VIEWER_IP_ADDRESS,
						  	:PZT_USER_AGENT,
						  	:PZT_UNIQUE_ID
						)
		";
		
		// improve
		try
		{
			$this->getDb()->query($sql, $params);
		}
		catch(Exception $e)
		{
		}
		

		$sql = "SELECT pzt_id FROM pages_zone_sponsorship_stats WHERE pzt_unique_id=:uniqueId";
		return $this->getDb()->fetchOne($sql, compact(uniqueId));
	}
}

