<?php

class Myshipserv_NotificationManager_Email_Targetcustomers_Unlocked extends Myshipserv_NotificationManager_Email_Abstract
{
	private 
		$recipientUserId,
		$bybBranchCode,
		$spbBranchCode,
		$buyer,
		$user,
		$buyerBranch,
		$validFrom,
		$recepientsList,
		$validFromTime,
		$lockOrdRefNo,
		$ordDate,
		$validTill,
		$validTillTime,
		$config,
		$testRecepient,
		$buyerInfo, 
		$shipInfo
		;
	
	public function __construct ($db, $data)
	{
		parent::__construct($db);
		$buyerId = (int)$data['BSR_BYB_BRANCH_CODE'];
		$this->config  = Zend_Registry::get('config');
		$this->recipientUserId = $data['BSR_PSU_ID'];
		$this->bybBranchCode = $data['BSR_BYB_BRANCH_CODE'];
		$this->buyer = Shipserv_Buyer::getBuyerBranchInstanceById( $buyerId );
		$this->buyerBranch = Shipserv_Buyer_Branch::getInstanceById( $buyerId );
		$this->spbBranchCode = $data['BSR_SPB_BRANCH_CODE'];
		$this->user = Shipserv_User::getInstanceById($this->recipientUserId);
		$this->validFrom = ucwords(strtolower($data['BSR_VALID_FROM']));
		$this->validFromTime = $data['BSR_VALID_FROM_TIME'];
		$this->lockOrdRefNo = $data['BSR_LOCKED_ORD_INTERNAL_REF_NO'];
		$this->ordDate =  $data['ORD_CREATED_DATE'];
		$this->validTill = ucwords(strtolower($data['BSR_VALID_TILL']));
		$this->validTillTime = $data['BSR_VALID_TILL_TIME'];
		if ($this->config->shipserv->targeting->email->all == 1) {
			$this->getRecepientsFromDb();
		} else {
			$this->getTestRecepients();
		}

		$showChildren = ($this->buyerBranch->isTopLevelBranch() && $this->buyer->bybPromoteChildBranches == 0);
		$this->buyerInfo = Shipserv_Oracle_Targetcustomers_Buyerinfo::getInstance()->getBuyerInfo( $this->bybBranchCode, $this->spbBranchCode, false, null, $showChildren);
		$this->shipInfo = Shipserv_Oracle_Targetcustomers_Vessel::getInstance()->getVesselInfo($this->bybBranchCode, $this->spbBranchCode, false, $showChildren);
	}


	/**
	* Load the list of email addresses 
	*/
	protected function getRecepientsFromDb()
	{
		$userList = new Shipserv_Profile_Targetcustomers_Reports();
		$users = $userList->getApprovedUsers();
		$result = array();

		foreach ($users as $user) {
			if ($user['receiveNotifications'] == 1) {
				array_push($result, array('PUT_PSU_ID' => $user['id']));
			}
		}

        $this->testRecepient = false;
        $this->recepientsList = $result;

        /*
			$this->db = $this->getDb();
			$sql = "
				SELECT
				  put_psu_id
				FROM
				  pages_user_target
				WHERE
				  PUT_SPB_BRANCH_CODE = :spbBranchCode
				  AND put_target_notification = 1
			";
			$params = array(
					'spbBranchCode' => $this->spbBranchCode,
				);
	        $resultSet = $this->db->fetchAll($sql, $params);
	        $this->recepientsList = $resultSet;
        */
	}

	protected function getTestRecepients()
	{
		$this->testRecepient = true;
		$this->recepientsList = array(
				array(
					'EMAIL' =>	$this->config->shipserv->targeting->test->email
					)
			);

	}

	/**
	* Convert the refepients, fetced above from the database to a list of Myshipserv_NotificationManager_Recipient object
	* @return array Array of Myshipserv_NotificationManager_Recipient instances
	*/
public function getRecipients()
	{

		$recipient = new Myshipserv_NotificationManager_Recipient;
		$recipientStorage = array();
		if ($this->testRecepient == true) {
			foreach ($this->recepientsList as $recepient) {
				$recipientStorage['TO'][] = array("name" => $recepient['EMAIL'], "email" => $recepient['EMAIL']);
			}

		} else {

			if (Myshipserv_NotificationManager_Email_Targetcustomers_Validation::emailGoesToBccOnly($this->spbBranchCode) == true) {
				$bccKey = 'TO';
			} else {
				$bccKey = 'BCC';
				foreach ($this->recepientsList as $recepient) {
					$user = $this->getUser($recepient['PUT_PSU_ID']);
					$recipientStorage['TO'][] = array("name" => $user->email, "email" => $user->email);
				}
			}

			$bcc = Myshipserv_Config::getTargetingRecipients();
	    	if (!empty($bcc)) {
	    		foreach ($bcc as $bccEmail) {
	    			$recipientStorage[$bccKey][] = array("name" => "ShipServ Staff", "email" => $bccEmail);
	    		}
	    	}
		}

       $recipient->list = $recipientStorage;

       return $recipient;
	}
	
	public function getSubject ()
	{
		return $this->buyerInfo['BYB_NAME'].' unlocked from Active Promotion.'; //TODO confirm title
	}
	
	public function getBody ()
	{

		$recipients = $this->getRecipients();
		$view = $this->getView();
		
		$view->buyer = $this->buyer;
		$view->shipInfo = $this->shipInfo;
		$view->buyerInfo = $this->buyerInfo;

		$vesselTypeList = '';
		$vesselTypeCount = count($this->shipInfo['vesselTypeList']);

		for ($i = 0 ; $i<$vesselTypeCount; $i++) {
			if ($i<5) {
			$vesselTypeList .= ($vesselTypeList == '') ?  $this->shipInfo['vesselTypeList'][$i] : ', '.$this->shipInfo['vesselTypeList'][$i];
			}
		}


		if ($i>=5) {
			$vesselTypeList .='... and '.($vesselTypeCount - 5).' more';
		}	

	  	$userName = $this->user->firstName . ' ' . $this->user->lastName;
		$userName = ($userName == ' ')? explode('@',$this->user->email)[0] : $userName;

		$view->vesselTypeList = $vesselTypeList;
		$view->user = $this->user;
		$view->userName = $userName;
		$view->validFrom = $this->validFrom;

		$view->lockOrdRefNo = $this->lockOrdRefNo;
		$view->ordDate = $this->ordDate; 

		$view->lockReleased = $this->validTill;
		$validFromParts = explode(' ',$this->validFrom);
		$view->ValidYear = $validFromParts[2];
		$view->ValidMonth = $validFromParts[1];

		$config  = Zend_Registry::get('config');
		$logoUrl = $this->config->shipserv->images->buyerLogo->urlPrefix.$this->bybBranchCode.'.gif';

		$headers = get_headers($logoUrl);
		$responseCode = (int)explode(' ',$headers[0])[1];

		$imageFileSize = 0;
		foreach ($headers as  $header) {
			$headerParts = explode(":",$header);
			if ($headerParts[0] == 'Content-Length') {
				$imageFileSize = (int)$headerParts[1];
			}
		}
		
		$view->logoUrlIsValid = (($responseCode == 200) && ($imageFileSize > 500));
		$view->logoUrl = $logoUrl;
		$view->domain = $this->config->shipserv->application->hostname;

		$res = $view->render('email/Targetcustomers/buyer-unlocked.phtml');
		$emails[$recipients->getHash()] = $res;
		
        return $emails;

	}


}
