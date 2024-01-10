<?php
use PHPMD\Rule\CleanCode\BooleanArgumentFlag;
/**
 * A dedicated controller to host match engine related buyer webservices
 *
 * @author  Yuriy Akopov
 * @date    2014-02-18
 */
class Buyer_MatchController extends Myshipserv_Controller_Action {
    
    //Generic constants
    const DATE_FORMAT = 'Y-m-d h:i:s a';
    
    const
        PARAM_RFQ       = 'rfqRefNo',
        PARAM_TERMS     = 'terms',
        PARAM_SUPPLIER  = 'supplierTnid',
        PARAM_NEW_ONLY  = 'newOnly'
    ;

    // hi-level keys for search terms JSON
    const
        TERM_TYPE_BRANDS        = 'brands',
        TERM_TYPE_CATEGORIES    = 'categories',
        TERM_TYPE_TAGS          = 'tags',
        TERM_TYPE_LOCATIONS     = 'locations'
    ;

    // keys for pagination and other meta information
    const
        PAGE_TOTAL       = 'total',
        PAGE_CUR_NO      = 'page_no',
        PAGE_SIZE        = 'page_size',
        TIME_ELAPSED     = 'time_elapsed'
    ;

    // keys for search results (matched suppliers)
    const
        RESULT_RFQ           = 'rfq_id',
        RESULT_HASH          = 'hash',
        RESULT_SUPPLIER      = 'tnid',
        RESULT_NAME          = 'name',
        RESULT_CITY          = 'city',
        RESULT_SCORE         = 'score',
        RESULT_LEVEL         = 'level',
        RESULT_TRADE_RANK    = 'trade_rank',
        RESULT_COMMENT       = 'comment',
        RESULT_URL           = 'url',
        RESULT_RESULT_ID     = 'result_id',
        RESULT_ERROR         = 'error',
        RESULT_MATCH_RANK    = 'match_rank',
        RESULT_RESPONSE_RATE = 'response_rate'
    ;

    // keys for RFQ supplier list
    const
        SUPPLIER_ID                 = 'tnid',
        SUPPLIER_NAME               = 'name',
        SUPPLIER_URL                = 'url',
        SUPPLIER_CITY               = 'city',
        SUPPLIER_FROM_MATCH         = 'from_match',
        SUPPLIER_RANK               = 'rank',
        SUPPLIER_LEVEL              = 'level',
        SUPPLIER_QUOTE_ID           = 'quote_id',
        SUPPLIER_QUOTE_DATE         = 'quote_date',
        SUPPLIER_QUOTE_DATE_DISPLAY = 'quote_date_display',
        SUPPLIER_QUOTE_PRICE        = 'quote_price',
        SUPPLIER_QUOTE_PRICE_HIDDEN = 'quote_price_hidden',
        SUPPLIER_QUOTE_CURRENCY     = 'quote_currency',
        SUPPLIER_QUOTE_PRICE_USD    = 'quote_price_usd',
        SUPPLIER_QUOTE_PRICE_FLOAT  = 'quote_price_float',
        SUPPLIER_QUOTE_SHARE        = 'quote_share',
        SUPPLIER_QUOTE_CHEAPEST     = 'quote_cheapest',
        SUPPLIER_QUOTE_EXCLUDE      = 'quote_exclude',
        SUPPLIER_RFQ_STATUS         = 'rfq_status',
        SUPPLIER_QUOTE_URL          = 'quote_url',
        SUPPLIER_ACTIVE             = 'active',
        SUPPLIER_RFQ_ID             = 'rfq_id'
    ;

    // keys for RFQ supplier transaction status
    const
        SUPPLIER_RFQ_STATUS_QUOTED      = 'quoted',
        SUPPLIER_RFQ_STATUS_DECLINED    = 'declined',
        SUPPLIER_RFQ_STATUS_ORDERED     = 'ordered',
        SUPPLIER_RFQ_STATUS_SUBMITTED   = 'submitted'
    ;

    // match score range identifiers
    const
        SCORE_RANGE_LOW     = 'L',
        SCORE_RANGE_MEDIUM  = 'M',
        SCORE_RANGE_HIGH    = 'H'
    ;
    // search terms keys made equals to ones used by the match engine to avoid re-mapping
    const
        TERM_ID     = Shipserv_Match_Match::TERM_ID,
        TERM_SCORE  = Shipserv_Match_Match::TERM_SCORE,
        TERM_TAG    = Shipserv_Match_Match::TERM_TAG,
        TERM_NAME   = Shipserv_Match_Match::TERM_NAME,
        TERM_LEVEL  = 'level'  // a new key for store range which isn't used in Match Engine itself
    ;


    /**
     * @author  Yuriy Akopov
     * @date    2013-08-23
     * @story   S7926, S7903
     *
     * @param   int         $rfqId
     * @param   array|null  $terms
     * @param   Shipserv_Helper_Stopwatch $t
     * @param   null        $elapsedInMatch
     *
     * @return  Shipserv_Match_Component_Search
     * @throws  Exception
     */
    protected function getSearchByRfqId($rfqId, array $terms = null, Shipserv_Helper_Stopwatch $t = null, &$elapsedInMatch = null) {
        if (is_null($t)) {
            $t = new Shipserv_Helper_Stopwatch();
        }

        // checking if the RFQ exists - if it doesn't, an exception will be thrown
        $helper = new Shipserv_Helper_Controller($this);
        $rfq = $helper->getRfqById($rfqId);

        $matchClient = new Shipserv_Match_Client();

        $t->click();
        $response = $matchClient->matchForRfqOutbox($rfqId, $terms);
        $elapsedInMatch = $response->getElapsedInMatch(); // time reported by the match app as opposed to time logged here
        $t->click('Requesting details from Match app');

        $t->click();
        $search = $response->getSearch();
        $t->click('Loading the search');

        return $search;
    }

