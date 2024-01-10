<?php
/**
 * A dedicated controller to host webservices related to quote list functionality
 *
 * @author  Yuriy Akopov
 * @date    2014-02-17
 * @story   S8493
 */
class Buyer_QuoteController extends Myshipserv_Controller_Action {
    const
        PARAM_QUOTE_ID  = 'quoteRefNo',
        PARAM_FILTERS   = 'filters'
    ;

    const
        // quote list query fields which are used in more than one function
        COL_DATE_READ     = 'DATE_READ',
        COL_ORDER_ID      = 'ORDER_ID',
        COL_QUOTE_STATUS  = 'QUOTE_STATUS',
        COL_VESSEL_NAME   = 'VESSEL_NAME',
        COL_VESSEL_IMO    = 'VESSEL_IMO'
    ;

    const
        ORDER_SUPPLIER  = 'supplier',
        ORDER_REFERENCE = 'reference',
        ORDER_VESSEL    = 'vessel',
        ORDER_DATE      = 'date',
        ORDER_READ      = 'read',
        ORDER_STATUS    = 'status',
        ORDER_PRIORITY  = 'priority'
    ;

    const
        ORDER_DIR_DESC = 'desc',
        ORDER_DIR_ASC  = 'asc'
    ;

    // filters that could be applied to quote list
    const
        FILTER_KEYWORDS = 'keywords',
        FILTER_VESSEL   = 'vessel',
        FILTER_DAYS     = 'days',
        FILTER_TYPE     = 'type',
        FILTER_STATUS   = 'status',
        FILTER_READ     = 'read',
        FILTER_BUYER    = 'buyer'
    ;

    const
        FILTER_STATUS_ACCEPTED  = 'ACC',
        FILTER_STATUS_DECLINED  = 'DEC',
        FILTER_STATUS_SUBMITTED = 'SUB'
    ;

    const
        FILTER_READ_READ   = 'read',
        FILTER_READ_UNREAD = 'unread'
    ;

    const
        FILTER_TYPE_BUYER   = 'buyer',
        FILTER_TYPE_MATCH   = 'match'
    ;


    public function init() {
        parent::init();

        $allowedActions = array(
            'stats-exclude'
            );
        $action = $this->_getParam('action');
        $user = $this->abortIfNotLoggedIn();

        if (!in_array($action, $allowedActions)) {
            // only selected buyers and shipmates are allowed to access this function so far
            if (!($user->isShipservUser())) {
                throw new Myshipserv_Exception_MessagedException("Sorry, you don't have access to this functionality yet as it is still in beta.");
            }
        }
    }

