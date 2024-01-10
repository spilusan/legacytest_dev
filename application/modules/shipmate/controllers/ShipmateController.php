<?php
/**
 * A controller for shipmate tools
 *
 * @author  Gly
 * @date    2014-05-29
 * @story   S6152
 */
class Shipmate_ShipmateController extends Myshipserv_Controller_Action
{
	public function init()
	{
		parent::init();
		parent::preDispatch();
		Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		$this->abortIfNotShipMate();
    }

    public function indexAction()
    {
    	$this->view->customPage = $this->getRequest()->getParam('customPage', false);
     }

    public function targetSegmentsAction() 
    {

    }

    public function designGuidelinesAction()
    {

    }

    public function wpAllSectionsAction()
    {
     
    }

    public function wpAllComponentsAction()
    {
     
    }

    public function wpRegistrationAction()
    {
     
    }

    public function wpRegistrationTwoAction()
    {
     
    }


    /**
    * Action for Cactus Supplier Performance Report
    */

    public function supplierPerformanceReportAction()
    {
     
    }

    /**
    * Action for Buyer Usage Dashboard
    */
    public function buyerUsageDashboardAction() 
    {

    }

    /**
    * Action for Buyer Usage Dashboard Drilldown
    */
    public function buyerUsageDashboardDrilldownAction() 
    {
        $byo = (array_key_exists('byo', $this->params)) ? (int)$this->params['byo'] : 0;
        $params = array(
                'type' => (array_key_exists('type', $this->params)) ? $this->params['type'] : '',
                'name' => (array_key_exists('name', $this->params)) ? $this->params['name'] : '',
                'range' => (array_key_exists('range', $this->params)) ? $this->params['range'] : '',
                'timezone' => (array_key_exists('timezone', $this->params)) ? $this->params['timezone'] : '',
                'excludeSM' => (array_key_exists('excludeSM', $this->params)) ? $this->params['excludeSM'] : '',
                'reportType' => (array_key_exists('reportType', $this->params)) ? $this->params['reportType'] : null
            );
        
        //Setting report title
        switch ($params['type']) {
            case 'verifiedUserAccounts':
                $this->view->title = 'Verified User Accounts';
                break;
            case 'nonVerifiedUserAccounts':
                $this->view->title = 'Non-Verified Active User Accounts';
                break;
            case 'succSignIns':
                $this->view->title = 'Successful Sign Ins';
                break;
            case 'failedSignIns':
                $this->view->title = 'Failed Sign Ins';
                break;
            case 'userActivity':
                $activity = new Shipserv_User_Activity();
                $this->view->title = $activity->getEventGroupCategoryName($params['reportType']);
                break;
            case 'searchEvents':
                $this->view->title = 'Search Events';
                break;
            case 'spbImpressions':
                $this->view->title = 'Supplier Search Impressions';
                break;
            case 'contactRequests':
                $this->view->title = 'Contact Requests';
                break;
            case 'pagesRfqs':
                $this->view->title = 'Pages Rfqs';
                break;
            case 'activeTradingAccounts':
             	$this->view->title = 'Active Trading Accounts';
               	break;
            case 'rfqOrdWoImo':
            	$this->view->title = 'Active Trading Accounts';
               	break;
            default:
                $this->view->title = 'RFQs or POs where IMO number field is blank';
                break;
        }

        $this->view->byo = Shipserv_Buyer::getInstanceById($byo, true);
        $this->view->params = $params;
    }

    /**
    * Action for Supplier Usage Dashboard
    */
    public function supplierUsageDashboardAction() 
    {

    }

    /**
     * Action for Buyer Usage Dashboard Drilldown
     */
    public function supplierUsageDashboardDrilldownAction()
    {
    	$spb = (array_key_exists('spb', $this->params)) ? (int)$this->params['spb'] : 0;
    	$params = array(
    			'type' => (array_key_exists('type', $this->params)) ? $this->params['type'] : '',
    			'name' => (array_key_exists('name', $this->params)) ? $this->params['name'] : '',
    			'range' => (array_key_exists('range', $this->params)) ? $this->params['range'] : '',
    			'timezone' => (array_key_exists('timezone', $this->params)) ? $this->params['timezone'] : '',
    			'excludeSM' => (array_key_exists('excludeSM', $this->params)) ? $this->params['excludeSM'] : '',
    			'reportType' => (array_key_exists('reportType', $this->params)) ? $this->params['reportType'] : null
    	);
    
    	//Setting report title
    	switch ($params['type']) {
    	
    		case 'userActivity':
    			$activity = new Shipserv_User_Activity();
    			$this->view->title = $activity->getEventGroupCategoryName($params['reportType']);
    			break;
    		
    		default:
    			$this->view->title = '';
    			break;
    	}
    	//TODO continue it from here
    	$this->view->spb = Shipserv_Supplier::getInstanceById($spb, "", true, false);
    	$this->view->params = $params;
    }
    
