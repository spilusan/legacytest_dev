<?php
/**
 * Controller actions Catalogue Search
 */

class Reports_CatalogueRestController extends Myshipserv_Controller_RestController
{
    /**
     * Setting up initial access level checks
     * @return bool
     */
    public function init()
    {
        parent::init(false);
    }

    /**
     * Maybe called on get request, and redirected to getAction
     * @return undefined
     */
    public function indexAction()
    {
        $this->getAction();
    }

    /**
     * We want to allow post, or get for the same service, so  in case of get, forwarding it to the post
     * @return undefined
     */
    public function getAction()
    {
        $this->postAction();
    }

    /**
     * Triggered when POST request is sent
     *
     * @return mixed
     */
    public function postAction()
    {
        $reporService = Myshipserv_ReportService_Gateway::getInstance(Myshipserv_ReportService_Gateway::REPORT_PAGES_SERVICE_SEARCH);
        $id = $this->getRequest()->getParam('id', 0);
        $params = array(
            'folderStart' => $this->getRequest()->getParam('folderStart', 0),
            'folderRows'  => $this->getRequest()->getParam('folderRows', 10),
            'itemStart' => (int)$this->getRequest()->getParam('itemStart', 0),
            'itemRows'  => (int)$this->getRequest()->getParam('itemRows', 10),
            'query' => urlencode($this->getRequest()->getParam('query', ''))
        );

        return $this->_replyJson($reporService->forward($id, $params));
    }
}
