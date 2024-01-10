<?php

/**
 * @package ShipServ
 * @author Elvir <eleonard@shipserv.com>
 * @copyright Copyright (c) 2011, ShipServ
 */
class Shipserv_Quote extends Shipserv_Object
{
    const
        TABLE_NAME  = 'QUOTE',

        COL_ID          = 'QOT_INTERNAL_REF_NO',
        COL_RFQ_ID      = 'QOT_RFQ_INTERNAL_REF_NO',
        COL_SUPPLIER_ID = 'QOT_SPB_BRANCH_CODE',
        COL_PUBLIC_ID   = 'QOT_REF_NO',
        COL_SUBJECT     = 'QOT_SUBJECT',
        COL_DATE        = 'QOT_CREATED_DATE',
        COL_ARCHIVED    = 'QOT_ARCHIVAL_STATE',
        COL_BUYER_ID    = 'QOT_BYB_BRANCH_CODE',
        COL_STATUS      = 'QOT_QUOTE_STS',
        COL_PRIORITY    = 'QOT_PRIORITY',
        COL_CURRENCY    = 'QOT_CURRENCY',
        COL_TOTAL_COST  = 'QOT_TOTAL_COST',

        COL_GENUINE     = 'QOT_IS_GENUINE_SPARE',
        COL_PAYMENT_TERMS   = 'QOT_TERMS_OF_PAYMENT',
        COL_LEAD_TIME       = 'QOT_DELIVERY_LEAD_TIME',
        COL_DELIVERY_STATUS = 'QOT_DELIVERY_STS',
        COL_DELIVERY_TIME   = 'QOT_ON_TIME_DELIVERY_TILL',
        COL_DELIVERY_TERMS  = 'QOT_TERMS_OF_DELIVERY',

        COL_ADD_COST1 = 'QOT_ADDITIONAL_COST_AMOUNT1',
        COL_ADD_COST2 = 'QOT_ADDITIONAL_COST_AMOUNT2',
        COL_ADD_COST_DESC1 = 'QOT_ADDITIONAL_COST_DESC1',
        COL_ADD_COST_DESC2 = 'QOT_ADDITIONAL_COST_DESC2',

        COL_LINE_ITEM_COUNT = 'QOT_LINE_ITEM_COUNT'
    ;

    // possible values of COL_STATUS column
    const
        STATUS_SUBMITTED = 'SUB',
        STATUS_DRAFT     = 'DFT',
        STATUS_DELETED   = 'DEL'
    ;

    // possible values of COL_GENUINE column
    const
        GENUINE_YES = 'Y',
        GENUINE_NO  = 'N',
        GENUINE_UNKNOWN = 'U',
        GENUINE_NA  = 'NA'
    ;

	public $id;
	public $name;

    /**
     * Human readable explanations of delivery terms codes
     *
     * @author  Yuriy Akopov
     * @date    2014-01-30
     * @story   S92131
     *
     * @var array
     */
    protected static $_deliveryTerms = array(
        'EXW' => 'Ex Works',
        'FCA' => 'Free Carrier',
        'FAS' => 'Free Alongside Ship',
        'FOB' => 'Free On Board',
        'CFR' => 'Cost and Freight',
        'CIF' => 'Cost, Insurance, Freight',
        'CPT' => 'Carriage Paid To',
        'CIP' => 'Carriage and Insurance Paid To',
        'DAF' => 'Delivered at Frontier',
        'DES' => 'Delivered Ex Ship',
        'DEQ' => 'Delivered Ex Quay',
        'DDU' => 'Delivered Duty Unpaid',
        'DDP' => 'Delivered Duty Paid'
    );
	
	public function __construct ($data)
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
	
	private static function createObjectFromDb( $data )
	{
		$object = new self($data);
		
		return $object;
	}
	
	public static function getInstanceById($id, $useArchive = false)
	{
		$row = self::getDao()->fetchById($id, $useArchive);
		$data = parent::camelCase($row);
		return self::createObjectFromDb($data);
	}

