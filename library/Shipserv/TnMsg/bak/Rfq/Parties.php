<?php

/**
 * Describes Parties involved in RFQ
 */
class Shipserv_TnMsg_Rfq_Parties
{
	private $buyer;
	private $deliveryPort;
	private $vesselName;
	private $vesselImo;
	private $deliveryAddress;
	private $suggestedForwarder;
	private $billingAddress;
	
	public function __construct (Shipserv_TnMsg_Rfq_PartyBuyer $buyer)
	{
		$this->buyer = $buyer;
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
	 * A valid port code, of the form: 'ZA-DUR'.
	 * 
	 * @param string $deliveryPort
	 */
	public function setDeliveryPort ($deliveryPort = null)
	{
		if ($deliveryPort === null)
		{
			$this->deliveryPort = null;
		}
		else
		{
			$deliveryPort = (string) $deliveryPort;
			
			if (preg_match('/^[A-Z]{2}-[A-Z]{3}$/', $deliveryPort) == 1)
			{
				$this->deliveryPort = $deliveryPort;
			}
			else
			{
				throw new Exception("Expected port code of form: 'ZA-DUR'");
			}
		}
	}
	
	/**
	 * @param string $vesselName
	 */
	public function setVesselName ($vesselName = null)
	{
		$vesselName = trim($vesselName);
		
		if ($vesselName == '')
		{
			$this->vesselName = null;
		}
		else
		{
			$this->vesselName = $vesselName;
		}
	}
	
	/**
	 * @param string $vesselImo
	 */
	public function setVesselImo ($vesselImo = null)
	{
		if ($vesselImo === null)
		{
			$this->vesselImo = null;
		}
		else
		{
			if (is_numeric($vesselImo) && strlen($vesselImo) == 7)
			{
				$this->vesselImo = $vesselImo;
			}
			else
			{
				throw new Exception("IMO must be a 7-digit numeric string");
			}
		}
	}
	
	/**
	 * @param string $deliveryAddress
	 */
	public function setDeliveryAddress ($deliveryAddress = null)
	{
		$deliveryAddress = trim($deliveryAddress);
		
		if ($deliveryAddress == '')
		{
			$this->deliveryAddress = null;
		}
		else
		{
			$this->deliveryAddress = $deliveryAddress;
		}
	}
	
	/**
	 * @param string $forwarder
	 */
	public function setSuggestedFreightForwarder ($forwarder = null)
	{
		$forwarder = trim($forwarder);
		
		if ($forwarder == '')
		{
			$this->suggestedForwarder = null;
		}
		else
		{
			$this->suggestedForwarder = $forwarder;
		}
	}
	
	public function setBillingAddress (Shipserv_TnMsg_Rfq_BillingAddress $address = null)
	{
		$this->billingAddress = $address;
	}
}
