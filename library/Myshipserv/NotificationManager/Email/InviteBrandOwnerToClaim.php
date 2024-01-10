<?php
class Myshipserv_NotificationManager_Email_InviteBrandOwnerToClaim extends Myshipserv_NotificationManager_Email_Abstract{
	
   	private $brandAuthRequest;
	private $message;
	private $emails;
	protected static $statistic = null;
	protected static $brandId;
	protected $db;
	
	public function __construct ($db, $brandAuthRequest)
	{
			
		parent::__construct($db);
		
		$this->db = $db;
		
		// enable SMTP relay through jangoSMTP (or any other)
		$this->enableSMTPRelay = true;

		$this->brandAuthRequest = $brandAuthRequest;
		
		
		
	}
	
	public function getRecipients ( $debug = false )
	{
		$res = array();
		$ucDom = $this->getUserCompanyDomain();
		$uColl = $ucDom->fetchUsersForCompany('SPB', $this->brandAuthRequest->companyId);
	
		if( $debug ) echo "CompanyId: " . $this->brandAuthRequest->companyId . "\n";
		if( $debug ) echo "BrandId: " . $this->brandAuthRequest->brandId . "\n";
		
		foreach ($uColl->getAdminUsers() as $u)
		{
				$row = array(
				'userId' => $u->userId,
				'email' => $u->email,
				'name'	=> $u->firstName.' '.$u->lastName,
				'companyId'	=> $brandOwnerCompanyId
			);
			if( $debug ) echo "- sending email to: " . $u->email . "\n";
			$res[] = $row;
		}
		if( $debug ) echo "--------------------------\n";
		
		return $res;
	}

	public function getSubject ()
	{
		$subject = 'You have a listing on ShipServ Pages';
		
		if ($this->enableSMTPRelay)
		{
			// group name on JANGOSMTP
			$subject .= "{Brand Owner Invite To Claim}";
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
		return 'https://' . $_SERVER['HTTP_HOST'] . $this->makeLinkPath('claim-brand-ownership', 'brand-auth', null, $parameters);
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
		foreach ($this->getRecipients( true ) as $recipient)
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

					// prepare the link to be passed onto the view
					$data = array(
						"link" => $tokenForApproval->generateUrlToVerify(),
						"hostname" => $_SERVER["HTTP_HOST"],
						"statistic" => $this->statistic
					);
					
				}
				
				$view->data = $data;
				$view->brandAuthRequest = $this->brandAuthRequest;
				$bodyHtml = $view->render('email/invite-brand-owner-to-claim.phtml');
			
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
		if( $this->brandId != $brandId || $this->statistic === null )
		{
			$this->brandId = $brandId;
			
			$brandAdapter = new Shipserv_Oracle_Brands( $this->db );
			$supplierAdapter = new Shipserv_Supplier( $this->db );
			$analyticsAdapter = new Shipserv_Oracle_Analytics( $this->db );
			
			// get authentication level by querying DAO
			$this->statistic["authLevel"] = $brandAdapter->fetchAuthLevelAnalytics( $brandId, true);

			// get number of search of this brand
			$this->statistic["totalBrandSearch"] = $analyticsAdapter->getTotalSearchOnBrand( $brandId );
			
			// get list of random suppliers by query DAO
			$result = $brandAdapter->fetchSuppliers( $brandId, true, 3, true, false);
			
			// create a unique array which contains supplierId/companyId with their authorisation levels
			foreach( $result as $row )
			{
				if( !isset($suppliersData[ $row["PCB_COMPANY_ID"] ])) 
					$suppliersData[ $row["PCB_COMPANY_ID"] ] = array();
				
				$suppliersData[ $row["PCB_COMPANY_ID"] ] = array_merge( $suppliersData[ $row["PCB_COMPANY_ID"] ]
																		, array( $row["PCB_AUTH_LEVEL"]) );
			}
			
			if( count( $suppliersData ) > 0 )
			{
				// go through the authorisation levels and process them
				foreach( $suppliersData as $supplierId => $authLevels)
				{
					$supplier =  $supplierAdapter->fetch( $supplierId, $this->db);
					$auth = array();
					
					foreach( $authLevels as $authLevel){
						if( $authLevel == "OEM" )
						{
							//die( $supplierId );
						}
						if( $authLevel != "LST" )
						{
							$auth[] = array(
								"key" => $authLevel,
								"name" => Shipserv_BrandAuthorisation::getAuthLevelNameByKey( $authLevel )
							);
						}
					}
					if( $supplier->name != "" )
					{
						$data = array(
							"name" => $supplier->name,
							"authLevels" => $auth,
							"url" => 'https://' . $_SERVER['HTTP_HOST'] . '/supplier/profile/s/' . preg_replace('/(\W){1,}/', '-', $supplier->name) . '-' . $supplier->tnid
						);
						$this->statistic["suppliers"][] = $data;
					}
				}
			}
			else 
			{
				$this->statistic["suppliers"] = array();
			}
		}

		return true;
	}
}
?>