<?php

/**
 * Class Apiservices_ActiveUserCompanyController
 *
 * Retrurn the selected active user company details
 */
class Apiservices_ActiveUserCompanyController extends Myshipserv_Controller_RestController
{
    /**
     * Pre dispatch is overridden here to disable layout and force skipping authentication
     *
     * @return undefined|void
     */
    public function preDispatch()
    {
        $this->_helper->layout()->disableLayout();
        parent::preDispatch(false);
    }

    /**
     * init is overridden here to skip authentication (must be done here and pre-dispatch as well)
     *
     */
    public function init()
    {
        parent::init(false);
    }

    /**
     * Maybe called on get request, and redirected to getAction
     * @return null
     */
    public function indexAction()
    {
        $this->_helper->viewRenderer('get');
        $this->getAction();
    }

    /**
     * Return Active Selected Company ID and Type as Json
     *
     * @return null
     */
    public function getAction()
    {
        $companyId = Myshipserv_Helper_Session::getActiveCompanyId();
        $activeCompanyType = Myshipserv_Helper_Session::getActiveCompanyType(true);

        $companyName = Shipserv_Company::getCompanyNameByType($activeCompanyType, $companyId);
        $this->view->json = array(
            'activeUserCompanyId' => $companyId,
            'activeUserCompanyType' => $activeCompanyType,
            'activeUserCompanyName' => $companyName
        );
    }

}
