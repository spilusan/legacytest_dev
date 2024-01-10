<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @category   Zend
 * @package    Zend_View
 * @subpackage Helper
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @version    $Id: HeadLink.php 20096 2010-01-06 02:05:09Z bkarwin $
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/** Zend_View_Helper_Placeholder_Container_Standalone */
require_once 'Zend/View/Helper/HeadLink.php';

/**
 * Zend_Layout_View_Helper_HeadLink
 *
 * @see        http://www.w3.org/TR/xhtml1/dtds.html
 * @uses       Zend_View_Helper_Placeholder_Container_Standalone
 * @package    Zend_View
 * @subpackage Helper
 * @copyright  Copyright (c) 2005-2010 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Myshipserv_View_Helper_CDNLink extends Myshipserv_View_Helper_HeadLink
{
    
    /**
     * @var string registry key
     */
    protected $_regKey = 'Zend_View_Helper_CDNLink';

    /**
     * headLink() - View Helper Method
     * 
     * Returns current object instance. Optionally, allows passing array of
     * values to build link.
     * 
     * @return Zend_View_Helper_HeadLink
     */
    public function CDNLink(array $attributes = null, $placement = Zend_View_Helper_Placeholder_Container_Abstract::APPEND)
    {
        return $this->headLink($attributes, $placement);
    }

    /**
     * Create HTML link element from data item
     *
     * @param  stdClass $item
     * @return string
     */
    public function itemToString(stdClass $item)
    {
        $attributes = (array) $item;
        $link       = '<link ';
        $config     = $GLOBALS['application']->getBootstrap()->getOptions();
        
        foreach ($this->_itemKeys as $itemKey) {
            if (isset($attributes[$itemKey])) {
                if(is_array($attributes[$itemKey])) {
                    foreach($attributes[$itemKey] as $key => $value) {
                        
                        $link .= sprintf('%s="%s" ', $key, ($this->_autoEscape) ? $this->_escape($value) : $value);
                    }
                } else {
                    if ($itemKey == 'href' && @$attributes['rel'] == 'stylesheet' && $config['shipserv']['cdn']['use'] == 1)
                    {
                        $attributes[$itemKey] = $config['shipserv']['cdn']['css'].$attributes[$itemKey];
                    }
                    
                    $link .= sprintf('%s="%s" ', $itemKey, ($this->_autoEscape) ? $this->_escape($attributes[$itemKey]) : $attributes[$itemKey]);
                }
            }
        }

        if ($this->view instanceof Zend_View_Abstract) {
            $link .= ($this->view->doctype()->isXhtml()) ? '/>' : '>';
        } else {
            $link .= '/>';
        }

        if (($link == '<link />') || ($link == '<link >')) {
            return '';
        }

        if (isset($attributes['conditionalStylesheet'])
            && !empty($attributes['conditionalStylesheet'])
            && is_string($attributes['conditionalStylesheet']))
        {
            $link = '<!--[if ' . $attributes['conditionalStylesheet'] . ']> ' . $link . '<![endif]-->';
        }

        return $link;
    }
    
    /**
     * Returns an image URL if it's supposed to be CDNd
     *
     * @access public
     * @param string $url The relative image url
     * @return string the image url - CDNd or not
     */
    public function image ($url)
    {
        return $this->createCDNUrl($url, 'image');
    }
    
    /**
     * Returns a JavaScript URL if it's supposed to be CDNd
     *
     * @access public
     * @param string $url The relative image url
     * @return string the image url - CDNd or not
     */
    public function javascript ($url)
    {
        return $this->createCDNUrl($url, 'javascript');
    }

    /**
     * Returns a CSS URL if it's supposed to be CDNd
     *
     * @access public
     * @param string $url The relative image url
     * @return string the image url - CDNd or not
     */
    public function css ($url)
    {
        return $this->createCDNUrl($url, 'css');
    }

    private function createCDNUrl ($url, $type)
    {
        $config = $GLOBALS['application']->getBootstrap()->getOptions();
				
        /*Create CDN URL*/
        if ($config['shipserv']['cdn']['use'] == 1 && $config['shipserv']['cdn'][$type] != '')
        {
            $url = $config['shipserv']['cdn'][$type].$url;
        }
        
        return $url;
    }
}
