<?php
/**
 * A trait to handle CAS authentication via ShipServ Pages app
 *
 * Use this trait in a controller that you want to be protected by CAS login
 *
 * @author  Yuriy Akopov (Modified by Attila O for Pages, Original source from Match App)
 * @date    2015-01-22
 * @story   S12490
 * 
 * @TODO I beleive this should be refactored using new CAS implementation
 * still works as the compatibility session management exists
 * 
 */
include_once('CAS/CAS.php');

trait Myshipserv_Controller_Auth
{

    protected static $casIsInitialised = false;
    protected static $isLoggedIn = false;

    /**
    * Initalise CAS client library
    * @return unknown
    */
    protected static function _initCas()
    {

        if (strstr($_SERVER['REQUEST_URI'], 'cas-auth-check?method=iframe') === false) {
            setcookie('ra', null, time()-1000, '/');
        }

        $config = Zend_Registry::get('config');

        if (self::$casIsInitialised === false && Myshipserv_CasControl::getInstance()->getCasClientCalled() === false) {

            phpCAS::client(
                SAML_VERSION_1_1,
                $config->shipserv->services->sso->cas->host,
                (int) $config->shipserv->services->sso->cas->port,
                $config->shipserv->services->sso->cas->context
            );

            // check if verbose logging is enabled
            if ($config->shipserv->services->sso->cas->verboseLogging == 1) {
                phpCAS::setDebug('/prod/logs/pages/cas/verbose-' . date('Y-m-d') . '.log');
            }
            phpCAS::setNoClearTicketsFromUrl();
            phpCAS::setNoCasServerValidation();

            phpCAS::handleLogoutRequests(true);
            phpCAS::setNoClearTicketsFromUrl();

            self::$casIsInitialised = true;
            Myshipserv_CasControl::getInstance()->setCasClientCalled();
        }

    }

    /**
    * On initi check if we are loggen in
    * @return unknown
    */
    public function init()
    {
        self::_initCas();
        if (Shipserv_User::isLoggedIn() === false) {
            $this->_replyJsonError(new Myshipserv_Exception_JSONException("You are not logged in", 1), 404);
            self::$isLoggedIn = false;
        } else {
            self::$isLoggedIn = true;
        }
    }
}