<?php

/**
* This class stores the form data for Company Automatic Remidner
*/
class Shipserv_Profile_CompanyAutomaticReminder extends Shipserv_Oracle
{
	public $bbsRmdrRfqIsEnabled;
	public $bbsRmdrOrdIsEnabled;
	public $bbsRmdrRfqRepIsEnabled; 
	public $bbsRmdrOrdRepIsEnabled; 
	public $bbsRmdrGetCopy; 
	public $bbsRmdrIncludeMatch; 
	public $bbsRmdrRfqSendAfter; 
	public $bbsRmdrRfqRepeatAfter; 
	public $bbsRmdrRfqRepeat; 
	public $bbsRmdrOrdSendAfter;
	public $bbsRmdrOrdRepeatAfter; 
	public $bbsRmdrOrdRepeat; 

	const	RFQ_RMD = 'RFQ_RMD',
			ORD_RMD = 'ORD_RMD';

	/*
	* Set initiaL status
	*/
   public function __construct()
	{
		//set the reminder data to empty initial values
		$this->setRemiderData(false);
		parent::__construct($reportDb);
		//$reportDb = $this->getSsreport2Db();
		//$this->db  = $reportDb;

	}
	/**
	* Save the reminder form data for the supplied TNID, The data have to be set as above
	* @params integet $bybBranchCode Branch code
	* Other form variables have to be set as you can see in the public properties above
	*/
	public function saveReminder( $bybBranchCode, $userId = 0 )
	{
		$validityArray = $this->validateParams();

		if (count($validityArray) > 0) {
			//the parameters are not valid, raise exception
			throw new Exception("Automatic reminder form data not valid!");
		}

		//Update email config tables
		if ($this->bbsRmdrGetCopy) {
			$this->updateEmailAlertConfig( $bybBranchCode, self::RFQ_RMD, $this->bbsRmdrRfqIsEnabled);
			$this->updateEmailAlertConfig( $bybBranchCode, self::ORD_RMD, $this->bbsRmdrOrdIsEnabled);
		} else {
			$this->updateEmailAlertConfig( $bybBranchCode, self::RFQ_RMD, false);
			$this->updateEmailAlertConfig( $bybBranchCode, self::ORD_RMD, false);
		}

		if ($this->reminderExists($bybBranchCode)) {
			//if the record already exist, we update it
			$this->updateReminder($bybBranchCode, $userId);
		} else {
			//if the record does not exists, insert it to the database
			$this->insertReminder($bybBranchCode, $userId);
		}

	}

	/**
	*	Load the reminder
	*	@return array of the reminder, or false if reminder does not exists 
	*/
	public function loadReminder( $bybBranchCode ) {
		$sql = 'SELECT 
					bbs_rmdr_rfq_is_enabled,
					bbs_rmdr_ord_is_enabled,
					bbs_rmdr_rfq_rep_is_enabled, 
					bbs_rmdr_ord_rep_is_enabled,
					bbs_rmdr_get_copy,
					bbs_rmdr_include_match, 
					bbs_rmdr_rfq_send_after,
					bbs_rmdr_rfq_repeat_after,
					bbs_rmdr_rfq_repeat,
					bbs_rmdr_ord_send_after, 
					bbs_rmdr_ord_repeat_after, 
					bbs_rmdr_ord_repeat, 
					bbs_date_created,
					bbs_date_updated
				FROM
					buyer_branch_setting
				WHERE
					bbs_byb_branch_code = :bybBranchCode';


			$rows = $this->db->fetchAll($sql, array(
					'bybBranchCode' => (int) $bybBranchCode,
					));

			if ($rows) {
				 $res = $rows[0]; //return the remidner
			} else {
				$res = false; //return false, if the reminder record was empty
			}
			$this->setRemiderData($res);
			return $res;

	}

