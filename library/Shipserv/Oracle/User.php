<?php

class Shipserv_Oracle_User extends Shipserv_Oracle
{
	const
        ALERTS_IMMEDIATELY = 'I',
	    ALERTS_WEEKLY      = 'W',
	    ALERTS_NEVER       = 'N'
    ;

    /**
     * @var Zend_Db_Adapter_Oracle
     */
	protected $db;
	
	function __construct($db = null)
	{
		if( $db == null )
		{
			$this->db = $this->getDb();
		}
		else 
		{
			$this->db = $db;
		}
	}
	
	/**
	 * Fetches valid Pages users.
	 * 
	 * @return Shipserv_Oracle_User_UserCollection
	 */
	public function fetchUsers (array $ids, $type = "P")
	{
		// Fetch USER rows and pull out type = 'P'
		$uuById = array();
		foreach ($this->getUsers($ids, array()) as $u)
		{
			//if ($type == "P" && $u['USR_TYPE'] == 'P') 
				$uuById[$u['USR_USER_CODE']] = $u;
		}
		
		// Fetch corresponding PAGES_USER rows & build array of Shipserv_User instances
		$ssuArr = array();
		$ssuInactiveArr = array();

		if( $type == "" )
		{
			// get non pages users
			foreach ($this->getNonPagesUsers(array_keys($uuById), array()) as $pu)
			{
				$thisSsu = Shipserv_User::fromDb(array_merge($uuById[$pu['PSU_ID']], $pu));
				if ($uuById[$pu['PSU_ID']]['USR_STS'] != 'INA') $ssuArr[] = $thisSsu;
				else $ssuInactiveArr[] = $thisSsu;
			}
		}
		
		// get pages
		foreach ($this->getPagesUsers(array_keys($uuById), array()) as $pu)
		{
			$thisSsu = Shipserv_User::fromDb(array_merge($uuById[$pu['PSU_ID']], $pu));
			if ($uuById[$pu['PSU_ID']]['USR_STS'] != 'INA') $ssuArr[] = $thisSsu;
			else $ssuInactiveArr[] = $thisSsu;
		}
				
		return new Shipserv_Oracle_User_UserCollection($ssuArr, $ssuInactiveArr);
	}
	
	/**
	 * Fetch valid Pages user by ID.
	 * 
	 * @return Shipserv_User
	 * @throws Shipserv_Oracle_User_Exception_NotFound
	 */
	public function fetchUserById ($userId, $type = "P", $skipCheck = false)
	{
		$userId = (int) $userId;
		$uArr = $this->fetchUsers(array($userId), $type)->makeShipservUsers($skipCheck);
		
		if (!$uArr) throw new Shipserv_Oracle_User_Exception_NotFound();
		return $uArr[0];
	}
	
	/**
	 * Fetch valid Pages users by e-mail.
	 * 
	 * @return Shipserv_Oracle_User_UserCollection
	 */
	public function fetchUsersByEmails(array $emails, $skipRegistryCache = false)
	{
		// Normalize $emails array carefully - avoids some issues with dirty data
		foreach ($emails as $i => $em) $emails[$i] = strtolower(trim($em));
		$emails = array_unique($emails);
		
		// Fetch PAGES_USER rows and index by id
		$puuById = array();
		foreach ($this->getPagesUsers(array(), $emails, $skipRegistryCache) as $pu) $puuById[$pu['PSU_ID']] = $pu;
		
		// Fetch corresponding USER rows & build array of Shipserv_User instances
		$ssuArr = array();
		$ssuInactiveArr = array();
		
		foreach ($this->getUsers(array_keys($puuById), array()) as $u)
		{
			if ($u['USR_TYPE'] == 'P')
			{
				$pu = $puuById[$u['USR_USER_CODE']];
				$emailIdx = strtolower($pu['PSU_EMAIL']);
				
				if ($u['USR_STS'] != 'INA')
				{
					$ssuArr[$emailIdx] = Shipserv_User::fromDb(array_merge($u, $pu));
				}
				else
				{
					$ssuInactiveArr[] = $emailIdx;
				}
			}
		}
		
		return new Shipserv_Oracle_User_UserCollection($ssuArr);
	}
	
