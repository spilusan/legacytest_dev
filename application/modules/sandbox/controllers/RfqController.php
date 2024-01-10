<?php

class Sandbox_RfqController extends Myshipserv_Controller_Action
{
	public function indexAction ()
	{
		if ($this->getRequest()->isPost())
		{
			$response = self::doRfqFromPost($this->_getAllParams());
			echo htmlentities($response, ENT_COMPAT); echo "<br /><br />";
		}
	}
	
	private static function doRfqFromPost (array $params)
	{
		$client = new Zend_Http_Client();
		
		$client->setUri('http://jonah.myshipserv.com:8680/mtml-rest/docs/10569/outbox');
		
		$client->setConfig(array(
			'maxredirects' => 0,
			'timeout'      => 30));
		
		$client->setMethod(Zend_Http_Client::POST);
		
		$client->setRawData(self::buildRfqXml(), 'text/xml');
		
		$response = $client->request();
		
		return $response->getBody();
	}
	
	private static function buildRfqXml ()
	{
		$controlReference = "RequestForQuote:4325030";
		
		$references = new Mtml_Rfq_References($controlReference, 'Human ref');
		$partyBuyer = new Mtml_Rfq_PartyBuyer('Company name');
		$parties = new Mtml_Rfq_Parties($partyBuyer);
		
		$rfq = new Mtml_Rfq_RequestForQuote
		(
			$controlReference,
			$references,
			$parties
		);
		
		$lineItem = new Mtml_Rfq_LineItem
		(
			1,
			'Description 2',
			20,
			'PA'
		);
		
		$rfq->addLineItem($lineItem);
		
		$comments = new Mtml_Rfq_Comments();
		$comments->setSubject('subject');
		$comments->setPurchasingInformation('General comments');
		
		$rfq->setComments($comments);
		
		$interchangeRfq = new Mtml_Rfq_Interchange
		(
			$controlReference,
			time(),
			10569,
			77988,
			$rfq
		);
		
		return $interchangeRfq->toXml();
	}
}

// Lib code

class Mtml_XmlNode
{
	private $name;
	private $attributes = array();
	private $children = array();
	
	public function __construct ($name, array $attributes = array())
	{
		$this->name = (string) $name;
		
		foreach ($attributes as $k => $v)
		{
			$this->addAttribute($k, $v);
		}
	}
	
	public function addAttribute ($name, $value)
	{
		$this->attributes[$name] = (string) $value;
		return $this;
	}
	
	public function addChild (Mtml_XmlNode $node)
	{
		$this->children[] = $node;
		return $this;
	}
	
	public function addText ($text)
	{
		$this->children[] = (string) $text;
		return $this;
	}
	
	public function toXml ()
	{
		$xml = "<" . $this->name;
		foreach ($this->attributes as $k => $v)
		{
			$vEsc = $this->escapeXml($v);
			$xml .= " $k=\"$vEsc\"";
		}
		$xml .= ">";
		
		foreach ($this->children as $c)
		{
			if (is_string($c))
			{
				$xml .= $this->escapeXml($c);
			}
			else
			{
				$xml .= $c->toXml();
			}
		}
		
		$xml .= "</" . $this->name . ">";
		
		return $xml;
	}
	
	private function escapeXml ($str)
	{
		$escStr = _htmlspecialchars($str, ENT_COMPAT);
		$escStr = str_replace("'", '&apos;', $escStr);
		return $escStr;
	}
}

class Mtml_Rfq_References
{
	private $controlReference;
	private $humanRfqRef;
	
	public function __construct ($controlReference, $humanRfqRef)
	{
		$this->controlReference = (string) $controlReference;
		$this->humanRfqRef = (string) $humanRfqRef;
	}
	
	public function getControlReference ()
	{
		return $this->controlReference;
	}
	
	public function toXmlArr ()
	{
		$xmlArr = array();
		
		$humanRefXml = new Mtml_XmlNode('Reference', array('Qualifier' => 'UC'));
		$humanRefXml->addAttribute('ReferenceNumber', $this->humanRfqRef);
		$xmlArr[] = $humanRefXml;
		
		$controlRefXml = new Mtml_XmlNode('Reference', array('Qualifier' => 'AGI'));
		$controlRefXml->addAttribute('ReferenceNumber', $this->controlReference);
		$xmlArr[] = $controlRefXml;
		
		return $xmlArr;
	}
}

