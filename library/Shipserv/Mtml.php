<?php
/**
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Mtml extends Shipserv_Object
{
	public $id;
	public $name;
	public $xmlInString;
	
	public static function createFromString( $string )
	{
		$object = new self;
		$object->xmlInString = $string;
		return $object;
	}
	
	public function getContent()
	{
		return $this->xmlInString;
	}
	public function getBuyer()
	{
		return $this->buyer;
	}
		
	public function getSupplier()
	{
		return $this->supplier;
	}
	
	public function sanitise( $params )
	{
		$params = _htmlspecialchars( trim($params) );
		return $params;
	}
	
	public static function convertDateToMtmlDate( $input )
	{
		$data = explode("/", $input);
		return 
			$data[2] .
			str_pad($data[0], 2, "0", STR_PAD_LEFT) .
			str_pad($data[1], 2, "0", STR_PAD_LEFT);
		
	}
	
	public static function convertDateToOracleDate( $input )
	{
		$object = new DateTime();
		$data = explode("/", $input);
		
		$object->setDate($data[2], $data[0], $data[1]);
		
		return strtoupper($object->format('d-M-Y'));
	}
	
	public static function createFromParam( $params )
	{
		// parse the section/line items
		foreach((array)$params['section'] as $section)
		{			
			$lineItem['sDescription'] = $section['lidDescription'];
			$lineItem['sManufacturer'] = $section['lidManufacturer'];
			$lineItem['sModel'] = $section['lidModel'];
			$lineItem['sSerialNumber'] = $section['lidSerialNumber'];
			$lineItem['sType'] = $section['lidType'];
			$lineItem['sDrawingNumber'] = $section['lidDrawingNumber'];
			$lineItem['sFor'] = $section['lidFor'];
			$lineItem['sRating'] = $section['lidRating'];
				
			foreach( $section['lineItems'] as $li)
			{
				$lineItem['lQuantity'] 		= trim($li['lQuantity']);
				$lineItem['lUoM'] 			= trim($li['lUoM']);
				$lineItem['lPartType'] 		= trim($li['lPartType']);
				$lineItem['lPartNo'] 		= trim($li['lPartNo']);
				$lineItem['lDescription'] 	= trim($li['lDescription']);
				$lineItem['lComments'] 		= trim($li['lComments']);
				
				extract($lineItem);
				
				if( $lQuantity != "" || $lUoM != "" || $lPartType != "" || $lPartNo != "" || $lDescription != "" || $lComments != "" )
				{
					++$lineItemNo;
						
					$templateForLineItems = 
'
	<LineItem Number="'.$lineItemNo.'" Quantity="'.$lQuantity.'" MeasureUnitQualifier="'.self::sanitise($lUoM).'" TypeCode="'.self::sanitise($lPartType).'" Identification="'.self::sanitise($lPartNo).'" Description="'.self::sanitise($lDescription).'">
		<Comments Qualifier="LIN">
	    	<Value><![CDATA['.$lComments.']]></Value>
	    </Comments>
	    <Section Description="'.self::sanitise($sDescription).'" Manufacturer="'.self::sanitise($sManufacturer).'" ModelNumber="'.self::sanitise($sModel).'" SerialNumber="'.self::sanitise($sSerialNumber).'" DepartmentType="'.self::sanitise($sType).'" DrawingNumber="'.self::sanitise($sDrawingNumber).'" Name="'.self::sanitise($sFor).'" Rating="'.self::sanitise($sRating).'" />
	</LineItem>
';
					$data['lineItems'][] = $templateForLineItems;
				}
			}	
		}
		
		// handling ZERO line items
		if( count($data['lineItems']) == 0 )
		{
			$templateForLineItems = 
'
	<LineItem Number="1" Quantity="1" MeasureUnitQualifier="" TypeCode="" Identification="" Description="">
		<Comments Qualifier="LIN">
	    	<Value><![CDATA[]]></Value>
	    </Comments>
	    <Section Description="" Manufacturer="" ModelNumber="" SerialNumber="" DepartmentType="" DrawingNumber=""/>
	</LineItem>
';
			$data['lineItems'][] = $templateForLineItems;
		}
		
		// joining the XML for line items
		$outputForLineItems = implode("\n", $data['lineItems']);
		$totalLineItems = count($data['lineItems']);
		
		extract( $params );
		
		if( $bCompanyName == "" )
		{
			$rRfqSubject = "RFQ from " . $bEmail . " - " . $rRfqSubject;
		}
		else
		{
			$rRfqSubject = "RFQ from " . $bCompanyName . " - " . $rRfqSubject;
		}
		
		if( $vVesselETA != "" 	&& strstr(strtoupper($vVesselETA), 	'MM') === false ) 	$dateTimePeriods[] = '<DateTimePeriod FormatQualifier="_102" Qualifier="_132" Value="' 		. self::convertDateToMtmlDate($vVesselETA) .'"/>';
		if( $vVesselETD != "" 	&& strstr(strtoupper($vVesselETD), 	'MM') === false ) 	$dateTimePeriods[] = '<DateTimePeriod FormatQualifier="_102" Qualifier="_133" Value="' 		. self::convertDateToMtmlDate($vVesselETD) .'"/>';
		if( $dDeliveryBy != "" 	&& strstr(strtoupper($dDeliveryBy), 'MM') === false ) 	$dateTimePeriods[] = '<DateTimePeriod FormatQualifier="_102" Qualifier="_2" Value="' 		. self::convertDateToMtmlDate($dDeliveryBy) .'"/>';
		if( $rReplyBy != "" 	&& strstr(strtoupper($rReplyBy), 	'MM') === false ) 		$dateTimePeriods[] = '<DateTimePeriod FormatQualifier="_102" Qualifier="_175" Value="' 	. self::convertDateToMtmlDate($rReplyBy) .'"/>';
		$dateTimePeriods = ( count($dateTimePeriods) > 0 ) ? implode("\n", $dateTimePeriods) : "";
		
		if( $lBuyerComments != "" ) $buyerComments[] = '<Comments Qualifier="PUR"><Value><![CDATA[' . $lBuyerComments . ']]></Value></Comments>';
		if( $tPaymentTerms != "" ) 	$buyerComments[] = '<Comments Qualifier="ZTP"><Value><![CDATA[' . $tPaymentTerms . ']]></Value></Comments>';
		if( $tGeneralTC != "" ) 	$buyerComments[] = '<Comments Qualifier="ZTC"><Value><![CDATA[' . $tGeneralTC . ']]></Value></Comments>';
		if( $rRfqSubject != "" ) 	$buyerComments[] = '<Comments Qualifier="ZAT"><Value><![CDATA[' . $rRfqSubject . ']]></Value></Comments>';
		$buyerComments = ( count($buyerComments) > 0 ) ? implode("\n", $buyerComments) : "";
		
		if( $dTransportMode != "" ) $transportMode = 'TransportModeCode="' . trim($dTransportMode) . '"';
		
		$xml = 
'<?xml version="1.0" encoding="utf-8"?>
<MTML>
  <Interchange ControlReference="[Reqd_CtrlRef]" Sender="[Reqd_SenderID]" Recipient="[Reqd_RecptID]">
    <RequestForQuote MessageReferenceNumber="[Reqd_MsgRefNum]" LineItemCount="'.$totalLineItems.'" FunctionCode="_9" ' . $transportMode . '>
      
      '.$dateTimePeriods.'
	  '.$buyerComments.'
       
      <Reference Qualifier="UC" ReferenceNumber="'.self::sanitise($rRfqReference).'"/>
		
      <!--Vessel Details -->
      <Party Qualifier="UD" Name="'.self::sanitise($vVesselName).'" Identification="'.self::sanitise($vImoNumber).'">
        <PartyLocation Qualifier="ZUC" Port="'.self::sanitise($dDeliveryPort).'"/>
      </Party>
		
      <!--Buyer Details -->
      <Party Qualifier="BY" Name="'.self::sanitise($bCompanyName).'" 
      	City="'.self::sanitise($bCity).'" 
      	CountrySubEntityIdentification="'.self::sanitise($bProvince).'" 
      	PostcodeIdentification="'.self::sanitise($bPostcode).'" 
      	CountryCode="'.self::sanitise($bCountry).'">
        <StreetAddress><![CDATA['.$bAddress1.']]></StreetAddress>
        <StreetAddress><![CDATA['.$bAddress2.']]></StreetAddress>
        <Contact Name="'.self::sanitise($bName).'">
          <CommunicationMethod Qualifier="TE" Number="'.self::sanitise($bPhone).'"/>
          <CommunicationMethod Qualifier="EM" Number="'.self::sanitise($bEmail).'"/>
        </Contact>
      </Party>
		
      <!--Delivery Details-->
      <Party Qualifier="CN" Name="'.self::sanitise($dDeliveryTo).'" 
      	City="'.self::sanitise($dCity).'" 
      	CountrySubEntityIdentification="'.self::sanitise($dProvince).'" 
      	PostcodeIdentification="'.self::sanitise($dPostcode).'" 
      	CountryCode="'.self::sanitise($dCountry).'">
        <StreetAddress><![CDATA['.$dAddress1.']]></StreetAddress>
        <StreetAddress><![CDATA['.$dAddress2.']]></StreetAddress>
      </Party>
      <PackagingInstructions RelatedInformationCode="34">
        <Value><![CDATA['.$dPackagingInstructions.']]></Value>
      </PackagingInstructions>
		
      <!--Line Items-->
     '.$outputForLineItems.'
      
    </RequestForQuote>
  </Interchange>
</MTML>
';
		$mtml = new self;
		$mtml->xmlInString = $xml;
		return $mtml;
	}
	
	public function getXml()
	{
		$xml = simplexml_load_string($this->xmlInString,null, LIBXML_NOCDATA);
		
		return $xml;
	}
}
