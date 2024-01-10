<?php
/**
 * A dedicated controller to host webservices related to RFQ list functionality
 *
 * @author  Yuriy Akopov
 * @date    2014-02-17
 */
class Buyer_RfqController extends Myshipserv_Controller_Action {
    // incoming parameter names
    
    const
        PARAM_RFQ       = 'rfqRefNo',
        PARAM_FILTERS   = 'filters',
        PARAM_HASH      = 'hash'
    ;

    const
        // RFQ list query fields used in more than one function
        RFQ_CLOSED     = 'CLOSED',
        RFQ_FORWARDS   = 'FORWARDS',
        RFQ_RESPONSES  = 'RESPONSES',
        // a pseudo field which is not really a part of the query but is supplied as a sorting option
        // @todo: to be changed to 'closed' which is a part of the query
        RFQ_STATUS     = 'STATUS',
        RFQ_REF_NO     = 'REF_NO',
        RFQ_SUBJECT    = 'SUBJECT',
        RFQ_VESSEL     = 'VESSEL'
    ;

    // identifies for filters accepted for the RFQ list
    const
        FILTER_DAYS        = 'days',
        FILTER_KEYWORDS    = 'keywords',
        FILTER_VESSEL      = 'vessel',
        FILTER_VESSEL_IMO  = 'vessel_imo',
        FILTER_STATUS      = 'status',
        FILTER_BUYER       = 'buyer',
        FILTER_MATCH        = 'matchStat'
    ;

    // statuses returned for the RFQ events and accepted for filtering that list (values of RFQ_LIST_FILTER_STATUS field above)
    const
        STATUS_CLOSED   = 'C',  // there is an order in an RFQ event
        STATUS_OPEN     = 'OPN' // no order received by an RFQ event
    ;

    const
        MATCH_MATCH          = 'match',
        MATCH_BUYER_SELECTED = 'buyer'
    ;

    public function init() {
        parent::init();
        // only selected buyers and shipmates are allowed to access this function so far
        $this->abortIfNotLoggedIn();
    }

    /**
     * Returns JSON with RFQ savings information
     *
     * @author  Yuriy Akopov
     * @story   S8133
     * @date    2013-11-21
     */
    public function rfqSavingsAction() {
        $timeStart = microtime(true);

        $matchSupplierId = Shipserv_Match_Settings::get(Shipserv_Match_Settings::SUPPLIER_PROXY_ID);

        $helper = new Shipserv_Helper_Controller($this);
        $eventRfq = $helper->getRfqById($this->_getParam(self::PARAM_RFQ));

        $eventSupplierInfo = $eventRfq->getSuppliers(false);
        $matchRfqId = null;
        $hasBuyerSelected = false;
        foreach ($eventSupplierInfo as $supplierInfo) {
            if ($supplierInfo[Shipserv_Rfq::RFQ_SUPPLIERS_BRANCH_ID] == $matchSupplierId) {
                $matchRfqId = $supplierInfo[Shipserv_Rfq::RFQ_SUPPLIERS_RFQ_ID];
            } else {
                $hasBuyerSelected = true;
            }
        }
                                
        if (is_null($matchRfqId)) {
            $data = array(
                'match_status' => false,
                'buyer_status' => $hasBuyerSelected,

                'potential_savings'  => 0.0,
                'actual_savings'     => 0.0,

                'best_price_original' => null,
                'best_price_match'    => null,
            );
        } else {
            $matchRfq = Shipserv_Rfq::getInstanceById($matchRfqId);

            $bestMatchQuote = $matchRfq->getBestQuote(true, true);
            $bestBuyerQuote = $matchRfq->getBestQuote(false, true);

            $data = array(
                'match_status' => true,
                'buyer_status' => $hasBuyerSelected,

                'potential_savings'  => $matchRfq->getPotentialSaving(),
                'actual_savings'     => $matchRfq->getActualSaving(),

                'best_price_original' => ($bestBuyerQuote ? $bestBuyerQuote->getPriceTag(Shipserv_Oracle_Currency::CUR_USD) : null),
                'best_price_match'    => ($bestMatchQuote ? $bestMatchQuote->getPriceTag(Shipserv_Oracle_Currency::CUR_USD) : null)
            );
        }

        //S16128
        if ($eventRfq->shouldHideQuotePrice()) {
            $data['savings_hidden'] = true;
        } else {
            $data['savings_hidden'] = false;
        }
        
        $data['elapsed'] = microtime(true) - $timeStart;

        return $this->_helper->json((array)$data);
    }