    /**
     * Converts match engine score into a lo/me/hi range so users can be shown that instead of precise score
     *
     * @param   float   $score
     *
     * @return  string
     */
    protected function scoreToRange($score) {
        if ($score < Shipserv_Match_Settings::get(Shipserv_Match_Settings::TERM_SCORE_RANGE_MEDIUM_MIN)) {
            return self::SCORE_RANGE_LOW;
        } else if ($score > Shipserv_Match_Settings::get(Shipserv_Match_Settings::TERM_SCORE_RANGE_MEDIUM_MAX)) {
            return self::SCORE_RANGE_HIGH;
        }

        return self::SCORE_RANGE_MEDIUM;
    }

    /**
     * Converts front end score range into a match engine score value (so user can specify lo/me/hi range instead of precise score)
     *
     * @param   array  $info
     *
     * @return  array
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function parseUserScore(array $info) {
        if (array_key_exists(self::TERM_SCORE, $info)) {
            // if there is precise score specified, don't change anything
            return $info;
        }

        switch ($info[self::TERM_LEVEL]) {
            case self::SCORE_RANGE_LOW:
                $info[self::TERM_SCORE] = (float) Shipserv_Match_Settings::get(Shipserv_Match_Settings::TERM_SCORE_RANGE_LOW_DEFAULT);
                break;

            case self::SCORE_RANGE_MEDIUM:
                $info[self::TERM_SCORE] = (float) Shipserv_Match_Settings::get(Shipserv_Match_Settings::TERM_SCORE_RANGE_MEDIUM_DEFAULT);
                break;

            case self::SCORE_RANGE_HIGH:
                $info[self::TERM_SCORE] = (float) Shipserv_Match_Settings::get(Shipserv_Match_Settings::TERM_SCORE_RANGE_HIGH_DEFAULT);
                break;

            default:
                throw new Myshipserv_Exception_MessagedException('Unknown search term score range specified');
        }

        return $info;
    }

    /**
     * A helper function converting technical match comments into more human-friendly text
     *
     * @todo: this function is a crutch and is only needed until we store match information in connected tables properties instead of statically rendered text
     *
     * @param   string  $comment
     *
     * @return  string|null
     */
    protected function _makeReadableMatchComments($comment) {
        $matchInfo = array();
        $boostInfo = array();

        $rawBits = explode(";", $comment);

        foreach ($rawBits as $bit) {
            if (preg_match('/^Brand \{ID \d+, "(.*)"\} (.*)$/', $bit, $matches)) {
                $matchInfo[] = "brand \"" . $matches[1];

            } else if (preg_match('/^Category \{ID \d+, "(.*)"(\}|,.*)$/', $bit, $matches)) {
                $matchInfo[] = "category \"" . $matches[1] . "\"";

            } else if (preg_match('/^\{"(.*)"\} (.*)$/', $bit, $matches)) {
                $keywords = explode(' ', $matches[1]);
                if (count($keywords) >= 2) {
                    $matchInfo[] = "phrase \"" . $matches[1] . "\"";
                } else {
                    $matchInfo[] = "keyword \"" . $matches[1] . "\"";
                }

            } else if (preg_match('/^Supplier penalised by \{(.*)\}% for Category spam\.$/', $bit, $matches)) {
                $boostInfo[] = "penalty for category spam";

            } else if (preg_match('/^Supplier penalised by \{100 \* (.*)\}% for Chandlery\.$/', $bit, $matches)) {
                $boostInfo[] = "penalty for chandlery";

            } else if (preg_match('/^Supplier received (.*)% boost for special category and Premium Profile$/', $bit, $matches)) {
                $boostInfo[] = "boost for premium status";

            } else if (preg_match('/^Supplier matches (continent|country) .* \((Country|Continent) ratio\)$/', $bit, $matches)) {
                $boostInfo[] = "boost for " . $matches[1] . " match";

            } else if (preg_match('/^Supplier does not have any category in common with buyer chosen suppliers. Score reduced/', $bit, $matches)) {
                $boostInfo[] = 'penalty for no common categories';
            }
        }

        if (empty($matchInfo) and empty($boostInfo)) {
            return null;
        }

        $str = "";

        $displayedLength = 3;
        $truncated = false;
        if (count($matchInfo) > $displayedLength) {
            $matchInfo = array_slice($matchInfo, 0, $displayedLength);
            $truncated = true;
        }

        if (count($matchInfo)) {
            $str = "Recommended for " . implode(', ', $matchInfo) . ($truncated ? " and more" : "") . ".";
        }

        if (count($boostInfo)) {
            if (count($matchInfo)) {
                $str .= " Additional factors: ";
            } else {
                $str = "Match factors: ";
            }
            $str .= implode(", ", $boostInfo) . ".";
        }

        return $str;
    }