class Mtml_Rfq_Comments
{
	private $subject;
	private $purchasingInfo;
	
	public function __construct ()
	{
		// Do nothing
	}
	
	public function setSubject ($subject)
	{
		$this->subject = (string) $subject;
	}
	
	public function setPurchasingInformation ($purchasingInfo)
	{
		$this->purchasingInfo = (string) $purchasingInfo;
	}
	
	public function toXmlArr ()
	{
		$xmlArr = array();
		
		if ($this->purchasingInfo != '')
		{
			$pInfXml = new Mtml_XmlNode('Comments', array('Qualifier' => 'PUR'));
			$pInfValXml = new Mtml_XmlNode('Value');
			$pInfValXml->addText($this->purchasingInfo);
			$pInfXml->addChild($pInfValXml);
			$xmlArr[] = $pInfXml;
		}
		
		if ($this->subject != '')
		{
			$subjXml = new Mtml_XmlNode('Comments', array('Qualifier' => 'ZAT'));
			$subjValXml = new Mtml_XmlNode('Value');
			$subjValXml->addText($this->subject);
			$subjXml->addChild($subjValXml);
			$xmlArr[] = $subjXml;
		}
		
		return $xmlArr;
	}
}

class Mtml_Rfq_Parties
{
	private $buyer;
	private $deliveryPort;
	private $vesselName;
	private $vesselImo;
	
	public function __construct (Mtml_Rfq_PartyBuyer $buyer)
	{
		$this->buyer = clone $buyer;
	}
	
	public function setDeliveryPort ($deliveryPort)
	{
		$this->deliveryPort = (string) $deliveryPort;
	}
	
	public function setVesselName ($vesselName)
	{
		$this->vesselName = (string) $vesselName;
	}
	
	public function setVesselImo ($vesselImo)
	{
		$this->vesselImo = (string) $vesselImo;
	}
	
	public function toXmlArr ()
	{
		$xmlArr = array();
		
		if ($this->vesselImo != '' || $this->vesselName != '' || $this->deliveryPort != '')
		{
			$partyXml = new Mtml_XmlNode('Party', array('Qualifier' => 'UD'));
			
			if ($this->vesselImo != '')
			{
				$partyXml->addAttribute('Identification', $this->vesselImo);
			}
			
			if ($this->vesselName != '')
			{
				$partyXml->addAttribute('Name', $this->vesselName);
			}
			
			if ($this->deliveryPort != '')
			{
				$partyXml->addChild($pLocXml = new Mtml_XmlNode('PartyLocation'));
				$pLocXml->addAttribute('Port', $this->deliveryPort);
				$pLocXml->addAttribute('Qualifier', 'ZUC');
			}
			
			// If you want to add a contact (ship-level), do it here by adding
			// test to wrapping if-clause and adding 'Contact' node as child of 'Party'.
			// e.g. <Contact FunctionCode="PD" Name="RFQ Requested By"/>
			
			$xmlArr[] = $partyXml;
		}
		
		foreach ($this->buyer->toXmlArr() as $xi)
		{
			$xmlArr[] = $xi;
		}
		
		return $xmlArr;
	}
}

class Mtml_Rfq_PartyBuyer
{
	private $companyName;
	private $contactName;
	private $contactPhone;
	private $contactEmail;
	
	public function __construct ($companyName)
	{
		$this->companyName = (string) $companyName;
	}
	
	public function setContactName ($contactName)
	{
		$this->contactName = (string) $contactName;
	}
	
	public function setContactPhone ($contactPhone)
	{
		$this->contactPhone = (string) $contactPhone;
	}
	
	public function setContactEmail ($contactEmail)
	{
		$this->contactEmail = (string) $contactEmail;
	}
	
	public function toXmlArr ()
	{
		$xmlArr = array();
		
		$partyXml = new Mtml_XmlNode('Party', array('Qualifier' => 'BY'));
		$partyXml->addAttribute('Name', $this->companyName);
		$xmlArr[] = $partyXml;
		
		if ($this->contactName != '' || $this->contactPhone != '' || $this->contactEmail != '')
		{
			$partyXml->addChild($contactXml = new Mtml_XmlNode('Contact'));
			$contactXml->addAttribute('FunctionCode', 'PD');
			
			if ($this->contactName != '')
			{
				$contactXml->addAttribute('Name', $this->contactName);
			}
			
			if ($this->contactPhone != '')
			{
				$contactXml->addChild($comTelXml = new Mtml_XmlNode('CommunicationMethod'));
				$comTelXml->addAttribute('Qualifier', 'TE');
				$comTelXml->addAttribute('Number', $this->contactPhone);
			}
			
			if ($this->contactEmail != '')
			{
				$contactXml->addChild($comEmlXml = new Mtml_XmlNode('CommunicationMethod'));
				$comEmlXml->addAttribute('Qualifier', 'EM');
				$comEmlXml->addAttribute('Number', $this->contactEmail);				
			}
		}
		
		return $xmlArr;
	}
}

