<?php
/**
 * A class to house general purpose functions related to session management
 *
 * @author  Yuriy Akopov
 * @date    2016-07-22
 * @story   DE6822
 */
class Myshipserv_Helper_Cookie
{

    /**
     * Set cookie 
     * 
     * @param string $key
     * @param strimg $value
     * 
     */
    public static function set($key, $value)
    {
        $config = Zend_Registry::get('options');
        $cookie = $config['shipserv']['enquiryBasket']['cookie'];
        
        $expiry = ($cookie['expiry'] == 0) ? 0 : time() + $cookie['expiry'];
        setcookie($key, urlencode($value), $expiry, $cookie['path'], $cookie['domain']);

    }

    /**
     * Get cookie seafely
     * 
     * @param $key
     * @return string 
     */
    public static function get($key)
    {
        return isset($_COOKIE[$key]) ? _htmlspecialchars(urldecode($_COOKIE[$key])) : null;
    }

    /**
     * Delete cookie
     * 
     * @param $key;
     * 
     */
    public function del($key)
    {
        $config = $this->config;
        $cookie = $config['shipserv']['enquiryBasket']['cookie'];
        
        setcookie($key, '', time() - 3600 , $cookie['path'], $cookie['domain']);
    }
}