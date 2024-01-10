<?php

/**
 * Represents e-mail to external supplier from membership owner
 */
class Myshipserv_NotificationManager_Email_MembershipInviteSupplier extends Myshipserv_NotificationManager_Email_Abstract
{

	private $recipient;
	private $text;
	private $membershipId;
	private $companyId;

	/**
	 *
	 * @param <type> $db
	 * @param Shipserv_User $recipient
	 * @param Shipserv_ReviewRequest $reviewRequest
	 */
	public function __construct ($db, $recipient, $text, $membershipId, $companyId )
	{
		parent::__construct($db);
		$this->recipient = $recipient;
		$this->text = $text;
		$this->membershipId = $membershipId;
		$this->companyId = $companyId;
	}

	public function getRecipients ()
	{
		return array($this->recipient);
	}

	public function getSubject ()
	{
		return 'You are invited to ShipServ Pages';
	}

	public function getBody ()
	{

		// Fetch e-mail template
		$view = $this->getView();

		$view->companyInfo = $this->getCompany("SPB",$this->companyId);
		$view->membershipInfo = $this->getMembershipInfo ();

		$view->link = $this->createLink();

		// Render view and return
		$view->text = $this->text;
		return array($this->recipient["email"] => $view->render('email/memberships-supplier-invite.phtml'));
	}

	public function getMembershipInfo ()
	{
		$membershipDao = new Shipserv_Oracle_Memberships($this->db);
		return $membershipDao->fetch($this->membershipId);
	}

	private function createLink ()
	{
		return 'https://' . $_SERVER['HTTP_HOST'] . '/pages/selfService/main/freeListingLandingPage.jsf';
	}
}


