<?php
/**
 * Class Apiservices_MenuServiceController
 *
 * general Micro services to provide support for the new ShipServ architecture
 * this is a temporary solution until real Java microservices can take over
 *
 */

class Apiservices_MenuServiceController extends Myshipserv_Controller_RestController
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
        $userId = $this->getRequest()->getParam('userId');
        $activeCompanyId = $this->getRequest()->getParam('activeCompanyId');
        $activeCompanyType = $this->getRequest()->getParam('activeCompanyType');

        $nav = new Myshipserv_View_Helper_PagesNav();

        if ($userId && $activeCompanyId && $activeCompanyType) {
            // if we pass user details then return menu for the specified user and active company details

            $safeUserId = (int)$userId;
            $safeActiveCompanyId = (int)$activeCompanyId;

            try {
                $user = Shipserv_User::getInstanceById($safeUserId);
            } catch (Shipserv_Oracle_User_Exception_NotFound $e) {
                return $this->_replyJsonError(new Myshipserv_Exception_JSONException('Invalid user ID ' . $safeUserId, 500), 500);
            }

            if (!$user->isShipservUser() && !$user->isPartOfCompany($safeActiveCompanyId)) {
                return $this->_replyJsonError(
                    new Myshipserv_Exception_JSONException(
                        'The provided active company (' . $safeActiveCompanyId . ') does not belong to the specified user (' .  $safeUserId  . ')',
                        500
                    ),
                    500
                );
            }

            $activeCompany = new stdClass();
            $activeCompany->id = $safeActiveCompanyId;
            Myshipserv_Helper_Session::fake($activeCompany);
            $shortActiveCompanyType = Myshipserv_Helper_Session::getShortCompanyIdByFullId($activeCompanyType);

            if ($shortActiveCompanyType === $activeCompanyType) {
                return $this->_replyJsonError(new Myshipserv_Exception_JSONException('Could not resolve company type (' .  $activeCompanyType . ')', 500), 500);
            }

            try {

                switch ($shortActiveCompanyType) {
                    case 'v':
                        $company = Shipserv_Supplier::getInstanceById($safeActiveCompanyId, '', true);
                        if ($company->tnid === null) {
                            return $this->_replyJsonError(new Myshipserv_Exception_JSONException('Supplier does not exists (' . $safeActiveCompanyId . ')', 500), 500);
                        }
                        break;
                    case 'b':
                        $company = Shipserv_Buyer::getInstanceById($safeActiveCompanyId);
                        if ($company->id === null) {
                            return $this->_replyJsonError(new Myshipserv_Exception_JSONException('Buyer does not exists (' . $safeActiveCompanyId . ')', 500), 500);
                        }

                        break;
                    case 'c':
                        $company = Shipserv_Consortia::getConsortiaInstanceById($safeActiveCompanyId);
                }

            } catch (Exception $e) {
                return $this->_replyJsonError(new Myshipserv_Exception_JSONException($e->getMessage(), 500), 500);
            }

            $activeCompany->type = $shortActiveCompanyType;
            $this->view->json = $nav->pagesNav($user, $activeCompany);

            return;
        }

        // if we are not using passed TGT for user authentication then try to login with cookie or return basic nav
        $this->view->json = $nav->pagesNav();
    }

}