
<?php
class Trade_TradeController extends Myshipserv_Controller_Action {



	/**
	 * Initialise the controller - set up the context helpers for AJAX calls
	 * @access public
	 */
	public function init() {
		parent::init();
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext->addActionContext('rfq-inbox', 'html')
		->initContext();

		$this->activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
		
	}
	
	public function indexAction()
	{
		//Sell tab clicked
		$user = Shipserv_User::isLoggedIn();
		if ($user) {
			$user->logActivity(Shipserv_User_Activity::SELL_TAB_CLICKED, 'PAGES_USER', $user->userId, $user->email);
		}
		$this->redirect("/trade/rfq");
	}

    /**
     * @return string
     */
    private function _getSessionHash()
	{
		return md5('TradeController::_getSessionHash_' . session_id() . "_" . $this->activeCompany->id);
	}
	
	public function viewRfqAction()
	{
		$this->_helper->layout->setLayout('print');
		$this->view->hash = $this->_getSessionHash();
		$this->view->params = $this->params;
	}
	
	public function rfqAction()
	{
		$this->_helper->layout->setLayout('default');
		$this->view->hash = $this->_getSessionHash();
		$this->view->params = $this->params;

		$user = Shipserv_User::isLoggedIn();
		if( $this->params['enquiryId'] != "" && $this->params['tnid'] != "" && $this->params['hash'] != "" )
		{
			$enquiry = Shipserv_Enquiry::getInstanceByIdAndTnid($this->params['enquiryId'], $this->params['tnid']);
			if( $enquiry->pinHashKey != $this->params['hash'])
			{
				throw new Myshipserv_Exception_MessagedException("We cannot find your RFQ, please check your url");
			}
		}
		
		if( $this->activeCompany->type === 'b' )
		{
			throw new Myshipserv_Exception_MessagedException("There is no RFQ Inbox for Buyer on ShipServ Pages", 200);
		}
		
		if( $user === false )
		{
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}
	}

	public function blockBuyerAction()
	{
		$user = Shipserv_User::isLoggedIn();

		if( $user === false ) {
			Myshipserv_CAS_CasRest::getInstance()->redirectToLogin();
		}

		if( $this->activeCompany->type === 'b' )
		{
			throw new Myshipserv_Exception_MessagedException("There is no RFQ Inbox for Buyer on ShipServ Pages", 200);
		}

		$this->_helper->layout->setLayout('default');
	}

	public function poAction()
	{
		$config = Zend_Registry::get('config');
		//S19037 Temporarily supend this function
		$suspended = (int)$config->shipserv->create->po->from->pages;
		if ($suspended === 0) {
			$this->view->suspended = true;
		} else {
			$this->view->suspended = false;
			
			$this->_helper->layout->setLayout('default');
			$activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
			
			$pdfExporter = ( $this->params['pdfExport'] == 1);
			
			// checking if user is authorised to see this quote
			if( $this->params['h'] != md5("Shipserv_Quote" . $this->params['rfqInternalRefNo'] . $this->params['qotInternalRefNo']) )
			{
				throw new Myshipserv_Exception_MessagedException("You are not authorised to view this page.", 403);
			}
			
			$quote = Shipserv_Quote::getInstanceById($this->params['qotInternalRefNo']);
			$rfq = $quote->getRfq();
			
			if( $pdfExporter == false )
			{
				$user = Shipserv_User::isLoggedIn();
				if ($user === false)
				{
					$url = "/user/cas?redirect=".$this->view->uri()->obfuscate($_SERVER['REQUEST_URI'])."&benc=1";
					$this->redirect(
						$this->getUrlToCasLogin($url),
						array('exit', true)
					);
				}
			}
			$enquiry = $rfq->getEnquiry();
			
			$this->view->selectedCompany = (($enquiry->pinPucCompanyType=="BYO")?"b":"v") . $enquiry->pinPucCompanyId;
			
			
			if( $enquiry->pinUsrUserCode != $user->userId )
			{
				if( $pdfExporter === false )
				{
					throw new Myshipserv_Exception_MessagedException("You are not authorised to place this PO. Please login with the right user.", 403);
				}
			}
			else
			{
				// redirect user to the right selected company
				if( $activeCompany->id != $enquiry->pinPucCompanyId )
				{
					$url = '/user/switch-company?tnid=' . (($enquiry->pinPucCompanyType=="BYO")?"b":"v") . $enquiry->pinPucCompanyId .'&redirect=' . urlencode($_SERVER['REQUEST_URI']);
					$this->redirect($url, array('exit', true));
				}
			}
			
			
			$this->view->quote = $quote;
			$this->view->rfq = $rfq;
	
	        // added by Yuriy Akopov on 2013-09-06, S8093
	        // if the user hasn't confirmed terms and conditions before, ask for a confirmation now
	        $this->view->tocConfirmed = $user->areTermsAndConditionsConfirmed();
		}
	}
	
