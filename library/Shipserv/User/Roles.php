<?php
class Shipserv_User_Roles extends Shipserv_Object
{
    private static $_instance;
    
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }
        
        return static::$_instance;
    }

    /**
     * Protected classes to prevent creating a new instance 
     */
    protected function __construct()
    {
    }

    private function __clone()
    {
    }

    /**
    * Get the user roles using oracle package
    * @param string $userName
    * @return array
    */
    public function getUsrRoles($userName)
    {
		$return = array();
    	$result = $this->_getRolesFromDb($userName);
    	//We have to convert the array format to a flattened array format, as Oracle returns different way
    	if (is_array($result)) {
    		foreach ($result as $key => $value) {
    			if (array_key_exists('GROUP_NAME', $value)) {
    				array_push($return, $value['GROUP_NAME']);
    			}
    		}
    	}

    	return $return;
    }

    /**
    * Get SYS_REFCURSOR result of oracle package "get_user_roles"
    * @return array;
    */
	protected function _getRolesFromDb($userName)
	{
		//create connection
        $sql = 'pkg_user_security.get_user_roles(:username)';
        $params = array('username' => $userName);
        return Shipserv_Helper_Database::executeOracleFunctionReturningCursor($conn = $this->getDb(), $sql, $params);
	}

}

