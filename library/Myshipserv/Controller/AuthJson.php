<?php
/**
 * When you need your JSON controller to rely on CAS 
 */   
trait Myshipserv_Controller_AuthJson
{
    protected static $isLoggedIn = false;

    use  Myshipserv_Controller_Json
    {
        Myshipserv_Controller_Json::init as initJson;
    }

    /**
     * If the force logged in param is set, it will require the user to be loggged in.
     *
     * @param bool $forceLoggedIn
     * @throws Zend_Controller_Response_Exception
     */
    public function init($forceLoggedIn = true, $allowCrossOrigin = false)
    {

        self::$isLoggedIn = Shipserv_User::isLoggedIn();

        if ($forceLoggedIn === true &&  self::$isLoggedIn === false) {
            $this->_replyJsonError(new Myshipserv_Exception_JSONException("You are not logged in", 1), 404);
        } else {
            self::$isLoggedIn = true;
        }

        $this->initJson();

        if ($allowCrossOrigin === true) {
            header("Access-Control-Allow-Origin: *");
        } else {
            header('Access-Control-Allow-Origin: ' . Myshipserv_Config::getApplicationUrl());
        }
        
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");
        header("Content-type: application/json; charset=utf-8");

        if ($this->getRequest()->isOptions()) {
            // do not proceed if it is a pre-flight request send by cross-domain AJAX
            // a proper GET/POST request with the same params will follow
            $this->_replyJsonEnvelope("OK");
            exit();
        }
    }
}