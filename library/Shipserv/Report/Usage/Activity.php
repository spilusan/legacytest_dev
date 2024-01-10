<?php
/**
* Make a single instance class for user activity
* getting the names per activity type
*/
class Shipserv_Report_Usage_Activity
{
    private static $_instance;
    private $_activity;
    
    /**
    * Get single instance
    * @return object
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
    protected function __construct()
    {
    	$this->_activity = new Shipserv_User_Activity();
    }

	/**
	* Protected classes to prevent creating a new instance 
	* @return object
	*/
    private function __clone()
    {

    }

    /**
    * translate the activity type to a readable title
    * @param string $name The name of activity
    * @return string
    * @throws error if not exits
    */
    public function translate($name)
    {
    	return $this->_activity->translate($name);
    }
	/**
	* Getting the events by group
	* @param string $groupName Name of event group
	* @return array
	* @throws Myshipserv_Exception_MessagedException
	*/
    public function getEventsByGroupName($groupName)
    {
    	return $this->_activity->getEventsByGroupName($groupName);
    }
    
}