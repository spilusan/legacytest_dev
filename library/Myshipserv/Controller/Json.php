<?php
trait Myshipserv_Controller_Json
{
    use Myshipserv_Controller;

    /**
     * Helper function which adds meta information block to a JSON to be returned to client
     *
     * @param   array   $json
     * @param   Shipserv_Helper_Stopwatch   $t
     *
     * @return array
     * @throws Myshipserv_Exception_MessagedException
     */
    protected function _addDebugInfo(array $json, Shipserv_Helper_Stopwatch $t = null)
    {
        if (!Myshipserv_Config::getEnv() !== 'production')
        {
            return $json;
        }

        $now = new DateTime();

        if (array_key_exists('_debug', $json))
        {
            throw new Myshipserv_Exception_MessagedException('Time key has already been added to JSON structure');
        }

        $match = new Shipserv_Match_Match(null);

        $json['_debug'] = array(
            'version' => $match->getVersion(),
            'session' => Shipserv_Log::getSessionId(),
            'time' => array(
                'timestamp' => $now->getTimestamp(),
                'timeISO'   => $now->format('Y-m-d H:i:s')
            ),
            'call'  => array(
                'host'       => $_SERVER['HTTP_HOST'],
                'uri'        => $_SERVER['REQUEST_URI'],
                'remoteIp'   => $_SERVER['REMOTE_ADDR'],
                'resolvedIp' => Shipserv_Helper_Network::getRemoteIP(),
                'parameters' => array(
                    'GET'  => $_GET,
                    'POST' => $_POST
                )
            )
        );

        if ($t)
        {
            $total = $t->getTotal();
            $loops = $t->getLoops(false);
            $sorted = $loops;
            arsort($sorted);

            $json['_debug']['elapsed'] = array(
                'total'  => $total,
                'steps'  => $loops,
                'sorted' => $sorted
            );
        }

        return $json;
    }

    /**
     * Creates a JSON type of exception and will respond as a JSON data with error log and error codes
     *
     * @param Exception $e
     * @param int $httpCode
     * @return bool
     * @throws Zend_Controller_Response_Exception
     */
    protected function _replyJsonError(Exception $e, $httpCode = 409)
    {
        /** @var $this Zend_Controller_Action */
        $this->getResponse()->setHttpResponseCode($httpCode);

        return $this->_replyJson(array(
            'status' => 'error',
            'exception' => array(
                'code' => $e->getCode(),
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            )
        ), null, true);
    }

    /**
     * @date    2015-09-21
     * @story   S14698
     *
     * @param mixed $json
     * @param Shipserv_Helper_Stopwatch|null $t
     * @param bool|false $forceNoDebugInfo
     *
     * @return  bool
     */
    protected function _replyJsonEnvelope($json, Shipserv_Helper_Stopwatch $t = null, $forceNoDebugInfo = false)
    {
        return $this->_replyJson(array('response' => $json), $t, $forceNoDebugInfo);
    }

    /**
     * Reply as a json
     *
     * @param array $json
     * @param Shipserv_Helper_Stopwatch|null $t
     * @param bool $forceNoDebugInfo
     * @return bool
     * @throws Myshipserv_Exception_MessagedException
     */
    protected function _replyJson(array $json, Shipserv_Helper_Stopwatch $t = null, $forceNoDebugInfo = false)
    {
       if (!Myshipserv_Config::getEnv() !== 'production')
       {
            if (!$forceNoDebugInfo) {
                $json = $this->_addDebugInfo($json, $t);
            }
        }

        $this->_helper->layout()->disableLayout();

        if ($this instanceof Zend_Controller_Action)
        {
            $this->view->json = $json;

            $viewPaths = $this->view->getScriptPaths();
            $this->view->setScriptPath(implode(DIRECTORY_SEPARATOR, array(
                APPLICATION_PATH, 'views', 'scripts'
            )));

            $this->renderScript('json/json.phtml');
            $this->view->setScriptPath($viewPaths);
        }

        return true;
    }


    public function init()
    {
        if (!($this instanceof Zend_Controller_Action))
        {
            return;
        }
    }

    /**
     * @param bool|false $emptyAllowed
     * @return mixed|null
     * @throws Myshipserv_Exception_MessagedException
     */
    protected function _getJsonPayload($emptyAllowed = false)
    {
        /** @var $this Zend_Controller_Action */
        $strJson = $this->getRequest()->getRawBody();

        if (strlen($strJson) === 0) {
            if ($emptyAllowed) {
                return null;
            } else {
                throw new Myshipserv_Exception_MessagedException('Empty request payload');
            }
        }

        $json = json_decode($strJson, true);
        if (is_null($json)) {
            throw new Myshipserv_Exception_MessagedException('Invalid JSON payload');
        }

        return $json;
    }

    /**
     * @param   array           $payload
     * @param   string|array    $keys
     * @param   bool|false      $noneAllowed
     *
     * @return  array|string|int|null
     * @throws  Myshipserv_Exception_MessagedException
     */
    protected function _getPayloadValue(array $payload, $keys, $noneAllowed = false)
    {
        if (!is_array($keys))
        {
            $keys = explode('.', $keys);
        }

        $node = $payload;
        foreach ($keys as $key) {
            if (!array_key_exists($key, $node)) {
                if ($noneAllowed) {
                    return null;
                } else {
                    throw new Myshipserv_Exception_MessagedException('Payload structure invalid, key ' . implode('.', $keys) . ' not found');
                }
            }

            $node = $node[$key];
        }

        return $node;
    }
}