    /**
     * Returns status of the transaction for the provided RFQ (quoted/ordered/etc.
     * @todo: queries should be moved to models
     *
     * @param   Shipserv_Rfq        $rfq
     * @param   Shipserv_Supplier   $supplier
     *
     * @return  string
     */
    protected function getSupplierTransactionStatus(Shipserv_Rfq $rfq, Shipserv_Supplier $supplier) {
        // check if there is a priced quote
        $quote = $rfq->getQuote($supplier);
        if ($quote) {
            // check if the quote has an order
            $order = $quote->getOrder();
            if ($order) {
                return self::SUPPLIER_RFQ_STATUS_ORDERED;
            }

            return self::SUPPLIER_RFQ_STATUS_QUOTED;
        }

        // no priced quote for the RFQ
        $declineQuote = $rfq->getQuote($supplier, true);
        if ($declineQuote) {
            // zero price quote issued, indicates a decline
            return self::SUPPLIER_RFQ_STATUS_DECLINED;
        }

        $db = Shipserv_Helper_Database::getDb();
        $selectResponse = new Zend_Db_Select($db);
        $selectResponse
            ->from(
                array('rfp' => 'rfq_response'),
                'rfp.rfp_sts'
            )
            ->where('rfp.rfp_spb_branch_code = ?', $supplier->tnid)
            ->where('rfp.rfp_rfq_internal_ref_no = ?', $rfq->rfqInternalRefNo)
            ->where('rfp.rfp_sts = ?', 'DEC')
            ->order('rfp.rfp_created_date DESC')
            ->order('rfp.rfp_updated_date DESC')
        ;

        if ($db->fetchOne($selectResponse)) {
            // RFQ declined
            return self::SUPPLIER_RFQ_STATUS_DECLINED;
        }

        // not quoted yet and also not declined
        return self::SUPPLIER_RFQ_STATUS_SUBMITTED;
    }

    /**
     * Prepares a front end JSON structure for an RFQ supplier item
     *
     * @author  Yuriy Akopov
     * @date    2013-10-02
     *
     * @param   Shipserv_Supplier               $supplier
     * @param   Shipserv_Match_Component_Search $search
     * @param   Shipserv_Rfq|null               $supplierRfq
     * @param   Shipserv_Helper_Stopwatch       $t
     *
     * @return  array
     */
    protected function _renderSupplierResponse(Shipserv_Supplier $supplier, Shipserv_Match_Component_Search $search, Shipserv_Rfq $supplierRfq = null, Shipserv_Helper_Stopwatch $t = null) {
        if (is_null($t)) {
            $t = new Shipserv_Helper_Stopwatch();
        }

        // basic supplier properties
        $t->click();
        $data = array(
            self::SUPPLIER_ID     => (int) $supplier->tnid,
            self::SUPPLIER_NAME   => $supplier->name,
            self::SUPPLIER_CITY   => $supplier->city,
            self::SUPPLIER_URL    => $supplier->getUrl(),
            self::SUPPLIER_ACTIVE => (($supplier->tradeNetStatus === true) and ($supplier->directoryStatus === 'PUBLISHED'))
        );
        $t->click('Supplier basic properties');

        // if supplier is found in match results, match properties need to be added
        $t->click();
        $result = $search->getResultBySupplier(Shipserv_Match_Component_Result::FEED_TYPE_MATCHES, $supplier);
        if ($result) {
            $data[self::SUPPLIER_LEVEL]     = $this->scoreToRange($result->getScore());
            $data[self::RESULT_RESULT_ID]   = $result->getId();

            // also adding number of the supplier in the unfiltered list of results (i.e. in the list from which RFQ
            // suppliers haven't been removed
            $data[self::SUPPLIER_RANK] = $search->getResultNo(Shipserv_Match_Component_Result::FEED_TYPE_MATCHES, $result, false);
        }
        $t->click('Locating search result');

        // if there is no RFQ we cannot carry on with analysing supplier status properties
        if (is_null($supplierRfq)) {
            return $data;
        }

        $t->click();
        $data[self::SUPPLIER_RFQ_ID] = (int) $supplierRfq->rfqInternalRefNo;
        $data[self::SUPPLIER_RFQ_STATUS] = $this->getSupplierTransactionStatus($supplierRfq, $supplier);
        $quote = $supplierRfq->getQuote($supplier, false, true);
        $t->click('Checking transaction status');

        // add quote properties
        if ($quote) {
            $t->click();
            $data[self::SUPPLIER_QUOTE_ID] = (int) $quote->qotInternalRefNo;
            $data[self::SUPPLIER_QUOTE_URL]  = $quote->getUrl();
            $data[self::SUPPLIER_QUOTE_DATE] = ($quote->qotCreatedDate ? $quote->qotCreatedDate->format(self::DATE_FORMAT) : null);

            //S16128: If the buyer is Keppel, they price shold not be shown. This Sql function centralized such request /svn/shipserv_projects/sservdba/trunk/procs/pkg_rfq_deadline_control.sql 
            if ($supplierRfq->shouldHideQuotePrice()) {
                $data[self::SUPPLIER_QUOTE_PRICE]    = '';
                $data[self::SUPPLIER_QUOTE_CURRENCY] = '';
                $data[self::SUPPLIER_QUOTE_PRICE_USD] = '';
                $data[self::SUPPLIER_QUOTE_PRICE_HIDDEN] = 1;
            } else {
                $data[self::SUPPLIER_QUOTE_PRICE]    = $quote->getPriceTag();
                $data[self::SUPPLIER_QUOTE_CURRENCY] = $quote->qotCurrency;
                $data[self::SUPPLIER_QUOTE_PRICE_USD] = $quote->getPriceTag(Shipserv_Oracle_Currency::CUR_USD);
                $data[self::SUPPLIER_QUOTE_PRICE_HIDDEN] = 0;
            }
            
            $t->click('Quote basic properties');

            $t->click();
            // @todo: replace with Shipserv_Quote::getCompleteness()
            if ($supplierRfq->rfqLineItemCount > 0) {
                $quoteLineItems = $quote->getLineItem();
                $lineItemsQuoted = 0;
                foreach ($quoteLineItems as $lineItem) {
                    if ($lineItem['QLI_TOTAL_LINE_ITEM_COST'] > 0) {
                        $lineItemsQuoted++;
                    }
                }

                $data[self::SUPPLIER_QUOTE_SHARE] = round($lineItemsQuoted / $supplierRfq->rfqLineItemCount * 100, 2);
            } else {
                $data[self::SUPPLIER_QUOTE_SHARE] = null;
            }
            $t->click('Calculating quote share');

            $t->click();
            $data[self::SUPPLIER_QUOTE_EXCLUDE] = Shipserv_Quote_UserAction::isStatsExcluded($quote);
            $t->click('Checking if quote is excluded');

            // fields added by Yuriy Akopov on 2015-03-16, S12889
            /*
            $t->click();
            $lastOrderDate = $supplier->getLastOrderDate($quote->qotBybBranchCode);
            $data['quote_supplier_last_order'] = $lastOrderDate ? $lastOrderDate->format('Y-m-d H:i:s') : null;
            $t->click('Getting last order date');
            */

            $t->click();
            $supplierWhitelisted = false;
            $buyerOrg = $this->getUserBuyerOrg();
            $whitelist = new Shipserv_Buyer_SupplierList($buyerOrg);
            if ($whitelist->isEnabled(Shipserv_Buyer_SupplierList::TYPE_WHITELIST)) {
                if (in_array($supplier->tnid, $whitelist->getListedSuppliers(Shipserv_Buyer_SupplierList::TYPE_WHITELIST))) {
                    $supplierWhitelisted = true;
                }
            }
            $data['quote_supplier_whitelisted'] = $supplierWhitelisted;
            $t->click('Checking the whitelist status');

            $t->click();
            $data['quote_is_genuine'] = $quote->getGenuineInfo();;

            $data['quote_shipping_cost'] = is_null($quote->qotShippingCost) ? null : (float) $quote->qotShippingCost;
            $data['quote_payment_terms'] = $quote->qotTermsOfPayment;
            $data['quote_lead_time'] = $quote->qotDeliveryLeadTime;
            $data['quote_delivery_terms'] = $quote->getReadableDeliveryTerms();
            $t->click('Quote credibility basic properties');
        }

        return $data;
    }