    /**
     * Forwarding resolve step made optional by Yuriy Akopov on 2014-11-19, DE5483
     *
     * @param   int     $id
     * @param   bool    $resolveForward
     * @return Shipserv_Rfq
     * @throws Exception
     */
	public function getRfq( $id = null, $resolveForward = true )
	{
		// if id = '' then get the related RFQ
		if( $id == null )
		{
			$id = $this->qotRfqInternalRefNo;
		}
		
		// pull RFQ
		try
		{
			$forwardedRFQ = Shipserv_Rfq::getInstanceById( $id );	
		}
		catch (Exception $e )
		{
			throw new Exception("System cannot find RFQ: " . $id);
		}

        // added by Yuriy Akopov on 2014-11-19
        if (!$resolveForward) {
            return $forwardedRFQ;
        }

		try
		{
			$originalRFQ = Shipserv_Rfq::getInstanceById( $forwardedRFQ->rfqSourcerfqInternalNo );
			return $originalRFQ;
		}
		catch( Exception $e )
		{
			return $forwardedRFQ;	
		}
	}
	
	public function getLineItem()
	{
		return self::getDao()->fetchLineItems( $this->qotInternalRefNo );
	}
	

	public function getLineItemChanges()
	{
		return self::getDao()->fetchLineItemChanges( $this->qotInternalRefNo, $this->qotRfqInternalRefNo );
	}
	
	public function getSupplier()
	{
		$supplier = Shipserv_Supplier::fetch($this->qotSpbBranchCode, self::getDb(), true);
		if( empty($supplier->tnid) ) 
		{
			throw new Exception("Supplier cannot be found with the QOT_SPB_BRANCH_CODE=" . $this->qotSpbBranchCode);	
		}
		return $supplier;
	}
	
	/**
	 * Get the database access object
	 * @return Shipserv_Oracle_Quote
	 */
	private static function getDao( $what = null )
	{
		if( $what == null )
		{
			return new Shipserv_Oracle_Quote( self::getDb() );
		}
		else if( $what == "lineItem")
		{
			return new Shipserv_Oracle_Quote( self::getDb() );
		}
	}
	
	/**
	 * Returning the url where buyer can place purchase order
	 * @return string url
	 */
	public function getUrlToPlacePurchaseOrder()
	{
		$saltedHash = md5( "Shipserv_Quote" . $this->getRfq()->rfqInternalRefNo . $this->qotInternalRefNo );
		return "http://" . $_SERVER['HTTP_HOST'] . "/trade/po?rfqInternalRefNo=" . $this->getRfq()->rfqInternalRefNo . "&qotInternalRefNo=" . $this->qotInternalRefNo . "&h=" . $saltedHash . "&cname=" . get_class($this);
	}
	
	public function getSecurityHashToPlacePurchaseOrder()
	{
		$hash = $this->qotInternalRefNo  . "-" . $this->qotRfqInternalRefNo . "-" .  $this->qotSpbBranchCode;
		return $hash;
	}
	
	public function convertToPo()
	{
		return Shipserv_PurchaseOrder::createFromQuote($this);
	}
	
	public function getUrl()
	{
		$users = Shipserv_User::getActiveUserBySpbBranchCode($this->qotSpbBranchCode);
		$url = "http://". $this->getHostname() . "/printables/app/print?docid=". $this->qotInternalRefNo . "&usercode=" . $users[0]['USR_USER_CODE'] . "&branchcode=" . $this->qotSpbBranchCode . "&doctype=QOT&custtype=supplier&md5=" . $users[0]['USR_MD5_CODE'] . "";
		return $url;
	}
	
	public static function getPrintableUrl($id)
	{
		return "/user/printable?d=qot&id=" . $id . "&h=" . md5('qot' . $id);
	}
	
	

    /**
     * Returns quote total price as string (amount and currency)
     *
     * @param   string|null $currencyCode
     *
     * @return  float
     */
    public function getPriceTag($currencyCode = null) {
        if (is_null($currencyCode)) {
            $amount = $this->qotTotalCost;
            $currencyCode = $this->qotCurrency;
        } else {
            $amount = Shipserv_Oracle_Currency::convertTransactionCost($this, true, $currencyCode);
        }

        return round($amount, 2);
    }

