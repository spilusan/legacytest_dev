<?php
/**
 * Controller to Self Service Access Code Retrieval
 *
 */
class Selfservice_ReminderController extends Myshipserv_Controller_Action
{
    public function init()
    {
      parent::init();
    }

    public function indexAction()
    {
		$user = Shipserv_User::isLoggedIn();
		$this->view->user = $user;
		
		$config = Zend_Registry::get('options');
		$db     = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->params;

		$message = '<p>Please enter the email registered with ShipServ to retrieve your access code</p>';
		$errors = null;
		$display = "reminderForm";

		if ($this->getRequest()->isPost())
		{
			
			switch ($params["hdnAction"])
			{
				case "requestAccessCode":
					$accessCodeReminderForm = new Myshipserv_Form_Accesscodereminder();
					if ($accessCodeReminderForm->isValid($params))
					{
						$suppliersAdapter = new Shipserv_Oracle_Suppliers($db);
						$countriesAdapter = new Shipserv_Oracle_Countries($db);
						$suppliers = $suppliersAdapter->fetchAccessCodesByEmail($params["email"]);
						if (count($suppliers) > 0)
						{
							$emailText = "";
							foreach ($suppliers as $supplier)
							{
								if (!empty($supplier["ACCESS_CODE"]))
								{
									$location = array();
									if (trim($supplier["SPB_CITY"])!="") $location["SPB_CITY"] = $supplier["SPB_CITY"];
									if (trim($supplier["SPB_STATE_PROVINCE"])!="") $location["SPB_STATE_PROVINCE"] = $supplier["SPB_STATE_PROVINCE"];
									if (trim($supplier["SPB_REGION"])!="") $location["SPB_REGION"] = $supplier["SPB_REGION"];
									if (trim($supplier["SPB_COUNTRY"])!="")
									{
										$country = $countriesAdapter->fetchCountryByCode($supplier["SPB_COUNTRY"]);
										$location["SPB_COUNTRY"] = $country[0]["CNT_NAME"];

									}

									$emailText .= "Company Name: " . $supplier["SPB_NAME"] . " <br>";
									$emailText .= "TradeNet ID: " . $supplier["SPB_BRANCH_CODE"] . " <br>";
									$emailText .= "Location: " . implode (", ",$location) . " <br>";
									$emailText .= "Access Code: " . $supplier["ACCESS_CODE"] . " <br>";
									$emailText .= "Access Link: <a href='http://www.shipserv.com/pages/admin/selfService/access-code-input.jsf?accessCode=" . urlencode($supplier["ACCESS_CODE"]) . "'>http://www.shipserv.com/pages/admin/selfService/access-code-input.jsf?accessCode=" . urlencode($supplier["ACCESS_CODE"]) . "</a> <br><br>";
								}
								
							}
							if ($emailText != "")
							{
								$emailText = "Dear ". $params["email"] .",<br>
We received a request for your access code to your Pages listing(s). Here is the listing or listings we found which are associated to your email address: <br><br>" . $emailText;
								$emailText .= "

If you have problems with the above link". ((count($supplier)>1)?"s":"") .", please try the following: <br>
- Go to this page: <a href='http://www.shipserv.com/pages/admin/selfService'>http://www.shipserv.com/pages/admin/selfService</a> <br>
- Copy and paste an access code into the provided field <br>
- Click 'Edit Listing' <br><br>

If you didn't request for your access code, pls report this incident to us.<br>
Thanks,<br>
Shipserv Pages<br>
<a href='mailto:support@shipserv.com'>support@shipserv.com</a><br>";
								$mail = new Zend_Mail();
								$mail->setBodyHtml($emailText)
										->setFrom('support@shipserv.com', 'ShipServ Pages')
										->addTo($params["email"], $params["email"])
										//->addTo('umaroz@shipserv.com', 'umaroz@shipserv.com')
										->setSubject('ShipServ Pages Access Code Reminder')
										->send();
								$message = '<p>An email reminder with your Access Code was sent to '.$params["email"].'. You should receive it shortly.</p>';
								$display = "";
							}
							//we have not found records that have access codes - send to manual request
							else
							{
								$this->view->formValues = $params;
								$message = '<p>Sorry, we could not locate any records that match your provided email. Please complete the form below and our support team will send you access code.</p>';
								$display = "emailToSupportForm";
							}
						}
						else
						{
							$this->view->formValues = $params;
							$message = '<p>Sorry, we could not locate any records that match your provided email. Please complete the form below and our support team will send you access code.</p>';
							$display = "emailToSupportForm";
						}
					}
					else
					{
						$errors = $accessCodeReminderForm->getMessages();
						$this->view->formValues = $params;
					}
					break;
				case "emailToSupport":
					$accessCodeSupportRequest = new Myshipserv_Form_Accesscodesupportrequest();
					if ($accessCodeSupportRequest->isValid($params))
					{

						$emailText = "The following information was submitted with request for an access code for Pages Self-Service Admin \n\n";
						$emailText .= "Email: ". $params['email'] ."\n";
						$emailText .= 'Company Name: '.$params['companyName']."\n";
						$emailText .= 'TNID: '. $params['tnid'] . "\n";
						$emailText .= 'Contact Name: '.$params['contactName']. "\n" ;
						$emailText .= 'Position: '.$params['contactPosition'] . "\n";
						$emailText .= 'Contect Phone: '.$params['contactPhone']."\n";
						$emailText .= 'Country: '.$params['country']. "\n";
						$mail = new Zend_Mail();
						$mail->setBodyText($emailText)
								->setFrom($params["email"], $params["email"])
								->addTo('support@shipserv.com', 'ShipServ Pages')
								//->addTo('umaroz@shipserv.com', 'umaroz@shipserv.com')
								->setSubject('Please send me an access key reminder.')
								->send();
						$message = '<p>Thank you for sending your request. Our support team will promptly review your details and will get back to you.</p>';
						$display = "";
					}
					else
					{
						$display = "emailToSupportForm";
						$errors = $accessCodeSupportRequest->getMessages();
						$this->view->formValues = $params;
					}
					break;
				default:
					break;
			}
		}

		$this->view->display = $display;
		$this->view->errors = $errors;
		$this->view->message = $message;
    }

}