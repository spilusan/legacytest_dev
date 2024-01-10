<?php

/**
 * Describes the buyer as an RFQ 'Party' element.
 */
class Shipserv_TnMsg_Rfq_PartyBuyer
{
	private $companyName;
	private $contactName;
	private $contactPhone;
	private $contactEmail;
	
	/**
	 * @param string $companyName
	 */
	public function __construct ($companyName)
	{
		$companyName = trim($companyName);
		
		if ($companyName == '')
		{
			throw new Exception("Company name cannot be empty");
		}
		else
		{
			$this->companyName = $companyName;
		}
	}
	
	public function __get ($name)
	{
		if (property_exists($this, $name))
		{
			return $this->$name;
		}
		else
		{
			throw new Exception("Requested property does not exist");
		}
	}
	
	/**
	 * @param string $contactName
	 */
	public function setContactName ($contactName = null)
	{
		$contactName = trim($contactName);
		
		if ($contactName == '')
		{
			$this->contactName = null;
		}
		else
		{
			$this->contactName = (string) $contactName;
		}
	}
	
	/**
	 * @param string $contactPhone
	 */
	public function setContactPhone ($contactPhone = null)
	{
		$contactPhone = trim($contactPhone);
		
		if ($contactPhone == '')
		{
			$this->contactPhone = null;
		}
		else
		{
			$this->contactPhone = $contactPhone;
		}
	}
	
	/**
	 * @param string $contactEmail
	 */
	public function setContactEmail ($contactEmail = null)
	{
		$contactEmail = trim($contactEmail);
		
		if ($contactEmail == '')
		{
			$this->contactEmail = null;
		}
		else
		{
			// todo: validate e-mail?
			
			$this->contactEmail = $contactEmail;
		}
	}
}
