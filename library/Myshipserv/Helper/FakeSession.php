<?php
/**
 * This is a fake session, so when the session is turned off for non logged in pages, we have a temproary memory storage
 * to emulate the Myshipserv_Zend_Session_Namespace
 */

class Myshipserv_Helper_FakeSession
{

    protected $values = array();

    /**
	* This var containing the instance 
	*
	* @var object
	*/
    private static $_instance;

    /**
	* Singleton 
	* 
	* @return Myshipserv_CAS_CasRest
	*/
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }         
        return static::$_instance;
    }

    /**
	 * Protected classes to prevent creating a new instance 
	 * @return object
	*/
    protected function __construct() {}
    private function __clone() {}

    /**
     * Magic function to get temporary data
     * 
     * @param string @key
     * 
     * @return string
     */
    public function __get($key)
    {
        return $this->values[$key] ?? null;
    }

    /**
     * Magic function to set temporary data
     * 
     * @param string @key
     * @param string @value
     * 
     * @return null
     */
    public function __set($key, $value)
    {
        $this->values[$key] = $value;
    }
}