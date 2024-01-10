<?php 
/**
 * Get a route signature by an URL 
 * used in navigation, to match selected menu for mobile meny auto select
 * 
 * @author attilaolbrich
 * Get the controller and action name by URL
 */
class Myshipserv_Helper_RouteByUrl
{
    /**
     * Get the signiture Controller/Action from an absolute URL like https://www.shipserv.com/profile
     * @param string $url
     * @return string
     */
    public static function getByAbsoluteUrl($url)
    {
        $request = new Zend_Controller_Request_Http($url);
        $frontController = Zend_Controller_Front::getInstance();
        $router = $frontController->getRouter();
        $result = $router->route($request)->getModuleName() . '_' .$router->route($request)->getControllerName() . '_' . $router->route($request)->getActionName();
        unset($request);
        return $result;
    }
    
    /**
     * Get the signiture form a relative URL like /profile
     * @param string $url
     * @return string
     */
    public static function getByRelativeUrl($url)
    {
        $absUrl = Myshipserv_Config::getApplicationProtocol() . '://' . Myshipserv_Config::getApplicationUrl() . $url; 
        return self::getByAbsoluteUrl($absUrl);
    }
    
}
