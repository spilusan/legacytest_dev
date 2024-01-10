<?php

class Myshipserv_ReviewRequest_Generator {

	// Access to user data
	private $userDao;

	private $companyData = array ();

	private $emailsWithRecentRequests = array();

	private $reviewsData;

	private $newSuppliersData;

	private $mostViewedSuppliers;
	
	private $normSuppliers;

	private $requests = array();

	public $prefSupplierCategories = array(1,2,3,4,5,6,7,8,9);

	public $debug = false;

	public function  __construct()
	{
		$this->userDao = new Myshipserv_UserCompany_Domain(self::getDb());
	}

	public function generate()
	{

		if ($this->debug) echo 'Debug mode is enabled \n';

		//remove expired requests
		Shipserv_ReviewRequest::removeExpiredRequests();

		//get number of distinct buyer users
		$usersCount = self::getBuyerUsersCount();

		//get number of emails to be sent, on sixth of all emails;
		if (!$this->debug)
		{
			$emailsToBeSent = floor($usersCount/6);
		}
		else
		{
			$emailsToBeSent = 10;
		}
		echo '\nEmails to be sent:'.$emailsToBeSent.' \n';

		$notificationManager = new Myshipserv_NotificationManager(self::getDb());

		//proceed only if today is Tuesday, Wednesday or Thursday

		if (in_array(date('w'),array(2,3,4)) or $this->debug)
		{
			//loop through buer companies that not opted out from reviews
			foreach (self::getAllBuyersIds() as $company)
			{
				//loop through each user
				foreach ($this->userDao->fetchUsersForCompany('BYO', $company["PUC_COMPANY_ID"])->getActiveUsers() as $endorserUser)
				{

					echo '\nUser: '. $endorserUser->email .', company: '. $company["PUC_COMPANY_ID"] . '\n';

					$supplierToRequest =  $this->getSupplierForRequest($company["PUC_COMPANY_ID"], $endorserUser);


					if ($supplierToRequest!==false)
					{
						if ($emailsToBeSent > 0)
						{
							echo ' Supplier to recommend: ' .$supplierToRequest[0].'\n';

							$reviewRequest = Shipserv_ReviewRequest::create(null, $supplierToRequest[0] ,$company["PUC_COMPANY_ID"], $endorserUser->email, "",$supplierToRequest[1]);

							if ($endorserUser->alertStatus == Shipserv_User::ALERTS_IMMEDIATELY)
							{
								$notificationManager->requestReview(array("name"=>($endorserUser->firstName.' '.$endorserUser->lastName),"email"=>$endorserUser->email), $reviewRequest);
								echo "Email has been sent \n";
							}
							else
							{
								echo "User has selected not to receive immediate notifications\n";
							}

							$this->requests[] = $reviewRequest;

							$emailsToBeSent--;
						}
						else
						{
							echo "Limit of emails to be sent out has been reached. No email is sent to this user\n";
							exit();
						}
					}
					else
					{
						echo "User is skipped\n";
					}


				}
			}
		}

	}

	private function getSupplierForRequest ($companyId, $user)
	{
		//skip those users that were solicited in last two weeks
		if (!$this->isEmailRecentlySolicited($user->email))
		{
			/**
			 *  The user’s buyer company placed their first order with the supplier more than one month ago, and that supplier has not been
			 *  reviewed by that buyer user and a review has not been solicited from that buyer user. If more than one possible supplier is
			 *  identified, the supplier where the order was furthest in the past will be selected.
			 */
			echo "Trying to find new supplier...\n";
			if ($selectedSupplier = $this->findSupplierForRequest($this->getNewSuppliers($companyId), $user)) return array ($selectedSupplier,"new");
			echo "No new suppliers found.\n";

			/*
			 * If no supplier is returned by the above, we will look for suppliers that are in the top 500 by page impression count per day
			 * over the past 6 months which have traded with the user’s buyer company but which have not been reviewed and which have never
			 * been solicited for a review by that supplier and which have less than 3 reviews in total. If more than one possible supplier
			 * is found, we will rank by position within the 500.
			 */
			echo "Trying to find most viewed supplier...\n";
			if ($selectedSupplier = $this->findSupplierForRequest($this->getMostViewedSuppliers($companyId), $user)) return array ($selectedSupplier,"most_viewed");
			echo "No appropriate most viewed suppliers found.\n";

			/**
			 * If no supplier is returned by the above, we will look for suppliers that are members of priority categories which have traded
			 * with the user’s buyer company but which have not been reviewed and which have never been solicited for a review by that
			 * supplier. Current priority categories will be identified by a flag in the database. If more than one possible supplier is
			 * found, we will rank them by frequency of trade with the buyer (by transaction count within past 6 months).
			 */

			echo "Trying to find most supplier from predefined categories (". implode(",", $this->prefSupplierCategories) .")...\n";
			if ($selectedSupplier = $this->findSupplierForRequest($this->getTargetSuppliers($companyId), $user)) return array ($selectedSupplier,"priority_category");
			echo "No appropriate suppliers found.\n";

			/*
			 * If no supplier is returned by the above, we will select the most frequently used supplier (by transaction count within past
			 * 12 months)  by that buyer company, where that buyer user has not reviewed that supplier and has been never been solicited for
			 * a review by that supplier.
			 */
			echo "Trying to find supplier from trade history...\n";
			if ($selectedSupplier = $this->findSupplierForRequest($this->getRecentSuppliers($companyId), $user)) return array ($selectedSupplier,"most_frequent");
			echo "No supplier found that has traded in last year with company.\n";

			echo "No supplier found for this user\n";
		}
		else
		{
			echo "This supplier has recent review or review request\n";
		}
		return false;
	}

