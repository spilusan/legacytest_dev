<?php
/**

 * This class is only to be used to generate exceptions to test functionality of the system alerts system. IT IS NOT TO BE DEPLOYED TO LIVE!
 * 
 */

class Exceptiongenerator_IndexController extends Myshipserv_Controller_Action
{
    public function init(){
        parent::init();
    }
    
    public function indexAction(){
        
        $params = $this->params;
        
        switch ($params['throw']) {
            case 1:
                throw new Exception("This is exception example 1", "111111");
                break;
            case 2:
                $exception = new Myshipserv_Exception_MessagedException("This is a Myshipserv messaged exception example");
                $exception->errorCode = 212;
                throw $exception;
                break;
            case 3:
                throw new Myshipserv_Exception_NotLoggedIn("This is a 'Not Logged In' exception", "1000001");
                break;
            case 4:
                throw new Zend_Db_Exception();
                break;
            case 5:
                throw new Zend_Controller_Exception();
                break;
            default:
                throw new Exception("Exception number " . $params['throw'], $params['throw']);
                break;
        }
        
        
    }
    
}