    /**
     * Returns JSON with the paginated search results for the given RFQ
     *
     * @author  Yuriy Akopov
     * @date    2013-08-23
     * @story   S7926
     */
    public function resultsAction() {
        $elapsed = array(
            'total' => microtime(true)
        );

        $rfqId      = $this->_getParam(self::PARAM_RFQ);
        $termsJson  = $this->_getParam(self::PARAM_TERMS);

        if (strlen($termsJson)) {
            $terms = json_decode($termsJson, true);
            if (is_null($terms)) {
                throw new Myshipserv_Exception_MessagedException('Invalid JSON terms supplied, unable to parse');
            }

            if (!is_array($terms[self::TERM_TYPE_BRANDS])) {
                throw new Myshipserv_Exception_MessagedException('Terms validation error: brands information missing');
            } else {
                foreach ($terms[self::TERM_TYPE_BRANDS] as $key => $info) {
                    $terms[self::TERM_TYPE_BRANDS][$key] = $this->parseUserScore($info);
                }
            }

            if (!is_array($terms[self::TERM_TYPE_CATEGORIES])) {
                throw new Myshipserv_Exception_MessagedException('Terms validation error: categories information missing');
            } else {
                foreach ($terms[self::TERM_TYPE_CATEGORIES] as $key => $info) {
                    $terms[self::TERM_TYPE_CATEGORIES][$key] = $this->parseUserScore($info);
                }
            }

            if (!is_array($terms[self::TERM_TYPE_TAGS])) {
                throw new Myshipserv_Exception_MessagedException('Terms validation error: tags information missing');
            } else {
                foreach ($terms[self::TERM_TYPE_TAGS] as $key => $info) {
                    $terms[self::TERM_TYPE_TAGS][$key] = $this->parseUserScore($info);
                }
            }

            if (!is_array($terms[self::TERM_TYPE_LOCATIONS])) {
                throw new Myshipserv_Exception_MessagedException('Terms validation error: locations information missing');
            } else {
                foreach ($terms[self::TERM_TYPE_LOCATIONS] as $key => $info) {
                    $terms[self::TERM_TYPE_LOCATIONS][$key] = $this->parseUserScore($info);
                }
            }

            if (
                empty($terms[self::TERM_TYPE_BRANDS])
                and empty($terms[self::TERM_TYPE_CATEGORIES])
                and empty($terms[self::TERM_TYPE_TAGS])
            ) {
                throw new Myshipserv_Exception_MessagedException('Terms validation error: an empty set of settings supplied');
            }

        } else {
            $terms = null;
        }

        $elapsed['search'] = microtime(true);
        $search = $this->getSearchByRfqId($rfqId, $terms);
        $elapsed['search'] = microtime(true) - $elapsed['search'];

        $elapsed['page'] = microtime(true);
        $pageNo     = (int) $this->_getParam(self::PARAM_PAGE_NO, 1);
        $pageSize   = (int) $this->_getParam(self::PARAM_PAGE_SIZE, 10);
        $newOnly    = (bool) $this->_getParam(self::PARAM_NEW_ONLY, true);

        $results = $search->getResultsPage(Shipserv_Match_Component_Result::FEED_TYPE_MATCHES, true, $pageNo, $pageSize);
        $elapsed['page'] = microtime(true) - $elapsed['page'];

        // to make it easier to handle the data in Backbone.js at the front end, meta data is "denormalised" and
        // included into every model item rather than put into an envelope
        $metaData = array(
            self::PAGE_TOTAL    => (int) $search->getResultCount(Shipserv_Match_Component_Result::FEED_TYPE_MATCHES, $newOnly),
            self::PAGE_CUR_NO   => (int) $pageNo,
            self::PAGE_SIZE     => (int) $pageSize,
        );

        $elapsed['basicData'] = microtime(true);
        $data = array();
        $suppliers = array(); /** @var Shipserv_Supplier[] $suppliers */
        foreach ($results as $resNo => $res) {
            $supplier = $res->getSupplier();
            $score = $res->getScore();
            
            $data[] = array_merge($metaData, array(
                self::RESULT_RFQ        => (int) $rfqId,
                self::RESULT_HASH       => Shipserv_Helper_Security::rfqSecurityHash((int) $rfqId),
                self::RESULT_SUPPLIER   => (int) $supplier->tnid,
                self::RESULT_NAME       => $supplier->name,
                self::RESULT_CITY       => $supplier->city,
                self::RESULT_SCORE      => $score,
                self::RESULT_LEVEL      => $this->scoreToRange($score),
                self::RESULT_TRADE_RANK => $supplier->tradeRank,
                self::RESULT_COMMENT    => $this->_makeReadableMatchComments($res->getComment()),
                self::RESULT_URL        => $supplier->getUrl(),
                self::RESULT_RESULT_ID  => $res->getId(),
                self::RESULT_MATCH_RANK => ($pageNo * $pageSize) + ($resNo + 1),
                self::RESULT_MATCH_RANK => $search->getResultNo(Shipserv_Match_Component_Result::FEED_TYPE_MATCHES, $res, false)
            ));

            $suppliers[] = $supplier;
        }
        $elapsed['basicData'] = microtime(true) - $elapsed['basicData'];

        $elapsed['extendedData'] = microtime(true);
        foreach ($data as $index => $item) {
            $supplier = $suppliers[$index];

            $rfqsTotal = $supplier->getRfqCount(null);
            if ($rfqsTotal == 0) {
                $data[$index][self::RESULT_RESPONSE_RATE] = 'N/A';
                continue;
            }

            $quotesTotal   = $supplier->getQuoteCount(null, false);
            $declinesTotal = $supplier->getDeclineCount(null);

            $data[$index][self::RESULT_RESPONSE_RATE] = (float) (($quotesTotal + $declinesTotal) / $rfqsTotal);
        }
        $elapsed['extendedData'] = microtime(true) - $elapsed['extendedData'];

        $elapsed['total'] = microtime(true) - $elapsed['total'];
        foreach ($data as $index => $item) {
            $data[$index]['elapsed'] = $elapsed;
        }

        return $this->_helper->json((array)$data);
    }

