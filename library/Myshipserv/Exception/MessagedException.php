<?php
/**
 * Throw this exception if you want you message to be displayed nicely to user on error screen
 */
class Myshipserv_Exception_MessagedException extends Exception  {
	
	public $errorCode;
	
	// adding extra parameter
	// @author elvir
	function __construct($message, $code = "", $paramsForView = null)
	{
		$this->errorCode = $code;
		$this->message = $message;	
		$this->paramsForView = $paramsForView;
	}
	
}
?>