	/**
     * Value Events upload tool used to correct Value Events in SalesForce
     *
     * Reworked by Yuriy Akopov on 2016-08-05, S17177
     *
     * @throws Myshipserv_Exception_MessagedException
     */
    public function valueEventAction()
    {
    	if ($this->user->canPerform('PSG_ACCESS_SALESFORCE') === false) {
    		throw new Myshipserv_Exception_MessagedException('You are not allowed to access this page as specified within your group: ' . $this->user->getGroupName(), 403);
    	}

	    $legacyFormat = ($this->_getParam('data_format') === 'legacy');
	    $this->view->legacyFormat = $legacyFormat;

	    if ($this->params['upload'] == '1') {
		    // STEP 3: upload user specified value events to SalesForce
		    $application = new Myshipserv_Salesforce_Report_Billing_Supplier();

		    try {
			    $deletedErrors = $application->deleteExistingValueEvents($this->params['valueEventId']);

		    } catch (Exception $e) {
			    throw new Myshipserv_Exception_MessagedException(
				    "Failed to remove existing value events from SalesForce."
			    );
		    }

		    if (empty($deletedErrors)) {
			    try {
				    $result = $application->uploadToSF($this->params['csvContent']);
				    $this->view->jobId = (string) $result['jobId'][0];

			    } catch (Exception $e) {
				    throw new Myshipserv_Exception_MessagedException(
					    "Failed to upload new value events to SalesForce."
				    );
			    }
		    }

		    $this->view->deletedErrors = $deletedErrors;

	    } else if (strlen($this->params['sf_account_id'])) {
		    // STEP 2: retrieve supplier's data and value events from SalesForce
		    $application = new Myshipserv_Salesforce_Report_Billing_Supplier();

		    $application->setPeriodStart($this->params['start_month'], $this->params['start_year']);
		    $application->setPeriodEnd($this->params['end_month'], $this->params['end_year']);
		    $application->setSFAccountId($this->params['sf_account_id']);

		    $this->view->app = $application;

		    try {
			    $account = $application->getAccountDetail();
			    $this->view->rateSet = $application->getRateSetFromSalesforce();
			    $this->view->valueEvent = $application->getCurrentValueEventInSalesforce();
		    } catch (Exception $e) {
		    	throw new Myshipserv_Exception_MessagedException(
		    	    "Failed to retrieve supplier account details, rate sets and value events from SalesForce." . $e->getMessage()
			    );
		    }

		    if ($legacyFormat) {
		        $this->view->uploadCsvHeaders = array(
				    'Period_start__c',
				    'Period_end__c',
				    'Gross_Merchandise_Value__c',
				    'Unactioned_RFQs__c',
				    'Unique_contact_views__c',
				    'Targeted_impressions__c',
				    'Rate__c',
				    'TransactionAccount__c'
			    );

			    try {
				    $this->view->valueEventsToBeUploaded = $application->getValueEventDataFromReportService();
			    } catch (Exception $e) {
			        throw new Myshipserv_Exception_MessagedException(
			            "Failed to retrieve legacy value events for supplier, probably not on legacy VPB pricing. " .
				        "Try switching to AP format."
			        );
			    }
		    } else {
			    $this->view->uploadCsvHeaders = $application->getCsvHeaders();

			    try {
				    $this->view->valueEventsToBeUploaded = $application->getValueEventsFromDb();
			    } catch (Exception $e) {
			    	throw new Myshipserv_Exception_MessagedException(
			            "Failed to retrieve value events for supplier possibly due to date range.  Legacy value events " .
			            "are pre-February 2016, whereas Active Promo are post January 2016. " .
			            "So try running those separately.  First the legacy pre-April 2016, then AP post March 2016."
				    );
			    }
		    }

		    $this->view->supplier = Shipserv_Supplier::getInstanceById($application->getSalesForceAccountData('tnid'), '', true, false);
	    }

    	// default and STEP 1
    }