    /**
     * Helper function for rfqListAction(), applies user sorting to the given RFQ list query
     *
     * @author  Yuriy Akopov
     * @date    2013-10-23
     * @story   S8492
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
    protected function _applyRfqListSorting(Zend_Db_Select $select, $orderBy, $orderDir, array $fields, array $filters) {
        $db = $select->getAdapter();

        switch (strtoupper($orderBy)) {
            // sort RFQ events by number of quotes
            case self::RFQ_RESPONSES:
                // @todo: temporarily disabled in 4.8 to allow calculating number of responses per visible row and not for every event

                /*
                if (strlen($filters[self::FILTER_STATUS]) === 0) {
                    // quote table hasn't already been joined, count the number of submitted ones
                    $select
                        ->joinLeft(
                            array('qot' => Shipserv_Quote::TABLE_NAME),
                            implode(' AND ', array(
                                'qot.' . Shipserv_Quote::COL_RFQ_ID . ' = rfq.' . Shipserv_Rfq::COL_ID,
                                $db->quoteInto('qot.' . Shipserv_Quote::COL_STATUS . ' = ?', Shipserv_Quote::STATUS_SUBMITTED),
                                'qot.' . Shipserv_Quote::COL_TOTAL_COST . ' > 0'
                            )),
                            array(
                                self::RFQ_RESPONSES => new Zend_Db_Expr('COUNT(DISTINCT qot.' . Shipserv_Quote::COL_SUPPLIER_ID . ')')
                            )
                        )
                    ;
                }

                $select->order(self::RFQ_RESPONSES . ' ' . $orderDir);
                */

                break;

            // sort by RFQ status
            case self::RFQ_STATUS:
                if (strlen($filters[self::FILTER_STATUS]) === 0) {
                    // if order table hasn't been joined in yet
                    $select
                        ->joinLeft(
                            array('qot' => Shipserv_Quote::TABLE_NAME),
                            implode(' AND ', array(
                                'qot.' . Shipserv_Quote::COL_RFQ_ID . ' = rfq.' . Shipserv_Rfq::COL_ID
                            )),
                            array()
                        )
                        ->joinLeft(
                            array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                            implode(' AND ', array(
                                'ord.' . Shipserv_PurchaseOrder::COL_QUOTE_ID . ' = qot.' . Shipserv_Quote::COL_ID,
                                $db->quoteInto('ord.' . Shipserv_PurchaseOrder::COL_STATUS . ' = ?', Shipserv_PurchaseOrder::STATUS_SUBMITTED)
                            )),
                            array(
                                self::RFQ_CLOSED => new Zend_Db_Expr('MAX(ord.' . Shipserv_PurchaseOrder::COL_ID . ')')
                            )
                        )
                    ;
                }

                $select
                    ->order(array(
                        'MAX(ord.' . Shipserv_PurchaseOrder::COL_ID .') ' . $orderDir
                    ))
                ;
                break;

