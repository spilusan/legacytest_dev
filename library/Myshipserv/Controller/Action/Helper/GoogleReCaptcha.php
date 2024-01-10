<?php


/**
 * ShipServ integration of Google ReCaptcha (I'm not a robot magic checkbox)
 * https://developers.google.com/recaptcha/docs
 */
class Myshipserv_Controller_Action_Helper_GoogleReCaptcha extends Zend_Controller_Action_Helper_Abstract
{
    const SITE_KEY = '6LctdSUTAAAAACv_vvV1k5xPidolUnO__ZRYmGwM';
    const SECRET_KEY = '6LctdSUTAAAAAP5qmPYiuyX7A1apqUNYhNjHJpnG';
    const VERIFY_WS = 'https://www.google.com/recaptcha/api/siteverify';
    
    /**
     * https://developers.google.com/recaptcha/docs/verify
     * 
     * @param String $reponse the g-recaptcha-response post param value
     * @return Bool true if verification succeed, false if did not 
     */
    public function verifyUserResponse($reponse) 
    {
        //Post to Google
        $httpClient = new Zend_Http_Client();
        $httpClient
        ->setUri(self::VERIFY_WS)
        ->setParameterPost('response', $reponse)
        ->setParameterPost('secret', self::SECRET_KEY)
        ->setMethod(Zend_Http_Client::POST);
        
        //Handle unexpected errors
        try {
            $output = $httpClient->request();
            if ($output->isError()) {
                throw new Zend_Http_Client_Exception(
                    sprintf(
                        'POST to https://www.google.com/recaptcha/api/siteverify with g-recaptcha-response="%s" failed with http status %s and message %s',
                        $this->getRequest()->getParam('g-recaptcha-response'),
                        $output->getStatus(), $output->getMessage()
                    )
                );
            }
            $response = Zend_Json::decode($output->getBody());
        } catch(Exception $e) {
            trigger_error('Failing to post captcha validation to Google: ' . (String) $e, E_USER_WARNING);
            return false;
        }
        
        //Check Google response
        if (!$response['success']) {
            return false;
        }
        return true;
    }
}