	/**
	 * Fetch valid Pages user by e-mail.
	 * 
	 * @return Shipserv_User
	 * @throws Exception if not found
	 */
	public function fetchUserByEmail ($email, $skipRegistryCache = false)
	{
		$uArr = $this->fetchUsersByEmails(array($email), $skipRegistryCache)->makeShipservUsers();
		if (!$uArr) throw new Shipserv_Oracle_User_Exception_NotFound();
		return $uArr[0];
	}
	
	public function testUserExistanceByEmail ($email)
	{
			// Normalize $email carefully - avoids some issues with dirty data
			$email = strtolower(trim($email));
			$sql = "SELECT COUNT(*) FROM PAGES_USER WHERE PSU_EMAIL = '$email'";
			
			return $this->db->fetchOne($sql) != '0';
	}
	
	/**
	 * Update user details in PAGES_USER table. Throws exception if user does not exist.
	 * Refactored by Yuriy Akopov on 2014-09-22, DE5298
     *
	 * @param   string  $userId
	 * @param   string  $firstName
	 * @param   string  $lastName
	 * @param   string  $alertStatus
     * @param   string  $anonymityFlag
	 * @param   string  $alias
	 * @param   string  $companyName
	 * @param   int     $pctId              Pages company type ID, or null
	 * @param   string  $otherCompanyType
	 * @param   int     $pjfId              Pages job function ID, or null
	 * @param   string  $otherJobFunction
     *
	 * @throws  Exception   if $userId does not exist
	 */
	public function updatePagesUser($userId, $firstName, $lastName, $alertStatus, $anonymityFlag, $alias, $companyName, $pctId, $otherCompanyType, $pjfId, $otherJobFunction) {
		if (!in_array($alertStatus, array(
            self::ALERTS_IMMEDIATELY,
            self::ALERTS_WEEKLY,
            self::ALERTS_NEVER
        ))) {
            throw new Exception("Invalid alert status: '$alertStatus' specified for user " . $userId);
        }

        if (!in_array($anonymityFlag, array(
            Shipserv_User::ANON_LEVEL_NONE,
            Shipserv_User::ANON_LEVEL_COMPANY_JOB,
            Shipserv_User::ANON_LEVEL_COMPANY,
            Shipserv_User::ANON_LEVEL_ALL
        ))) {
            throw new Exception("Invalid anonymity status: '$anonymityFlag' specified for user " . $userId);
        }
		
		// Validate company type id
		if (strlen($pctId)) {
			$pctId = (int) $pctId;
			$row = $this->db->fetchRow("SELECT * FROM PAGES_USER_COMPANY_TYPE WHERE PCT_ID = :pctId", array('pctId' => $pctId));
			if (empty($row)) {
                throw new Exception("Invalid company type id: " . $pctId . " when updating user " , $userId);
            }
		} else {
			$pctId = null;
		}
		
		// Validate job function id
		if (strlen($pjfId)) {
			$pjfId = (int) $pjfId;
			$row = $this->db->fetchRow("SELECT * FROM PAGES_USER_JOB_FUNCTION WHERE PJF_ID = :pjfId", array('pjfId' => $pjfId));
			if (empty($row)) {
                throw new Exception("Invalid job function id: " . $pjfId . " when updating user " . $userId);
            }
		} else {
			$pctId = null;
		}
		
        $result = $this->db->update(
            Shipserv_User::TABLE_NAME,
            array(
                Shipserv_User::COL_NAME_FIRST => $firstName,
                Shipserv_User::COL_NAME_LAST  => $lastName,
                Shipserv_User::COL_ALERT_STS  => $alertStatus,
                Shipserv_User::COL_ALIAS      => $alias,
                Shipserv_User::COL_COMPANY    => $companyName,
                Shipserv_User::COL_COMPANY_TYPE_ID     => $pctId,
                Shipserv_User::COL_JOB_FUNCTION_ID     => $pjfId,
                Shipserv_User::COL_JOB_FUNCTION_OTHER => $otherJobFunction,
                Shipserv_User::COL_COMPANY_TYPE_OTHER  => $otherCompanyType,
                Shipserv_User::COL_UPDATED_BY   => 'PAGES',
                Shipserv_User::COL_UPDATED_DATE => new Zend_Db_Expr('SYSDATE'),
                Shipserv_User::COL_ANONYMITY  => $anonymityFlag
            ),
            $this->db->quoteInto(Shipserv_User::COL_ID . ' = ?', $userId)
        );

        if ($result === 1) {
            return;
        }

        if ($result === 0) {
            throw new Exception("Failed to update user " . $userId);
        }

        throw new Exception("An unexpected error happened when updating user " . $userId . ", more than 1 record updated");

        /*
		// Attempt to update PAGES_USER and return if successful
		$sql = "UPDATE PAGES_USER SET
				PSU_FIRSTNAME    = :firstName,
				PSU_LASTNAME     = :lastName,
				PSU_ALERT_STATUS = :alertStatus,
				PSU_ALIAS        = :alias,
				PSU_COMPANY      = :companyName,
				PSU_PCT_ID       = :pctId,
				PSU_OTHER_COMP_TYPE = :otherCompanyType,
				PSU_PJF_ID      = :pjfId,
				PSU_OTHER_JOB_FUNCTION = :otherJobFunction,
				PSU_LAST_UPDATE_BY = 'PAGES',
				PSU_LAST_UPDATE_DATE = SYSDATE,
				PSU_ANONYMITY_FLAG = :privacySetting
			WHERE PSU_ID = :userId";
		
		$stmt = $this->db->query($sql, compact(
			'firstName',
			'lastName',
			'alertStatus',
			'alias',
			'companyName',
			'pctId',
			'otherCompanyType',
			'pjfId',
			'otherJobFunction',
			'userId'));
		
		if ($stmt->rowCount() == 0)
		{
			throw new Exception("Failed to update user: $userId");
		}
        */
	}

