<?php

/**
 * Class for reading and writing endorsement data from Oracle
 *
 * @package Shipserv
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class Shipserv_Oracle_UserEndorsement extends Shipserv_Oracle
{
	public function __construct (&$db)
	{
		parent::__construct($db);
	}

	/**
	 * Store User's Review
	 *
	 *
	 *
	 * @return integer Id of newly inserted record
	 */
	public function store ($endorseeId,$endorserId,$userId,$overallImpression,$ratingIAD,$ratingDOT,$ratingCS,$comment,$byMemberOfEndorser)
	{
		$sql = "INSERT INTO PAGES_USER_ENDORSEMENT";
		$sql.= " (PUE_ID, PUE_ENDORSEE_ID, PUE_ENDORSER_ID, PUE_USER_ID, PUE_CREATED_DATE,PUE_OVERALL_IMPRESSION,PUE_RATING_IAD,PUE_RATING_DOT,PUE_RATING_CS,PUE_COMMENT,PUE_BY_MEMBER_OF_ENDORSER)";
		$sql.= " VALUES (";
		$sql.= " PAGES_USER_ENDORSEMENT_seq.nextval,";
		$sql.= " :endorseeId,";
		$sql.= " :endorserId,";
		$sql.= " :userId,";
		$sql.= " SYSDATE,";
		$sql.= " :overallImpression,";
		$sql.= " :ratingIAD,";
		$sql.= " :ratingDOT,";
		$sql.= " :ratingCS,";
		$sql.= " :reviewComment,";
		$sql.= " :byMemberOfEndorser";
		$sql.= " )";

		$sqlData = array(
			'endorseeId'		=> $endorseeId,
			'endorserId'		=> $endorserId,
			'userId'			=> $userId,
			'overallImpression'	=> $overallImpression,
			'ratingIAD'			=> $ratingIAD,
			'ratingDOT'			=> $ratingDOT,
			'ratingCS'			=> $ratingCS,
			'reviewComment'			=> $comment,
			'byMemberOfEndorser'	=> $byMemberOfEndorser
		);
		$this->db->query($sql,$sqlData);

		return $this->db->lastSequenceId('PAGES_USER_ENDORSEMENT_SEQ');

	}

	/**
	 *	Fetch User Endorsement by Id
	 *
	 * @access public
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetch ($userEndorsementId, $useCache = false, $cacheTTL = 86400)
	{
		$sql = 'SELECT *';
		$sql.= '  FROM PAGES_USER_ENDORSEMENT';
		$sql.= ' WHERE PUE_ID = :userEndorsementId';

		$sqlData = array('userEndorsementId' => $userEndorsementId);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'USERENDORSEMENT'.$userEndorsementId.
			       $this->memcacheConfig->client->keySuffix;
			

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{

			$result = $this->db->fetchAll($sql,$sqlData);
		}

		return $result;
	}

	public function update ($id, $endorseeId, $endorserId, $authorUserId, $overallImpression, $ratingItemsAsDescribed, $ratingDeliveredOnTime, $ratingCustomerService, $comment, $reply, $byMemberOfEndorser, $isEdited = false)
	{
		$sql = "UPDATE PAGES_USER_ENDORSEMENT";
		$sql.= " SET ";
		$sql.= " PUE_ENDORSEE_ID = :endorseeId, ";
		$sql.= " PUE_ENDORSER_ID = :endorserId, ";
		$sql.= " PUE_USER_ID = :userId, ";
		$sql.= " PUE_OVERALL_IMPRESSION = :overallImpression, ";
		$sql.= " PUE_RATING_IAD = :ratingIAD, ";
		$sql.= " PUE_RATING_DOT = :ratingDOT, ";
		$sql.= " PUE_RATING_CS = :ratingCS, ";
		$sql.= " PUE_COMMENT = :reviewComment, ";
		$sql.= " PUE_REPLY = :reviewReply, ";
		if ($isEdited === true)
		{
			$sql.= " PUE_UPDATED_DATE = SYSDATE, ";
		}

		$sql.= " PUE_BY_MEMBER_OF_ENDORSER = :byMemberOfEndorser ";
		$sql.= " WHERE";
		$sql.= " PUE_ID = :reviewId";

		$sqlData = array(
			'reviewId'		=> $id,
			'endorseeId'		=> $endorseeId,
			'endorserId'		=> $endorserId,
			'userId'			=> $authorUserId,
			'overallImpression'	=> $overallImpression,
			'ratingIAD'			=> $ratingItemsAsDescribed,
			'ratingDOT'			=> $ratingDeliveredOnTime,
			'ratingCS'			=> $ratingCustomerService,
			'reviewComment'		=> $comment,
			'reviewReply'		=> $reply,
			'byMemberOfEndorser'=> $byMemberOfEndorser
		);
		$this->db->query($sql,$sqlData);

		return true;
	}

	/**
	 *	Fetch review request by secret code
	 *
	 * @access public
	 * @param string $requestCode Secret code for endorsement request
	 * @param boolean $cache Fetch from the cache if possible, and cache the
	 * 						 result if cache is invalid
	 * @param int $cacheTTL If using the cache, what TTL should be used?
	 * @return array
	 */
	public function fetchRequest ($requestCode, $useCache = false, $cacheTTL = 86400)
	{
		$sql = 'SELECT *';
		$sql.= ' FROM PAGES_USER_ENDORSEMENT';
		$sql.= ' WHERE PUE_REQUEST_CODE = :requestCode';

		$sqlData = array('requestCode' => $requestCode);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'USERENDORSEMENTREQUEST'.$requestCode.
			       $this->memcacheConfig->client->keySuffix;


			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{

			$result = $this->db->fetchAll($sql,$sqlData);
		}

		return $result;
	}

	/**
	 *	Removes review and review categories from DB
	 *
	 *
	 * @param integer $id
	 * @return boolean
	 */
	public function remove ($id)
	{
		$sql = 'DELETE';
		$sql.= ' FROM PAGES_USER_ENDORSEMENT';
		$sql.= ' WHERE PUE_CREATED_DATE IS NOT NULL AND PUE_ID = :reviewId';

		$sqlData = array('reviewId' => $id);


		$result = $this->db->query($sql,$sqlData);


		$sql = 'DELETE';
		$sql.= ' FROM PAGES_ENDORSEMENT_CATEGORIES';
		$sql.= ' WHERE PEC_USER_END_ID = :reviewId';

		$sqlData = array('reviewId' => $id);


		$result = $this->db->query($sql,$sqlData);


		return true;
	}

	/**
	 *	Removes review request from DB
	 *
	 *
	 * @param integer $id
	 * @return boolean
	 */
	public function removeRequest ($id)
	{
		$sql = 'DELETE';
		$sql.= ' FROM PAGES_USER_ENDORSEMENT';
		$sql.= ' WHERE PUE_CREATED_DATE IS NULL AND PUE_ID = :requestId';

		$sqlData = array('requestId' => $id);


		$result = $this->db->query($sql,$sqlData);

		return true;
	}

	/**
	 *	Fetch review requests for given email
	 *
	 * @access public
	 * @param string $userEmail
	 * @return array
	 */
	public function fetchRequestsForEmail ($userEmail)
	{
		$sql = 'SELECT *';
		$sql.= ' FROM PAGES_USER_ENDORSEMENT';
		$sql.= ' WHERE PUE_CREATED_DATE IS NULL AND PUE_USER_EMAIL = :userEmail ';
		$sql.= ' AND PUE_ENDORSEE_ID NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM) ORDER BY PUE_ID DESC';

		$sqlData = array('userEmail' => $userEmail);


		$result = $this->db->fetchAll($sql,$sqlData);


		return $result;
	}

	/**
	 *	Fetch review requests for array of emails
	 *
	 * @access public
	 * @param array $emails
	 * @return array
	 */
	public function fetchRequestsForEmails ($emails)
	{
		$sql = 'SELECT *';
		$sql.= ' FROM PAGES_USER_ENDORSEMENT';
		$sql.= ' WHERE PUE_CREATED_DATE IS NULL AND PUE_USER_EMAIL IN ('. $this->arrToSqlList($emails) .') AND PUE_ENDORSEE_ID NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM)';
		$sql.= ' ORDER BY PUE_REQUESTED_DATE ';

		$sqlData = array();


		$result = $this->db->fetchAll($sql,$sqlData);

		return $result;
	}

	public function removeExpiredRequests ()
	{
		$sql = 'DELETE FROM PAGES_USER_ENDORSEMENT WHERE PUE_REQUESTED_DATE < add_months(SYSDATE, -6) AND PUE_CREATED_DATE IS NULL';

		$sqlData = array();

		$result = $this->db->query($sql,$sqlData);

		return true;
	}


	/**
	 *	Fetch reviews for given user
	 *
	 * @access public
	 * @param string $userEmail
	 * @return array
	 */
	public function fetchReviewsForUser ($userId)
	{
		$sql = 'SELECT *';
		$sql.= ' FROM PAGES_USER_ENDORSEMENT';
		$sql.= ' WHERE PUE_CREATED_DATE IS NOT NULL AND PUE_ENDORSEE_ID NOT IN (SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM) AND PUE_USER_ID = :userId';

		$sqlData = array('userId' => $userId);


		$result = $this->db->fetchAll($sql,$sqlData);


		return $result;
	}

	/**
	 *	Create user endorsement based on previous request
	 *
	 * @param <type> $endorseeId
	 * @param <type> $endorserId
	 * @param <type> $userId
	 * @param <type> $overallImpression
	 * @param <type> $ratingIAD
	 * @param <type> $ratingDOT
	 * @param <type> $ratingCS
	 * @param <type> $comment
	 * @return <type>
	 */
	public function storeForRequest ($requestId,$userId,$overallImpression,$ratingIAD,$ratingDOT,$ratingCS,$comment,$byMemberOfEndorser)
	{
		$sql = "UPDATE PAGES_USER_ENDORSEMENT SET";
		$sql.= " PUE_CREATED_DATE = SYSDATE,";
		$sql.= " PUE_OVERALL_IMPRESSION = :overallImpression,";
		$sql.= " PUE_RATING_IAD = :ratingIAD,";
		$sql.= " PUE_RATING_DOT = :ratingDOT,";
		$sql.= " PUE_RATING_CS = :ratingCS,";
		$sql.= " PUE_COMMENT = :reviewComment,";
		$sql.= " PUE_USER_ID = :userId,";
		$sql.= " PUE_BY_MEMBER_OF_ENDORSER = :byMemberOfEndorser,";
		$sql.= " PUE_REQUEST_CODE = NULL";
		$sql.= " WHERE PUE_ID=:requestId";

		$sqlData = array(
			'requestId'			=> $requestId,
			'overallImpression'	=> $overallImpression,
			'ratingIAD'			=> $ratingIAD,
			'ratingDOT'			=> $ratingDOT,
			'ratingCS'			=> $ratingCS,
			'reviewComment'			=> $comment,
			'userId'			=> $userId,
			'byMemberOfEndorser'=>$byMemberOfEndorser
		);
		$this->db->query($sql,$sqlData);

		return true;

	}

	/**
	 * Create request for review
	 *
	 * @param integer $requestorUserId
	 * @param integer $endorseeId
	 * @param integer $endorserId
	 * @param integer $userId
	 * @param string $requestText
	 * @return string Secret code for new review request
	 */
	public function createRequest ($requestorUserId, $endorseeId,$endorserId,$userEmail,$requestText,$secretCode,$requestAlg)
	{
		$sql = "INSERT INTO PAGES_USER_ENDORSEMENT";
		$sql.= " (PUE_ID, PUE_REQUEST_USER_ID, PUE_ENDORSEE_ID, PUE_ENDORSER_ID, PUE_USER_EMAIL, PUE_REQUESTED_DATE,PUE_REQUEST_CODE,PUE_REQUEST_TEXT,PUE_ALG_USED)";
		$sql.= " VALUES (";
		$sql.= " PAGES_USER_ENDORSEMENT_seq.nextval,";
		$sql.= " :requestorUserId,";
		$sql.= " :endorseeId,";
		$sql.= " :endorserId,";
		$sql.= " :userEmail,";
		$sql.= " SYSDATE,";
		$sql.= " :requestCode,";
		$sql.= " :requestText,";
		$sql.= " :requestAlg";
		$sql.= " )";

		$sqlData = array(
			'requestorUserId'	=> $requestorUserId,
			'endorseeId'		=> $endorseeId,
			'endorserId'		=> $endorserId,
			'userEmail'			=> $userEmail,
			'requestCode'		=> $secretCode,
			'requestText'		=> $requestText,
			'requestAlg'		=> $requestAlg
		);
		$this->db->query($sql,$sqlData);

		return $secretCode;

	}

	public function addReviewCategory ($userEndorsementId,$categoryId,$categoryText)
	{
		$sql = "INSERT INTO PAGES_ENDORSEMENT_CATEGORIES";
		$sql.= " (PEC_USER_END_ID, PEC_CATEGORY_ID, PEC_CATEGORY_TEXT)";
		$sql.= " VALUES (";
		$sql.= " :userEndorsementId,";
		$sql.= " :categoryId,";
		$sql.= " :categoryText";
		$sql.= " )";

		$sqlData = array(
			'userEndorsementId'		=> $userEndorsementId,
			'categoryId'			=> $categoryId,
			'categoryText'			=> $categoryText
		);
		$this->db->query($sql,$sqlData);

		return true;
	}

	public function getReviewCategories ($userEndorsementId)
	{
		$sql = "SELECT * FROM PAGES_ENDORSEMENT_CATEGORIES";
		$sql.= " WHERE PEC_USER_END_ID =:userEndorsementId";


		$sqlData = array(
			'userEndorsementId'		=> $userEndorsementId
		);
		$result = $this->db->fetchAll($sql, $sqlData);

		return $result;
	}

	public function removeReviewCategories ($userEndorsementId)
	{
		$sql = 'DELETE';
		$sql.= ' FROM PAGES_ENDORSEMENT_CATEGORIES';
		$sql.= ' WHERE PEC_USER_END_ID = :userEndorsementId';

		$sqlData = array(
			'userEndorsementId'		=> $userEndorsementId
		);

		$result = $this->db->query($sql,$sqlData);

		return true;
	}

	public function fetchReviews ($endorseeId, $endorserId, $useCache = false, $cacheTTL = 120)
	{
		$sql = 'SELECT * FROM pages_user_endorsement pue';
		$sql.= ' INNER JOIN pages_user_company puc ';
		$sql.= ' ON ( ';
		$sql.= ' pue.pue_user_id = puc.puc_psu_id ';
		$sql.= " and puc.puc_company_type = 'BYO' ";
		$sql.= ' and pue.pue_endorser_id = puc.puc_company_id ';
		$sql.= " and puc.puc_status = 'ACT' ";
		$sql.= ' ) ';
		$sql.= ' LEFT JOIN pages_byo_norm pbn ON (pue.pue_endorser_id = pbn_byo_org_code) ';
		$sql.= ' WHERE pue_endorsee_id=:endorseeId AND pue_endorser_id=:endorserId AND pue_created_date IS NOT NULL ';
		$sql.= ' AND NOT(NVL(pbn.pbn_byo_org_code,0)!=0 AND NVL(pbn.pbn_norm_byo_org_code,0)=0) ';
		$sql.= ' ORDER BY pue_endorser_id, pue_created_date DESC';

		$sqlData = array(
			'endorseeId' => $endorseeId,
			'endorserId' => $endorserId
		);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'REVIEWSFOR_'.$endorseeId.'_'.$endorserId.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}

	public function fetchReviewsForSupplier ($endorseeId, $useCache = false, $cacheTTL = 120)
	{
		$sql = 'SELECT pue.* FROM pages_user_endorsement pue';
		$sql.= ' INNER JOIN pages_user_company puc ';
		$sql.= ' ON ( ';
		$sql.= ' pue.pue_user_id = puc.puc_psu_id ';
		$sql.= " and puc.puc_company_type = 'BYO' ";
		$sql.= ' and pue.pue_endorser_id = puc.puc_company_id ';
		$sql.= " and puc.puc_status = 'ACT' ";
		$sql.= ' ) ';
		$sql.= ' LEFT JOIN pages_byo_norm pbn ON (pue.pue_endorser_id = pbn_byo_org_code) ';
		$sql.= ' WHERE pue_endorsee_id=:endorseeId AND pue_created_date IS NOT NULL ';
		$sql.= ' AND NOT(NVL(pbn.pbn_byo_org_code,0)!=0 AND NVL(pbn.pbn_norm_byo_org_code,0)=0) ';
		$sql.= ' ORDER BY pue_endorser_id, pue_created_date DESC';

		$sqlData = array('endorseeId' => $endorseeId);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'REVIEWSFOR_'.$endorseeId.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}

	public function fetchReviewsCounts($endorseeIdsArr)
	{
		$sql = 'SELECT pue.pue_endorsee_id, count(*) as reviewsCount FROM pages_user_endorsement pue';
		$sql.= ' INNER JOIN pages_user_company puc ';
		$sql.= ' ON ( ';
		$sql.= ' pue.pue_user_id = puc.puc_psu_id ';
		$sql.= " and puc.puc_company_type = 'BYO' ";
		$sql.= ' and pue.pue_endorser_id = puc.puc_company_id ';
		$sql.= " and puc.puc_status = 'ACT' ";
		$sql.= ' ) ';
		$sql.= ' LEFT JOIN pages_byo_norm pbn ON (pue.pue_endorser_id = pbn_byo_org_code) ';
		$sql.= ' WHERE pue_endorsee_id IN ('. $this->arrToSqlList($endorseeIdsArr)  .') AND pue_created_date IS NOT NULL ';
		$sql.= ' AND NOT(NVL(pbn.pbn_byo_org_code,0)!=0 AND NVL(pbn.pbn_norm_byo_org_code,0)=0) ';
		$sql.= ' GROUP BY pue_endorsee_id';

		$result = $this->db->fetchAll($sql, array());

		$returnArr = array();
		foreach ($result as $resultRow) $returnArr[$resultRow["PUE_ENDORSEE_ID"]] = $resultRow["REVIEWSCOUNT"];

		return $returnArr;
	}

	public function fetchReviewsByEndorser ($endorserId, $useCache = false, $cacheTTL = 120)
	{
		$sql = 'SELECT count(*) FROM pages_user_endorsement pue';
		$sql.= ' INNER JOIN pages_user_company puc ';
		$sql.= ' ON ( ';
		$sql.= ' pue.pue_user_id = puc.puc_psu_id ';
		$sql.= " and puc.puc_company_type = 'BYO' ";
		$sql.= ' and pue.pue_endorser_id = puc.puc_company_id ';
		$sql.= " and puc.puc_status = 'ACT' ";
		$sql.= ' ) ';
		$sql.= ' WHERE pue_endorser_id=:endorserId AND pue_created_date IS NOT NULL ';
		$sql.= ' ORDER BY pue_endorser_id, pue_created_date DESC';

		$sqlData = array(
			'endorserId' => $endorserId
		);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'REVIEWSBY_'.$endorserId.
			       $this->memcacheConfig->client->keySuffix;

			$result = $this->fetchCachedQuery($sql, $sqlData, $key, $cacheTTL);
		}
		else
		{
			$result = $this->db->fetchAll($sql, $sqlData);
		}

		return $result;
	}
	/**
	 * Updates membership status in endorser organisation for given author
	 * @param integer $userId
	 * @param integer $endorserId
	 * @param boolean $isMember
	 */
	public function updateUserMembership ($userId, $endorserId, $isMember)
	{
		$sql = "UPDATE PAGES_USER_ENDORSEMENT SET";
		$sql.= " PUE_BY_MEMBER_OF_ENDORSER = :byMemberOfEndorser";
		$sql.= " WHERE PUE_USER_ID=:userId AND PUE_ENDORSER_ID=:endorserId";

		$sqlData = array(
			'userId'			=> $userId,
			'endorserId'		=> $endorserId,
			'byMemberOfEndorser'=> (($isMember)?'Y':'N')
		);

		$this->db->query($sql,$sqlData);

		return true;
	}

	/**
	 * Transforms values of input array into a quoted list suitable for
	 * a SQL in clause: e.g. 3, 'str val', ...
	 *
	 * @return string
	 */
	private function arrToSqlList ($arr)
	{
		$sqlArr = array();
		foreach ($arr as $item)
		{
			$sqlArr[] = $this->db->quote($item);
		}
		if (!$sqlArr) $sqlArr[] = 'NULL';
		return join(', ', $sqlArr);
	}
}