class Mtml_Rfq_LineItem
{
	private $number;
	private $decription;
	private $quantity;
	private $partType;
	private $partCode;
	private $uom;
	private $comment;
	private $section;
	
	public function __construct ($number, $decription, $quantity, $uom)
	{
		$this->number = (int) $number;
		$this->decription = (string) $decription;
		$this->quantity = (float) $quantity;
		$this->uom = (string) $uom;
	}
	
	public function setPartNumber ($type, $code)
	{
		$this->partType = (string) $type;
		$this->partCode = (string) $code;
	}
	
	public function setComment ($comment)
	{
		$this->comment = (string) $comment;
	}
	
	public function setSection (Mtml_Rfq_Section $section)
	{
		$this->section = clone $section;
	}
	
	public function getNumber ()
	{
		return $this->number;
	}
	
	public function toXmlArr ()
	{
		// Note - removed these attributes: array('Priority' => 'Medium', 'Quality' => 'Low')
		$liXml = new Mtml_XmlNode('LineItem');
		
		$liXml->addAttribute('Number', $this->number);
		$liXml->addAttribute('Description', $this->decription);
		$liXml->addAttribute('Quantity', $this->quantity);
		
		if ($this->partType != '')
		{
			$liXml->addAttribute('TypeCode', $this->partType);
		}
		
		if ($this->partCode != '')
		{
			$liXml->addAttribute('Identification', $this->partCode);
		}
		
		$liXml->addAttribute('MeasureUnitQualifier', $this->uom);
		
		if ($this->comment != '')
		{
			$liXml->addChild($comXml = new Mtml_XmlNode('Comments'));
			$comXml->addAttribute('Qualifier', 'LIN');
			$comXml->addChild($comValXml = new Mtml_XmlNode('Value'));
			$comValXml->addText($this->comment);
		}
		
		if ($this->section)
		{
			foreach ($this->section->toXmlArr() as $xi)
			{
				$liXml->addChild($xi);
			}
		}
		
		return array($liXml);
	}
}

class Mtml_Rfq_Section
{
	private $departmentType;
	private $description;
	private $drawingNumber;
	private $manufacturer;
	private $modelNumber;
	private $name;
	private $serialNumber;
	
	public function __construct ()
	{
		// Do nothing
	}
	
	public function setDepartmentType ($departmentType)
	{
		$this->departmentType = (string) $departmentType;
	}
	
	public function setDescription ($description)
	{
		$this->description = (string) $description;
	}
	
	public function setDrawingNumber ($drawingNumber)
	{
		$this->drawingNumber = (string) $drawingNumber;
	}
	
	public function setManufacturer ($manufacturer)
	{
		$this->manufacturer = (string) $manufacturer;
	}
	
	public function setModelNumber ($modelNumber)
	{
		$this->modelNumber = (string) $modelNumber;
	}
	
	public function setName ($name)
	{
		$this->name = (string) $name;
	}
	
	public function setSerialNumber ($serialNumber)
	{
		$this->serialNumber = (string) $serialNumber;
	}
	
	public function toXmlArr ()
	{
		$xmlArr = array();
		
		// Note - removed 'Rating' attribute: feel free to add back in if useful
		$sectionXml = new Mtml_XmlNode('Section');
		
		foreach (array(
			'departmentType',
			'description',
			'drawingNumber',
			'manufacturer',
			'modelNumber',
			'name',
			'serialNumber') as $pName)
		{
			if ($this->$pName != '')
			{
				$sectionXml->addAttribute(ucfirst($pName), $this->$pName);
			}
		}
		
		$xmlArr[] = $sectionXml;
		
		return $xmlArr;
	}
}

class Mtml_Rfq_RequestForQuote
{
	private $messageReferenceNumber;
	private $references;
	private $parties;
	private $comments;
	private $lineItems = array();
	