	private static function getDb ()
	{
		return $GLOBALS["application"]->getBootstrap()->getResource('db');
	}

	private static function getStandByDb ()
	{

		$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
		return $resource->getDb('standbydb');
	}


	/**
	 * Retrieve IDs of all buyer companies that have not disabled reviews
	 */
	private static function getAllBuyersIds()
	{
		$sql = "SELECT DISTINCT PUC_COMPANY_ID ";
		$sql .= " FROM PAGES_USER_COMPANY ";
		$sql .= " LEFT JOIN PAGES_COMPANY ON (PUC_COMPANY_ID = PCO_ID) ";
		$sql .= " WHERE PUC_COMPANY_TYPE='BYO' AND PUC_STATUS='ACT' AND NVL(PCO_REVIEWS_OPTOUT,'N')!='Y'";

		$sqlData = array();

		$result = self::getStandByDb()->fetchAll($sql, $sqlData);

		return $result;
	}

	/**
	 * Retrieve IDs of all buyer companies that have not disabled reviews
	 */
	private static function getBuyerUsersCount()
	{
		$sql = "select count (distinct puc_psu_id) as USERSCOUNT from pages_user_company where puc_company_type='BYO' and puc_status='ACT'";

		$sqlData = array();

		$result = self::getStandByDb()->fetchAll($sql, $sqlData);

		return $result[0]["USERSCOUNT"];
	}


	private function getNewSuppliers ($companyId)
	{
		if (!isset($this->companyData[$companyId]["newSuppliers"]))
		{

			$newSupplierIds = array ();

			foreach ($this->getNewSuppliersData() as $supplier)
			{
				if ($supplier["BYO_ORG_CODE"] == $companyId)
				{
					//only add those suppliers that do not have reviews or review request from this buyer
					if (!$this->isSupplierReviewedOrSolicitedbyBuyer($supplier["SPB_BRANCH_CODE"], $companyId))
					{
						$newSupplierIds[] = $supplier["SPB_BRANCH_CODE"];
					}
				}
			}

			$this->companyData[$companyId]["newSuppliers"] = $newSupplierIds;
		}

		return $this->companyData[$companyId]["newSuppliers"];
	}

	private function getMostViewedSuppliers ($companyId)
	{
		$sql ="select pe_endorsee_id
			from pages_endorsement pe
			left join
			(
				select pue_endorsee_id, count(*) as endCount from pages_user_endorsement where pue_created_date is not null group by pue_endorsee_id
			) c on (pe.pe_endorsee_id = c.pue_endorsee_id)
			where
				pe_endorsee_id in (
					select pss_spb_branch_code
					from
					(
						select a.*, ROW_NUMBER() OVER (ORDER BY a.displayCount) rn
						from
						(
							select pss_spb_branch_code, count (*) as displayCount
							from pages_statistics_supplier
							where
								pss_view_date > add_months(sysdate,-6)
								group by pss_spb_branch_code order by displayCount desc
						) a
					) b
					where b.rn<=500
				)
				and nvl(endCount,0)<3
				and pe_endorser_id=:companyId";

			$sqlData = array("companyId"=>$companyId);

			$result = self::getStandByDb()->fetchAll($sql, $sqlData);

			$viewedSupplierIds = array();

			if (count($result)>0)
			{
				foreach ($result as $endorsementArray)
				{
					//only add those suppliers that do not have reviews or review request from this buyer
					if (!$this->isSupplierReviewedOrSolicitedbyBuyer($endorsementArray["PE_ENDORSEE_ID"], $companyId))
					{
						$viewedSupplierIds[] = $endorsementArray["PE_ENDORSEE_ID"];
					}
				}
			}
		return $viewedSupplierIds;
	}

