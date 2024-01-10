<?php

/**
 * Review
 *
 * @author Uladzimir Maroz
 */
class Shipserv_Review {

	protected $id;

	protected $endorseeId;

	protected $endorserId;

	protected $authorUserId;

	protected $userEmail;

	protected $createdDate;

	protected $updatedDate;

	protected $overallImpression;

	protected $ratingItemsAsDescribed;

	protected $ratingDeliveredOnTime;

	protected $ratingCustomerService;

	protected $comment;

	protected $reply;

	protected $isEdited;

	protected $byMemberOfEndorser;

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

	/**
	 *
	 * Magic method to set object's fields
	 *
	 * @param string $name
	 * @param mixed $value
	 */
	public function __set ($name,$value)
	{
		$this->{$name} = $value;
	}

	public function  __construct($id, $endorseeId, $endorserId, $authorUserId, $createdDate, $updatedDate, $overallImpression, $ratingItemsAsDescribed, $ratingDeliveredOnTime, $ratingCustomerService, $comment, $reply, $userEmail = null, $byMemberOfEndorser = "Y")
	{
		$this->id = $id;
		$this->endorseeId = $endorseeId;
		$this->endorserId = $endorserId;
		$this->authorUserId = $authorUserId;
		$this->createdDate = $createdDate;
		$this->updatedDate = $updatedDate;
		$this->overallImpression = $overallImpression;
		$this->ratingItemsAsDescribed = $ratingItemsAsDescribed;
		$this->ratingDeliveredOnTime = $ratingDeliveredOnTime;
		$this->ratingCustomerService = $ratingCustomerService;
		$this->comment = $comment;
		$this->reply = $reply;
		$this->userEmail = $userEmail;
		$this->byMemberOfEndorser = $byMemberOfEndorser;
	}

	/**
	 * Fetch review by Id
	 *
	 * @param integer $id
	 * @return Shipserv_Review
	 */
	public static function fetch ($id)
	{
		if ($result = self::getDao()->fetch($id))
		{
			return self::createObjectFromDBRow($result[0]);
		}
		else
		{
			return null;
		}
	}

