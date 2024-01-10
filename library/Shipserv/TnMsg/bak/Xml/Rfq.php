<?php

/**
 * Serializes an RFQ object into a TradeNet XML message.
 */
class Shipserv_TnMsg_Xml_Rfq
{
	/**
	 * Converts RFQ into XML.
	 * 
	 * @return string
	 */
	public function rfqToMtml (Shipserv_TnMsg_Rfq $rfq)
	{
		$xml = '<?xml version="1.0"?>';
		
		$xml .= '<!DOCTYPE MTML SYSTEM "Mtml.dtd">';
		$xml .= '<MTML>';
		
		foreach ($this->interchange($rfq) as $xi)
		{
			$xml .= $xi->toXml();
		}
		
		$xml .= '</MTML>';
		
		return $xml;
	}
	
	/**
	 * Converts 'Section' block of an RFQ Line Item into XML.
	 *
	 * @param int $liNumber Line item index (starts from 1)
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function section (Shipserv_TnMsg_Rfq $rfq, $liNumber)
	{
		// Fetch line items
		$liArr = $rfq->lineItems;
		
		// Check that requested line item index is in range
		if ($liNumber < 1 || $liNumber > count($liArr))
		{
			throw new Exception("Line item number out of range");
		}
		
		// Fetch requested line item
		$lineItem = $liArr[$liNumber - 1];
		
		// Fetch line item's section block
		$section = $lineItem->section;
		
		// Array to hold XML objects
		$xmlArr = array();
		
		// If there is no section object, or if all fields on object are null, 
		// return empty array
		if ($section === null || $section->isNull())
		{
			return $xmlArr;
		}
		
		// Generate section XML block
		$sectionXml = new Shipserv_TnMsg_Xml_XmlNode('Section');
		
		// Department type
		if ($section->departmentType !== null)
		{
			$sectionXml->addAttribute('DepartmentType', $section->departmentType);
		}
		
		// Description
		if ($section->description !== null)
		{
			$sectionXml->addAttribute('Description', $section->description);
		}
		
		// Drawing number
		if ($section->drawingNumber !== null)
		{
			$sectionXml->addAttribute('DrawingNumber', $section->drawingNumber);
		}
		
		// Manufacturer
		if ($section->manufacturer !== null)
		{
			$sectionXml->addAttribute('Manufacturer', $section->manufacturer);
		}
		
		// Model Number
		if ($section->modelNumber !== null)
		{
			$sectionXml->addAttribute('ModelNumber', $section->modelNumber);
		}
		
		// Name
		if ($section->name !== null)
		{
			$sectionXml->addAttribute('Name', $section->name);
		}
		
		// Serial Number
		if ($section->serialNumber !== null)
		{
			$sectionXml->addAttribute('SerialNumber', $section->serialNumber);
		}
		
		// Note - removed 'Rating' attribute: feel free to add back in if useful
		
		// Add XML to XML array
		$xmlArr[] = $sectionXml;
		
		// Return array
		return $xmlArr;
	}
		
	/**
	 * Converts 'Comments' section of RFQ into XML.
	 * 
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function comments (Shipserv_TnMsg_Rfq $rfq)
	{
		// Fetch comments from RFQ object
		$comments = $rfq->comments;
		
		// Array to hold return XML
		$xmlArr = array();
		
		// If comments is null, return empty array
		if ($comments === null)
		{
			return $xmlArr;
		}
		
		// Purchasing information
		if ($comments->getPurchasingInfo() !== null)
		{
			$pInfXml = new Shipserv_TnMsg_Xml_XmlNode('Comments', array('Qualifier' => 'PUR'));
			$pInfValXml = new Shipserv_TnMsg_Xml_XmlNode('Value');
			$pInfValXml->addText($comments->getPurchasingInfo());
			$pInfXml->addChild($pInfValXml);
			$xmlArr[] = $pInfXml;
		}
		
		// RFQ subject
		if ($comments->subject !== null)
		{
			$subjXml = new Shipserv_TnMsg_Xml_XmlNode('Comments', array('Qualifier' => 'ZAT'));
			$subjValXml = new Shipserv_TnMsg_Xml_XmlNode('Value');
			$subjValXml->addText($comments->subject);
			$subjXml->addChild($subjValXml);
			$xmlArr[] = $subjXml;
		}
		
		// Terms of payment
		if ($comments->termsOfPayment !== null)
		{
			$topXml = new Shipserv_TnMsg_Xml_XmlNode('Comments', array('Qualifier' => 'ZTP'));
			$topValXml = new Shipserv_TnMsg_Xml_XmlNode('Value');
			$topValXml->addText($comments->termsOfPayment);
			$topXml->addChild($topValXml);
			$xmlArr[] = $topXml;
		}
		
		// General terms & conditions
		if ($comments->generalTerms !== null)
		{
			$gtcXml = new Shipserv_TnMsg_Xml_XmlNode('Comments', array('Qualifier' => 'ZTC'));
			$gtcValXml = new Shipserv_TnMsg_Xml_XmlNode('Value');
			$gtcValXml->addText($comments->generalTerms);
			$gtcXml->addChild($gtcValXml);
			$xmlArr[] = $gtcXml;
		}
		
		return $xmlArr;
	}
	
	/**
	 * Converts 'Reference' section of RFQ into XML.
	 * 
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function references (Shipserv_TnMsg_Rfq $rfq)
	{
		// Fetch comments from RFQ object
		$comments = $rfq->comments;
		
		// Array to hold return XML
		$xmlArr = array();
		
		// Human readable RFQ reference code
		if ($comments !== null && $comments->humanRfqRef !== null)
		{
			$humanRefXml = new Shipserv_TnMsg_Xml_XmlNode('Reference', array('Qualifier' => 'UC'));
			$humanRefXml->addAttribute('ReferenceNumber', $comments->humanRfqRef);
			$xmlArr[] = $humanRefXml;
		}
		
		// Control reference
		$controlRefXml = new Shipserv_TnMsg_Xml_XmlNode('Reference', array('Qualifier' => 'AGI'));
		$controlRefXml->addAttribute('ReferenceNumber', $rfq->controlReference);
		$xmlArr[] = $controlRefXml;
		
		return $xmlArr;
	}
	
	/**
	 * Converts Party Billing Address of RFQ into XML.
	 * 
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function billingAddress (Shipserv_TnMsg_Rfq $rfq)
	{
		$addr = null;
		
		if ($rfq->parties !== null)
		{
			$addr = $rfq->parties->billingAddress;
		}
		
		if ($addr === null || $addr->isNull())
		{
			return array();
		}
		
		$addrXml = new Shipserv_TnMsg_Xml_XmlNode('Party', array(
			'City' => $addr->city,
			'CountryCode' => $addr->country,
			'CountrySubEntityIdentification' => $addr->stateOrProvince,
			'PostcodeIdentification' => $addr->zip,
			'Qualifier' => 'BA',
		));
		
		$addrXml->addChild($strAddr = new Shipserv_TnMsg_Xml_XmlNode('StreetAddress'));
		$strAddr->addText($addr->street);
		
		return array($addrXml);
	}
	
	/**
	 * Converts Party blocks of RFQ into XML.
	 * 
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function parties (Shipserv_TnMsg_Rfq $rfq)
	{
		$xmlArr = array();
		
		$parties = $rfq->parties;
		
		if ($parties === null)
		{
			return $xmlArr;
		}
		
		if ($parties->suggestedForwarder !== null)
		{
			$xmlArr[] = new Shipserv_TnMsg_Xml_XmlNode('Party', array(
				'Name' => $parties->suggestedForwarder,
				'Qualifier' => 'FW',
			));
		}
		
		if ($parties->vesselImo !== null || $parties->vesselName !== null || $parties->deliveryPort !== null)
		{
			$partyXml = new Shipserv_TnMsg_Xml_XmlNode('Party', array('Qualifier' => 'UD'));
			
			if ($parties->vesselImo !== null)
			{
				$partyXml->addAttribute('Identification', $parties->vesselImo);
			}
			
			if ($parties->vesselName !== null)
			{
				$partyXml->addAttribute('Name', $parties->vesselName);
			}
			
			if ($parties->deliveryPort !== null)
			{
				$partyXml->addChild($pLocXml = new Shipserv_TnMsg_Xml_XmlNode('PartyLocation'));
				$pLocXml->addAttribute('Port', $parties->deliveryPort);
				$pLocXml->addAttribute('Qualifier', 'ZUC');
			}
			
			// If you want to add a contact (ship-level), do it here by adding
			// test to wrapping if-clause and adding 'Contact' node as child of 'Party'.
			// e.g. <Contact FunctionCode="PD" Name="RFQ Requested By"/>
			
			$xmlArr[] = $partyXml;
		}
		
		if ($parties->deliveryAddress !== null)
		{
			$dPartyXml = new Shipserv_TnMsg_Xml_XmlNode('Party', array('Qualifier' => 'CN'));
			$dPartyXml->addChild($strAddr = new Shipserv_TnMsg_Xml_XmlNode('StreetAddress'));
			$strAddr->addText($parties->deliveryAddress);
			
			$xmlArr[] = $dPartyXml;
		}
		
		foreach ($this->partyBuyer($rfq) as $xi)
		{
			$xmlArr[] = $xi;
		}
		
		foreach ($this->billingAddress($rfq) as $xi)
		{
			$xmlArr[] = $xi;
		}
		
		return $xmlArr;
	}
	
	/**
	 * Converts Buyer Party block of RFQ into XML.
	 * 
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function partyBuyer (Shipserv_TnMsg_Rfq $rfq)
	{
		$xmlArr = array();
		
		$partyBuyer = null;
		
		if ($rfq->parties !== null)
		{
			$partyBuyer = $rfq->parties->buyer;
		}
		
		if ($partyBuyer === null)
		{
			return $xmlArr;
		}
		
		if ($partyBuyer->companyName !== null)
		{
			$partyXml = new Shipserv_TnMsg_Xml_XmlNode('Party', array('Qualifier' => 'BY'));
			$partyXml->addAttribute('Name', $partyBuyer->companyName);
			$xmlArr[] = $partyXml;
		}
		
		if ($partyBuyer->contactName !== null || $partyBuyer->contactPhone !== null || $partyBuyer->contactEmail !== null)
		{
			$partyXml->addChild($contactXml = new Shipserv_TnMsg_Xml_XmlNode('Contact'));
			$contactXml->addAttribute('FunctionCode', 'PD');
			
			if ($partyBuyer->contactName !== null)
			{
				$contactXml->addAttribute('Name', $partyBuyer->contactName);
			}
			
			if ($partyBuyer->contactPhone !== null)
			{
				$contactXml->addChild($comTelXml = new Shipserv_TnMsg_Xml_XmlNode('CommunicationMethod'));
				$comTelXml->addAttribute('Qualifier', 'TE');
				$comTelXml->addAttribute('Number', $partyBuyer->contactPhone);
			}
			
			if ($partyBuyer->contactEmail !== null)
			{
				$contactXml->addChild($comEmlXml = new Shipserv_TnMsg_Xml_XmlNode('CommunicationMethod'));
				$comEmlXml->addAttribute('Qualifier', 'EM');
				$comEmlXml->addAttribute('Number', $partyBuyer->contactEmail);				
			}
		}
		
		return $xmlArr;
	}
	
	/**
	 * Converts RFQ Line Items into XML.
	 * 
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function lineItems (Shipserv_TnMsg_Rfq $rfq)
	{
		$xmlArr = array();
		
		$liNum = 1;
		$liNumMax = count($rfq->lineItems);
		for ($liNum = 1; $liNum <= $liNumMax; $liNum++)
		{
			foreach ($this->lineItem($rfq, $liNum) as $xi)
			{
				$xmlArr[] = $xi;
			}
		}
		
		return $xmlArr;
	}
	
	/**
	 * Converts RFQ Line Item into XML.
	 *
	 * @param int $number Line item index (starts from 1)
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function lineItem ($rfq, $number)
	{
		$liArr = $rfq->lineItems;
		
		if ($number < 1 || $number > count($liArr))
		{
			throw new Exception("Line item number out of range");
		}
		
		$lineItem = $liArr[$number - 1];
		
		// Note - removed these attributes: array('Priority' => 'Medium', 'Quality' => 'Low')
		$liXml = new Shipserv_TnMsg_Xml_XmlNode('LineItem');
		
		$liXml->addAttribute('Number', $number);
		$liXml->addAttribute('Description', $lineItem->decription);
		$liXml->addAttribute('Quantity', $lineItem->quantity);
		
		if ($lineItem->partType !== null)
		{
			$liXml->addAttribute('TypeCode', $lineItem->partType);
		}
		
		if ($lineItem->partCode !== null)
		{
			$liXml->addAttribute('Identification', $lineItem->partCode);
		}
		
		if ($lineItem->uom !== null)
		{
			$liXml->addAttribute('MeasureUnitQualifier', $lineItem->uom);
		}
		
		if ($lineItem->comment !== null)
		{
			$liXml->addChild($comXml = new Shipserv_TnMsg_Xml_XmlNode('Comments'));
			$comXml->addAttribute('Qualifier', 'LIN');
			$comXml->addChild($comValXml = new Shipserv_TnMsg_Xml_XmlNode('Value'));
			$comValXml->addText($lineItem->comment);
		}
		
		foreach ($this->section($rfq, $number) as $xi)
		{
			$liXml->addChild($xi);
		}
		
		return array($liXml);
	}
	
	/**
	 * Forms 'RFQ' block of TN XML RFQ message.
	 * 
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function rfq (Shipserv_TnMsg_Rfq $rfq)
	{
		// Create RFQ node
		// Note - if this is part of a sequence of messages, will need to add MessageNumber attribute
		$rfqXml = new Shipserv_TnMsg_Xml_XmlNode('RequestForQuote', array(
			'MessageReferenceNumber' => $rfq->controlReference,
			'LineItemCount' => count($rfq->lineItems),
			'AssociationAssignedCode' => "MARL10",
			'ControllingAgency' => "UN",
			'CurrencyCode' => "USD",
			'FunctionCode' => "_9",
			'ReleaseNumber' => "96A",
			'VersionNumber' => "D",
			
			// Removed these for minimalism: feel free to add back if useful
			//'Priority' => "High",
		));
		
		// Tax status
		if ($rfq->taxStatus !== null)
		{
			$rfqXml->addAttribute('TaxStatus', $rfq->taxStatus);
		}
		
		// Transport mode code
		if ($rfq->transportMode !== null)
		{
			$rfqXml->addAttribute('TransportModeCode', $rfq->transportMode);
		}
		
		// Delivery terms code
		if ($rfq->deliveryTerms !== null)
		{
			$rfqXml->addAttribute('DeliveryTermsCode', $rfq->deliveryTerms);
		}
		
		// Vessel ETA
		if ($rfq->getVesselEta() !== null)
		{
			$rfqXml->addChild(new Shipserv_TnMsg_Xml_XmlNode('DateTimePeriod', array(
				'FormatQualifier' => "_203",
				'Qualifier' => '_132',
				'Value' => $rfq->getVesselEta(),
			)));
		}
		
		// Vessel ETD
		if ($rfq->getVesselEtd() !== null)
		{
			$rfqXml->addChild(new Shipserv_TnMsg_Xml_XmlNode('DateTimePeriod', array(
				'FormatQualifier' => "_203",
				'Qualifier' => '_133',
				'Value' => $rfq->getVesselEtd(),
			)));
		}
		
		// Requested delivery time
		if ($rfq->getDeliveryRequestedTime() !== null)
		{
			$rfqXml->addChild(new Shipserv_TnMsg_Xml_XmlNode('DateTimePeriod', array(
				'FormatQualifier' => "_203",
				'Qualifier' => '_2',
				'Value' => $rfq->getDeliveryRequestedTime(),
			)));
		}
		
		// Quote required-by time
		if ($rfq->getQotAdviseByTime() !== null)
		{
			$rfqXml->addChild(new Shipserv_TnMsg_Xml_XmlNode('DateTimePeriod', array(
				'FormatQualifier' => "_203",
				'Qualifier' => '_175',
				'Value' => $rfq->getQotAdviseByTime(),
			)));
		}
		
		// Document submitted date
		//if ($rfq->getDocSubmittedTime() !== null)
		//{
		//	$rfqXml->addChild(new Shipserv_TnMsg_Xml_XmlNode('DateTimePeriod', array(
		//		'FormatQualifier' => "_203",
		//		'Qualifier' => '_137',
		//		'Value' => $rfq->getDocSubmittedTime(),
		//	)));
		//}
		
		// Comments block
		foreach ($this->comments($rfq) as $xi)
		{
			$rfqXml->addChild($xi);
		}
		
		// References block
		foreach ($this->references($rfq) as $xi)
		{
			$rfqXml->addChild($xi);
		}
		
		// Parties block
		foreach ($this->parties($rfq) as $xi)
		{
			$rfqXml->addChild($xi);
		}
		
		// Line items
		foreach ($this->lineItems($rfq) as $xi)
		{
			$rfqXml->addChild($xi);
		}
		
		return array($rfqXml);
	}
	
	/**
	 * Forms top-level 'Interchange' XML block for TN RFQ message.
	 * 
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function interchange (Shipserv_TnMsg_Rfq $rfq)
	{
		$iXml = new Shipserv_TnMsg_Xml_XmlNode('Interchange', array(
			'ControlReference' => $rfq->controlReference,
			'Identifier' => "UNOC",
			'PreparationDate' => $rfq->getPreparationDate(),
			'PreparationTime' => $rfq->getPreparationTime(),
			'Recipient' => $rfq->supplierId,
			'RecipientCodeQualifier' => "ZEX",
			'Sender' => $rfq->buyerId,
			'SenderCodeQualifier' => "ZEX",
			'VersionNumber' => "2",
		));
		
		foreach ($this->rfq($rfq) as $xi)
		{
			$iXml->addChild($xi);
		}
		
		return array($iXml);
	}
}
