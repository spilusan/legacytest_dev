<?php

/**
 * Helper class which wraps RFQ data from DB and provides parameters for
 * TN XML generation.
 */
class Shipserv_TnMsg_Xml_RfqHelper
{
	private $rfq;
	private $lineItems;
	private $recipients;
	private $buyerCompanyName;
	private $transportModeMap;
	private $taxStatusMap;
	private $rfqColPrefix = Shipserv_Oracle_Util_PagesRfqTableDef::RFQ_COL_PREFIX;
	private $liColPrefix = Shipserv_Oracle_Util_PagesRfqTableDef::LI_COL_PREFIX;
	private $preparationTimestamp;
	
	/**
	 * @param array $rfq Row of Pages RFQ table as assoc array.
	 * @param array $lineItems Array of rows of Pages RFQ line item table as assoc arrays.
	 * @param array $recipients Array of supplier branch rows from DB.
	 */
	public function __construct (array $rfq, array $lineItems, array $recipients)
	{
		$this->rfq = $rfq;
		$this->lineItems = $lineItems;
		$this->recipients = $recipients;
		$this->buyerCompanyName = $this->fetchBuyerCompanyName($rfq);
		//$this->transportModeMap = new Myshipserv_Purchasing_Values_TransportMode();
		//$this->taxStatusMap = new Myshipserv_Purchasing_Values_TaxStatus();
		$this->preparationTimestamp = time();
	}
	
	private function fetchBuyerCompanyName(array $rfq)
	{
		// todo
		return "TEST BUYER NAME";
	}
	
	public function getControlRef ()
	{
		// DB always has Pages control reference
		return 'RequestForQuote:' . $this->rfq[$this->rfqColPrefix . 'PAGES_CONTROL_REF'];
	}
	
	public function getPreparationDate ()
	{
		return gmdate('Y-M-d', $this->preparationTimestamp);
	}
	
	public function getPreparationTime ()
	{
		return gmdate('H:m', $this->preparationTimestamp);
	}
	
	/**
	 * @return string Space separated list of SPB codes.
	 * @throws Exception if not set
	 */
	public function getRecipients ()
	{
		
		$idArr = array();
		foreach ($this->recipients as $r)
		{
			$id = (int) $r['SPB_BRANCH_CODE'];
			if ($id == 0)
			{
				throw new Exception("Invalid recipient ID");
			}
			$idArr[] = $id;
		}
		
		if (!$idArr)
		{
			throw new Exception("At least 1 recipient ID required");
		}
		
		return join(' ', $idArr);
	}
	
	public function getSender ()
	{
		// DB model always has buyer branch code
		return (string) $this->rfq[$this->rfqColPrefix . 'BYB_BRANCH_CODE'];
	}
	
	/**
	 * @return string
	 * @throws Exception if line item count < 1
	 */
	public function getLineItemCount ()
	{
		$n = count($this->lineItems);
		if ($n > 0)
		{
			return (string) $n;
		}
		else
		{
			throw new Exception("No line items");
		}
	}
	
	/**
	 * @return string or null
	 */
	public function getTaxStatus ()
	{
		//@todo need fixing
		//return (string) $this->taxStatusMap->db2Mtml($this->rfq[$this->rfqColPrefix . 'TAX_STS']);
	}
	
	/**
	 * @return string or null
	 */
	public function getTransportMode ()
	{
		//@todo need fixing
		//return (string) $this->transportModeMap->db2Mtml($this->rfq[$this->rfqColPrefix . 'TRANSPORTATION_MODE']);
	}
	
	/**
	 * @return string or null
	 */
	public function getDeliveryTerms ()
	{
		return $this->rfq[$this->rfqColPrefix . 'TERMS_OF_DELIVERY'];
	}
	
	/**
	 * @return string or null
	 */
	public function getVesselEta ()
	{
		return $this->dbTime2Mtml($this->rfq[$this->rfqColPrefix . 'ESTIMATED_ARRIVAL_TIME']);
	}
	
	/**
	 * @return string or null
	 */
	public function getVesselEtd ()
	{
		return $this->dbTime2Mtml($this->rfq[$this->rfqColPrefix . 'ESTIMATED_DEPARTURE_TIME']);
	}
	
	/**
	 * @return string or null
	 */
	public function getDeliveryTime ()
	{
		return $this->dbTime2Mtml($this->rfq[$this->rfqColPrefix . 'DATE_TIME']);
	}
	
	/**
	 * @return string or null
	 */
	public function getAdviseByTime ()
	{
		return $this->dbTime2Mtml($this->rfq[$this->rfqColPrefix . 'ADVICE_BEFORE_DATE']);
	}
	
	/**
	 * @return string or null
	 */
	public function getBuyerComments ()
	{
		return $this->rfq[$this->rfqColPrefix . 'COMMENTS'];
	}
	
	/**
	 * @return string or null
	 */
	public function getSubject ()
	{
		return $this->rfq[$this->rfqColPrefix . 'SUBJECT'];
	}
	
	/**
	 * @return string or null
	 */
	public function getTermsOfPayment ()
	{
		return $this->rfq[$this->rfqColPrefix . 'TERMS_OF_PAYMENT'];
	}
	