    public function vbpHealthCheckAction()
    {
    	if ($this->user->canPerform('PSG_ACCESS_SALESFORCE') === false) {
    		throw new Myshipserv_Exception_MessagedException('You are not allowed to access this page as specified within your group: ' . $this->user->getGroupName(), 403);
    	}

    	ini_set('max_execution_time', 0);
    	$application = new Myshipserv_Salesforce_Report_Billing_HealthCheck();
    	$this->view->application = $application;
    	$x = $application->getData();;
    	$this->view->vbpData = $x;
    }

    
    public function cronHealthCheckAction()
    {
    	$sql = "
    		SELECT
    			TO_CHAR(job_run, 'DD-MON-YYYY HH24:MI:SS') job_run
    			, sysdate - job_run days_ago
    			, job_type
    			, job_description
    		FROM
    			pages_job
    		ORDER BY
    			job_run DESC
    	";
     	$resource = $GLOBALS["application"]->getBootstrap()->getPluginResource('multidb');
        $db = $resource->getDb('ssreport2');

    	$this->view->result = $db->fetchAll($sql);
    }

    
    public function poRateAction()
    {
    	$sql = "
    		SELECT
    			spb_branch_code
    			, spb_name
    			, spb_monetization_percent
    			, SPB_PO_PACK_PERCENTAGE
    			, spb_vbp_percentage
    			, SPB_SALESFORCE_ID sfa_id
    		FROM
    			supplier_branch
    		WHERE
				sPB_ACCOUNT_DELETED = 'N'
				AND spb_test_account = 'N'
    			AND (
    				spb_monetization_percent IS NOT null
    				OR SPB_PO_PACK_PERCENTAGE IS NOT null
    			)
    	";

    	$this->view->result = $this->db->fetchAll($sql);
    }

    
    public function changeOrganisationNameAction()
    {
        $response = array(
            'status' => 'failure',
            'description' => '',
        );
        if ($this->getRequest()->isPost()) {
            try {
                $org = new Shipserv_Oracle_BuyerOrganisations();
                $org->updateName($this->getRequest()->getParam('id'), $this->getRequest()->getParam('name'));
                $this->_helper->json((array)
                    array(
                        'status' => 'success',
                        'description' => $this->getRequest()->getParam('name'),
                    )
                );
            } catch (Exception $e) {
                $response['description'] = $e->getMessage();
            }
        } else {
            $response['description'] = 'http method must be a post';
        }
        $this->_helper->json((array)$response);
    }
    
    /**
     * Action called by user & company byo setting, to change SPR status
     */
    public function changeByoSprAccessStatusAction()
    {
    	$response = array(
    			'status' => 'failure',
    			'description' => '',
    	);
    	if ($this->getRequest()->isPost()) {
    		try {
    			$org = new Shipserv_Oracle_BuyerOrganisations();
    			$org->updateSprAccessStatus($this->getRequest()->getParam('id'), $this->getRequest()->getParam('status'));
    			$this->_helper->json((array)
    					array(
    						'status' => 'success',
    						'description' => $this->getRequest()->getParam('status'),
    						)
    					);
    		} catch (Exception $e) {
    			$response['description'] = $e->getMessage();
    		}
    	} else {
    		$response['description'] = 'http method must be a post';
    	}
    	$this->_helper->json((array)$response);
    }
    
    /**
     * Action called by user & company byo setting, to change SPR status
     * Requires a post HTTP request, using id, status
     * if status is not set, it will only read the current status, if set, it will reset the status
     * 
     * @return unkown
     */
    public function changeByoMembershipStatusAction()
    {
    	$response = array(
    			'status' => 'failure',
    	);
    	
    	if ($this->getRequest()->isPost()) {
    		try {
				$id = $this->getRequest()->getParam('id', 0);
				$status = $this->getRequest()->getParam('status', null);
				$companyType = 'BYO';
				Shipserv_Oracle_PagesCompany::getInstance()->setMembershipLevel($companyType, $id, (int)$status);
				$result = Shipserv_Oracle_PagesCompany::getInstance()->getMembershipLevel($companyType, $id);
    			$this->_helper->json((array)
    				array(
    					'status' => 'success',
    					'level' => $result
    				)
    			);
    		} catch (Exception $e) {
    			$response['description'] = $e->getMessage();
    		}

    	}
    	
    	$this->_helper->json((array)$response);
    }


