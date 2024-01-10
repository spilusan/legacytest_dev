<?php

/**
 * Represents e-mails to company users on review request 
 */
class Myshipserv_NotificationManager_Email_InviteUnverifiedSupplier extends Myshipserv_NotificationManager_Email_Abstract
{
	const ITS_EMAIL = 'support@shipserv.com';

	private $recipient;
	private $supplier;
	private $analytics;
	private $competitors;

	/**
	 *
	 * @param <type> $db
	 * @param Shipserv_User $recipient
	 * @param Shipserv_ReviewRequest $reviewRequest
	 */
	public function __construct ($db, $recipient, $data )
	{
		parent::__construct($db);
		$this->recipient = $recipient;
		$this->supplier = $data["supplier"];
		$this->analytics = $data["analytics"];
		$this->competitors = $data["competitors"];
		$this->enableSMTPRelay = true;
	}

	public function getRecipients ()
	{
		return array(array(
			"name"	=> ($this->recipient->firstName.' '.$this->recipient->lastName),
			"email" => $this->recipient->email
		));
	}

	public function getSubject ()
	{
		$subject = "Verify your listing with ShipServ Pages!";
		if ($this->enableSMTPRelay)
		{
			$subject .= "{Supplier Listing Verification}";
		}
		return $subject;
	}

	public function getBody ()
	{

		// Fetch e-mail template
		$view = $this->getView();

		// create token to confirm that the listing is correct
		$url = $this->makeLinks("correct");
		$tokenForCorrectListing = new Myshipserv_AutoLoginToken($this->db);
		$tokenForCorrectListing->generateToken($this->recipient->userId, $url, '1 click');
		
		// token to change the listing
		$url = $this->makeLinks("incorrect");
		$tokenForIncorrectListing = new Myshipserv_AutoLoginToken($this->db);
		$tokenForIncorrectListing->generateToken($this->recipient->userId, $url, '1 click');
		
		// Render view and return
		$view->data = array (
			"correctListingLink" => $tokenForCorrectListing->generateUrlToVerify(),	
			"incorrectListingLink" => $tokenForIncorrectListing->generateUrlToVerify(),
			"links"	=> "https://www.shipserv.com",
			"analytics" => $this->analytics,
			"supplier" => $this->supplier,
			"competitors" => $this->competitors,
			"hostname" =>  $_SERVER['HTTP_HOST'], 
			"helper" => new Myshipserv_View_Helper_SupplierProfileUrl()
		);
		
		return array($this->recipient->email => $view->render('email/invite-unverified-supplier.phtml'));
	}

	/**
	 * @return array
	 */
	private function makeLinks ( $mode )
	{
		// send user to update the date
		if( $mode == "correct" )
		{
			$params = array(
				"tnid" => $this->supplier->tnid
			);
			return $this->makeLinkPath('listing-verified', 'supplier', null, $params);
		}
		
		// send user to legacy java page with access code
		else 
		{
			$suppliersAdapter = new Shipserv_Oracle_Suppliers($this->db);
			$result = $suppliersAdapter->fetchAccessCodesByBranchCode( $this->supplier->tnid );
			
			return "http://www.shipserv.com/pages/admin/selfService/access-code-input.jsf?accessCode=" . urlencode( $result[0]["ACCESS_CODE"] );
		}
	}

}


