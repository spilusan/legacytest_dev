<?php
/**
* Trait to respond CSV 
*/
trait Myshipserv_Controller_Export
{
    use Myshipserv_Controller;

    /**
    * Send out Error message as CSV
    * @param object  $e        Exception object
    * @param integer $httpCode HTTP respoonse code
    * @return array  Exception represented in an array
    */
    protected function _replyCsvError(Exception $e, $httpCode = 409)
    {
        /** @var $this Zend_Controller_Action */
        $this->getResponse()->setHttpResponseCode($httpCode);

        return $this->_replyCsv(
            array(
            	'error' => 'Export error',
                'code' => $e->getCode(),
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            )
        );
    }

	/**
	* This will output a Csv to the output, after proper header is sent, it will be a file download
	* @param  array $data The array to export as a CSV
	*
	* @return bool
	*/
    protected function _replyCsv(array $data)
    {
        // disable layout for Expott responses
        $this->_helper->layout()->disableLayout();
        //$this->_helper->viewRenderer->setNoRender(true);

        if ($this instanceof Zend_Controller_Action) {
            $this->view->data = $data;

            $viewPaths = $this->view->getScriptPaths();
            $this->view->setScriptPath(implode(DIRECTORY_SEPARATOR, array(APPLICATION_PATH, 'views', 'scripts')));

            $this->renderScript('export/csv.phtml');
            $this->view->setScriptPath($viewPaths);
        }

        return true;
    }

    /**
    * Check if class can be initalized and instance of Zend_Controller_Action
    * @return undefined 
    */
    public function init()
    {
        if (Shipserv_User::isLoggedIn() === false) {
            $this->_replyJsonError(new Myshipserv_Exception_JSONException("You are not logged in", 1), 404);
            self::$isLoggedIn = false;
        } else {
            self::$isLoggedIn = true;
        }

        if (!($this instanceof Zend_Controller_Action)) {
            return;
        }
    }

}