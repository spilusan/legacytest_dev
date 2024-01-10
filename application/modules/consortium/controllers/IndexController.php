<?php
/**
 * Controller actions for Consortium
 *
 * @author attilaolbrich
 *
 */

class Consortium_IndexController extends Myshipserv_Controller_Action
{

    /**
     * Initalise action parameters
     * {@inheritDoc}
     * @see Myshipserv_Controller_Action::init()
     *
     * @return unknown
     */
    public function init()
    {
        parent::init();

        $user = Shipserv_User::isLoggedIn();

        if (!$user) {
            throw new Myshipserv_Exception_MessagedException('This page requires you to be logged in', 403);
        } else {
            $activeCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
            if (Shipserv_Oracle_Consortia_BuyerSupplier::getSupplierCountForConsortiaBuyer($activeCompany->id) === 0) {
                throw new Myshipserv_Exception_MessagedException('You do not have right to access this page', 403);
            }
        }
    }
    /**
     * Action for main report
     *
     * @return unknown
     * @throws Exception
     */
    public function indexAction()
    {

    }
}