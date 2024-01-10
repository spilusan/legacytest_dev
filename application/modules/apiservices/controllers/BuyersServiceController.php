<?php
/**
 * Class Apiservices_BuyersServiceController
 *
 * general Micro services to provide support for the new ShipServ architecture
 * this is a temporary solution until real Java microservices can take over
 * It will respond with the buyer list of the selected active company
 *
 */

class Apiservices_BuyersServiceController extends Myshipserv_Controller_RestController
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
     * Entry point to get the buyer list
     *
     * @return null
     */
    public function getAction()
    {

        $data  = Shipserv_Report_Buyer_Match_BuyerBranches::getInstance()->getBuyerBranches(Shipserv_User::BRANCH_FILTER_WEBREPORTER, false);
        if ($this->_getParam('context') === 'rfq') {
            $data[] = array(
                'id'   => Myshipserv_Config::getProxyPagesBuyer(),
                'name' => 'Pages RFQs',
                'default' => 0
            );
        } elseif ($this->_getParam('context') === 'quote') {
            $data[] = array(
                'id'   => Myshipserv_Config::getProxyPagesBuyer(),
                'name' => 'Pages Quotes',
                'default' => 0
            );
        }

        $this->view->json = $data;

    }
}

