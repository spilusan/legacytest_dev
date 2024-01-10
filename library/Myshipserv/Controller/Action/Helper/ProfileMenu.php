<?php

class Myshipserv_Controller_Action_Helper_ProfileMenu extends Zend_Controller_Action_Helper_Abstract
{	
	public $myCompanies;
	
	public function getTopLevelMenu($headerLeft, $selectId = null, $pendingCompanies = array())
	{
		$user = $this->getUser();
		$arr = array(
			'headerLeft'  => $headerLeft,
			'headerRight' => 'Profile',
			'menuItems'   => array(
				array(
					'id'	 => 'overview',
					'link'   => '/profile/overview',
					'imgSrc' => '/images/layout_v2/profile/my-info.png',
					'text'   => 'My Info'
				),
				array(
					'id'			=> 'companies',
					'link'          => '/profile/companies',
					'imgSrc'        => '/images/layout_v2/profile/my-companies.png',
					'text'          => 'My Companies',
					//'countOverlay'  => Zend_Controller_Action_HelperBroker::getStaticHelper('PendingAction')->countPendingCompanyActions(),
					//'countOverlayId'=> 'jq-pending-company-actions',
					'isNew'			=> false
				)
			)
		);
		
		$pendingReviewRequestsCount = Zend_Controller_Action_HelperBroker::getStaticHelper('PendingAction')->countPendingReviewActions();

		if ($pendingReviewRequestsCount > 0)
		{
			$arr["menuItems"][] = array(
				'id'			=> 'review-requests',
				'link'			=> '/profile/review-requests',
				'imgSrc'		=> '/images/layout_v2/profile/review_requests_icon.gif',
				'countOverlay'	=> $pendingReviewRequestsCount,
				'countOverlayId'=> 'jq-pending-reviews-actions',
				'text'			=> 'Review Requests'
			);
		}
		$arr["menuItems"][] =array(
			'id'			=>'my-reviews',
			'link'			=> '/profile/my-reviews',
			'imgSrc'		=> '/images/layout_v2/profile/my-reviews.png',
			'text'			=> 'My Reviews'
		);

		
		$arr["menuItems"][] = array(
					'id'		=> 'notifications',
					'link'      => '/profile/notifications',
					'imgSrc'    => '/images/layout_v2/profile/notifications.png',
					'text'      => 'Settings'
				);
		
		$managedCategories = Shipserv_CategoryAuthorisation::getManagedCategories($user->userId);
		if (count($managedCategories)>0)
		{
			$arr["menuItems"][] = array(
				'id'			=> 'categories',
				'link'			=> '/profile/categories',
				'imgSrc'		=> '/images/layout_v2/categories/categories_icon.png',
				'countOverlay'	=> Zend_Controller_Action_HelperBroker::getStaticHelper('PendingAction')->countPendingCategoriesActions(),
				'countOverlayId'=> 'jq-pending-categories-actions',
				'text'			=> 'Category management'
			);
		}
		
		foreach ($arr['menuItems'] as $k => &$v)
		{
			$v['selected'] = ($v['id'] == $selectId ? 1 : 0);
		}
		
		return $arr;
	}
	
	
	/**
	 * getCompanyMenu get the vertical menu displayed in /profile section 
	 * 
	 * @param unknown $headerLeft
	 * @param unknown $companyType
	 * @param Int $companyId
	 * @param Int $selectId
	 * @param Array $pendingUsers
	 * @param Bool $isAdmin
	 * @return Array
	 */
	public function getCompanyMenu($headerLeft, $companyType, $companyId, $selectId = null, $pendingUsers = array(), $isAdmin = null)
	{

		$companyUrlParams = "type/{$companyType}/id/{$companyId}";
		$user = $this->getUser();
		$isShipservUser = $user->isShipservUser();
		$isAdminOfCompany = $user->isAdminOf($companyId);
		$isPartOfCompany = $user->isPartOfCompany($companyId);
		
		$arr = array(
			'headerLeft'   => $headerLeft,
			
			'headerRight'  => 'Preferences'
		);
		
		$arr['menuItems'] = array();

		if ($companyType == "b") {
			//$tnUser = $user->getTradenetUser();
			//if ($tnUser->canAccessApprovedSupplier()) { 
			if ($isAdminOfCompany || $isShipservUser)  {
				$arr["menuItems"][] = array(
					'id'	   => 'company-approved-suppliers',
					'link'     => "/profile/company-approved-suppliers",
					'imgSrc'   => '/images/layout_v2/profile/my-reviews.png',
					'text'     => 'Approved Suppliers'
				);
			}
		}
		
		
		if ($companyType == "v") {
			$arr["menuItems"][] = array(
				'id'			 => 'company-profile',
				'link'           => "/profile/company-profile/$companyUrlParams",
				'imgSrc'         => '/images/layout_v2/profile/my-info.png',
				'text'           => 'Company Profile',
				'isNew'			 => false
			);
			if ($user->canAccessActivePromotion()) {
				$arr["menuItems"][] = array(
					'id'			 => 'target-customers',
					'link'           => "/profile/target-customers/$companyUrlParams",
					'text'           => 'Active Promotion',
					'isNew'			 => true
				);
			}
			
		}
		
		if ($companyType == "b") {
			$arr["menuItems"][] = array(
				'id'	   => 'company-reviews',
				'link'     => "/profile/company-reviews/$companyUrlParams",
				'imgSrc'   => '/images/layout_v2/profile/my-reviews.png',
				'text'     => 'Reviews'
			);

			if ($isShipservUser || ($user && $user->canAccessFeature($user::BRANCH_FILTER_AUTOREMINDER))) {
				$arr["menuItems"][] = array(
					'id'	   => 'company-automatic-reminder',
					'link'     => "/profile/company-automatic-reminder/$companyUrlParams",
					'imgSrc'   => '/images/layout_v2/profile/my-reviews.png',
					'text'     => 'Automatic Reminders'
				);
			}

		}
		
		$arr["menuItems"][] = array(
			'id'			 => 'company-people',
			'link'           => "/profile/company-people/$companyUrlParams",
			'imgSrc'         => '/images/layout_v2/profile/my-info.png',
			'countOverlay'   => $pendingUsers === null ? 0 : count($pendingUsers),
			'countOverlayId' => 'jq-pending-people-actions',
			'text'           => 'Users'
		);
		

		// only enable this for user (NOT SHIPMATE)
		if ($isShipservUser === false || $isShipservUser === true) {
			if ($isPartOfCompany == true) {
				if ($user->isAdminOf($companyId)) {
					$arr["menuItems"][] = array(
						'id'	   => 'company-privacy',
						'link'     => "/profile/company/$companyUrlParams",
						'imgSrc'   => '/images/layout_v2/profile/privacy-and-emails.png',
						'text'     => 'Privacy'
					);
				}
			}
			
			if (count(Shipserv_BrandAuthorisation::getManagedBrands($companyId)) > 0) {
				if ($isAdminOfCompany || $isShipservUser) {
					$arr["menuItems"][] = array(
						'id'	   => 'company-brands',
						'link'     => "/profile/company-brands/$companyUrlParams",
						'imgSrc'   => '/images/layout_v2/profile/brands_management_icon.gif',
						'countOverlay'   => Zend_Controller_Action_HelperBroker::getStaticHelper('PendingAction')->countCompanyBrandActions($companyId),
						'countOverlayId' => 'jq-pending-brands-actions',
						'text'     => 'Brands'
					);
				}
			}
			if ($isPartOfCompany == true) {
				if (count(Shipserv_MembershipAuthorisation::getOwnedMemberships($companyId)) > 0 && $isAdminOfCompany) {
					$arr["menuItems"][] = array(
						'id'	   => 'company-memberships',
						'link'     => "/profile/company-memberships/$companyUrlParams",
						'imgSrc'   => '/images/layout_v2/profile/brands_management_icon.gif',
						'countOverlay'   => Zend_Controller_Action_HelperBroker::getStaticHelper('PendingAction')->countCompanyMembershipActions($companyId),
						'countOverlayId' => 'jq-pending-memberships-actions',
						'text'     => 'Memberships'
					);
				}
			}
		}

		if ($isAdminOfCompany || ( $isShipservUser &&  $user->canPerform('PSG_TURN_TN_INTEGRATION') == true)) {

			$arr["menuItems"][] = array(
				'id'    => 'company-settings-pages',
                'link'  => "/profile/company-settings-pages/$companyUrlParams",
                'imgSrc'   => '/images/layout_v2/profile/settings_icon.gif',
                'text'  => 'Pages Settings',
			);
		}

		if ($companyType == "b") {
            	if ($isAdminOfCompany || $isShipservUser) {
					 $arr["menuItems"][] = array(
    					'id'    => 'company-settings-match',
                        'link'  => "/profile/company-settings-match/$companyUrlParams",
                        'imgSrc'   => '/images/layout_v2/profile/settings_icon.gif',
                        'text'  => 'Spend Management'
    				);
				}
			}
		
		if ($companyType == "v") {
			
			
			/*
		if( $user->isShipservUser() === true )
		{
			$arr["menuItems"][] =array(
				'id'			=>'user-management',
				'link'			=> '/profile/user-management',
				'imgSrc'		=> '/images/layout_v2/profile/profile_add_user_icon.png',
				'text'			=> 'Users',
				'isNew'			=> true
		
			);
			$arr["menuItems"][] =array(
				'id'			=>'enquiry-browser',
				'link'			=> '/profile/enquiry-browser',
				'imgSrc'		=> '/images/layout_v2/profile/pages_enquiries_icon.png',
				'text'			=> 'RFQs',
				'isNew'			=> true
			);
		}
			
			 */
			/*
			$arr["menuItems"][] =array(
				'id'			=>'company-enquiry',
				'link'			=> '/profile/company-enquiry/' . $companyUrlParams,
				'imgSrc'		=> '/images/layout_v2/profile/pages_enquiries_icon.png',
				'countOverlay'	=> Zend_Controller_Action_HelperBroker::getStaticHelper('PendingAction')->countUnreadEnquiriesActions($companyId),
				'countOverlayId' => 'jq-unread-enquiries',
				'text'			=> 'RFQs',
				'isNew'			=> true
			);

			$arr["menuItems"][] = array(
				'id'	   => 'company-blocked-list',
				'link'     => "/profile/company-blocked-user-list/$companyUrlParams",
				'imgSrc'   => '/images/layout_v2/profile/settings_icon.gif',
				'text'     => 'RFQ Blocked list',
				'isNew'			=> true
			);
			*/
			/*
			$arr["menuItems"][] = array(
					'id'	   => 'company-badge',
					'link'     => "/profile/company-badge/$companyUrlParams",
					'imgSrc'   => '/images/layout_v2/profile/settings_icon.gif',
					'text'     => 'Supplier badge',
					'isNew'			=> true
			);
			*/	
		}
		
		foreach ( $arr['menuItems'] as &$v) {
			$v['selected'] = ($v['id']==$selectId ? 1 : 0);
            
            if (isset($v['secondary'])) {
                foreach ($v['secondary'] as &$sec) {
                    $sec['selected'] = ($sec['id']==$selectId ? 1 : 0);
                }
            }
		}

		
		return $arr;
	}
	
	
	
	private function getUser()
	{
		$user = Shipserv_User::isLoggedIn();
		if (!$user) {
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}
		return $user;
	}
}
