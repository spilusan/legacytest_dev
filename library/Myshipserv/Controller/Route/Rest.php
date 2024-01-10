<?php
/**
 * Based on aporat/application_rest_controller_route composer package, then improved
 *
 * @author  Yuriy Akopov 
 * @date    2015-10-27
 * @story   S14698
 */
class Myshipserv_Controller_Route_Rest extends Zend_Controller_Router_Route
{

    /**
     * @var Zend_Controller_Front
     */
    protected $_front;

    protected $_actionKey     = 'action';

    const URI_DELIMITER = '/';

    /**
     * Prepares the route for mapping by splitting (exploding) it
     * to a corresponding atomic parts. These parts are assigned
     * a position which is later used for matching and preparing values.
     *
     * @param Zend_Controller_Front $front Front Controller object
     * @param string $route Map used to match with later submitted URL path
     * @param array $defaults Defaults for map variables with keys as variable names
     * @param array $reqs Regular expression requirements for variables (keys as variable names)
     * @param Zend_Translate $translator Translator to use for this instance
     */
    public function __construct(Zend_Controller_Front $front, $route, $defaults = array(), $reqs = array(), Zend_Translate $translator = null, $locale = null)
    {
        $this->_front      = $front;
        $this->_dispatcher = $front->getDispatcher();

        parent::__construct($route, $defaults, $reqs, $translator, $locale);
    }



    /**
     * Matches a user submitted path with parts defined by a map. Assigns and
     * returns an array of variables on a successful match.
     *
     * @param string $path Path used to match against this routing map
     * @return array|false An array of assigned values or a false on a mismatch
     */
    public function match($path, $partial = false)
    {

        $return = parent::match($path, $partial);

        // add the RESTful action mapping
        if ($return) {
            $request = $this->_front->getRequest();
            $path   = $request->getPathInfo();
            $params = $request->getParams();

            $path   = trim($path, self::URI_DELIMITER);

            if ($path != '') {
                $path = explode(self::URI_DELIMITER, $path);
            }

            // Store path count for method mapping
            // $pathElementCount = count($path);

            // Determine Action
            $requestMethod = strtolower($request->getMethod());
            if ($requestMethod != 'get') {
                if ($request->getParam('_method')) {
                    $return[$this->_actionKey] = strtolower($request->getParam('_method'));
                } elseif ( $request->getHeader('X-HTTP-Method-Override') ) {
                    $return[$this->_actionKey] = strtolower($request->getHeader('X-HTTP-Method-Override'));
                } else {
                    $return[$this->_actionKey] = $requestMethod;
                }

            } else {
                // if the last argument in the path is a numeric value, consider this request a GET of an item
                $lastParam = array_pop($path);
                if (is_numeric($lastParam)) {
                    $return[$this->_actionKey] = 'get';
                } else {
                    $return[$this->_actionKey] = 'index';
                }
            }

        }

        return $return;

    }

}