    /**
     * Returns an order for the quote or null if there are no connected orders
     *
     * @return null|Shipserv_PurchaseOrder
     */
    public function getOrder() {
        $select = new Zend_Db_Select($this->getDb());
        $select
            ->from(
                array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                'ord.' . Shipserv_PurchaseOrder::COL_ID
            )
            ->where('ord.' . Shipserv_PurchaseOrder::COL_QUOTE_ID . ' = ?', $this->qotInternalRefNo)
            ->where('ord.' . Shipserv_PurchaseOrder::COL_STATUS . ' = ?', Shipserv_PurchaseOrder::STATUS_SUBMITTED)
        ;

        $orderId = $select->getAdapter()->fetchOne($select);
        if (strlen($orderId) === 0) {
            return null;
        }

        $order = Shipserv_PurchaseOrder::getInstanceById($orderId);
        return $order;
    }

    /**
     * Check if the quote RFQ was a forwarded one and returns a sender of the original RFQ
     * Is needed if a quote is issued to a proxy buyer and we need to know which one initiated the match
     *
     * @author  Yuriy Akopov
     * @date    2013-12-06
     * @story   S8971
     *
     * @param   bool    $fallBackToDefaultBuyer
     *
     * @return  int|null
     */
    public function getOriginalBuyerId($fallBackToDefaultBuyer = true) {
        $select = new Zend_Db_Select($this->getDb());
        $select
            ->from(
                array('qot' => Shipserv_Quote::TABLE_NAME),
                array()
            )
            ->join(
                array('rfq_fwd' => Shipserv_Rfq::TABLE_NAME),
                'rfq_fwd.' . Shipserv_Rfq::COL_ID . ' = qot.' . Shipserv_Quote::COL_RFQ_ID,
                array()
            )
            ->join(
                array('rfq_match' => Shipserv_Rfq::TABLE_NAME),
                'rfq_match.' . Shipserv_Rfq::COL_ID . ' = rfq_fwd.rfq_sourcerfq_internal_no', // @todo: replace with a constant after merged with S8133
                array('buyer_id' => 'rfq_match.rfq_byb_branch_code')
            )
            ->where('qot.' . Shipserv_Quote::COL_ID . ' = :quote_id')
            ->order('rfq_match.' . Shipserv_Rfq::COL_ID . ' DESC')
        ;

        $buyerIds = $select->getAdapter()->fetchCol($select, array('quote_id' => $this->qotInternalRefNo));

        if (count($buyerIds)) {
            return (int) $buyerIds[0];
        }

        if ($fallBackToDefaultBuyer) {
            return (int) $this->qotBybBranchCode;
        }

        return null;
    }
    
    /**
     * Excluding a Quote from Match related report computation 
     * This mainly touch SSREPORT2 db
     *
     * @author  Elvir Leonard
     * @date    2013-12-06
     * @story   S9233
     *
     *
     * @return  true|false
     */
    public function excludeQuoteFromMatchReport()
    {
    	$sql = "MERGE INTO ";
    }

    /**
     * Check if the quote RFQ was a forwarded one and returns a sender of the original RFQ
     * Is needed if a quote is issued to a proxy buyer and we need to know which one initiated the match
     *
     * @author  Yuriy Akopov
     * @date    2013-12-06
     * @story   S8971
     *
     * @return  Shipserv_Rfq
     */
    public function getOriginalRfq() {
        $db = $this->getDb();

        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('qot' => Shipserv_Quote::TABLE_NAME),
                array(
                    'RFQ_ID' => new Zend_Db_Expr("
                        CASE
                            WHEN rfq_match." . Shipserv_Rfq::COL_ID . " IS NOT NULL THEN rfq_match." . Shipserv_Rfq::COL_ID . "
                            ELSE rfq_orig." . Shipserv_Rfq::COL_ID . "
                        END
                    ")
                )
            )
            ->join(
                array('rfq_orig' => Shipserv_Rfq::TABLE_NAME),
                'rfq_orig.' . Shipserv_Rfq::COL_ID . ' = qot.' . Shipserv_Quote::COL_RFQ_ID,
                array()
            )
            ->joinLeft(
                array('rfq_match' => Shipserv_Rfq::TABLE_NAME),
                implode(' AND ', array(
                    $db->quoteInto('rfq_orig.' . Shipserv_Rfq::COL_BUYER_ID . ' = ?', Myshipserv_Config::getProxyMatchBuyer()),
                    'rfq_match.' . Shipserv_Rfq::COL_ID . ' = rfq_orig.' . Shipserv_Rfq::COL_SOURCE_ID
                )),
                array()
            )
            ->where('qot.' . Shipserv_Quote::COL_ID . ' = ?', $this->qotInternalRefNo)
        ;

        $rfqId = $select->getAdapter()->fetchOne($select);
        $rfq = Shipserv_Rfq::getInstanceById($rfqId);

        return $rfq;
    }

