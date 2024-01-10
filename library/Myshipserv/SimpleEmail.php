<?php
/**
 * A class to send simple emails that require no template (internal notifications etc.)
 *
 * @author  Yuriy Akopov
 * @date    2016-03-23
 */
class Myshipserv_SimpleEmail {
	/**
	 * @var Zend_Mail
	 */
	protected $mail = null;

	/**
	 * Stores email body text before it is sent. The reason is that if we assign to Zend_Mail straight away,
	 * we won't be able to retrieve it as plain text, only MIME encoded
	 *
	 * @var string
	 */
	protected $content = null;

	/**
	 * It true, emails will only be sent to test address in all environments apart from production
	 *
	 * @var bool
	 */
	protected $overrideInTestEnv = true;

	/**
	 * Returns true if supplier array is not a simple sequential one
	 *
	 * @todo: probably belongs to some utilities class
	 *
	 * @param   array   $arr
	 *
	 *
	 * @return  bool
	 */
	protected function _isAssociativeArray(array $arr) {
		return array_keys($arr) !== range(0, count($arr) - 1);
	}

	public function __construct($overrideInTestEnv = true) {
		$this->overrideInTestEnv = $overrideInTestEnv;
		$this->mail = new Zend_Mail();
	}

	/**
	 * Assigns email subject and plain text body
	 *
	 * @param   string  $subject
	 * @param   string  $body
	 */
	public function setSubjectAndBody($subject, $body) {
		$this->setSubject($subject);
		$this->setBody($body);
	}

	/**
	 * @param   string  $subject
	 */
	public function setSubject($subject) {
		$this->mail->setSubject($subject);
	}

	/**
	 * @param string $body
	 */
	public function setBody($body) {
		$this->content = $body;
		$this->mail->setBodyText($this->content);
	}

	/**
	 * @return string
	 */
	public function getSubject() {
		return $this->mail->getSubject();
	}

	/**
	 * Returns text body as plain text
	 *
	 * @return  string
	 */
	public function getBody() {
		return $this->content;
	}

	/**
	 * Adds files from supplied paths as email attachments
	 *
	 * @param   string  $path
	 * @param   string  $mimeType
	 *
	 * @throws  Exception
	 */
	public function addAttachment($path, $mimeType = null) {
		if (!file_exists($path)) {
			throw new Exception("Email attachment file at " . $path . " not found");
		}

		$attachmentContent = file_get_contents($path);
		$attachment = new Zend_Mime_Part($attachmentContent);

		if (!is_null($mimeType)) {
			$attachment->type = $mimeType;
		}

		$attachment->disposition = Zend_Mime::DISPOSITION_ATTACHMENT;
		$attachment->filename = basename($path);

		$this->mail->addAttachment($attachment);
	}

	/**
	 * Sends an email
	 *
	 * @param   array|string    $recipients
	 * @param   string          $name
	 *
	 * @throws  Exception
	 */
	public function send($recipients, $name = null) {
		if (!is_array($recipients)) {
			if (is_null($name)) {
				$recipients = array($recipients);
				$expectNames = false;
			} else {
				$recipients = array($recipients => $name);
				$expectNames = true;
			}
		} else {
			if (!is_null($name)) {
				throw new Exception("Ambiguous recipient parameters when sending an email (a list of email but one name)");
			}
			$expectNames = $this->_isAssociativeArray($recipients);
		}

		$emailAddresses = array();
		$recipientNames = array();
		foreach ($recipients as $index => $value) {
			if ($expectNames) {
				$emailAddresses[] = $index;
				$recipientNames[] = $value;
			} else {
				$emailAddresses[] = $value;
			}
		}

		$this->mail->clearRecipients();

		if ($this->overrideInTestEnv and (!Myshipserv_Config::isInProduction())) {
			// @todo: should be read from config after merged into 2016-03-01 release where such config entry is introduced
			$this->mail->addTo("test@shipserv.com", "Replaced in test environment");

			$bodyPrefix = "Originally intended for:\n";

			foreach ($emailAddresses as $index => $toAddress) {
				if ($expectNames) {
					$bodyPrefix .= $toAddress . " <" . $recipientNames[$index] . ">\n";
				} else {
					$bodyPrefix .= $toAddress . "\n";
				}
			}

			$this->setBody($bodyPrefix . "---\n" . $this->getBody());
		} else {
			foreach ($emailAddresses as $index => $toAddress) {
				if (!filter_var($toAddress, FILTER_VALIDATE_EMAIL)) {
					throw new Exception("Email address " . $toAddress . " does not seem valid");
				}

				if ($expectNames) {
					$this->mail->addTo($toAddress, $recipientNames[$index]);
				} else {
					$this->mail->addTo($toAddress);
				}
			}
		}

		$this->mail->send();
	}
}