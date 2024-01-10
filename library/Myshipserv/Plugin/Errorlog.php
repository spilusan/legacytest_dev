<?php

class Myshipserv_Plugin_Errorlog extends Zend_Controller_Plugin_Abstract
{
    /**
     * A unique id to identify this http request, useful when grepping into logs
     * @var Int $uniqid
     */
    public static $uniqid = null;
    
    
    /**
     * Declare the handlers
     */
    public function __construct()
    {
        self::$uniqid = uniqid();
        if (!Myshipserv_Config::isInProduction()) {
            //set_error_handler('Myshipserv_Plugin_Errorlog::catchError', E_WARNING | E_NOTICE | E_ERROR | E_USER_ERROR | E_USER_NOTICE | E_USER_WARNING);
            set_error_handler('Myshipserv_Plugin_Errorlog::catchError', E_WARNING | E_ERROR | E_USER_ERROR | E_USER_WARNING);
        } else {
            set_error_handler('Myshipserv_Plugin_Errorlog::catchError', E_ERROR | E_USER_ERROR);
        }
        
        register_shutdown_function('Myshipserv_Plugin_Errorlog::checkFatalError');
    }
        
    
    /**
     * Inherit Zend plugin postDispatch
     * 
     */
    public function postDispatch(Zend_Controller_Request_Abstract $request)
    {
        $response = $this->getResponse();
        if (($response->isException())) {
            $message = '';
            foreach ($response->getException() as $exception) {
                $message .= str_replace("\n", "||", $exception);
            }
            self::_generateLog('error', $message);
        }
    }
    
    
    /**
     * The callback function declared in set_error_handler
     * 
     * @param Int $errno
     * @param Str $errstr
     * @param String $errfile
     * @param Int $errline
     * @param String $errcontext
     * @return boolean
     */
    static function catchError($errno, $errstr, $errfile, $errline, $errcontext = '')
    {
        $code = array(
            E_WARNING => 'warning',
            E_NOTICE => 'notice',
            E_ERROR => 'error',
            E_CORE_ERROR => 'core_error',
            E_PARSE => 'parse_error',
            E_USER_ERROR => 'user_error',
            E_USER_NOTICE => 'user_notice',
            E_USER_WARNING => 'user_warning'
        );
        
        $context = '';
        if ($errno === E_WARNING || $errno === E_NOTICE) {
            $stack = array();
                //var_dump(array_reverse(debug_backtrace(0)));
            foreach (debug_backtrace(0) as $key => $trace) {
                if (isset($trace['file'])) {
                    $stack[] = "#$key " . $trace['file'] . '(' . $trace['line'] . ')';
                }
            }
            $context .= '||' . implode('||', $stack);
        } else {
            $errcontext = (array) $errcontext;
            if (count($errcontext)) {
                $context = '||' . str_replace("\n", '||', print_r($errcontext, true));
            }            
        }
        
        self::_generateLog($code[$errno], "$errstr in $errfile($errline)" . $context);
        
        return true;
    }
    
    
    /**
     * The callback function declared in register_shutdown_function
     */
    static function checkFatalError()
    {
        $error = error_get_last();
        if ($error === null) {
            return;
        }

        $errno = $error['type'];
        $errstr = $error['message'];
        $errfile = $error['file'];
        $errline = $error['line'];
        
        if ($errno === E_ERROR || $errno === E_COMPILE_ERROR) {
            self::_generateLog('error', "$errstr in $errfile($errline))");
        }
    }
    
    
    /**
     * Collect all useful context info from $_SERVER, $_REQUEST and the $message itself to then log down a structured messaeg with err_log php function
     * 
     * @param String $level  the error log level
     * @param String $message  the error message
     */
    private static function _generateLog($level, $message)
    {
        //When loading helpers with Zend getHelper(), zend is looking both in Myshipserv and Shipserv helper path. When searching in either the first or second one, an error message will be raised
        //Solutions: definitely with some coding here and there can be fixed. Maybe fixed in newere Zend versions? Good thing would be to merge Myshipserva dn Shipserv together. For the moment this is a workaround 
        if (strpos($level, 'error') === false && strpos($message, 'failed to open stream: No such file or directory in')) {
            return;
        }
        $errorLevel = "[error_level $level] ";
        $realIp = '[real_client '. (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : '') . '] ';
        $uri = '[url ' . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '') . '] ';
        $previousUri = '[referer ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '') . '] ';
        $userAgent = '[user_agent ' . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '') . '] ';            
        $method = '[method '.$_SERVER['REQUEST_METHOD'].'] ';
        $parameters = '[parameters ' . ((is_array($_REQUEST) && count($_REQUEST) > 0)? http_build_query($_REQUEST) : '') . '] ';
        $tnid = '[tnid ' . (isset($_SESSION['userActiveCompany']) && isset($_SESSION['userActiveCompany']['id'])? $_SESSION['userActiveCompany']['id'] : null) . '] '; //should be the quicker and safer way for this logger to get the tnid
        $username = '[username ' . (isset($_SESSION['phpCAS']) && isset($_SESSION['phpCAS']['user'])? $_SESSION['phpCAS']['user'] : null) . '] '; //should be the quicker and safer way for this logger to get the id
        //$cookie = '[cookie ' . json_encode($_COOKIE) . '] ';
        $errors = '[error_message ' . $message . '] ';
        $reqid = '[reqid ' . self::$uniqid . '] ';
        $log = $errorLevel . $realIp . $uri . $method . $parameters . $previousUri . $userAgent . $tnid . $username . /*$cookie .*/ $errors . $reqid;
        error_log($log);
    }
}