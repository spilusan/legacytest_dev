<?php
	
class Shipserv_TnMsg_Rfq_BillingAddress
{
	private $street;
	private $city;
	private $zip;
	private $stateOrProvince;
	private $country;
	
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
	 * @param string $street
	 */
	public function setStreet ($street = null)
	{
		$street = trim($street);
		
		if ($street == '')
		{
			$this->street = null;
		}
		else
		{
			$this->street = $street;
		}
	}
	
	/**
	 * @param string $city
	 */
	public function setCity ($city = null)
	{
		$city = trim($city);
		
		if ($city == '')
		{
			$this->city = null;
		}
		else
		{
			$this->city = $city;
		}
	}
	
	/**
	 * @param string $zip
	 */
	public function setZip ($zip = null)
	{
		$zip = trim($zip);
		
		if ($zip == '')
		{
			$this->zip = null;
		}
		else
		{
			$this->zip = $zip;
		}
	}
	
	/**
	 * @param string $stateOrProvince
	 */
	public function setStateOrProvince ($stateOrProvince = null)
	{
		$stateOrProvince = trim($stateOrProvince);
		
		if ($stateOrProvince == '')
		{
			$this->stateOrProvince = null;
		}
		else
		{
			$this->stateOrProvince = $stateOrProvince;
		}
	}
	
	/**
	 * Valid country code.
	 * 
	 * @param string $country
	 */
	public function setCountry ($country = null)
	{
		$country = trim($country);
		
		if ($country == '')
		{
			$this->country = null;
		}
		else
		{
			// todo: validate country code
			$this->country = $country;
		}
	}
	
	/**
	 * Returns true if all fields are null.
	 *
	 * @return bool
	 */
	public function isNull ()
	{
		static $fieldsToCheck = array(
			'street',
			'city',
			'zip',
			'stateOrProvince',
			'country',
		);
		
		// If one field is not null, return false
		foreach ($fieldsToCheck as $f)
		{
			if ($this->$f !== null)
			{
				return false;
			}
		}
		
		// All fields null
		return true;
	}
}
