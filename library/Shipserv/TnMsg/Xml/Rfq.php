<?php

class Shipserv_TnMsg_Xml_Rfq
{	
	/**
	 * Converts RFQ into XML.
	 * 
	 * @return string
	 */
	public function rfqToMtml (Shipserv_TnMsg_Xml_RfqHelper $rfq)
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
	private function section (Shipserv_TnMsg_Xml_RfqLiHelper $lineItem)
	{
		$xmlArr = array();
		if (!$lineItem->sectionIsNull())
		{
			$sectionXml = new Shipserv_TnMsg_Xml_XmlNode('Section');
			$xmlArr[] = $sectionXml;
			
			if ($lineItem->getConfDeptType() !== null)
			{
				$sectionXml->addAttribute('DepartmentType', $lineItem->getConfDeptType());
			}
			
			if ($lineItem->getConfDesc() !== null)
			{
				$sectionXml->addAttribute('Description', $lineItem->getConfDesc());
			}
			
			if ($lineItem->getConfDrawingNo() !== null)
			{
				$sectionXml->addAttribute('DrawingNumber', $lineItem->getConfDrawingNo());
			}
			
			if ($lineItem->getConfManufacturer() !== null)
			{
				$sectionXml->addAttribute('Manufacturer', $lineItem->getConfManufacturer());
			}
			
			if ($lineItem->getConfModelNo() !== null)
			{
				$sectionXml->addAttribute('ModelNumber', $lineItem->getConfModelNo());
			}
			
			if ($lineItem->getConfName() !== null)
			{
				$sectionXml->addAttribute('Name', $lineItem->getConfName());
			}
			
			if ($lineItem->getConfSerialNo() !== null)
			{
				$sectionXml->addAttribute('SerialNumber', $lineItem->getConfSerialNo());
			}
			
			// Note - removed 'Rating' attribute: feel free to add back in if useful
		}
		
		return $xmlArr;
	}
		
