<?php
class Myshipserv_NotificationManager_Email_BrandAuthRequested  extends Myshipserv_NotificationManager_Email_Abstract{
	
   	private $brandAuthRequest;

	public function __construct ($db, $brandAuthRequest)
	{
		parent::__construct($db);
		$this->brandAuthRequest = $brandAuthRequest;

	}

	public function getRecipients ()
	{
		$res = array();
		$ucDom = $this->getUserCompanyDomain();

		//retrieve list of companies - brand owners
		foreach (Shipserv_BrandAuthorisation::getBrandOwners($this->brandAuthRequest->brandId) as $brandOwnerCompanyId)
		{
			$uColl = $ucDom->fetchUsersForCompany('SPB', $brandOwnerCompanyId);

			foreach ($uColl->getAdminUsers() as $u)
			{
				$row = array(
					'email' => $u->email,
					'name'	=> $u->firstName.' '.$u->lastName,
					'companyId'	=> $brandOwnerCompanyId
				);
				$res[] = $row;
			}
		}

		return $res;
	}

	public function getSubject ()
	{
		return 'You have new brand authorisation request on ShipServ Pages';
	}

	public function getBody ()
	{
		// Fetch e-mail template
		$view = $this->getView();

		$body = array ();

		foreach ($this->getRecipients() as $recipient)
		{
			$data = array(
				"link" => $this->makeLinkToBrandsPage($recipient["companyId"])
			);
			$view->data = $data;
			$view->brandAuthRequest = $this->brandAuthRequest;
			$bodyHtml = $view->render('email/request-brand-auth.phtml');
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