    /**
     * Does the same as updatePagesUser() above, only takes parameters as an array
     * These two functions need to be unified together one day.
     *
     * So far it was only somewhat refactored by Yuriy Akopov on 2014-09-25
     *
     * @param   array           $data
     * @param   Shipserv_user   $user
     *
     * @throws Exception
     * @throws Zend_Db_Adapter_Exception
     */
	public function updatePagesUserByArray(array $data, Shipserv_User $user) {
        $data['userId'] = $user->userId;

        // @todo: the following two values are taked from the existing profile, so might just skip updating them probably? to check with Elvir
		$data['alertStatus'] = $user->alertStatus;
		if (!in_array($data['alertStatus'], array(
            self::ALERTS_IMMEDIATELY,
            self::ALERTS_WEEKLY,
            self::ALERTS_NEVER
        ))) {
            throw new Exception("Invalid alert status: " . $data['alertStatus'] . " specified for user " . $data['userId']);
        }

        $data['anonymitySetting'] = $user->anonymityFlag;
        if (!in_array($data['anonymitySetting'], array(
            Shipserv_User::ANON_LEVEL_NONE,
            Shipserv_User::ANON_LEVEL_COMPANY_JOB,
            Shipserv_User::ANON_LEVEL_COMPANY,
            Shipserv_User::ANON_LEVEL_ALL
        ))) {
            throw new Exception("Invalid anonymity status: " . $data['anonymitySetting'] . " specified for user " . $data['userId']);
        }

		if (strlen($data['companyType'])) {
			$pctId = (int) $data['companyType'];
			$rows = $this->db->fetchAll("SELECT * FROM PAGES_USER_COMPANY_TYPE WHERE PCT_ID = :pctId", array('pctId' => $pctId));
			if (!$rows) throw new Exception("Invalid company type id: '$pctId'");
		} else {
			$pctId = null;
		}
	
		// Validate job function id
		if ($data['jobFunction'] != '')
		{
			$pjfId = (int) $data['jobFunction'];
			$rows = $this->db->query("SELECT * FROM PAGES_USER_JOB_FUNCTION WHERE PJF_ID = :pjfId", compact('pjfId'));
			if (!$rows) throw new Exception("Invalid job function id: '$pjfId'");
		} else {
			$pctId = null;
		}
		
		$data['cAddress'] = implode("\n", array($data['cAddress1'], $data['cAddress2'], $data['cAddress3']));

		/*
		unset($data['cAddress1']);
		unset($data['cAddress2']);
		unset($data['cAddress3']);
        $vesselType = $data['vesselType'];
		unset($data['vesselType']);
		*/
		
		// Attempt to update PAGES_USER and return if successful
        $result = $this->db->update(Shipserv_User::TABLE_NAME, array(
                Shipserv_User::COL_NAME_FIRST => $data['name'],
                Shipserv_User::COL_NAME_LAST  => $data['surname'],
                Shipserv_User::COL_ALERT_STS  => $data['alertStatus'],
                Shipserv_User::COL_ALIAS      => $data['alias'],
                Shipserv_User::COL_COMPANY    => $data['company'],
                Shipserv_User::COL_COMPANY_TYPE_ID     => $data['companyType'],
                Shipserv_User::COL_COMPANY_TYPE_OTHER  => $data['otherCompanyType'],
                Shipserv_User::COL_JOB_FUNCTION_ID     => $data['jobFunction'],
                Shipserv_User::COL_JOB_FUNCTION_OTHER  => $data['otherJobFunction'],
                Shipserv_User::COL_UPDATED_BY     => 'PAGES',
                Shipserv_User::COL_UPDATED_DATE   => new Zend_Db_Expr('SYSDATE'),
                Shipserv_User::COL_DECISION_MAKER => $data['isDecisionMaker'],
                Shipserv_User::COL_ANONYMITY      => $data['anonymitySetting'],
                Shipserv_User::COL_COMPANY_ADDRESS   => $data['cAddress'],
                Shipserv_User::COL_COMPANY_POSTCODE  => $data['cZipcode'],
                Shipserv_User::COL_COMPANY_COUNTRY   => $data['cCountryCode'],
                Shipserv_User::COL_COMPANY_PHONE     => $data['cPhone'],
                Shipserv_User::COL_COMPANY_WEBSITE   => $data['cWebsite'],
                Shipserv_User::COL_COMPANY_SPENDING  => $data['cSpending'],
                Shipserv_User::COL_COMPANY_VESSEL_NO => $data['cNoOfVessel']
            ),
            $this->db->quoteInto(Shipserv_User::COL_ID . ' = ?', $data['userId'])
        );

        if ($result === 0) {
            throw new Exception("Failed to update user " . $data['userId']);
        } else if ($result > 1) {
            throw new Exception("An unexpected error happened when updating useer " . $data['userId']);
        }

        /*
		$sql = "
			UPDATE PAGES_USER SET
				PSU_FIRSTNAME = :name,
				PSU_LASTNAME = :surname,
				PSU_ALERT_STATUS = :alertStatus,
				PSU_ALIAS = :alias,
				PSU_COMPANY = :company,
				PSU_PCT_ID = :companyType,
				PSU_OTHER_COMP_TYPE = :otherCompanyType,
				PSU_PJF_ID = :jobFunction,
				PSU_OTHER_JOB_FUNCTION = :otherJobFunction,
				PSU_LAST_UPDATE_BY = 'PAGES',
				PSU_LAST_UPDATE_DATE = SYSDATE,
				PSU_COMPANY_TYPE = :companyType,

				PSU_IS_DECISION_MAKER = :isDecisionMaker,
				PSU_ANONYMITY_FLAG = :anonymitySetting,
				
				PSU_COMPANY_ADDRESS = :cAddress,
			  	PSU_COMPANY_ZIP_CODE = :cZipcode,
			  	PSU_COMPANY_COUNTRY_CODE = :cCountryCode,
			  	PSU_COMPANY_PHONE = :cPhone,
			  	PSU_COMPANY_WEBSITE = :cWebsite,
			  	PSU_COMPANY_SPENDING = :cSpending,
			  	PSU_COMPANY_NO_VESSEL = :cNoOfVessel
							
			WHERE PSU_ID = :userId";

		$stmt = $this->db->query($sql, $data);
	
		if ($stmt->rowCount() == 0)
		{
			throw new Exception("Failed to update user: $userId");
		}

		$this->db->query("DELETE FROM pages_user_vessel_type WHERE puv_psu_id=:userId", array('userId' => $data['userId']));
	    */

		// re-inserting vessel types that user had chosen
        $this->db->delete('pages_user_vessel_type', $this->db->quoteInto('puv_psu_id = ?', $data['userId']));

        $vesselType = $data['vesselType'];
		foreach((array) $vesselType as $id){
            $this->db->insert('pages_user_vessel_type', array(
                'puv_psu_id'      => $data['userId'],
                'puv_vessel_type' => $id
            ));
            /*
			$this->db->query("INSERT INTO pages_user_vessel_type(puv_psu_id, puv_vessel_type) VALUES(:userId, :vesselType)", array(
                'userId' => $data['userId'],
                'vesselType' => $id)
            );
            */
		}
		
	}
	
