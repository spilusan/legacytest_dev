<?php

/**
 * A dedicated controller to host webservices related to general data source functionality
 * E.g. retrieving simple lists of our vessels or buyer branches
 *
 * @author  Yuriy Akopov
 * @date    2014-02-17
 */
class Buyer_DataController extends Myshipserv_Controller_Action
{
    /**
     * Returns JSON for category picker control
     * Copied from tools modules of the abandoned S5618-market-segmentation-tools branch
     *
     * @author  Yuriy Akopov
     * @date    2013-09-23
     * @story   S7903
     */
    public function categoriesAction() 
    {
    	// Removed because of the SIR implementation on 24 of Oct, 2014
    	//$this->abortIfNotLoggedIn();

        //Sending out headers for crossdomain call
        $matchUrl = Myshipserv_Config::getMatchUrl(true);
        $this->getResponse()
            ->setHeader('Access-Control-Allow-Origin', $matchUrl)
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->setHeader('Access-Control-Allow-Credentials', ' true');


        $adapter = new Shipserv_Oracle_Categories(Shipserv_Helper_Database::getDb());
        $data = $adapter->fetchNestedCategories();
        $this->_helper->json((array)$data);
    }

    /**
     * Returns JSON for brand picker control
     *
     * @author  Yuriy Akopov
     * @date    2013-09-25
     * @story   S7903
     */
    public function brandsAction() 
    {

    	// Removed because of the SIR implementation on 24 of Oct, 2014
        // $this->abortIfNotLoggedIn();
    	
        //Sending out headers for crossdomain call
        $matchUrl = Myshipserv_Config::getMatchUrl(true);
        $this->getResponse()
                   ->setHeader('Access-Control-Allow-Origin', $matchUrl)
                   ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
                   ->setHeader('Access-Control-Allow-Credentials', ' true');

        $adapter = new Shipserv_Oracle_Brands(Shipserv_Helper_Database::getDb());
        $data = $adapter->fetchAllBrands();
        $this->_helper->json((array)$data);
    }

    
    /**
     * Returns JSON with all the available countries (not grouped by continents)
     *
     * @author  Yuriy Akopov
     * @date    2013-11-26
     */
    public function locationsAction() 
    {
        $countries = new Shipserv_Oracle_Countries();
        $rows = $countries->fetchNonRestrictedCountries(false);

        $data = array();
        foreach ($rows as $row) {
            $data[] = array(
                'id'   => $row[Shipserv_Oracle_Countries::COL_CODE_COUNTRY],
                'name' => $row[Shipserv_Oracle_Countries::COL_NAME_COUNTRY]
            );
        }

        $this->_helper->json((array)$data);
    }

    /**
     * Ports action
     */
    public function portsAction() 
    {
        $matchUrl = Myshipserv_Config::getMatchUrl(true);
        $this->getResponse()
            ->setHeader('Access-Control-Allow-Origin', $matchUrl)
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->setHeader('Access-Control-Allow-Credentials', ' true');

        $adapter = new Shipserv_Oracle_Ports(Shipserv_Helper_Database::getDb());
        $data = $adapter->fetchAllNonRestrictedPortsGroupedByCountry();
        $this->_helper->json((array)$data);
    }

    /**
     * Match purchase action
     * @throws Exception
     */
	public function buyerMatchPurchaserAction() 
	{
    	$adapter = new Shipserv_Match_Report_AdoptionRate;
    	
    	if (!$this->params['startDate'] != '' && !$this->params['endDate'] != '') {
    		throw new Exception('Invalid parameter', 404);
    	}
    	
    	if (ctype_digit($this->params['buyerId']) === false) {
            if ($this->params['buyerId'] != null && preg_match('/^([0-9]+,?)+$/', $this->params['buyerId'])) {
                $buyerId = explode(",", $this->params['buyerId']);
            } else {
                throw new Exception('Invalid buyer', 404);
            } 
    	} else {
            $buyerId = $this->params['buyerId'];
        }
    	
    	$startDate = Shipserv_DateTime::fromString($this->params['startDate']);
    	$endDate = Shipserv_DateTime::fromString($this->params['endDate']);
    	 
    	$data = $adapter->getPurchaserForPeriod($buyerId, $startDate->format('d-M-Y'), $endDate->format('d-M-Y'), $this->params['vesselName']);
    	
    	$this->_helper->json((array)$data);

    }

