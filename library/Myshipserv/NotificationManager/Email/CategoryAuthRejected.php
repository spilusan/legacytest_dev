<?php
class Myshipserv_NotificationManager_Email_CategoryAuthRejected  extends Myshipserv_NotificationManager_Email_Abstract{

   	private $requests;

	public function __construct ($db, $requests)
	{
		parent::__construct($db);
		$this->requests = $requests;

	}

	public function getRecipients ()
	{
		$res = array();
		$ucDom = $this->getUserCompanyDomain();


		$uColl = $ucDom->fetchUsersForCompany('SPB', $this->requests[0]->companyId);

		foreach ($uColl->getAdminUsers() as $u)
		{
			$row = array(
				'email' => $u->email,
				'name'	=> $u->firstName.' '.$u->lastName
			);
			$res[] = $row;
		}


		return $res;
	}

	public function getSubject ()
	{
		return 'ShipServ Pages: category listing request rejected';
	}

	public function getBody ()
	{
		// Fetch e-mail template
		$view = $this->getView();

		$view->requests = $this->requests;
		$bodyHtml = $view->render('email/reject-category-auth.phtml');

		$body = array ();

		foreach ($this->getRecipients() as $recipient)
		{
			$body[$recipient["email"]] = $bodyHtml;
		}

		return $body;
	}


	private function getUserCompanyDomain ()
	{
		return new Myshipserv_UserCompany_Domain($this->db);
	}

}
?>