	/**
	 * Fetch Pages User (requires both USER and PAGES_USER).
	 *
	 * @param string &$password returns user's password
	 * @return Shipserv_User
	 * @throws Exception if not found
	 */
	public function fetchPagesUserByUsername($username, &$password = '', &$status = '')
	{
		$uArr = $this->getUsers(array(), array($username));
		if (!$uArr) throw new Shipserv_Oracle_User_Exception_NotFound("User not found for username: '$username'");
		
		$u = $uArr[0];
		if ($u['USR_TYPE'] != 'P') throw new Shipserv_Oracle_User_Exception_NotFound("Expected user type 'P', found: '{$u['USR_TYPE']}'");
		
		$puArr = $this->getPagesUsers(array($u['USR_USER_CODE']), array());
		if (!$puArr) throw new Shipserv_Oracle_User_Exception_NotFound("Pages User not found for id: '{$u['USR_USER_CODE']}'");

		$status = $u['USR_STS'];
		$pu = $puArr[0];
		return Shipserv_User::fromDb(array_merge($u, $pu));
	}
	
	/**
	 * Check if login username already exists in system (USERS table only)
	 *
	 * @return bool
	 */
	public function boolUsernameExists ($username)
	{
		return (bool) $this->getUsers(array(), array($username));
	}
	