	/**
	*	Set the class public propertyes according to the form values
	*	@param array $formParams, post values
	*/
	public function setFormParams( $formParams )
	{

			//checkbox values

			$this->bbsRmdrRfqIsEnabled = array_key_exists('bbs_rmdr_rfq_is_enabled', $formParams);
			$this->bbsRmdrOrdIsEnabled = array_key_exists('bbs_rmdr_ord_is_enabled', $formParams);
			$this->bbsRmdrRfqRepIsEnabled =  array_key_exists('bbs_rmdr_rfq_rep_is_enabled', $formParams); 
			$this->bbsRmdrOrdRepIsEnabled = array_key_exists('bbs_rmdr_ord_rep_is_enabled', $formParams); 
			$this->bbsRmdrGetCopy = array_key_exists('bbs_rmdr_get_copy', $formParams); 
			$this->bbsRmdrIncludeMatch = array_key_exists('bbs_rmdr_include_match', $formParams); 
			//input field values
			$this->bbsRmdrRfqSendAfter = (array_key_exists('bbs_rmdr_rfq_send_after', $formParams)) ? $formParams['bbs_rmdr_rfq_send_after'] : null; 
			$this->bbsRmdrRfqRepeatAfter = (array_key_exists('bbs_rmdr_rfq_repeat_after', $formParams)) ? $formParams['bbs_rmdr_rfq_repeat_after'] : null; 
			$this->bbsRmdrRfqRepeat = (array_key_exists('bbs_rmdr_rfq_repeat', $formParams)) ? $formParams['bbs_rmdr_rfq_repeat'] : null; 
			$this->bbsRmdrOrdSendAfter = (array_key_exists('bbs_rmdr_ord_send_after', $formParams)) ? $formParams['bbs_rmdr_ord_send_after'] : null;
			$this->bbsRmdrOrdRepeatAfter = (array_key_exists('bbs_rmdr_ord_repeat_after', $formParams)) ? $formParams['bbs_rmdr_ord_repeat_after'] : null; 
			$this->bbsRmdrOrdRepeat = (array_key_exists('bbs_rmdr_ord_repeat', $formParams)) ? $formParams['bbs_rmdr_ord_repeat'] : null; 

	}

	/**
	*	Backend validation of the data
	*	@return array, associative array of validation errors, or empty arra, then  valid
	*/
	public function validateParams()
	{
			$errors = array();

			if ($this->bbsRmdrRfqIsEnabled)
			{
				if ($this->bbsRmdrRfqSendAfter == null || $this->bbsRmdrRfqSendAfter == '')
				{
					//Send first reminder after RFQ
					$errors['bbsRmdrRfqSendAfter'] = 'Required';
				}


				if ($this->bbsRmdrRfqRepIsEnabled)
				{

					if ($this->bbsRmdrRfqRepeatAfter == null || $this->bbsRmdrRfqRepeatAfter == '') {
						$errors['bbsRmdrRfqRepeatAfter'] = 'Required';
					}
					
					if ($this->bbsRmdrRfqRepeat == null || $this->bbsRmdrRfqRepeat == '') {
						$errors['bbsRmdrRfqRepeat'] = 'Required';
					} 

				}
			}

			if ($this->bbsRmdrOrdIsEnabled)
			{

				if ($this->bbsRmdrOrdSendAfter == null || $this->bbsRmdrOrdSendAfter == '') {
					// Send first reminder after PO
					$errors['bbsRmdrOrdSendAfter'] = 'Required';
				}

				if ($this->bbsRmdrOrdRepIsEnabled)
				{

					if ($this->bbsRmdrOrdRepeatAfter == null || $this->bbsRmdrOrdRepeatAfter == '') {
						$errors['bbsRmdrOrdRepeatAfter'] = 'Required';
					}

					if ($this->bbsRmdrOrdRepeat == null || $this->bbsRmdrOrdRepeat == '') {
						$errors['bbsRmdrOrdRepeat'] = 'Required';
					}
				}
			}
			return $errors;
	}

