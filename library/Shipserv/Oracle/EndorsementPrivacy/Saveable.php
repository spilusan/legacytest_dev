<?php

/**
 * Save type for privacy settings.
 */
interface Shipserv_Oracle_EndorsementPrivacy_Saveable
{
	/**
	 * Returns ID identifying owner of privacy setting.
	 * 
	 * @return int
	 */
	public function getOwnerId ();
	
	/**
	 * @return string Shipserv_Oracle_EndorsementPrivacy::ANON_NO | Shipserv_Oracle_EndorsementPrivacy::ANON_YES | Shipserv_Oracle_EndorsementPrivacy::ANON_TN
	 */
	public function getGlobalAnon ();
	
	/**
	 * Returns id-specific anonymization policies.
	 * 
	 * @return array Associative array of id => Shipserv_Oracle_EndorsementPrivacy::ANON_YES | Shipserv_Oracle_EndorsementPrivacy::ANON_NO | Shipserv_Oracle_EndorsementPrivacy::ANON_YES : Shipserv_Oracle_EndorsementPrivacy::ANON_TN
	 */
	public function getExceptionList ();
}