	/**
	 * Mark user's email as confirmed (or unconfirmed)
	 */
	public function confirmEmail ($userId, $confirm = true)
	{
		$sql = "UPDATE PAGES_USER SET PSU_EMAIL_CONFIRMED = :emailConfirmed WHERE PSU_ID = :userId";
		$stmt = $this->db->query($sql, array('emailConfirmed' => $confirm ? 'Y' : 'N', 'userId' => $userId));
		if ($stmt->rowCount() == 0) throw new Exception("Failed to update user: '$userId'");
	}
	
	/**
	 * Extract column from array.
	 * 
	 * @return array
	 */
	private function getArrCol (array $arr, $colName)
	{
		$colArr = array();
		foreach ($arr as $r) $colArr[] = @$r[$colName];
		
		return $colArr;
	}
	
	/**
	 * Create Pages User (and User record).
	 * BUY-962 Refactored by Attila O / Removing plain text password dependency
	 *
	 * @return array
	 * @throws Shipserv_Oracle_User_Exception_FailCreateUser
	 */
	public function createUser($email, $firstName, $lastName, $password = null, $companyName = '', $emailConfirmed = 'N', $creator = 'PAGES')
	{
		// Standardize email
		$email = strtolower(trim($email));
		
		// Check that e-mail is well-formed
		if (!self::staticIsEmail($email)) {
			throw new Shipserv_Oracle_User_Exception_FailCreateUser("Invalid e-mail: $email", Shipserv_Oracle_User_Exception_FailCreateUser::CODE_BAD_EMAIL);
		}
		
		// Trim names
		$firstName = trim($firstName);
		$lastName = trim($lastName);
		
		// If no password provided, generate one randomly
		if ($password == '') {
			$password = $this->genPassword();
		}
		
		// Trim company name
		$companyName = trim($companyName);
		
		// Validate email confirmed
		if (!Shipserv_User::isValidEmailConfirmed($emailConfirmed)) {
			throw new Exception("Invalid email confirmation value: '$emailConfirmed'");
		}
		
		// attempt to register
		$authAdapter = new Shipserv_Adapters_Authentication();
		$result = $authAdapter->register(
        		    $email,
        		    $password,
        		    $firstName,
        		    $lastName,
        		    $companyName,
        		    '',    // companyTypeId,
        		    '',    // otherCompanyType,
        		    null,  // jobFunctionId,
        		    '',    // otherJobFunction,
        		    false, // marketingUpdated,
        		    null,  // companyType,
        		    null,  // isDecisionMaker,
        		    null,  // companyAddress,
        		    null,  // companyZipCode,
        		    null,  // companyCountryCode,
        		    null,  // companyPhoneNo, 
        		    null,  // companyWebsite,
        		    null,  // companySpending,
        		    null,  // vesselCount,
        		    null   // vesselType
		    );
		
		// If registration was successful
		if ($result['success'] == true) {
		    return array(
		        'password' => $password,
		    );
		} else {
		    $errorMessage = $result['messages'][0];
		    
		    if (stristr($errorMessage, 'duplicate username')) {
		        throw new Shipserv_Oracle_User_Exception_FailCreateUser(
		            "Account already exists for e-mail: '$email'",
		            Shipserv_Oracle_User_Exception_FailCreateUser::CODE_DUPLICATE
		            );
		    }
		    
		    throw new Shipserv_Oracle_User_Exception_FailCreateUser($errorMessage);
		}
	
	}
	