	/**
	* Validate field, 
	* @param string $fieldName The mame pf the fiels
	* @return bool, or string. If true, valid, if not, the error message as string
	*/
	public function isFieldInvalid( $fieldName ) {
		$validParams = $this->validateParams();
		if (array_key_exists($fieldName, $validParams)) {
			return $validParams[$fieldName];
		} else {
			return false;
		}

	}

	/**
	* Set the remider data according to the database record, if no record, it resets
	* @param array $remiderRecord, the fetched record from the database, or it can be false, then the properties will be reseted to their initial state
	*/
	protected function setRemiderData( $remiderRecord )
	{
		if ($remiderRecord == false)
		{
			//if remider data does not exist, reset remider datas
			$this->bbsRmdrRfqIsEnabled = false;
			$this->bbsRmdrOrdIsEnabled = false;
			$this->bbsRmdrRfqRepIsEnabled =  false; 
			$this->bbsRmdrOrdRepIsEnabled = false; 
			$this->bbsRmdrGetCopy = false; 
			$this->bbsRmdrIncludeMatch = false; 

			$this->bbsRmdrRfqSendAfter = null; 
			$this->bbsRmdrRfqRepeatAfter = null; 
			$this->bbsRmdrRfqRepeat = null; 
			$this->bbsRmdrOrdSendAfter = null;
			$this->bbsRmdrOrdRepeatAfter = null; 
			$this->bbsRmdrOrdRepeat = null; 
		} else {
			//if reminder data exist, set the properites
			$this->bbsRmdrRfqIsEnabled = ($remiderRecord['BBS_RMDR_RFQ_IS_ENABLED'] == 1);
			$this->bbsRmdrOrdIsEnabled = ($remiderRecord['BBS_RMDR_ORD_IS_ENABLED'] == 1);
			$this->bbsRmdrRfqRepIsEnabled =  ($remiderRecord['BBS_RMDR_RFQ_REP_IS_ENABLED'] == 1); 
			$this->bbsRmdrOrdRepIsEnabled = ($remiderRecord['BBS_RMDR_ORD_REP_IS_ENABLED'] == 1); 
			$this->bbsRmdrGetCopy = ($remiderRecord['BBS_RMDR_GET_COPY'] == 1); 
			$this->bbsRmdrIncludeMatch = ($remiderRecord['BBS_RMDR_INCLUDE_MATCH'] == 1); 

			$this->bbsRmdrRfqSendAfter = $remiderRecord['BBS_RMDR_RFQ_SEND_AFTER']; 
			$this->bbsRmdrRfqRepeatAfter = $remiderRecord['BBS_RMDR_RFQ_REPEAT_AFTER']; 
			$this->bbsRmdrRfqRepeat = $remiderRecord['BBS_RMDR_RFQ_REPEAT']; 
			$this->bbsRmdrOrdSendAfter = $remiderRecord['BBS_RMDR_ORD_SEND_AFTER'];
			$this->bbsRmdrOrdRepeatAfter = $remiderRecord['BBS_RMDR_ORD_REPEAT_AFTER']; 
			$this->bbsRmdrOrdRepeat = $remiderRecord['BBS_RMDR_ORD_REPEAT']; 
		}
	}