    /**
     * Because the RN(row number) is unique per result,
     * we remove the attribute to create unique results as the ID is used as the identifier when
     * result set is shown
     * @param $rows
     * @return array
     */
    private function uniqueResults($rows){

        if(count($rows) ===0){
            return [];
        }

        foreach($rows as &$item){
            unset($item['RN']);
        }

        unset($item);

        $serialized = array_map('serialize', $rows);
        $unique = array_unique($serialized);
        return array_intersect_key($rows, $unique);
    }
    
    
    public function manageUserAction()
    {
        $params = $this->params;
        $app = new Myshipserv_UserCompany_Management;
        $app->setParams($params);
        if (!isset($params['b'])) {
            if ($params['a'] == 'search') {
            	$keyword = (($params['value']!="")?$params['value']:$params['q']);
            	$limit = (($params['value']!="")?10:null);

                $rows = $app->search($keyword, $this->params['t'], $limit);

                $rows = $this->uniqueResults($rows);

                $this->_helper->json((array)$rows);
            } elseif ($params['a'] == 'user') {
                $rows = $app->getUserDetail($params['q']);
                $res = $rows[0];
                $res['CAN_LOGIN_AS'] = (Shipserv_User::isLoggedIn()->canPerform('PSG_LOGIN_AS')? 'Y' : 'N');
                $this->_helper->json((array)$res);
            } elseif ($params['a'] == 'user-company') {
                $rows = $app->getListOfUserCompanies($params['q']);
                foreach ($rows as &$row) {
                    $row['PUC_IS_DEFAULT'] = (String) (isset($row['PUC_IS_DEFAULT']) && $row['PUC_IS_DEFAULT']? 1 : 0);
                }
                $this->_helper->json((array)$rows);
            } elseif ($params['a'] == 'user-activity') {
                $rows = $app->getListOfUserActivity($params['q']);
                $this->_helper->json((array)$rows);
            } elseif ($params['a'] == 'spb') {
                $supplier = Shipserv_Supplier::getInstanceById($params['q'], "", true, false);
                $this->_helper->json((array)$supplier);
            } elseif ($params['a'] == 'con') {
                $consortia = Shipserv_Consortia::getConsortiaInstanceById((int)$params['q']);
                $this->_helper->json((array)$consortia);
            } elseif ($params['a'] == 'byb') {
                $buyerBranch = Shipserv_Buyer::getBuyerBranchInstanceById($params['q'], "", true);
                $this->_helper->json((array)$buyerBranch);
            } elseif ($params['a'] == 'byo') {
            	$buyerBranch = Shipserv_Buyer::getInstanceById($params['q'], true);
            	$buyerBranch->normalisationOfCompanyId = $buyerBranch->getNormalisedCompanyByOrgId($buyerBranch->id);
            	$buyerBranch->normalisingCompanyIds = $buyerBranch->getNormalingCompaniesByOrgId($buyerBranch->id);
            	$allowedTnIds = explode(",", $this->config['shipserv']['spr']['allow']['tnids']);
            	//S19658 Indicate if TNID SPR access level is overrided in application.ini 
            	$buyerBranch->sprAllowOverride = (in_array($params['q'], $allowedTnIds)) ? 1 : 0;
            	
            	if ($buyerBranch->normalisationOfCompanyId !== null) {
            		$buyerBranch->parentBuyer = Shipserv_Buyer::getInstanceById($buyerBranch->normalisationOfCompanyId, true);
            	}

            	$this->_helper->json((array)$buyerBranch);
            } elseif ($params['a'] == 'company-user') {
            	$rows = $app->getListOfUsersFromCompanies($params['q'], $params['t']);
                $this->_helper->json((array)$rows);
            } elseif ($params['a'] == 'byo-byb') {
            	$rows = $app->getListOfBuyerBranchByBuyerOrgCode($params['q']);
                $this->_helper->json((array)$rows);
		    } elseif ($params['a'] == 'byo-struc') {
                $bybNo = (int)$this->params['byo'];
                if ($bybNo > 0) {
                    $byoId = Shipserv_Buyer::getInstanceById( (int)$this->params['byo'] );
                    $tree = new Shipserv_Buyer_OrgTree($byoId);
                    $this->_helper->json((array)$tree->getTree());
                } else {
                    $this->_helper->json((array)array('error: Invalid BYO No'));
                }
            }
            
        } else if (isset( $params['b'])) {
			//ddl goes in here
            if ($params['b'] == 'user') {
            	$result = $app->updateUser();
                $this->_helper->json((array)$result);
            } elseif ($params['b'] == 'update-user-company') {
            	$result = $app->processUpdateColumnUserCompany($this->params);
            	$this->_helper->json((array)$result);
            } elseif ($params['b'] == 'user-company') {
            	$result = $app->processUserJoinCompany($this->params);
            	$this->_helper->json((array)$result);
            }
        }
    }
    