	/**
	 * Create review
	 *
	 * @param integer $endorseeId
	 * @param integer $endorserId
	 * @param integer $authorUserId
	 * @param string $overallImpression
	 * @param integer $ratingIAD
	 * @param integer $ratingDOT
	 * @param integer $ratingCS
	 * @param string $comment
	 * @param string $byMemberOfEndorser Is this user member of endorser organisation, 'Y' or 'N'
	 * @return Shipserv_Review
	 */
	public static function create ($endorseeId, $endorserId, $authorUserId, $overallImpression, $ratingIAD, $ratingDOT, $ratingCS, $comment,$byMemberOfEndorser)
	{
		if (self::checkCompanySettings($endorseeId,$endorserId))
		{
			$newReviewId = self::getDao()->store($endorseeId, $endorserId, $authorUserId, $overallImpression, $ratingIAD, $ratingDOT, $ratingCS, $comment,$byMemberOfEndorser);
			return Shipserv_Review::fetch($newReviewId);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Update reply by endorsee
	 * @param string $replyText
	 */
	public function updateReply($replyText)
	{
		$this->reply = $replyText;
		$this->update();
	}
	
	/**
	 * Adds category for review (category of goods supplied by endorsee)
	 *
	 * @param integer $categoryId
	 * @param string $categoryText
	 */
	public function addCategory ($categoryId,$categoryText)
	{
		self::getDao()->addReviewCategory($this->id, $categoryId, $categoryText);
	}

	/**
	 * Gets categories for review (category of goods supplied by endorsee)
	 *
	 */
	public function getCategories ()
	{
		return self::getDao()->getReviewCategories($this->id);
	}

	/**
	 * Removes categories from review
	 */
	public function removeCategories()
	{
		self::getDao()->removeReviewCategories($this->id);
	}

	/**
	 * Updates review
	 */
	public function update()
	{
		self::getDao()->update($this->id, $this->endorseeId, $this->endorserId, $this->authorUserId, $this->overallImpression, $this->ratingItemsAsDescribed, $this->ratingDeliveredOnTime, $this->ratingCustomerService, $this->comment, $this->reply, $this->byMemberOfEndorser, $this->isEdited);
	}

	/**
	 * Delete review
	 */
	public function delete()
	{
		self::getDao()->remove($this->id);
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
	 * Instantiate class using array returned from DB adaptor
	 * @param array $dbRow
	 * @return Shipserv_Review
	 */
	public static function createObjectFromDBRow ($dbRow)
	{
		return new Shipserv_Review($dbRow["PUE_ID"], $dbRow["PUE_ENDORSEE_ID"], $dbRow["PUE_ENDORSER_ID"], $dbRow["PUE_USER_ID"], $dbRow["PUE_CREATED_DATE"], $dbRow["PUE_UPDATED_DATE"], $dbRow["PUE_OVERALL_IMPRESSION"], $dbRow["PUE_RATING_IAD"], $dbRow["PUE_RATING_DOT"], $dbRow["PUE_RATING_CS"], $dbRow["PUE_COMMENT"], $dbRow["PUE_REPLY"], $dbRow["PUE_USER_EMAIL"], $dbRow["PUE_BY_MEMBER_OF_ENDORSER"]);
	}

	/**
	 * Retrives reviews submitted by User
	 *
	 * @param integer $userId
	 *
	 * @return array
	 */
	public static function getUserReviews ($userId)
	{
		$reviews = array ();
		foreach (self::getDao()->fetchReviewsForUser($userId) as $reviewRow)
		{
			$reviews[] = self::createObjectFromDBRow($reviewRow);
		}

		return $reviews;
	}

	/**
	 * Retrieve all reviews for endorsee company left by users from endorser company
	 *
	 * @param int $endorseeId
	 * @param int $endorserId
	 * @return array
	 */
	public static function getReviews($endorseeId, $endorserId)
	{
		$reviews = array ();
		foreach (self::getDao()->fetchReviews($endorseeId, $endorserId) as $reviewRow)
		{
			$reviews[] = self::createObjectFromDBRow($reviewRow);
		}

		return $reviews;

	}

	/**
	 * Retrieve all reviews from endorser company
	 *
	 * @param int $endorserId
	 * @return array
	 */
	public static function getReviewsByEndorser($endorserId)
	{
		$reviews = array ();
		foreach (self::getDao()->fetchReviewsByEndorser($endorserId) as $reviewRow)
		{
			$reviews[] = self::createObjectFromDBRow($reviewRow);
		}

		return $reviews;

	}

	/**
	 * Retrieve all reviews from endorser company
	 *
	 * @param int $endorserId
	 * @return array
	 */
	public static function getReviewsByEndorsee($endorseeId)
	{
		$reviews = array ();
		foreach (self::getDao()->fetchReviewsForSupplier($endorseeId) as $reviewRow)
		{
			$reviews[] = self::createObjectFromDBRow($reviewRow);
		}

		return $reviews;

	}

	public static function getReviewsCounts($endorseeIdArray)
	{
		return self::getDao()->fetchReviewsCounts($endorseeIdArray);
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

	/**
	 * Really wrong place for this
	 */

	public static function fetchCountryNameByCode ($countryCode)
	{
		$coutriesDAO = new Shipserv_Oracle_Countries(self::getDb());
		$country = $coutriesDAO->fetchCountryByCode($countryCode);
		if (count($country)==1)
		{
			return $country[0]["CNT_NAME"];
		}
		else
		{
			return false;
		}
	}

	/**
	 *
	 * @return Shipserv_User
	 */
	public function getAuthor()
	{

		$userDao = new Shipserv_Oracle_User(self::getDb());
		if ($this->authorUserId)
		{
			return $userDao->fetchUserById($this->authorUserId);
		}
		elseif ($this->userEmail)
		{
			return $userDao->fetchUserByEmail($this->userEmail);
		}

	}

	/**
	 * Works out whether endorser identity can be revealed
	 *
	 * @return boolean
	 */
	public function showEndorser ()
	{

		// Default: do not show
		$showThis = false;

		// if user is part of endorser or endorsee company - show endorser, no matter what preferences are set
		if ($user = Shipserv_User::isLoggedIn())
		{
			if (in_array($this->endorseeId, $user->fetchCompanies()->getSupplierIds())) $showThis = true;
			if (in_array($this->endorserId, $user->fetchCompanies()->getBuyerIds())) $showThis = true;
		}

		// otherwise - proceed with checks
		if (!$showThis)
		{
			$dbPriv = new Shipserv_Oracle_EndorsementPrivacy(self::getDb());
			$sPrivacy = $dbPriv->getSupplierPrivacy($this->endorseeId);

			// Shipserv_Oracle_EndorsementPrivacy::ANON_YES | Shipserv_Oracle_EndorsementPrivacy::ANON_NO | Shipserv_Oracle_EndorsementPrivacy::ANON_TN
			$sAnonPolicy = $sPrivacy->getGlobalAnonPolicy();

			// Supplier's anon policy is never anonymise ...
			if ($sAnonPolicy == Shipserv_Oracle_EndorsementPrivacy::ANON_NO)
			{
				// Check buyer's anon policy
				$bPrivacy = $dbPriv->getBuyerPrivacy($this->endorserId);
				$bPolicy = $bPrivacy->getGlobalAnonPolicy();

				// Buyer's policy is do not anonymise ...
				if ($bPolicy == Shipserv_Oracle_EndorsementPrivacy::ANON_NO)
				{
					// Allow
					$showThis = true;

					// But, check exceptions ...
					$bExRules = $bPrivacy->getExceptionRules();

					// If there is an exception rule for this supplier ...
					if ($bExRules[$this->endorseeId] == Shipserv_Oracle_EndorsementPrivacy::ANON_YES)
					{
						$showThis = false;
					}
				}

				// Buyer's policy is to anonymise, except for TN buyers ...
				elseif ($bPolicy == Shipserv_Oracle_EndorsementPrivacy::ANON_TN)
				{
					if (is_object($user) and (bool)$user->fetchCompanies()->getBuyerIds())
					{
						$showThis = true;
					}
				}
			}

		}

		return $showThis;
	}

	public static function fetchSummary ($endorseeId)
	{

		$reviews = array(
			"reviews" => array(),
			"sumIAD" => 0,
			"sumDOT" => 0,
			"sumCS"	=> 0,
			"countIAD" => 0,
			"countDOT" => 0,
			"countCS"	=> 0,
			"countPositive"	=> 0,
			"countNeutral"	=> 0,
			"countNegative"	=> 0
		);

		foreach (self::getReviewsByEndorsee($endorseeId) as $review)
		{

			if (!isset($reviews["reviews"][$review->endorserId]))
			{
				$reviews["reviews"][$review->endorserId] = array (
					"reviews" => array(),
					"sumIAD" => 0,
					"sumDOT" => 0,
					"sumCS"	=> 0,
					"countIAD" => 0,
					"countDOT" => 0,
					"countCS"	=> 0,
					"countPositive"	=> 0,
					"countNeutral"	=> 0,
					"countNegative"	=> 0
				);
			}
			$reviews["reviews"][$review->endorserId]["reviews"][] = $review;

			switch($review->overallImpression){
				case -1:
					$reviews["reviews"][$review->endorserId]["countNegative"]++;
					$reviews["countNegative"]++;
					break;
				case 0:
					$reviews["reviews"][$review->endorserId]["countNeutral"]++;
					$reviews["countNeutral"]++;
					break;
				case 1:
					$reviews["reviews"][$review->endorserId]["countPositive"]++;
					$reviews["countPositive"]++;
					break;
			}
			if ($review->ratingItemsAsDescribed>0)
			{
				$reviews["reviews"][$review->endorserId]["countIAD"]++;
				$reviews["countIAD"]++;
				$reviews["reviews"][$review->endorserId]["sumIAD"] += $review->ratingItemsAsDescribed;
				$reviews["sumIAD"] += $review->ratingItemsAsDescribed;
			}
			if ($review->ratingDeliveredOnTime>0)
			{
				$reviews["reviews"][$review->endorserId]["countDOT"]++;
				$reviews["countDOT"]++;
				$reviews["reviews"][$review->endorserId]["sumDOT"] += $review->ratingDeliveredOnTime;
				$reviews["sumDOT"] += $review->ratingDeliveredOnTime;
			}
			if ($review->ratingCustomerService>0)
			{
				$reviews["reviews"][$review->endorserId]["countCS"]++;
				$reviews["countCS"]++;
				$reviews["reviews"][$review->endorserId]["sumCS"] += $review->ratingCustomerService;
				$reviews["sumCS"] += $review->ratingCustomerService;
			}
		}

		return $reviews;
	}

	/**
	 * Checks if logged-in user is a TN buyer: i.e. is an active member of at
	 * least 1 buyer organisation.
	 *
	 * @return bool
	 */
	private function isLoggedUserTnBuyer ()
	{
		$u = Shipserv_User::isLoggedIn();

		// If not logged-in, return no
		if (!$u) return false;

		return (bool)$user->fetchCompanies()->getBuyerIds();
	}
	
	/**
	 * Updates membership status in endorser organisation for given author
	 * @param integer $userId
	 * @param integer $endorserId
	 * @param boolean $isMember
	 */
	public static function updateUserMembership ($userId, $endorserId, $isMember)
	{
		//if user is joining company - we need to send all notifications about reviews
		if ($isMember)
		{
			//if user is part - it means review was published, we can send notification
			$notificationManager = new Myshipserv_NotificationManager(self::getDb());
			foreach (self::getUserReviews($userId) as $review)
			{
				$notificationManager->reviewAdded($review);
			}
		}

		return self::getDao()->updateUserMembership($userId, $endorserId, $isMember);
	}

	public static function checkCompanySettings($endorseeId,$endorserId)
	{
		$profileDao = new Shipserv_Oracle_Profile(self::getDb());
		$endorsee = $profileDao->getSuppliersByIds(array($endorseeId));
		$endorseeInfo =  $endorsee[0];
		if ($endorseeInfo["PCO_DISABLE_REV_SUBMISSION"]!='Y'){
			return true;
		}
		else
		{
			return false;
		}
	}
}
?>
