<?php
class Myshipserv_NotificationManager_Email_CompanyApprovedSuppliers extends Myshipserv_NotificationManager_Email_Abstract{

	public $recepientsList;
	private $message;
	private $emails;
	protected $db;
	protected $orgCode;
	protected $branchCodes;


	/**
	* On initalize, set the db, and the organization code
	* @param object $db database object
	* @param integer $orgCode Organization code
	* @param array $branchCodes Suppier branch codes 
	* @return object instance
	*/

	 public function __construct ($db, $branchCodes, $orgCode)
	{
		parent::__construct($db);

		$this->db = $db;
		$this->branchCodes = $branchCodes;
		$this->orgCode = $orgCode;

		// enable SMTP relay through jangoSMTP (or any other)
		$this->enableSMTPRelay = false;
		$this->getRecepientsFromDb( $orgCode );

	}

	/**
	* Load the list of email addresses relating to this buyer org, and was set at approved suppliers page form the database.
	* @param integer $orgCode, the organization code
	*/
	protected function getRecepientsFromDb( $orgCode )
	{
		$this->db = $this->getDb();
		$sql = "
			SELECT
  				bsm_mail
			FROM
  				buyer_supplier_mailsappr
			WHERE 
  				bsm_byo_org_code = :orgCode";

		$params = array(
			'orgCode' => (int)$orgCode,
		);
        $resultSet = $this->db->fetchAll($sql, $params);
        $this->recepientsList = $resultSet;
	}

	/**
	* Convert the refepients, fetced above from the database to a list of Myshipserv_NotificationManager_Recipient object
	* @return array Array of Myshipserv_NotificationManager_Recipient instances
	*/
	public function getRecipients ()
	{

		$recipient = new Myshipserv_NotificationManager_Recipient;
		$recipientStorage = array();
		foreach ($this->recepientsList as $recepient) {
			$recipientStorage['TO'][] = array("name" => $recepient['BSM_MAIL'], "email" => $recepient['BSM_MAIL']);
		}
       
       $recipient->list = $recipientStorage;
       return $recipient;
	}

	/**
	*	@return string, the email subject
	*/
	public function getSubject ()
	{
		return 'ALERT: Transaction sent to non-approved vendor';
	}

	/**
	* Renders the current email template, and returns
	* @return array,  rendered email body to send
	*
	*/
	public function getBody ()
	{
		$recipients = $this->getRecipients();
		$view = $this->getView();
		
		// Render view and return
		$hostName = $this->getHostname();
		$docType = ($this->branchCodes['DOCTYPE'] == 'RFQ') ? 'rfq' : 'ord';
		$printable = "http://".$hostName."/user/printable?d=".$docType."&id=" . $this->branchCodes['RFQ_INTERNAL_REF_NO'] . "&h=" . md5('rfq' . $this->branchCodes['RFQ_INTERNAL_REF_NO']);

		$view->data = array (
			'branchCodes' => $this->branchCodes,
			'orgCode' => $this->orgCode,
			'buyer'	=>	$this->buyer,
			'supplier' => $this->supplier,
			'printable' => $printable,
			'hostName' => $hostName,
		);

        $body = $view->render('email/company-approved-suppliers.phtml');
        $emails[$recipients->getHash()] = $body;
        return $emails;
		//return array($this->recipient->email => $body);
	}

	public function getHostname()
	{
		if ($_SERVER['APPLICATION_ENV'] == "production" || $_SERVER['APPLICATION_ENV'] == "development-production-data" || $_SERVER['APPLICATION_ENV'] == "ukdev5")
		{
			$hostname = "www.shipserv.com";
		}
		else if ( $_SERVER['APPLICATION_ENV'] == "testing" )
		{
			$hostname = "test.shipserv.com";
		}
		else if ( $_SERVER['APPLICATION_ENV'] == "test2" )
		{
			$hostname = "test2.shipserv.com";
		}
		else
		{
			$hostname = "ukdev.shipserv.com";
		}
		return $hostname;
	}

}