    public function zoneHelperAction()
    {

    	if( $this->params['a'] == 'xml')
    	{
    		define('SCRIPT_PATH', dirname(__FILE__));
    		$dir = dirname(dirname(SCRIPT_PATH)) . '/../../library/zones';

    		$cdir = scandir($dir);
    		foreach ($cdir as $key => $value)
    		{
    			$content = file_get_contents($dir . '/' . $value);

    			$sql = "
    				UPDATE
    					PAGES_ZONE
    				SET
    					pgz_xml_content=:xml
    				WHERE
    					pgz_system_name=:name
    			";
    			$params = array('xml' => $content, 'name' => str_replace('.xml', '', $value));
				try{
					$this->db->query($sql, $params);
				}catch(Exception $e){
					echo "Issue with " . $params['name'] . " -- error: " . $e->getMessage() .  "<br />";
				}
				echo $params['name'] . " XML inserted ... <br />";
    		}
    	}

    	if( $this->params['a'] == 'sql')
    	{

	    	echo "SET DEFINE OFF; <br />";

	    	foreach(Shipserv_Zone_Old::$urlToZone as $systemName => $url)
	    	{
	    		echo "INSERT INTO pages_zone(pgz_system_name, pgz_url, pgz_xml_content) VALUES(	'"  . $systemName . "'	,	 '"  . $url . "', EMPTY_CLOB()	);" . "<br />";
	    	}

	    	echo "<br />COMMIT;<br />";


	    	foreach(Shipserv_Zone_Old::$zoneData as $systemName => $row)
	    	{
	    		echo "UPDATE pages_zone SET pgz_name='"  . $row['name'] . "'	WHERE pgz_system_name='"  . $systemName . "'	;" . "<br />";
	    	}

	    	echo "<br />COMMIT;<br />";

	    	foreach(Shipserv_Zone_Old::$enabledZones as $systemName => $row)
	    	{
	    		echo "UPDATE pages_zone SET pgz_homepage_image='"  . $row['image'] . "', pgz_homepage_title='"  . $row['title'] . "' WHERE pgz_system_name='"  . $systemName . "'	;" . "<br />";
	    	}

	    	echo "<br />COMMIT;<br />";

	    	foreach(Shipserv_Zone_Old::$zoneSynonyms as $keyword => $systemName)
	    	{
	    		echo "INSERT INTO pages_zone_keyword(pzk_pgz_system_name, pzk_keyword) VALUES('"  . $systemName . "', '"  . $keyword . "');" . "<br />";
	    		//echo "UPDATE pages_zone SET pgz_homepage_image='"  . $row['image'] . "', pgz_homepage_title='"  . $row['title'] . "' WHERE pgz_system_name='"  . $systemName . "'	;" . "<br />";
	    	}

	    	echo "<br />COMMIT;<br />";

	    	foreach(Shipserv_Zone_Old::$mapArray as $mapTo => $systemName)
	    	{
	    		echo "INSERT INTO pages_zone_mapping(PZM_PGZ_SYSTEM_NAME, PZM_MAP_TO) VALUES('"  . $systemName . "', '"  . $mapTo . "');" . "<br />";
	    		//echo "UPDATE pages_zone SET pgz_homepage_image='"  . $row['image'] . "', pgz_homepage_title='"  . $row['title'] . "' WHERE pgz_system_name='"  . $systemName . "'	;" . "<br />";
	    	}


	    	$zoneAds = array('hamburg'=>array("text"=>"Hamburg Supplier Guide",
	    			"title"=>"Search marine suppliers in the Hamburg",
	    			"style"=>"hamburg"),
	    			'rotterdam'=>array("text"=>"Rotterdam Supplier Guide",
	    					"title"=>"Search marine suppliers in the Rotterdam",
	    					"style"=>"rotterdam"),
	    			'ppg' => array("text" => "PPG Zone",
	    					"title" => "PPG Coatings",
	    					"style" => "ppg"),
	    			'shanghai'=>array("text"=>"Shanghai Supplier Guide",
	    					"title"=>"Search marine suppliers in the Shanghai",
	    					"style"=>"shanghai"),
	    			'copenhagen'=>array("text"=>"Copenhagen Supplier Guide",
	    					"title"=>"Search marine suppliers in the Copenhagen",
	    					"style"=>"copenhagen"),
	    			'singapore'=>array("text"=>"Singapore Supplier Guide",
	    					"title"=>"Search marine suppliers in the Singapore",
	    					"style"=>"singapore"),
	    			'chandlers'=>array("text"=>"Chandlery Supplier Guide",
	    					"title"=>"Chandlers Zone",
	    					"style"=>"chandlery"),
	    			'issa'=>array("text"=>"ISSA Zone",
	    					"title"=>"ISSA Zone",
	    					"style"=>"issa"),
	    					// taken out as requested by Jo
	    			//						 'wartsila'=>array("text"=>"W&auml;rtsil&auml; Zone",
	    					//										"title"=>"All authorised Wartsila Suppliers",
	    					//						 				"style"=>"wartsila"),
	    			'tts' => array("text" => "TTS Zone",
	    					"title" => "All authorised TTS Suppliers",
	    					"style" => "tts"),
	    					//						 'sperre'=>array("text"=>"Sperre Zone",
	    							//										"title"=>"All authorised Sperre Suppliers",
	    							//						 				"style"=>"sperre"),
	    			'wencon'=>array("text"=>"Wencon Zone",
	    					"title"=>"All authorised Wencon Suppliers",
	    					"style"=>"wencon"),
	    			'hms'=>array("text"=>"HMS Zone",
	    							"title"=>"All HMS group members",
	    							"style"=>"hms"),
	    							'sinwa'=>array("text"=>"Sinwa Zone",
	    									"title"=>"Sinwa Zone",
	    									"style"=>"sinwa"),
	    									'jpsauer'=>array("text"=>"Sauer Compressors Zone",
	    											"title"=>"All authorised JP Sauer Suppliers",
	    											"style"=>"sauer"),
	    											'bnwas'=>array("text"=>"BNWAS Zone",
	    													"title"=>"Search marine suppliers of BNWAS",
	    													"style"=>"bnwas"),
	    													'pumps'=>array("text"=>"Pump Zone",
	    															"title"=>"Search marine suppliers of Pumps",
	    															"style"=>"pump"),
	    															'valves'=>array("text"=>"Valves Zone",
	    																	"title"=>"Search marine suppliers of Valves",
	    																	"style"=>"valves"),
	    																	'oil_gas'=>array("text"=>"Oil & Gas Zone",
	    																			"title"=>"Search marine suppliers of Oil & Gas",
	    																			"style"=>"oil-gas"),
	    																			'mandiesel' => array("text" => "MAN Diesel & Turbo",
	    																					"title" => "All authorised Man Diesel Suppliers",
	    																					"style"=>"man"),
	    																					'life-saving-equipment'=>array("text"=>"Life Saving Equipment Zone",
	    																							"title"=>"Search marine suppliers of Life Saving Equipment",
	    																							"style"=>"life"),
	    																							'yanmar'=>array("text"=>"Yanmar Zone",
	    																									"title"=>"All authorised Wencon Suppliers",
	    																									"style"=>"yanmar"),
	    																									'rs'=>array("text"=>"RS Components Zone",
	    																											"title"=>"RS Components Zone",
	    																											"style"=>"rs"),
	    																											'timavo'=>array("text"=>"<strong>Timavo Ship Supply & TSS d.o.o</strong> Brandzone",
	    																													"title"=>"Timavo Ship Supply & TSS d.o.o Brandzone",
	    																													"style"=>"timavo"),
	    																													'iss'=>array("text"=>"<strong>ISS Machinery Services</strong> Zone",
	    																															"title"=>"ISS Machinery Services Zone",
	    																															"style"=>"iss"),
	    																															'manitowoc'=>array("text"=>"<strong>Manitowoc</strong> Zone",
	    																																	"title"=>"Manitowoc Zone",
	    																																	"style"=>"manitowoc"),
	    																																	'wrist'=>array("text"=>"<strong>Wrist Ship Supply</strong> Zone",
	    																																			"title"=>"Wrist Ship Supply Zone",
	    																																			"style"=>"wrist"),
	    																																			'mak'=>array("text"=>"<strong>MaK Marine Engines</strong> Zone",
	    																																					"title"=>"MaK Marine Engines Zone",
	    																																					"style"=>"mak"),
	    																																					'alfalaval'=>array("text"=>"<strong>Alfa Laval Aalborg</strong> Zone",
	    																																							"title"=>"Alfa Laval Aalborg",
	    																																							"style"=>"alfa"),
	    																																							'ems'=>array("text"=>"<strong>EMS Seven Seas </strong>",
	    																																									"title"=>"EMS Seven Seas Spain",
	    																																									"style"=>"ems"),
	    																																									'rms'=>array("text"=>"<strong>RMS Marine Service Company Ltd</strong> Zone",
	    																																											"title"=>"RMS Marine Service Company Ltd",
	    																																											"style"=>"rms")/*,
	    					'gns'=>array("text"=>"",
	    							"title"=>"",
	    							"style"=>"gns")*/

	    																																									);

	    	echo "<br />COMMIT;<br />";
	    	foreach($zoneAds as $systemName => $row)
	    	{
	    		echo "UPDATE pages_zone SET pgz_homepage_text='"  . htmlentities($row['text']) . "', pgz_homepage_style='"  . $row['style'] . "' WHERE pgz_system_name='"  . $systemName . "'	;" . "<br />";
	    	}

	    	echo "<br />COMMIT;<br />";

    	}
    	die();
    }

