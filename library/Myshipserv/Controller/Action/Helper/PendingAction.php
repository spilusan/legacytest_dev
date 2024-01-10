<?php

class Myshipserv_Controller_Action_Helper_PendingAction extends Zend_Controller_Action_Helper_Abstract
{
	private $cntTable;

	protected $data;

	public function countPendingActions ()
	{
		$total = 0;
		foreach ($this->countPendingActionsByCompany() as $cArr)
		{
			foreach ($cArr as $cnt) $total += $cnt;
		}
		
		$total += count($this->getReviewsActions());
		$total += $this->countPendingCategoriesActions();
				
		return $total;
	}

	/**
	 * Count how many actions needed for all company that this user
	 * is belong to
	 * 
	 * @return int total
	 */
	public function countPendingCompanyActions ()
	{
		$total = 0;
		foreach ($this->countPendingActionsByCompany() as $cArr)
		{
			foreach ($cArr as $cnt) $total += $cnt;
		}
		$myRequest = $this->_getMyPendingRequestToJoinCompany();
		$total += count( $myRequest );
		return $total;
	}

	public function countPendingReviewActions ()
	{
		return count($this->getReviewsActions());
	}

	public function countUnreadEnquiriesActions ( $supplierId = null )
	{
		$total = 0;
		foreach( $this->_getMyUnreadEnquiries( $supplierId ) as $data )
		{
			$total += $data['unread'];
		}
		return $total;
	}
	
	public function countCompanyBrandActions ($companyId)
	{
		$brandRequestsCount = count( $this->_getCompanyBrandActions($companyId) );
		return $brandRequestsCount;
	}
	
	public function countCompanyMembershipActions ($companyId)
	{
		return count( $this->_getCompanyMembershipActions( $companyId ) );
	}
		
	public function countPendingActionsByCompany ()
	{
		if ($this->cntTable === null)
		{
			$compColl = $this->getActions()->fetchMyCompanies();
			$aa = $this->getAdminActions();
			$reqCollArr = array();
			$compColl->getAdminIds($bIdArr, $sIdArr);
			
			$supplierAdapter = new Shipserv_Supplier( $this->getDb() );
			$supplierDbAdapter = new Shipserv_Oracle_Suppliers( $this->getDb() );
			
			$user = $this->getUser();
			
			foreach ($bIdArr as $bId)
			{
				$reqCollArr[] = $aa->fetchJoinRequestsForCompany(Myshipserv_UserCompany_AdminActions::COMP_TYPE_BYO, $bId, !$user->isShipservUser());
			}
			foreach ($sIdArr as $sId)
			{
				$reqCollArr[] = $aa->fetchJoinRequestsForCompany(Myshipserv_UserCompany_AdminActions::COMP_TYPE_SPB, $sId, !$user->isShipservUser());
			}
						
			$cntTable = array();

			$joinCompanyRequests = $this->_getPendingRequestToJoinCompany();

			if( $joinCompanyRequests !== null )
			{
				foreach( $joinCompanyRequests as $r )
				{
					$cntTable[$r['companyType']][$r['companyId']] += 1;
				}
			}
			
//			$unverifiedCompanies = $this->_getUnverifiedCompany();
//			if( $unverifiedCompanies !== null )
//			{
//				foreach( $unverifiedCompanies as $c )
//				{
//					$cntTable["SPB"][ $c["id"] ] += 1;
//				}
//			}
			
			
			//loop through suppliers
			foreach ($sIdArr as $sId)
			{
				if (isset($cntTable["SPB"][$sId]))
				{
					$cntTable["SPB"][$sId] += $this->countCompanyBrandActions($sId);
					$cntTable["SPB"][$sId] += $this->countCompanyMembershipActions($sId);
				}
				else
				{
					$cntTable["SPB"][$sId] = $this->countCompanyBrandActions($sId);
					$cntTable["SPB"][$sId] += $this->countCompanyMembershipActions($sId);
				}
			}
			
			$this->cntTable = $cntTable;
		}
		
		return $this->cntTable;
	}
	
	private function getActions ()
	{
		$user = $this->getUser();
		return new Myshipserv_UserCompany_Actions($this->getDb(), $user->userId, $user->email);
	}
	
	private function getAdminActions ()
	{
		$user = $this->getUser();
		return new Myshipserv_UserCompany_AdminActions($this->getDb(), $user->userId);
	}

	private function getReviewsActions ()
	{
		$user = $this->getUser();
		return Shipserv_ReviewRequest::getRequestsForEmail($user->email);
	}

	public function countPendingCategoriesActions ()
	{
		return count( $this->_getPendingCategoriesActions() );
	}
	
	private function getUser ()
	{
        $user = Shipserv_User::isLoggedIn();
        if (!$user) {
            Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
        }

        return $user;
	}
	