    /**
     * Vessel action
     * @throws Exception
     */
    public function buyerMatchVesselAction() 
    {
    	$adapter = new Shipserv_Match_Report_AdoptionRate;
        $hasDate = false;
        $startDateParam = $this->params['startDate'] ?? '';
        $endDateParam = $this->params['endDate'] ?? '';
    	 
    	if (!$startDateParam != '' && !$endDateParam != '') {
            $hasDate = false;
    		//throw new Exception('Invalid parameter', 404);
    	} else {
            $hasDate = true;
            $startDate = Shipserv_DateTime::fromString($this->params['startDate']);
            $endDate = Shipserv_DateTime::fromString($this->params['endDate']);
        }
    	 
        //if (ctype_digit($this->params['buyerId']) === false )
        if (!preg_match('/^[0-9,]+$/', $this->params['buyerId'])) {
    		throw new Exception('Invalid buyer(s)', 404);
    	}

    	if ($hasDate === true) {
            $data = $adapter->getVesselForPeriod($this->params['buyerId'], $startDate->format('d-M-Y'), $endDate->format('d-M-Y'));
        } else {
            $data = $adapter->getVesselForBuyer($this->params['buyerId']);
        }
         
    	$this->_helper->json((array)$data);
    
    }

    
    /**
     * Returns JSON with buyer branches available to currently logged in user and their active buyer company
     * The original code was moved to Shipserv_Report_Buyer_Match_BuyerBranches by Attila O, as it is required to be called from Report Controller MatchNewAction too. (2015-09-22)
     * @author  Yuriy Akopov
     * @date    2013-12-04
     */
    public function buyerBranchesAction()
    {
        $this->_buyerBranches(Shipserv_User::BRANCH_FILTER_MATCH, false);
    }

     
    /**
     * Returns JSON with buyer branches available to currently logged in user and their active buyer company, filtered by buy
     *
     * @author  Attila Olbrich
     * @date    2015-07-13
     */
    public function buyerBranchesBuyAction()
    {
        $this->_buyerBranches(Shipserv_User::BRANCH_FILTER_BUY, true);
    }

    
    /**
     * _buyerBranches util function used by buyerBranchesBuyAction and buyerBranchesAction
     * 
     * @param String $filterType is expected to be Shipserv_User::BRANCH_FILTER_MATCH or Shipserv_User::BRANCH_FILTER_BUY
     * @param Bool $excludeIna  should exclude INActive branches?
     */
    private function _buyerBranches($filterType, $excludeIna)
    {
        // $user = $this->abortIfNotLoggedIn();
        $data  = Shipserv_Report_Buyer_Match_BuyerBranches::getInstance()->getBuyerBranches($filterType, $excludeIna);
        if ($this->_getParam('context') === 'rfq') {
            $data[] = array(
                'id'   => Myshipserv_Config::getProxyPagesBuyer(),
                'name' => 'Pages RFQs',
                'default' => 0
            );            
        } elseif ($this->_getParam('context') === 'quote') {
            $data[] = array(
                'id'   => Myshipserv_Config::getProxyPagesBuyer(),
                'name' => 'Pages Quotes',
                'default' => 0
            );
        }        
        $this->_helper->json((array)$data);        
    }
    
    
    /**
     * Returns JSON of all suppliers that have been trading with active buyer company organisation
     *
     * @author Elvir Leonard
     * @date 2013-01-27
     */
    public function supplierTradingPartnerAction() 
    {
        $adapter = new Shipserv_Oracle_BuyerOrganisations();
        $data = array();
        foreach ($adapter->getSupplierTraded($this->getUserBuyerOrg()->byoOrgCode) as $row) {
            $data[] = array("id" => $row['SPB_BRANCH_CODE'], "name" => $row['SPB_NAME']);
        }
        $this->_helper->json((array)$data);
    }