    public function erroneousTransactionsAction()
    {

    }

    public function erroneousTransactionsReportAction()
    {

        switch ($this->params['type']) {
            case 'transaction-list':
                $report = new Shipserv_Report_erroneousTransactions_Report();
                $result = $report->getTransactionNotifications((int)$this->params['page'], (int)$this->params['itemPerPage']);
                $this->_helper->json((array)$result);
                break;
            case 'resend-buyer':
                $ordInternalRefNo = (int)$this->params['ordInternalRefNo'];
                if ($ordInternalRefNo > 0) {
                    Shipserv_Report_erroneousTransactions_Resend::getInstance()->reSendNotificationToBuyer($ordInternalRefNo);
                    $this->_helper->json((array)array('ok'));
                } else {
                    $this->_helper->json((array)array('error: Invalid Ord Internal Ref No'));
                }

                break;
                case 'resend-supplier':
                $ordInternalRefNo = (int)$this->params['ordInternalRefNo'];
                if ($ordInternalRefNo > 0) {
                    Shipserv_Report_erroneousTransactions_Resend::getInstance()->reSendNotificationToSupplier($ordInternalRefNo);
                    $this->_helper->json((array)array('ok'));
                } else {
                    $this->_helper->json((array)array('error: Invalid Ord Internal Ref No'));
                }
                break;  
                case 'email-supplier':
                    $ordInternalRefNo = (int)$this->params['ordInternalRefNo'];
                    if ($ordInternalRefNo > 0) {
                        $result = Shipserv_Report_erroneousTransactions_View::getInstance()->getEmailTemlateForSupplier($ordInternalRefNo);
                        $this->_helper->json((array)array('html' => $result));
                    } else {
                        $this->_helper->json((array)array('error: Invalid Ord Internal Ref No'));
                    }
                    break;
                case 'email-buyer':
                    $ordInternalRefNo = (int)$this->params['ordInternalRefNo'];
                    if ($ordInternalRefNo > 0) {
                        $result = Shipserv_Report_erroneousTransactions_View::getInstance()->getEmailTemlateForBuyer($ordInternalRefNo);
                        $this->_helper->json((array)array('html' => $result));
                    } else {
                        $this->_helper->json((array)array('error: Invalid Ord Internal Ref No'));
                    }
                    break;
                case 'email-gsd':
                    $ordInternalRefNo = (int)$this->params['ordInternalRefNo'];
                    if ($ordInternalRefNo > 0) {
                        $result = Shipserv_Report_erroneousTransactions_View::getInstance()->getEmailTemlateForGsd($ordInternalRefNo);
                        $this->_helper->json((array)array('html' => $result));
                    } else {
                        $this->_helper->json((array)array('error: Invalid Ord Internal Ref No'));
                    }
                    break;
                case 'email-second-reminder':
                    $ordInternalRefNo = (int)$this->params['ordInternalRefNo'];
                    if ($ordInternalRefNo > 0) {
                        $result = Shipserv_Report_erroneousTransactions_View::getInstance()->getEmailTemlateForSecondReminder($ordInternalRefNo);
                        $this->_helper->json((array)array('html' => $result));
                    } else {
                        $this->_helper->json((array)array('error: Invalid Ord Internal Ref No'));
                    }
                    break;
                    case 'confirm-as-correct':
                    $ordInternalRefNo = (int)$this->params['ordInternalRefNo'];
                    if ($ordInternalRefNo > 0) {
                        Shipserv_Report_erroneousTransactions_Confirm::getInstance()->confirm($ordInternalRefNo);
                        $this->_helper->json((array)array('ok'));
                    } else {
                        $this->_helper->json((array)array('error: Invalid Ord Internal Ref No'));
                    }
                    break;

                    
            default:
                throw new Myshipserv_Exception_MessagedException('The supplier parameter is incorrect ' . $this->user->getGroupName(), 403);
                break;
        }
    }