	private function getDb ()
	{
		return $GLOBALS['application']->getBootstrap()->getResource('db');
	}

	
	/**
	 * Utility function to append a new value to data store
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	public function addToData( $key, $value )
	{
		if( !isset($this->data[ $key ] ) ) $this->data[ $key ] = array();
		$this->data[ $key ] = array_merge( $this->data[ $key ], ( is_array( $value ) ) ? $value : array($value) );
	}
	
	/**
	 * Utility function to create a section in data store
	 * 
	 * @param string $key
	 * @param mixed $value
	 */
	public function storeToData( $key, $value )
	{
		$this->data[ $key ] = ( is_array( $value ) ) ? $value : array($value);
	}
	
	
	private function _getCompanyBrandActions( $companyId )
	{
		//loop through brands that they manage
		$requests = array();
		foreach (Shipserv_BrandAuthorisation::getManagedBrands($companyId) as $brandId=>$brand)
		{
			$pendingCompanyRequests = array ();
			$brandRequests = Shipserv_BrandAuthorisation::getRequests($brandId);
			if( count( $brandRequests ) > 0 )
			{
				foreach(Shipserv_BrandAuthorisation::getRequests($brandId) as $request)
				{
					if (!isset($pendingCompanyRequests[$brandId][$request->companyId])) {
						$pendingCompanyRequests[$brandId][$request->companyId] = true;
						//$this->data[ Myshipserv_AlertManager_Alert::ALERT_COMPANY_BRAND_AUTH ][] = $request;
						$requests[] = $request;
					}
				}
			}
		}
		
		return $requests;		
	}

	private function _getCompanyMembershipActions( $companyId )
	{
		$requests = array();
		//loop through memberships that they manage
		foreach (Shipserv_MembershipAuthorisation::getOwnedMemberships($companyId) as $membershipId=>$membership)
		{
			foreach( Shipserv_MembershipAuthorisation::getRequests($membershipId) as $request)
			{
				//$this->data[ Myshipserv_AlertManager_Alert::ALERT_COMPANY_MEMBERSHIP][] 
				$requests[]	= array( "membership" => $membership, "membershipRequest" => $request);
				//$requests[] = $request;
			}
		}
		return $requests;		
	}

	private function _getPendingCategoriesActions()
	{
		$d = array();
		$pendingCategoriesActions = 0;
		$user = $this->getUser();
		$managedCategories = Shipserv_CategoryAuthorisation::getManagedCategories($user->userId);
		if (count($managedCategories)>0)
		{
			$pendingCategoriesActions = 0;
			foreach ($managedCategories as $categoryId=>$managedCategory)
			{
				$requests = Shipserv_CategoryAuthorisation::getRequests($categoryId);
				foreach ( $requests as $request )
				{
					//$this->data[ Myshipserv_AlertManager_Alert::ALERT_PERSONAL_CATEGORY_REQUEST][] 
					$d[] = array( "companyId" => $request->companyId, "categoryId" => $request->categoryId, "categoryName" => $managedCategory["DISPLAYNAME"] );
					//$d[] = $request;
				}
			}
		}
		return $d;
	}
	
	private function _getMyPendingRequestToJoinCompany()
	{
		$ucActions = new Myshipserv_UserCompany_Actions($this->getDb(), $this->getUser()->userId, $this->getUser()->email);
		$profileDb = new Shipserv_Oracle_Profile($db);
		
		$ucaReqCompanies = $ucActions->fetchMyRequestedCompanies();
		$ucaReqCompanies->getLatestUniqueRequests(
			Myshipserv_UserCompany_RequestCollection::GET_PENDING,
			$returnBuyerIds, $returnSupplierIds, $returnRequests);		
		
		foreach( $returnRequests as $request )
		{
			//if( $request["PUCR_COMPANY_TYPE"] == "SPB")
			$new[] = $request;
		}
		return $new;
	}
	
	private function _getPendingRequestToJoinCompany()
	{
		$requests = array();
		
		$compColl = $this->getActions()->fetchMyCompanies();
		$aa = $this->getAdminActions();
		$reqCollArr = array();
		$compColl->getAdminIds($bIdArr, $sIdArr);
		
		$supplierAdapter = new Shipserv_Supplier( $this->getDb() );
		$supplierDbAdapter = new Shipserv_Oracle_Suppliers( $this->getDb() );
		
		$user = $this->getUser();
		foreach ($bIdArr as $bId)
		{
			$reqCollArr[] = $aa->fetchJoinRequestsForCompany(Myshipserv_UserCompany_AdminActions::COMP_TYPE_BYO, $bId, !$user->isShipservUser());
		}
		foreach ($sIdArr as $sId)
		{
			$reqCollArr[] = $aa->fetchJoinRequestsForCompany(Myshipserv_UserCompany_AdminActions::COMP_TYPE_SPB, $sId, !$user->isShipservUser());
		}
		
		foreach ($reqCollArr as $rc)
		{
			foreach ($rc->getPendingRequests() as $r)
			{
				$requests[] = array( "companyType" => $r['PUCR_COMPANY_TYPE'], 
									 "companyId" => $r['PUCR_COMPANY_ID'], 
							 		 "requestId" => $r["PUCR_ID"] ,
							 		 "userId" => $r["PUCR_PSU_ID"], 
							 		 "date" => $r["PUCR_CREATED_DATE"] );
			}
		}
		
		//$this->data[  Myshipserv_AlertManager_Alert::ALERT_COMPANY_USER_JOIN ]
		return $requests;
	}	

	
	private function _getUnverifiedCompany()
	{
		$companyArray = array();
		$compColl = $this->getActions()->fetchMyCompanies();
		$aa = $this->getAdminActions();
		$reqCollArr = array();
		$compColl->getAdminIds($bIdArr, $sIdArr);
		
		$supplierAdapter = new Shipserv_Supplier( $this->getDb() );
		$supplierDbAdapter = new Shipserv_Oracle_Suppliers( $this->getDb() );

		foreach ($sIdArr as $sId)
		{
			// check if it's verified
			if( $supplierDbAdapter->isVerifiedById( $sId ) === false )
			{	
				$companyArray[] = array( "type" => Myshipserv_UserCompany_AdminActions::COMP_TYPE_SPB, "id" => $sId );
			}
		}
		return $companyArray;
	}
	