    /**
     * Returns JSON with vessels mentioned in RFQs returned by rfq-list method
     * Accepts same filters to form more precise list, if no parameters given, returns vessels for default list
     *
     * @author  Yuriy Akopov
     * @date    2013-10-24
     *
     * @throws Exception
     */
    public function vesselsAction() 
    {
	    $timeStart = microtime(true);

        $buyerOrg = $this->getUserBuyerOrg();
        $buyerIds = $buyerOrg->getBranchesTnid();

        if (empty($buyerIds)) {
            return $this->_helper->json((array)array());
        }

        $context = $this->_getParam('context');
        switch ($context) {
            case 'rfq-outbox':
                $buyerBranchId = $this->_getParam('buyerBranchId');
				if (strlen($buyerBranchId) === 0) {
					$buyerBranchId = null;
				}

				$dateFrom = new DateTime();
				$dateFrom->modify('-' . Shipserv_Rfq_EventManager::OUTBOX_DEPTH_DAYS . ' days');

				$vesselsObj = new Shipserv_Oracle_Vessel();
				$rows = $vesselsObj->getVesselsFromRfqsSelect($buyerOrg->id, $buyerBranchId, $dateFrom);

                break;

            case 'quote':
                $db = Shipserv_Helper_Database::getDb();
                $select = new Zend_Db_Select($db);
                $select
                    ->from(
                        array(
                            'rfq' => Shipserv_Rfq::TABLE_NAME
                        ),
                        array(
                            'NAME'         => new Zend_Db_Expr('UPPER(rfq.' . Shipserv_Rfq::COL_VESSEL_NAME . ')'),
                            'NAME_TRIMMED' => new Zend_Db_Expr('UPPER(TRIM(rfq.' . Shipserv_Rfq::COL_VESSEL_NAME . '))'),
                            'IMO'   => 'rfq.' . Shipserv_Rfq::COL_VESSEL_IMO,
                            'BUYER' => 'rfq.' . Shipserv_Rfq::COL_BUYER_ID   // no proxy IDs resolved so far
                        )
                    )
                    ->join(
                        array(
                            'qot' => Shipserv_Quote::TABLE_NAME
                        ),
                        implode(
                            ' AND ',
                            array(
                                'qot.' . Shipserv_Quote::COL_RFQ_ID . ' = rfq.' . Shipserv_Rfq::COL_ID,
                                $db->quoteInto('qot.' . Shipserv_Quote::COL_DATE . ' >= (SYSDATE - ?)', Shipserv_Quote_ListManager::INBOX_DEPTH_DAYS),
                                $db->quoteInto('rfq.' . Shipserv_Rfq::COL_DATE . ' >= (SYSDATE - ?)', Shipserv_Quote_ListManager::INBOX_DEPTH_DAYS),
                                $db->quoteInto('qot.' . Shipserv_Quote::COL_BUYER_ID . ' IN (?)', $buyerOrg->getBranchesTnid())
                            )
                        ),
                        array()
                    )
                    ->order('NAME_TRIMMED')
                    ->distinct();

	            $rows = $select->getAdapter()->fetchAll($select);

                break;

            case 'market-sizing':

                break;

            default:
                // general use case - return all buyer's vessels
                $db = Shipserv_Helper_Database::getSsreport2Db();
                $select = new Zend_Db_Select($db);
                $select
                    ->from(
                        array('v' => 'vessel'),
                        array(
                            'NAME'  => new Zend_Db_Expr('UPPER(v.VES_NAME)'),
                            'IMO'   => 'v.VES_IMO_NO',
                            'BUYER' => 'v.BYB_BRANCH_CODE'
                        )
                    )
                    ->where('v.byb_branch_code IN (?)', $buyerIds)
                    ->order('NAME');

	            $rows = $select->getAdapter()->fetchAll($select);

        }

	    $elapsed = microtime(true) - $timeStart;

        // group vessels by their name (might still have different buyers and / or IMOs which is often ignored)
        $data = array();
        foreach ($rows as $vesselRow) {
            $name = $vesselRow['NAME'];

            if (strlen($name) === 0) {
                // we're vessel name-centric, not IMO centric here. name without an IMO is allowed but not the other way around
                // this is because this method is mostly used for interface purposes, e.g. to populate a dropdown, so we need a name
                continue;
            }

            $imo   = $vesselRow['IMO'];
            $buyer = (int) $vesselRow['BUYER'];

            if (!array_key_exists($name, $data)) {
                $data[$name] = array(
                    'name'     => $name,
                    'imo'      => array($imo),
                    'buyer_id' => array($buyer),

                    'elapsed'  => $elapsed
                );
            } else {
                $data[$name]['imo'][] = $imo;
                $data[$name]['buyer_id'][] = $buyer;
            }
        }

        // dealing with potentially non-unique arrays collected on the previous step
        // something wrong from data design point of view, but as far as I remember, some front end depends on that
        // @todo: check the statement above
        foreach ($data as $name => $item) {
            foreach ($item as $key => $value) {
                if (is_array($value)) {
                    $data[$name][$key] = array_unique($value);

                    if (count($data[$name][$key]) === 1) {
                        $data[$name][$key] = $value[0];
                    }
                }
            }
        }

        return $this->_helper->json((array)array_values($data));
    }
    
    
    /**
     * Returns JSON for match usage
     * 
     *
     * @author  Elvir
     * @date    2014-06-03
     * @story   S10525
     * @usage	http://production.myshipserv.com/buyer/usage/rfq-list?buyerId=11035&startDate=03/06/2013&endDate=03/06/2014&start=0&limit=10
     */
    public function usageRfqListAction()
    {
    	$report = new Shipserv_Match_Report_Buyer($this->params['buyerId']);
    	
    	if (!empty($this->params['startDate'])) {
    		$stt = strtotime(str_replace("/", "-", $this->params['startDate']));
    		$report->setStartDate(date('d', $stt), date('M', $stt), date('Y', $stt));
    	}
    	if (!empty($this->params['endDate'])) {
    		$stt = strtotime(str_replace("/", "-", $this->params['endDate']));
    		$report->setEndDate(date('d', $stt), strtoupper(date('M', $stt)), date('Y', $stt));
    	}

        $page = ( ($this->params['start'] === null )?1:$this->params['start'] );
		$startRow = ($page>1)?(($page-1)*$this->params['limit']):1; 
		
		if ($this->params['csv'] == 1) {
			$data = $report->getRfqSentToMatch($this->params['vessel'], $this->params['purchaser']);
			$csv = array();
			$csv[] = array(
				'RFQ ref.'
				, 'RFQ Subject'
				, 'Vessel name'
				, 'Purchaser'
				, 'RFQ sent'
				, 'No. of buyer selected suppliers'
				, 'No. of match selected suppliers'
				, 'No. of quotes from buyer selected suppliers'
				, 'No. of quotes from match selected suppliers'
				, 'Po awarded'
				, 'Potential Savings [USD]'
				, 'Actual Savings [USD]'
			);
				
			foreach ($data as $row) {
				$ordStatus = "";
				if ($row['ORD_STATUS']=="ord_by_buyer") {
					$ordStatus = "Buyer Supplier";
				} else if ($row['ORD_STATUS']=="ord_by_match") {
					$ordStatus = "Match Supplier";
				}
				
				$csv[] = array(
					$row['RFQ_REF_NO']
					, $row['RFQ_SUBJECT']
					, $row['RFQ_VESSEL_NAME']
					, $row['PURCHASER_NAME']
					, $row['RFQ_SUBMITTED_DATE']
					, $row['TOTAL_BUYER_SUPPLIER']
					, $row['TOTAL_MATCH_SUPPLIER']
					, $row['TOTAL_QOT_BY_BYB']
					, $row['TOTAL_QOT_BY_MATCH']
					, $ordStatus			
					, $row['POTENTIAL_SAVINGS']
					, $row['ACTUAL_SAVINGS']
				);
			}
			
			
			
			// filename for download
			$filename = "website_data_" . date('Ymd') . ".xls";
			
			header("Content-Disposition: attachment; filename=\"$filename\"");
			header("Content-Type: application/vnd.ms-excel");
			
			foreach ($csv as $row) {
				array_walk($row, array($this, 'cleanData'));
				echo implode("\t", array_values($row)) . "\r\n";
			}
			exit;
				
			return $this->_helper->json((array)array('url' => 'x'));
		} else {
			$report->setStartRow($startRow);
			$report->setLimit($this->params['limit']);

			$data = $report->getRfqSentToMatch($this->params['vessel'], $this->params['purchaser']);
			return $this->_helper->json((array)array_values($data));
		}
    	
    }