    /**
     * Returns JSON with a list of suppliers RFQ has been sent to
     *
     * @author  Yuriy Akopov
     * @date    2013-10-02
     * @story   S8459
     */
    public function rfqSuppliersAction() {
        $tt = new Shipserv_Helper_Stopwatch();
        $elapsedInMatch = null;

        $rfqId = $this->_getParam(self::PARAM_RFQ);
        $search = $this->getSearchByRfqId($rfqId, null, $tt, $elapsedInMatch);

        $helper = new Shipserv_Helper_Controller($this);
        $rfq = $helper->getRfqById($rfqId);

        $buyerBranch = new Shipserv_Oracle_BuyerBranch();
        $isRfqDeadlineAllowed = $buyerBranch->isRfqDeadlineAllowed($rfq->rfqBybBranchCode);
        $buyerBranchRows = (array) $buyerBranch->fetchBuyerBranchById($rfq->rfqBybBranchCode);
        $buyerBranchRow = $buyerBranchRows[0];        
                
        $tt->click();
        $supplierInfo = $rfq->getSuppliers(true, true);
        $tt->click('Loading supplier list');

        $tt->click();

        $quotePrices = array();
        $data = array();
        if (count($supplierInfo)) {
            foreach ($supplierInfo as $index => $row) {
                $supplier = Shipserv_Supplier::getInstanceById($row[Shipserv_Rfq::RFQ_SUPPLIERS_BRANCH_ID], '', true);
                $supplierRfq = Shipserv_Rfq::getInstanceById($row[Shipserv_Rfq::RFQ_SUPPLIERS_RFQ_ID]);

                $t = new Shipserv_Helper_Stopwatch();
                $item = $this->_renderSupplierResponse($supplier, $search, $supplierRfq, $t);

                //S16551 Deadline control
                if ($isRfqDeadlineAllowed && $buyerBranchRow && isset($item[self::SUPPLIER_QUOTE_DATE]) && $item[self::SUPPLIER_QUOTE_DATE]) {
                    if ($item[self::SUPPLIER_QUOTE_DATE] && $buyerBranchRow['BYB_TIME_DIFFERENCE_FROM_GMT']) {
                        $date = DateTime::createFromFormat(self::DATE_FORMAT, $item[self::SUPPLIER_QUOTE_DATE]);
                        $date = date_add($date, date_interval_create_from_date_string((Int) $buyerBranchRow['BYB_TIME_DIFFERENCE_FROM_GMT'] . ' hours'));
                        $item[self::SUPPLIER_QUOTE_DATE_DISPLAY] = $date->format(self::DATE_FORMAT);
                        if ($buyerBranchRow['BYB_TIMEZONE']) {
                            $item[self::SUPPLIER_QUOTE_DATE_DISPLAY] .= ' (' . $buyerBranchRow['BYB_TIMEZONE'] . ')';
                        }
                    }
                }
                
                $item['elapsed_per_supplier'] = $t->getLoops();

                $item[self::SUPPLIER_FROM_MATCH] = (bool) $row[Shipserv_Rfq::RFQ_SUPPLIERS_FROM_MATCH];

                if ($item[self::SUPPLIER_QUOTE_PRICE_USD]) {
                    $priceKey = (string) $item[self::SUPPLIER_QUOTE_PRICE_USD];

                    if (array_key_exists($priceKey, $quotePrices)) {
                        $quotePrices[$priceKey] = array($index);
                    } else {
                        $quotePrices[$priceKey][] = $index;
                    }
                }

                $data[] = $item;
            }
        }

        $tt->click('Expanding suppliers');

        $tt->click();

        // indicate the cheapest quote if every supplier quoted back
        if (count($quotePrices) > 1) {
            $cheapestPrice = min(array_keys($quotePrices));

            foreach ($quotePrices[$cheapestPrice] as $index) {
                $data[$index][self::SUPPLIER_QUOTE_CHEAPEST] = true;
            }
        }

        // sort suppliers by their match rank
        usort($data, function($a, $b) {
            if ($a['rank'] == 0) {
                if ($b['rank'] == 0) {
                    if ($a['rfq_id'] == $b['rfq_id']) {
                        return 0;
                    } else {
                        return ($a['rfq_id'] < $b['rfq_id']) ? -1 : 1;
                    }
                } else {
                    return 1;
                }
            } else {
                if ($b['rank'] == 0) {
                    return -1;
                } else {
                    if ($a['rank'] == $b['rank']) {
                        return 0;
                    } else {
                        return ($a['rank'] < $b['rank']) ? -1 : 1;
                    }
                }
            }
        });

        $tt->click('Sorting quote prices');

        foreach ($data as &$item) {
            $item['elapsed_total'] = array(
                'Pages' => $tt->getLoops(),
                'Match' => $elapsedInMatch
            );
        }
        unset($item);

        return $this->_helper->json((array)$data);
    }

