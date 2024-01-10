<?php

class ErrorController extends Myshipserv_Controller_Action 
{

	/**
	 * Error handling
	 * 
	 * [EL] Note: - if request type is AJAX request, the error will be displayed in 
	 * 			  	JSON variable, otherwise it'll use exception's view (requested by Kevin)
	 * 			  - new http response code added to the exception, developer can now specify
	 * 				which status code needs to be given, this will defaulted to 500 if not specified
	 * 				example: throw new Exception("Invalid parameter", 404)
	 */
	public function errorAction() 
	{
		$errors = $this->_getParam('error_handler');
		$this->view->errorCode = $errors->exception->getCode();
		$errorMessage = $errors->exception->getMessage();
		$translateTo404 = array(
				'Invalid controller specified',
				'does not exist and was not trapped in'
		);
		foreach ($translateTo404 as $translateText) {
			if (strpos($errorMessage, $translateText) !== false) {
				$this->view->errorCode = 404;
			}
		}
		switch ($errors->type) {
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
			case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:

				// 404 error -- controller or action not found
				$this->getResponse()->setHttpResponseCode(404);
				$this->view->message = 'Page not found';
				break;

			default:
				//We dont need to monitor Messaged Exceptions as they are for the user to see rather than Sysadmins. 
				if (get_class($errors->exception) != "Myshipserv_Exception_MessagedException") 
				{
					$sysAlert = new Myshipserv_SystemAlerts_SystemAlert();
					$sysAlert->recordError($errors->exception, $errors->request);
					$this->view->displayErrorDetails = $sysAlert->displayError;
				}

				$request = new Zend_Controller_Request_Http();
				$this->view->userError = false;
				// application error
				if (get_class($errors->exception) == "Myshipserv_Exception_MessagedException") 
				{
					
					if ($errors->exception->errorCode != "")
						$this->getResponse()->setHttpResponseCode($errors->exception->errorCode);

					if ($request->isXmlHttpRequest()) 
					{
						if( $_SERVER['APPLICATION_ENV'] == "production" )
						{
							$this->_helper->json((array)array("error" => ""));
						}
						else
						{
							$this->_helper->json((array)array("error" => $errorMessage));
						}
					} 
					else 
					{
						$this->view->userError = true;
						$this->view->message = $errorMessage;
						$this->view->displayMessage = $errorMessage;
					}
					
					// processing paramsForView
					foreach( (array) $errors->exception->paramsForView as $var => $value)
					{
						
						$this->view->$var = $value;	
					}
					
				} 
				else if (get_class($errors->exception) == "Myshipserv_Exception_JSONException") 
				{
					if ($errors->exception->errorCode != "")
						$this->getResponse()->setHttpResponseCode($errors->exception->errorCode);
					else
						$this->getResponse()->setHttpResponseCode(500);

					if( $_SERVER['APPLICATION_ENV'] == "production" )
					{
						$this->_helper->json((array)array("error" => ""));
					}
					else
					{
						$this->_helper->json((array)array("error" => $errorMessage));
					}
					
					$this->view->message = $errorMessage;
					$this->view->displayMessage = $errorMessage;
				}
				else 
				{
				    if ($errors->exception instanceof Exception) {
				        $message = $errorMessage;
				    } else {
				        $message = 'ErrorController::errorAction found a non exception as property $errors->exception. Unable to show any proper message.';
				    }
					$this->getResponse()->setHttpResponseCode(500);
					if ($request->isXmlHttpRequest())
					{
						if( $_SERVER['APPLICATION_ENV'] == "production" )
						{
							$this->_helper->json((array)array("error" => ""));
						}
						else
						{
							$this->_helper->json((array)array("error" => $message));
						}
					}
					else
					{
						$this->view->message = $message;
					}
				}

				break;
		}

		$this->view->exception = $errors->exception;
		$this->view->request = $errors->request;
		if( $this->view->user === null )
		$this->view->user = Shipserv_User::isLoggedIn();
	}
	
	/*
	 * Raised, when the user has no full acccess 
	 * 
	 */
	public function membershipLevelAccessErrorAction()
	{
		$this->view->menu = $this->getRequest()->getParam('menu', '');
	}


}
