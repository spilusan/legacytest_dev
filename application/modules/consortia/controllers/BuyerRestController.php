<?php
/**
 * Controller actions Consortia
 * Sample URL: /reports/data/consortia/:type?params......
 *
 * @author attilaolbrich
 *
 */

class Consortia_BuyerRestController extends Myshipserv_Controller_RestController
{

    /**
     * Setting up initial access level checks
     * @return bool
     */
    public function init()
    {
        parent::init();

        $user = Shipserv_User::isLoggedIn();

        if (!$user) {
            return $this->_replyJsonError(new Myshipserv_Exception_JSONException("This page requires you to be logged in", 500), 500);
        } else {
            if ($user->canAccessTransactionReport() === false) {
                return $this->_replyJsonError(new Myshipserv_Exception_JSONException("You do not have right to access this page", 500), 500);
            }
        }
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
        $sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
        $reporService = Myshipserv_ReportService_Gateway::getInstance(Myshipserv_ReportService_Gateway::REPORT_SPR_CONSORTIA);

        $type = $this->getRequest()->getParam('type', null);
        $lowerdate = $this->getRequest()->getParam('lowerdate', null);
        $upperdate  = $this->getRequest()->getParam('upperdate', null);
        $pageNo = (int)$this->getRequest()->getParam('pageNo', 1);
        $pageSize  = (int)$this->getRequest()->getParam('pageSize', 10);
        $byb = $this->getRequest()->getParam('byb', null);
        $spb = $this->getRequest()->getParam('spb', null);

        //validate mandatory input
        if ($type === null || $lowerdate === null || $upperdate === null) {
            return $this->_replyJsonError(new Myshipserv_Exception_JSONException("Report type, or lowerdate or  upperdate is missing, incomplete URL", 500), 500);
        }

        switch ($type) {
            case 'buyers':
                return $this->_replyJson($reporService->forward($sessionActiveCompany->id . '/buyers', array('lowerdate' => $lowerdate, 'upperdate' => $upperdate)));
            case 'buyers-post':
                if ($byb === null) {
                    return $this->_replyJsonError(new Myshipserv_Exception_JSONException("buyer branch parameter is missing or invalid, incomplete URL", 500), 500);
                }
                return $this->_replyJson($reporService->forward($sessionActiveCompany->id . '/buyers/' . (int)$byb . '/pos', array('lowerdate' => $lowerdate, 'upperdate' => $upperdate, 'pageNo' => $pageNo, 'pageSize' => $pageSize)));
            case 'child-buyers':
                if ($byb === null) {
                    return $this->_replyJsonError(new Myshipserv_Exception_JSONException("buyer branch parameter is missing or invalid, incomplete URL", 500), 500);
                }
                return $this->_replyJson($reporService->forward($sessionActiveCompany->id . '/buyers/' . (int)$byb . '/child-buyers', array('lowerdate' => $lowerdate, 'upperdate' => $upperdate)));
            case 'suppliers':
                if ($byb === null) {
                    return $this->_replyJsonError(new Myshipserv_Exception_JSONException("buyer branch parameter is missing or invalid, incomplete URL", 500), 500);
                }
                return $this->_replyJson($reporService->forward($sessionActiveCompany->id . '/buyers/' . (int)$byb . '/suppliers', array('lowerdate' => $lowerdate, 'upperdate' => $upperdate)));
            case 'suppliers-pos':
                if ($byb === null || $spb === null) {
                    return $this->_replyJsonError(new Myshipserv_Exception_JSONException("buyer branch parameter is missing or invalid, incomplete URL", 500), 500);
                }
                return $this->_replyJson($reporService->forward($sessionActiveCompany->id . '/buyers/' . (int)$byb . '/suppliers/' . (int)$spb . '/pos', array('lowerdate' => $lowerdate, 'upperdate' => $upperdate, 'pageNo' => $pageNo, 'pageSize' => $pageSize)));
            case 'suppliers-child-suppliers':
                if ($byb === null || $spb === null) {
                    return $this->_replyJsonError(new Myshipserv_Exception_JSONException("buyer branch or supplier branch parameter is missing or invalid, incomplete URL", 500), 500);
                }
                return $this->_replyJson($reporService->forward($sessionActiveCompany->id . '/buyers/' . (int)$byb . '/suppliers/' . (int)$spb . '/child-suppliers', array('lowerdate' => $lowerdate, 'upperdate' => $upperdate)));
            case 'buyers-child-suppliers':
                if ($byb === null || $spb === null) {
                    return $this->_replyJsonError(new Myshipserv_Exception_JSONException("buyer branch or supplier branch parameter is missing or invalid, incomplete URL", 500), 500);
                }
                return $this->_replyJson($reporService->forward($sessionActiveCompany->id . '/buyers/' . (int)$byb . '/suppliers/' . (int)$spb . '/child-suppliers', array('lowerdate' => $lowerdate, 'upperdate' => $upperdate)));
        }

        return $this->_replyJsonError(new Myshipserv_Exception_JSONException("Invalid report type", 500), 500);

    }

}