    /**
     * Sends a given RFQ to a given supplier
     * Returns supplier data to be added to the list of RFQ outbox recipient suppliers,
     * in the same format sd rfqSuppliersAction() does
     *
     * @author  Yuriy Akopov
     * @date    2013-10-02
     * @story   S8492
     */
    public function rfqSendAction() {
        $user = $this->abortIfNotLoggedIn();
        $buyerOrg = $this->getUserBuyerOrg();
        $helper = new Shipserv_Helper_Controller($this);

        $rfqId = $this->_getParam(self::PARAM_RFQ);
        $rfq = $helper->getRfqById($rfqId);

        $supplierIds = $this->_getParam(self::PARAM_SUPPLIER);
        if (!is_array($supplierIds)) {
            $supplierIds = array($supplierIds);
        }

        // check if the suppliers belong to RFQ search results
        $search = $this->getSearchByRfqId($rfqId);

        // finally, forward the RFQ

        // $forwarder = new Shipserv_Match_Forwarder();
        // $forwardedIds = $forwarder->forwardRfqEvent($buyerOrg, $user, $rfq, $supplierIds);
        $client = new Shipserv_Match_Client();
        $forwardedIds = $client->forward($rfq, $supplierIds);
        
        // prepare the JSON response for the front end to update its lists
        $data = array();
        foreach ($forwardedIds as $supplierId => $forwardedRfqId) {
            $supplier = Shipserv_Supplier::getInstanceById($supplierId, '', true);

            if ($forwardedRfqId !== false) {
                $item = $this->_renderSupplierResponse($supplier, $search);
            } else {
                $item = array(
                    self::RESULT_SUPPLIER   => $supplierId,
                    self::RESULT_NAME       => $supplier->name,
                    self::RESULT_ERROR      => 'Failed to forward the RFQ'
                );
            }

            $item[self::SUPPLIER_FROM_MATCH] = true;    // since we have just sent it through match
            $data[] = $item;
        }

        return $this->_helper->json((array)$data);
    }