    /**
     * @author  Yuriy Akopov
     * @date    2015-03-11
     * @story   S12888
     *
     * @param   string  $termsOfDelivery
     *
     * @return  string|null
     */
    public static function getReadableDeliveryTermsForKey($termsOfDelivery) {
        if (array_key_exists($termsOfDelivery, self::$_deliveryTerms)) {
            return self::$_deliveryTerms[$termsOfDelivery];
        }

        return $termsOfDelivery;
    }

    /**
     * Returns quote delivery terms in a readable form
     *
     * @author  Yuriy Akopov
     * @date    2014-01-30
     * @story   9231
     *
     * @return string|null
     */
    public function getReadableDeliveryTerms() {
        return self::getReadableDeliveryTermsForKey($this->qotTermsOfDelivery);
    }

    /**
     * Return
     *
     * @author  Yuriy Akopov
     * @date    2014-01-31
     * @story   S9231
     *
     * @param   array   $lineItem
     *
     * @return  array
     */
    public static function getLineItemSectionDescription(array $lineItem) {
        $configFields = array(
            'QLI_CONFG_DESC'         => 'Desc',
            'QLI_CONFG_DEPT_TYPE'    => 'Type',
            'QLI_CONFG_MANUFACTURER' => 'Manufacturer',
            'QLI_CONFG_MODEL_NO'     => 'M/n',
            'QLI_CONFG_SERIAL_NO'    => 'S/n',
            'QLI_CONFG_DRAWING_NO'   => 'Drwg',
            'QLI_CONFG_RATING'       => 'Rating'
        );

        $sectionDescItems = array();

        foreach ($configFields as $field => $label) {
            if (strlen($lineItem[$field])) {
                $sectionDescItems[] = $label . ': ' . $lineItem[$field];
            }
        }

        return $sectionDescItems;
    }

    /**
     * Returns quote total cost after a discount
     *
     * @author  Yuriy Akopov
     * @date    2014-03-26
     * @story   DE4654
     *
     * @return  float
     */
    public function getDiscountedTotal() {
        $cost = $this->qotSubtotal - $this->getDiscount();
        return (float) $cost;
    }

    /**
     * Returns quote discount amount (as opposed to db-defined percentage)
     *
     * @author  Yuriy Akopov
     * @date    2014-03-26
     * @story   DE4654
     *
     * @return  float
     */
    public function getDiscount() {
        $discount = $this->qotDiscountPercentage * $this->qotSubtotal / 100;
        return (float) $discount;
    }

    /**
     * Returns true if a quote has been marked as declined
     *
     * @author  Yuriy Akopov
     * @date    2014-01-02
     *
     * @return  bool
     */
    public function isDeclined() {
        $db = $this->getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('qrp' => 'quote_response'),
                'qrp.qrp_sts'
            )
            ->where('qrp.qrp_qot_internal_ref_no = ?', $this->qotInternalRefNo)
            ->order('qrp.qrp_updated_date DESC')
        ;

        $status = $db->fetchOne($select);

