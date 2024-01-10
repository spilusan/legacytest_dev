<?php

class Myshipserv_Controller_Action_Helper_ProfileNotifications extends Zend_Controller_Action_Helper_Abstract
{
	public function makeForm ()
	{
        $user = $this->getUser();
		$nForm = new Myshipserv_Form_Alerts();

		$nForm->populate(array(
            Myshipserv_Form_Alerts::ELEMENT_ALERTS    => $user->alertStatus,
            Myshipserv_Form_Alerts::ELEMENT_ANONYMITY => $user->anonymityFlag
        ));

		return $nForm;
	}

	public function saveForm (Myshipserv_Form_Alerts $form)
	{
		$user = $this->getUser();
		Shipserv_User::updateDetails(
			$user->firstName,
			$user->lastName,
			$form->getAlertsFlag(),
            $form->getAnonymityFlag(),
			$user->alias,
			$user->companyName,
			$user->pctId,
			$user->otherCompanyType,
			$user->pjfId,
			$user->otherJobFunction
        );
	}
	
	private function getUser ()
	{
		$user = Shipserv_User::isLoggedIn();
		if (!$user) {
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

        // reload user to update cached flags in the session
        $user = Shipserv_User::getInstanceById($user->userId);

		return $user;
	}
	
	private function getDb ()
	{
		return $this->getActionController()->getInvokeArg('bootstrap')->getResource('db');
	}
	
	private function getUserDao ()
	{
		return new Shipserv_Oracle_User($this->getDb());
	}
}
