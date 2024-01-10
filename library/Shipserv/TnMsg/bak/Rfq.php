<?php

// For xml: RequestForQuote:4324998

/**
 * Represents a TradeNet RFQ
 */
class Shipserv_TnMsg_Rfq
{
	const TRANSPORT_MODE_MARITIME = '_1';
	const TRANSPORT_MODE_RAIL = '_2';
	const TRANSPORT_MODE_ROAD = '_3';
	const TRANSPORT_MODE_AIR = '_4';
	
	// todo: add the rest ... there are lots of these!
	const DELIVERY_TERMS_EX_WORKS = 'EXW';
	
	const TAX_STATUS_EXEMPT = 'Exempt';
	const TAX_STATUS_NOT_TAXABLE = 'NotTaxable';
	const TAX_STATUS_TAXABLE = 'Taxable';
	
	private $controlReference;
	private $preparationTimestamp;
	private $buyerId;
	private $supplierId;
	private $taxStatus;
	private $transportMode;
	private $deliveryTerms;
	private $vesselEta;
	private $vesselEtd;
	private $deliveryRequestedTime;
	private $qotAdviseByTime;
	private $parties;
	private $comments;
	private $lineItems = array();
	
	public function __construct ($controlReference, $preparationTimestamp, $buyerId, $supplierId, Shipserv_TnMsg_Rfq_Parties $parties)
	{
		// Init control reference
		$this->controlReference = trim($controlReference);
		
		if ($this->controlReference == '')
		{
			throw new Exception("Control reference cannot be empty");
		}
		
		// Init timestamp
		$this->preparationTimestamp = (int) $preparationTimestamp;
		
		if ($this->preparationTimestamp <= 0)
		{
			throw new Exception("Invalid timestamp");
		}
		
		// Init buyer ID
		$this->buyerId = (int) $buyerId;
		
		if ($this->buyerId <= 0)
		{
			throw new Exception("Invalid buyer ID");
		}
		
		// Init supplier ID
		$this->supplierId = (int) $supplierId;
		
		if ($this->supplierId <= 0)
		{
			throw new Exception("Invalid supplier ID");
		}
		
		// Init parties
		$this->parties = $parties;
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
	 * "Exempt" | "NotTaxable" | "Taxable"
	 * 
	 * @param string $status
	 */
	public function setTaxStatus ($status = null)
	{
		static $possVals = array(
			self::TAX_STATUS_EXEMPT,
			self::TAX_STATUS_NOT_TAXABLE,
			self::TAX_STATUS_TAXABLE,
		);
		
		if ($status === null)
		{
			$this->taxStatus = null;
		}
		else
		{
			if (in_array($status, $possVals))
			{
				$this->taxStatus = $status;
			}
			else
			{
				throw new Exception("Invalid tax status");
			}
		}
	}
	
	/**
	 * Set mode of transport for delivery.
	 * 
	 * @param string $mode
	 */
	public function setTransportMode ($mode = null)
	{
		static $validModes = array(
			self::TRANSPORT_MODE_MARITIME,
			self::TRANSPORT_MODE_RAIL,
			self::TRANSPORT_MODE_ROAD,
			self::TRANSPORT_MODE_AIR,
		);
		
		if ($mode === null)
		{
			$this->transportMode = null;
		}
		else
		{
			$mode = (string) $mode;
			
			if (in_array($mode, $validModes))
			{
				$this->transportMode = $mode;
			}
			else
			{
				throw new Exception("Invalid transport mode");
			}
		}
	}
	
	/**
	 * Set terms of delivery.
	 * 
	 * @param string $terms
	 */
	public function setDeliveryTerms ($terms = null)
	{
		static $validTerms = array(
			self::DELIVERY_TERMS_EX_WORKS,
		);
		
		if ($terms === null)
		{
			$this->deliveryTerms = null;
		}
		else
		{
			$terms = (string) $terms;
			
			if (in_array($terms, $validTerms))
			{
				$this->deliveryTerms = $terms;
			}
			else
			{
				throw new Exception("Invalid delivery terms");
			}
		}
	}
	
	/**
	 * @param int $timestamp
	 */
	public function setVesselEta ($timestamp = null)
	{
		if ($timestamp === null)
		{
			$this->vesselEta = null;
		}
		else
		{
			$timestamp = (int) $timestamp;
			
			if ($timestamp > 0)
			{
				$this->vesselEta = $timestamp;
			}
			else
			{
				throw new Exception("Invalid timestamp");
			}
		}
	}
	
	/**
	 * @param int $timestamp
	 */
	public function setVesselEtd ($timestamp = null)
	{
		if ($timestamp === null)
		{
			$this->vesselEtd = null;
		}
		else
		{
			$timestamp = (int) $timestamp;
			
			if ($timestamp > 0)
			{
				$this->vesselEtd = $timestamp;
			}
			else
			{
				throw new Exception("Invalid timestamp");
			}
		}
	}
	
	/**
	 * @param int $timestamp
	 */
	public function setDeliveryRequestedTime ($timestamp = null)
	{
		if ($timestamp === null)
		{
			$this->deliveryRequestedTime = null;
		}
		else
		{
			$timestamp = (int) $timestamp;
			
			if ($timestamp > 0)
			{
				$this->deliveryRequestedTime = $timestamp;
			}
			else
			{
				throw new Exception("Invalid timestamp");
			}
		}
	}
	
	/**
	 * @param int $timestamp
	 */
	public function setQotAdviseByTime ($timestamp = null)
	{
		if ($timestamp === null)
		{
			$this->qotAdviseByTime = null;
		}
		else
		{
			$timestamp = (int) $timestamp;
			
			if ($timestamp > 0)
			{
				$this->qotAdviseByTime = $timestamp;
			}
			else
			{
				throw new Exception("Invalid timestamp");
			}
		}
	}
	
	public function setComments (Shipserv_TnMsg_Rfq_Comments $comments = null)
	{
		$this->comments = $comments;
	}
	
	public function addLineItem (Shipserv_TnMsg_Rfq_LineItem $lineItem)
	{
		$this->lineItems[] = $lineItem;
	}
	
	/**
	 * @return string Ymd
	 */
	public function getPreparationDate ()
	{
		return gmdate('Y-M-d', $this->preparationTimestamp);
	}
	
	/**
	 * @return string Hm
	 */
	public function getPreparationTime ()
	{
		return gmdate('H:m', $this->preparationTimestamp);
	}
	
	/**
	 * @return string YmdHi, or null
	 */
	public function getVesselEta ()
	{
		if ($this->vesselEta !== null)
		{
			return gmdate('YmdHi', $this->vesselEta);
		}
	}
	
	/**
	 * @return string YmdHi, or null
	 */
	public function getVesselEtd ()
	{
		if ($this->vesselEtd !== null)
		{
			return gmdate('YmdHi', $this->vesselEtd);
		}
	}
	
	/**
	 * @return string YmdHi, or null
	 */
	public function getDeliveryRequestedTime ()
	{
		if ($this->deliveryRequestedTime !== null)
		{
			return gmdate('YmdHi', $this->deliveryRequestedTime);
		}
	}
	
	/**
	 * @return string YmdHi, or null
	 */
	public function getQotAdviseByTime ()
	{
		if ($this->qotAdviseByTime !== null)
		{
			return gmdate('YmdHi', $this->qotAdviseByTime);
		}
	}
}
