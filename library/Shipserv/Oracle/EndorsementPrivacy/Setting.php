<?php

/**
 * Return type representing privacy settings
 */
class Shipserv_Oracle_EndorsementPrivacy_Setting
{	
	private $globalAnon;
	
	private $exceptionRules = array();
	
	/**
	 * @param bool $boolGlobalAnon Global anonymization policy on/off
	 * @param array $exceptionRules Associative array of id => policy on/off
	 */
	public function __construct ($gAnon, array $exceptionRules)
	{
		if (!in_array($gAnon, array(Shipserv_Oracle_EndorsementPrivacy::ANON_YES, Shipserv_Oracle_EndorsementPrivacy::ANON_NO, Shipserv_Oracle_EndorsementPrivacy::ANON_TN))) throw new Exception("Invalid global anonymisation setting: '$gAnon'");
		$this->globalAnon = (string) $gAnon;
		
		foreach ($exceptionRules as $id => $val)
		{
			if ($id == '') throw new Exception("ID must not be empty");
			if (!in_array($val, array(Shipserv_Oracle_EndorsementPrivacy::ANON_YES, Shipserv_Oracle_EndorsementPrivacy::ANON_NO, Shipserv_Oracle_EndorsementPrivacy::ANON_TN))) throw new Exception("Invalid global anonymisation setting: '$val'");
			$this->exceptionRules[$id] = $val;
		}
	}
	
	/**
	 * Indicates a company's overall anonymization policy for revealing trading relationships.
	 *
	 * @return string Shipserv_Oracle_EndorsementPrivacy::ANON_YES | Shipserv_Oracle_EndorsementPrivacy::ANON_NO | Shipserv_Oracle_EndorsementPrivacy::ANON_TN
	 */
	public function getGlobalAnonPolicy ()
	{
		return $this->globalAnon;
	}
	
	/**
	 * Indicates a company's anonymization policies relating to specific trading partners.
	 *
	 * @return array Associative array of id => Shipserv_Oracle_EndorsementPrivacy::ANON_YES | Shipserv_Oracle_EndorsementPrivacy::ANON_NO | Shipserv_Oracle_EndorsementPrivacy::ANON_TN
	 */
	public function getExceptionRules ()
	{
		return $this->exceptionRules;
	}
}
