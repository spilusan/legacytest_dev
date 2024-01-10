<?php
/**
 * Helper class to hold functionality shared by more than one but by less than all controllers
 * Something that should've been a trait, but we're running PHP 5.2 in production yet
 *
 * @author  Yuriy Akopov
 * @date    2014-02-18
 */
class Shipserv_Helper_Controller {
    /**
     * Controller we're helping so we can access its member functions
     *
     * @var Myshipserv_Controller_Action
     */
    protected $controller = null;

    /**
     * @param Myshipserv_Controller_Action $controller
     */
    public function __construct(Myshipserv_Controller_Action $controller) {
        $this->controller = $controller;
    }

    /**
     * Checks if the RFQ ID is valid and that RFQ can be accessed by the current user, returns RFQ if it's fine
     *
     * @param   int     $rfqId
     *
     * @return  Shipserv_Rfq
     * @throws  Myshipserv_Exception_MessagedException
     */
    public function getRfqById($rfqId) {
        $rfqId = $this->getNumberParam($rfqId);

        $user = $this->controller->abortIfNotLoggedIn();
        $buyerOrg = $this->controller->getUserBuyerOrg();

        try {
            $rfq = Shipserv_Rfq::getInstanceById($rfqId);
        } catch (Exception $e) {
            throw new Myshipserv_Exception_MessagedException("Requested RFQ ID " . $rfqId . " not found");
        }

        $rfqSenderBuyer = $rfq->getOriginalSender();
        if (!($rfqSenderBuyer instanceof Shipserv_Buyer)) {
            throw new Myshipserv_Exception_MessagedException("Not a buyer RFQ");
        }

        if ($user->isShipservUser()) {
            return $rfq;
        }

        if (!$user->isPartOfBuyer($rfqSenderBuyer->id, true, true)) {
            throw new Myshipserv_Exception_MessagedException("You don't have access to the requested RFQ (" . $rfqId . ")");
        }

        return $rfq;
    }

    /**
     * Validates parameter as a valid numerical ID
     *
     * @param   string   $number
     *
     * @return  int
     * @throws  Myshipserv_Exception_MessagedException
     */
    public function getNumberParam($number) {
        if (!filter_var($number, FILTER_VALIDATE_INT)) {
            throw new Myshipserv_Exception_MessagedException("Integer value expected, " . $number . " given");
        }

        if ($number <= 0) {
            throw new Myshipserv_Exception_MessagedException("Positive number expected, " . $number . " given");
        }

        return (int) $number;
    }

    /**
     * Checks if the supplier ID is valid
     *
     * @param   int     $supplierId
     * @param   bool    $allowAll
     *
     * @return  Shipserv_Supplier
     * @throws  Myshipserv_Exception_MessagedException
     */
    public function getSupplierById($supplierId, $allowAll = false) {
        $supplierId = $this->getNumberParam($supplierId);

        $supplier = Shipserv_Supplier::getInstanceById($supplierId, '', $allowAll);
        if (strlen($supplier->tnid) === 0) {
            throw new Myshipserv_Exception_MessagedException("Supplier " . $supplierId . " not found");
        }

        return $supplier;
    }
}