    /**
     * Clean tabulator and new lines from text
     * @param string $str
     */
    protected function cleanData(&$str)
    {
    	$str = preg_replace("/\t/", "\\t", $str);
    	$str = preg_replace("/\r?\n/", "\\n", $str);
    	if (strstr($str, '"')) $str = '"' . str_replace('"', '""', $str) . '"';
    }
    
    
    /**
     * Returns JSON for to show how match forwards an RFQ
     *
     *
     * @author  Elvir
     * @date    2014-06-03
     * @story   S10525
     * @usage	http://production.myshipserv.com/buyer/usage/rfq-detail-list?buyerId=11035&rfqId=10371558&start=0&limit=10
     */
    public function usageRfqDetailListAction()
    {
    	$report = new Shipserv_Match_Report_Buyer_Rfq($this->params['rfqId'], $this->params['buyerId'], true);
    	$data = $report->getData();
    	
    	$new = array();
    	foreach ($data as $row) {
    		if ($row['QOT_INTERNAL_REF_NO'] != "") {

                //Get supplier with skipping normalisation check, for getting Country 
                $supplier = Shipserv_Supplier::getInstanceById($row['TNID'], "", true);
                $row['SPB_COUNTRY_NAME'] = $supplier->countryName;
                
    			$quote = Shipserv_Quote::getInstanceById($row['QOT_INTERNAL_REF_NO'], true);
                
                //If we have order, fetch its date
                if ($row['ORD_INTERNAL_REF_NO'] != null) {
                    $ord = Shipserv_Order::getInstanceById($row['ORD_INTERNAL_REF_NO'], true);
                    $row['ORD_SUBMITTED_DATE'] = $ord->ordSubmittedDate;
                } else {
                    $row['ORD_SUBMITTED_DATE'] = null;
                }

    			$row['QOT_TERMS_OF_DELIVERY'] = $quote->getReadableDeliveryTerms();
    			$row['QOT_TERMS_OF_PAYMENT']  = $quote->qotTermsOfPayment;
                $row['QOT_IS_GENUINE_SPARE']  = $quote->getGenuineInfo();

    		}
    		
    		$new[] = $row;
    	}
    	
    	$new[0]['SUMMARY'] = $report->getSummary();
    	return $this->_helper->json((array)array_values($new));
    }
        
    
    /**
     * This api is called from /profile/company-people when clicking on trading accounts
     * 
     * @todo: add check if the call is made by shipmate or admin of the company byo
     * @throws Myshipserv_Exception_MessagedException
     * @return json
     */
    public function userCompanyListAction() 
    {
        if (!($userId = $this->getRequest()->getParam('userId', false)) || !($byoOrgCode = $this->getRequest()->getParam('byoOrgCode'))) {
            throw new Myshipserv_Exception_MessagedException("You need to specify a user id and buyer organisation id");
        }
        
        $output = array();
        $user = Shipserv_User::getInstanceById($userId);
        $deadlineManager = (Bool) $user->userRow['PSU_RFQ_DEADLINE_MGR'];

        $allCompanies = Shipserv_Oracle_PagesUserCompany::getInstance()->fetchCompaniesForUser($user->userId, $byoOrgCode);
        foreach ($allCompanies as $company) {
            if ($company['PUC_COMPANY_TYPE'] === Myshipserv_UserCompany_Company::TYPE_BYB) {
                $output[] = array(
                    'id' => $company['PUC_COMPANY_ID'],
                    'orgId' => $company['BYB_BYO_ORG_CODE'],
                    'name' => $company['BYB_NAME'],
                    'status' => ($company['BYB_STS'] === Shipserv_Buyer_Branch::STATUS_INACTIVE? 'Inactive' : 'Active'),
                    'parentId' => $company['BYB_UNDER_CONTRACT'],
                    'selected' => (Bool) $company['PUC_IS_DEFAULT'],
                    'defaulted' => (Bool) $company['PUC_IS_DEFAULT'],
                    'txnMon' => (Bool) $company['PUC_TXNMON'],
                    'webReporter' => (Bool) $company['PUC_WEBREPORTER'],
                    'match' => (Bool) $company['PUC_MATCH'],
                    'buy' => (Bool) $company['PUC_BUY'],
                    'appSup' => (Bool) $company['PUC_APPROVED_SUPPLIER'], 
                    'txnMonAdm' => (Bool) $company['PUC_TXNMON_ADM'],
                    'autoReminder' => (Bool) $company['PUC_AUTOREMINDER'],
                    'userIsDeadlineManager' => $deadlineManager,
                    'branchIsDeadlineManager' => (Bool) $company['CCF_RFQ_DEADLINE_CONTROL'],
                    'show' => in_array($company['PUC_STATUS'], array(Myshipserv_UserCompany_Company::STATUS_INACTIVE, Myshipserv_UserCompany_Company::STATUS_DELETED))? false : true
                );  
            } elseif ($company['PUC_COMPANY_TYPE'] === Myshipserv_UserCompany_Company::TYPE_BYO && $byoOrgCode == $company['PUC_COMPANY_ID']) {
                $byoCompany = $company;
            } else {
                //Nothing to do for suppliers as they do not support trading accounts
                //Nothing to do for the byo different than the current tnid
            }
        }        

        // If no byb was found in PUC table, try to see if should be some (lazy population), 
        // and insert them directly even if we don't expect this case to happen anymore apart for some eventual legacy edge cases (S17550) 
        if (!count($output) && !is_null($byoCompany)) {
            // $dao = new Shipserv_Oracle_PagesUserCompany();
            $allBuyers = Shipserv_Buyer::getInstanceById($byoOrgCode)->getBranchesTnid(false); //list of all buyers
            $output = $this->convertUserCompanyList($allBuyers, $deadlineManager);
        } else {
            $allBuyers = Shipserv_Buyer::getInstanceById($byoOrgCode)->getBranchesTnid(false, true); //list of all buyers
            $mergeOutput = $this->convertUserCompanyList($allBuyers, $deadlineManager, false);
            $output = $this->mergeUserCompanyLists($mergeOutput, $output);
            $output = $this->resortUserCompanyListById($output);
        }

        $this->_helper->json((array)array_values($output));
    }


