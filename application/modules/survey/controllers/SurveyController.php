<?php
class Survey_SurveyController extends Myshipserv_Controller_Action
{
   /**
     * Initialise the controller - set up the context helpers for AJAX calls
     * @access public
     */
    public function init()
    {
		parent::init();
        $ajaxContext = $this->_helper->getHelper('AjaxContext');
        $ajaxContext->addActionContext('index', 'html')
                    ->initContext();
    }

    /**
     * Where the interface lives
     */
    public function indexAction()
    {	
    	$params = $this->_getAllParams();
		
       	if( $params["surveyId"] == "" )
    	{
    		throw new Myshipserv_Exception_MessagedException("Invalid URL, check your Survey Id", 400);
    	}
    	
    	if( $params["email"] == "" )
    	{
    		throw new Myshipserv_Exception_MessagedException("Invalid URL, check your Email", 400);
    	}

		if($this->getRequest()->isPost() ) 
		{	
    		$survey = Shipserv_Survey::getInstanceById( $params["surveyId"] );
			$result = $survey->storeAnswers( $params );
            $this->redirect('/survey/completed', array('exit', true));
		}
		else 
		{
	    	// check hash
	    	if( md5("CSS" . $params["email"] ) != $params["c"] )
	    	{
	    		throw new Myshipserv_Exception_MessagedException("Unauthorised user", 403);
	    	}
	    	
			$survey = Shipserv_Survey::getInstanceById( $params["surveyId"] );
			$this->view->params = $params;
			$this->view->survey = $survey;
		}    
    }
    
    public function completedAction()
    {
    	
    }

    public function inviteAction()
    {
        $remoteIp = Myshipserv_Config::getUserIp();

        $config = Zend_Registry::get('config');
        
        if (!$this->isIpInRange($remoteIp, Myshipserv_Config::getSuperIps())) {
        	throw new Myshipserv_Exception_MessagedException("User not authorised: " . $remoteIp, 403);
        }
        

		$generator = new Myshipserv_CustomerSatisfactionSurveyEmail_Generator();
        $generator->debug = true;
        
        // beautify the output
        ob_start();
			$generator->generate();
            $output = ob_get_contents();
        ob_end_clean();
        
        echo str_replace("\n", "<br />", $output);
	    die();
    }
}
