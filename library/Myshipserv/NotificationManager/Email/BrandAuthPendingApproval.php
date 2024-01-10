<?php
class Myshipserv_NotificationManager_Email_BrandAuthPendingApproval  extends Myshipserv_NotificationManager_Email_Abstract{

   	private $brandAuthRequest;
   	private $brandOwnerId;

	public function __construct ($db, $brandAuthRequest, $brandOwnerId)
	{
		parent::__construct($db);
		$this->brandAuthRequest = $brandAuthRequest;
		$this->brandOwnerId = $brandOwnerId;

	}

	public function getRecipients ()
	{
		$res = array();
		$ucDom = $this->getUserCompanyDomain();

		$uColl = $ucDom->fetchUsersForCompany('SPB', $this->brandAuthRequest->companyId);

		foreach ($uColl->getAdminUsers() as $u)
		{
			$row = array(
				'email' => $u->email,
				'name'	=> $u->firstName.' '.$u->lastName,
				'companyId'	=> $this->brandAuthRequest->companyId
			);
			$res[] = $row;
		}
		

		return $res;
	}

	public function getSubject ()
	{
		return 'Your brand authorisation is pending approval on ShipServ Pages';
	}

	public function getBody ()
	{
		// Fetch e-mail template
		$view = $this->getView();

		$body = array ();

		foreach ($this->getRecipients() as $recipient)
		{
			$data = array(
				"link" => $this->makeLinkToBrandsPage($recipient["companyId"]),
				"company" => $this->getCompany("SPB", $this->brandOwnerId)
			);
			$view->data = $data;
			$view->brandAuthRequest = $this->brandAuthRequest;
			$bodyHtml = $view->render('email/pending-approval-brand-auth.phtml');
			$body[$recipient["email"]] = $bodyHtml;
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

}
?>