    /**
     * Create formatted output for JSON response
     *
     * @param array $allBuyers
     * @param bool $deadlineManager
     * @param bool $defaults
     * @return array
     */
    protected function convertUserCompanyList(array $allBuyers, $deadlineManager, $defaults = true)
    {
        $output = array();

        foreach ($allBuyers as $buyer) {
            $output[] = array(
                'id' => $buyer->bybBranchCode,
                'orgId' => $buyer->bybByoOrgCode,
                'name' => $buyer->bybName,
                'status' => ($buyer->bybSts === Shipserv_Buyer_Branch::STATUS_INACTIVE? 'Inactive' : 'Active'),
                'parentId' => $buyer->bybUnderContract,
                'selected' => false,
                'defaulted' => false,
                'txnMon' => $defaults,
                'webReporter' => $defaults,
                'match' => $defaults,
                'buy' => $defaults,
                'appSup' => $defaults,
                'txnMonAdm' => $defaults,
                'autoReminder' => $defaults,
                'userIsDeadlineManager' => $deadlineManager,
                'branchIsDeadlineManager' => false,
                'show' => true
            );
        }

        return $output;
    }


    /**
     * Merge the two arrays together in tow directions by matching keys
     *
     * @param array $array1
     * @param array $array2
     * @return array
     */
    protected function mergeUserCompanyLists(array $array1, array $array2)
    {
        $result = array();

        // merge two arrays together, array2 has privilege over array1
        foreach ($array1 as $item) {
            $foundLookupItem = null;
            foreach ($array2 as $lookupItem) {
                if ($lookupItem['id'] === $item['id']) {
                    $foundLookupItem = $lookupItem;
                }
            }

            if ($foundLookupItem) {
                $result[] = $foundLookupItem;
            } else {
                $result[] = $item;
            }
        }

        // make sure an element form array2 is not in result yet (not likely) then add it to the end of the array
        foreach ($array2 as $item) {
            $itemFound = false;
            foreach ($array1 as $lookupItem) {
                if ($lookupItem['id'] === $item['id']) {
                    $itemFound = true;
                }
            }

            if (!$itemFound) {
                //var_dump($item);
                $result[] = $item;
            }
        }

        return $result;
    }

