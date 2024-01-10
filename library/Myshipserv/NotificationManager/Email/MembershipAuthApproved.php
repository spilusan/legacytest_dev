<?php

class Myshipserv_NotificationManager_Email_MembershipAuthApproved  extends Myshipserv_NotificationManager_Email_Abstract{

   	private $auths;

	public function __construct ($db, $auths)
	{
		parent::__construct($db);
		$this->auths = $auths;

	}

	public function getRecipients ()
	{
		$res = array();
		$ucDom = $this->getUserCompanyDomain();


		$uColl = $ucDom->fetchUsersForCompany('SPB', $this->auths[0]->companyId);

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
		return 'Your membership authorisation request was approved';
	}

	public function getBody ()
	{
		// Fetch e-mail template
		$view = $this->getView();

		$view->auths = $this->auths;
		$bodyHtml = $view->render('email/approved-membership-auth.phtml');

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
