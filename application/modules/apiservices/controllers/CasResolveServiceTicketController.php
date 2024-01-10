<?php

/**
 * Resolving CAS Service ticket and return the user object if the ticiet is valid
 */
class Apiservices_CasResolveServiceTicketController extends Myshipserv_Controller_RestController
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
     * Init is overridden here to skip authentication (must be done here and pre-dispatch as well)
     * Disable login requirement as we don't have sesion when we calling it from outside
     * 
     * Allow cross origin for Buyer Connect Admin as it is from a different domain
     */
    public function init()
    {
        parent::init(false, true);
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
     * Entry point
     *
     * @return null
     */
    public function getAction()
    {
        $serviceTicket = $this->getRequest()->getParam('ticket', null);

        if (!$serviceTicket) {
            $result = $this->createErrorMessage('ticket parameter is missing'); 
        } else {
            $casRequest = Myshipserv_CAS_CasRest::getInstance();
            $userName = $casRequest->validateSt($serviceTicket, null, false);
            if ($userName) {
                $user = Shipserv_User::getInstanceByEmail($userName);
                if ($user) {
                    $result = array(
                        'userId' => $user->userId,
                        'username' => $user->username,
                        'firstName' => $user->firstName,
                        'lastName' => $user->lastName,
                        'isShipservUser' => $user->isShipservUser(),
                        'isBcAdmin' => $user->canPerform('PSG_ACCESS_BC_ADMIN')
                    );
                } else {
                    $result = $this->createErrorMessage('user is logged off'); 
                }
            } else {
                $result = $this->createErrorMessage('invalid service ticket'); 
            }
        }

        $this->_helper->json((array)$result);
    }

    /**
     * This function just pushes the error message into an array
     * 
     * @param string $message
     * 
     * @return array
     */
    protected function createErrorMessage($message)
    {
        return array(
            'error' => $message
        );
    }
}
