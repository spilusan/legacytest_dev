<?php

/**
 * Internal administration methods for CR.
 */
class Ssadmin_JoinReqController extends Myshipserv_Controller_Action
{
	public function preDispatch ()
	{
		// Ensure IP is authorised for all controller actions
		if (!$this->isIpInRange(Myshipserv_Config::getUserIp(), Myshipserv_Config::getSuperIps()))
		{
			throw new Myshipserv_Exception_MessagedException("To do this action, you will need to be physically located within ShipServ Office", 403);
		}
	}
	
	/**
	 * Approve or decline join request.
	 * 
	 * http://.../reqId/<id>/req-action/approve|approve-admin|decline
	 */	
	public function processAction ()
	{
		// Resolve action parameter
		switch ($this->_getParam('req-action'))
		{
			case 'approve':
			case 'approve-admin':
				$boolApprove = true;
				break;
						
			case 'decline':
				$boolApprove = false;
				break;
			
			default:
				throw new Exception("Unrecognised action");
		}
		
		// Apply request
		try
		{
			// Process join request
			$jrp = new Ssadmin_JoinReqController_JoinReqProcessor($this->getDb());
			$jrp->processJoinRequest($this->_getParam('reqId'), $boolApprove);
			
			// Promote to admin
			if ($this->_getParam('req-action') == 'approve-admin')
			{
				$this->updateLevel($this->_getParam('reqId'), true);
			}
			
			// Compose UI message
			if ($this->_getParam('req-action') == 'approve')
			{
				$msg = "Join request approved OK";
			}
			elseif ($this->_getParam('req-action') == 'approve-admin')
			{
				$msg = "Join request approved, user promoted to company admin OK";
			}
			elseif ($this->_getParam('req-action') == 'decline')
			{
				$msg = "Join request declined OK";
			}
			else
			{
				throw new Exception("Logic error");
			}
		}
		catch (Ssadmin_JoinReqController_JoinReqProcessor_Exception $e)
		{
			$msg = "No pending join request found for ID: '" . $this->_getParam('reqId') . "'";
			$msg .= "<br />";
			$msg .= "(i.e. Request already processed)";
			throw new Myshipserv_Exception_MessagedException( $msg );
			
		}
		catch (Exception $e)
		{
			$msg = (string) $e;
		}
		$this->view->action = array('msg' => $msg);
	}
	
	/**
	 * @return array
	 * @throws Exception if not found
	 */
	private function getJoinReq ($joinReqId)
	{
		$userCompanyRequestDao = new Shipserv_Oracle_UserCompanyRequest($this->getDb());
		return $userCompanyRequestDao->fetchRequestById($joinReqId);
	}
	
	/**
	 * Update membership level of association that has been made from
	 * a join request.
	 */
	private function updateLevel ($jReqId, $isAdminBool)
	{
		$jReq = $this->getJoinReq($jReqId);
		$userCompanyDao = new Shipserv_Oracle_PagesUserCompany($this->getDb());
		$userCompanyDao->updateLevel($jReq['PUCR_PSU_ID'], $jReq['PUCR_COMPANY_TYPE'], $jReq['PUCR_COMPANY_ID'], $isAdminBool ? Shipserv_Oracle_PagesUserCompany::LEVEL_ADMIN : Shipserv_Oracle_PagesUserCompany::LEVEL_USER);
	}
	
	private function getDb ()
	{
		return $this->getInvokeArg('bootstrap')->getResource('db');
	}
}

/**
 * Exception used by Ssadmin_JoinReqController_JoinReqProcessor
 */
class Ssadmin_JoinReqController_JoinReqProcessor_Exception extends Exception
{
	
}

/**
 * Convenience class for approving / declining join requests.
 *
 * Note: this ought to be refactored so that Myshipserv_UserCompany_AdminActions
 * and this class delegate to the same block of code. For now I've duplicated
 * as a quick fix.
 */
class Ssadmin_JoinReqController_JoinReqProcessor
{
	private $db;
	private $userCompanyDao;		
	private $userCompanyRequestDao;
	
	public function __construct ($db)
	{
		$this->db = $db;
		$this->userCompanyDao = new Shipserv_Oracle_PagesUserCompany($db);		
		$this->userCompanyRequestDao = new Shipserv_Oracle_UserCompanyRequest($db);
	}
	
	/**
	 * Approve / reject join request for company.
	 *
	 * @return void
	 */
	public function processJoinRequest ($joinReqId, $boolApprove)
	{
		try
		{
			// Exception thrown if row not found
			$jReq = $this->userCompanyRequestDao->fetchRequestById($joinReqId);
			
			// Change status of request to confirmed / rejected
			// Note: throws exception if request is not pending
			$this->userCompanyRequestDao->updateRequest($jReq['PUCR_ID'],
				$boolApprove ? Shipserv_Oracle_UserCompanyRequest::STATUS_CONFIRMED
					: Shipserv_Oracle_UserCompanyRequest::STATUS_REJECTED);
		}
		catch (Shipserv_Oracle_UserCompanyRequest_Exception $e)
		{
			// Shipserv_Oracle_UserCompanyRequest_Exception::UPDATE_NOT_FOUND
			// Shipserv_Oracle_UserCompanyRequest_Exception::FETCH_NOT_FOUND
			throw new Ssadmin_JoinReqController_JoinReqProcessor_Exception("No pending join request found for ID: '$joinReqId'");
		}
		
		// Add user as member of company
		// Use 'upsert' because a 'logically' deleted row may exist
		// Note: throws exception on failure
		$this->userCompanyDao->insertUserCompany(
            $jReq['PUCR_PSU_ID'],
            $jReq['PUCR_COMPANY_TYPE'], $jReq['PUCR_COMPANY_ID'],
            Shipserv_Oracle_PagesUserCompany::LEVEL_USER,
            $boolApprove ? Shipserv_Oracle_PagesUserCompany::STATUS_ACTIVE : Shipserv_Oracle_PagesUserCompany::STATUS_DELETED,
            true
        );
		
		// Send notification
		$nm = new Myshipserv_NotificationManager($this->db);
		if ($boolApprove) $nm->grantCompanyMembership ($jReq['PUCR_PSU_ID'], $jReq['PUCR_COMPANY_TYPE'], $jReq['PUCR_COMPANY_ID']);
		else $nm->declineCompanyMembership ($jReq['PUCR_PSU_ID'], $jReq['PUCR_COMPANY_TYPE'], $jReq['PUCR_COMPANY_ID']);
	}
}