	private function _getMyUnreadEnquiries( $supplierId = null )
	{
		if( $supplierId === null )
		{
			$companyArray = array();
			$compColl = $this->getActions()->fetchMyCompanies();
			$aa = $this->getAdminActions();
			$reqCollArr = array();
			$compColl->getAdminIds($bIdArr, $sIdArr);
			$supplierIds = $compColl->getSupplierIds();
		}
		else
		{
			$supplierIds = array( $supplierId );
		}
		
		// set the default date
		$now = new DateTime();
		$lastYear = new DateTime();
		
		$now->setDate(date('Y'), date('m'), date('d'));
		$lastYear->setDate(date('Y')-1, date('m'), date('d'));
		
		// store it to a data structure to be passed around
		$period = array('start' => $lastYear, 'end' => $now );
		
		foreach ($supplierIds as $sId)
		{
			$supplier = Shipserv_Supplier::fetch( $sId );
			$statistic = $supplier->getEnquiriesStatistic( $period );
			$companyArray[] = array( 'type' => Myshipserv_UserCompany_AdminActions::COMP_TYPE_SPB, "unread" => $statistic['notClicked'], "id" => $sId );
		}
		
		return $companyArray;
		
	}
	/**
	 * Pulls information about all actions that a user needs to do as mixed variables
	 * 
	 * @return mixed
	 */
	public function getData()
	{
		$compColl = $this->getActions()->fetchMyCompanies();
		$aa = $this->getAdminActions();
		$reqCollArr = array();
		$compColl->getAdminIds($bIdArr, $sIdArr);
		
		$supplierAdapter = new Shipserv_Supplier( $this->getDb() );
		$supplierDbAdapter = new Shipserv_Oracle_Suppliers( $this->getDb() );

		// get join company request data		
		$joinCompanyRequests = $this->_getPendingRequestToJoinCompany();
		if( $joinCompanyRequests !== null )
			$this->storeToData(Myshipserv_AlertManager_Alert::ALERT_COMPANY_USER_JOIN, $joinCompanyRequests, false );
		
		// get unverified suppliers
//		$unverifiedCompanies = $this->_getUnverifiedCompany();
//		if( $unverifiedCompanies !== null )
//		{
//			$this->storeToData( Myshipserv_AlertManager_Alert::ALERT_COMPANY_UNVERIFIED, $unverifiedCompanies);
//		}
		
		//loop through suppliers
		foreach ($sIdArr as $sId)
		{
			$x = $this->_getCompanyBrandActions($sId);
			if( count( $x ) > 0 )
				$this->addToData(Myshipserv_AlertManager_Alert::ALERT_COMPANY_BRAND_AUTH, $x );
			
			$x = $this->_getCompanyMembershipActions($sId);
			if( count( $x ) > 0 )
				$this->addToData(Myshipserv_AlertManager_Alert::ALERT_COMPANY_MEMBERSHIP, $x );
		}
 		
		$reviews = $this->getReviewsActions();
		if( count( $reviews ) > 0 )
			$this->storeToData( Myshipserv_AlertManager_Alert::ALERT_PERSONAL_REVIEW_REQUEST, $reviews);
		
		$categories = $this->_getPendingCategoriesActions();
		if( count( $categories ) > 0 )
			$this->storeToData( Myshipserv_AlertManager_Alert::ALERT_PERSONAL_CATEGORY_REQUEST, $categories);

			
		$myPendingRequest =  $this->_getMyPendingRequestToJoinCompany();
		if( count( $myPendingRequest ) > 0 )
			$this->storeToData( Myshipserv_AlertManager_Alert::ALERT_PERSONAL_COMPANY_JOIN_REQUEST, $myPendingRequest);

		
		$myUnreadEnquiries =  $this->_getMyUnreadEnquiries();
		if( count( $myUnreadEnquiries ) > 0 )
			$this->storeToData( Myshipserv_AlertManager_Alert::ALERT_COMPANY_UNREAD_ENQUIRIES, $myUnreadEnquiries);
		
			
		return $this->data;
	}	
	
}