    /**
     * Helper function to get a quote requested by user
     *
     * @return  Shipserv_Quote
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _getUserQuote() {
        $quoteId = $this->_getParam(self::PARAM_QUOTE_ID);
        if (strlen($quoteId) === 0) {
            throw new Myshipserv_Exception_MessagedException("No quote ID supplied");
        }

        $quote = Shipserv_Quote::getInstanceById($quoteId);

        $helper = new Shipserv_Helper_Controller($this);
        try {
            $rfq = $helper->getRfqById($quote->qotRfqInternalRefNo);
        } catch (Exception $e) {
            throw new Myshipserv_Exception_MessagedException("Requested quote " . $quote->qotInternalRefNo . " cannot be accessed (" . $e->getMessage() . ")");
        }

        return $quote;
    }

    /**
     * Returns JSON with details of the requested quote
     *
     * @author  Yuriy Akopov
     * @date    2014-01-30
     * @story   S9231
     */
    public function quoteDetailsAction() {
        $buyerOrg = $this->getUserBuyerOrg();
        $user = Shipserv_User::isLoggedIn();

        $timeStarted = microtime(true);

        $quote = $this->_getUserQuote();
        $origRfq = $quote->getOriginalRfq();

        $data = array(
            'id'        => (int) $quote->qotInternalRefNo,
            'reference' => $quote->qotRefNo,
            'subject'   => $quote->qotSubject,
            'created'   => Shipserv_Helper_Database::dbDateToIso($quote->qotCreatedDate),
            'expires'   => Shipserv_Helper_Database::dbDateToIso($quote->qotExpiryDate),
            'trackingNo' => $quote->qotSsTrackingNo,

            'rfqId'     => (int) $quote->qotRfqInternalRefNo,
            'hash'      =>  Shipserv_Helper_Security::rfqSecurityHash((int) $quote->qotRfqInternalRefNo),
            'originalRfqId' => (int) $origRfq->rfqInternalRefNo,

            'delivery'  => array(
                'freightForwarder'    => $quote->qotSuggestedShipper,
                'packingInstructions' => $quote->qotPackagingInstructions,
                'terms'         => $quote->qotTermsOfDelivery,
                'termsReadable' => $quote->getReadableDeliveryTerms(),
                'transportMode' => $quote->qotTransportationMode,

                'requested' => Shipserv_Helper_Database::dbDateToIso($origRfq->rfqDateTime),
                'quoted'    => is_null($quote->qotDeliveryLeadTime) ? null : (int) $quote->qotDeliveryLeadTime,

                'releaseTo' => array()
            ),

            'cost' => array(
                'totalCost'            => (float) $quote->qotTotalCost,
                'subTotalCost'         => (float) $quote->qotSubtotal,

                'discountPercentage'   => (float) $quote->qotDiscountPercentage,
                'discountTotalCost'    => $quote->getDiscountedTotal(),
                'discount'             => $quote->getDiscount(),

                'freight' => (float) $quote->qotShippingCost,

                'additional' => array()
            ),

            'currency'             => $quote->qotCurrency,
            'paymentTerms'         => $quote->qotTermsOfPayment,
            'currencyInstructions' => $quote->qotCurrencyInstructions,
            'termsAndConditions'   => $quote->qotGeneralTermsConditions,

            'comments' => $quote->qotComments,
            'url'      => $quote->getUrl(),

            // to be populated later in dedicated code blocks below
            'supplierBranch' => null,
            'lineItemSections' => array()
        );

        // adding possible additional costs
        $additionalCosts = array(
            array($quote->qotAdditionalCostAmount1, $quote->qotAdditionalCostDesc1),
            array($quote->qotAdditionalCostAmount2, $quote->qotAdditionalCostDesc2),
        );

        foreach ($additionalCosts as $index => $cost) {
            if ((strlen($cost[0]) === 0) or ($cost[0] === 0)) {
                continue;
            }

            if (strlen($cost[1]) === 0) {
                $cost[1] = "Other";
            }

            $data['cost']['additional'][] = array(
                'amount'      => (float) $cost[0],
                'description' => ucwords($cost[1])
            );
        }

        // delivery address
        $address = $origRfq->getAddress();
        if (!empty($address)) {
            $data['delivery']['releaseTo'] = array(
                'line1'     => $address['PARTY_STREETADDRESS'],
                'line2'     => $address['PARTY_STREETADDRESS2'],
                'city'      => $address['PARTY_CITY'],
                'state'     => $address['PARTY_COUNTRY_SUB_ENTITY_ID'],
                'zip'       => $address['PARTY_POSTALCODE'],
                'country'   => $address['COUNTRY_NAME']
            );
        }

        $supplier = Shipserv_Supplier::getInstanceById($quote->qotSpbBranchCode, '', true);
        if ($supplier->tnid) {
            $data['supplierBranch'] = array(
                'id'        => (int) $supplier->tnid,
                'name'      => $supplier->name,
                'url'       => $supplier->getUrl(),
                'contact'   => array(
                    'name'  => $supplier->contactPerson,
                    'email' => $supplier->publicEmail,
                    'phone' => $supplier->phoneNo
                ),
                'address'   => array(
                    'line1'   => $supplier->address1,
                    'line2'   => $supplier->address2,
                    'city'    => $supplier->city,
                    'state'   => $supplier->state,
                    'zip'     => $supplier->zipCode,
                    'country' => $supplier->countryName
                )
            );
        }

        $lineItemData = array();

        // request and re-arrange line items
        $rawLineItems = $quote->getLineItem();
        $lineItems = array();
        foreach ($rawLineItems as $rawLineItem) {
            $lineItems[$rawLineItem['QLI_LINE_ITEM_NUMBER']] = $rawLineItem;
        }
        // request changes
        $lineItemChanges = $quote->getLineItemChanges();

        // build resulting structure
        $sectionData = array();
        foreach ($lineItems as $no => $lineOrig) {
            // build section data
            $sectionDescItems = Shipserv_Quote::getLineItemSectionDescription($lineOrig);
            $sectionDesc = implode(', ', $sectionDescItems);
            $sectionDescHash = md5($sectionDesc);

            if (!array_key_exists($sectionDescHash, $sectionData)) {
                $sectionData[$sectionDescHash] = array(
                    'sectionDescription' => $sectionDesc,
                    'lineItems' => array()
                );
            }

            // build line item data for the section
            $lineChange = $lineItemChanges[$no - 1];

            $lineData = array(
                // 'orig'   => $lineOrig,

                'number'      => (int) $no,

                'description' => $lineOrig['QLI_DESC'],
                'partType'    => $lineOrig['QLI_ID_TYPE'],
                'partNumber'  => $lineOrig['QLI_ID_CODE'],
                'comments'    => $lineOrig['QLI_COMMENTS'],

                'quantity'    => (float) $lineOrig['QLI_QUANTITY'],
                'unit'        => $lineOrig['QLI_UNIT'],

                'priceUnit'   => (float) $lineOrig['QLI_UNIT_COST'],
                'priceTotal'  => (float) $lineOrig['QLI_TOTAL_LINE_ITEM_COST'],

                'discountPercentage' => (float) $lineOrig['QLI_DISCOUNT_PERCENTAGE'],
                'discountUnitCost'   => (float) $lineOrig['QLI_DISCOUNTED_UNIT_COST'],

                'config' => array(
                    'name'         => $lineOrig['QLI_CONFG_NAME'],
                    'description'  => $lineOrig['QLI_CONFG_DESC'],
                    'manufacturer' => $lineOrig['QLI_CONFG_MANUFACTURER'],
                    'modelNo'      => $lineOrig['QLI_CONFG_MODEL_NO'],
                    'rating'       => $lineOrig['QLI_CONFG_RATING'],
                    'serialNo'     => $lineOrig['QLI_CONFG_SERIAL_NO'],
                    'drawingNo'    => $lineOrig['QLI_CONFG_DRAWING_NO'],
                    'deptType'     => $lineOrig['QLI_CONFG_DEPT_TYPE'],
                    'deptCode'     => $lineOrig['QLI_CONFG_DEPT_CODE']
                ),

                'changes' => array(
                    'status'      => $lineChange['RQLC_LINE_ITEM_STATUS'],
                    'quantity'    => (is_null($lineChange['RQLC_QUANTITY_CHANGE']) ? null : (float) $lineChange['RQLC_QUANTITY_CHANGE']),
                    'unit'        => $lineChange['RQLC_UNIT_CHANGE'],
                    'description' => $lineChange['RQLC_PRODUCT_DESC_CHANGE'],
                    'config'      => $lineChange['RQLC_CONFG_SECTION_CHANGE']
                )
            );

            // calculate percentage manually if it is not specified
            if (
                empty($lineOrig['QLI_DISCOUNT_PERCENTAGE'])
                and !empty($lineOrig['QLI_DISCOUNTED_UNIT_COST'])
                and !empty($lineOrig['QLI_UNIT_COST'])
            ) {
                $priceDiff = $lineOrig['QLI_UNIT_COST'] - $lineOrig['QLI_DISCOUNTED_UNIT_COST'];
                $lineData['discountPercentage'] = $priceDiff / $lineOrig['QLI_UNIT_COST'] * 100;
            }

            $sectionData[$sectionDescHash]['lineItems'][] = $lineData;
        }

        $data['lineItemSections'] = array_values($sectionData);

        // mark a quote as read by the current user
        Shipserv_Quote_UserAction::markAsRead($quote, $user);

        $data['elapsed'] = microtime(true) - $timeStarted;

        return $this->_helper->json((array)$data);
    }