	/**
	 *
	 * @param integer $companyId
	 */
	private function getTargetSuppliers ($companyId)
	{
		$sql ="select pe_endorsee_id
			from pages_endorsement pe
			inner join
			(
				select distinct supplier_branch_code from supply_category where product_category_id in (".implode(",", $this->prefSupplierCategories).")
			) c on (pe.pe_endorsee_id = c.supplier_branch_code)
			where
				pe_endorser_id=:companyId";

			$sqlData = array("companyId"=>$companyId);

			$result = self::getStandByDb()->fetchAll($sql, $sqlData);

			$targetSupplierIds = array();

			if (count($result)>0)
			{
				foreach ($result as $endorsementArray)
				{
					//only add those suppliers that do not have reviews or review request from this buyer
					if (!$this->isSupplierReviewedOrSolicitedbyBuyer($endorsementArray["PE_ENDORSEE_ID"], $companyId))
					{
						$targetSupplierIds[] = $endorsementArray["PE_ENDORSEE_ID"];
					}
				}
			}
		return $targetSupplierIds;
	}

	/**
	 *
	 *
	 * @param integer $companyId Buyer org Id
	 */
	private function getRecentSuppliers ($companyId)
	{
		if (!isset($this->companyData[$companyId]["recentSuppliers"]))
		{
			$sql =
<<<EOT
	select pe_endorsee_id
	from pages_endorsement
	where
		pe_endorser_id=:companyId

EOT;
			$sqlData = array("companyId"=>$companyId);

			$result = self::getStandByDb()->fetchAll($sql, $sqlData);

			$recentSupplierIds = array();

			if (count($result)>0)
			{
				foreach ($result as $endorsementArray)
				{
					//only add those suppliers that do not have reviews or review request from this buyer
					if (!$this->isSupplierReviewedOrSolicitedbyBuyer($endorsementArray["PE_ENDORSEE_ID"], $companyId))
					{
						$recentSupplierIds[] = $endorsementArray["PE_ENDORSEE_ID"];
					}
				}
			}

			$this->companyData[$companyId]["recentSuppliers"] = $recentSupplierIds;
		}

		return $this->companyData[$companyId]["recentSuppliers"];
	}



	private function isEmailRecentlySolicited ($email)
	{
		if (!$this->emailsWithRecentRequests)
		{
			$sql =
<<<EOT
	select distinct PUE_USER_EMAIL
	from pages_user_endorsement
	where pue_requested_date>(sysdate-14)
		and pue_created_date is null
EOT;
			$sqlData = array();

			$result = self::getDb()->fetchAll($sql, $sqlData);

			if (count($result))
			{
				foreach ($result as $emailArr)
				{
					$this->emailsWithRecentRequests[] = strtolower($emailArr["PUE_USER_EMAIL"]);
				}
			}
		}

		return in_array(strtolower($email), $this->emailsWithRecentRequests);

	}

	private function isSupplierSolicitedOrReviewedByUser ($userId, $email, $supplierId)
	{
		$sql =
<<<EOT
	select *
	from pages_user_endorsement
	where
		(pue_user_email = :email OR pue_user_id = :userId)
		and pue_endorsee_id = :supplierId
EOT;
			$sqlData = array(
				"email"			=>$email,
				"userId"		=>$userId,
				"supplierId"	=> $supplierId
			);

			$result = self::getDb()->fetchAll($sql, $sqlData);

			return (count($result)>0);

	}

	private function findSupplierForRequest ($supplierIds, $user)
	{
		if (count($supplierIds)>0)
		{
			foreach ($supplierIds as $supplierId)
			{
				//exclude all suppliers that may be normalised to something else
				if (!$this->isSupplierNormalised($supplierId))
				{
					//check if supplier will not mind to be autosolicited
					if ($this->isSupplierAllowedAutoSolicitation($supplierId))
					{
						//check if this supplier previously not reviewed by user and user does not have solicitation for its review
						if (!$this->isSupplierSolicitedOrReviewedByUser($user->userId, $user->email, $supplierId))
						{
							return $supplierId;
						}
					}
				}
			}
		}
		return false;
	}

