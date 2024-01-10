<?php
class Myshipserv_NotificationManager_Email_BrandAuthInviteBrandOwnerToAuthoriseSupplier  extends Myshipserv_NotificationManager_Email_Abstract{
	
   	private $brandAuthRequest;
	private $message;
	private $emails;
	protected static $statistic = null;
	protected $db;
	
	public function __construct ($db, $brandAuthRequest, $emails, $message)
	{
			
		parent::__construct($db);
		
		$this->db = $db;
		
		// enable SMTP relay through jangoSMTP (or any other)
		$this->enableSMTPRelay = true;

		$this->brandAuthRequest = $brandAuthRequest;
		$this->emails = $emails;
		$this->message = $message;
	}
	
	public function getRecipients ()
	{
		$res = array();
		$ucDom = $this->getUserCompanyDomain();


		//fetch list of companies that own brand
		$brandOwnerCompanyIds = Shipserv_BrandAuthorisation::getBrandOwners($this->brandAuthRequest->brandId); 
		
		if( count( $brandOwnerCompanyIds ) == 0 )
		{
			// get passive brand owners
			$brandOwnerCompanyIds = Shipserv_BrandAuthorisation::getPassiveBrandOwners($this->brandAuthRequest->brandId, true); 
		}

		//retrieve list of companies - brand owners
		foreach ($brandOwnerCompanyIds as $brandOwnerCompanyId)
		{
			$uColl = $ucDom->fetchUsersForCompany('SPB', $brandOwnerCompanyId);

			foreach ($uColl->getAdminUsers() as $u)
			{
				$row = array(
					'userId' => $u->userId,
					'email' => $u->email,
					'name'	=> $u->firstName.' '.$u->lastName,
					'companyId'	=> $brandOwnerCompanyId
				);
				$res[] = $row;
			}
		}
		
		// retrieve emails specified on the form
		foreach( $this->emails as $email )
		{
			$row = array(
				'email' => $email,
			);
			$res[] = $row;
		
		}
		
		return $res;
	}

	public function getSubject ()
	{
		$companyInfo = $this->brandAuthRequest->getCompanyInfo();
		
		$subject = 'Verification request from ' . $companyInfo["SPB_NAME"];

		if ($this->enableSMTPRelay)
		{
			// group name on JANGOSMTP
			$subject .= "{Brand Owner Invite}";
		}
		return $subject;
		
	}

	/**
	 * Generate url to approve brand authentication
	 * 
	 * @param object $brandAuthRequest
	 */
	public function getUrlToApprove( $brandAuthRequest, $userId, $brandOwnerId )
	{
		// create the hash
		$hash = md5( "action=approve" . "supplierId=".$brandAuthRequest->companyId . "brandId=" . $brandAuthRequest->brandId. "brandOwnerId=" . $brandOwnerId);

		// prepare the parameters
		$parameters = array(
			"a" => "approve",
			"supplierId"=> $brandAuthRequest->companyId,
		   	"brandId" => $brandAuthRequest->brandId,
		   	"brandOwnerId" => $brandOwnerId,
		   	"auth" => $hash
	   	);
		
		// prepare the link to be passed onto the view
		return 'https://' . $_SERVER['HTTP_HOST'] . $this->makeLinkPath('claim-ownership', 'brand-auth', null, $parameters);
	}
	
