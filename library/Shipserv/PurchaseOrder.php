<?php

/**
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_PurchaseOrder extends Shipserv_Object
{
    const
        TABLE_NAME      = 'PURCHASE_ORDER',
        COL_ID          = 'ORD_INTERNAL_REF_NO',
        COL_QUOTE_ID    = 'ORD_QOT_INTERNAL_REF_NO',
        COL_STATUS      = 'ORD_STS',
        COL_DATE        = 'ORD_CREATED_DATE',
        COL_DATE_SUB    = 'ORD_SUBMITTED_DATE',
        COL_COUNT       = 'ORD_COUNT',
        COL_VESSEL_IMO  = 'ORD_IMO_NO',
        COL_TOTAL_COST  = 'ORD_TOTAL_COST',
        COL_CURRENCY    = 'ORD_CURRENCY',
        COL_SUPPLIER_ID = 'ORD_SPB_BRANCH_CODE',
        COL_BUYER_ID    = 'ORD_BYB_BUYER_BRANCH_CODE',
        COL_BUYER_ORG_ID = 'ORD_BYB_BYO_BUYER_ORG_CODE',
        COL_REF_NO      = 'ORD_REF_NO',
        COL_VESSEL_NAME = 'ORD_VESSEL_NAME'
    ;

    const
        STATUS_SUBMITTED = 'SUB',
        STATUS_DRAFT     = 'DFT',
        STATUS_DELETED   = 'DEL'
    ;

	public $quote;

	public function __construct ($data = null)
	{
		// populate the supplier object
		if (is_array($data))
		{
			foreach ($data as $name => $value)
			{
				$this->{$name} = $value;
			}
		}
	}

	protected static function createObjectFromDb( $data )
	{
		$object = new self($data);

		return $object;
	}

	public static function getInstanceById( $id )
	{
		$sql = "SELECT * FROM purchase_order WHERE ord_internal_ref_no=:docId";
		$row = parent::getDb()->fetchAll($sql, array('docId' => $id));
        if( count($row) == 0 ) return false;
		$data = parent::camelCase($row[0]);
		return self::createObjectFromDb($data);
	}


	public function getUrl()
	{
		$users = Shipserv_User::getActiveUserBySpbBranchCode($this->ordSpbBranchCode);
		$url = "http://". $this->getHostname() . "/printables/app/print?docid=". $this->ordInternalRefNo . "&usercode=" . $users[0]['USR_USER_CODE'] . "&branchcode=" . $this->ordSpbBranchCode . "&doctype=ORD&custtype=supplier&md5=" . $users[0]['USR_MD5_CODE'] . "";
		return $url;
	}

	/**
	 * Added by Yuriy Akopov on 2015-12-14 basing on Shipserv_Rfq::getCancelUrl()
	 *
	 * @return string
	 */
	public function getCancelUrl(){
		$supplier = $this->getSupplier();
		$hash = md5('ordInternalRefNo=' . $this->rfqInternalRefNo . '&spbBranchCode=' . $supplier->tnid);
		return $url = "http://". $this->getHostname() . "/buyer/cancel-rfq?doc=ord&id=" . $this->ordInternalRefNo . "&h=" . $hash . "&s=" . $supplier->tnid;
	}

    public function getDeclineUrl(){
        $supplier = $this->getSupplier();
        $hash = md5('ordInternalRefNo=' . $this->ordInternalRefNo . '&spbBranchCode=' . $supplier->tnid);
        return $url = "http://". $this->getHostname() . "/supplier/decline?doc=ord&id=" . $this->ordInternalRefNo . "&h=" . $hash . "&s=" . $supplier->tnid;

    }

	public static function createFromQuote( $quote )
	{
		$object = new self();
		$object->quote = $quote;
		return $object;
	}

	public function getPurchaseOrderConfirmation()
	{
		return Shipserv_PurchaseOrderConfirmation::getInstanceById($this->ordInternalRefNo);
	}

	public function getMtml()
	{

		$config 			= $this->getConfig();
		$controlReference 	= $this->quote->qotInternalRefNo . time();
		$supplierTnid 		= $this->quote->qotSpbBranchCode;
		$pagesBuyerTnid 	= $config->shipserv->pagesrfq->buyerId;
		$rfq 				= $this->quote->getRfq();
		$enquiry			= $rfq->getEnquiry();
		$supplier			= Shipserv_Supplier::getInstanceById($this->quote->qotSpbBranchCode, "", true);


		foreach($this->quote->getLineItem() as $li)
		{
			$lineItems[] = '
		<LineItem Identification="' . $li['QLI_ID_CODE'] . '" MeasureUnitQualifier="' . $li['QLI_UNIT'] . '" Number="' . $li['QLI_LINE_ITEM_NUMBER'] . '" Quantity="' . $li['QLI_QUANTITY'] . '" Description="' . Shipserv_Mtml::sanitise($li['QLI_DESC']) . '">
			<PriceDetails Qualifier="CAL" Value="' . (($li['QLI_UNIT_COST']!="")?$li['QLI_UNIT_COST']:0) . '" TypeCode="QT" TypeQualifier="GRP" />

			<PriceDetails Qualifier="CAL" Value="' . (($li['QLI_UNIT_COST']!="" && $li['QLI_DISCOUNTED_UNIT_COST']!="")?$li['QLI_UNIT_COST'] - $li['QLI_DISCOUNTED_UNIT_COST']:0) . '" TypeCode="QT" TypeQualifier="DPR" />
			<Section
				DepartmentType="' . Shipserv_Mtml::sanitise($li['QLI_CONFG_DEPT_TYPE']) 	. '"
				DrawingNumber="' . 	Shipserv_Mtml::sanitise($li['QLI_CONFG_DRAWING_NO']) 	. '"
				SerialNumber="' . 	Shipserv_Mtml::sanitise($li['QLI_CONFG_SERIAL_NO']) 	. '"
				Rating="' . 		Shipserv_Mtml::sanitise($li['QLI_CONFG_RATING']) 		. '"
				ModelNumber="' . 	Shipserv_Mtml::sanitise($li['QLI_CONFG_MODEL_NO']) 		. '"
				Manufacturer="' . 	Shipserv_Mtml::sanitise($li['QLI_CONFG_MANUFACTURER']) 	. '"
				Description="' . 	Shipserv_Mtml::sanitise($li['QLI_CONFG_DESC']) 			. '"
				Name="' . 			Shipserv_Mtml::sanitise($li['QLI_CONFG_NAME']) 			. '" />
			<Comments Qualifier="LIN">
				<Value><![CDATA[' . Shipserv_Mtml::sanitise($li['QLI_COMMENTS']) . ']]></Value>
			</Comments>
		</LineItem>
			';
		}

		$mtml = '
<?xml version="1.0" encoding="utf-8"?>
<MTML>
  <Interchange ControlReference="' . Shipserv_Mtml::sanitise($controlReference) . '" Recipient="' . $supplierTnid . '" Sender="' . $pagesBuyerTnid . '">
    <Order
    	MessageReferenceNumber="' . Shipserv_Mtml::sanitise($controlReference) . '"
    	LineItemCount="' . $this->quote->qotLineItemCount . '"
    	FunctionCode="_9"
    	CurrencyCode="' . $this->quote->qotCurrency . '"
    	TransportModeCode="' . Shipserv_Mtml::sanitise(Shipserv_Rfq::translateTransportationMode($this->quote->qotTransportationMode, true)) . '"
    	DeliveryTermsCode="' . Shipserv_Mtml::sanitise($this->quote->qotTermsOfDelivery) . '">
      <Reference Qualifier="AGI" ReferenceNumber="' . $this->quote->qotInternalRefNo . '"/>
      <Reference Qualifier="UC" ReferenceNumber="PO-' . $this->quote->qotInternalRefNo . '"/>
		<Comments Qualifier="ZAT">
			<Value><![CDATA[PO for ' . Shipserv_Mtml::sanitise($this->quote->qotRefNo) . ']]></Value>
		</Comments>
		<Comments Qualifier="ZTC">
			<Value><![CDATA[' . Shipserv_Mtml::sanitise($this->quote->qotGeneralTermsConditions) . ']]>
			</Value>
		</Comments>
		<Comments Qualifier="ZTP">
			<Value><![CDATA[' . Shipserv_Mtml::sanitise($this->quote->qotTermsOfPayment) . ']]></Value>
		</Comments>
		<Comments Qualifier="PUR">
			<Value><![CDATA[Supplier Comment: ' . Shipserv_Mtml::sanitise($this->quote->qotComments) . ' ' . Shipserv_Mtml::sanitise($this->quote->qotCurrencyInstructions) . ']]></Value>
		</Comments>
		<Party Qualifier="FW" Name="' . Shipserv_Mtml::sanitise($this->quote->qotSuggestedShipper) . '" />
		<Party Qualifier="BY" Name="' . Shipserv_Mtml::sanitise($enquiry->pinCompany) . '">
			<Contact FunctionCode="PD" Name="' . Shipserv_Mtml::sanitise($rfq->rfqContact) . '">
		    	<CommunicationMethod Number="' . Shipserv_Mtml::sanitise($rfq->rfqPhoneNo) . '" Qualifier="TE" />
		        <CommunicationMethod Number="' . Shipserv_Mtml::sanitise($rfq->rfqEmailAddress) . '" Qualifier="EM" />
		    </Contact>
		</Party>
		<Party Qualifier="VN" Name="' . Shipserv_Mtml::sanitise($supplier->name) . '" />
		<PackagingInstructions>
			<Value><![CDATA[' . Shipserv_Mtml::sanitise($this->quote->qotPackagingInstructions) . ']]></Value>
		</PackagingInstructions>

		' . implode("\n", $lineItems) . '
	  <MonetaryAmount Qualifier="_79" 	Value="' . (($this->quote->qotSubtotal != "")?$this->quote->qotSubtotal:0) . '" /> <!--Line Item  Subtotal-->
	  <MonetaryAmount Qualifier="_204" 	Value="' . (($this->quote->qotSubtotal != "" && $this->quote->qotDiscountPercentage != "" )?round($this->quote->qotSubtotal * $this->quote->qotDiscountPercentage/100,2):0) . '" /> <!--Total Discount-->
	  <MonetaryAmount Qualifier="_64" 	Value="' . (($this->quote->qotShippingCost != "")?$this->quote->qotShippingCost:0) . '" /> <!--Freight-->
	  <MonetaryAmount Qualifier="_106" 	Value="' . (($this->quote->qotAdditionalCostAmount1 != "")?$this->quote->qotAdditionalCostAmount1:0) . '" /> <!--Packing-->
	  <MonetaryAmount Qualifier="_259" 	Value="' . (($this->quote->qotTotalCost != "")?$this->quote->qotTotalCost:0) . '" /> <!--Total Price-->
	</Order>
  </Interchange>
</MTML>

		';

		return $mtml;
	}

	public function getUserOfPagesBuyer()
	{
		$sql = "

SELECT
  USERS.USR_USER_CODE
FROM
  buyer_branch_user
  JOIN USERS ON (bbu_usr_user_code=usr_user_code)
WHERE
  BBU_BYB_BRANCH_CODE=:tnid
		";
		$config = $this->getConfig();

		$db = parent::getDb();
		$r = $db->fetchAll($sql, array('tnid' => $config->shipserv->pagesrfq->buyerId));
		return $r;

	}


	public function sendPoToTradeNetCore()
	{
	    
		$logger = new Myshipserv_Logger_File('sending-PO-to-tradenet-core');
		$config = $this->getConfig();

		$logger->log("----------------------------------------------------------------------------------");
		$logger->log("----------------------------------------------------------------------------------");
		$logger->log("Creating MTML for PurchaseOrder");
		$mtml = trim($this->getMtml());

		$pagesBuyerTnid = $config->shipserv->pagesrfq->buyerId;
		$userOfPagesBuyer = $this->getUserOfPagesBuyer();
		
		$params['AppDetails']['Name'] = "Pages";
		$params['AppDetails']['Version'] = "3.5.13";

		$params['IntegrationDetails']['TradeNetID'] = $pagesBuyerTnid;
		$params['IntegrationDetails']['UserID'] = $pagesBuyerTnid;
		$params['IntegrationDetails']['IntegrationCode'] = "STD";

		$params['DocumentDetails']['DocumentType'] = "PO";
		$params['DocumentDetails']['ClientFileName'] = "Pages Quote to PO";
		$params['DocumentDetails']['FileContentsAsBytes'] = $mtml;
		$params['DocumentDetails']['EncodingTypeCode'] = "utf-8";

		$dataToSend['UserIntegrationDoc'] = $params;

		$soapUrl = $config->shipserv->services->tradenet->core->url;
				
		// BUY-962  CAS Service ticket here instead of plain text password sending
		$strAuthHeader = "Authorization: CAS ". Myshipserv_CAS_CasRest::getInstance()->generateNewSt() . ' ' . Myshipserv_CAS_CasRest::getInstance()->getDefaultCasServiceUrl();
		
		$arrContext = array('http' =>array('header' => $strAuthHeader));
		$objContext = stream_context_create($arrContext);
		
		$soapConfig = array(
			"soap_version" => "SOAP 1.1",
			"encoding" => "utf-8",
			"trace" => true,
			"connection_timeout" => 10,
		    'stream_context' => $objContext
		);
		
		$client = new SoapClient($soapUrl, $soapConfig);

		$response = $client->ping();
		if($response->PingResult == 1)
		{
			$logger->log("TNC is available");
			$logger->log("Trying to send document to TNC::SendEncodedDocument");

			$response = $client->SendEncodedDocument($dataToSend);
			
			if( $response->SendEncodedDocumentResult->ErrorMessage != "" )
			{
				$dataToLog .= "----------- RAW MTML --------------\n";
				$dataToLog .= $mtml. "\n";
				$dataToLog .= "----------- SOAP RAW REQUEST --------------\n";
				$dataToLog .= print_r($client->__getLastRequest(), true) . "\n";
				$dataToLog .= "----------- SOAP RAW RESPONSE --------------\n";
				$dataToLog .= print_r($client->__getLastResponse(), true) . "\n\n\n";
				$logger->log("Error happened on TNC::SendEncodedDocument", $dataToLog);
				$logger->log("Failed");

				return false;
			}
			else
			{
				$dataToLog = "----------- RAW MTML --------------\n";
				$dataToLog .= $mtml. "\n";
				$dataToLog .= "----------- SOAP RAW RESPONSE --------------\n";
				$dataToLog .= print_r($client->__getLastResponse(), true) . "\n\n\n";

				$logger->log("Successful", $dataToLog);

				return true;
			}
		}
	}

    /**
	 * Get the related buyer of this RFQ, return empty if buyer org id not found
	 * @return Shipserv_Buyer
	 */
     public function getBuyer()
 	{
 		if( $this->ordBybByoBuyerOrgCode == null ) return;
 		return Shipserv_Buyer::getInstanceById($this->ordBybByoBuyerOrgCode, self::getDb());
 	}

    public function getBuyerBranch(){
        $buyer = Shipserv_Buyer::getBuyerBranchInstanceById($this->ordBybBuyerBranchCode);
        return $buyer;
    }
    /**
	 * Get related supplier of this RFQ
	 * @return Shipserv_Supplier
	 */
	public function getSupplier()
	{
		$supplier = Shipserv_Supplier::getInstanceById( $this->ordSpbBranchCode, "", true);
		return $supplier;
	}

    public function cancel(){
        $buyer	  = $this->getBuyerBranch();
        $supplier = $this->getSupplier();

        $sql = "
            INSERT INTO email_alert_queue(eaq_id, eaq_internal_ref_no, eaq_spb_branch_code, eaq_alert_type, eaq_created_date)
            VALUES (sq_email_alert_queue.nextval,:docId, :spbBranchCode, :alertType, TO_DATE(:dateTime))
        ";
        $d = new DateTime;

		$params = array(
			'docId'		    => $this->ordInternalRefNo,
			'spbBranchCode' => $supplier->tnid,
			'alertType' 	=> 'ORD_CAN',
			'dateTime' 		=> $d->format('d-M-Y')
		);

		$this->getDb()->query($sql, $params);
    }

    public function decline(){

        $messageReferenceNumber = date('YmdHi') . $this->ordInternalRefNo;
        $subject = $this->ordSubject;
        $supplier = $this->getSupplier();
        $declineReason = "Purchase Order: \"" . $subject . "\" Ref: \"" . $this->ordRefNo . "\" was declined by " . $supplier->name . ' (TNID: ' . $supplier->tnid . ')';

        // send response to TNC
        $mtml = '<?xml version="1.0" encoding="utf-8"?>
<MTML>
  <Interchange ControlReference="' . $messageReferenceNumber . '" Sender="' . $this->ordSpbBranchCode . '" Recipient="' . $this->ordBybBuyerBranchCode . '" PreparationDate="2010-Sep-28" PreparationTime="07:16" SenderCodeQualifier="ZEX" RecipientCodeQualifier="ZEX" VersionNumber="2" Identifier="UNOC">
    <OrderResponse MessageReferenceNumber="' . $messageReferenceNumber . '" MessageNumber="' . $messageReferenceNumber . '" OrderNumber="Order:' . $this->ordInternalRefNo . '" AssociationAssignedCode="MARL10" ControllingAgency="UN" ReleaseNumber="96A" VersionNumber="D" FunctionCode="_27">
      <DateTimePeriod FormatQualifier="_203" Value="' . date('YmdHi') . '" Qualifier="_137" />
      <Comments Qualifier="SUR">
        <Value>
            <![CDATA[
                ' . $declineReason . '
            ]]>
        </Value>
      </Comments>
      <Comments Qualifier="ZAT">
        <Value>
            <![CDATA[
                ' . $this->ordSubject . '
            ]]>
        </Value>
      </Comments>
    </OrderResponse>
  </Interchange>
</MTML>';

        $tnc = new Shipserv_Adapters_Soap_MTMLLink;
        return $tnc->sendEncodedDocument( $mtml, 'SPB', $this->ordSpbBranchCode, 'Shipserv_PurchaseOrder::decline');
    }

    public function getDeclineInformation(){
        $sql = "
            SELECT
                ORP_CREATED_BY AUTHOR
                , ORP_CREATED_DATE CREATED_DATE
                , ORP_ORD_STS STATUS
            FROM
                order_response
            WHERE
                ORP_ORD_INTERNAL_REF_NO=:ordInternalRefNo
        ";
        return $this->getDb()->fetchAll($sql, array('ordInternalRefNo' => $this->ordInternalRefNo));
    }
}
