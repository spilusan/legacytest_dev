<?php

/** Zend_View_Helper_HeadScript */
require_once 'Zend/View/Helper/HeadScript.php';

/**
 * Helper for setting and retrieving script elements for inclusion in HTML body
 * section
 *
 * @uses       Zend_View_Helper_Head_Script
 * @package    Zend_View
 * @subpackage Helper
 */
class Myshipserv_View_Helper_CompressedScript extends Myshipserv_View_Helper_HeadScript
{
    /**
     * Registry key for placeholder
     * @var string
     */
    protected $_regKey = 'Zend_View_Helper_CompressedScript';

    /**
     * Return InlineScript object
     *
     * Returns InlineScript helper object; optionally, allows specifying a
     * script or script file to include.
     *
     * @param  string $mode Script or file
     * @param  string $spec Script/url
     * @param  string $placement Append, prepend, or set
     * @param  array $attrs Array of script attributes
     * @param  string $type Script type and/or array of script attributes
     * @return Zend_View_Helper_InlineScript
     */
    public function compressedScript($mode = Zend_View_Helper_HeadScript::FILE, $spec = null, $placement = 'APPEND', array $attrs = array(), $type = 'text/javascript')
    {
        return $this->headScript($mode, $spec, $placement, $attrs, $type);
    }
    
    /**
     * Create script HTML
     *
     * @param  string $type
     * @param  array $attributes
     * @param  string $content
     * @param  string|int $indent
     * @return string
     */
    public function itemToString($item, $indent, $escapeStart, $escapeEnd)
    {
        $attrString = '';
        $config = $GLOBALS['application']->getBootstrap()->getOptions();
        
        if (!empty($item->attributes)) {
            foreach ($item->attributes as $key => $value) {
                if (!$this->arbitraryAttributesAllowed()
                    && !in_array($key, $this->_optionalAttributes))
                {
                    continue;
                }
                if ('defer' == $key) {
                    $value = 'defer';
                }
                                
                // CDN Hack - add a CDN http host if live
                if ($key == 'src' && $config['shipserv']['cdn']['use'] == 1) {
                	//Do not add CDN if we are trying to pass an absolute URL
                	if (strpos($value, 'http') !== 0) {
                    	$value = $config['shipserv']['cdn']['javascript'].$value;
                	}
                }
                
                $attrString .= sprintf(' %s="%s"', $key, ($this->_autoEscape) ? $this->_escape($value) : $value);
            }
        }

        $type = ($this->_autoEscape) ? $this->_escape($item->type) : $item->type;
        $html  = '<script type="' . $type . '"' . $attrString . '>';
        if (!empty($item->source)) {
              $html .= PHP_EOL . $indent . '    ' . $escapeStart . PHP_EOL . $item->source . $indent . '    ' . $escapeEnd . PHP_EOL . $indent;
        }
        $html .= '</script>';

        if (isset($item->attributes['conditional'])
            && !empty($item->attributes['conditional'])
            && is_string($item->attributes['conditional']))
        {
            $html = $indent . '<!--[if ' . $item->attributes['conditional'] . ']> ' . $html . '<![endif]-->';
        } else {
            $html = $indent . $html;
        }

        return $html;
    }
    
    /**
     * Retrieve string representation
     *
     * @param  string|int $indent
     * @return string
     */
    public function toString($indent = null)
    {
        $indent = (null !== $indent)
                ? $this->getWhitespace($indent)
                : $this->getIndent();

        if ($this->view) {
            $useCdata = $this->view->doctype()->isXhtml() ? true : false;
        } else {
            $useCdata = $this->useCdata ? true : false;
        }
        $escapeStart = ($useCdata) ? '//<![CDATA[' : '//<!--';
        $escapeEnd   = ($useCdata) ? '//]]>'       : '//-->';

        $items = array();
        $this->getContainer()->ksort();

        foreach ($this as $item) {
            if (!$this->_isValid($item)) {
                continue;
            }
            
            $items[] = $this->itemToString($item, $indent, $escapeStart, $escapeEnd);
        }
        
        $return = implode($this->getSeparator(), $items);
        
        return $return;
    }
}