	/**
	* Update the reminder, if the record exists. Please see the public properties above
	* @param integer $bybBranchCode, the branch code
	*/
	protected function updateReminder( $bybBranchCode, $userId )
	{
				$sql = 'UPDATE 
							buyer_branch_setting
						SET
							bbs_rmdr_rfq_is_enabled = :bbsRmdrRfqIsEnabled,
							bbs_rmdr_ord_is_enabled  = :bbsRmdrOrdIsEnabled,
							bbs_rmdr_rfq_rep_is_enabled  = :bbsRmdrRfqRepIsEnabled,
							bbs_rmdr_ord_rep_is_enabled = :bbsRmdrOrdRepIsEnabled,
							bbs_rmdr_get_copy = :bbsRmdrGetCopy,
							bbs_rmdr_include_match = :bbsRmdrIncludeMatch, 
							bbs_rmdr_rfq_send_after = :bbsRmdrRfqSendAfter,
							bbs_rmdr_rfq_repeat_after = :bbsRmdrRfqRepeatAfter,
							bbs_rmdr_rfq_repeat = :bbsRmdrRfqRepeat, 
							bbs_rmdr_ord_send_after = :bbsRmdrOrdSendAfter, 
							bbs_rmdr_ord_repeat_after = :bbsRmdrOrdRepeatAfter,
							bbs_rmdr_ord_repeat = :bbsRmdrOrdRepeat,
							bbs_psu_id = :bbsPsuId
						WHERE 
							bbs_byb_branch_code = :bybBranchCode
							';

						//update the actual record
						return $this->db->query($sql, $this->getBuyerParamArray($bybBranchCode, $userId));
	}

	/**
	*	Insert the reminider to the database, see the public properties at the top of the class
	*	@param the Branch code 
	*/
	protected function insertReminder( $bybBranchCode, $userId )
	{
		$sql = 'INSERT INTO
					buyer_branch_setting
				(
					bbs_byb_branch_code,
					bbs_rmdr_rfq_is_enabled, 
					bbs_rmdr_ord_is_enabled,
					bbs_rmdr_rfq_rep_is_enabled , 
					bbs_rmdr_ord_rep_is_enabled, 
					bbs_rmdr_get_copy, 
					bbs_rmdr_include_match, 
					bbs_rmdr_rfq_send_after, 
					bbs_rmdr_rfq_repeat_after, 
					bbs_rmdr_rfq_repeat, 
					bbs_rmdr_ord_send_after,
					bbs_rmdr_ord_repeat_after, 
					bbs_rmdr_ord_repeat,
					bbs_psu_id
				)
				VALUES
				(
					:bybBranchCode,
					:bbsRmdrRfqIsEnabled,
					:bbsRmdrOrdIsEnabled,
					:bbsRmdrRfqRepIsEnabled, 
					:bbsRmdrOrdRepIsEnabled, 
					:bbsRmdrGetCopy, 
					:bbsRmdrIncludeMatch, 
					:bbsRmdrRfqSendAfter, 
					:bbsRmdrRfqRepeatAfter, 
					:bbsRmdrRfqRepeat, 
					:bbsRmdrOrdSendAfter,
					:bbsRmdrOrdRepeatAfter, 
					:bbsRmdrOrdRepeat,
					:bbsPsuId 
				)';
			
			//insert reminder to the database
			return $this->db->query($sql, $this->getBuyerParamArray($bybBranchCode, $userId));

	}

	/**
	* Check if the record already exists for this reminder
	* @param integer $bybBranchCode
	* @return bool, ture or false
	*/
	protected function reminderExists( $bybBranchCode )
	{
			$sql = 'SELECT
						count(bbs_byb_branch_code) reminder_exists
					FROM
						buyer_branch_setting
					WHERE
						bbs_byb_branch_code = :bybBranchCode
					';

			$rows = $this->db->fetchAll($sql, array(
					'bybBranchCode' => (int)$bybBranchCode,
					));

			if ($rows) {
				//If the REMINDER_EXISTS value is not 0 then return false
				$res = !$rows[0]['REMINDER_EXISTS'] == '0';
			} else {
				//if we could not even fetch the row, return false (likely never occures)
				$res = false;
			}

		return $res;
	}

