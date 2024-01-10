<?php

/**
 * Represents e-mails to company users on review request 
 */
class Myshipserv_NotificationManager_Email_RequestReview extends Myshipserv_NotificationManager_Email_Abstract
{
	const ITS_EMAIL = 'support@shipserv.com';

	private $recipient;
	private $reviewRequest;

	/**
	 *
	 * @param <type> $db
	 * @param Shipserv_User $recipient
	 * @param Shipserv_ReviewRequest $reviewRequest
	 */
	public function __construct ($db, $recipient, $reviewRequest )
	{
		parent::__construct($db);
		$this->recipient = $recipient;
		$this->reviewRequest = $reviewRequest;
		$this->enableSMTPRelay = true;
	}

	public function getRecipients ()
	{
		return array($this->recipient);
	}

	public function getSubject ()
	{
		$endorseeInfo = $this->reviewRequest->getEndorseeInfo();
		$subject = 'Please provide feedback on  '. $endorseeInfo["SPB_NAME"];
		if ($this->enableSMTPRelay)
		{
			$subject .= "{Review Request}";
		}
		return $subject;
	}

	public function getBody ()
	{

		// Fetch e-mail template
		$view = $this->getView();

		$data = array (
			"links"	=> $this->makeRequestLinks(),
			"ignoreLink" => $this->makeIgnoreLink()
		);


		// Render view and return
		$view->data = $data;
		$view->reviewRequest = $this->reviewRequest;
		return array($this->recipient["email"] => $view->render('email/request-review.phtml'));
	}

	/**
	 * @return array
	 */
	private function makeRequestLinks ()
	{
		$params = array(
			'reqcode'			=> $this->reviewRequest->code,
			'oi'	=> "1"
		);
		$relUrlArr['positive'] = $this->makeLinkPath('add-review', 'reviews', null, $params);

		$params = array(
			'reqcode'			=> $this->reviewRequest->code,
			'oi'	=> "0"
		);
		$relUrlArr['neutral'] = $this->makeLinkPath('add-review', 'reviews', null, $params);

		$params = array(
			'reqcode'			=> $this->reviewRequest->code,
			'oi'	=> "-1"
		);
		$relUrlArr['negative'] = $this->makeLinkPath('add-review', 'reviews', null, $params);

		$urlArr = array();
		foreach ($relUrlArr as $k => $ru) $urlArr[$k] = 'https://' . $_SERVER['HTTP_HOST'] . $ru;
		return $urlArr;
	}

	private function makeIgnoreLink ()
	{
		$params = array(
			'reqcode'			=> $this->reviewRequest->code,
		);
		return  'https://' . $_SERVER['HTTP_HOST'] . $this->makeLinkPath('invalid-request', 'reviews', null, $params);

	}

}