	public function convertPoToPdfAction()
	{
		$this->_helper->layout->setLayout('default');
		include_once("/var/www/libraries/PdfCrowd/pdfcrowd.php");
		try
		{
			// create an API client instance
			$client = new Pdfcrowd("shipserv", "50329d7172b9c6529321ebdb66141fad");
		
			// convert a web page and store the generated PDF into a $pdf variable
			$pdf = $client->convertURI('http://ukdev5.shipserv.com/trade/po?rfqInternalRefNo=7390227&qotInternalRefNo=5569067&h=91ba8a01af0854aed14296a7a884600c&cname=Shipserv_Quote&pdfExport=1');
		
			// set HTTP response headers
			header("Content-Type: application/pdf");
			header("Cache-Control: no-cache");
			header("Accept-Ranges: none");
			header("Content-Disposition: attachment; filename=\"google_com.pdf\"");
		
			// send the generated PDF
			echo $pdf;
		}
		catch(PdfcrowdException $why)
		{
			echo "Pdfcrowd Error: " . $why;
		}
		
		die();
	}
	
	public function processPoAction()
	{
        // block added by Yuriy Akopov on 2013-09-10, S8093
        $user = Shipserv_User::isLoggedIn();
        if ($user === false) {
            $url = "/user/register-login/redirectUrl/".$this->view->uri()->obfuscate($_SERVER['REQUEST_URI'])."/benc/1";
            $this->redirect(
            		$this->getUrlToCasLogin($url),
            		array('exit', true)
            );
        }

		$params = $this->params;

		// validation
		if(
            $params['qid'] &&
            ctype_digit($params['qid']) &&
            $params['rid'] != "" &&
            ctype_digit($params['rid']) &&
            $params['h'] != "" &&
            // added by Yuriy Akopov on 2013-09-09, S8093
            $params['tocConfirmed'] == 1
        ) {
			$quote = Shipserv_Quote::getInstanceById($params['qid']);
			if( $quote->getSecurityHashToPlacePurchaseOrder() != $params['h'] )
			{
				throw new Exception("Security breach");
			}
			
			$po = $quote->convertToPo();
			if( $po->sendPoToTradeNetCore() === true)
			{
                // added by Yuriy Akopov on 2013-09-10, S8093
                // if PO was sent successfully, leave a mark that there is no need to ask for terms confirmation again
                if (!$user->areTermsAndConditionsConfirmed()) {
                    $user->confirmTermsAndConditions();
                }

				//redirect
				$this->_forward('po-sent', 'trade', null, null);
			}
			else
			{
				throw new Myshipserv_Exception_MessagedException("There is an error with our system. Our engineers have been notified.", 200);
			}
		}
		else
		{
			throw new Exception("Invalid request");
		}
	}
	
	public function poSentAction()
	{
		$config = Zend_Registry::get('config');
		//S19037 Temporarily supend this function
		$this->view->suspended = ((int)$config->shipserv->create->po->from->pages === 0);
		$this->_helper->layout->setLayout('default');
	}
		
