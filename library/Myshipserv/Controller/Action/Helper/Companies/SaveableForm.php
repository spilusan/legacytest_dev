<?php

/**
 * Wraps form representing privacy settings implementing an interface that
 * may be saved to DB.
 */
class Myshipserv_Controller_Action_Helper_Companies_SaveableForm implements Shipserv_Oracle_EndorsementPrivacy_Saveable
{
	private $ownerId;
	private $globalAnon;
	private $exceptionList = array();
	
	public function __construct (Myshipserv_Form_Endorsement_PrivacyAbstract $form, $ownerId)
	{
		// Init owner id
		$this->ownerId = $ownerId;
		if ($this->ownerId == '') throw new Exception("Owner ID must not be empty");
		
		// Pull field values from form
		$values = $form->getValues();
		
		// Strip prefix from ID: 'SPB-1234' => '1234'
		if (is_array($values['selective_anon']))
		{
			foreach ($values['selective_anon'] as $k => $v)
			{
				$v = strstr($v, '-');
				if ($v !== false)
				{
					$v = substr($v, 1);
					$values['selective_anon'][$k] = $v;
				}
			}
		}
		
		// Read anon policy from form, defaulting to 'off'
		$globalAnonFormState = $this->readKeyWithDefault(
			Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON,
			$values,
			Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON_OFF);
		
		// Fetch exception list from form (defaults to empty array)
		$formExList = $this->readKeyWithDefault(
			Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_SELECTIVE_ANON,
			$values,
			array());
		
		// If form is set to 'anonymize always' ...
		if ($globalAnonFormState == Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON_ON)
		{
			$this->globalAnon = Shipserv_Oracle_EndorsementPrivacy::ANON_YES;
			
			// Add specific rules with same anonymization policy as global rule
			// ensures that the exception list persists without affecting anything
			foreach ($formExList as $exId)
			{
				$this->exceptionList[$exId] = Shipserv_Oracle_EndorsementPrivacy::ANON_YES;
			}
		}
		
		// If form is set to 'anonymize always' ...
		elseif ($globalAnonFormState == Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON_TN_ONLY)
		{
			$this->globalAnon = Shipserv_Oracle_EndorsementPrivacy::ANON_TN;
			
			// Add specific rules with same anonymization policy as global rule
			// ensures that the exception list persists without affecting anything
			foreach ($formExList as $exId)
			{
				$this->exceptionList[$exId] = Shipserv_Oracle_EndorsementPrivacy::ANON_TN;
			}
		}
		
		// If form is set to 'show all' or 'show all with exceptions' ...
		elseif ($globalAnonFormState == Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON_OFF || $globalAnonFormState == Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON_SELECT)
		{
			$this->globalAnon = Shipserv_Oracle_EndorsementPrivacy::ANON_NO;
			
			// Add specific rules with policy depending on global state
			// Ensures harmless persistence of list, or persists list and enforces specific rules
			$exVal = ($globalAnonFormState == Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON_SELECT);
			foreach ($formExList as $exId)
			{
				$this->exceptionList[$exId] = $exVal ? Shipserv_Oracle_EndorsementPrivacy::ANON_YES : Shipserv_Oracle_EndorsementPrivacy::ANON_NO;
			}
		}
	}
	
	private function readKeyWithDefault ($key, $arr, $defaultVal)
	{
		if (isset($arr[$key]))
		{
			return $arr[$key];
		}
		else
		{
			return $defaultVal;
		}
	}
	
	public function getOwnerId ()
	{
		return $this->ownerId;
	}
	
	public function getGlobalAnon ()
	{
		return $this->globalAnon;
	}
	
	public function getExceptionList ()
	{
		return $this->exceptionList;
	}
}
