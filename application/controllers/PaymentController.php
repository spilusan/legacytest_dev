<?php
// commented out by Yuriy Akopov on 2016-06-08, S16162 as Salesforce library is moved to composer
// require_once ('/var/www/libraries/SalesForce/SforceEnterpriseClient.php');
/**
 * Controller for payment actions
 *
 * @package myshipserv
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class PaymentController extends Myshipserv_Controller_Action
{

	public function init()
	{
		parent::init();
	}

	public function indexAction()
	{
		// $config   = Zend_Registry::get('options');
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->params;

		$paymentGateway = new Shipserv_Adapters_SagePay();
		$paymentGateway->runWithoutInvoice();
		// $mySforceConnection = new SforceEnterpriseClient();  // S16162 commented as never used

		// @todo: commented by Yuriy Akopov because of redundancy
		/*
		$record = $records[0];

		$nameSplit = explode(" ",$record->Billing_Contact__c);
		$firstName = array_shift($nameSplit);
		$lastName = implode(" ", $nameSplit);
		*/

		$currency = new Shipserv_Oracle_Currency;
		$this->view->currencies = $currency->fetchAll();

		$paymentGateway->setParams(
			array(
				"BillingCity"			=> $this->params['BillingCity'],
				"BillingCountryName"	=> $this->params['BillingCountry'],
				"BillingPostCode"		=> $this->params['BillingPostCode'],
				"BillingAddress1"		=> $this->params['BillingAddress1'],
				"BillingAddress2"		=> $this->params['BillingAddress2'],
				"CustomerName"			=> $this->params['CustomerName'],
				"CustomerEMail"			=> $this->params['CustomerEMail'],
				"BillingFirstnames" 	=> $this->params['BillingFirstnames'],
				"BillingSurname" 		=> $this->params['BillingSurname'],
				"Currency" 				=> $this->params['BillingCurrency'],
				"NetAmount" 			=> $this->params['TotalTransaction'],
				"Amount" 				=> $this->params['TotalTransaction'],
				"Tax" 					=> $this->params['Tax'],
				"InvoiceId" 			=> $this->params['Invoice']
			)
		);
		$paymentGateway->setParams($params);

		$countriesAdapter = new Shipserv_Oracle_Countries($db);
		$this->view->countries = $countriesAdapter->fetchAllCountries();

		// Getting only US states
        $usStatesAdapter = new Shipserv_Oracle_States($db);
        $usStates = $usStatesAdapter->fetchStateByCountryCode('US');
        $this->view->usStates = $usStates;

		$this->view->paymentGateway = $paymentGateway;
	}

	public function invoiceAction()
	{
		// $config   = Zend_Registry::get('options');
		$db = $this->getInvokeArg('bootstrap')->getResource('db');
		$params = $this->params;

		if (intval($params["sfiid"]) > 0) {
			//recover invoice id
			$obscuredId = intval($params["sfiid"]);
			$multiplier = $obscuredId % 100;

			if ((intval(floor($obscuredId/100)) % $multiplier) != 0) {
				throw new Myshipserv_Exception_MessagedException("Sorry, this invoice is not available.");
			}

			$invoiceId = intval(intval(floor($obscuredId/100)) / $multiplier);

			$paymentGateway = new Shipserv_Adapters_SagePay();

			// S16162: a  fix by Yuriy Akopov to switch to SalesForce credentials and paths in the config
			$salesForceBase = new Myshipserv_Salesforce_Base();
			$salesForceBase->initialiseConnection();
			$mySforceConnection = $salesForceBase->getSalesForceConnection();

			/*
			$mySforceConnection = new SforceEnterpriseClient();
			$config = new Myshipserv_Config();
			$mySoapClient = $mySforceConnection->createConnection('/var/www/libraries/SalesForce/enterprise.wsdl.xml');
			$mylogin = $mySforceConnection->login("guest@shipserv.com", "1Visitor4S");
			*/

			$query = "
				SELECT
					Name,
					Tax__c,
					CurrencyIsoCode,
					Gross__c,
					Net__c,
					Account__c,
					Billing_contact__c,
					SysBlockWebAccess__c,
					Account__r.Name,
					Account__r.BillingStreet,
					Account__r.BillingCity,
					Account__r.BillingCountry,
					Account__r.BillingPostalCode,
					Account__r.Billing_contact_e_mail__c
				FROM
					Invoicex__c
				WHERE
					Invoicex__c.Name = '" . $invoiceId . "'"
			;
			$queryResult = $mySforceConnection->query($query);
			$records = $queryResult->records;

			if (count($records)==1) {
				$record = $records[0];

				//check if access to this invoice is not blocked
				if ($record->SysBlockWebAccess__c == "1") {
					throw new Myshipserv_Exception_MessagedException("This invoice is not available.");
				}

				//check if net value is not negative
				if (floatval($record->Net__c) < 0) {
					throw new Myshipserv_Exception_MessagedException("This invoice cannot be accessed for payment.");
				}
				
				$nameSplit = explode(" ", $record->Billing_Contact__c);
				$firstName = array_shift($nameSplit);
				$lastName = implode(" ", $nameSplit);
				
				$paymentGateway->setParams(
					array(
						"BillingCity"           => $record->Account__r->BillingCity,
						"BillingCountryName"    => $record->Account__r->BillingCountry,
						"BillingPostCode"       => $record->Account__r->BillingPostalCode,
						"BillingAddress1"       => $record->Account__r->BillingStreet,
						"CustomerName"          => $record->Account__r->Name,
						"CustomerEMail"         => $record->Account__r->Billing_contact_e_mail__c,
						"BillingFirstnames"     => $firstName,
						"BillingSurname"        => $lastName,
						"Currency"              => $record->CurrencyIsoCode,
						"NetAmount"             => $record->Net__c,
						"Amount"                => $record->Gross__c,
						"Tax"                   => $record->Tax__c,
						"sfiid"                 => $record->Name,
						"InvoiceId"             => $invoiceId
					)
				);
			} else {
				throw new Myshipserv_Exception_MessagedException("Invoice is not found");
			}

			$paymentGateway->setParams($params);

			$countriesAdapter = new Shipserv_Oracle_Countries($db);
			$this->view->countries = $countriesAdapter->fetchAllCountries();
			$this->view->paymentGateway = $paymentGateway;

		} else {
			throw new Myshipserv_Exception_MessagedException("Invoice Id is incorrect");
		}
	}

	public function successAction()
	{

	}

	public function failureAction()
	{
		$this->view->params = $this->params;
	}



}