	/**
	 * Action to handle all JSON requests from the FrontEnd
	 * Example:
	 *   http://dev3.myshipserv.com/trade/rfq-data?type=stats&id=51606
	 *   http://dev3.myshipserv.com/trade/rfq-data?type=rfqs&id=51606&start=10&total=10
	 *   http://dev3.myshipserv.com/trade/rfq-data?type=rfq&id=123456789
	 *   
	 */
	public function rfqDataAction()
	{
		$method = $_SERVER['REQUEST_METHOD'];
		$db 	= $this->db;
		$params = $this->params;
		$config = $this->config;
		
		$now = new DateTime();
		$lastYear = new DateTime();
		
		$now->setDate(date('Y'), date('m'), date('d'));
		$lastYear->setDate(date('Y')-1, date('m'), date('d'));
		
		$period = array('start' => $lastYear, 'end' => $now );
		
		if( $params['hash'] != $this->_getSessionHash() )
		{
			throw new Myshipserv_Exception_MessagedException("You are not authorised to see this", 403);
		}
		
		if( $params['type'] == "stats" || $params['type'] == "rfqs" )
		{
			if( $params['id'] != "" )
			{
				// url: http://dev3.myshipserv.com/trade/rfq-data?type=stats&id=51606
				if( $params['type'] == "stats")
				{
					$supplier = Shipserv_Supplier::getInstanceById( $params['id'] );
					$data['stats'] = $supplier->getEnquiriesStatistic( $period );
					//$data['stats']['read'] = $data['stats']['read'] - $data['stats']['replied'];
					//if( $data['stats']['read'] ) $data['stats']['read'] = 0;
					 
					$data['notification']['email'] = $supplier->getEnquiryEmail();
					$data['notification']['urlToChange'] = $supplier->getEditUrl();
					$response = new Myshipserv_Response(200, "OK", $data );
				}
				
				// url: http://dev3.myshipserv.com/trade/rfq-data?type=rfqs&id=51606&start=10&total=10
				else if ($params['type'] == "rfqs" 	)
				{
					$fields = array('pinId', 'pinName', 'pinCompany', 'pinEmail', 'pinPhone'
									, 'pinSubject', 'pirCreationDate','pirCreationDateFull', 'pinStatus', 'pinHasAttachment'
									, 'pirIsDeclined', 'pirIsReplied', 'pirIsRead', 'pirRfqInternalRefNo', 'dateDiffFromToday');
					
					$results = Shipserv_Enquiry::getInstanceByTnid($params['id'], $params['start']--, $params['total'], $totalFound, $period);

					foreach((array) $results as $object)
					{
						$row = $object->toArray($fields);
						$row['totalFound'] = $totalFound;
						if( $object->pirRfqInternalRefNo != "" )
						{
							$rfq = Shipserv_Rfq::getInstanceById($object->pirRfqInternalRefNo);
							if( $rfq->rfqRefNo != "" )
							{
								$row['pinSubject'] = $row['pinSubject'] . " (REF: " . $rfq->rfqRefNo . ")";
							} 
						}
						$data[] = $row;
					}
					$response = $data;
					$returnAsSimple = true;
				}
			}
		}
		else if( $params['type'] == "rfq" )
		{
			switch( $method )
			{
				case "GET":
                    if (strlen($params['id'])) {
                        // enquiry ID available - legacy behaviour
                        $rfq = Shipserv_Rfq::getInstanceByEnquiryIdAndTnid($params['id'], $params['tnid']);
                    } else {
                        // enquiry ID available - handler added by Yuriy Akopov on 2013-11-01 to produce the same result
                        // as with an enquiry
                        $rfq = Shipserv_Rfq::getInstanceById($params['tnid']);
                        $rfq->loadRelatedData();
                    }

                    // $response = new Myshipserv_Response(200, "OK", $rfq );
                    $response = $rfq;
                    $returnAsSimple = true;
                    break;
									
				case "POST"     :	// merging JSON with the normal params _POST
									$request = $this->getRequest();
									$rawBody = $request->getRawBody();
									$data = Zend_Json::decode($rawBody);
									$this->params = array_merge( $this->params, $data);
									
									// getting the related enquiry
									$enquiry = Shipserv_Enquiry::getInstanceByIdAndTnid($this->params['pinId'], $this->params['tnid']);
									
									// mark the RFQ to READ
									if( $this->params['pinStatus'] == Shipserv_Enquiry::IS_READ_AS_WORD )
									{
										$enquiry->setViewedDate();
										$response = array('status' => 200);
									}
									// mark the RFQ as DECLINED
									else if( $this->params['pinStatus'] == Shipserv_Enquiry::IS_DECLINED_AS_WORD )
									{
										//$enquiry->setViewedDate();
										$enquiry->setDeclinedDate();
										$response = array('status' => 200);
									}
									
									// mark the RFQ as REPLIED
									else if( $this->params['pinStatus'] == Shipserv_Enquiry::IS_REPLIED_AS_WORD )
									{
										$enquiry->setViewedDate();
										$enquiry->setRepliedDate();
										$response = array('status' => 200);
									}
									
									$returnAsSimple = true;
									break;
			}
		}
		
		if( $returnAsSimple == false )
		{
			$this->_helper->json((array) $response->toArray() );
		}
		else
		{
			$this->_helper->json((array) $response );
		}
	}
}