	/**
	*	Converts an array for the query, for the prepared statements, Insert and Update also use the same param list.
	* 	@param integer $bybBranchCode, and please see the public properties of the class for further data 
	* 	@return array, array key, value pairs for the database table prepared queries
	*/
	protected function getBuyerParamArray( $bybBranchCode, $userId )
	{
		//convert the public properties to an array
		return  array(
			'bbsRmdrRfqIsEnabled' => $this->bbsRmdrRfqIsEnabled,
			'bbsRmdrOrdIsEnabled' => $this->bbsRmdrOrdIsEnabled,
			'bbsRmdrRfqRepIsEnabled' => $this->bbsRmdrRfqRepIsEnabled,
			'bbsRmdrOrdRepIsEnabled' => $this->bbsRmdrOrdRepIsEnabled,
			'bbsRmdrGetCopy' => $this->bbsRmdrGetCopy,
			'bbsRmdrIncludeMatch' => $this->bbsRmdrIncludeMatch,
			'bbsRmdrRfqSendAfter' => $this->bbsRmdrRfqSendAfter,
			'bbsRmdrRfqRepeatAfter' => $this->bbsRmdrRfqRepeatAfter,
			'bbsRmdrRfqRepeat' => $this->bbsRmdrRfqRepeat,
			'bbsRmdrOrdSendAfter' => $this->bbsRmdrOrdSendAfter,
			'bbsRmdrOrdRepeatAfter' => $this->bbsRmdrOrdRepeatAfter,
			'bbsRmdrOrdRepeat' => $this->bbsRmdrOrdRepeat,
			'bybBranchCode' => (int)$bybBranchCode,
			'bbsPsuId' => $userId,
			);
	}

	/**
	* Sets the email_alert_config table for the specified tnid, and alert type, If checked sets TO, and CC, else NIL
	* @param integer $bybBranchCode TNID of the branch
	* @param const $alertType Alert type of self::RFQ_RMD or self::ORD_RMD
	* @param boolean $checked if must be set
	*/ 
	protected function updateEmailAlertConfig( $bybBranchCode, $alertType, $checked )
	{

		$eacBranchRcpType = ($checked) ? 'TO' : 'NIL'; 
		$eacContactRcpType = ($checked) ? 'CC' : 'NIL'; 

		$sql = 'SELECT
				 count(eac_branch_code) config_exists
				FROM
					email_alert_config
				WHERE eac_branch_code = :bybBranchCode
				AND eac_alert_type = :alertType 
				';

			$row = $this->db->fetchAll($sql, array(
					'bybBranchCode' => (int)$bybBranchCode,
					'alertType' => $alertType,
					));

			if ($row) {
				//If the REMINDER_EXISTS value is not 0 then return false
				$entryExists = !$row[0]['CONFIG_EXISTS'] == '0';
			} else {
				//if we could not even fetch the row, return false (likely never occures)
				$entryExists = false;
			}


		if (true === $entryExists) {
			//if the record exists, just update it
			$sql = "UPDATE 
	        			email_alert_config
	        		SET
	        			eac_branch_rcp_type=:eacBranchRcpType, eac_contact_rcp_type=:eacContactRcpType 
	        		WHERE eac_branch_code = :buyerTnid
	        		AND eac_alert_type = :alertType
	        	";
	        $this->db->query($sql, array(
	        		'buyerTnid' => $bybBranchCode,
	        		'alertType' => $alertType,
	        		'eacBranchRcpType' => $eacBranchRcpType,
					'eacContactRcpType' => $eacContactRcpType,
	        		));

		} else {
			//if the record does not exists, insert a new one
			$sql = "INSERT INTO
	        		email_alert_config
	        			(eac_branch_code, eac_alert_type, eac_branch_rcp_type, eac_contact_rcp_type, eac_email_format)
	        		VALUES
	        			(:buyerTnid, :alertType, :eacBranchRcpType, :eacContactRcpType, 'STD')
	        	";
	        $this->db->query($sql, array(
	        		'buyerTnid' => $bybBranchCode,
	        		'alertType' => $alertType,
					'eacBranchRcpType' => $eacBranchRcpType,
					'eacContactRcpType' => $eacContactRcpType,
	        		));
		}
	}

}