            default:
                if (array_key_exists(strtoupper($orderBy), $fields)) {
                    $orderBy = strtoupper($orderBy);
                    $select->order(strtoupper($orderBy) . ' ' . $orderDir);
                } else {
                    throw new Myshipserv_Exception_MessagedException("Unknown RFQ list sorting field " . $orderBy);
                }
        }

        return $select;
    }

    /**
     * Helper function for rfqListAction(), applies user filtering to the given RFQ list query
     *
     * @author  Yuriy Akopov
     * @date    2013-10-23
     * @story   S8492
     *
     * @param   Zend_Db_Select  $select
     * @param   array           $filters
     *
     * @return  Zend_Db_Select
     * @throws  Exception
     */
    protected function _applyRfqListFiltering(Zend_Db_Select $select, array $filters = array()) {
        if (empty($filters)) {
            return $select;
        }

        $db = $select->getAdapter();

        $config = Zend_Registry::get('config');
        $pagesBuyerProxy = $config->shipserv->pagesrfq->buyerId;

        foreach($filters as $filterName => $filterValue) {
            if (strlen($filterValue) === 0) {
                continue;
            }

            switch ($filterName) {
                // number of days to go back
                case self::FILTER_DAYS:
                // buyer branch (including the Pages proxy)
                case self::FILTER_BUYER:
                    // do nothing - these filters are set on the upper level and by this time they're already in place
                    break;

                // keywords in RFQ and its line items text fields
                case self::FILTER_KEYWORDS:
                    $likeStr = Shipserv_Helper_Database::escapeLike($select->getAdapter(), strtolower($filterValue));
                    $select
                        ->joinLeft(
                            array('rfl' => 'rfq_line_item'),
                            implode(' AND ', array(
                                'rfl.rfl_rfq_internal_ref_no = rfq.' . Shipserv_Rfq::COL_ID,
                                '(' . implode(' OR ', array(
                                    'lower(rfl.rfl_product_desc) ' . $likeStr,
                                    'lower(rfl.rfl_comments) ' . $likeStr
                                )) . ')'
                            )),
                            array()
                        )
                        ->where(implode(' OR ', array(
                            'lower(rfq.' . Shipserv_Rfq::COL_PUBLIC_ID . ') ' . $likeStr,
                            'lower(rfq.' . Shipserv_Rfq::COL_SUBJECT . ') ' . $likeStr,
                            'lower(rfq.' . Shipserv_Rfq::COL_COMMENTS . ') ' . $likeStr,
                            'rfl.rfl_rfq_internal_ref_no IS NOT NULL'
                        )))
                    ;
                    break;

                // vessel name
                case self::FILTER_VESSEL:
                    $select->where('lower(rfq.' . Shipserv_Rfq::COL_VESSEL_NAME . ') = ?', strtolower($filterValue));
                    break;

                // RFQ (event) status
                case self::FILTER_STATUS:
                    switch ($filterValue) {
                        // display RFQ events which don't have a single order
                        case self::STATUS_OPEN:
                            $rfqStatus = Shipserv_Rfq::STATUS_SUBMITTED;

                            // left join orders to exclude events which RFQs has resulted in an order
                            $select
                                ->joinLeft(
                                    array('qot' => Shipserv_Quote::TABLE_NAME),
                                    implode(' AND ', array(
                                        'qot.' . Shipserv_Quote::COL_RFQ_ID . ' = rfq.' . Shipserv_Rfq::COL_ID
                                    )),
                                    array(
                                        /*
                                        self::RFQ_RESPONSES => new Zend_Db_Expr('
                                            COUNT(DISTINCT CASE
                                                WHEN qot.' . Shipserv_Quote::COL_STATUS . ' = ' . $db->quote(Shipserv_Quote::STATUS_SUBMITTED) . ' THEN qot.' . Shipserv_Quote::COL_SUPPLIER_ID . '
                                                ELSE NULL
                                            END)
                                        ')
                                        */
                                    )
                                )
                                ->joinLeft(
                                    array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                                    implode(' AND ', array(
                                        'ord.' . Shipserv_PurchaseOrder::COL_QUOTE_ID . ' = qot.' . Shipserv_Quote::COL_ID,
                                        $db->quoteInto('ord.' . Shipserv_PurchaseOrder::COL_STATUS . ' = ?', Shipserv_PurchaseOrder::STATUS_SUBMITTED)
                                    )),
                                    array(
                                        self::RFQ_CLOSED => new Zend_Db_Expr('MAX(ord.' . Shipserv_PurchaseOrder::COL_ID . ')')
                                    )
                                )
                                ->where('ord.' . Shipserv_PurchaseOrder::COL_ID . ' IS NULL')
                            ;
                            break;

                        case self::STATUS_CLOSED:
                            // only display RFQ events which have at least one order placed
                            $select
                                ->join(
                                    array('qot' => Shipserv_Quote::TABLE_NAME),
                                    implode(' AND ', array(
                                        'qot.' . Shipserv_Quote::COL_RFQ_ID . ' = rfq.' . Shipserv_Rfq::COL_ID
                                    )),
                                    array(
                                        /*
                                        self::RFQ_RESPONSES => new Zend_Db_Expr('
                                            COUNT(DISTINCT CASE
                                                WHEN qot.' . Shipserv_Quote::COL_STATUS . ' = ' . $db->quote(Shipserv_Quote::STATUS_SUBMITTED) . ' THEN qot.' . Shipserv_Quote::COL_SUPPLIER_ID . '
                                                ELSE NULL
                                            END)
                                        ')
                                        */
                                    )
                                )
                                ->join(
                                    array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                                    implode(' AND ', array(
                                        'ord.' . Shipserv_PurchaseOrder::COL_QUOTE_ID . ' = qot.' . Shipserv_Quote::COL_ID,
                                        $db->quoteInto('ord.' . Shipserv_PurchaseOrder::COL_STATUS . ' = ?', Shipserv_PurchaseOrder::STATUS_SUBMITTED)
                                    )),
                                    array(
                                        self::RFQ_CLOSED => new Zend_Db_Expr('MAX(ord.' . Shipserv_PurchaseOrder::COL_ID . ')')
                                    )
                                )
                            ;
                            break;

                        default:
                            throw new Exception("Unrecognised RFQ status filter " . $filterValue);
                    }
                    break;

                case self::FILTER_MATCH:
                    switch ($filterValue) {
                        case self::MATCH_MATCH:
                            $matchStatus = 1;
                            break;

                        case self::MATCH_BUYER_SELECTED:
                            $matchStatus = 0;
                            break;

                        default:
                            throw new Myshipserv_Exception_MessagedException("Unrecognised RFQ type filter " . $filterValue);
                    }

                    $select->having("MAX(
                        CASE
                            WHEN rfq." . Shipserv_Rfq::COL_BUYER_ID . " = " . $db->quote(Myshipserv_Config::getProxyMatchBuyer()) . " THEN 1
                            ELSE 0
                        END) = " . $matchStatus . "
                    ");

                    break;

                default:
                    throw new Myshipserv_Exception_MessagedException("Unrecognised RFQ list filter " . $filterName);
            }
        }

        return $select;
    }

    /**
     * Returns JSON with RFQs posted by user buyers with user requested filtering / sorting applied
     *
     * @author  Yuriy Akopov
     * @date    2013-10-22
     * @story   S8492
     *
     * @throws Exception
     */
    public function rfqListAction() {
        $debugMode = false;
        
        if (Myshipserv_Config::isInDevelopment(true)) {
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
        $db = Shipserv_Helper_Database::getDb();

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
            $filters[self::FILTER_BUYER] = null; // $buyerBranches[0];   // default is the first branch
        }

        // validate sorting parameters
        $orderDir = $this->_getParam(self::PARAM_ORDER_DIR, 'desc');
        $orderBy = $this->_getParam(self::PARAM_ORDER_BY, 'DATE_SENT');
        if (!in_array($orderDir, array('asc', 'desc'))) {
            throw new Myshipserv_Exception_MessagedException('Unknown sort direction');
        }

        $fields = array(
            // fields taken from the table as they are
            'RFQ_ID'        => 'MIN(rfq.' . Shipserv_Rfq::COL_ID . ')',
            'RFQ_COUNT'     => 'COUNT(rfq.' . Shipserv_Rfq::COL_ID . ')',
            self::RFQ_REF_NO /*'RFQ_REF_NO' */   => 'MIN(rfq.' . Shipserv_Rfq::COL_PUBLIC_ID .')',
            self::RFQ_VESSEL /*'RFQ_VESSEL'*/    => 'MIN(rfq.' . Shipserv_Rfq::COL_VESSEL_NAME . ')',
            self::RFQ_SUBJECT /*'RFQ_SUBJECT'*/   => 'MIN(rfq.' . Shipserv_Rfq::COL_SUBJECT . ')',
            'DATE_SENT'     => 'MIN(rfq.' . Shipserv_Rfq::COL_DATE . ')',
            'RFQ_BUYER_ID'  => 'MIN(rfq.' . Shipserv_Rfq::COL_BUYER_ID .')',
            // 'RFQ_STATUS'    => new Zend_Db_Expr('MIN(rfq.' . Shipserv_Rfq::COL_STATUS . ')'),
            // fields with some logic behind
            'PRIORITY' => new Zend_Db_Expr("
                CASE
                    WHEN MAX(rfq." . Shipserv_Rfq::COL_PRIORITY . ") = 'Y' THEN 1
                    ELSE 0
                END
            "),
            'INTEGRATED' => new Zend_Db_Expr("
                CASE
                    WHEN MAX(rfq." . Shipserv_Rfq::COL_BUYER_ID . ") = " . $db->quote($pagesBuyerId) . " THEN 0
                    ELSE 1
                END
            "),
            'TYPE' => new Zend_Db_Expr("MAX(
                CASE
                    WHEN rfq." . Shipserv_Rfq::COL_BUYER_ID . " = " . $db->quote(Myshipserv_Config::getProxyMatchBuyer()) . " THEN 1
                    ELSE 0
                END)
            ")
        );

        // update RFQ events table
        $select = Shipserv_Rfq_EventManager::getEventListSelect($buyerOrg, $fields, $filters[self::FILTER_BUYER], $filters[self::FILTER_DAYS]);
        $this->_applyRfqListFiltering($select, $filters);
        $this->_applyRfqListSorting($select, $orderBy, $orderDir, $fields, $filters);

        if ($debugMode == 2) {
            // if requested, do not proceed with running the queries and just print them out instead
            print $select->assemble(); exit;
        }

        // dealing with pagination
        $pageNo = (int) $this->_getParam(self::PARAM_PAGE_NO, 1);
        $pageSize = (int) $this->_getParam(self::PARAM_PAGE_SIZE, 10);

        $timeStartPaginator = microtime(true);

        $paginator = Zend_Paginator::factory($select);
        $paginator->setCurrentPageNumber($pageNo);
        $paginator->setItemCountPerPage($pageSize);

        $rfqRows = $paginator->getCurrentItems();
        $total   = $paginator->getTotalItemCount();

        $elapsedPaginator = microtime(true) - $timeStartPaginator;

        $elapsedAggregates = array(
            'closed'    => 0,
            'responses' => 0,
            'forwards'  => 0,
            'match'     => 0
        );
        $data = array();
        foreach($rfqRows as $row) {
            $rfqId = (int) $row['RFQ_ID'];
            $item = array(
                'rfq_id'            => $rfqId,
                'hash' => Shipserv_Helper_Security::rfqSecurityHash($rfqId),

                'rfq_count'         => (int) $row['RFQ_COUNT'],
                'event_hash'        => $row[Shipserv_Rfq_EventManager::COL_EVENT_HASH],

                'date_sent'         => $row['DATE_SENT'],
                'ref_no'            => $row[self::RFQ_REF_NO /*'RFQ_REF_NO'*/],
                'subject'           => $row[self::RFQ_SUBJECT /*'RFQ_SUBJECT'*/],
                'vessel'            => trim(strtoupper($row[self::RFQ_VESSEL /*'RFQ_VESSEL'*/])),
                'buyer_branch_id'   => (int) $row['RFQ_BUYER_ID'],

                'integrated'        => (bool) $row['INTEGRATED'],
                'priority'          => (bool) $row['PRIORITY'],
                'match'             => (bool) $row['TYPE']
            );

            $timeStartLoop = microtime(true);
            if (strlen($filters[self::FILTER_STATUS])) {
                $rfq = Shipserv_Rfq::getInstanceById($item['rfq_id']);
                $item['match'] = $rfq->isMatchRfq(true);
            }
            $elapsedAggregates['match'] += microtime(true) - $timeStartLoop;

            // calculate aggregate values per row if they haven't been already calculated by the query
            $timeStartLoop = microtime(true);
            if (array_key_exists(self::RFQ_CLOSED, $row)) {
                $eventClosed = (bool) $row[self::RFQ_CLOSED];
            } else {
                $eventClosed = self::getClosedStatus($row[Shipserv_Rfq_EventManager::COL_EVENT_HASH]);
            }
            $elapsedAggregates['closed'] += microtime(true) - $timeStartLoop;

            $item['status'] = ($eventClosed ? self::STATUS_CLOSED : self::STATUS_OPEN);

            $timeStartLoop = microtime(true);
            if (array_key_exists(self::RFQ_RESPONSES, $row)) {
                $item['responses'] = (int) $row[self::RFQ_RESPONSES];
            } else {
                $item['responses'] = self::getResponseCount($row[Shipserv_Rfq_EventManager::COL_EVENT_HASH]);
            }
            $elapsedAggregates['responses'] += microtime(true) - $timeStartLoop;

            $timeStartLoop = microtime(true);
            if (array_key_exists(self::RFQ_FORWARDS, $row)) {
                $item['forwards'] = (int) $row[self::RFQ_FORWARDS];
            } else {
                $item['forwards'] = self::getForwardCount($row[Shipserv_Rfq_EventManager::COL_EVENT_HASH]);
            }
            $elapsedAggregates['forwards'] += microtime(true) - $timeStartLoop;

            $data[] = $item;
        }

        $metaData = array(
            'total'            => (int) $total,
            'page_no'          => (int) $pageNo,
            'page_size'        => (int) $pageSize,
            'elapsed'          => array(
                'paginator'     => (float) $elapsedPaginator,
                'aggregates'    => $elapsedAggregates,
                'total'         => (float) (microtime(true) - $timeStart)
            )
        );

        foreach ($data as $index => $item) {
            $data[$index] = array_merge($item, $metaData);
        }

        $user = Shipserv_User::isLoggedIn();
        if ($user){
            $user->logActivity(Shipserv_User_Activity::BUY_TAB_RFQ_LIST, 'PAGES_USER', $user->userId, $user->email);
        }

        $this->_helper->json((array)$data);
    }

    /**
     * Returns JSON with RFQ and its line items properties
     *
     * @author  Yuriy Akopov
     * @date    2013-10-24
     */
    public function rfqDetailsAction() {
        $rfqId  = $this->_getParam(self::PARAM_RFQ);
        $hash  = $this->_getParam(self::PARAM_HASH);
        $validatorHash = Shipserv_Helper_Security::rfqSecurityHash($rfqId);
        
        if ($validatorHash !== $hash) {
            throw new Myshipserv_Exception_MessagedException("Page not found, please check your url and try again.", 404);
        }
        
        $helper = new Shipserv_Helper_Controller($this);
        $rfq = $helper->getRfqById($rfqId);
        $rfq = $rfq->resolveMatchForward();
        $rfq->loadRelatedData();

        $data = array(
            'rfqRefNo'          => $rfq->rfqRefNo,
            'rfqSubject'        => $rfq->rfqSubject,
            'rfqAccountRefNo'   => $rfq->rfqAccountRefNo,

            'rfqContact'            => $rfq->rfqContact,
            'rfqPhoneNo'            => $rfq->rfqPhoneNo,
            'rfqEmailAddress'       => $rfq->rfqEmailAddress,
            'rfqEmailAddressess'    => $rfq->rfqEmailAddressess,

            'rfqDateTime'               => $rfq->rfqDateTime,
            'rfqCreatedDate'            => $rfq->rfqCreatedDate,
            'rfqCreatedDateDisplayed'   => $rfq->rfqCreatedDate, //init as so, later it will be rfqCreatedDate - BYB_TIME_DIFFERENCE_FROM_GMT
            'rfqQuoteLabel'             => 'Quote before',      
            'rfqAdviceBeforeDate'       => $rfq->rfqAdviceBeforeDate,
            'rfqDeliveryPortName'       => $rfq->rfqDeliveryPortName,
            'rfqEstimatedDepartureTime' => (is_null($rfq->rfqEstimatedDepartureTime) ? null : $rfq->rfqEstimatedDepartureTime->format('Y-m-d')),
            'rfqEstimatedArrivalTime'   => (is_null($rfq->rfqEstimatedArrivalTime) ? null : $rfq->rfqEstimatedArrivalTime->format('Y-m-d')),

            'rfqVesselName'             => $rfq->rfqVesselName,
            'rfqImoNo'                  => $rfq->rfqImoNo,
            'rfqVesselClassification'   => $rfq->rfqVesselClassification,

            'rfqTermsOfDelivery'    => $rfq->rfqTermsOfDelivery,
            'rfqTransportationMode' => $rfq->rfqTransportationMode,

            'rfqTaxSts'         => $rfq->rfqTaxSts,
            'rfqTermsOfPayment' => $rfq->rfqTermsOfPayment,

            'rfqBillingAddress1'        => $rfq->rfqBillingAddress1,
            'rfqBillingAddress2'        => $rfq->rfqBillingAddress2,
            'rfqBillingCity'            => $rfq->rfqBillingCity,
            'rfqBillingPostalZipCode'   => $rfq->rfqBillingPostalZipCode,
            'rfqBillingStateProvince'   => $rfq->rfqBillingStateProvince,
            'rfqBillingCountry'         => $rfq->rfqBillingCountry,

            'rfqGeneralTermsConditions' => $rfq->rfqGeneralTermsConditions,
            'rfqPackagingInstructions'  => $rfq->rfqPackagingInstructions,
            'rfqSuggestedShipper'       => $rfq->rfqSuggestedShipper,
            'rfqCurrencyInstructions'   => $rfq->rfqCurrencyInstructions,

            'rfqComments' => $rfq->rfqComments,

            // data holders to be populated later
            'rfqBuyer'      => array(),
            'attachment'    => array(),
            'rfqLineItems'  => array(),

            'releaseTo' => array()
        );

        $address = $rfq->getAddress();
        if (!empty($address)) {
            $data['releaseTo'] = array(
                'line1'     => $address['PARTY_STREETADDRESS'],
                'line2'     => $address['PARTY_STREETADDRESS2'],
                'city'      => $address['PARTY_CITY'],
                'state'     => $address['PARTY_COUNTRY_SUB_ENTITY_ID'],
                'zip'       => $address['PARTY_POSTALCODE'],
                'country'   => $address['COUNTRY_NAME']
            );
        }

        // buyer branch details are only loaded for non-pages RFQs
        // the way we access buyer branch is different for integrated and non-integrated (enquiries) RFQs
        $buyerBranchData = null;
        if ($rfq->enquiry) {
            // non-integrated RFQ also known as Pages RFQ
            $data['rfqBuyer'] = array(
                'name'       => $rfq->enquiry->pinCompany,
                'rfqPhoneNo' => $rfq->rfqPhoneNo
            );

            try {
                $user = $rfq->enquiry->getSenderUser(); // Pages user who sent the RFQ

                $data['rfqBuyer']['byoContactName']  = $user->getDisplayName();
                $data['rfqBuyer']['byoContactEmail'] = $user->getEmail();
            } catch (Exception $e) {
                $data['rfqBuyer']['byoContactName']   = $rfq->rfqContact;
                $data['rfqBuyer']['byoContactEmail']  = $rfq->rfqEmailAddress;
            }

        } else {
            // integrated RFQ
            $buyerBranch = new Shipserv_Oracle_BuyerBranch();
            $buyerBranchRows = $buyerBranch->fetchBuyerBranchById($rfq->rfqBybBranchCode);
            $buyerBranchRow = $buyerBranchRows[0];

            $data['rfqBuyer'] = array(
                'name' => $buyerBranchRow['BYB_NAME'],

                'byoContactAddress1'    => $buyerBranchRow['BYB_ADDRESS_1'],
                'byoContactAddress2'    => $buyerBranchRow['BYB_ADDRESS_2'],
                'byoContactCity'        => $buyerBranchRow['BYB_CITY'],
                'byoContactState'       => $buyerBranchRow['BYB_STATE_PROVINCE'],
                'byoContactZip'         => $buyerBranchRow['BYB_ZIP_CODE'],
                'byoCountry'            => $buyerBranchRow['BYB_COUNTRY'],

                'byoContactName'        => $rfq->rfqContact,
                'byoContactPhone1'      => $rfq->rfqPhoneNo,
                'byoContactEmail'       => $rfq->rfqEmailAddress,
                'bybTimezone'           => null
            );
            
            //S16551 Deadline control 
            if ($buyerBranch->isRfqDeadlineAllowed($buyerBranchRow['BYB_BRANCH_CODE'])) {
                $data['rfqQuoteLabel'] = 'Quote Deadline';
                if ($rfq->rfqCreatedDate && $buyerBranchRow['BYB_TIME_DIFFERENCE_FROM_GMT']) {
                    $format = 'd M Y h:i:s a';
                    $date = DateTime::createFromFormat($format, $rfq->rfqCreatedDate);
                    $date = date_add($date, date_interval_create_from_date_string((Int) $buyerBranchRow['BYB_TIME_DIFFERENCE_FROM_GMT'] . ' hours'));
                    $data['rfqCreatedDateDisplayed'] = $date->format($format);
                    $data['rfqBuyer']['bybTimezone'] = $buyerBranchRow['BYB_TIMEZONE'];
                }
            }            
        }

        $attachments = array_merge($rfq->attachments->enquiry, $rfq->attachments->rfq);
        if (count($attachments)) {
            foreach ($attachments as $att) {
                $data['attachment'][] = array(
                    'url'       => $att['url'],
                    'filename'  => $att['filename_orig']
                );
            }
        }

        if (count($rfq->rfqLineItems)) {
            foreach ($rfq->rfqLineItems as $lineItemsSection) {
                $sectionData = array(
                    'name'                => $lineItemsSection['name'],
                    'sectionDescription'  => $lineItemsSection['sectionDescription'],
                    'sectionType'         => $lineItemsSection['sectionType'],

                    'lineItems' => array()
                );

                if (count($lineItemsSection['lineItems'])) {
                    foreach ($lineItemsSection['lineItems'] as $lineItem) {
                        $sectionData['lineItems'][] = array(
                            'rflLineItemNo'     => $lineItem['rflLineItemNo'],

                            'rflIdType'         => $lineItem['rflIdType'],
                            'rflIdCode'         => $lineItem['rflIdCode'],

                            'rflQuantity'       => $lineItem['rflQuantity'],

                            'rflUnitDesc'       => $lineItem['rflUnitDesc'],
                            'rflProductDesc'    => $lineItem['rflProductDesc'],

                            'rflComments'       => $lineItem['rflComments']
                        );
                    }
                }

                $data['rfqLineItems'][] = $sectionData;
            }
        }

        $this->_helper->json((array)$data);
    }

    /**
     * Helper function to calculate number of non-proxy suppliers received RFQs from the given event
     *
     * @param   string  $eventHash
     *
     * @return  int
     */
    public static function getForwardCount($eventHash) {
        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('rqr' => 'rfq_quote_relation'),
                new Zend_Db_Expr('COUNT(DISTINCT rqr.rqr_spb_branch_code)')
            )
            ->join(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                'rfq.' . Shipserv_Rfq::COL_ID . ' = rqr.rqr_rfq_internal_ref_no',
                array()
            )
            ->where('rfq.' . Shipserv_Rfq::COL_EVENT_HASH . ' = HEXTORAW(?)', $eventHash)
            ->where('rqr.rqr_spb_branch_code <> ?', Shipserv_Match_Settings::get(Shipserv_Match_Settings::SUPPLIER_PROXY_ID))
        ;

        return (int) $select->getAdapter()->fetchOne($select);
    }

    /**
     * Helper function to calculate number of quotes issued in response to RFQs in the given event
     *
     * @param   string  eventHash
     *
     * @return  int
     */
    public static function getResponseCount($eventHash) {
        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('qot' => Shipserv_Quote::TABLE_NAME),
                new Zend_Db_Expr('COUNT(DISTINCT qot.' . Shipserv_Quote::COL_SUPPLIER_ID . ')')
            )
            ->join(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                'rfq.' . Shipserv_Rfq::COL_ID . ' = qot.' . Shipserv_Quote::COL_RFQ_ID,
                array()
            )
            ->where('rfq.' . Shipserv_Rfq::COL_EVENT_HASH . ' = HEXTORAW(?)', $eventHash)
            ->where('qot.' . Shipserv_Quote::COL_STATUS . ' = ?', Shipserv_Quote::STATUS_SUBMITTED)
            ->where('qot.' . Shipserv_Quote::COL_TOTAL_COST . ' > 0')
        ;

        return (int) $select->getAdapter()->fetchOne($select);
    }

    /**
     * Helper function to check if the given event is closed (one of its RFQs has a resulted in an order)
     *
     * @param   string  $eventHash
     *
     * @return  bool
     */
    public static function getClosedStatus($eventHash) {
        $select = new Zend_Db_Select(Shipserv_Helper_Database::getDb());
        $select
            ->from(
                array('ord' => Shipserv_PurchaseOrder::TABLE_NAME),
                new Zend_Db_Expr('MAX(ord.' . Shipserv_PurchaseOrder::COL_ID . ')')
            )
            ->join(
                array('qot' => Shipserv_Quote::TABLE_NAME),
                'qot.' . Shipserv_Quote::COL_ID . ' = ord.' . Shipserv_PurchaseOrder::COL_QUOTE_ID,
                array()
            )
            ->join(
                array('rfq' => Shipserv_Rfq::TABLE_NAME),
                'rfq.' . Shipserv_Rfq::COL_ID . ' = qot.' . Shipserv_Quote::COL_RFQ_ID,
                array()
            )
            ->where('qot.qot_submitted_date >= rfq.' . Shipserv_Rfq::COL_DATE)
            ->where('rfq.' . Shipserv_Rfq::COL_EVENT_HASH . ' = HEXTORAW(?)', $eventHash)
            ->where('ord.' . Shipserv_PurchaseOrder::COL_STATUS . ' = ?', Shipserv_PurchaseOrder::STATUS_SUBMITTED)
        ;

        // print $select->assemble(); exit;

        return (bool) $select->getAdapter()->fetchOne($select);
    }
}