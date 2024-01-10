<?php
/**
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Rfq extends Shipserv_Object
{
    // added by Yuriy Akopov on 2013-09-02, story S8133
    const
        TABLE_NAME = 'REQUEST_FOR_QUOTE',

        COL_ID          = 'RFQ_INTERNAL_REF_NO',
        COL_DATE        = 'RFQ_CREATED_DATE',
        COL_STATUS      = 'RFQ_STS',
        COL_SUBJECT     = 'RFQ_SUBJECT',
        COL_COMMENTS    = 'RFQ_COMMENTS',
        COL_VESSEL_IMO  = 'RFQ_IMO_NO',
        COL_VESSEL_NAME = 'RFQ_VESSEL_NAME',
        COL_PUBLIC_ID   = 'RFQ_REF_NO',
        COL_BUYER_ID    = 'RFQ_BYB_BRANCH_CODE',
        COL_SOURCE_ID   = 'RFQ_SOURCERFQ_INTERNAL_NO',
        COL_PRIORITY    = 'RFQ_PRIORITY',
        COL_BUYER_ORG   = 'RFQ_BYB_BYO_ORG_CODE',
        COL_LINE_ITEM_COUNT = 'RFQ_LINE_ITEM_COUNT',
        COL_EVENT_HASH  = 'RFQ_EVENT_HASH',
        COL_DATE_UPDATED = 'RFQ_UPDATED_DATE'
    ;

    // keys in structure returned by getSuppliers() method
    const
        RFQ_SUPPLIERS_FROM_MATCH = 'FROM_MATCH',    // RFQ has been sent to a supplier from match
        RFQ_SUPPLIERS_FORWARDED  = 'FORWARDED',     // RFQ has been forwarded (not buyer selected supplier)
        RFQ_SUPPLIERS_BRANCH_ID  = 'SUPPLIER_ID',
        RFQ_SUPPLIERS_RFQ_ID     = 'RFQ_ID',
        RFQ_SUPPLIERS_ELAPSED    = 'ELAPSED'
    ;

    // constants for RFQ events select
    const
        SELECT_RFQ_ID       = 'RFQ_ID',
        SELECT_EVENT_ID     = 'EVENT_ID',
        SELECT_BUYER_ID     = 'BUYER_ID'
    ;

    const
        STATUS_DRAFT     = 'DFT',
        STATUS_DELETED   = 'DEL',
        STATUS_SUBMITTED = 'SUB'
        // CNC
    ;

    const
        SENDER_TYPE_BUYER    = 'BYO',
        SENDER_TYPE_SUPPLIER = 'SPB',
        SENDER_TYPE_USER     = 'USR'
    ;

    /**
     * @var Shipserv_Enquiry
     */
    public $enquiry = null;

    /** @var Shipserv_Buyer */
    public $rfqBuyer = null;

	/**
	 * Creating RFQ object based on the data received from the database
	 * @param unknown_type $data
	 */
	public function __construct($data)
	{
		// populate the supplier object
		if (is_array($data)) {
			foreach ($data as $name => $value) {
				$this->{$name} = $value;
			}

			// populate all dates to the format that we want
			if ($this->rfqDateTime != null) {
				$this->rfqDateTime = self::formatDate($this->rfqDateTime);
			}
			if ($this->rfqAdviceBeforeDate != null) {
				$this->rfqAdviceBeforeDate = self::formatDate($this->rfqAdviceBeforeDate);
			}
			if ($this->rfqCreatedDate != null) {
				$this->rfqCreatedDate = self::formatDate($this->rfqCreatedDate);
			}
			if ($this->rfqUpdatedDate != null) {
				$this->rfqUpdatedDate = self::formatDate($this->rfqUpdatedDate);
			}

            $db = $this->getDb();
			$adapterForPort = new Shipserv_Oracle_Ports($db);
			$adapterForCountry = new Shipserv_Oracle_Countries($db);

			$tmp = explode("-", $this->rfqDeliveryPort);

			// translate the delivery port
			$dataForPort = $adapterForPort->fetchPortByCode ($this->rfqDeliveryPort);
			$dataForCountry = $adapterForCountry->fetchCountriesByCode((array)$tmp[0]);
			$this->rfqDeliveryPortName = $dataForPort[0]['PRT_NAME'] . " " . $dataForCountry[0]['CNT_NAME'];
		}
	}

	
	public static function formatDate($date, $format = "d M Y h:i:s a")
	{
		if (gettype($date) == "string") {
			$object = new DateTime();
			$object->setDate(substr($date,0,4), substr($date,4,2), substr($date, 6, 2) );
			$output = $object->format($format);
		} else {
			$output = $date->format($format);
		}
		return $output;
	}

	
	public function getEnquiry()
	{
		$db = parent::getDb();
		$sql = "SELECT pir_pin_id as id FROM pages_inquiry_recipient WHERE pir_rfq_internal_ref_no = :rfqInternalRefNo";
		$result = $db->fetchAll($sql, array('rfqInternalRefNo' => $this->rfqInternalRefNo));
		if ($result[0]['ID'] != "") {
			$object = Shipserv_Enquiry::getInstanceById($result[0]['ID']);
			return $object;
		} else {
			return false;
		}
	}

	/**
	 * Creating object from the database
     *
	 * @param   array   $data
     * @param   Shipserv_Enquiry    $enquiry
     *
     * @return  Shipserv_Rfq
	 */
	private static function createObjectFromDb($data = null, Shipserv_Enquiry $enquiry = null)
	{
		$object = new self($data);

		// for RFQ created from enquiry
		if(!is_null($enquiry)) {
            $object->loadRelatedData($enquiry);
		} else {
			$object->rfqLineItems = $object->getLineItem(true);
		}

		return $object;
	}

    /**
     * As not all of the legacy RFQ constructors load the extended data like supplier or line items into the RFQ object
     * there is a function to load it when it is required and we can't use constructors that do that (e.g. we only have
     * RFQ id, but not an enquiry ID)
     *
     * @author  Yuriy Akopov
     * @date    2013-11-01
     *
     * @param   Shipserv_Enquiry    $enquiry    for legacy mechanism where enquiry used to come from outside
     */
    public function loadRelatedData(Shipserv_Enquiry $enquiry = null) {
        if (is_null($enquiry)) {
            $enquiry = $this->getEnquiry();
        }

        if ($enquiry instanceof Shipserv_Enquiry) {
            $this->enquiry = $enquiry;
            $this->enquiry->attachment = $enquiry->getAttachments();

            $this->rfqBuyer     = $this->getBuyerForPages();                // load buyer from enquiry
            $this->rfqLineItems = $this->getLineItemGroupedBySection(true); // load line items from enquiry
        } else {
            // enquiry is not available, but we still need to load the extended data
            // and these circumstances are exactly why we needed to create this function

            try {
                $buyer = $this->getBuyer(); // load buyer from organisation code
            } catch (Exception $e) {
                $buyer = Shipserv_Buyer::getBuyerBranchInstanceById($this->rfqBybBranchCode);   // load buyer from branch code
            }
            $this->rfqBuyer = $buyer;

            $lineItems = self::getDao()->fetchLineItems($this->rfqInternalRefNo);
            foreach ($lineItems as $index => $item) {
                $lineItems[$index] = parent::camelCase($item);
            }
            $this->rfqLineItems = Shipserv_Rfq::groupLineItemsBySection($lineItems);
        }

        // loading data which mechanism is independent from enquiry
        $this->rfqSupplier  = $this->getSupplier();
        $this->rfqAction    = $this->getAvailableActions();
        $this->rfqAddress   = implode(', ', $this->getAddress());

        // loading attachment information
        $this->attachments = new stdClass();
        $this->attachments->rfq = array();
        $this->attachments->enquiry = array();

        $attachments = Application_Model_Transaction::getTransactionAttachments($this);
        if (!empty($attachments)) {
            $this->attachments->rfq = $attachments;
        }

        // enquiry attachments
        if ($enquiry) {
            $attachments = Application_Model_Transaction::getTransactionAttachments($enquiry);
            $this->attachments->enquiry = $attachments;
        }

        // load party contract address
    }

	public function getAvailableActions()
	{
		$data['product'] = $this->rfqSupplier->getIntegrationType();
        //$data['product'] = 'START_SUPPLIER';

        if ($this->enquiry) {
            $data['urlForStartSupplier'] = $this->enquiry->getUrlToReplyOnStartSupplier();
            $data['actor'] = ($this->enquiry->getUserWritingResponse()->firstName!="")?$this->enquiry->getUserWritingResponse()->firstName . ' (' . $this->enquiry->getUserWritingResponse()->email . ')':'';
        }

		return $data;
	}

	/**
	 * Create instance of the RFQ based on PIN_ID and PIR_SPB_BRANCH_CODE
	 * this will return two different types of RFQ, if an enquiry has a physical RFQ
	 * on REQUEST_FOR_QUOTE table, this will return a longer version of it, if not,
	 * it'll return a cut-down version
	 *
	 * @param   int $pinId
	 * @param   int $tnid
     *
     * @return Shipserv_Rfq
	 */
	public static function getInstanceByEnquiryIdAndTnid($pinId, $tnid)
	{
		$enquiry = Shipserv_Enquiry::getInstanceByIdAndTnid($pinId, $tnid);

		if(!is_null($enquiry->pirRfqInternalRefNo)) {
			$rfq = self::getInstanceById($enquiry->pirRfqInternalRefNo, $enquiry);
            $rfq->enquiry = $enquiry;
            $rfq->enquiry->attachment = $enquiry->getAttachments();
            $rfq->rfqAddress = implode(', ', $rfq->getAddress());

			return $rfq;
		}

        return self::getInstanceByEnquiry($enquiry);
	}

	/**
	 * Create object of RFQ if no physical RFQ row found on the request_for_quote table
	 * notice the manual approach -
	 * @todo do this on the database level instead
	 * @param Shipserv_Enquiry $enquiry
	 */
	public static function getInstanceByEnquiry( $enquiry)
	{
		// passing all data on the enquiry object to be transformed as RFQ
		$rfqData['rfqComments'] = nl2br($enquiry->pinInquiryText);
		$rfqData['rfqVesselName'] = $enquiry->pinVesselName;
		$rfqData['rfqSubject'] = $enquiry->pinSubject;
		$rfqData['rfqContact'] = $enquiry->pinName;
		$rfqData['rfqContactDetail']['buyer']['phone'] = $enquiry->pinPhone;
		$rfqData['rfqContactDetail']['buyer']['company'] = $enquiry->pinCompany;
		$rfqData['rfqEmailAddress'] = $enquiry->pinEmail;
		$rfqData['rfqImoNo'] = $enquiry->pinImo;
		$rfqData['rfqDeliveryPort'] = $enquiry->pinDeliveryLocation;

		if( $enquiry->pinMtml != "" )
		{
			$db = parent::getDb();
			$adapterForCountry = new Shipserv_Oracle_Countries($db);

			$mtml = Shipserv_Mtml::createFromString($enquiry->pinMtml);
			$mtml = $mtml->getXml();

			// general stuff
			$rfqData['rfqRefNo'] = self::xpath($mtml,"Interchange/RequestForQuote/Reference[@Qualifier='UC']/@ReferenceNumber");
			$rfqData['rfqSubject'] = self::xpath($mtml,"Interchange/RequestForQuote/Comments[@Qualifier='ZAT']/Value");
			$rfqData['rfqDeliveryPort'] = self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='UD']/PartyLocation/@Port");
			$rfqData['rfqPackagingInstructions'] = self::xpath($mtml,"Interchange/RequestForQuote/PackagingInstructions/Value");
			$rfqData['rfqTransportationMode'] = self::translateTransportationMode(self::xpath($mtml,"Interchange/RequestForQuote/@TransportModeCode"));

			// related dates
			$rfqData['rfqAdviceBeforeDate'] = self::xpath($mtml,"Interchange/RequestForQuote/DateTimePeriod[@Qualifier='_175']/@Value");
			$rfqData['rfqDateTime'] = self::xpath($mtml,"Interchange/RequestForQuote/DateTimePeriod[@Qualifier='_2']/@Value");
			$address = array();
			// address
			if( self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/@Name") != "" ) $address[] = self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/@Name");
			if( self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/StreetAddress[1]") != "" ) $address[] = self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/StreetAddress[1]");
			if( self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/StreetAddress[2]") != "" ) $address[] = self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/StreetAddress[2]");
			if( self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/@City") != "" ) $address[] = self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/@City");
			if( self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/@CountrySubEntityIdentification") != "" ) $address[] = self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/@CountrySubEntityIdentification");
			if( self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/@PostcodeIdentification") != "" ) $address[] = self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/@PostcodeIdentification");
			if( self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/@CountryCode") != "" )
			{
				$tmp = $adapterForCountry->fetchCountriesByCode((array) self::xpath($mtml,"Interchange/RequestForQuote/Party[@Qualifier='CN']/@CountryCode") );

				$address[] = $tmp[0]['CNT_NAME'];
			}

			$rfqData['rfqAddress'] = implode(", ", $address);
		}

		$rfqData['rfqBuyer']['contactPhone'] = $enquiry->pinPhone;
		$rfqData['rfqBuyer']['name'] = $enquiry->pinCompany;
		$rfqData['rfqBuyer']['contactName'] = $enquiry->pinName;
		$rfqData['rfqBuyer']['address1'] = $enquiry->pinName;
		$rfqData['rfqBuyer']['address2'] = $enquiry->pinName;
		$rfqData['rfqBuyer']['city'] = $enquiry->pinName;
		$rfqData['rfqBuyer']['postcode'] = $enquiry->pinName;
		$rfqData['rfqBuyer']['state'] = $enquiry->pinName;
		$rfqData['rfqBuyer']['country'] = $enquiry->pinName;

		$rfqData['enquiry'] = $enquiry;
		$rfqData['enquiry']->attachment = $enquiry->getAttachments();
		$object = self::createObjectFromDb($rfqData, $enquiry);

		$rfqData['rfqLineItems'] = $object->getLineItemGroupedBySection();
		$object = self::createObjectFromDb($rfqData, $enquiry);

		return $object;
	}

    /**
     * Amended by Yuriy Akopov on 2014-03-21, DE4650
     *
     * @return array
     */
    public function getAddress()
	{
		$sql =
			"SELECT
				PARTY_CONTACT.PARTY_NAME,
				PARTY_CONTACT.PARTY_STREETADDRESS,
				PARTY_CONTACT.PARTY_STREETADDRESS2,
				PARTY_CONTACT.PARTY_CITY,
				PARTY_CONTACT.PARTY_COUNTRY_SUB_ENTITY_ID,
				PARTY_CONTACT.PARTY_POSTALCODE,
				(SELECT CNT_NAME FROM COUNTRY WHERE CNT_COUNTRY_CODE=PARTY_COUNTRYCODE) COUNTRY_NAME,
				PARTY_CONTACT.PARTY_CONTACT_PHONE,
				PARTY_CONTACT.PARTY_CONTACT_CELLPHONE,
				PARTY_CONTACT.PARTY_CONTACT_FAX,
				PARTY_CONTACT.PARTY_CONTACT_AFTERHOURSPHONE,
				PARTY_CONTACT.PARTY_CONTACT_EMAIL,
				PARTY_CONTACT.PARTY_CONTACT_URL
			FROM
			  PARTY_CONTACT
			WHERE
			  PARTY_DOC_INTERNAL_REF_NO=:rfqId
			  AND PARTY_DOC_TYPE = 'RFQ'
			  AND PARTY_QUALIFIER = 'CN'"
        ;

		$row = $this->getDb()->fetchRow($sql, array(
            'rfqId' => $this->rfqInternalRefNo,
        ));

        $data = array();

        if (empty($row)) {
            return $data;
        }

        $prefixes = array(
            'PARTY_CONTACT_PHONE'           => 'Phone: ',
            'PARTY_CONTACT_CELLPHONE'       => 'Cell: ',
            'PARTY_CONTACT_FAX'             => 'Fax: ',
            'PARTY_CONTACT_AFTERHOURSPHONE' => 'After Hrs Phone: ',
            'PARTY_CONTACT_EMAIL'           => 'Email: ',
            'PARTY_CONTACT_URL'             => 'URL: '
        );

        foreach ($row as $field => $value) {
            if (strlen($value) === 0) {
                continue;
            }

            if (array_key_exists($field, $prefixes)) {
                $value = $prefixes[$field] . $value;
            }

            $data[$field] = $value;
        }

		return $data;

	}

	public static function translateUnitOfMeasurements($code)
	{
		$sql = "
			SELECT
				MSU_CODE_DESC AS RFL_UNIT_DESC
			FROM
				MTML_STD_UNIT
			WHERE
				MSU_CODE=:code
		";
		$result = parent::getDb()->fetchAll($sql, array("code" => $code));
		return $result[0]['RFL_UNIT_DESC'];
	}

	public static function translateTransportationMode($input, $returnAsCode = false)
	{
		$data = array(
			"_1" => "Maritime",
			"_2" => "Rail",
			"_3" => "Road",
			"_4" => "Air"
		);
		if( $returnAsCode == true)
		{
			foreach($data as $k=>$d)
			{
				if( $d == $input)
				{
					return $k;
				}
			}
		}
		else
		{
			return $data[$input];
		}
	}

	public static function translateTaxStatus($input, $returnAsCode = false)
	{
		$data = array(
				"E" => "Exempt",
				"N" => "NotTaxable",
				"T" => "Taxable"
		);

		if( $returnAsCode == true)
		{
			foreach($data as $k=>$d)
			{
				if( $d == $input)
				{
					return $k;
				}
			}
		}
		else
		{
			return $data[$input];
		}

	}
	public static function xpath($xml, $xpath)
	{
		$result = $xml->xpath($xpath);
		return (string)$result[0];
	}
	/**
	 * Getting RFQ by the ID of the RFQ (rfq_internal_ref_no)
	 * @param   int   $id
	 */
	public static function getInstanceById($id, $enquiry = null, $useArchive = false)
	{
		$row = self::getDao()->fetchById($id, $useArchive);
		$data = parent::camelCase($row);

		return self::createObjectFromDb($data, $enquiry);
	}

    /**
     * Retrieves the first RFQ of the given event
     *
     * @author  Yuriy Akopov
     * @date    2014-06-04
     * @story   S10311
     *
     * @param $eventHash
     *
     * @return Shipserv_Rfq
     */
    public static function getInstanceByEvent($eventHash, $submitted = true) {
        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);

        $select
            ->from(
                array('rfq' => self::TABLE_NAME),
                'rfq.' . self::COL_ID
            )
            ->where('rfq.' . self::COL_EVENT_HASH . ' = HEXTORAW(?)', $eventHash)
            ->order('rfq.' . self::COL_ID)
        ;

        if ($submitted) {
            $select->where('rfq.' . self::COL_STATUS . ' = ?', self::STATUS_SUBMITTED);
        }

        $rfqId = $db->fetchOne($select);

        $rfq = self::getInstanceById($rfqId);
        if (strlen($rfq->rfqInternalRefNo) === 0) {
            throw new Exception('No valid RFQ found to represent event . ' . $eventHash);
        }

        return $rfq;
    }

	/**
	 * Get list of RFQs received by a TNID
	 * @param array $tnid
	 */
	public static function getRfqByTnids( $tnid )
	{
		$sql = "
			SELECT
			  pages_inquiry.*,
			  '--',
			  pages_inquiry_recipient.*,
			  '--',
			  request_for_quote.*
			FROM
			  pages_inquiry_recipient JOIN pages_inquiry ON (PIN_ID=PIR_PIN_ID)
			    JOIN request_for_quote ON ( pir_rfq_internal_ref_no = rfq_internal_ref_no)
			WHERE
			  PIR_RFQ_INTERNAL_REF_NO IS NOT NULL
			  --PIR_SPB_BRANCH_CODE = :tnid
		";

		foreach( (array) self::getDb()->fetchAll($sql) as $row )
		{
			$rfqs[] = self::getInstanceById( $row['PIR_RFQ_INTERNAL_REF_NO'] );
		}
		return $rfqs;
	}

	/**
	 * Get the related buyer of this RFQ, return empty if buyer org id not found
	 * @return Shipserv_Buyer
	 */
	public function getBuyer()
	{
		if( $this->rfqBybByoOrgCode == null ) return;
		return Shipserv_Buyer::getInstanceById($this->rfqBybByoOrgCode, self::getDb());
	}

	public function getBuyerBranch(){
		$buyer = Shipserv_Buyer::getBuyerBranchInstanceById($this->rfqBybBranchCode);
		return $buyer;
	}

	// buyer on
	public function getBuyerForPages()
	{
		if( $this->enquiry->pinPucCompanyType == 'SPB')
		{
			$supplier = Shipserv_Supplier::getInstanceById($this->enquiry->pinPucCompanyId, self::getDb());
			return $supplier->toArray('contact-detail');
		}
		else if( $this->enquiry->pinPucCompanyType == 'BYO')
		{
			$buyer = Shipserv_Buyer::getInstanceById($this->enquiry->pinPucCompanyId, self::getDb());
			return $buyer->toArray('contact-detail');
		}
		return $data;

		if( $this->rfqBybByoOrgCode == null ) return;
		return Shipserv_Buyer::getInstanceById($this->rfqBybByoOrgCode, self::getDb());
	}

	/**
	 * Returns the list of TNIDs RFQ is directly associated with (as opposed to the list of suppliers for the whole event)
	 *
	 * @author	Yuriy Akopov
	 * @story	S14652
	 * @date	2015-12-15
	 *
	 * @returns array
	 */
	public function getDirectSupplierIds() {
		$select = new Zend_Db_Select($this->getDb());
		$select
			->from(
				array('rqr' => 'rfq_quote_relation'),
				'rqr_spb_branch_code'
			)
			->where('rqr_rfq_internal_ref_no = ?', $this->rfqInternalRefNo)
		;

		$ids = $select->getAdapter()->fetchCol($select);

		if (empty($ids)) {
			if (strlen($this->enquiry->pirSpbBranchCode)) {
				$ids[] = $this->enquiry->pirSpbBranchCode;
			}
		}

		return $ids;
	}

	/**
	 * Get related supplier of this RFQ
	 * @return Shipserv_Supplier
	 */
	public function getSupplier()
	{
		$sql = "SELECT RQR_SPB_BRANCH_CODE FROM rfq_quote_relation WHERE RQR_RFQ_INTERNAL_REF_NO=:rfqId";
		$data = $this->getDb()->fetchAll($sql, array('rfqId' => $this->rfqInternalRefNo));

		if( $data[0]['RQR_SPB_BRANCH_CODE'] == null )
		{
			$tnid = $this->enquiry->pirSpbBranchCode;
		}
		else
		{
			$tnid = $data[0]['RQR_SPB_BRANCH_CODE'];
		}

		$supplier = Shipserv_Supplier::getInstanceById( $tnid, "", true);
		return $supplier;
	}

	/**
	 * Get the database access object
	 * @return Shipserv_Oracle_Rfq
	 */
	private static function getDao()
	{
		return new Shipserv_Oracle_Rfq( self::getDb() );
	}

	/**
	 * Get related LineItems for this RFQ
	 * there are 3 types of RFQ
	 *	// 1. RFQ that is structured and goes directly to TradeNet
	 *	// 2. RFQ that is structured and DOESN'T go to TradeNet (buyer will only get an email to respond etc)
	 *	// 3. RFQ that is NOT structred (old/legacy type of RFQ)
	 *
	 * @param boolean $camelCase option to camelcase the keys of the array
	 * @return array
	 */
	public function getLineItem($camelCase = false)
	{
		// to handle RFQ 1, we can query the database for the line items
		if($this->enquiry and ($this->enquiry->pirRfqInternalRefNo != ""))
		{
			$data = self::getDao()->fetchLineItems( $this->rfqInternalRefNo );
			//print_r( $data );
			if( $camelCase === true )
			{
				foreach((array)$data as $row)
				{
					$new[] = parent::camelCase($row);
				}
				$data = $new;
			}
		}
		// to handle RFQ 2, we need to parse the MTML and pull the line items
		else if($this->enquiry and ($this->enquiry->pirRfqInternalRefNo == "" && $this->enquiry->pinMtml != ""))
		{
			$mtml = Shipserv_Mtml::createFromString($this->enquiry->pinMtml);

			// loop through the line items
			foreach( $mtml->getXml()->Interchange->RequestForQuote->LineItem as $item )
			{
				// convert the XML into array
				$lineItems = Shipserv_Helper_Xml::simpleXml2Array($item);

				// put the line items into how it stored on the db - then parse it
				$li['RFL_RFQ_INTERNAL_REF_NO'] = '';
				$li['RFL_LINE_ITEM_NO'] = $lineItems['@attributes']['Number'];
				$li['RFL_QUANTITY'] = $lineItems['@attributes']['Quantity'];
				$li['RFL_ID_TYPE'] = $lineItems['@attributes']['TypeCode'];
				$li['RFL_ID_CODE'] = $lineItems['@attributes']['Identification'];
				$li['RFL_PRODUCT_DESC'] = $lineItems['@attributes']['Description'];
				$li['RFL_QUALITY'] = '';
				$li['RFL_UNIT'] = $lineItems['@attributes']['MeasureUnitQualifier'];
				$li['RFL_PRIORITY'] = '';
				$li['RFL_COMMENTS'] = $lineItems['Comments']['Value'];
				$li['RFL_UNIT_COST'] = '0';
				$li['RFL_TOTAL_LINE_ITEM_COST'] = 0;
				$li['RFL_STS'] = 'SUB';
				$li['RFL_CONFG_NAME'] = $lineItems['Section']['@attributes']['Name'];
				$li['RFL_CONFG_DESC'] = $lineItems['Section']['@attributes']['Description'];
				$li['RFL_CONFG_MANUFACTURER'] = $lineItems['Section']['@attributes']['Manufacturer'];
				$li['RFL_CONFG_MODEL_NO'] = $lineItems['Section']['@attributes']['ModelNumber'];
				$li['RFL_CONFG_RATING'] = $lineItems['Section']['@attributes']['Rating'];
				$li['RFL_CONFG_SERIAL_NO'] = $lineItems['Section']['@attributes']['SerialNumber'];
				$li['RFL_CONFG_DRAWING_NO'] = $lineItems['Section']['@attributes']['DrawingNumber'];
				$li['RFL_CONFG_DEPT_TYPE'] = $lineItems['Section']['@attributes']['DepartmentType'];
				$li['RFL_DELIVERY_STS'] = '';
				$li['RFL_ACCOUNT_REF'] = '';
				$li['RFL_WEIGHT'] = '';
				$li['RFL_CONFG_DEPT_CODE'] = '';
				$li['RFL_CREATED_BY'] = '';
				$li['RFL_CREATED_DATE']	 = '';
				$li['RFL_UPDATED_BY'] = '';
				$li['RFL_UPDATED_DATE'] = '';
				$li['RFL_RLI_LINE_ITEM_NO'] = '';
				$li['RFL_DISCOUNTED_UNIT_COST'] = 0;
				$li['RFL_SOURCERFQ_INTERNAL_NO'] = '';
				$li['RFL_SOURCERFQ_LINEITEM_NO'] = '';
				$li['RFL_UNIT_DESC'] = self::translateUnitOfMeasurements($lineItems['@attributes']['MeasureUnitQualifier']);

				$li = parent::camelCase($li);
				$data[] = $li;
			}
		}
		// RFQ 3
		else if(!$this->enquiry or ($this->enquiry->pirRfqInternalRefNo == "" && $this->enquiry->pinMtml == ""))
		{
			$data = array();
		}

		return $data;
	}

    /**
     * @author  Yuriy Akopov
     * @date    2013-10-28
     *
     * @param array $data
     * @return mixed
     */
    public static function groupLineItemsBySection($data) {
        $x = 1;
        $index['empty'] = 0;

        foreach((array)$data as $li)
        {
            $sName = trim($li['rflConfgDesc']);

            $hash = trim($li['rflConfgName']);;
            $hash .= trim($li['rflConfgDesc']);
            $hash .= trim($li['rflConfgDeptType']);
            $hash .= trim($li['rflConfgModelNo']);
            $hash .= trim($li['rflConfgRating']);
            $hash .= trim($li['rflConfgSerialNo']);
            $hash .= trim($li['rflConfgDrawingNo']);

            $hash = strtolower($hash);
            $hash = md5($hash);

            $index[$hash] = ( is_null($index[$hash]) )?$x++:$index[$hash];
            $sectionName = $index[$hash];

            $section[$sectionName]['name'] = 'section' . $sectionName;
            $section[$sectionName]['sectionName'] = trim($li['rflConfgName']);
            $section[$sectionName]['sectionDescription'] = $li['rflConfgDesc'];
            $section[$sectionName]['sectionType'] = $li['rflConfgDeptType'];
            $section[$sectionName]['sectionModel'] = trim($li['rflConfgModelNo']);
            $section[$sectionName]['sectionSerialNumber'] = $li['rflConfgSerialNo'];
            $section[$sectionName]['sectionDrawingNumber'] = $li['rflConfgDrawingNo'];
            $section[$sectionName]['sectionManufacturer'] = $li['rflConfgManufacturer'];
            $section[$sectionName]['sectionRating'] = $li['rflConfgRating'];
            $section[$sectionName]['lineItems'][] = $li;
        }

        return $section;
    }

	/**
	 * Grouping the line items by the section
	 * requested by Attila
	 * @param boolean $camelCase
	 */
	public function getLineItemGroupedBySection($camelCase = false)
	{
		$data = $this->getLineItem($camelCase);
		return self::groupLineItemsBySection($data);
	}
	/**
	 * Return the port
	 * @param unknown_type $code
	 * @param unknown_type $mode
	 */
	public function getPort($code = null, $mode = 'string')
	{
		if( $code == null ) $code = $this->rfqDeliveryPort;
		$adapter = new Shipserv_Oracle_Ports( self::getDb() );
		$data = $adapter->fetchPortByCode( $code );
		if( !empty( $data ))
		{
			$data = $data[0];
			if( $mode == "string" )
			{
				return $data['PRT_NAME'] . ", " . $data['PRT_CNT_COUNTRY_CODE'];
			}
		}
		else
		{
			return "";
		}
	}

	/**
	 * Get related pages enquiry data
	 * @return Shipserv_Enquiry
	 */
	public function getPagesEnquiry()
	{
		try
		{
			$sql = "SELECT PIR_PIN_ID, PIR_SPB_BRANCH_CODE, (SELECT PIN_HASH_KEY FROM PAGES_INQUIRY WHERE PIN_ID=PIR_PIN_ID) HASH FROM PAGES_INQUIRY_RECIPIENT WHERE PIR_RFQ_INTERNAL_REF_NO=" . $this->rfqInternalRefNo;
			$result = $this->getDb()->fetchAll($sql);
			$result = $result[0];
			$enquiry = Shipserv_Enquiry::getMyshipservEnquiryInstanceById($result['PIR_PIN_ID'], $result['PIR_SPB_BRANCH_CODE'], $result['HASH'], true);
			return $enquiry;
		}
		catch( Exception $e )
		{
			echo $e->getMessage();
			return false;
		}
	}

	public function _setRecipient( $supplier )
	{
		$this->recipient = $supplier;
	}

	/**
	 * Get MTML object or String
	 * @param boolean $asString
	 */
	public function getMtml( $asString = true )
	{
		$rfq = Shipserv_Rfq::getInstanceById($this->rfqInternalRefNo);
		$rfqObject = new Shipserv_TnMsg_Xml_RfqHelper(array($this->rfqInternalRefNo), $rfq->getLineItem(), array(0 => array('SPB_BRANCH_CODE' => 51606)));

		// conversion
		$stringConverter = new Shipserv_TnMsg_Xml_Rfq;
		$string = $stringConverter->rfqToMtml($rfqObject);

		if( $asString === true )
		{
			return $string;
		}
		else
		{
			$obj = Shipserv_Mtml::createFromString($string);
			return $obj;
		}
	}

	public function getPotentialSaving()
	{
		$sql = "SELECT get_potential_saving_by_rfq_v2(:rfqId) FROM dual";
		$db = $this->getDbByType('ssreport2');
		return round($db->fetchOne($sql, array('rfqId' => $this->rfqInternalRefNo )),2);
	}

	public function getActualSaving()
	{
		$sql = "SELECT get_actual_saving_by_rfq_v2(:rfqId) FROM dual";
		$db = $this->getDbByType('ssreport2');
		return round($db->fetchOne($sql, array('rfqId' => $this->rfqInternalRefNo )),2);
	}

	/**
	 * Support for custom supplierId added by Yuriy Akopov on 2015-12-23, DE6297
	 *
	 * @param string $viewer
	 * @param null $supplierId
	 * @return string
	 * @throws Shipserv_Match_Exception
	 */
	public function getUrl($viewer = 'supplier', $supplierId = null)
	{
        if( $viewer == 'supplier' ){
	        if (is_null($supplierId)) {
		        $supplierId = $this->getSupplier()->tnid;
	        } else {
		        $rfqSupplierIds = $this->getDirectSupplierIds();
		        if (!in_array($supplierId, $rfqSupplierIds)) {
			        throw new Exception("Supplier " . $supplierId . " not found for RFQ " . $this->rfqInternalRefNo . ", impossible to show printable view");
		        }
	        }

            $users = Shipserv_User::getActiveUserBySpbBranchCode($supplierId);
            $url = "http://". $this->getHostname() . "/printables/app/print?docid=". $this->rfqInternalRefNo . "&usercode=" . $users[0]['USR_USER_CODE'] . "&branchcode=" . $supplierId . "&doctype=RFQ&custtype=supplier&md5=" . $users[0]['USR_MD5_CODE'];

        }else{
            $rfq = $this->resolveMatchForward();
            $users = Shipserv_User::getActiveUserByBybBranchCode($rfq->rfqBybBranchCode);

            $url = "http://". $this->getHostname() . "/printables/app/print?docid=". $rfq->rfqInternalRefNo . "&usercode=" . $users[0]['USR_USER_CODE'] . "&branchcode=" . $rfq->rfqBybBranchCode . "&doctype=RFQ&custtype=buyer&md5=" . $users[0]['USR_MD5_CODE'];
        }

		return $url;
	}

	public static function getPrintableUrl($id)
	{
		return "/user/printable?d=rfq&id=" . $id . "&h=" . md5('rfq' . $id);
	}

	public function getCancelUrl($supplierId = null) {
		if (is_null($supplierId)) {
			$supplier = $this->getSupplier();
		} else {
			$supplier = Shipserv_Supplier::getInstanceById($supplierId, '', true);
		}

		$hash = md5('rfqInternalRefNo=' . $this->rfqInternalRefNo . '&spbBranchCode=' . $supplier->tnid);
		return $url = "http://". $this->getHostname() . "/buyer/cancel-rfq?doc=rfq&id=" . $this->rfqInternalRefNo . "&h=" . $hash . "&s=" . $supplier->tnid;

	}

	public function getDeclineUrl($supplierId = null) {
		if (is_null($supplierId)) {
			$supplier = $this->getSupplier();
		} else {
			$supplier = Shipserv_Supplier::getInstanceById($supplierId, '', true);
		}

		$hash = md5('rfqInternalRefNo=' . $this->rfqInternalRefNo . '&spbBranchCode=' . $supplier->tnid);
		return $url = "http://". $this->getHostname() . "/supplier/decline?doc=rfq&id=" . $this->rfqInternalRefNo . "&h=" . $hash . "&s=" . $supplier->tnid;

	}

	public function getDeclineInformation(){
		$sql = "
            SELECT
                RFP_CREATED_BY AUTHOR
                , RFP_CREATED_DATE CREATED_DATE
                , RFP_STS STATUS
            FROM
                rfq_response
            WHERE
                RFP_RFQ_INTERNAL_REF_NO=:rfqInternalRefNo
        ";
		return $this->getDb()->fetchAll($sql, array('rfqInternalRefNo' => $this->rfqInternalRefNo));
	}

	public function getMessageNumberFromMapping($tnid){
		$sql = "
            SELECT
             MIM_MSG_NO
            FROM
             MR_INTERNAL_MAPPING
            WHERE
             MIM_INTERNAL_NO=:rfqInternalRefNo
             AND MIM_BRANCH_CODE =:tnid
             AND MIM_DOCUMENT_NAME = 'RequestForQuote'
        ";
		return $this->getDb()->fetchOne($sql, array('tnid' => $tnid, 'rfqInternalRefNo' => $this->rfqInternalRefNo));

	}

	public function decline($declineReason, $supplierId = null){
		$messageReferenceNumber = date('YmdHi') . $this->rfqInternalRefNo;
		$subject = $this->rfqSubject;

		if (is_null($supplierId)) {
			$supplier = $this->getSupplier();
		} else {
			$supplier = Shipserv_Supplier::getInstanceById($supplierId, '', true);
		}

		$buyer = $this->getBuyerBranch();
		$messageNumber = $this->getMessageNumberFromMapping($buyer->bybBranchCode);

		// send response to TNC
		$mtml = '<?xml version="1.0" encoding="utf-8"?>
        <MTML>
        	<Interchange Identifier="UNOC" VersionNumber="2" Sender="' . $supplier->tnid . '" SenderCodeQualifier="ZEX" Recipient="' . $this->rfqBybBranchCode . '" RecipientCodeQualifier="ZEX" PreparationDate="' . date('Ymd') . '" PreparationTime="' . date('Hi') . '" ControlReference="' . $messageReferenceNumber . '">
        		<RFQResponse AssociationAssignedCode="MARL10" ControllingAgency="UN" MessageNumber="' . $messageNumber . '" FunctionCode="_27" MessageReferenceNumber="'.$messageReferenceNumber.'" ReleaseNumber="96A" RFQNumber="'.$this->rfqInternalRefNo.'" VersionNumber="D">
        			<DateTimePeriod Qualifier="_137" Value="' . date('YmdHi') . '" FormatQualifier="_203" />
        			<Comments Qualifier="SUR">
        				<Value>
                        <![CDATA[
                            ' . $declineReason . '
                        ]]>
        				</Value>
        			</Comments>
        		</RFQResponse>
        	</Interchange>
        </MTML>
        ';

		$tnc = new Shipserv_Adapters_Soap_MTMLLink;
		return $tnc->sendEncodedDocument( $mtml, 'SPB', $supplier->tnid, 'Shipserv_Rfq::decline');
	}

	/**
	 * Cancel RFQ for the given supplier or for all of them
	 * Modified by Yuriy Akopov on 2015-12-15, S14652 because earlier Elvir's implementation was cancelling it for the
	 * first supplier only
	 *
	 * @param	int|null	$supplierId
	 *
	 * @return	int
	 */
	public function cancel($supplierId = null /* $cancelAllRfqEvents = false */) {
		if (!is_null($supplierId)) {
			$supplierIds = $this->getDirectSupplierIds();
			if (!in_array($supplierId, $supplierIds)) {
				return false;	// impossible to cancel for supplier which didn't receive this RFQ
			}
		}

		/*
		$supplierSql = "
        	SELECT
        		rfq_internal_ref_no,
        		spb_branch_code
        	FROM
        		rfq
        	WHERE
        		rfq_event_hash = (
                    SELECT rfq_event_hash FROM rfq WHERE rfq_internal_ref_no = :rfqId
                )
		";

		$rows = $this->getSsreport2Db()->fetchAll($supplierSql, array('rfqId' => $this->rfqInternalRefNo));
		*/

		$supplierInfo = $this->getSuppliers();

		$today = new DateTime();
		$result = 0;

		foreach ($supplierInfo as $spb) {
			$curSupplierId = $spb[self::RFQ_SUPPLIERS_BRANCH_ID];
			$curRfqId = $spb[self::RFQ_SUPPLIERS_RFQ_ID];

			if (is_null($supplierId) or ($supplierId == $curSupplierId)) {
				$alertSql = "
					INSERT INTO email_alert_queue(eaq_id, eaq_internal_ref_no, eaq_spb_branch_code, eaq_alert_type, eaq_created_date)
					VALUES (sq_email_alert_queue.nextval,:docId, :spbBranchCode, :alertType, TO_DATE(:dateTime))
				";
				$this->getDb()->query($alertSql, array(
					'docId'			=> $curRfqId,
					'spbBranchCode' => $curSupplierId,
					'alertType'		=> 'RFQ_CAN',
					'dateTime'		=> $today->format('d-M-Y')
				));

				$rqrSql = "UPDATE rfq_quote_relation SET RQR_RFQ_CANCELLED_DATE = SYSDATE WHERE RQR_RFQ_INTERNAL_REF_NO = :docId";
				$this->getDb()->query($rqrSql, array('docId' => $curRfqId));

				$result++;
			}
		}

		return $result;

		/*
		$d = new DateTime;
		if( $cancelAllRfqEvents === true ){
			$sql = "
                SELECT rfq_internal_ref_no, spb_branch_code FROM rfq WHERE rfq_event_hash=(
                    SELECT rfq_event_hash FROM rfq WHERE rfq_internal_ref_no=" . $this->rfqInternalRefNo . "
                )
            ";

			foreach($this->getSsreport2Db()->fetchAll($sql) as $row){
				$params = array(
						'docId' => $row['RFQ_INTERNAL_REF_NO'],
						'spbBranchCode' => $row['SPB_BRANCH_CODE'],
						'alertType' => 'RFQ_CAN',
						'dateTime' => $d->format('d-M-Y')
				);

				$this->processCancellation($params);
			}


		}else{
			$supplier = $this->getSupplier();
			$params = array(
					'docId' => $this->rfqInternalRefNo,
					'spbBranchCode' => $supplier->tnid,
					'alertType' => 'RFQ_CAN',
					'dateTime' => $d->format('d-M-Y')
			);

			$this->processCancellation($params);

		}

		return true;
		*/
	}

	/*
	private function processCancellation(array $params){
		$sql = "
            INSERT INTO email_alert_queue(eaq_id, eaq_internal_ref_no, eaq_spb_branch_code, eaq_alert_type, eaq_created_date)
            VALUES (sq_email_alert_queue.nextval,:docId, :spbBranchCode, :alertType, TO_DATE(:dateTime))
        ";

		$this->getDb()->query($sql, $params);

		$sql = "
            UPDATE rfq_quote_relation SET RQR_RFQ_CANCELLED_DATE=SYSDATE WHERE RQR_RFQ_INTERNAL_REF_NO=:docId
        ";
		$this->getDb()->query($sql, array('docId' => $params['docId']));

		return true;
	}
	*/

    /**
     * Returns the most recent quote associated with the given RFQ or null if none found
     *
     * Reworked on 2016-06-11 to avoid displaying duplicate quotes which were imported from the match ones
     *
     * @author  Yuriy Akopov
     * @date    2013-10-15
     *
     * @param   Shipserv_Supplier   $supplier
     * @param   bool                $allowZeroPrice
     * @param   bool                $ignoreImportedQuotes
     *
     * @return  Shipserv_Quote|null
     */
    public function getQuote(Shipserv_Supplier $supplier = null, $allowZeroPrice = false, $ignoreImportedQuotes = false) {
        $select = new Zend_Db_Select($this->getDb());
        $select
            ->from(
                array('q' => Shipserv_Quote::TABLE_NAME),
                array('quote_id' => 'q.' . Shipserv_Quote::COL_ID)
            )
            ->where('q.' . Shipserv_Quote::COL_RFQ_ID . ' = ?', $this->rfqInternalRefNo)
            ->where('q.' . Shipserv_Quote::COL_STATUS . ' = ?', Shipserv_Quote::STATUS_SUBMITTED)
            ->order('q.' . Shipserv_Quote::COL_ID . ' DESC')
        ;

        if (!$allowZeroPrice) {
            $select->where('q.' . Shipserv_Quote::COL_TOTAL_COST . ' > 0');
        }

        if ($supplier) {
            $select->where('q.' . Shipserv_Quote::COL_SUPPLIER_ID . ' = ?', $supplier->tnid);
        }

	    if ($ignoreImportedQuotes) {
		    // DE6555: ignore a quote for the RFQ if it is an imported one
		    $select
		        ->joinLeft(
			        array('mir' => 'match_imported_rfq_proc_list'),
					'mir.mir_qot_internal_ref_no = q.' . Shipserv_Quote::COL_ID,
			        array()
		        )
			    ->where('mir.mir_id IS NULL')
		    ;
	    }

        $quoteId = $select->getAdapter()->fetchOne($select);
        if (strlen($quoteId) === 0) {
            return null;
        }

        $quote = Shipserv_Quote::getInstanceById($quoteId);

        return $quote;
    }

    /**
     * Returns a structure with supplier IDs RFQ has been sent to along with some meta information about that sending
     * Overlaps in purpose with the function in Shipserv_Tradenet_RequestForQuote which will be retired in favour of this one
     *
     * Modified on 2016-03-07 to exclude imported quotes
     *
     * @author  Yuriy Akopov
     * @date    2013-10-15
     *
     * @param   bool    $ignoreMatchSupplier    include proxy match supplier in the returned list
     * @param   bool    $ignoreImportedRfqs
     *
     * @return array
     */
    public function getSuppliers($ignoreMatchSupplier = true, $ignoreImportedRfqs = false) {
        $timeStart = microtime(true);

        $matchBuyerId =  Shipserv_Match_Settings::get(Shipserv_Match_Settings::BUYER_PROXY_ID);         // 11107
        $matchSupplierId = Shipserv_Match_Settings::get(Shipserv_Match_Settings::SUPPLIER_PROXY_ID);    // 99999

        $db = Shipserv_Helper_Database::getDb();

        // joining RFQs with suppliers and meta data
        $selectSuppliers = new Zend_Db_Select($db);
        $selectSuppliers
            ->from(
                array('spb' => Shipserv_Supplier::TABLE_NAME),
                array(
                    self::RFQ_SUPPLIERS_BRANCH_ID => 'spb.' . Shipserv_Supplier::COL_ID
                )
            )
            ->join(
                array('rqr' => 'rfq_quote_relation'),
                'rqr.rqr_spb_branch_code = spb.' . Shipserv_Supplier::COL_ID,
                array()
            )
            ->join(
                array('rfq' => self::TABLE_NAME),
                'rfq.' . Shipserv_Rfq::COL_ID . ' = rqr.rqr_rfq_internal_ref_no',
                array(
                    self::RFQ_SUPPLIERS_FROM_MATCH => new Zend_Db_Expr(
                        'CASE
                            WHEN rfq.' . self::COL_BUYER_ID . ' = ' . $db->quote($matchBuyerId) . ' THEN 1
                            ELSE 0
                        END'
                    ),
                    self::RFQ_SUPPLIERS_RFQ_ID    => 'rfq.' . self::COL_ID,
                )
            )
	        ->where('rfq.' . self::COL_EVENT_HASH . ' = HEXTORAW(?)', $this->rfqEventHash)
	        ->where('rfq.' . self::COL_STATUS . ' <> ?', self::STATUS_DRAFT)
            ->order('rfq.' . Shipserv_Rfq::COL_ID . ' DESC')
            ->distinct()
        ;

	    // added by Yuriy Akopov to exclude RFQs which were created as a part of match quote import process
	    // DE6555, 2016-05-25 
	    if ($ignoreImportedRfqs) {
		    $selectSuppliers
			    ->joinLeft(
			        array('mir' => 'match_imported_rfq_proc_list'),
			        implode(' AND ', array(
				        'mir.mir_rfq_internal_ref_no = rfq.' . Shipserv_Rfq::COL_ID,
				        'mir.mir_spb_branch_code = spb.' . Shipserv_Supplier::COL_ID,
				        'mir.mir_qot_internal_ref_no > 0',
			        )),
			        array()
		        )
		        ->where('mir.mir_id IS NULL')
	        ;
	    }
	    
        // print $selectSuppliers->assemble(); die;

        if ($ignoreMatchSupplier) {
            $selectSuppliers->where('spb.' . Shipserv_Supplier::COL_ID . ' <> ?', $matchSupplierId);
        }

        $supplierInfo = $selectSuppliers->getAdapter()->fetchAll($selectSuppliers);
        $elapsed = microtime(true) - $timeStart;

        if (count($supplierInfo) === 0) {
            return array();
        }

        // deal with types
        foreach ($supplierInfo as $index => $row) {
            $supplierInfo[$index] = array(
                self::RFQ_SUPPLIERS_FROM_MATCH => (bool) $row[self::RFQ_SUPPLIERS_FROM_MATCH],
                self::RFQ_SUPPLIERS_RFQ_ID     => (int) $row[self::RFQ_SUPPLIERS_RFQ_ID],
                self::RFQ_SUPPLIERS_BRANCH_ID  => (int) $row[self::RFQ_SUPPLIERS_BRANCH_ID],
                self::RFQ_SUPPLIERS_ELAPSED    => $elapsed
            );
        }

        return $supplierInfo;
    }

    /**
     * Returns cheapest quote received for the RFQ
     *
     * @param   bool    $matchQuote     return best match quote price or best buyer selected quote price
     * @param   bool    $fullQuoted     100% quoted items only
     *
     * @return  Shipserv_Quote|null
     */
    public function getBestQuote($matchQuote, $fullQuoted = true) {
        $db = Shipserv_Helper_Database::getSsreport2Db();
        $select = new Zend_Db_Select($db);

        $quoteType = ($matchQuote ? 'match' : 'buyer');
        if ($fullQuoted) {
            $quoteType .= '-100-quoted';
        }

        $params = array(
            'rfq_id'        => $this->rfqInternalRefNo,
            'rfq_ref'       => $this->rfqRefNo,
            'quote_type'    => $quoteType
        );

        $select->from(
            'dual',
            new Zend_Db_Expr('get_cheapest_qotid_v2(:rfq_id, :rfq_ref, :quote_type)')
        );

        $quoteId = $db->fetchOne($select, $params);
        if (strlen($quoteId) === 0) {
            return null;
        }

        $quote = Shipserv_Quote::getInstanceById($quoteId);

        return $quote;
    }

    /**
     * Returns Pages user who has created an enquiry
     *
     * @author  Yuriy Akopov
     *
     * @return Shipserv_User
     * @throws Exception
     */
    public function getSenderUser() {
        if (strlen($this->rfqBbuUsrUserCode) === 0) {
            throw new Exception("No user recorded for this RFQ");
        }

        $user = Shipserv_User::getInstanceById($this->rfqBbuUsrUserCode);
        return $user;
    }

    /**
     * Returns sender type and ID raw and non-instantiated
     *
     * @author  Yuriy Akopov
     * @date    2014-05-27
     *
     * @return  array
     */
    public function getOriginalSenderSignature() {
        $db = $this->getDb();

        $select = new Zend_Db_Select($db);
        $select->from(
            'dual',
            array(
                'SENDER_SIGNATURE' => new Zend_Db_Expr(
                    $db->quoteInto('RFQ_GET_SENDER_SIGNATURE(?)', $this->rfqInternalRefNo)
                )
            )
        );

        $signature = $db->fetchOne($select);
        $signatureBits = explode(':', $signature);

        if (count($signatureBits) !== 2) {
            throw new Exception("Failed to produce sender signature for RFQ " . $this->rfqInternalRefNo);
        }

        return $signatureBits;
    }

    /**
     * Resolves the possible proxy IDs and forwards and returns the original sender of the RFQ
     * which could be buyer organisation, supplier one or a user
     *
     * @author  Yuriy Akopov
     * @date    2014-01-17 (replaced with DB proc on 2014-05-12)
     * @story   S9298
     *
     * @return  Shipserv_Buyer|Shipserv_User
     * @throws  Exception
     */
    public function getOriginalSender() {
        $signatureBits = $this->getOriginalSenderSignature();

        switch ($signatureBits[0]) {
            case self::SENDER_TYPE_BUYER:
                $sender = Shipserv_Buyer::getInstanceById($signatureBits[1], true);
                break;

            case self::SENDER_TYPE_SUPPLIER:
                $sender = Shipserv_Supplier_Organisation::getInstanceById($signatureBits[1]);
                break;

            case self::SENDER_TYPE_USER:
                $sender = Shipserv_User::getInstanceById($signatureBits[1]);
                break;

            default:
                throw new Exception("Invalid sender signature returned for RFQ " . $this->rfqInternalRefNo);
        }

        return $sender;
    }

    /**
     * If this RFQ is a match forwards returns the original RFQ forwarded, otherwise returns itself
     *
     * @author  Yuriy Akopov
     * @date    2014-03-07
     * @story   S9607
     *
     * @return  Shipserv_Rfq
     * @throws  Shipserv_Match_Exception
     */
    public function resolveMatchForward() {
        $rfq = $this;
        $db = $this->getDb();

        // resolve possible forwards by the Match engine
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                'rfq.' . Shipserv_Rfq::COL_SOURCE_ID
            )
            ->where('rfq.' . Shipserv_Rfq::COL_ID . ' = ?', $rfq->rfqInternalRefNo);
        ;

        $sourceIds = array();
        while ($rfq->rfqBybBranchCode == Myshipserv_Config::getProxyMatchBuyer()) {
            $sourceRfqId = $db->fetchOne($select);

            if (in_array($sourceRfqId, $sourceIds)) {
                throw new Shipserv_Match_Exception("An endless loop detected in RFQ forwards for RFQ " . $this->rfqInternalRefNo);
            }

            $sourceIds[] = $sourceRfqId;
            $rfq = Shipserv_Rfq::getInstanceById($sourceRfqId);
        }

        return $rfq;
    }

    /**
     * Returns true if the RFQ was sent to match supplier
     *
     * @author  Yuriy Akopov
     * @date    2014-06-10
     * @story   S10311
     *
     * @param   bool    $checkEvent
     *
     * @return  bool
     */
    public function isMatchRfq($checkEvent = false) {
        $db = $this->getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('rqr' => 'rfq_quote_relation'),
                new Zend_Db_Expr('COUNT(rqr.rqr_spb_branch_code)')
            )
            ->where('rqr.rqr_spb_branch_code = ?', Myshipserv_Config::getProxyMatchSupplier())
        ;

        if ($checkEvent) {
            $select->join(
                array('rfq' => self::TABLE_NAME),
                $db->quoteInto('rfq.' . self::COL_EVENT_HASH . ' = HEXTORAW(?)', $this->rfqEventHash),
                array()
            );
        } else {
            $select->where('rqr.rqr_rfq_internal_ref_no = ?', $this->rfqInternalRefNo);
        }

        $match = $select->getAdapter()->fetchOne($select);

        return ($match > 0);
    }

    /**
     * Returns true if the RFQ was selected by automatched scripts
	 *
	 * Changed by Yuriy Akopov on 2015-08-11 by adding $pureAutoMatch
     *
     * @author  Yuriy Akopov
     * @date    2014-06-11
     * @story   S10311
	 *
	 * @param	bool	$pureAutoMatch
     *
     * @return bool
     */
    public function isAutoMatchEvent($pureAutoMatch = false) {
		// check if there any keyword sets matched to this event
        $sets = Shipserv_Match_Auto_Manager::getMatchedKeywordSets($this->rfqEventHash);
        $eventAutomatched = !empty($sets);

        $result = false;

		if (!$pureAutoMatch) {
            // default behaviour - only checks if RFQ event was ever automatched
            $result = $eventAutomatched;

        } else if ($eventAutomatched) {
            // new behaviour - only returns true if RFQ event hasn't been sent to Match prior to automatching it
            $automatchDate = Shipserv_Match_Auto_Manager::getAutomatchedDate($this->rfqEventHash);

            if (is_null($automatchDate)) {
                // should never happen really as we have established there were keyword sets matched to the event
                // so the date is also available. still, in such case it is safe to proceed with "not automathed"
                $result = false;

            } else {
                // now we know the date when the RFQ event got automatched - let's check if it was sent to Match before
                $db = Shipserv_Helper_Database::getDb();
                $select = new Zend_Db_Select($db);
                $select
                    ->from(
                        array('rqr' => 'rfq_quote_relation'),
                        new Zend_Db_Expr('MIN(rqr.rqr_rfq_internal_ref_no)')
                    )
                    ->join(
                        array('rfq' => Shipserv_Rfq::TABLE_NAME),
                        'rfq.' . Shipserv_Rfq::COL_ID . ' = rqr.rqr_rfq_internal_ref_no',
                        array()
                    )
                    ->where('rfq.' . Shipserv_Rfq::COL_EVENT_HASH . ' = HEXTORAW(?)', $this->rfqEventHash)
                    ->where('rqr.rqr_spb_branch_code = ?', Myshipserv_Config::getProxyMatchSupplier())
                    ->where('rqr.rqr_submitted_date < ' . Shipserv_Helper_Database::getOracleDateExpr($automatchDate))
                ;

                $matchRfqId = $db->fetchOne($select);
                // if the ID is returned that means there is an RFQ sent to match before automatched date
                // which means this RFQ event is not 'pure Automatch', it is also a normal Match
                $result = (strlen($matchRfqId) === 0);
            }
        }


        return $result;
    }

    /**
     * Returns true is the RFQ was sent from Pages
     *
     * @author  Yuriy Akopov
     * @date    2014-07-01
     * @story   S10311
     *
     * @param   bool    $resolveMatchForward
     *
     * @return bool
     */
    public function isPagesRfq($resolveMatchForward = true) {
        if ($resolveMatchForward) {
            $rfq = $this->resolveMatchForward();
        } else {
            $rfq = $this;
        }

        return ($rfq->rfqBybBranchCode == Myshipserv_Config::getProxyPagesBuyer());
    }

    /**
     * Adds a supplier to the list of current RFQ recipients
     *
     * @author  Yuriy Akopov
     * @date    2014-10-23
     * @story   S11438
     *
     * @param   Shipserv_Supplier   $supplier
     * @param   Shipserv_User       $user
     *
     * @throws  Exception
     * @return  int
     */
    public function addRecipientSupplier(Shipserv_Supplier $supplier, Shipserv_User $user = null) {
        if (is_null($user)) {
            try {
                $user = Shipserv_User::getInstanceById($this->rfqCreatedBy);
            } catch (Exception $e) {
                $user = Myshipserv_Config::getMatchPagesUser();
            }
        }

        $db = $this->getDb();
	    
	    // DE6682 - check if RQR record already exists for the RFQ
	    $select = new Zend_Db_Select($db);
	    $select
	        ->from(
		        'RFQ_QUOTE_RELATION',
		        'RQR_SEQ_NO'
	        )
		    ->where('RQR_RFQ_INTERNAL_REF_NO = ?', $this->rfqInternalRefNo)
		    ->where('RQR_BYB_BRANCH_CODE = ?', $this->rfqBybBranchCode)
		    ->where('RQR_SPB_BRANCH_CODE = ?', $supplier->tnid)
	    ;
	    
	    $existingRecId = $select->getAdapter()->fetchOne($select);
	    if (strlen($existingRecId) > 0) {
		    $addedReference = $this->addMtmlReferenceForSupplier($supplier);
		    return $existingRecId;
	    }
	    
        $seqNo = $db->fetchOne('SELECT rqr_id.nextval FROM dual');

        $db->beginTransaction();

        try {
            $db->insert('RFQ_QUOTE_RELATION', array(
                'RQR_SEQ_NO'                => $seqNo,
                'RQR_RFQ_INTERNAL_REF_NO'   => $this->rfqInternalRefNo,
                'RQR_SS_RFQ_TRACKING_NO'    => $this->rfqSsTrackingNo,
                'RQR_BYB_BRANCH_CODE'       => $this->rfqBybBranchCode,
                'RQR_BYB_BYO_ORG_CODE'      => $this->rfqBybByoOrgCode,
                'RQR_RFQ_STS'               => 'NEW',
                'RQR_SPB_BRANCH_CODE'       => $supplier->tnid,    // Shipserv_Match_Settings::get(Shipserv_Match_Settings::SUPPLIER_PROXY_ID),
                'RQR_SPB_SUP_ORG_CODE'      => $supplier->orgCode, // Shipserv_Match_Settings::get(Shipserv_Match_Settings::SUPPLIER_PROXY_ORG_ID),
                'RQR_CREATED_BY'            => $user->userId,
                'RQR_UPDATED_BY'            => $user->userId,
                'RQR_CREATED_DATE'          => new Zend_Db_Expr('SYSDATE'),
                'RQR_UPDATED_DATE'          => new Zend_Db_Expr('SYSDATE'),
                'RQR_SUBMITTED_DATE'        => new Zend_Db_Expr('SYSDATE'),
                'RQR_MTML_ACKNOWLEDGED'     => 0
            ));

            $db->update(
                Shipserv_Rfq::TABLE_NAME,
                array(
                    'rfq_vendor_count' => new Zend_Db_Expr('(rfq_vendor_count + 1)')    // since we have added a new supplier
                ),
                $db->quoteInto(Shipserv_Rfq::COL_ID . ' = ?', $this->rfqInternalRefNo)
            );

	        // DE6561: fixing AGI connection for imported match quotes (Yuriy Akopov on 2016-04-21)
	        $addedReference = $this->addMtmlReferenceForSupplier($supplier);

            $db->commit();

        } catch (Exception $e) {
            $db->rollBack();
            throw $e;
        }

        return $seqNo;
    }
    
    
    /**
     * Using sql function pkg_rfq_deadline_control.hide_qot_price, returns true 
     * if the quote price should be hidden (when buyer is keppel and rfq deadline has passed), 
     * and false if should not 
     * For hide_qot_price sql func definition see  http://dev.shipserv.com/svn/shipserv_projects/sservdba/trunk/procs/pkg_rfq_deadline_control.sql)
     * 
     * @author  Claudio Ortelli
     * @date    2016-04-12
     * @story   S16121
     * return Boolean
     */
    public function shouldHideQuotePrice()
    {
        if (!isset($this->rfqInternalRefNo)) {
            throw new Exception('Method "shouldHideQuotePrice" need the property rfqInternalRefNo to be defined, but it was not');
        }
        
        $result = Shipserv_Helper_Database::registryFetchOne(
        		__CLASS__ . '_' . __FUNCTION__,
        		'SELECT pkg_rfq_deadline_control.hide_qot_price(:rfqId) FROM dual',
        		array('rfqId' => $this->rfqInternalRefNo)
        		);

        $result = (Boolean) $result;
        
        return $result;
    }

	/**
	 * Is called when a new RQR record is created to leave an AGI link for imported match quotes
	 * Returns true if the new was record created or false if it already exists / is not needed
	 *
	 * @author  Yuriy Akopov
	 * @story   DE6561
	 * @date    2016-04-22
	 *
	 * @param Shipserv_Supplier $supplier
	 *
	 * @return  bool
	 * @throws  Shipserv_Match_Exception
	 */
	protected function addMtmlReferenceForSupplier(Shipserv_Supplier $supplier) {
		$db = Shipserv_Helper_Database::getDb();

		// Step 1: load an existing MTML reference for the RFQ
		$selectMtmlRef = new Zend_Db_Select($db);
		$selectMtmlRef
			->from(
				'mtml_reference',
				'*'
			)
			->where('mtml_doc_type = ?', 'RFQ')
			->where('mtml_internal_ref_no = ?', $this->rfqInternalRefNo)
		;

		// Step 1.1: first check if the reference already exists for the given supplier
		$selectMtmlRefExact = clone($selectMtmlRef);
		$selectMtmlRefExact
			->where('mtml_spb_branch_code = ?', $supplier->tnid)
			->where('mtml_spb_sup_org_code = ?', $supplier->orgCode)
		;

		if ($selectMtmlRefExact->getAdapter()->fetchRow($selectMtmlRefExact) !== false) {
			// reference record already exists for this supplier and RFQ
			// according to JP, there is no need to create a new one, even if an RQR record is added not for the first time
			return false;
		}

		// Step 1.2: loading an existing reference record (for another supplier)
		$mtmlRefRecord = $selectMtmlRef->getAdapter()->fetchRow($selectMtmlRef);
		if ($mtmlRefRecord === false) {
			// if no record for any supplier exists for this RFQ, don't create a new one according to JP
			return false;
		}

		// Step 1.3: cleansing and editing the loaded record

		// reset primary key field to allow clean autoincrement
		unset($mtmlRefRecord['MTML_QUALIFIER_SEQ_NO']);
		// reset time to be populated by trigger
		unset($mtmlRefRecord['MTML_CREATED_DATE']);
		unset($mtmlRefRecord['MTML_UPDATED_DATE']);
		// replace supplier branch and org in accordance with RQR created above
		$mtmlRefRecord['MTML_SPB_BRANCH_CODE']  = $supplier->tnid;
		$mtmlRefRecord['MTML_SPB_SUP_ORG_CODE'] = $supplier->orgCode;

		// Step 2: retrieve user for the supplier to be added (according to JP any active user would do)
		$selectActiveUser = new Zend_Db_Select($db);
		$selectActiveUser
			->from(
				array('sbu' => 'supplier_branch_user'),
				'sbu.sbu_usr_user_code'
			)
			->join(
				array('usr' => 'users'),
				'usr.usr_user_code = sbu.sbu_usr_user_code',
				array()
			)
			->where('sbu.sbu_spb_branch_code = ?', $supplier->tnid)
			->where('sbu.sbu_sts = ?', 'ACT')
			->where('usr.usr_sts = ?', 'ACT')
		;

		$supplierUserId = $selectActiveUser->getAdapter()->fetchOne($selectActiveUser);
		if (strlen($supplierUserId) === 0) {
			throw new Shipserv_Match_Exception("Active user not found for supplier " . $supplier->tnid . " to create and MTML reference for RFQ " . $this->rfqInternalRefNo);
		}

		// replace user ID in the record to be added
		$mtmlRefRecord['MTML_SBU_USR_USER_CODE'] = $supplierUserId;

		// Step 3: insert a new record
		$db->insert('MTML_REFERENCE', $mtmlRefRecord);
		
		return true;
	}

}
