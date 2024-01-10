<?php

/**
 * Wraps an object representing privacy settings regarding trading partners
 * in order to map 2-state 'anonymization on/off' model to 3-states
 * 'anonymization on/off/off-with-exceptions'.
 *
 * This is achieved by using the presence (or absence) of exception rules to
 * map 'anonymization off' into 'anonymization off' or 'anonymization off with
 * exceptions'.
 */
class Myshipserv_Controller_Action_Helper_Companies_PrivacyAdapter
{
	const GLOBAL_ANON_ON = 1;
	const GLOBAL_ANON_OFF = 2;
	const GLOBAL_ANON_OFF_EXCEPT = 3;
	const GLOBAL_ANON_TN_ONLY = 4;
	
	private $state;
	
	private $exceptionList;
	
	public function __construct (Shipserv_Oracle_EndorsementPrivacy_Setting $setting)
	{
		// Fetch id-specific anonymization rules
		$exRules = $setting->getExceptionRules();
		
		// If global anonymization policy is ON
		if ($setting->getGlobalAnonPolicy() == Shipserv_Oracle_EndorsementPrivacy::ANON_YES)
		{
			$this->state = self::GLOBAL_ANON_ON;
			
			// Ignore specific show / hide policies: just get a list of ids present
			$this->exceptionList = array_keys($exRules);
		}
		
		// If global anon policy is TN only
		elseif ($setting->getGlobalAnonPolicy() == Shipserv_Oracle_EndorsementPrivacy::ANON_TN)
		{
			$this->state = self::GLOBAL_ANON_TN_ONLY;
			
			// Ignore specific show / hide policies: just get a list of ids present
			$this->exceptionList = array_keys($exRules);
		}
		
		// If global anonymization policy is OFF
		else
		{
			// Pull out ids with 'always anonymize' rule
			$exAnonIds = array_keys($exRules, Shipserv_Oracle_EndorsementPrivacy::ANON_YES);
			
			if ($exAnonIds)
			{
				// Presence of 'always anonymize' ids means set state to 'off except'
				$this->state = self::GLOBAL_ANON_OFF_EXCEPT;
				
				// Set exception list to 'always anonymize' ids, ignoring any 'always show' ids
				$this->exceptionList = $exAnonIds;
			}
			else
			{
				// Set anonymization to 'off'
				$this->state = self::GLOBAL_ANON_OFF;
				
				// Ignore specific show / hide policies: just get a list of ids present
				$this->exceptionList = array_keys($exRules);
			}
		}
	}
	
	public function getGlobalState ()
	{
		return $this->state;
	}
	
	public function getExceptionList ()
	{
		return $this->exceptionList;
	}
}
