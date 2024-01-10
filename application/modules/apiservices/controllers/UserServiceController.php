<?php
/**
 * Class Apiservices_MenuServiceController
 *
 * general Micro services to provide support for the new ShipServ architecture
 * this is a temporary solution until real Java microservices can take over
 *
 */

class Apiservices_UserServiceController extends Myshipserv_Controller_RestController
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
        $result = array();

        if ($tgt) {

            $userName = Myshipserv_CAS_CasRest::getInstance()->validateTgt($tgt, true);
            if (!$userName) {
                $this->view->json = array(
                    'error' => 'Validation error'
                );

                return;
            }

            $user = Shipserv_User::getInstanceByEmail($userName);

        } else {
            $user = Shipserv_User::isLoggedIn();
        }

        try {
            if ($user) {
                $userObjectArray = (array)$user;
                $result['user'] = array();

                unset($userObjectArray['userRow']['USR_PWD_HASH']);
                unset($userObjectArray['userRow']['USR_PWD_SALT']);
                unset($userObjectArray['userRow']['USR_PASSWORD']);
                $result['companies'] = $user->fetchCompanies(true);

                //camel case user keys
                foreach ($userObjectArray['userRow'] as $key => $row) {
                    $result['user'][implode('', array_map('ucfirst', explode('_', strtolower($key))))] = $row;
                }

                $result['user']['IsShipservUser'] = $user->isShipservUser();

            }

            $this->view->json = $result;
        } catch (Shipserv_Oracle_User_Exception_NotFound $e) {
            $this->view->json = array(
                'error' => 'User not found ' . $e->getMessage()
            );
        }
    }

}