<?php
class Shipserv_Agreement_PrivacyPolicy extends Shipserv_Agreement
{
	public static function getLatest()
	{
		
		$sql = "SELECT ppp_id FROM pages_privacy_policy WHERE ppp_is_active=1 AND rownum=1 ORDER BY ppp_id DESC";

		$result = Shipserv_Helper_Database::registryFetchOne(__CLASS__ . '_' . __FUNCTION__, $sql);
		
		return self::getInstanceById($result);
	}
	
	public function agree()
	{
		if( $this->user == null )
		{
			throw Exception("User isn't set");
		}
	
		$sql = "
			MERGE INTO pages_user_agreement USING DUAL ON (pua_psu_id=:userId AND PUA_PPP_ID=:agreementId )
				WHEN MATCHED THEN
					UPDATE SET
						PUA_DATE_UPDATED=SYSDATE
				WHEN NOT MATCHED THEN
					INSERT (PUA_PPP_ID, PUA_PSU_ID, PUA_DATE_CREATED)
					VALUES (:agreementId, :userId, SYSDATE)
		";
	
		parent::getDb()->query($sql, array('userId' => $this->user->userId, 'agreementId' => $this->pppId));
		parent::getDb()->commit();
	}
	
	public function userHasAgreed()
	{
		if( $this->user == null )
		{
			throw Exception("User isn't set");
		}
		
		$sql = "SELECT COUNT(*) FROM pages_user_agreement WHERE pua_psu_id=:userId AND pua_ppp_id=:agreementId";
		
		$params = array(
				'userId' => $this->user->userId,
				'agreementId' => $this->pppId
		);
		
		$result = ((Shipserv_Helper_Database::registryFetchOne(__CLASS__ . '_' . __FUNCTION__, $sql, $params)) > 0);

		return $result;
	}
	
	private static function createObjectFromDb( $data )
	{
		$object = new self($data);
	
		return $object;
	}
	
	public function __construct ($data)
	{
		if (is_array($data))
		{
			foreach ($data as $name => $value)
			{
				$this->{$name} = $value;
			}
		}
	}
	
	
	public static function getInstanceById( $id )
	{
		$sql = "SELECT * FROM pages_privacy_policy WHERE ppp_id=:id";
		$row = parent::getDb()->fetchAll($sql, array('id' => $id));
		$data = parent::camelCase($row[0]);
		return self::createObjectFromDb($data);
	}
	
	
}

