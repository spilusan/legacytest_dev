<?php

/**
 * Represents e-mail to external supplier from brand owner
 */
class Myshipserv_NotificationManager_Email_BrandInviteSupplier extends Myshipserv_NotificationManager_Email_Abstract
{

	private $recipient;
	private $text;
	private $brandId;
	private $companyId;

	/**
	 *
	 * @param <type> $db
	 * @param Shipserv_User $recipient
	 * @param Shipserv_ReviewRequest $reviewRequest
	 */
	public function __construct ($db, $recipient, $text, $brandId, $companyId )
	{
		parent::__construct($db);
		$this->recipient = $recipient;
		$this->text = $text;
		$this->brandId = $brandId;
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
		$view->brandInfo = $this->getBrandInfo ();
		
		$view->link = $this->createLink();

		// Render view and return
		$view->text = $this->text;
		return array($this->recipient["email"] => $view->render('email/brands-supplier-invite.phtml'));
	}

	public function getBrandInfo ()
	{
		$brandDao = new Shipserv_Oracle_Brands($this->db);
		return $brandDao->fetchBrand($this->brandId);
	}

	private function createLink ()
	{
		return 'https://' . $_SERVER['HTTP_HOST'] . '/pages/selfService/main/freeListingLandingPage.jsf';
	}
}