	public function __construct ($messageReferenceNumber, Mtml_Rfq_References $references, Mtml_Rfq_Parties $parties)
	{
		$this->messageReferenceNumber = (string) $messageReferenceNumber;
		
		$this->references = clone $references;
		if ($this->references->getControlReference() != $this->messageReferenceNumber)
		{
			throw new Exception("Reference's controlReference property does not match RFQ's messageReferenceNumber.");
		}
		
		$this->parties = clone $parties;
	}
	
	public function setComments (Mtml_Rfq_Comments $comments)
	{
		$this->comments = clone $comments;
	}
	
	public function addLineItem (Mtml_Rfq_LineItem $lineItem)
	{
		$myLineItem = clone $lineItem;
		
		if ($myLineItem->getNumber() != (count($this->lineItems) + 1))
		{
			throw new Exception("LineItem's number does not match sequence in RFQ.");
		}
		
		$this->lineItems[] = $myLineItem;
	}
	
	public function getMessageReferenceNumber ()
	{
		return $this->messageReferenceNumber;
	}
	
	public function toXmlArr ()
	{
		// Note - if this is part of a sequence of messages, will need to add MessageNumber attribute
		
		$rfqXml = new Mtml_XmlNode('RequestForQuote', array(
			'AssociationAssignedCode' => "MARL10",
			'ControllingAgency' => "UN",
			'CurrencyCode' => "USD",
			'FunctionCode' => "_9",
			'ReleaseNumber' => "96A",
			'VersionNumber' => "D",
			
			// Removed these for minimalism: feel free to add back if useful
			//'DeliveryTermsCode' => "EXW",
			//'Priority' => "High",
			//'TaxStatus' => "Exempt",
			//'TransportModeCode' => "_4",
		));
		
		$rfqXml->addAttribute('MessageReferenceNumber', $this->messageReferenceNumber);
		$rfqXml->addAttribute('LineItemCount', count($this->lineItems));
		
		if ($this->comments)
		{
			foreach ($this->comments->toXmlArr() as $xi)
			{
				$rfqXml->addChild($xi);
			}
		}
		
		foreach ($this->references->toXmlArr() as $xi)
		{
			$rfqXml->addChild($xi);
		}
		
		foreach ($this->parties->toXmlArr() as $xi)
		{
			$rfqXml->addChild($xi);
		}
		
		foreach ($this->lineItems as $li)
		{
			foreach ($li->toXmlArr() as $xi)
			{
				$rfqXml->addChild($xi);
			}
		}
		
		return array($rfqXml);
	}
}

class Mtml_Rfq_Interchange
{
	private $controlReference;
	private $preparationDate;
	private $preparationTime;
	private $buyerId;
	private $supplierId;
	private $rfq;
	
	public function __construct ($controlReference, $preparationTimestamp, $buyerId, $supplierId, Mtml_Rfq_RequestForQuote $rfq)
	{
		$this->controlReference = (string) $controlReference;
		$this->preparationDate = date('Y-M-d', $preparationTimestamp);
		$this->preparationTime = date('h:i', $preparationTimestamp);
		$this->buyerId = (int) $buyerId;
		$this->supplierId = (int) $supplierId;
		
		$this->rfq = clone $rfq;
		
		if ($this->rfq->getMessageReferenceNumber() != $this->controlReference)
		{
			throw new Exception("RFQ's messageReferenceNumber does not match Interchange's controlReference.");
		}
	}
	
	public function toXmlArr ()
	{
		$iXml = new Mtml_XmlNode('Interchange', array(
			'ControlReference' => $this->controlReference,
			'Identifier' => "UNOC",
			'PreparationDate' => $this->preparationDate,
			'PreparationTime' => $this->preparationTime,
			'Recipient' => $this->supplierId,
			'RecipientCodeQualifier' => "ZEX",
			'Sender' => $this->buyerId,
			'SenderCodeQualifier' => "ZEX",
			'VersionNumber' => "2",
		));
		
		foreach ($this->rfq->toXmlArr() as $xi)
		{
			$iXml->addChild($xi);
		}
		
		return array($iXml);
	}
	
	public function toXml ()
	{
		$xml .= '<?xml version="1.0"?>';
		$xml .= '<!DOCTYPE MTML SYSTEM "Mtml.dtd">';
		$xml .= '<MTML>';
		
		foreach ($this->toXmlArr() as $xi)
		{
			$xml .= $xi->toXml();
		}
		
		$xml .= '</MTML>';
		
		return $xml;
	}
}