    /**
     * Sorting array by id (buyer branch conde)
     *
     * @param array $array
     * @return array|bool
     */
    protected function resortUserCompanyListById(array $array)
    {

        /**
         * For sorting array with usort
         *
         * @param mixed $a
         * @param mixed $b
         *
         * @return int
         */
        function sortById($a, $b)
        {
            $a = (int)$a['id'];
            $b = (int)$b['id'];

            if ($a === $b) {
                return 0;
            }

            return ($a < $b) ? -1 : 1;
        }

        if (usort($array, 'sortById')) {
            return $array;
        }

        return false;
    }


    /**
     * Acttion getting the list of match Segments
     * @return string Json
     */
    public function buyerMatchSegmentsAction()
    {
    	$buyerBranches = $this->getRequest()->getParam('bybBranchCode', "0");
    	$segment = new Shipserv_Match_Buyer_Segment();
    	$this->_helper->json((array)$segment->getSegments($buyerBranches));
    }
    
    /**
     * Acttion getting the list of keywords according to segmentID
     * @return string Json
     */
    public function buyerMatchKeywordsAction()
    {
    	$segmentId = $this->getRequest()->getParam('segmentId', null);
    	$buyerBranches = $this->getRequest()->getParam('bybBranchCode', "0");
    	
    	if ($segmentId === null) {
    		throw new Myshipserv_Exception_MessagedException('Required "segmentId" parameter is missing', 500);
    	}
    	
    	$segment = new Shipserv_Match_Buyer_Segment();
    	$this->_helper->json((array)$segment->getKeywordsBySegment($segmentId, $buyerBranches));
    }

}
