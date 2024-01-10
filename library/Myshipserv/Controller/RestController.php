<?php
/**
 * Class for REST type of controllers automatically returning JSON response,
 * and managing GET, POST, DELETE, PUT type of requests
 *
 * Class Myshipserv_Controller_RestController
 */
abstract class Myshipserv_Controller_RestController extends Zend_Rest_Controller
{
    use Myshipserv_Controller_AuthJson;

    protected $params = array();

    /**
    * Make sure that we are logged in, if not it will throw a JSON error
    *
    * @param boolean $forceLoggedIn
    * @return void
    */
    public function preDispatch($forceLoggedIn = true)
    {
        set_time_limit(0);
        ini_set("memory_limit", "-1");

        if ($forceLoggedIn === true) {
            if (!self::$isLoggedIn) {
                // This may be good to refactor not using die() eand echo
                $e = new Myshipserv_Exception_JSONException("You are not logged in", 1);
                $error = array(
                    'status' => 'error',
                    'exception' => array(
                        'code' => $e->getCode(),
                        'type' => get_class($e),
                        'message' => $e->getMessage(),
                        'trace' => $e->getTrace()
                    )
                );

                echo json_encode($error, JSON_PRETTY_PRINT);
                die();
            }
        }

        $this->params = $this->_getAllParams();
    }

    /**
    * Default REST index action, if not defined in the subclass we thring a not implemented JSON error
    * @return json error
    */
    public function indexAction()
    {
        return $this->_replyJsonError(new Myshipserv_Exception_JSONException("INDEX action not supported", 0), 404);
    }

    /**
    * Default REST GET action, if not defined in the subclass we thring a not implemented JSON error
    * @return json error
    */
    public function getAction()
    {
        return $this->_replyJsonError(new Myshipserv_Exception_JSONException("GET action not supported", 0), 404);
    }

    /**
    * Default POST index action, if not defined in the subclass we thring a not implemented JSON error
    * @return json error
    */
    public function postAction()
    {
        return $this->_replyJsonError(new Myshipserv_Exception_JSONException("POST action not supported", 0), 404);
    }

    /**
    * Default PUT index action, if not defined in the subclass we thring a not implemented JSON error
    * @return json error
    */
    public function putAction()
    {
        return $this->_replyJsonError(new Myshipserv_Exception_JSONException("PUT action not supported", 0), 404);
    }

    /**
    * Default REST DELETE action, if not defined in the subclass we thring a not implemented JSON error
    * @return json error
    */
    public function deleteAction()
    {
        return $this->_replyJsonError(new Myshipserv_Exception_JSONException("DELETE action not supported", 0), 404);
    }

    /**
    * The HEAD method is identical to GET except that the server MUST NOT return a message-body in the response.
    * The metainformation contained in the HTTP headers in response to a HEAD request SHOULD be identical to the
    * information sent in response to a GET request. This method can be used for obtaining metainformation about
    * the entity implied by the request without transferring the entity-body itself. This method is often used for
    * testing hypertext links for validity, accessibility, and recent modification.
    * 
    * The response to a HEAD request MAY be cacheable in the sense that the information contained in the response
    * MAY be used to update a previously cached entity from that resource. If the new field values indicate that
    * the cached entity differs from the current entity (as would be indicated by a change in Content-Length,
    * Content-MD5, ETag or Last-Modified), then the cache MUST treat the cache entry as stale.
    *
    * @return json error
    */
    public function headAction()
    {
        return $this->_replyJsonError(new Myshipserv_Exception_JSONException("HEAD action not supported"), 404);
    }
  
}