    /**
    * Action for /shipmate/scorecard
    */
    public function scorecardAction()
    {
        $user = Shipserv_User::isLoggedIn();
        if ($user && $user->canPerform('PSG_ACCESS_SCORECARD')) {
            // Generate monts for many dropdowns
            $months = array();
            for ($i=1;$i<=12;$i++) {
                $mon = date("M",mktime(0,0,0,$i,1,2011));
                $months[strtoupper($mon)] = $mon;
            }

            $this->view->newLayout = false;
            $this->view->montsh = $months;
            $this->view->currentYear = (int)date('Y');
            $this->view->md5sum = $user->userRow['USR_MD5_CODE'];
            $this->view->userCode = (int)$user->userRow['USR_USER_CODE'];
            $this->view->scorecardDomain = $this->config['shipserv']['services']['scorecard']['domain'];
            $this->view->telesalesDomain = $this->config['shipserv']['services']['scorecard']['telesales']['domain'];
            $this->view->scorecardAdvanced = $user->canPerform('PSG_ACCESS_SCORECARD_ADV');
        } else {
            throw new Myshipserv_Exception_MessagedException('You are not allowed to access this page', 403);
        }
        
    }

    /**
    * Action for redirecting, and add new hash to ScoreCard report calls
    */
    public function scorecardRedirectAction()
    {
        $user = Shipserv_User::isLoggedIn();
        if( $this->user && $this->user->canPerform('PSG_ACCESS_SCORECARD') ) 
        {
            if (array_key_exists('service', $this->params))
            {
                $urlParts = explode("?",$this->params['service']);
                if (count($urlParts) > 1)
                {
                    $salt = 'x7A@_salt_D9ac_';
                    $hash = Myshipserv_Pbkdf2::pbkdf2("sha256", $urlParts[1], $salt, 783, 32);
                    $url = $this->params['service'] . '&h='.$hash;
                    $this->redirect($url);
                }
            }
        }

        throw new Myshipserv_Exception_MessagedException('You are not allowed to access this page', 403);
    }
    
    
    /**
     * Login as action allowed only for shipmates with login-as permissions 
	 */
    public function loginAsAction()
    {
        if (!Shipserv_User::isLoggedIn() || !Shipserv_User::isLoggedIn()->canPerform('PSG_LOGIN_AS')) {
            throw new Myshipserv_Exception_MessagedException('You are not allowed to perform this action', 403); 
        }
        if (!$this->getRequest()->isPost()) {
            throw new Myshipserv_Exception_MessagedException('This action is avaialble only via http POST', 501);
        }        
        if (!$this->getRequest()->getParam('username') || !$this->getRequest()->getParam('superpassword')) {
            throw new Myshipserv_Exception_MessagedException('You need to provide a username and the super password to perform this action', 403);
        }     
        
        if (Shipserv_User::autoLoginViaCas($this->getRequest()->getParam('username'), $this->getRequest()->getParam('superpassword'), false, true)) {
            $this->_helper->json((array)array('status' => 'success'));            
        } else {
            throw new Myshipserv_Exception_MessagedException('Credentials not valid. Auto login could not succeed', 200);
        }
    }
    