    /**
     * Returns JSON with search terms for the given RFQ from the most recent search stored for that RFQ
     * If the search doesn't exist (wasn't stored yet), RFQ is parsed into terms, search is run and stored - then those
     * stored terms are returned
     *
     * @author  Yuriy Akopov
     * @date    2013-08-23
     * @story   7926
     */
    public function termsAction() {
        $rfqId = $this->_getParam(self::PARAM_RFQ);
        $search = $this->getSearchByRfqId($rfqId);

        $data = array();

        $data[self::TERM_TYPE_BRANDS] = array();
        $brands = $search->getBrands();
        if (!empty($brands)) {
            foreach ($brands as $brand) {
                $info = $brand->toMatchEngine();
                $data[self::TERM_TYPE_BRANDS][] = array(
                    self::TERM_ID       => $info[Shipserv_Match_Match::TERM_ID],
                    self::TERM_NAME     => $info[Shipserv_Match_Match::TERM_NAME],
                    self::TERM_SCORE    => $info[Shipserv_Match_Match::TERM_SCORE],
                    self::TERM_LEVEL    => $this->scoreToRange($info[Shipserv_Match_Match::TERM_SCORE])
                );
            }
        }

        $data[self::TERM_TYPE_CATEGORIES] = array();
        $categories = $search->getCategories();
        if (!empty($categories)) {
            foreach ($categories as $cat) {
                $info = $cat->toMatchEngine();
                $data[self::TERM_TYPE_CATEGORIES][] = array(
                    self::TERM_ID       => $info[Shipserv_Match_Match::TERM_ID],
                    self::TERM_NAME     => $info[Shipserv_Match_Match::TERM_NAME],
                    self::TERM_SCORE    => $info[Shipserv_Match_Match::TERM_SCORE],
                    self::TERM_LEVEL    => $this->scoreToRange($info[Shipserv_Match_Match::TERM_SCORE])
                );
            }
        }

        $data[self::TERM_TYPE_TAGS] = array();
        $tags = $search->getTags();
        if (!empty($tags)) {
            foreach ($tags as $tag) {
                $info = $tag->toMatchEngine();
                $data[self::TERM_TYPE_TAGS][] = array(
                    self::TERM_TAG      => $info[Shipserv_Match_Match::TERM_TAG],
                    self::TERM_NAME     => $info[Shipserv_Match_Match::TERM_TAG],
                    self::TERM_SCORE    => $info[Shipserv_Match_Match::TERM_SCORE],
                    self::TERM_LEVEL    => $this->scoreToRange($info[Shipserv_Match_Match::TERM_SCORE])
                );
            }
        }

        $data[self::TERM_TYPE_LOCATIONS] = array();
        $locations = $search->getLocations();
        if (!empty($locations)) {
            foreach ($locations as $loc) {
                $info = $loc->toMatchEngine();
                $data[self::TERM_TYPE_LOCATIONS][] = array(
                    self::TERM_ID       => $info[Shipserv_Match_Match::TERM_ID],
                    self::TERM_NAME     => $info[Shipserv_Match_Match::TERM_NAME],
                    self::TERM_SCORE    => $info[Shipserv_Match_Match::TERM_SCORE],
                    self::TERM_LEVEL    => $this->scoreToRange($info[Shipserv_Match_Match::TERM_SCORE])
                );
            }
        }

        return $this->_helper->json((array)$data);
    }

    /**
     * Helper function for match settings webservices initialising settings object from user session and parameters
     * DE6884 Modified by Attila O adding BYO as a parameter
     *
     * @param integer        $buyerBranch Buyer Branch ID
     * @param Shipserv_Buyer $buyerOrg    Shipserv Buyer Org or null for automaticly assign 
     *
     * @return Shipserv_Match_Buyer_Settings
     */
    protected function _getSettingsObj($buyerBranch = null, $buyerOrg = null) {
        $buyerOrg = ($buyerOrg === null) ? $this->getUserBuyerOrg() : $buyerOrg;
        $buyerBranchId = ($buyerBranch === null) ? $this->_getNonEmptyParam('buyerBranchId') : (int)$buyerBranch;
        // $buyerBranchId = $this->_getNonEmptyParam('buyerBranchId'); 
        $settingsObj = new Shipserv_Match_Buyer_Settings($buyerOrg, $buyerBranchId);

        return $settingsObj;
    }

    /**
     * Erases (resets to default, effectively) buyer match settings
     *
     * @author  Yuriy Akopov
     * @date    2014-07-02
     * @story   S10313
     */
    public function deleteSettingsAction() {
        $settingsObj = $this->_getSettingsObj();
        $resetAllBranches = $this->_getNonEmptyParam('allBranches', false);

        try {
            $settingsObj->eraseSettings($resetAllBranches);

        } catch (Shipserv_Match_Buyer_Settings_Exception $e) {
            // special handler here because there might be an expected exception when param combination is invalid
            throw new Myshipserv_Exception_MessagedException($e->getMessage());
        }

        return $this->getSettingsAction();
    }

    /**
     * Updates buyer match settings. Expects to recive the same JSON as is returned by getSettingsAction
     *
     * @author  Yuriy Akopov (Modified by Attila O, adding parameter showDetails, then it can update a list of branches in one call)
     * @date    2014-07-02
     * @story   S10313
     */
    public function updateSettingsAction() {

        $user = Shipserv_User::isLOggedIn();
        $settingsObj = $this->_getSettingsObj();
        $updateAllBranches = $this->_getNonEmptyParam('allBranches', false);
        $showDetails = $this->_getNonEmptyParam('showDetails');

        $settingsJson = json_decode($this->_getParam('settings'));
        $userSettings = ($showDetails) ? $settingsJson->default : $settingsJson;

        if (is_null($userSettings)) {
            throw new Myshipserv_Exception_MessagedException("Impossible to update buyer match settings, no settings provided");
        }

        // convert JSON structure into a DB row
        $dbSettings = array(
            Shipserv_Match_Buyer_Settings::COL_MAX_SUPPLIERS    => $userSettings->maxMatchSuppliers,
            Shipserv_Match_Buyer_Settings::COL_AUTOMATCH        => $userSettings->autoMatch->participant,
            Shipserv_Match_Buyer_Settings::COL_AUTOMATCH_CHEAP  => $userSettings->autoMatch->cheapQuotesOnly,
            Shipserv_Match_Buyer_Settings::COL_HIDE_CONTACTS    => $userSettings->hideContactDetails
        );

        try {
            $settingsObj->updateSettings($dbSettings, $updateAllBranches);

        } catch (Shipserv_Match_Buyer_Settings_Exception $e) {
            // special handler here because there might be an expected exception when param combination is invalid
            throw new Myshipserv_Exception_MessagedException($e->getMessage());
        }

        if ($showDetails) {
            foreach ($settingsJson->data as $settingItem) {
                $shipserBuyerBranch = Shipserv_Buyer_Branch::getInstanceById($settingItem->branchId);
                $buyerOrg = Shipserv_Buyer::getInstanceById($shipserBuyerBranch->orgId, true);
                $settingsObj = $this->_getSettingsObj($settingItem->branchId, $buyerOrg);

                // convert JSON structure into a DB row
                $dbSettings = array(
                    Shipserv_Match_Buyer_Settings::COL_MAX_SUPPLIERS    => $settingItem->data->maxMatchSuppliers,
                    Shipserv_Match_Buyer_Settings::COL_AUTOMATCH        => $settingItem->data->autoMatch->participant,
                    Shipserv_Match_Buyer_Settings::COL_AUTOMATCH_CHEAP  => $settingItem->data->autoMatch->cheapQuotesOnly,
                    Shipserv_Match_Buyer_Settings::COL_HIDE_CONTACTS    => $settingItem->data->hideContactDetails
                );

                try {
                    $settingsObj->updateSettings($dbSettings, false);

                    //Store event
                    if ($user) {
                        if ($settingItem->data->autoMatch->participant === true) {
                             $user->logActivity(Shipserv_User_Activity::SPEND_BENCHMARK_ACTIVATED, 'PAGES_USER', $user->userId, $settingItem->branchId);
                        } else {
                             $user->logActivity(Shipserv_User_Activity::SPEND_BENCHMARK_DEACTIVATED, 'PAGES_USER', $user->userId, $settingItem->branchId);
                        }
                    }
                } catch (Shipserv_Match_Buyer_Settings_Exception $e) {
                    // special handler here because there might be an expected exception when param combination is invalid
                    throw new Myshipserv_Exception_MessagedException($e->getMessage());
                }

            }
        }

        return $this->getSettingsAction();
    }