    /**
     * Returns JSON with a list of quotes available to buyer
     *
     * @author  Yuriy Akopov
     * @date    2014-01-28
     * @story   S9231
     */
    public function quoteListAction() {
        $debugMode = false;
        if ($this->isInTestingEnvironment()) { // only allow debugging features on UAT and UKDEV
            $debugMode = $this->_getParam('debugMode');
            if (strlen($debugMode) === 0) {
                $debugMode = 0;
            }
        }

        if ($debugMode == 1) {
            // clear DB cache so we get worst query performance possible
            Shipserv_Helper_Database::getDb()->query('ALTER SYSTEM FLUSH BUFFER_CACHE');
            Shipserv_Helper_Database::getDb()->query('ALTER SYSTEM FLUSH SHARED_POOL');
        }

        $timeStart = microtime(true);
        $buyerOrg = $this->getUserBuyerOrg();

        // validate user parameters and set defaults
        $filters = $this->_getParam(self::PARAM_FILTERS, array());

        // validate date interval
        if (strlen($filters[self::FILTER_DAYS])) {
            // only allow more to go back for more than one year in test environments, never on live because of performance concerns
            if (!$this->isInTestingEnvironment() and ($filters[self::FILTER_DAYS] > Shipserv_Rfq_EventManager::OUTBOX_DEPTH_DAYS)) {
                $filters[self::FILTER_DAYS] = Shipserv_Rfq_EventManager::OUTBOX_DEPTH_DAYS;
            }
        } else {
            $filters[self::FILTER_DAYS] = 7;   // set default date range
        }

        // validate buyer branch filter
        $pagesBuyerId = Myshipserv_Config::getProxyPagesBuyer();

        $buyerBranches = $buyerOrg->getBranchesTnid();
        $buyerBranches[] = $pagesBuyerId;

        if (strlen($filters[self::FILTER_BUYER])) {
            $buyerBranchId = $filters[self::FILTER_BUYER];

            if (!in_array($buyerBranchId, $buyerBranches)) {
                throw new Myshipserv_Exception_MessagedException("You cannot access other buyer's data");
            }
        } else {
            $filters[self::FILTER_BUYER] = $buyerBranches[0]; // $buyerBranches[0];   // default is the first branch
        }

        // validate sorting parameters
        $orderDir = $this->_getParam(self::PARAM_ORDER_DIR, 'desc');
        $orderBy = $this->_getParam(self::PARAM_ORDER_BY, 'date');
        if (!in_array($orderDir, array('asc', 'desc'))) {
            throw new Myshipserv_Exception_MessagedException('Unknown sort direction');
        }

        $fields = array(
            'QUOTE_ID'       => 'qot.' . Shipserv_Quote::COL_ID,
            'QUOTE_REF'      => 'qot.' . Shipserv_Quote::COL_PUBLIC_ID,
            'QUOTE_DATE'     => new Zend_Db_Expr('TO_CHAR(qot.' . Shipserv_Quote::COL_DATE . ", 'YYYY-MM-DD HH24:MI:SS')"),
            'QUOTE_PRIORITY' => 'qot.' . Shipserv_Quote::COL_PRIORITY,
            'QUOTE_SUBJECT'  => 'qot.' . Shipserv_Quote::COL_SUBJECT,

            'SUPPLIER_ID'    => 'qot.' . Shipserv_Quote::COL_SUPPLIER_ID,

            'RFQ_ID'         => 'qot.' . Shipserv_Quote::COL_RFQ_ID,
            'RFQ_ORIG_ID'    => 'rfq_fwd.' . Shipserv_Rfq::COL_SOURCE_ID
        );

        $select = Shipserv_Quote_ListManager::getQuoteListSelect($buyerOrg, $fields, $filters[self::FILTER_BUYER], $filters[self::FILTER_DAYS]);

        $select = $this->_applyQuoteListFiltering($select, $filters);
        $select = $this->_applyQuoteListSorting($select, $orderBy, $orderDir, $fields, $filters);

        if ($debugMode == 2) {
            print $select->assemble(); exit;
        }

        $paginator = Zend_Paginator::factory($select);

        $pageNo     = (int) $this->_getParam(self::PARAM_PAGE_NO, 1);
        $pageSize   = (int) $this->_getParam(self::PARAM_PAGE_SIZE, 10);

        $paginator->setCurrentPageNumber($pageNo);
        $paginator->setItemCountPerPage($pageSize);

        $quoteRows   = $paginator->getCurrentItems();

        // to make it easier to handle the data in Backbone.js at the front end, meta data is "denormalised" and
        // included into every model item rather than put into an envelope
        $metaData = array(
            'quoteCount' => (int) $paginator->getTotalItemCount(),
            'total'      => (int) count($paginator),
            'number'     => (int) $pageNo,
            'size'       => (int) $pageSize,
            'elapsed'    => array(
                'paginator' => microtime(true) - $timeStart
            )
        );

        $elapsedAggregates = array(
            'order'  => 0,
            'status' => 0,
            'read'   => 0,
            'vessel' => 0
        );

        $data = array();
        foreach($quoteRows as $row) {
            // instantiate objects and calculate data not included in the query per row
            $quoteId = (int) $row['QUOTE_ID'];
            $quote = Shipserv_Quote::getInstanceById($quoteId);

            $supplierId = (int) $row['SUPPLIER_ID'];
            $supplier = Shipserv_Supplier::getInstanceById($supplierId, '', true);

            // check if there is an order for a quote
            $timeStartLoop = microtime(true);
            if (!array_key_exists(self::COL_ORDER_ID, $row)) {
                // orders haven't been joined to save time, need to get the data per-row
                $orderIds = $quote->getOrderIds();
                $orderId = (empty($orderIds) ? null : max($orderIds));
            } else {
                $orderId = $row[self::COL_ORDER_ID];
            }

            if ($orderId) {
                $order = Shipserv_PurchaseOrder::getInstanceById($orderId);
            }
            $elapsedAggregates['order'] += microtime(true) - $timeStartLoop;

            // determine quote status
            $timeStartLoop = microtime(true);
            if (array_key_exists(self::COL_QUOTE_STATUS, $row)) {
                $quoteStatus = $row[self::COL_QUOTE_STATUS];
            } else {
                if ($order) {
                    $quoteStatus = self::FILTER_STATUS_ACCEPTED;
                } else {
                    if ($quote->isDeclined()) {
                        $quoteStatus = self::FILTER_STATUS_DECLINED;
                    } else {
                        $quoteStatus = self::FILTER_STATUS_SUBMITTED;
                    }
                }
            }
            $elapsedAggregates['status'] += microtime(true) - $timeStartLoop;

            // determine read status
            $timeStartLoop = microtime(true);
            if (array_key_exists(self::COL_DATE_READ, $row)) {
                $quoteRead = $row[self::COL_DATE_READ];
            } else {
                $user = Shipserv_User::isLoggedIn();
                $quoteRead = Shipserv_Quote_UserAction::getDateRead($quote, $user);
            }
            $elapsedAggregates['read'] += microtime(true) - $timeStartLoop;

            // get vessel information
            $timeStartLoop = microtime(true);
            if (array_key_exists(self::COL_VESSEL_IMO, $row) or array_key_exists(self::COL_VESSEL_NAME, $row)) {
                $vesselName = $row[self::COL_VESSEL_NAME];
                $vesselImo  = $row[self::COL_VESSEL_IMO];
            } else {
                $rfq = Shipserv_Rfq::getInstanceById($row['RFQ_ID']);
                $vesselName = $rfq->rfqVesselName;
                $vesselImo  = $rfq->rfqVesselImo;
            }
            $elapsedAggregates['vessel'] += microtime(true) - $timeStartLoop;

            $item = array(
                'quote' => array(
                    'id'            => $quoteId,
                    'subject'       => $row['QUOTE_SUBJECT'],
                    'date'          => $row['QUOTE_DATE'],
                    'reference'     => $row['QUOTE_REF'],
                    'rfqId'         => (int) $row['RFQ_ID'],
                    'originalRfqId' => (is_null($row['RFQ_ORIG_ID']) ? (int) $row['RFQ_ID'] : (int) $row['RFQ_ORIG_ID']),
                    'read'          => $quoteRead,
                    'priority'      => ($row['QUOTE_PRIORITY'] === 'Y'),
                    'status'        => $quoteStatus,
                    'url'           => $quote->getUrl()
                ),
                'order' => array(
                    'id'  => $orderId,
                    'url' => ($order ? $order->getUrl() : null)
                ),
                'vessel' => array(
                    'imo'  => $vesselImo,
                    'name' => $vesselName
                ),
                'supplier' => array(
                    'branchId' => $supplierId,
                    'name'     => $supplier->name,
                    'url'      => $supplier->getUrl()
                )
            );

            $data[] = $item;
        }

        $metaData['elapsed']['aggregates'] = $elapsedAggregates;
        $metaData['elapsed']['total'] = microtime(true) - $timeStart;

        foreach ($data as $index => $item) {
            $data[$index]['page'] = $metaData;
        }

        return $this->_helper->json((array)$data);
    }