	/**
	 * Converts 'Comments' section of RFQ into XML.
	 * 
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function comments (Shipserv_TnMsg_Xml_RfqHelper $rfq)
	{
		$xmlArr = array();
		
		// Purchasing information
		if ($rfq->getBuyerComments() !== null)
		{
			$pInfXml = new Shipserv_TnMsg_Xml_XmlNode('Comments', array('Qualifier' => 'PUR'));
			$pInfValXml = new Shipserv_TnMsg_Xml_XmlNode('Value');
			$pInfValXml->addText($rfq->getBuyerComments());
			$pInfXml->addChild($pInfValXml);
			$xmlArr[] = $pInfXml;
		}
		
		// RFQ subject
		if ($rfq->getSubject() !== null)
		{
			$subjXml = new Shipserv_TnMsg_Xml_XmlNode('Comments', array('Qualifier' => 'ZAT'));
			$subjValXml = new Shipserv_TnMsg_Xml_XmlNode('Value');
			$subjValXml->addText($rfq->getSubject());
			$subjXml->addChild($subjValXml);
			$xmlArr[] = $subjXml;
		}
		
		// Terms of payment
		if ($rfq->getTermsOfPayment() !== null)
		{
			$topXml = new Shipserv_TnMsg_Xml_XmlNode('Comments', array('Qualifier' => 'ZTP'));
			$topValXml = new Shipserv_TnMsg_Xml_XmlNode('Value');
			$topValXml->addText($rfq->getTermsOfPayment());
			$topXml->addChild($topValXml);
			$xmlArr[] = $topXml;
		}
		
		// General terms & conditions
		if ($rfq->getGeneralTerms() !== null)
		{
			$gtcXml = new Shipserv_TnMsg_Xml_XmlNode('Comments', array('Qualifier' => 'ZTC'));
			$gtcValXml = new Shipserv_TnMsg_Xml_XmlNode('Value');
			$gtcValXml->addText($rfq->getGeneralTerms());
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
	private function references (Shipserv_TnMsg_Xml_RfqHelper $rfq)
	{
		// Array to hold return XML
		$xmlArr = array();
		
		// Human readable RFQ reference code
		if ($rfq->getHumanRef() !== null)
		{
			$humanRefXml = new Shipserv_TnMsg_Xml_XmlNode('Reference', array('Qualifier' => 'UC'));
			$humanRefXml->addAttribute('ReferenceNumber', $rfq->getHumanRef());
			$xmlArr[] = $humanRefXml;
		}
		
		// Control reference
		$controlRefXml = new Shipserv_TnMsg_Xml_XmlNode('Reference', array('Qualifier' => 'UC'));
		$controlRefXml->addAttribute('ReferenceNumber', $rfq->getControlRef());
		$xmlArr[] = $controlRefXml;
		
		return $xmlArr;
	}
	
	/**
	 * Converts Party Billing Address of RFQ into XML.
	 * 
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function billingAddress (Shipserv_TnMsg_Xml_RfqHelper $rfq)
	{
		$res = array();
		
		$addrXml = new Shipserv_TnMsg_Xml_XmlNode('Party', array(
			'Qualifier' => 'BA',
		));
		
		if ($rfq->getBillingCity() !== null) $addrXml->addAttribute('City', $rfq->getBillingCity());
		if ($rfq->getBillingCountry() !== null) $addrXml->addAttribute('CountryCode', $rfq->getBillingCountry());
		if ($rfq->getBillingProvince() !== null) $addrXml->addAttribute('CountrySubEntityIdentification', $rfq->getBillingProvince());
		if ($rfq->getBillingZip() !== null) $addrXml->addAttribute('PostcodeIdentification', $rfq->getBillingZip());
		
		if ($rfq->getBillingAddress() !== null)
		{
			$addrXml->addChild($strAddr = new Shipserv_TnMsg_Xml_XmlNode('StreetAddress'));
			$strAddr->addText($rfq->getBillingAddress());
		}
		
		// If any attributes have been added (in addition to the default qualifier) or if a child has been added, form this node.
		if (count($addrXml->getAttributes()) > 1 || count($addrXml->getChildren())) $res[] = $addrXml;
		
		return $res;
	}
	
	/**
	 * Converts Party blocks of RFQ into XML.
	 * 
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function parties (Shipserv_TnMsg_Xml_RfqHelper $rfq)
	{
		$xmlArr = array();
		
		if ($rfq->getSuggestedShipper() !== null)
		{
			$xmlArr[] = new Shipserv_TnMsg_Xml_XmlNode('Party', array(
				'Name' => $rfq->getSuggestedShipper(),
				'Qualifier' => 'FW',
			));
		}
		
		if ($rfq->getImo() !== null || $rfq->getVesselName() !== null || $rfq->getDeliveryPort() !== null)
		{
			$partyXml = new Shipserv_TnMsg_Xml_XmlNode('Party', array('Qualifier' => 'UD'));
			
			if ($rfq->getImo() !== null)
			{
				$partyXml->addAttribute('Identification', $rfq->getImo());
			}
			
			if ($rfq->getVesselName() !== null)
			{
				$partyXml->addAttribute('Name', $rfq->getVesselName());
			}
			
			if ($rfq->getDeliveryPort() !== null)
			{
				$partyXml->addChild($pLocXml = new Shipserv_TnMsg_Xml_XmlNode('PartyLocation'));
				$pLocXml->addAttribute('Port', $rfq->getDeliveryPort());
				$pLocXml->addAttribute('Qualifier', 'ZUC');
			}
			
			// If you want to add a contact (ship-level), do it here by adding
			// test to wrapping if-clause and adding 'Contact' node as child of 'Party'.
			// e.g. <Contact FunctionCode="PD" Name="RFQ Requested By"/>
			
			$xmlArr[] = $partyXml;
		}
		
		if ($rfq->getDeliveryAddress() !== null)
		{
			$dPartyXml = new Shipserv_TnMsg_Xml_XmlNode('Party', array('Qualifier' => 'CN'));
			$dPartyXml->addChild($strAddr = new Shipserv_TnMsg_Xml_XmlNode('StreetAddress'));
			$strAddr->addText($rfq->getDeliveryAddress());
			
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
	private function partyBuyer (Shipserv_TnMsg_Xml_RfqHelper $rfq)
	{
		$xmlArr = array();
		
		if ($rfq->getBuyerCompanyName() !== null || $rfq->getContactName() !== null || $rfq->getContactPhone() !== null || $rfq->getContactEmail() !== null)
		{
			$partyXml = new Shipserv_TnMsg_Xml_XmlNode('Party', array('Qualifier' => 'BY'));
			$xmlArr[] = $partyXml;
			
			if ($rfq->getBuyerCompanyName() !== null)
			{
				$partyXml->addAttribute('Name', $rfq->getBuyerCompanyName());
			}
			
			if ($rfq->getContactName() !== null || $rfq->getContactPhone() !== null || $rfq->getContactEmail() !== null)
			{
				$partyXml->addChild($contactXml = new Shipserv_TnMsg_Xml_XmlNode('Contact'));
				$contactXml->addAttribute('FunctionCode', 'PD');
				
				if ($rfq->getContactName() !== null)
				{
					$contactXml->addAttribute('Name', $rfq->getContactName());
				}
				
				if ($rfq->getContactPhone() !== null)
				{
					$contactXml->addChild($comTelXml = new Shipserv_TnMsg_Xml_XmlNode('CommunicationMethod'));
					$comTelXml->addAttribute('Qualifier', 'TE');
					$comTelXml->addAttribute('Number', $rfq->getContactPhone());
				}
				
				if ($rfq->getContactEmail() !== null)
				{
					$contactXml->addChild($comEmlXml = new Shipserv_TnMsg_Xml_XmlNode('CommunicationMethod'));
					$comEmlXml->addAttribute('Qualifier', 'EM');
					$comEmlXml->addAttribute('Number', $rfq->getContactEmail());				
				}
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
	private function lineItems (Shipserv_TnMsg_Xml_RfqHelper $rfq)
	{
		$liXmlArr = array();
		foreach ($rfq->getLineItems() as $lineItem)
		{
			// Note - removed these attributes: array('Priority' => 'Medium', 'Quality' => 'Low')
			$liXml = new Shipserv_TnMsg_Xml_XmlNode('LineItem');
			
			$liXml->addAttribute('Number', $lineItem->getLineItemNo());
			$liXml->addAttribute('Description', $lineItem->getProductDesc());
			$liXml->addAttribute('Quantity', $lineItem->getQuantity());
			
			if ($lineItem->getIdType() !== null)
			{
				$liXml->addAttribute('TypeCode', $lineItem->getIdType());
			}
			
			if ($lineItem->getIdCode() !== null)
			{
				$liXml->addAttribute('Identification', $lineItem->getIdCode());
			}
			
			if ($lineItem->getUnit() !== null)
			{
				$liXml->addAttribute('MeasureUnitQualifier', $lineItem->getUnit());
			}
			
			if ($lineItem->getComment() !== null)
			{
				$liXml->addChild($comXml = new Shipserv_TnMsg_Xml_XmlNode('Comments'));
				$comXml->addAttribute('Qualifier', 'LIN');
				$comXml->addChild($comValXml = new Shipserv_TnMsg_Xml_XmlNode('Value'));
				$comValXml->addText($lineItem->getComment());
			}
			
			foreach ($this->section($lineItem) as $xi)
			{
				$liXml->addChild($xi);
			}
			
			$liXmlArr[] = $liXml;
		}
		return $liXmlArr;
	}
	
	/**
	 * Forms 'RFQ' block of TN XML RFQ message.
	 * 
	 * @return array of Shipserv_TnMsg_Xml_XmlNode
	 */
	private function rfq (Shipserv_TnMsg_Xml_RfqHelper $rfq)
	{
		// Create RFQ node
		// Note - if this is part of a sequence of messages, will need to add MessageNumber attribute
		$rfqXml = new Shipserv_TnMsg_Xml_XmlNode('RequestForQuote', array(
			'MessageReferenceNumber' => $rfq->getControlRef(),
			'LineItemCount' => $rfq->getLineItemCount(),
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
		if ($rfq->getTaxStatus() !== null)
		{
			$rfqXml->addAttribute('TaxStatus', $rfq->getTaxStatus());
		}
		
		// Transport mode code
		if ($rfq->getTransportMode() !== null)
		{
			$rfqXml->addAttribute('TransportModeCode', $rfq->getTransportMode());
		}
		
		// Delivery terms code
		if ($rfq->getDeliveryTerms() !== null)
		{
			$rfqXml->addAttribute('DeliveryTermsCode', $rfq->getDeliveryTerms());
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
		if ($rfq->getDeliveryTime() !== null)
		{
			$rfqXml->addChild(new Shipserv_TnMsg_Xml_XmlNode('DateTimePeriod', array(
				'FormatQualifier' => "_203",
				'Qualifier' => '_2',
				'Value' => $rfq->getDeliveryTime(),
			)));
		}
		
		// Quote required-by time
		if ($rfq->getAdviseByTime() !== null)
		{
			$rfqXml->addChild(new Shipserv_TnMsg_Xml_XmlNode('DateTimePeriod', array(
				'FormatQualifier' => "_203",
				'Qualifier' => '_175',
				'Value' => $rfq->getAdviseByTime(),
			)));
		}
		
		// Document submitted date
		//if ($rfq->getDocSubmittedTime() !== null)
		//{
		//	$rfqXml->addChild(new Shipserv_TnMsg_Xml_XmlNode('DateTimePeriod', array(
		//		'FormatQualifier' => "_203",
		//		'Qualifier' => '_137',
		//		'Value' => ...,
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
	private function interchange (Shipserv_TnMsg_Xml_RfqHelper $rfq)
	{
		$iXml = new Shipserv_TnMsg_Xml_XmlNode('Interchange', array(
			'ControlReference' => $rfq->getControlRef(),
			'Identifier' => "UNOC",
			'PreparationDate' => $rfq->getPreparationDate(),
			'PreparationTime' => $rfq->getPreparationTime(),
			'Recipient' => $rfq->getRecipients(),
			'RecipientCodeQualifier' => "ZEX",
			'Sender' => $rfq->getSender(),
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
