<?php
/**
 * Class Apiservices_CorporateNavServiceController
 *
 * general Micro services to provide support for the new ShipServ architecture
 * this is a temporary solution until real Java microservices can take over
 * Corporate site navigation
 *
 */

class Apiservices_CorporateNavServiceController extends Myshipserv_Controller_RestController
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
     *
     * @return null
     */
    public function getAction()
    {

        /*
         * @todo we might change it to ST valdation as it would be more secure as it is invalidated after read.
         * The function is alrady implemented in cas, just use validateSt instead with paramter ST
         */

        $tgt = $this->getRequest()->getParam('tgt');

        if ($tgt) {
            $userName = Myshipserv_CAS_CasRest::getInstance()->validateTgt($tgt, true);
            if (!$userName) {
                $this->view->json = array(
                    'error' => 'Validation error'
                );

                return;
            }
        }

        $this->view->json = Myshipserv_View_Helper_CorporateNav::getInstance()->getFullNav();

    }

}