	public function enableAccessToSIR( $id )
	{
		$sql =
			"UPDATE PAGES_USER 
				SET PSU_SVR_ACCESS='Y'
				WHERE PSU_ID=:id
			";
		$rows = $this->getDb()->query($sql, array('id' => $id));
	}
	
	public function getUserIdByEmail( $email )
	{
		$sql = "SELECT psu_id FROM pages_user WHERE psu_email=:email";
		return $this->getDb()->fetchAll( $sql, array('email' => $email ));
		
	}
	
	private function isEmail ($val)
	{
		return self::staticIsEmail($val);
	}
	
	public static function staticIsEmail ($val)
	{
		return (bool) preg_match("/^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z]{2,3})$/", $val);
	}
	
	/*public function genPassword ()
	{
		// Seed random number generator
		list($usec, $sec) = explode(' ', microtime());
		$seed = (float) $sec + ((float) $usec * 100000);
		srand($seed);
		
		// Build random string
		$validChars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$maxIdx = strlen($validChars) - 1;
		$pw = '';
		for ($i = 0; $i < 8; $i++)
		{
			$cIdx = rand(0, $maxIdx);
			$pw .= substr($validChars, $cIdx, 1);
		}
		
		// echo "DEV: generated password: $pw<br>";
		return $pw;
	}*/
	public function genPassword()
	{
		// Character sets
		$lowercaseChars = 'abcdefghijklmnopqrstuvwxyz';
		$uppercaseChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$digits = '0123456789';
		$specialChars = '!@#$%^&*()-_=+';
		
		$allChars = $lowercaseChars . $uppercaseChars . $digits . $specialChars;
		
		// Ensure the password contains at least one of each type of character
		$pw = '';
		$pw .= $lowercaseChars[random_int(0, strlen($lowercaseChars) - 1)];
		$pw .= $uppercaseChars[random_int(0, strlen($uppercaseChars) - 1)];
		$pw .= $specialChars[random_int(0, strlen($specialChars) - 1)];
		
		// Add more characters to meet desired length (e.g., 8 characters)
		for ($i = 0; $i < 5; $i++)
		{
			$pw .= $allChars[random_int(0, strlen($allChars) - 1)];
		}
		
		// Shuffle the characters to randomize their order
		$pw = str_shuffle($pw);
    
		return $pw;
	}
	
