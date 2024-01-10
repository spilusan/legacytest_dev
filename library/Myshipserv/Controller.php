<?php
/**
 * Some helper functions requested by both JSON and HTML controllers
 *
 * @author  Yuriy Akopov
 * @date    2015-09-21
 */
trait Myshipserv_Controller
{
    /**
     * Front end often supply '&param=' even when parameter is omitted, this needs to be treated as a default value
     * (as if the parameter wasn't supplied)
     *
     * @param   string                    $paramName
     * @param   mixed    $default
     *
     * @return  mixed
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _getNonEmptyParam($paramName, $default = null)
    {
        if (!$this instanceof Zend_Controller_Action)
        {
            throw new Myshipserv_Exception_MessagedException("Controller functions accessed outside of controller");
        }

        $value = $this->getParam($paramName);
        if (strlen($value) === 0)
        {
            $value = $default;
        }

        return $value;
    }

    /**
     * Retrieves a parameter which non-empty values is expected
     *
     * @param   string  $paramName
     * @param   bool    $arraysAllowed
     *
     * @return  mixed
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _getMandatoryParam($paramName, $arraysAllowed)
    {
        if (!$this instanceof Zend_Controller_Action)
        {
            throw new Myshipserv_Exception_MessagedException("Controller functions accessed outside of controller");
        }

        $value = $this->getParam($paramName, null);
        if (
            (!is_array($value) and (strlen($value) === 0)) or
            (is_array($value) and !$arraysAllowed) or
            (is_array($value) and empty($value)) or
            (is_array($value) and (count($value) === 1) and (strlen($value[0]) === 0))
        ) {
            throw new Myshipserv_Exception_MessagedException("Mandatory parameter " . $paramName . " is missing or invalid");
        }

        return $value;
    }
}