    /**
     * Returns buyer match settings
     *
     * @author  Yuriy Akopov (Modified by Attila O, if use parameter showDetails, it will list also all related buyer branches belonging to this buyer org)
     * @date    2014-07-02
     * @story   S10313
     */
    public function getSettingsAction() {

        $settingsObj = $this->_getSettingsObj();
        $fallbackToOrg = $this->_getNonEmptyParam('fallbackToOrg', true);
        $showDetails = $this->_getNonEmptyParam('showDetails');

        $settings = $settingsObj->getSettings($fallbackToOrg, true);

        $data = array(
            'maxMatchSuppliers' => is_null($settings[Shipserv_Match_Buyer_Settings::COL_MAX_SUPPLIERS]) ? null : (int) $settings[Shipserv_Match_Buyer_Settings::COL_MAX_SUPPLIERS],
            'autoMatch' => array(
                'participant'     => (bool) $settings[Shipserv_Match_Buyer_Settings::COL_AUTOMATCH],
                'cheapQuotesOnly' => (bool) $settings[Shipserv_Match_Buyer_Settings::COL_AUTOMATCH_CHEAP]
            ),
            'hideContactDetails' => (bool) $settings[Shipserv_Match_Buyer_Settings::COL_HIDE_CONTACTS]
        );

        if (!is_null($data['maxMatchSuppliers'])) {
            $data['maxMatchSuppliers'] = (int) $data['maxMatchSuppliers'];
        }
        if ($showDetails) {

            $result = array(
                        'default' => $data,
                        'data' => array()
                    );
            //get for multiple buyer branches
            $buyerOrg = Shipserv_Buyer::getInstanceById($this->getUserBuyerOrg()->id);
            $branchIds = $buyerOrg->getBranchesTnid(true, true);
            foreach ($branchIds as $buyerBranchId) {
                $buyer = Shipserv_Buyer::getBuyerBranchInstanceById($buyerBranchId);
                $settingsObj = $this->_getSettingsObj($buyerBranchId);
                $settings = $settingsObj->getSettings(true, true, $buyer->bybByoOrgCode);

                $dataItem= array(
                    'maxMatchSuppliers' => is_null($settings[Shipserv_Match_Buyer_Settings::COL_MAX_SUPPLIERS]) ? null : (int) $settings[Shipserv_Match_Buyer_Settings::COL_MAX_SUPPLIERS],
                    'autoMatch' => array(
                        'participant'     => (bool) $settings[Shipserv_Match_Buyer_Settings::COL_AUTOMATCH],
                        'cheapQuotesOnly' => (bool) $settings[Shipserv_Match_Buyer_Settings::COL_AUTOMATCH_CHEAP]
                    ),
                    'hideContactDetails' => (bool) $settings[Shipserv_Match_Buyer_Settings::COL_HIDE_CONTACTS]
                );

                if (!is_null($dataItem['maxMatchSuppliers'])) {
                    $dataItem['maxMatchSuppliers'] = (int) $dataItem['maxMatchSuppliers'];
                }

                array_push($result['data'], array(
                         'branchId' => $buyerBranchId
                        ,'name' => $buyer->bybName
                        ,'data' => $dataItem
                    ));
                }
        } else {
            $result = $data;
        }
     
        return $this->_helper->json((array)$result);
    }


    /**
     * Sends a reminder email to the supplier to quote
     *
     * @author  Yuriy Akopov
     * @date    2014-10-14
     * @story   S11021
     */
    public function remindAction() {
        $rfqId      = $this->_getParam(self::PARAM_RFQ);
        $supplierId = $this->_getParam(self::PARAM_SUPPLIER);

        $helper = new Shipserv_Helper_Controller($this);
        $rfq      = $helper->getRfqById($rfqId);
        $supplier = $helper->getSupplierById($supplierId, true);

        $transaction = new Application_Model_Transaction();
        $transaction->resendEmail($rfq->rfqInternalRefNo, $supplier->tnid, 'RFQ_SUB', true);

        return $this->_helper->json((array)array('result' => 'ok'));
    }
}