	/**
	 * @return string or null
	 */
	public function getGeneralTerms ()
	{
		return $this->rfq[$this->rfqColPrefix . 'GENERAL_TERMS_CONDITIONS'];
	}
	
	/**
	 * @return string or null
	 */
	public function getHumanRef ()
	{
		return $this->rfq[$this->rfqColPrefix . 'REF_NO'];
	}
	
	/**
	 * @return string or null
	 */
	public function getSuggestedShipper ()
	{
		return $this->rfq[$this->rfqColPrefix . 'SUGGESTED_SHIPPER'];
	}
	
	/**
	 * @return string or null
	 */
	public function getImo ()
	{
		return $this->rfq[$this->rfqColPrefix . 'IMO_NO'];
	}
	
	/**
	 * @return string or null
	 */
	public function getVesselName ()
	{
		return $this->rfq[$this->rfqColPrefix . 'VESSEL_NAME'];
	}
	
	/**
	 * @return string or null
	 */
	public function getDeliveryPort ()
	{
		return $this->rfq[$this->rfqColPrefix . 'DELIVERY_PORT'];
	}
	
	/**
	 * @return string or null
	 */
	public function getDeliveryAddress ()
	{
		return $this->rfq[$this->rfqColPrefix . 'ADDRESS'];
	}
	
	/**
	 * @return string or null
	 */
	public function getBuyerCompanyName ()
	{
		if ($this->buyerCompanyName != '')
		{
			return $this->buyerCompanyName;
		}
		else
		{
			return null;
		}
	}
	
	/**
	 * @return string or null
	 */
	public function getContactName ()
	{
		return $this->rfq[$this->rfqColPrefix . 'CONTACT'];
	}
	
	/**
	 * @return string or null
	 */
	public function getContactPhone ()
	{
		return $this->rfq[$this->rfqColPrefix . 'PHONE_NO'];
	}
	
	/**
	 * @return string or null
	 */
	public function getContactEmail ()
	{
		return $this->rfq[$this->rfqColPrefix . 'EMAIL_ADDRESS'];
	}
	
	/**
	 * @return string or null
	 */
	public function getBillingCity ()
	{
		$city = $this->rfq[$this->rfqColPrefix . 'BILLING_CITY'];
		if ($city != '')
		{
			return $city;
		}
		return null;
	}
	
	/**
	 * @return string or null
	 * @throws Exception if not set
	 */
	public function getBillingCountry ()
	{
		$country = $this->rfq[$this->rfqColPrefix . 'BILLING_COUNTRY'];
		if ($country != '')
		{
			return $country;
		}
		return null;
	}
	
	/**
	 * @return string or null
	 */
	public function getBillingProvince ()
	{
		$state = $this->rfq[$this->rfqColPrefix . 'BILLING_STATE_PROVINCE'];
		if ($state != '')
		{
			return $state;
		}
		return null;
	}
	
	/**
	 * @return string or null
	 */
	public function getBillingZip ()
	{
		$zip = $this->rfq[$this->rfqColPrefix . 'BILLING_POSTAL_ZIP_CODE'];
		if ($zip != '')
		{
			return $zip;
		}
		return null;
	}
	
	/**
	 * @return string or null
	 */
	public function getBillingAddress ()
	{
		$ba = (string) $this->rfq[$this->rfqColPrefix . 'BILLING_ADDRESS_1'];
		$ba .= ' ';
		$ba .= $this->rfq[$this->rfqColPrefix . 'BILLING_ADDRESS_2'];
		$ba = trim($ba);
		if ($ba != '')
		{
			return $ba;
		}
		return null;
	}
	
	/**
	 * @return array of Shipserv_TnMsg_Xml_RfqLiHelper
	 */
	public function getLineItems ()
	{
		$orderedLiArr = array();
		foreach ($this->lineItems as $li)
		{
			if ($this->rfq[$this->rfqColPrefix . 'PAGES_CONTROL_REF'] != $li[$this->liColPrefix . 'RFQ_PAGES_CONTROL_REF'])
			{
				throw new Exception("Line item parent mismatch");
			}
			if (array_key_exists($li[$this->liColPrefix . 'LINE_ITEM_NO'], $orderedLiArr))
			{
				throw new Exception("Duplicate line item number");
			}
			$orderedLiArr[$li[$this->liColPrefix . 'LINE_ITEM_NO']] = $li;
		}
		ksort($orderedLiArr);
		
		$i = 1;
		$liObjs = array();
		foreach ($orderedLiArr as $li)
		{
			if ($li[$this->liColPrefix . 'LINE_ITEM_NO'] != $i++)
			{
				throw new Exception("Non-sequential line items");
			}
			$liObjs[] = new Shipserv_TnMsg_Xml_RfqLiHelper($li);
		}
		return $liObjs;
	}
	
	/**
	 * Convert DB time string to MTML time string
	 * 
	 * @return string
	 */
	private function dbTime2Mtml ($dbTimeObj)
	{
		if ($dbTimeObj === null) return null;
		
		$res = gmdate('YmdHi', $dbTimeObj->getTimeStamp());
		return $res;
	}
}