	/**
	 * Fetch USER rows.
	 * 
	 * @return array
	 */
	private function getUsers (array $ids, array $usernames)
	{
		
		if (!$ids && !$usernames) return array();
		
		$idSql = $this->quoteArr($ids);
		
		foreach ($usernames as $i => $u) $usernames[$i] = strtolower($u);
		$unSql = $this->quoteArr($usernames);
		
		$sql = "SELECT * FROM USERS WHERE USR_USER_CODE IN ($idSql) OR LOWER(USR_NAME) IN ($unSql)";
		
		$result = Shipserv_Helper_Database::registryFetchAll(__CLASS__ . '_' . __FUNCTION__, $sql);

		return $result;
	}
	
	/**
	 * Fetch PAGES_USER rows. Note, this method does not check for a
	 * corresponding USER row, or USR_TYPE.
	 *
	 * @return array
	 */
	private function getPagesUsers(array $ids, array $emails, $skipRegistry = false)
	{
		if (!$ids && !$emails) return array();

		$idSql = $this->quoteArr($ids);
		
		foreach ($emails as $i => $v) $emails[$i] = strtolower($v);
		$emailSql = $this->quoteArr($emails);
		
		$sql =
			"SELECT
				PU.*
			FROM PAGES_USER PU
			WHERE
				PU.PSU_ID IN ($idSql) OR LOWER(PU.PSU_EMAIL) IN ($emailSql)";

		if ($skipRegistry === false) {
            $result = Shipserv_Helper_Database::registryFetchAll(__CLASS__ . '_' . __FUNCTION__, $sql);
        } else {
            $result =  $this->getDb()->fetchAll($sql);
        }

		return $result;
	}
	
	private function getNonPagesUsers (array $ids, array $emails)
	{
		if (!$ids && !$emails) return array();
		
		$idSql = $this->quoteArr($ids);
		
		foreach ($emails as $i => $v) $emails[$i] = strtolower($v);
		$emailSql = $this->quoteArr($emails);
		/*
			return new self($userRow['PSU_ID'], $userRow['USR_NAME'],
			$userRow['PSU_FIRSTNAME'], $userRow['PSU_LASTNAME'],
			$userRow['PSU_EMAIL'], $userRow['PSU_ALERT_STATUS'],
			$userRow['PSU_ALIAS'], $userRow['PSU_EMAIL_CONFIRMED'],
			$userRow['PSU_COMPANY'], $userRow['PSU_PCT_ID'],
			$userRow['PSU_OTHER_COMP_TYPE'], $userRow['PSU_PJF_ID'],
			$userRow['PSU_OTHER_JOB_FUNCTION'], $userRow['PSU_SVR_ACCESS']);
		 */
		
		$sql =
			"SELECT
				PU.USR_USER_CODE PSU_ID,
				PU.USR_NAME USR_NAME,
				PU.USR_NAME PSU_FIRSTNAME,
				PU.USR_NAME PSU_LASTNAME,
				PU.USR_NAME PSU_EMAIL,
				'N' PSU_ALERT_STATUS,
				'' PSU_EMAIL,
				'' PSU_EMAIL_CONFIRMED,
				'' PSU_COMPANY,
				'' PSU_PCT_ID,
				'' PSU_OTHER_COMP_TYPE,
				'' PSU_PJF_ID,
				'' PSU_OTHER_JOB_FUNCTION,
				'' PSU_SVR_ACCESS
			FROM 
				USERS PU
				
			WHERE
				PU.USR_USER_CODE IN ($idSql) ";
		
		return $this->getDb()->fetchAll($sql);
	}
	/**
	 * Fetch BUYER_BRANCH_USER rows. Note, this method does not check for a
	 * corresponding USER row, or USR_TYPE.
	 *
	 * @deprecated - PAGES should no longer be referencing this table.
	 * @return array
	 */
	private function getBuyerUsers (array $ids, array $emails)
	{
		throw new Exception("Deprecated");
		
		if (!$ids && !$emails) return array();
		
		$idSql = $this->quoteArr($ids);
		
		foreach ($emails as $i => $v)
		{
			// Exclude if does not validate as e-mail because I don't
			// trust data in branch user tables.
			if ($this->isEmail($v)) $emails[$i] = strtolower($v);
		}
		$emailSql = $this->quoteArr($emails);
		
		$sql =
			"SELECT
				BU.*
			FROM BUYER_BRANCH_USER BU
				INNER JOIN BUYER_BRANCH B ON BU.BBU_BYB_BRANCH_CODE = B.BYB_BRANCH_CODE
			WHERE
				(BU.BBU_USR_USER_CODE IN ($idSql) OR LOWER(BU.BBU_EMAIL_ADDRESS) IN ($emailSql))
				AND BU.BBU_STS = 'ACT'
				
				AND B.BYB_STS = 'ACT'";
				
		return $this->db->fetchAll($sql);
	}
	
