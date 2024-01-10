<?php
/**
* Extend Zend_Session_Namespace to automatically serialise objects and 
* unserialize them to protect other apps, like Match engine who are
* sharing sessions to automatically initalize non existing objects
* Serialisation can be turned off and on by adding a new parameter
*/
class Myshipserv_Zend_Session_Namespace extends Zend_Session_Namespace
{

	private $_serialize;

	/**
	* Override original constructor, add parameter to turn on and off serialize
	* @param string  $name      Name of session namespace, default if not set
	* @param boolean $serialize Set serialize on of off
	*
	* @return object
	*/
	public function __construct($name = null, $serialize = true)
	{
		$this->_serialize = $serialize;
		if ($name !== null) {
			parent::__construct($name);
		} else {
			parent::__construct();
		}
		
	}

	/**
	* Override magic __set method to check if we are trying to push an object into the session,
	* and serialize it, if serialization turned on
	* @param string $name  Name of the session variable
	* @param mixed  $value Value to store in session
	*
	* @return unknown
	*/
	public function __set($name, $value)
	{
		if ($this->_serialize === true) {
			if (is_object($value) === true) {
				parent::__set($name, serialize($value));
			} else {
				parent::__set($name, $value);
			}
		} else {
			parent::__set($name, $value);
		}
	}

	/**
	* Override magic method of __get to unserialize object, if it is stored as a
	* serialized object, and function is turned on
	* @param string $name name of the parameter to retrive, If it is a serialised object, and function on, it will be unserialised automatially
	* 
	* @return mixed
	*/
	public function & __get($name)
	{
		if ($this->_serialize === true) {
			$data = parent::__get($name);
			if ($this->isSerialized($data) === true) {
				return unserialize($data);
			} else {
				return $data;
			}
		} else {
			return parent::__get($name);
		}
	}
	
	/**
	* Check if the string is a serialized onbject, 
	* @param mixed $data the variable, can contain any format
	*
	* @return bool 
	*/
	protected function isSerialized($data)
	{
	    // if it isn't a string, it isn't serialized
	    if (!is_string($data)) {
	    	return false;
	    }

	    $data = trim($data);
	    if ('N;' == $data) {
	    	return true;
	    }

	    if (!preg_match('/^([adObis]):/', $data, $badions)) {
	    	return false;
	    }

	    switch ( $badions[1] ) {
	        case 'a' :
	        case 'O' :
	        case 's' :
	            if ( preg_match("/^{$badions[1]}:[0-9]+:.*[;}]\$/s", $data)) {
	            	return true;
	            }
	                
	            break;
	        case 'b' :
	        case 'i' :
	        case 'd' :
	            if (preg_match("/^{$badions[1]}:[0-9.E-]+;\$/", $data)) {
	            	return true;
	            }
	            break;
	    }

	    return false;
	}

}