        return ($status === 'DEC');
    }

    /**
     * Returns active orders posted for the quote
     *
     * @return  array
     */
    public function getOrderIds() {
        $db = $this->getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                'ord.' . Shipserv_PurchaseOrder::COL_ID
            )
            ->where('ord.' . Shipserv_PurchaseOrder::COL_STATUS . ' = ?', Shipserv_PurchaseOrder::STATUS_SUBMITTED)
            ->where('ord.' . Shipserv_PurchaseOrder::COL_QUOTE_ID . ' = ?', $this->qotInternalRefNo)
        ;

        $orderIds = $db->fetchCol($select);

        return $orderIds;
    }

    /**
     * Returns true is the quote was emailed about as a match quote notification
     *
     * @author  Yuriy Akopov
     * @date    2014-06-12
     * @story   S10311
     *
     * @return bool
     */
    public function wasEmailedAsMatch() {
        $sentAt = Shipserv_Match_BuyerAlert::getQuoteAlertDate($this);

        return !is_null($sentAt);
    }

    /**
     * Returns true is the quote is a quote sent by a match-selected supplier
     * Extended by Yuriy Akopov on 2014-11-19
     *
     * @author  Yuriy Akopov
     * @date    2014-06-13
     * @story   S10311
     *
     * @return  bool
     * @throws  Exception
     */
    public function isMatchQuote() {
        $matchProxyBuyerId = Myshipserv_Config::getProxyMatchBuyer();

        return ($this->qotBybBranchCode == $matchProxyBuyerId);

        /*
        // if it is not a proxy buyer it might still be a edge case of a match quote as described in DE5483
        // it happens when an RFQ is forwarded more than once (e.g. a supplier received a match RFQ, but then forwards it to another one)
        $rfq = $this->getRfq(null, false);
        $processedIds = array();
        while (strlen($rfq->rfqSourcerfqInternalNo)) {
            if (in_array($rfq->rfqInternalRefNo, $processedIds)) {
                throw new Exception("Circular link detected when processing POM RFQ forwards of quote " . $this->qotInternalRefNo);
            } else {
                $processedIds[] = $rfq->rfqInternalRefNo;
            }

            $rfq = Shipserv_Rfq::getInstanceById($rfq->rfqSourcerfqInternalNo);
            // trying to detect POM forward of a match RFQ
            if ($rfq->rfqBybBranchCode == $matchProxyBuyerId) {
                return true;
            }
        }

        return false;
        */
    }

    /**
     * Returns true if the quote is a quote sent by a match-selected supplier as a part of automatch process
     *
     * @author  Yuriy Akopov
     * @date    2014-06-13
     * @story   S10311
     *
     * @param   bool    $pureAutomatch
     *
     * @return  bool
     */
    public function isAutoMatchQuote($pureAutomatch = false) {
        // cheapest check ran first
        if (!$this->isMatchQuote()) {
            // every automatch quote is also a match quote by definition
            // so if it is not a match quote, it cannot be an automatch one either
            return false;
        }

        $rfq = $this->getRfq();
        if (!$rfq->isAutoMatchEvent($pureAutomatch)) {
            // RFQ event the quote is replying to is wasn't marked for automatch
            return false;
        }

        // following conversation with Stuart (2014-06-19) it is okay to assume any match quote sent in response to auto match
        // RFQ event is a auto match quote (even if it was issued earlier, before the event was recognised by auto match"

        return true;

        // code commented below does further analysis, but that precision was considered unnecessary

        /*
        // check if quote supplier is not found in buyer selected supplier list
        $supplierId = $this->qotSpbBranchCode;
        $rfqEventSuppliers = $rfq->getSuppliers();

        foreach ($rfqEventSuppliers as $supplierInfo) {
            if ($supplierInfo[Shipserv_Rfq::RFQ_SUPPLIERS_FROM_MATCH]) {
                continue;
            }

            if ($supplierInfo[Shipserv_Rfq::RFQ_SUPPLIERS_BRANCH_ID] == $supplierId) {
                // supplier that replied with a quote is a buyer selected supplier
                return false;
            }
        }

        // now check if the supplier sending this quote is amongst the automatched suppliers
        $autoBranches = Shipserv_Match_Auto_Manager::getFlattenedOwnerBranches($rfq->rfqInternalRefNo);
        if (!in_array($supplierId, $autoBranches)) {
            // a different supplier replies with a quote, no the one matched automatically
            return false;
        }

        // if we are here then all the checks were passed and this quote is an auto match one
        return true;
        */
    }

    /**
     * @author  Yuriy Akopov
     * @date    2014-10-23
     * @story   S11438
     *
     * @return  bool
     */
    public function prepareForAutoImport() {
        if (!$this->isMatchQuote()) {
            // no need to import non-match quotes
            return false;
        }

        $rfq = $this->getOriginalRfq();

        if (!$rfq->isMatchRfq()) {
            // should not happen in theory, but let's terminate silently if that is the case in DB
            return false;
        }

	    // query returns no data when the buyer needs to re-send the RFQ to get match quotes
	    // and a value returned means the match quote should be imported automatically

        $db = Shipserv_Helper_Database::getDb();
        $select = new Zend_Db_Select($db);
        $select
            ->from(
                array('byb' => Shipserv_Buyer_Branch::TABLE_NAME),
                'byb.' . Shipserv_Buyer_Branch::COL_ID
            )
            ->join(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                'rfq.' . Shipserv_Rfq::COL_BUYER_ID . ' = byb.' . Shipserv_Buyer_Branch::COL_ID,
                array()
            )
	        ->where('rfq.' . Shipserv_Rfq::COL_ID . ' = ?', $rfq->rfqInternalRefNo)
	        ->where(implode(' OR ', array(
		        // S16054 by Yuriy Akopov on 2016-03-09 - if 1, quotes are downloaded by the buyer on their own
		        $db->quoteInto('byb.' . Shipserv_Buyer_Branch::COL_MATCH_QUOTE_IMPORT . ' = ?', 1),
		        // Web Buyer
		        '(' . implode(' AND ', array(
			        $db->quoteInto('byb.' . Shipserv_Buyer_Branch::COL_MTML_BUYER . ' = ?', 'N'),
			        'byb.' . Shipserv_Buyer_Branch::COL_QS_PROFILE_TYPE . ' IS NOT NULL'
		        )) . ')'
	        )))
        ;

        $buyerNeedsQuoteImported = $db->fetchOne($select);
        if (!$buyerNeedsQuoteImported) {
            return false;
        }

        $supplier = $this->getSupplier();
        $rfq->addRecipientSupplier($supplier);

        return true;
    }

    /**
     * Moved to Quote class by Yuriy Akopov
     *
     * @author  Attila Olbrich
     *
     * @return  string
     */
    public function getGenuineInfo() {
        switch ($this->qotIsGenuineSpare) {
            case self::GENUINE_YES:
                $message = 'Yes'; //'Genuine';
                break;

            case self::GENUINE_NO:
                $message = 'No'; // 'Not Genuine';
                break;

            case self::GENUINE_NA:
                $message = 'Not Applicable';
                break;

            case self::GENUINE_UNKNOWN:
                $message = 'Unknown';
                break;

            case '':
                $message = 'Not specified';
                break;

            default:
                $key = (int) $this->qotIsGenuineSpare;
                if ($key > 0) {
                    $referenceDAO = new Shipserv_Oracle_Reference(Shipserv_Helper_Database::getDb());
                    $referenceArray = $referenceDAO->fetchQuoteQality($key);
                    if (count($referenceArray) > 0) {
                        $message = $referenceArray[0]['REF_VALUE'];
                    } else {
                        $message = 'Not specified';
                    }
                } else {
                    $message = 'Not specified';
                }
        }

        return $message;
    }

	/**
	 * Returns the share of line items quoted back of requested by the buyer
	 *
	 * @author  Yuriy Akopov
	 * @date    2016-01-28
	 * @story   DE6362
	 *
	 * @return float|int|null
	 * @throws Exception
	 */
	public function getCompleteness() {
		$quoteLineItems = $this->getLineItem();
		$lineItemsQuoted = 0;
		foreach ($quoteLineItems as $lineItem) {
			if ($lineItem['QLI_TOTAL_LINE_ITEM_COST'] > 0) {
				$lineItemsQuoted++;
			}
		}

		if ($lineItemsQuoted === 0) {
			return 0;
		}

		$rfq = $this->getRfq();
		$lineItemsRequested = $rfq->rfqLineItemCount;
		if ($lineItemsRequested == 0) {
			return null;
		}

		return ($lineItemsQuoted / $lineItemsRequested);
	}
}
