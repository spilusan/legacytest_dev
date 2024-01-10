<?php

/**
 * Class for reading endorsement data from Oracle
 *
 * @package Shipserv
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class Shipserv_Oracle_Endorsements extends Shipserv_Oracle
{
	public function __construct (&$db)
	{
		parent::__construct($db);
	}

	/**
	 * Fetches a list of endorsements for a given endorsee
	 *
	 * @access public
	 * @param int $endorseeTNID The TradeNet ID of the endorsee for which endorsements should be fetched
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchEndorsementsByEndorsee ($endorseeTNID, $useCache = false, $cacheTTL = 86400)
	{
		$sql = 'SELECT * FROM (SELECT * FROM pages_endorsement WHERE pe_endorsee_id = :endorsee_tnid) pe';
		$sql.= ' FULL join (SELECT pue_endorser_id as pe_endorser_id, count(*) as persEndCount from pages_user_endorsement where pue_endorsee_id=:endorsee_tnid and pue_created_date is not null group by pue_endorser_id) pue using (pe_endorser_id) ';
		$sql.= ' JOIN BUYER_ORGANISATION bo on (pe_endorser_id=bo.byo_org_code)';
		$sql.= " LEFT JOIN pages_company pco ON (pco_id=byo_org_code AND pco_type='BYO')";
		$sql.= " LEFT JOIN pages_byo_norm pbn ON (pe_endorser_id = pbn_byo_org_code) ";
		$sql.= " WHERE NVL(PCO_REVIEWS_OPTOUT,'N')!='Y' ";
		$sql.= " AND NOT(NVL(pbn.pbn_byo_org_code,0)!=0 AND NVL(pbn.pbn_norm_byo_org_code,0)=0) ";
		$sql.= ' ORDER BY pue.persEndCount DESC NULLS LAST';
		
		$sqlData = array('endorsee_tnid' => $endorseeTNID);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'ENDORSEMENTSFOR_'.$endorseeTNID.
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
	 * Fetches a list of endorsements for a given endorsee
	 *
	 * @access public
	 * @param int $endorseeTNID The TradeNet ID of the endorsee for which endorsements should be fetched
	 * @param boolean $useCache Set to TRUE if the results should be cached/fetched from cache
	 * @param int $cacheTTL The time in seconds of how long the cache should persist
	 * @return array
	 */
	public function fetchEndorsementsByEndorser ($endorserTNID, $order = null, $page = 1, $pageSize = 20, $useCache = false, $cacheTTL = 86400)
	{
		$sql = "";

		if ($order == "top")
		{
			$order = " pe_orders_num DESC NULLS LAST, ";
		}
		elseif ($order == "reviews")
		{
			$order = " persEndCount desc NULLS LAST, ";
		}
		elseif ($order == "recent")
		{
			$order = " PE_LAST_ORDER_DATE desc NULLS LAST, ";
		}
		else {
			$order = null;
		}

		if ($page > 0)
		{
			$sql .= 'SELECT bbb.* FROM (';

			$sql .= 'SELECT aaa.* , ROW_NUMBER() OVER (ORDER BY '. $order .' SPB_NAME) R, count(*) over () as totalCount FROM (';
		}
		$sql .= ' SELECT * ';
		$sql .= ' FROM (SELECT * FROM pages_endorsement WHERE pe_endorser_id = :endorser_tnid) pe ';
		$sql .= ' FULL join (SELECT pue_endorsee_id as pe_endorsee_id, count(*) as persEndCount from pages_user_endorsement where pue_endorser_id=:endorser_tnid and pue_created_date is not null GROUP BY pue_endorsee_id) pue using (pe_endorsee_id) ';
		$sql .= ' JOIN SUPPLIER_BRANCH ON (pe_endorsee_id = SPB_BRANCH_CODE) ';
		if ($page > 0)
		{
			$sql .= ') aaa) bbb WHERE R BETWEEN '.(($page-1)*$pageSize).' and '.($page*$pageSize);
		}
		
		$sqlData = array('endorser_tnid' => $endorserTNID);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'ENDORSEMENTSBY_'.$endorserTNID.
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
	 * Retrieve endorsement record by Endorsee and Endorser
	 *
	 * @param integer $endorseeTNID
	 * @param boolean $useCache
	 * @param integer $cacheTTL
	 * @return array
	 */
	public function fetchEndorsementsByEndorseeAndEndorser ($endorseeTNID, $endorserId, $useCache = false, $cacheTTL = 86400)
	{
		$sql = 'SELECT * FROM (SELECT * FROM pages_endorsement WHERE pe_endorsee_id = :endorsee_tnid and pe_endorser_id =:endorser_id) pe';
		$sql.= ' FULL join (SELECT pue_endorser_id as pe_endorser_id, count(*) as persEndCount from pages_user_endorsement where pue_endorsee_id=:endorsee_tnid and pue_endorser_id=:endorser_id and pue_created_date is not null group by pue_endorser_id) pue using (pe_endorser_id) ';
		$sql.= ' JOIN BUYER_ORGANISATION bo on (pe_endorser_id=bo.byo_org_code)';
		$sql.= " LEFT JOIN pages_company pco ON (pco_id=byo_org_code and pco_type='BYO')";
		$sql.= ' ORDER BY pue.persEndCount DESC NULLS LAST';
		$sqlData = array(
			'endorsee_tnid' => $endorseeTNID,
			'endorser_id'	=> $endorserId
		);

		if ($useCache)
		{
			$key = $this->memcacheConfig->client->keyPrefix . 'ENDORSEMENTSFOR_'.$endorseeTNID.'_'.$endorserId.
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
	 * Works out whether a supplier's trading relationships with specified buyers
	 * may be revealed.
	 *
	 * @param mixed $id Supplier branch code
	 * @param array $buyerIds Buyer organisation code
	 * @param   bool    $cliContext
     *
	 * @return array Associative array of buyer organisation codes => reveal (as boolean)
	 */
	function showBuyers ($id, array $buyerIds, $cliContext = false)
	{
		
		$dbPriv = new Shipserv_Oracle_EndorsementPrivacy($this->db);
		$sPrivacy = $dbPriv->getSupplierPrivacy($id);
		
		// Shipserv_Oracle_EndorsementPrivacy::ANON_YES | Shipserv_Oracle_EndorsementPrivacy::ANON_NO | Shipserv_Oracle_EndorsementPrivacy::ANON_TN
		$sAnonPolicy = $sPrivacy->getGlobalAnonPolicy();
		// Loop on proposed buyers
		$showBuyerArr = array();
		foreach ($buyerIds as $bId)
		{
			// Default: do not show
			$showThis = false;
			
			// Supplier's anon policy is never anonymise ...
			if ($sAnonPolicy == Shipserv_Oracle_EndorsementPrivacy::ANON_NO)
			{
				// Check buyer's anon policy
				$bPrivacy = $dbPriv->getBuyerPrivacy($bId);
				$bPolicy = $bPrivacy->getGlobalAnonPolicy();
				// Buyer's policy is do not anonymise ...
				if ($bPolicy == Shipserv_Oracle_EndorsementPrivacy::ANON_NO)
				{
					// Allow
					$showThis = true;
					
					// But, check exceptions ...
					$bExRules = $bPrivacy->getExceptionRules();
					
					// If there is an exception rule for this supplier ...
					if (isset($bExRules[$id]) && $bExRules[$id] == Shipserv_Oracle_EndorsementPrivacy::ANON_YES)
					{
						$showThis = false;
					}
				}
				
				// Buyer's policy is to anonymise, except for TN buyers ...
				elseif ($bPolicy == Shipserv_Oracle_EndorsementPrivacy::ANON_TN)
				{
                    if ($this->isLoggedUserTnBuyer($cliContext))
                    {
                        $showThis = true;
                    }
				}
			}
			
			// Record display decision against buyer id
			$showBuyerArr[$bId] = $showThis;
		}
		
		return $showBuyerArr;
	}
	
	/**
	 * Checks if logged-in user is a TN buyer: i.e. is an active member of at
	 * least 1 buyer organisation.
	 *
     * @param   bool    $cliContext
     *
	 * @return bool
	 */
	private function isLoggedUserTnBuyer($cliContext = false)
	{
        if ($cliContext) {
            return false;   // running in the context where the concept of the current user is not applicable
        }

		$u = Shipserv_User::isLoggedIn();

		// If not logged-in, return no
		if (!$u) return false;

		return (bool) $u->fetchCompanies()->getBuyerIds();
	}
}
