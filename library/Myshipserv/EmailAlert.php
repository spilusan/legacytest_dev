<?php
/**
 * Insert record into Email alert email_alert_queue
 * @author attilaolbrich
 * Story when implemented S18391, 17/01/2017
 */

class Myshipserv_EmailAlert
{
	protected $record;
	const ALERT_MODE_HTML = 'HTML';
	const ALERT_MODE_TEXT = 'TEXT';
	
	/**
	 * Constructor to set initial values
	 */
	public function __construct()
	{
		//Initialise records
		$this->resetValues();
	}
	
	/**
	 * Reset the email alert to it's default
	 */
	public function resetValues()
	{
		$this->record = array(
				'eaq_internal_ref_no' => 0,
				'eaq_spb_branch_code' => null,
				'eaq_alert_type' => null,
				'eaq_reminder' => null,
				'eaq_to' => null,
				'eaq_cc' => null
		);
	}
	
	//Setters
	/**
	 * Set the Internal Ref No
	 * @param integer $internalRefNo
	 * @return unknown
	 */
	public function setInternalRefNo($internalRefNo)
	{
		$this->record['eaq_internal_ref_no'] = (int)$internalRefNo;
	}
	
	/**
	 * Set the Internal Ref No
	 * @param integer $spbBranchCode
	 * @return unknown
	 */
	public function setSpbBranchCode($spbBranchCode)
	{
		$this->record['eaq_spb_branch_code'] = (int)$spbBranchCode;
	}
	
	/**
	 * Set the Alert Type
	 * @param string $alertType
	 * @return unknown
	 */
	public function setAlertType($alertType)
	{
		$this->record['eaq_alert_type'] = (string)$alertType;
	}
	
	/**
	 * Set the recepient Email
	 * @param string $email
	 * @return unknown
	 */
	public function setTo($email)
	{
		
		$this->record['eaq_to'] = (string)$email;
	}
	
	/**
	 * Set CC recepient Email
	 * @param string $email
	 * @return unknown
	 */
	public function setCc($email)
	{
		$this->record['eaq_cc'] = (string)$email;
	}
	
	/**
	 * Set HTML Mode on, Default is ON
	 * @param bool $isHtml
	 * @return unknown
	 */
	public function setHtmlMode($isHtml = true)
	{
		$this->record['eaq_reminder'] = ($isHtml === true) ? self::ALERT_MODE_HTML : self::ALERT_MODE_TEXT;
	}
	
	/**
	 * Inserting the record for sending out the email into email alert queue
	 * @param boolean $resetAfterSent Reset the email alert content after sending out
	 * @throws Exception
	 */
	public function send($resetAfterSent = true)
	{
		//Validating 
		if ($this->record['eaq_alert_type'] === null) {
			throw new Exception("for inserting record into email_alert_queue Alert type is required");
		}

		//setting the ID
		$this->record['eaq_id'] = new Zend_Db_Expr('sq_email_alert_queue.nextval');

		//Inserting the actual record
		$db = Shipserv_Helper_Database::getDb();
		$db->insert('email_alert_queue', $this->record);
		$db->commit();
		
		if ($resetAfterSent) {
			$this->resetValues();
		}
	}
}