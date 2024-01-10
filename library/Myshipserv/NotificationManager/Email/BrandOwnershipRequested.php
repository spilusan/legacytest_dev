<?php

/**
 * Represents e-mail to external supplier from brand owner
 */
class Myshipserv_NotificationManager_Email_BrandOwnershipRequested extends Myshipserv_NotificationManager_Email_Abstract
{

	private $params;

	/**
	 *
	 * @param <type> $db
	 * @param Shipserv_User $recipient
	 * @param Shipserv_ReviewRequest $reviewRequest
	 */
	public function __construct ($db, $params)
	{
		parent::__construct($db);
		$this->params = $params;
	}

	public function getRecipients ()
	{
		$row = array();
		$row['email'] = "support@shipserv.com";
		$row['name'] = "ShipServ Pages";
		
		return array($row);
	}

	public function getSubject ()
	{
		return 'Brand ownership requested';
	}

	public function getBody ()
	{

		// Fetch e-mail template
		$view = $this->getView();
		
		$view->details = $this->params;
		
		$recipients = $this->getRecipients ();
	
		return array($recipients[0]["email"] => $view->render('email/brand-ownership-requested.phtml'));
	}

}


?>
