<?php

/**
 * Review Request
 *
 * @author Uladzimir Maroz
 */
class Shipserv_ReviewRequest {

	protected $id;

	protected $endorseeId;

	protected $endorserId;

	protected $requestorUserId;

	protected $requestedDate;

	protected $userEmail;

	protected $code;

	protected $text;

	protected static $daoAdapter;

	/**
	 *
	 * Magic method to access object's fields
	 *
	 * @param string $name
	 * @return mixed
	 */
	public function __get ($name)
	{
		return $this->{$name};
	}

	public function  __construct($id, $endorseeId, $endorserId, $requestorUserId, $requestedDate, $userEmail, $code, $text)
	{
		$this->id = $id;
		$this->endorseeId = $endorseeId;
		$this->endorserId = $endorserId;
		$this->requestorUserId = $requestorUserId;
		$this->requestedDate = $requestedDate;
		$this->userEmail = $userEmail;
		$this->code = $code;
		$this->text = $text;
	}

	/**
	 * Retrieves Shipserv_ReviewRequest by secret code
	 *
	 * @param string $code
	 * @return Shipserv_ReviewRequest
	 */
	public static function fetchByCode ($code)
	{
		if ($result = self::getDao()->fetchRequest($code))
		{
			return self::createObjectFromDBRow($result[0]);
		}
		else
		{
			return null;
		}
	}
	/**
	 * Creates ReviewRequest
	 *
	 * @param integer $requestorUserId
	 * @param integer $endorseeId
	 * @param integer $endorserId
	 * @param string $userEmail
	 * @param string $requestText
	 * @return Shipserv_ReviewRequest
	 */
	public static function create ($requestorUserId, $endorseeId,$endorserId,$userEmail,$requestText,$requestAlg = null) {

		if (Shipserv_Review::checkCompanySettings($endorseeId,$endorserId))
		{
			$secretCode = self::generateSecretCode();

			self::getDao()->createRequest($requestorUserId, $endorseeId,$endorserId,$userEmail,$requestText,$secretCode, $requestAlg);

			return self::fetchByCode($secretCode);
		}
		else
		{
			return false;
		}
		
	}

	public function delete()
	{
		self::getDao()->removeRequest($this->id);
	}

	/**
	 * Create review for this review request
	 *
	 * @param <type> $userId
	 * @param <type> $overallImpression
	 * @param <type> $ratingIAD
	 * @param <type> $ratingDOT
	 * @param <type> $ratingCS
	 * @param <type> $comment
	 * @return Shipserv_Review
	 */
	public function createReview($userId,$overallImpression,$ratingIAD,$ratingDOT,$ratingCS,$comment,$byMemberOfEndorser)
	{

		self::getDao()->storeForRequest($this->id, $userId, $overallImpression, $ratingIAD, $ratingDOT, $ratingCS, $comment,$byMemberOfEndorser);

		return Shipserv_Review::fetch($this->id);
	}

	private static function getDb ()
	{
		return $GLOBALS["application"]->getBootstrap()->getResource('db');
	}

	/**
	 *
	 * @return Shipserv_Oracle_UserEndorsement
	 */
	private static function getDao()
	{
		if (!self::$daoAdapter)
		{
			self::$daoAdapter = new Shipserv_Oracle_UserEndorsement(self::getDb());
		}

		return self::$daoAdapter;
	}

	/**
	 * Instantiate class using array returned from DB adapter
	 * @param array $dbRow
	 * @return Shipserv_ReviewRequest
	 */
	public static function createObjectFromDBRow ($dbRow)
	{
		return new Shipserv_ReviewRequest($dbRow["PUE_ID"], $dbRow["PUE_ENDORSEE_ID"], $dbRow["PUE_ENDORSER_ID"], $dbRow["PUE_REQUEST_USER_ID"], $dbRow["PUE_REQUESTED_DATE"], $dbRow["PUE_USER_EMAIL"], $dbRow["PUE_REQUEST_CODE"], $dbRow["PUE_REQUEST_TEXT"]);
	}

	/**
	 *
	 * @param string $email
	 *
	 * @return array
	 */
	public static function getRequestsForEmail ($email)
	{
		$requests = array ();
		foreach (self::getDao()->fetchRequestsForEmail($email) as $requestRow)
		{
			$requests[] = self::createObjectFromDBRow($requestRow);
		}

		return $requests;
	}
	/**
	 * Should be rewritten to retun proper company object later
	 *
	 * @return array
	 */
	public function getEndorseeInfo()
	{

		$profileDao = new Shipserv_Oracle_Profile(self::getDb());
		$endorsee = $profileDao->getSuppliersByIds(array($this->endorseeId));
		return $endorsee[0];
	}

	/**
	 * Should be rewritten to retun proper company object later
	 *
	 * @return array
	 */
	public function getEndorserInfo()
	{

		$profileDao = new Shipserv_Oracle_Profile(self::getDb());
		$endorser = $profileDao->getBuyersByIds(array($this->endorserId));
		return $endorser[0];
	}

	public function getRequestorUserInfo()
	{
		$userDao = new Shipserv_Oracle_User(self::getDb());
		$requestorUsersArray = $userDao->fetchUsers(array($this->requestorUserId))->makeShipservUsers();
		if (count($requestorUsersArray)==1)
		{
			return $requestorUsersArray[0];
		}

	}

	public static function generateSecretCode()
	{
		return md5("asdlvhjkqp8e3wrhfjnv24dssdf".mt_rand());
	}

	/**
	 * Returns array of request objects for array of emails
	 *
	 * @param array $emails
	 * @return array
	 */
	public static function getRequestsByEmails ($emails)
	{

		// Sanitize array
		$cleanEmails = array ();
		foreach ($emails as $email)
		{
			if (!in_array(strtolower($email),$cleanEmails))
			{
				$cleanEmails[] = $email;
			}
		}

		$requests = array_fill_keys($cleanEmails, array());
		foreach (self::getDao()->fetchRequestsForEmails($cleanEmails) as $requestRow)
		{
			$request = self::createObjectFromDBRow($requestRow);
			$requests[$request->userEmail][] = $request;
		}
		return $requests;
	}

	public static function removeExpiredRequests(){

		self::getDao()->removeExpiredRequests();
	}
}
?>
