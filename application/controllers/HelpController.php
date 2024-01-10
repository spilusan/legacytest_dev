<?php

/**
 * Controller for help section. 
 * Most of the actions are just redirecting to the new help section hosted in WP, that we intergated in Jan 2016
 * A couple of actions are here for backward compatibility
 *  
 */
class HelpController extends Myshipserv_Controller_Action
{
	const CHUNK_SIZE=1048576;

	public function init()
	{
		parent::init();
	}


	public function preDispatch()
	{
		parent::preDispatch();

		$this->view->params = $this->_getAllParams();

		$request = Zend_Controller_Front::getInstance()->getRequest();
		$this->view->section = $request->getActionName();
		$this->view->subsection = $this->_getParam('subsection');
	}

	public function buyerfaqAction() {
	    $this->redirect('/info/help');
	}
	
	public function buynowAction() {
	    $this->redirect('/info/help');
	}
	
	public function contactAction() {
	    $this->redirect('/info/contact-us');
	}
	
	public function premiumAction() {
	    $this->redirect('/info/help');
	}
	
	public function basicAction() {
	    $this->redirect('/info/help');
	}
	
	public function sellerfaqAction() {
	    $this->redirect('/info/help');
	}
	
    public function newFeaturesHelpAction() {
        $this->redirect('/info/help');
    }

        
    public function ssoAction() {
        $this->redirect('/info/help');
    }


    /**
     * What the hell is this?
     */    
	protected function executeCronAction()
	{
		$result = [];
		$remoteIp = Myshipserv_Config::getUserIp();
		if (!$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
			throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 401);
		}

		$oldTimeOut = ini_set('max_execution_time', 0);

		ob_start();

		if( $this->params['method'] == 'monthly-billing-report-sync-to-salesforce' )
		{
			$logger = new Myshipserv_Logger;
			$logger->outputToBuffer = true;

			if( $this->params['year'] != "" && $this->params['month'] != "" && $this->params['month'] > 0 && $this->params['month'] < 13)
			{
				$randomiseBillingReportValue = ( $this->params['random'] != "" && $this->params['random'] == 'true' );
    			$job = new Myshipserv_Salesforce_Report_Billing($this->params['month'], $this->params['year'], $randomiseBillingReportValue);
    		}
			else
			{
				throw new Myshipserv_Exception_MessagedException('Please specify month and year: /help/execute-cron?method=monthly-billing-report-sync-to-salesforce&month=1&year=2013');
			}

			$job->upload();
			echo print_r($result, true);
			$logger->outputToBuffer = false;

		}
		else if( $this->params['method'] == 'pull-vbp-transition-date' )
		{
			$app = new Myshipserv_Salesforce_Supplier();
			$result = $app->pullVBPTransitionDate();
			echo print_r($result, true);
		}
		else if( $this->params['method'] == 'sync-contracted-vessel' )
		{
			$app = new Myshipserv_Salesforce_ContractedVessel();
			$result = $app->start();
			echo print_r($result, true);
		}
		else if( $this->params['method'] == 'email-hype' )
		{
			$generator = new Myshipserv_EmailCampaign_UpsellNonPayingSupplier_Generator();
			$generator->generate();
			echo print_r($result, true);
		}
		else
		{
			throw new Myshipserv_Exception_MessagedException('Invalid parameter');
		}

		$output = ob_get_contents();
		ob_end_clean();
		ini_set('max_execution_time', $oldTimeOut);

		echo str_replace("\n", "<br />", $output);
		die();
	}


	/**
	 * What the hell is this? 
	 */
	public function proxyAction()
	{
		$url = $this->view->uri()->deobfuscate($this->params['u']);

		if (strpos($url, 'http') === false) {

			$baseUrl = Myshipserv_Config::getCorporateSiteUrl();
			$urlDetails = parse_url($baseUrl);
			$url = $urlDetails['scheme'].'://' . $_SERVER['HTTP_HOST'] . $url;
		}

		echo file_get_contents($url);
		die();
	}


}
