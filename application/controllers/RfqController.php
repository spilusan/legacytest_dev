<?php
class RfqController extends Myshipserv_Controller_Action
{
	/**
	 * Initialise the controller - set up the context helpers for AJAX calls
	 *
	 * @access public
	 */
	public function init()
	{
		parent::init();
		 
		$ajaxContext = $this->_helper->getHelper('AjaxContext');
		$ajaxContext
		->addActionContext('add-supplier-to-basket', 'json')
		->addActionContext('remove-supplier-from-basket', 'json')
		->initContext();
	}
	
	public function sendAction()
	{
		$params = $this->params;
		$db = $this->db;
		
		// setting new layout
		$this->_helper->layout->setLayout('default');
		
		// get the UOM
		$sql = "SELECT DISTINCT MTML_STD_UNIT_CODE FROM UNIT_OF_MEASURE";
		$resultForUom = $db->fetchAll($sql);
		
		$countryAdapter = new Shipserv_Oracle_Countries($db);
		$resultForCountries = $countryAdapter->fetchAllCountries();
		
		// pass all required variables to view
		$this->view->params = $params;
		$this->view->uom = $resultForUom;
		$this->view->country = $resultForCountries;
		
		$spamChecker = new Myshipserv_Security_Spam();
		$captcha = $spamChecker->getCaptcha();
		
		$this->getResponse()->setHeader('Expires', 'Mon, 22 Jul 2002 11:12:01 GMT', true);
		$this->getResponse()->setHeader('Cache-Control', 'no-cache', true);
		$this->getResponse()->setHeader('Pragma', 'no-cache', true);
		
		$enquiryForm = new Myshipserv_Form_Enquiry();
		$config = $this->config;
		$cookieManager = $this->getHelper('Cookie');
		$db = $this->db;
		
		$this->view->requiresLogin = true;
		if ($user = Shipserv_User::isLoggedIn())
		{
			$this->view->requiresLogin = false;
		}
		
		$errors = array();
		$enquiryBasket = $supplierBasket = array();
		
		// if clearBasket is passed as a parameter, and is defined as 1, then the enquiry basket should be cleared
		if ($this->_getParam('clearBasket') == 1)
		{
			$cookieManager->clearCookie('enquiryBasket');
		}
		else
		{
			$enquiryBasket = $cookieManager->decodeJsonCookie('enquiryBasket');
		}
		 
		// if a TNID is set as a parameter, it should override whatever is in the enquiry basket
		if ($this->_getParam('tnid'))
		{
			$supplier = Shipserv_Supplier::fetch($this->_getParam('tnid'), $db);
			$supplierBasket = array($this->_getParam('tnid'));
		}
		
		
		if( count( $enquiryBasket['suppliers'] ) > 0  )
		{
			$supplierBasket = $enquiryBasket['suppliers'];
		}
		
		foreach ( (array) $supplierBasket as $tnid )
		{
			$supplierObject = Shipserv_Supplier::fetch($tnid, $db);
			$selectedSupplier[$tnid] = $supplierObject->name;
		}
		
		$enquiryBasket = array('suppliers' => $supplierBasket);
		$cookieManager->encodeJsonCookie('enquiryBasket', $enquiryBasket);
		
		if( count($selectedSupplier) == 0 )
		{
			throw new Myshipserv_Exception_MessagedException("Please select a supplier before sending a RFQ<br/><br/><a href='" . $_SERVER['HTTP_REFERER'] . "'>Click here</a> to go back", 200);
		}
		
		$this->view->selectedSupplier = $selectedSupplier;
		
		$showForm = true;
		if (!$supplierBasket)
		{
			$supplierBasket  = array();
			$errors['top'][] = 'You have no suppliers selected. Please choose some suppliers first: <a class="back_to_results_button" href="/search/results/">back to search</a>.';
			$showForm = false;
		}
		
		$appConfig = Zend_Registry::get('config');
		$maxSelectedSuppliers = $appConfig->shipserv->enquiryBasket->maximum;
		
		$this->view->showForm       = $showForm;
		$this->view->maxSelectedSuppliers = $maxSelectedSuppliers;
		$this->view->errors         = $errors;
		$this->view->supplierBasket = $supplierBasket;
		$this->view->user           = $user;
		
		$adapter = new Shipserv_Oracle_BuyerOrganisations();
		if( $this->view->requiresLogin == false )
		{
			// pull country from the user for PPG custom routing
			$companies = $this->_helper->companies->getMyCompanies($user);
			$total = count( $companies );
			if( $total == 1 )
			{
				if( $companies[0]["type"] == "v" )
				{
					$s = Shipserv_Supplier::fetch($companies[0]["id"]);
					$country[] = $s->countryCode;
				}
				else
				{
		
					$b = $adapter->fetchBuyerOrganisationsByIds ((array)$companies[0]["id"]);
					$country[] = $b[0]['BYO_COUNTRY'];
				}
		
		
				$this->view->defaultCountry = $country[0];
			}
			else
			{
				$this->view->defaultCountry = '';
			}
		}
		
		/**
		 * If the page request is not from a POST to this page, we should
		 * populate some of the form values with those from the user object
		 */
		$formValues = array();
		if (!$this->getRequest()->isPost() && is_object($user))
		{
			$formValues['sender-name']  = $user->firstName.' '.$user->lastName;
			$formValues['company-name'] = $user->companyName;
			$formValues['sender-email'] = $user->email;
		}
		
		// if any suppliers that are on the supplier-basket isn't PPG company, then remove the validation
		if( $this->isPPGCompanies($supplierBasket) == false )
		{
			//$enquiryForm->removeElement('sender-country');
			$element = $enquiryForm->getElement('sender-country');
			$element->setRequired(false);
			$element->clearValidators();
		}
		
		$showFileUploads = false;
		try
		{
			/*
			if( isset($params['captcha']) || $spamChecker->checkTotalRFQSentPerDay($user, $formValues)>2)
			{
			if (!$captcha->isValid($params['captcha']) )
			{
			throw new Exception("Please make sure that you enter correct word for the captcha below");
			}
			}
			*/
			if ( 	$this->getRequest()->isPost()
					&& (isset($params['captcha']) == false || (isset($params['captcha'])  && $captcha->isValid($params['captcha']) ))
			)
			{
				if (!$user = Shipserv_User::isLoggedIn())
				{
					$username = '';
					$userId   = '';
				}
				else
				{
					$username = $user->username;
					$userId   = $user->userId;
				}
		
				// disabled for structured RFQ
				// new automated vetting process that automatically mark a user as NOT TRUSTED/QUARANTINED
				// if they tried to send to more than 75 suppliers per week with the same RFQ content
				//$suspectedAsSpam = $spamChecker->checkRFQForSpam($user, $formValues, $enquiryBasket, $cookieManager);
		
				/**
				 * The TNIDs of the recipients are held in a cookie
				 * "SS_TNID_BASKET" as a serialized array
				 */
		
				$enquiryBasket = $cookieManager->decodeJsonCookie('enquiryBasket');
		
				// get the first 15 TNIDs
				$enquiryBasket['suppliers'] = array_slice( (array) $enquiryBasket['suppliers'], 0, $maxSelectedSuppliers, true );
		
				if (!is_array($supplierBasket) || count($supplierBasket) < 1)
				{
					throw new Exception('There are no recipients for this enquiry');
				}
		
				$tnids = $enquiryBasket['suppliers'];
		
				/*
				 // find the searchRecId if there is one
				$searchRecId = $cookieManager->fetchCookie('search');
		
				// find the getProfileId if there is one
				$getProfileId = $cookieManager->fetchCookie('profile');
		
				$files = $enquiryForm->enquiryFile->getFileName();
		
				if (!is_array($files))
				{
				$files = array($files);
				}
		
				// check existing files
				$ef = (isset($params["existingEnquiryFile"]))?$params["existingEnquiryFile"]:array();
				foreach( $ef as $f )
				{
				$existingFiles[] = '/tmp/' . $f;
				}
		
				$files = array_merge( $files, (array) $existingFiles );
		
				if( !isset( $errors['enquiryFile'] ) )
				{
		
				if (!$enquiryForm->enquiryFile->receive())
				{
				$showFileUploads = true;
				$messages = $enquiryForm->enquiryFile->getMessages();
				//throw new Exception(implode("\n", $messages));
				}
				$files = $enquiryForm->enquiryFile->getFileName();
				if (!is_array($files))
				{
				$files = array($files);
				}
		
				}
				 
				$files = array_merge( (array)$files, (array)$existingFiles );
				*/
				$files = array();
				$mtml = Shipserv_Mtml::createFromParam($this->params);
				$enquiry = new Myshipserv_Enquiry(
						$user->username,
						$user->userId,
						$user->firstName . " " . $user->lastName,
						$user->companyName,
						$user->email,
						$this->params['bPhone'],
						$this->params['dCountry'],
						$this->params['rRfqSubject'], // content
						$this->params['rRfqSubject'],
						$this->params['vVesselName'],
						$this->params['rRfqSubject'],
						$this->params['deliveryLocation'],
						$this->params['dDeliveryBy'],
						$searchRecId,
						$getProfileId,
						$files,
						$tnids,
						$mtml->xmlInString
				);
		
				if (!$user)
				{
					/**
					 * The user is not logged in, but we still want to capture
					 * the enquiry so they don't have to fill it in again.
					 *
					 * Let's stick it in memcache, then forward the user to a
					 * register/login page with a token containing the memcache
					 * key so it can be retrieved later.
					 */
					$memcache = new Memcache();
					$memcache->connect(	$config['memcache']['server']['host'],
							$config['memcache']['server']['port']);
		
					// create a key and set a cookie with it so we can fetch it later
					$key = md5(uniqid(rand(), true));
					$enqCookie = $config['shipserv']['enquiryStorage']['cookie'];
					$cookieManager->setCookie('enquiryStorage', $key);
		
					$memcache->set($key, $enquiry);

					// since it's now handled by case, then use this instead
					$this->redirect(
							$this->getUrlToCasLogin('/enquiry/send-from-login-register'),
							array('exit', true)
					);
				}
		
				// CAPTCHA
				if( isset($params['captcha']) || $spamChecker->checkTotalRFQSentPerDay($user, $formValues)>2)
				{
					if (!$captcha->isValid($params['captcha']) )
					{
						throw new Exception("Please make sure that you enter correct word for the captcha below");
					}
				}
		
				// send the RFQ to pages service
				if ( $enquiry->send())
				{
					$cookieManager->clearCookie('enquiryBasket');
		
					// clear the enquiry storage cookie
					$memcache = new Memcache();
					$memcache->connect($config['memcache']['server']['host'],
							$config['memcache']['server']['port']);
					if ($cookieManager->fetchCookie('enquiryStorage'))
					{
						$memcache->delete($cookieManager->fetchCookie('enquiryStorage'));
					}
		
					$cookieManager->clearCookie('enquiryStorage');
		
					$params['success'] = true;
					$params['header']  = 'Thank You!';
		
					if( $user->canSendPagesRFQDirectly() )
					{
						$params['message'] = 'The RFQ was successfully sent';
					}
					else
					{
						$params['message'] = "
						There will be a delay of a few hours before your RFQ is sent.<br />
						<br />
						We're checking that your RFQ follows our <a href='/help#8'>terms of use policy</a>.  We check RFQs only the first few times you use Pages.<br />
						<br />
						Suppliers ask us to do this to ensure they get quality RFQs.  Sorry for the inconvenience.<br />
						";
					}
					$user->logActivity(Shipserv_User_Activity::ENQUIRY_SENT, 'PAGES_INQUIRY+PIN_SUBJECT', $user->userId, $formValues['enquiry-subject']);
					$this->_forward('success-page', 'enquiry', null, $params);
				}
				 
			}
			elseif ($this->getRequest()->isPost())
			{
				if ($this->view->showForm) {
					$formValues = $this->getRequest()->getParams();
					$errors = $enquiryForm->getMessages();
		
					$files = array();
		
					// check existing files
					$existingFiles = (isset($params["existingEnquiryFile"]))?$params["existingEnquiryFile"]:array();
		
					if( !isset( $errors['enquiryFile'] ) )
					{
						if (!$enquiryForm->enquiryFile->receive())
						{
							$showFileUploads = true;
							$messages = $enquiryForm->enquiryFile->getMessages();
							throw new Exception(implode("\n", $messages));
						}
						$files = $enquiryForm->enquiryFile->getFileName();
						if (!is_array($files))
						{
							$files = array($files);
						}
		
					}
		
		
					$files = array_merge( $files, $existingFiles );
					$this->view->files = $existingFiles ;
		
					$this->view->files = $files;
					$errors = $enquiryForm->getMessages();
		
					if ($errors['enquiryFile'])
					{
						$showFileUploads = true;
					}
		
					if( isset($params['captcha']) || $spamChecker->checkTotalRFQSentPerDay($user, $formValues)>2)
					{
						if (!$captcha->isValid($params['captcha']) )
						{
							throw new Exception("Please make sure that you enter correct word for the captcha below");
						}
					}
		
				}
			}
		}
		catch (Myshipserv_Enquiry_Exception $e)
		{
			$errors['top'][] = $e->getMessage();
		}
		catch (Exception $e)
		{
			$errors['top'][] = $e->getMessage();
		}
		
		$this->view->showFileUploads = $showFileUploads;
		$this->view->formValues      = $formValues;
		$this->view->header          = $header;
		$this->view->message         = $message;
		$this->view->errors          = $errors;
		$this->view->isPPGSupplier 	 = $this->isPPGCompanies($supplierBasket);
		$config = Zend_Registry::get('options');
		$this->view->basketCookie = $config['shipserv']['enquiryBasket']['cookie'];
		
		// enable captcha when user's trying to send RFQ for the 3rd time
		if( $user = Shipserv_User::isLoggedIn() )
		{
			if( $spamChecker->checkTotalRFQSentPerDay($user, $formValues)>= 2 )
			{
				$this->view->captchaId = $captcha->generate();
				$this->view->captcha = $captcha;
			}
		}
	}
}