	/**
	 * Generate url to approve brand authentication
	 * 
	 * @param object $brandAuthRequest
	 */
	public function getUrlToReject( $brandAuthRequest, $userId, $brandOwnerId )
	{
		// create the hash
		$hash = md5( "action=reject" . "supplierId=".$brandAuthRequest->companyId . "brandId=" . $brandAuthRequest->brandId. "brandOwnerId=" . $brandOwnerId);

		// prepare the parameters
		$parameters = array(
			"a" => "reject",
			"supplierId"=> $brandAuthRequest->companyId,
		   	"brandId" => $brandAuthRequest->brandId,
		   	"brandOwnerId" => $brandOwnerId,
		   	"auth" => $hash
	   	);
		
		// prepare the link to be passed onto the view
		return 'https://' . $_SERVER['HTTP_HOST'] . $this->makeLinkPath('claim-ownership', 'brand-auth', null, $parameters);
	}
	/**
	 * @see Myshipserv_NotificationManager_Email_Abstract::getBody()
	 */
	public function getBody ()
	{
		// Fetch e-mail template
		$view = $this->getView();

		$body = array ();

		// send auth/token to approve the brand authentication request to all administrator of the brand owner
		foreach ($this->getRecipients() as $recipient)
		{

			if( $this->getStatisticByBrandId( $this->brandAuthRequest->brandId ) )
			{
				
				if( $recipient["userId"] != "" )
				{
					// prepare url to approve the brand authentication request
					// prepare the adapter for generating autologin token
					// get the tokenId
					$urlToApprove = $this->getUrlToApprove($this->brandAuthRequest, $recipient["userId"], $recipient["companyId"]);
					$tokenForApproval = new Myshipserv_AutoLoginToken($this->db);
					$tokenId = $tokenForApproval->generateToken($recipient["userId"], $urlToApprove, '1 click');
	
					$urlToReject = $this->getUrlToReject($this->brandAuthRequest, $recipient["userId"], $recipient["companyId"]);
					$tokenForRejection = new Myshipserv_AutoLoginToken( $this->db );
					$tokenId = $tokenForRejection->generateToken($recipient["userId"], $urlToReject, '1 click');
					
					// prepare the link to be passed onto the view
					$data = array(
						"linkToApprove" => $tokenForApproval->generateUrlToVerify(),
						"linkToReject" => $tokenForRejection->generateUrlToVerify(),
						"hostname" => $_SERVER["HTTP_HOST"],
						"statistic" => $this->statistic
					);
					
				}
				else
				{
					$data = array(
						"linkToApprove" => "http://" . $_SERVER["HTTP_HOST"] . "/brand-auth/add-brand-owner/brand/" . $this->brandAuthRequest->brandId,
						"linkToReject" => "http://" . $_SERVER["HTTP_HOST"] . "/brand-auth/add-brand-owner/brand/" . $this->brandAuthRequest->brandId,
						"hostname" => $_SERVER["HTTP_HOST"],
						"statistic" => $this->statistic
					);
				}
								
				$view->data = $data;
				$view->brandAuthRequest = $this->brandAuthRequest;
				$view->message = nl2br( $this->message );
				$bodyHtml = $view->render('email/invite-brand-owner-to-authorise-supplier.phtml');
			
				$body[ $recipient["email"] ] = $bodyHtml;
			}
		}
		return $body;
	}
	
	/**
	 * @return string
	 */
	private function makeLinkToBrandsPage ($companyId)
	{
		$params = array('type' => 'v', 'id' => $companyId);
		$link = 'https://' . $_SERVER['HTTP_HOST'] . $this->makeLinkPath('company-brands', 'profile', null, $params)."?brand=".$this->brandAuthRequest->brandId;

		return $link;
	}
	
	private function getUserCompanyDomain ()
	{
		return new Myshipserv_UserCompany_Domain($this->db);
	}

	/**
	 * Get statistic of each brand, and store it on static variable which can be accessed later
	 * 
	 * @param integer $brandId
	 */
	private function getStatisticByBrandId( $brandId )
	{
		if( $this->statistic === null )
		{
			$brandAdapter = new Shipserv_Oracle_Brands( $this->db );
			$supplierAdapter = new Shipserv_Supplier( $this->db );
			$analyticsAdapter = new Shipserv_Oracle_Analytics( $this->db );
			
			// get authentication level by querying DAO
			$this->statistic["authLevel"] = $brandAdapter->fetchAuthLevelAnalytics( $brandId, true);
			
			// get list of random suppliers by query DAO
			$result = $brandAdapter->fetchSuppliers( $brandId, true, 3, true, false);
			
			// get number of search of this brand
			$this->statistic["totalBrandSearch"] = $analyticsAdapter->getTotalSearchOnBrand( $brandId, "3 months" );
			
			// create a unique array which contains supplierId/companyId with their authorisation levels
			foreach( $result as $row )
			{
				if( !isset($suppliersData[ $row["PCB_COMPANY_ID"] ])) 
					$suppliersData[ $row["PCB_COMPANY_ID"] ] = array();
				
				$suppliersData[ $row["PCB_COMPANY_ID"] ] = array_merge( $suppliersData[ $row["PCB_COMPANY_ID"] ], array( $row["PCB_AUTH_LEVEL"]) );
			}
			
			// go through the authorisation levels and process them
			foreach( $suppliersData as $supplierId => $authLevels)
			{
				$supplier =  $supplierAdapter->fetch( $supplierId, $this->db);
				$auth = array();
				
				foreach( $authLevels as $authLevel){
					if( $authLevel != "LST" )
					{
						$auth[] = array(
							"key" => $authLevel,
							"name" => Shipserv_BrandAuthorisation::getAuthLevelNameByKey( $authLevel )
						);
					}
				}
				
				$data = array(
					"name" => $supplier->name,
					"authLevels" => $auth,
					"url" => 'https://' . $_SERVER['HTTP_HOST'] . '/supplier/profile/s/' . preg_replace('/(\W){1,}/', '-', $supplier->name) . '-' . $supplier->tnid
				);
				$this->statistic["suppliers"][] = $data;
			}
		}
		return true;
	}
	
}
?>