    /**
     * Converts a quote into an order
     *
     * @author  Yuriy Akopov
     * @date    2014-01-31
     * @story   S9231
     */
    public function quoteOrderAction() {
        $quote = $this->_getUserQuote();

        $po = $quote->convertToPo();

        if( $po->sendPoToTradeNetCore() !== true) {
            throw new Myshipserv_Exception_MessagedException("Failed to create an order");
        }

        return $this->_helper->json((array)array('success' => true));
    }

    /**
     * Appliers user filters to the query built by getBuyerQuotesSelect
     *
     * @param   Zend_Db_Select  $select
     * @param   array           $filters
     *
     * @return  Zend_Db_Select
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _applyQuoteListFiltering(Zend_Db_Select $select, array $filters) {
        $db = $select->getAdapter();

        foreach ($filters as $filterType => $filterValue) {
            if (strlen($filterValue) === 0) {
                continue;
            }

            switch ($filterType) {
                case self::FILTER_BUYER:
                case self::FILTER_DAYS:
                    // these filters are applied at earlier stage
                    continue;
                    break;

                case self::FILTER_KEYWORDS:
                    $select
                        ->join(
                            array('spb' => Shipserv_Supplier::TABLE_NAME),
                            'spb.' . Shipserv_Supplier::COL_ID . ' = qot.' . Shipserv_Quote::COL_SUPPLIER_ID,
                            array()
                        )
                    ;

                    $likeExpression = Shipserv_Helper_Database::escapeLike($db, strtolower($filterValue));
                    $select->where(implode(' OR ', array(
                        'LOWER(qot.' . Shipserv_Quote::COL_PUBLIC_ID . ') ' . $likeExpression,
                        'LOWER(spb.' . Shipserv_Supplier::COL_NAME . ') ' . $likeExpression
                    )));
                    break;

                case self::FILTER_VESSEL:
                    $select
                        ->join(
                            array('rfq' => SHipserv_Rfq::TABLE_NAME),
                            implode(' AND ', array(
                                'rfq.' . Shipserv_Rfq::COL_ID . ' = qot.' . Shipserv_Quote::COL_RFQ_ID,
                                'qot.qot_submitted_date >= rfq.' . Shipserv_Rfq::COL_DATE
                            )),
                            array(
                                self::COL_VESSEL_NAME => 'rfq.' . Shipserv_Rfq::COL_VESSEL_NAME,
                                self::COL_VESSEL_IMO  => 'rfq.' . Shipserv_Rfq::COL_VESSEL_IMO
                            )
                        )
                    ;

                    $vesselName = $db->quote(strtoupper($filterValue));
                    $select->where('UPPER(rfq.' . Shipserv_Rfq::COL_VESSEL_NAME . ') = ' . $vesselName);
                    break;

                case self::FILTER_TYPE:
                    $matchBuyerId = Myshipserv_Config::getProxyMatchBuyer();

                    switch ($filterValue) {
                        case self::FILTER_TYPE_BUYER:
                            $select->where('qot.' . Shipserv_Quote::COL_BUYER_ID . ' <> ?', $matchBuyerId);
                            break;

                        case self::FILTER_TYPE_MATCH:
                            $select->where('qot.' . Shipserv_Quote::COL_BUYER_ID . ' = ?', $matchBuyerId);
                            break;

                        default:
                            throw new Myshipserv_Exception_MessagedException("Invalid quote type filter settings");
                    }
                    break;

                case self::FILTER_STATUS:
                    switch($filterValue) {
                        case self::FILTER_STATUS_ACCEPTED:
                            $select->join(
                                array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                                implode(' AND ', array(
                                    'ord.' . Shipserv_PurchaseOrder::COL_QUOTE_ID . ' = qot.' . Shipserv_Quote::COL_ID,
                                    $db->quoteInto('ord.' . Shipserv_PurchaseOrder::COL_STATUS . ' = ?', Shipserv_PurchaseOrder::STATUS_SUBMITTED)
                                )),
                                array(
                                    self::COL_ORDER_ID      => new Zend_Db_Expr('ord.' . Shipserv_PurchaseOrder::COL_ID),
                                    self::COL_QUOTE_STATUS  => new Zend_Db_Expr($db->quote($filterValue))
                                )
                            );
                            break;

                        case self::FILTER_STATUS_DECLINED:
                        case self::FILTER_STATUS_SUBMITTED:
                            // both options mean no active options for a quote
                            $select
                                ->joinLeft(
                                    array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                                    implode(' AND ', array(
                                        'ord.' . Shipserv_PurchaseOrder::COL_QUOTE_ID . ' = qot.' . Shipserv_Quote::COL_ID,
                                        $db->quoteInto('ord.' . Shipserv_PurchaseOrder::COL_STATUS . ' = ?', Shipserv_PurchaseOrder::STATUS_SUBMITTED)
                                    )),
                                    array(
                                        self::COL_ORDER_ID      => new Zend_Db_Expr('ord.' . Shipserv_PurchaseOrder::COL_ID),
                                        self::COL_QUOTE_STATUS  => new Zend_Db_Expr($db->quote($filterValue))
                                    )
                                )
                                ->where('ord.' . Shipserv_PurchaseOrder::COL_ID . ' IS NULL')
                            ;
                            // both options would require this as a subquery
                            $declineSelect = Shipserv_Quote_ListManager::getDeclineSelect();

                            if ($filterValue == self::FILTER_STATUS_DECLINED) {
                                // declined RFQ means no active orders issues for a quote plus a DEC reply
                                $select->where('qot.' . Shipserv_Quote::COL_ID . ' IN (' . $declineSelect->assemble() . ')');

                            } else if ($filterValue == self::FILTER_STATUS_SUBMITTED) {
                                // submitted quotes means no orders and also no declines
                                // we cannot outer join on subquery to implement 'no declines' requirement, so will join twice:
                                // for no responses at all and for responses other than replies
                                $select->where('qot.' . Shipserv_Quote::COL_ID . ' NOT IN (' . $declineSelect->assemble() . ')');

                            } else {
                                throw new Myshipserv_Exception_MessagedException("This should not normally happen, please check the filter processing workflow");
                            }

                            break;

                        default:
                            throw new Myshipserv_Exception_MessagedException("Invalid quote status filter settings");
                    }
                    break;

                case self::FILTER_READ:
                    $user = Shipserv_User::isLoggedIn();

                    switch($filterValue) {
                        case self::FILTER_READ_UNREAD:
                            $select
                                ->joinLeft(
                                    array('qua' => Shipserv_Quote_UserAction::TABLE_NAME),
                                    implode(' AND ', array(
                                        'qua.' . Shipserv_Quote_UserAction::COL_QUOTE_ID . ' = qot.' . Shipserv_Quote::COL_ID,
                                        $db->quoteInto('qua.' . Shipserv_Quote_UserAction::COL_USER_ID . ' = ?', $user->userId),
                                        $db->quote('qua.' . Shipserv_Quote_UserAction::COL_ACTION . ' = ?', Shipserv_Quote_UserAction::ACTION_READ)
                                    )),
                                    array(
                                        self::COL_DATE_READ => new Zend_Db_Expr('TO_DATE(qua.' . Shipserv_Quote_UserAction::COL_DATE . ", 'YYYY-MM-DD HH24:MI:SS')")
                                    )
                                )
                                ->where('qua.' . Shipserv_Quote_UserAction::COL_ID . ' IS NULL')
                            ;
                            break;

                        case self::FILTER_READ_READ:
                            $select->join(
                                array('qua' => Shipserv_Quote_UserAction::TABLE_NAME),
                                implode(' AND ', array(
                                    'qua.' . Shipserv_Quote_UserAction::COL_QUOTE_ID . ' = qot.' . Shipserv_Quote::COL_ID,
                                    $db->quoteInto('qua.' . Shipserv_Quote_UserAction::COL_USER_ID . ' = ?', $user->userId),
                                    $db->quote('qua.' . Shipserv_Quote_UserAction::COL_ACTION . ' = ?', Shipserv_Quote_UserAction::ACTION_READ)
                                )),
                                array(
                                    self::COL_DATE_READ => 'qua.' . Shipserv_Quote_UserAction::COL_DATE
                                )
                            );

                            break;

                        default:
                            throw new Myshipserv_Exception_MessagedException("Invalid quote read filter settings");
                    }
                    break;

                default:
                    throw new Myshipserv_Exception_MessagedException("An unknown filter requested for quote list");
            }
        }

        return $select;
    }

    /**
     * Applies sorting to quote list query
     *
     * @param   Zend_Db_Select  $select
     * @param   string          $orderBy
     * @param   string          $orderDir
     * @param   array           $fields
     * @param   array           $filters
     *
     * @return  Zend_Db_Select
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _applyQuoteListSorting(Zend_Db_Select $select, $orderBy, $orderDir, array $fields, array $filters) {
        $db = $select->getAdapter();
        $sortDir = ' ' . $orderDir;

        switch ($orderBy) {
            case self::ORDER_SUPPLIER:
                if (strlen($filters[self::FILTER_KEYWORDS]) === 0) {
                    $select
                        ->join(
                            array('spb' => Shipserv_Supplier::TABLE_NAME),
                            'spb.' . Shipserv_Supplier::COL_ID . ' = qot.' . Shipserv_Quote::COL_SUPPLIER_ID,
                            array()
                        )
                    ;
                }

                $select->order('spb.' . Shipserv_Supplier::COL_NAME . $sortDir);
                break;

            case self::ORDER_REFERENCE:
                $select->order('qot.' . Shipserv_Quote::COL_PUBLIC_ID . $sortDir);
                break;

            case self::ORDER_VESSEL:
                if (strlen($filters[self::FILTER_VESSEL]) === 0) {
                    $select
                        ->join(
                            array('rfq' => SHipserv_Rfq::TABLE_NAME),
                            implode(' AND ', array(
                                'rfq.' . Shipserv_Rfq::COL_ID . ' = qot.' . Shipserv_Quote::COL_RFQ_ID,
                                'qot.qot_submitted_date >= rfq.' . Shipserv_Rfq::COL_DATE
                            )),
                            array(
                                self::COL_VESSEL_NAME => 'rfq.' . Shipserv_Rfq::COL_VESSEL_NAME,
                                self::COL_VESSEL_IMO  => 'rfq.' . Shipserv_Rfq::COL_VESSEL_IMO
                            )
                        )
                    ;
                }

                $select->order('rfq.' . Shipserv_Rfq::COL_VESSEL_NAME . $sortDir);
                break;

            case self::ORDER_DATE:
                $select->order('qot.qot_submitted_date' . $sortDir);
                break;

            case self::ORDER_READ:
                if (strlen($filters[self::FILTER_READ]) === 0) {
                    $user = Shipserv_User::isLoggedIn();

                    $select->joinLeft(
                        array('qua' => Shipserv_Quote_UserAction::TABLE_NAME),
                        implode(' AND ', array(
                            'qua.' . Shipserv_Quote_UserAction::COL_QUOTE_ID . ' = qot.' . Shipserv_Quote::COL_ID,
                            $db->quoteInto('qua.' . Shipserv_Quote_UserAction::COL_USER_ID . ' = ?', $user->userId),
                            $db->quote('qua.' . Shipserv_Quote_UserAction::COL_ACTION . ' = ?', Shipserv_Quote_UserAction::ACTION_READ)
                        )),
                        array(
                            self::COL_DATE_READ => 'qua.' . Shipserv_Quote_UserAction::COL_DATE
                        )
                    );
                }
                $select->order(new Zend_Db_Expr('qua.' . Shipserv_Quote_UserAction::COL_DATE . $sortDir));
                break;

            case self::ORDER_PRIORITY:
                $select->order('qot.' . Shipserv_Quote::COL_PRIORITY . $sortDir);
                break;

            case self::ORDER_STATUS:
                if (strlen($filters[self::FILTER_STATUS]) === 0) {
                    $select
                        ->joinLeft(
                            array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                            implode(' AND ', array(
                                'ord.' . Shipserv_PurchaseOrder::COL_QUOTE_ID . ' = qot.' . Shipserv_Quote::COL_ID,
                                $db->quoteInto('ord.' . Shipserv_PurchaseOrder::COL_STATUS . ' = ?', Shipserv_PurchaseOrder::STATUS_SUBMITTED)
                            )),
                            array(
                                self::COL_ORDER_ID => new Zend_Db_Expr('ord.' . Shipserv_PurchaseOrder::COL_ID . ')')
                            )
                        )
                        ->joinLeft(
                            array('decline' => Shipserv_Quote_ListManager::getDeclineSelect()),
                            'decline.qrp_qot_internal_ref_no = qot.' . Shipserv_Quote::COL_ID,
                            array(
                                self::COL_QUOTE_STATUS => new Zend_Db_Expr('
                                    CASE
                                        WHEN ord.' . Shipserv_PurchaseOrder::COL_ID . ' IS NOT NULL THEN ' . $db->quote(self::FILTER_STATUS_ACCEPTED) . '
                                        WHEN ord.' . Shipserv_PurchaseOrder::COL_ID . ' IS NOT NULL THEN ' . $db->quote(self::FILTER_STATUS_DECLINED) . '
                                        ELSE ' . $db->quote(self::FILTER_STATUS_SUBMITTED) . '
                                    END
                                ')
                            )
                        )
                        ->order(array(
                            self::COL_ORDER_ID . $sortDir,
                            self::COL_QUOTE_STATUS . $sortDir
                        ))
                    ;
                } else {
                    // do nothing, because filtering results in all the values in the status fields being identical
                }

                break;

            default:
                throw new Myshipserv_Exception_MessagedException("Invalid quote list sort settings");
        }

        return $select;
    }

    /**
     * (Un)marks the given quote as excluded from stats or returns the current status
     */
    public function statsExcludeAction() {

        $quoteId = $this->_getParam(self::PARAM_QUOTE_ID);
        if (strlen($quoteId) === 0) {
           $this->_helper->json((array)array(
                'reasons' => Shipserv_Quote_UserAction::getExcludeReasons(),
            ));
        } else {
            // $quote = $this->_getUserQuote();
            $quote = Shipserv_Quote::getInstanceById($quoteId);
            $exclude = $this->_getParam('exclude');
            
            if (strlen($exclude) === 0) {
                // no change of the status is required, return current state
                $exclude = Shipserv_Quote_UserAction::isStatsExcluded($quote);

            } else {
                // change status
                $reasonId = $this->_getParam('reasonId');

                $exclude = (bool) $exclude;
                $user = $this->abortIfNotLoggedIn();
                Shipserv_Quote_UserAction::setStatsExclude((bool) $exclude, $quote, $user, $reasonId);
            }

            $this->_helper->json((array)array(
                'quoteId'       => (int) $quote->qotInternalRefNo,
                'statsExclude'  => $exclude,
                'reasons' => Shipserv_Quote_UserAction::getExcludeReasons(),
                'reasonId' =>  Shipserv_Quote_UserAction::getExcludeReasonId($quote->qotInternalRefNo)
            ));
        }
    }
}