	private function getNewSuppliersData ()
	{
		if (!isset($this->newSuppliersData))
		{
			$sql =
<<<EOT

select * from
(
	select nvl(psn.psn_norm_spb_branch_code,ord_spb_branch_code) as spb_branch_code, nvl(pbb.pbbn_byo_org_code,nvl(pbn.pbn_norm_byo_org_code,ord_byb_byo_buyer_org_code)) as byo_org_code, max(last_or_date) as last_order_date_, min(first_or_date) as first_order_date from
	(
		select ord_spb_branch_code, ord_byb_byo_buyer_org_code, ord_byb_buyer_branch_code, max(ord_submitted_date) as last_or_date, min(ord_submitted_date) as first_or_date
		from purchase_order po
		left join order_response orp on (orp.orp_ord_internal_ref_no = po.ord_internal_ref_no)
		where
			ord_sts = 'SUB'
			and NVL(orp_ord_sts,'NON') != 'DEC'
			group by ord_spb_branch_code, ord_byb_byo_buyer_org_code, ord_byb_buyer_branch_code
	)
	left join pages_byo_norm pbn on (ord_byb_byo_buyer_org_code = pbn.pbn_byo_org_code)
	left join pages_spb_norm psn on (ord_spb_branch_code = psn.psn_spb_branch_code)
	left join pages_byb_byo_norm pbb on (ord_byb_buyer_branch_code = pbb.pbbn_byb_branch_code)
	where
		nvl(psn.psn_norm_spb_branch_code,ord_spb_branch_code) is not null
	group by nvl(psn.psn_norm_spb_branch_code,ord_spb_branch_code), nvl(pbb.pbbn_byo_org_code,nvl(pbn.pbn_norm_byo_org_code,ord_byb_byo_buyer_org_code))
)
where
	first_order_date >= add_months(sysdate,-3)
	and first_order_date < add_months(sysdate,-1)
order by first_order_date

EOT;
			$sqlData = array();
			$result = self::getStandByDb()->fetchAll($sql, $sqlData);
			$this->newSuppliersData = $result;
		}

		return $this->newSuppliersData;
	}

	private function getMostViewedSuppliersData () {

		if (!$this->mostViewedSuppliers)
		{
			$sql =
<<<EOT

select pss_spb_branch_code
from
(
	select a.*, ROW_NUMBER() OVER (ORDER BY a.displayCount) rn
	from
	(
		select pss_spb_branch_code, count (*) as displayCount
		from pages_statistics_supplier
		where
			pss_view_date > add_months(sysdate,-6)
			group by pss_spb_branch_code order by displayCount desc
	) a
) b
where b.rn<=500
EOT;
			$sqlData = array();
			$result = self::getStandByDb()->fetchAll($sql, $sqlData);

			$mostViewedSuppliersIds = array ();

			if (count($result)>0)
			{
				foreach ($result as $row)
				{
					$mostViewedSuppliersIds[] = $row["PSS_SPB_BRANCH_CODE"];
				}
			}

			$this->mostViewedSuppliers = $mostViewedSuppliersIds;
		}
		return $this->mostViewedSuppliers;
	}

	private function isSupplierReviewedOrSolicitedbyBuyer ($supplierId, $buyerId)
	{
		if (!$this->reviewsData)
		{
			$sql =
<<<EOT
select pue_endorser_id, pue_endorsee_id, count(*) as actCount from pages_user_endorsement group by pue_endorser_id, pue_endorsee_id
EOT;
			$sqlData = array();
			$result = self::getDb()->fetchAll($sql, $sqlData);

			if (count($result)>0)
			{
				foreach ($result as $row)
				{
					$this->reviewsData[$row["PUE_ENDORSEE_ID"]][$row["PUE_ENDORSER_ID"]] = $row["ACTCOUNT"];
				}
			}

		}

		return isset($this->reviewsData[$supplierId][$buyerId]);

	}
	
	private function isSupplierNormalised($supplierId)
	{
		return in_array($supplierId, $this->getNormalisedSuppliers());
	}

	private function isSupplierAllowedAutoSolicitation ($supplierId)
	{
		$supplier = Shipserv_Oracle_PagesCompany::getInstance()->fetchById("SPB",$supplierId);

		return $supplier["PCO_AUTO_REV_SOLICIT"]=="Y";

	}
	
	private function getNormalisedSuppliers()
	{
		if (!$this->normSuppliers)
		{
			$normSuppliers = array ();
			
			$sql =
<<<EOT
SELECT PSN_SPB_BRANCH_CODE FROM PAGES_SPB_NORM
EOT;
			$sqlData = array();
			$result = self::getDb()->fetchAll($sql, $sqlData);

			if (count($result)>0)
			{
				foreach ($result as $row)
				{
					$normSuppliers[] = $row["PSN_SPB_BRANCH_CODE"];
				}
			}
			
			$this->normSuppliers = $normSuppliers;

		}

		return $this->normSuppliers;
		
	}
}
?>
