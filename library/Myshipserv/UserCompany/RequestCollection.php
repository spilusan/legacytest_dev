<?php

/**
 * Collection of company join requests.
 */
class Myshipserv_UserCompany_RequestCollection
{
	// Values for $getType method parameter
	const GET_PENDING = Shipserv_Oracle_UserCompanyRequest::STATUS_PENDING;
	const GET_REJECTED = Shipserv_Oracle_UserCompanyRequest::STATUS_REJECTED;
	
	private $requestArr = array();
	
	/**
	 * @param array $requests Indexed array of associative arrays (columns of PAGES_USER_COMPANY_REQUEST table)
	 */
	public function __construct (array $requests)
	{
		$this->requestArr = $requests;
	}
	
	/**
	 * Fetch pending / rejected requests, extracting target company IDs and
	 * sorting them into buyer / supplier.
	 *
	 * This is a messy method signature: refactor.
	 * 
	 * @param mixed $getType GET_PENDING | GET_REJECTED
	 * @param array &$returnBuyerIds Buyer org codes
	 * @param array &$returnSupplierIds Supplier branch codes
	 * @param array &$returnRequests Join requests (as associative arrays)
	 * @return void
	 */
	public function getLatestUniqueRequests ($getType, &$returnBuyerIds, &$returnSupplierIds, &$returnRequests)
	{
		$uniqArr = $this->getCurrentRequests();
		
		// Only pending / rejected requests
		foreach ($uniqArr as $k => $req)
		{
			if ($req['PUCR_STATUS'] != Shipserv_Oracle_UserCompanyRequest::STATUS_PENDING && $req['PUCR_STATUS'] != Shipserv_Oracle_UserCompanyRequest::STATUS_REJECTED)
			{
				unset($uniqArr[$k]);
			}
		}
		
		// todo: remove any pendings that are current company members
		// Not necessary at moment because only route to add user is thru accepting request,
		// which turns pending request into confirmed request. But in future ...
		
		$returnBuyerIds = $returnSupplierIds = $returnRequests = array();
		foreach ($uniqArr as $k => $req)
		{	
			if ($req['PUCR_STATUS'] == $getType)
			{
				$returnRequests[] = $req;
				if ($req['PUCR_COMPANY_TYPE'] == Shipserv_Oracle_UserCompanyRequest::COMP_TYPE_SPB)
				{
					$returnSupplierIds[] = $req['PUCR_COMPANY_ID'];
				}
				elseif ($req['PUCR_COMPANY_TYPE'] == Shipserv_Oracle_UserCompanyRequest::COMP_TYPE_BYO)
				{
					$returnBuyerIds[] = $req['PUCR_COMPANY_ID'];
				}
			}
		}
	}
	
	/**
	 * Returns only the most recent join requests per user per company.
	 * (Join requests are stored historically: this is a roll-up by
	 * user id, company type & company id, ordered by time increasing).
	 * 
	 * @return array Join requests (as associative arrays)
	 */
	public function getCurrentRequests ()
	{
		// Sort requests by processed date if present, or by created date
		$forSort = $this->requestArr;
		usort($forSort, array($this, 'sortByActionDate'));
		
		// Only the latest request for each user-company pairing
		$uniqArr = array();
		$uniqArrKeysByUsr = array();
		foreach ($forSort as $k => $req)
		{
			$thisKey = "{$req['PUCR_PSU_ID']}-{$req['PUCR_COMPANY_TYPE']}-{$req['PUCR_COMPANY_ID']}";
			$uniqArr[$thisKey] = $req;
			
			// Also index these keys by user
			$uniqArrKeysByUsr[$req['PUCR_PSU_ID']][] = $thisKey;
		}
		
		// Check that users who made join requests: remove requests by non-valid users
		$uDao = new Shipserv_Oracle_User($this->getDb());
		
		// Fetch users who originated join requests (non-actives not returned)
		$uArr = $uDao->fetchUsers(array_keys($uniqArrKeysByUsr))->makeShipservUsers();
		$uIdsArr = array();
		foreach ($uArr as $k => $u)
		{
			$uIdsArr[$u->userId] = 1;
		}
		
		// Loop over users, removing non-valid requests from return arr
		foreach ($uniqArrKeysByUsr as $uId => $reqKeyArr)
		{
			// If this is not a valid user ...
			if (!@$uIdsArr[$uId])
			{
				// Loop over return array keys to delete
				foreach ($reqKeyArr as $keyToDel)
				{
					unset($uniqArr[$keyToDel]);
				}
			}
		}
		
		return array_values($uniqArr);
	}
	
	private function getDb ()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}
	
	/**
	 * Fetches current requests and filters out only the pending ones.
	 *
	 * @return array Join requests (as associative arrays)
	 */
	public function getPendingRequests ()
	{
		$resArr = array();
		$currentReqs = $this->getCurrentRequests();
		foreach ($currentReqs as $req)
		{
			if ($req['PUCR_STATUS'] == Shipserv_Oracle_UserCompanyRequest::STATUS_PENDING)
			{
				$resArr[] = $req;
			}
		}
		
		return $resArr;
	}
	
	// rework
	public function getPendingRequestById ($reqId)
	{
		foreach ($this->requestArr as $req)
		{
			if ($req['PUCR_ID'] == $reqId && $req['PUCR_STATUS'] == Shipserv_Oracle_UserCompanyRequest::STATUS_PENDING)
			{
				return $req;
			}
		}
		throw new Exception("No pending request for id: $reqId");
	}
	
	/**
	 * Sorts join requests by sequential ID
	 */
	private function sortByActionDate ($a, $b)
	{
		if ($a['PUCR_ID'] == $b['PUCR_ID']) return 0;
		else return $a['PUCR_ID'] < $b['PUCR_ID'] ? -1 : 1;
	}
}
