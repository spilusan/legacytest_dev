<?php
/**
 * Throw this exception if you want you message to be displayed nicely to user on error screen
 */
class Myshipserv_Exception_JSONException extends Exception  {
	
	public $errorCode;
	// adding extra parameter
	function __construct($message, $code)
	{
		$this->errorCode = $code;
		$this->message = $message;	
	}
}
?>