	/**
	 * Fetch SUPPLIER_BRANCH_USER rows. Note, this method does not check for a
	 * corresponding USER row, or USR_TYPE.
	 *
	 * @deprecated - PAGES should no longer be referencing this table.
	 * @return array
	 */
	private function getSupplierUsers (array $ids, array $emails)
	{
		throw new Exception("Deprecated");
		
		if (!$ids && !$emails) return array();
		
		$idSql = $this->quoteArr($ids);
		
		foreach ($emails as $i => $v)
		{
			// Exclude if does not validate as e-mail because I don't
			// trust data in branch user tables.
			if ($this->isEmail($v)) $emails[$i] = strtolower($v);
		}
		$emailSql = $this->quoteArr($emails);
		
		$sql =
			"SELECT
				SU.*
			FROM SUPPLIER_BRANCH_USER SU
				INNER JOIN SUPPLIER_BRANCH S ON SU.SBU_SPB_BRANCH_CODE = S.SPB_BRANCH_CODE
			WHERE
				(SU.SBU_USR_USER_CODE IN ($idSql) OR LOWER(SU.SBU_EMAIL_ADDRESS) IN ($emailSql))
				AND SU.SBU_STS = 'ACT'
				
				AND S.SPB_STS = 'ACT'";
		
		return $this->db->fetchAll($sql);
	}
	
	/**
	 * Quote array of values for SQL.
	 *
	 * @return string
	 */
	private function quoteArr (array $vals)
	{
		$quotedArr = array();
		foreach ($vals as $v) $quotedArr[] = $this->getDb()->quote($v);
		
		if ($quotedArr) $vSql = join(', ', $quotedArr);
		else $vSql = 'NULL';
		
		
		return $vSql;
	}
	
	public function logActivity($userId, $activity, $objectName, $objectId, $isShipserv, $info)
	{
		$sql = "
			INSERT INTO 
			pages_user_activity(pua_psu_id, pua_activity, pua_object_name, pua_object_id, pua_is_shipserv, pua_date_created, pua_date_updated, pua_info)
			VALUES(:userId, :activity, :objectName, :objectId, :isShipserv, SYSTIMESTAMP, SYSTIMESTAMP, :info)
		";
		$params = array(	'userId' => $userId, 
							'activity' => $activity, 
							"objectName" => $objectName, 
							"objectId" => $objectId, 
							"isShipserv" => (($isShipserv === true)?"Y":"N"), 
							"info" => $info);
		try
		{
			//TA54585 / S17040 Move DB to Transaction DB
			//$this->db->query($sql, $params);
			$this->getDbByName('ssreport2')->query($sql, $params);
		}
		catch(Exception $e){
		}
		return true;
	}
	
	public function canSendPagesRFQDirectly($userId)
	{
		$sql = "
			SELECT USR_PAGES_ENQUIRY_STATUS FROM users WHERE USR_USER_CODE=:userId
		";
		$rows = $this->db->fetchAll( $sql, array("userId" => $userId));
		if( $rows[0]['USR_PAGES_ENQUIRY_STATUS'] == "TRUSTED" )
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}

// Thrown to indicate requested record not found
class Shipserv_Oracle_User_Exception_NotFound extends Exception { }

// Thrown to indicate failure to create user
class Shipserv_Oracle_User_Exception_FailCreateUser extends Exception
{
	const CODE_BAD_EMAIL = 1;
	const CODE_DUPLICATE = 2;
}
