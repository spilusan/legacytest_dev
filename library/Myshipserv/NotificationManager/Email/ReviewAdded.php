<?php

class Myshipserv_NotificationManager_Email_ReviewAdded extends Myshipserv_NotificationManager_Email_Abstract
{

	private $review;

	public function __construct ($db, $review)
	{
		parent::__construct($db);
		$this->review = $review;
		$this->enableSMTPRelay = true;

	}

	public function getRecipients ()
	{
		$ucDom = $this->getUserCompanyDomain();
		$uColl = $ucDom->fetchUsersForCompany('SPB', $this->review->endorseeId);

		$res = array();
		foreach ($uColl->getAdminUsers() as $u)
		{
			$row = array();
			$row['email'] = $u->email;
			$row['name'] = $u->firstName.' '.$u->lastName;

			$res[] = $row;
		}

		return $res;
	}

	public function getSubject ()
	{
		$subject = 'You have a new review on ShipServ Pages';
		
		if ($this->enableSMTPRelay)
		{
			$subject .= "{Review Added}";
		}
		return $subject;
		
		
	}

	public function getBody ()
	{
		// Fetch e-mail template
		$view = $this->getView();

		$data = array(
			"link" => $this->makeLinkToReviewsPage()
		);
		$view->data = $data;
		$view->review = $this->review;

		$bodyHtml = $view->render('email/review-added.phtml');

		$body = array ();

		foreach ($this->getRecipients() as $recipient)
		{
			$body[$recipient["email"]] = $bodyHtml;
		}

		return $body;
	}

	/**
	 * @return array
	 */
	private function makeLinkToReviewsPage ()
	{
		$params = array('s' => $this->review->endorseeId, 'e' => $this->review->endorserId);
		$link = 'https://' . $_SERVER['HTTP_HOST'] . $this->makeLinkPath('all', 'reviews', null, $params);

		return $link;
	}

	private function getUserCompanyDomain ()
	{
		return new Myshipserv_UserCompany_Domain($this->db);
	}
}