    /**
     * Entry point to synch targer rate with SalesForce
     */
    public function rateSynchAction()
    {
    	//TODO S20551 Delete the exception when we will go live with this feature
    	throw new Myshipserv_Exception_MessagedException(
    			"Page does not exists.",
    			404
    			);
    }
    
    /**
     * Getting JSON result for data
     */
    public function rateSynchDataAction()
    {
    	set_time_limit(0);
    	$response = array(
    			'status' => 'error',
    			'description' => 'invalid parameters',
    	);
    	
	   	if ($this->getRequest()->getParam('tnid') !== null) {
	    	switch ($this->getRequest()->getParam('mode')) {
	    		case 'getRate':
	    			$response = Shipserv_Profile_Targetcustomers_RateSynch::getRate((int)$this->getRequest()->getParam('tnid'));
	    			break;
	    		case 'synchRate':
	    			$response = Shipserv_Profile_Targetcustomers_RateSynch::synchRate((int)$this->getRequest()->getParam('tnid'));
	    			break;
	    		default:
	    			break;
	    	}
    	} else {
    		$response['description'] = 'tnid parameter is missing';
    	}

    	$this->_helper->json((array)$response);
    }
    
    /*
     * This action will set IMPA related flags, in first step, anonymize impa reoort
     */
    public function impaSettingsAction()
    {
        if ($this->getRequest()->isPost()) {
            $this->view->impaAnonymize = Myshipserv_Impa_Anonymize::setStatus($this->getRequest()->getPost('impaAnonymize', '') == 'on');
        } else {
            $this->view->impaAnonymize = Myshipserv_Impa_Anonymize::getStatus();
        }
	}
	
	/*
	 * This action will set SPR related anonymize flag
	 */
	public function sprSettingsAction()
	{
	    if ($this->getRequest()->isPost()) {
	        $this->view->sprAnonymize = Myshipserv_Spr_Anonymize::setStatus($this->getRequest()->getPost('sprAnonymize', '') == 'on');
	    } else {
	        $this->view->sprAnonymize = Myshipserv_Spr_Anonymize::getStatus();
	    }
	}

    /**
     * Action for checking whether the user is allowed to go to BC Admin
     *
     * @throws Myshipserv_Exception_MessagedException
     */
	public function buyerConnectAdminAction()
	{
        $user = Shipserv_User::isLoggedIn();

        if (!($user && $user->canPerform('PSG_ACCESS_BC_ADMIN'))) {
            throw new Myshipserv_Exception_MessagedException('You are not allowed to access this page', 403);
        }
	}

	/**
     * Entry point for ShipMate Supplier Insight Report
     */
	public function supplierInsightReportAction()
    {
        $user = Shipserv_User::isLoggedIn();
        if (!($user && $user->canPerform('PSG_ACCESS_SIR'))) {
            throw new Myshipserv_Exception_MessagedException("You don't have access to this report", 403);
        }

        $sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
        if ($sessionActiveCompany->type !== 'v') {
            throw new Myshipserv_Exception_MessagedException('This report is only for Suppliers, please change your TNID', 403);
        }

        $tnid = $sessionActiveCompany->id;
        $supplier = Shipserv_Supplier::fetch($tnid, '', true);

        $this->view->selectedSupplier = $supplier;
    }

    /**
     * Entry point for the new ShipMate Supplier Insight Report
     */
	public function supplierInsightReportPctAction()
    {
        $user = Shipserv_User::isLoggedIn();
        if (!($user && $user->canPerform('PSG_ACCESS_SIR'))) {
            throw new Myshipserv_Exception_MessagedException("You don't have access to this report", 403);
        }

        $sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
        if ($sessionActiveCompany->type !== 'v') {
            throw new Myshipserv_Exception_MessagedException('This report is only for Suppliers, please change your TNID', 403);
        }

        $tnid = $sessionActiveCompany->id;
        $supplier = Shipserv_Supplier::fetch($tnid, '', true);

        $this->view->selectedSupplier = $supplier;
    }
    /**
     * Entry point to download SIR image type report
     *
     * @throws Exception
     * @throws Myshipserv_Exception_MessagedException
     */
    public function supplierInsightReportImgDownloadAction()
    {
        $user = Shipserv_User::isLoggedIn();
        if (!($user && $user->canPerform('PSG_ACCESS_SIR'))) {
            throw new Myshipserv_Exception_MessagedException("You don't have access to this report", 403);
        }

        $report = new Myshipserv_Shipmate_Sir_DownloadImgReport();
        $report->getImageReport($this->params);
    }

}
