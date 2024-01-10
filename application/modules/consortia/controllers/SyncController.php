<?php
/**
 * Entry point to the API synchronising Pull.php data between Admin Gateway data in Oracle and Salesforce.
 *
 * Synchronisation workflow triggered by the actions of this controller is explained here:
 * https://shipserv365.sharepoint.com/sites/itteam/_layouts/15/guestaccess.aspx?guestaccesstoken=JJ9U1N8QQkL%2Blpm3KQq8P9%2BM1I%2FPzcMHNmxxh5feNro%3D&docid=2_1b684c83f1a124c35b6812c32729a604e&rev=1&e=ea93943649ac4311aa6ebdf8a62e0757
 *
 * @author  Yuriy Akopov
 * @date    2017-11-30
 * @story   DEV-1170
 */
class Consortia_SyncController extends Myshipserv_Controller_Action
{
    /**
     * Validates Salesforce secret API key
     *
     * @throws  Myshipserv_Salesforce_Exception
     */
    protected function verifySalesforceApiKey()
    {
        $apiKey = $this->_getParam('apiKey');

        $salesforceSettings = Myshipserv_Config::getSalesForceCredentials();

        if ($apiKey !== $salesforceSettings->shipservApiKey) {
            $this->_helper->layout()->disableLayout();
            throw new Myshipserv_Salesforce_Exception("Invalid API key");
        }
    }

    /**
     * Throws an exception when the currently logged in user is not authorised to push Consortia info to Salesforce
     *
     * @todo: might need to check not only for ShipMate status, but also for Admin Gateway access privilege, if exists
     *
     * @return  Shipserv_User
     * @throws  Myshipserv_Salesforce_Exception
     */
    protected function verifyAdminGatewayUser()
    {
        $user = Shipserv_User::isLoggedIn();

        if (!($user instanceof Shipserv_User)) {
            throw new Myshipserv_Salesforce_Exception("Synchronisation of Consortia data is not authorised (no user)");
        }

        if (!$user->isShipservUser()) {
            throw new Myshipserv_Salesforce_Exception("Synchronisation of Consortia data is not authorised (no rights)");
        }

        return $user;
    }

    /**
     * Sends a pure JSON response sans HTML layout
     *
     * @param array $json
     *
     * @return bool
     */
    protected function replyJson(array $json)
    {
        // disable layout for JSON responses
        $this->_helper->layout()->disableLayout();

        if ($this instanceof Zend_Controller_Action) {
            $this->view->json = $json;

            $viewPaths = $this->view->getScriptPaths();
            $this->view->setScriptPath(
                implode(
                    DIRECTORY_SEPARATOR,
                    array(
                        APPLICATION_PATH, 'modules', 'consortia', 'views', 'scripts', 'sync'
                    )
                )
            );

            $this->renderScript('json.phtml');
            $this->view->setScriptPath($viewPaths);
        }

        return true;
    }

    /**
     * Replies with an error code and message
     *
     * @param   Exception $e
     * @param   int $httpCode
     *
     * @return  mixed
     * @throws  Zend_Controller_Response_Exception
     */
    protected function replyJsonError(Exception $e, $httpCode = 500)
    {
        /** @var $this Zend_Controller_Action */
        $this->getResponse()->setHttpResponseCode($httpCode);

        $salesforceId = null;
        if ($e instanceof Myshipserv_Consortia_Exception) {
            $salesforceId = $e->getSalesforceId();
        }

        return $this->replyJson(
            array(
                'success' => false,
                'salesforceId' => $salesforceId,

                'exception'     => array(
                    'code'      => $e->getCode(),
                    'type'      => get_class($e),
                    'message'   => $e->getMessage(),
                    'trace'     => $e->getTrace()
                )
            )
        );
    }

    /**
     * Validates consortia ID provided as an inbound parameter to the API
     *
     * @param   int     $consortiaId
     * @param   bool    $allowNone
     *
     * @return  int
     * @throws  Myshipserv_Consortia_Validation_Exception
     */
    protected function validateConsortiaId($consortiaId, $allowNone = false)
    {
        if ((strlen($consortiaId) === 0) and $allowNone) {
            return null;
        }

        try {
            Shipserv_Oracle_Consortia::getRecord($consortiaId);

        } catch (Exception $e) {
            throw new Myshipserv_Consortia_Validation_Exception("Consortia ID " . $consortiaId . " is not valid");
        }

        return (int) $consortiaId;
    }

    /**
     * Validates supplier TNIDs provided as inbound parameters to the API
     *
     * I have a feeling this has been implemented multiple times already though...
     *
     * @param   string|int|array    $supplierIds
     * @param   bool                $allowNone
     *
     * @return  array
     * @throws  Myshipserv_Consortia_Validation_Exception
     */
    protected function validateSupplierIds($supplierIds, $allowNone = false)
    {
        if (!is_array($supplierIds)) {
            $supplierIds = explode(',', $supplierIds);
        }

        foreach ($supplierIds as $key => $value) {
            $value = trim($value);

            if (strlen($value) === 0) {
                unset($supplierIds[$key]);
                continue;
            }

            if (!is_numeric($value)) {
                throw new Myshipserv_Consortia_Validation_Exception("Supplier TNID " . $value . " format is not valid");
            }

            // check if supplier exists
            $supplier = Shipserv_Supplier::getInstanceById($value, null, true);
            if (strlen($supplier->tnid) === 0) {
                throw new Myshipserv_Consortia_Validation_Exception("Supplier TNID " . $value . " is not found");
            }
        }

        if (!$allowNone and empty($supplierIds)) {
            throw new Myshipserv_Consortia_Validation_Exception("No supplier TNID provided");
        }

        foreach ($supplierIds as $key => $value) {
            $supplierIds[$key] = (int) $value;
        }

        return $supplierIds;
    }

    /**
     * Makes the necessary log records and sends out notifications when synchronisation fails
     *
     * @param   Exception               $e
     * @param   Myshipserv_Logger_Base  $logger
     * @param   string                  $description
     * @param   callable|null           $feedback
     *
     * @throws Exception
     */
    protected function handleSynchronisationException(Exception $e, Myshipserv_Logger_Base $logger, $description, callable $feedback = null)
    {
        $errSignature = get_class($e) . " (" . $e->getMessage() . ")";

        if ($e instanceof Myshipserv_Consortia_Exception) {
            $logger->log(
                $description . " " . $e->getSalesforceId() . " synchronisation error: " . $errSignature
            );

            if ($feedback) {
                // action to perform in case of a error that might be communicated back
                $feedback($e);
            }

        } else if ($e instanceof Myshipserv_Salesforce_Consortia_Exception) {
            $logger->log(
                "Salesforce error while synchronising " . $description . ": " . $errSignature
            );

            $e->sendNotification("Failed to synchronise Consortia " . $description);

        } else {
            $logger->log(
                "Unexpected error while synchronising " . $description . ": " . $errSignature
            );
        }
    }

    /**
     * Entry point to pulling supplier agreements from Salesforce
     *
     * A service which is normally called from Salesforce when the user clicks 'Save' on supplier agreements page
     *
     * @throws  Exception
     */
    public function pullSupplierAgreementsAction()
    {
        try {
            $this->verifySalesforceApiKey();
            $supplierIds = $this->validateSupplierIds($this->_getParam('supplierId'));

        } catch (Myshipserv_Consortia_Validation_Exception $e) {
            return $this->replyJsonError($e);
        }

        $logger = new Myshipserv_Logger_File('consortia-pull-supplier-agreements');
        $client = new Myshipserv_Salesforce_Consortia_Client_SupplierAgreement($supplierIds, $logger);

        $timeStart = microtime(true);
        $oldExecTime = ini_set('max_execution_time', 5 * 60);

        try {
            $syncResult = $client->sync();
            ini_set('max_execution_time', $oldExecTime);

        } catch (Exception $e) {
            ini_set('max_execution_time', $oldExecTime);

            // there is a change the error has a context of a Salesforce ID, so it needs to be communicated back
            $feedback = function (Myshipserv_Consortia_Exception $e) use ($client, $logger) {
                try {
                    $client->updateAgreementError($e);

                } catch (Exception $ee) {
                    $logger->log(
                        "Salesforce update error for supplier agreement " . $e->getSalesforceId() . ": " .
                        get_class($e) . ": " . $e->getMessage()
                    );

                    throw $ee;
                }
            };

            $this->handleSynchronisationException($e, $logger, "supplier agreements", $feedback);

            return $this->replyJsonError($e);
        }

        $result = array(
            'success'       => true,
            'supplierId'    => $supplierIds,
            'elapsed'       => round(microtime(true) - $timeStart, 2),
            'result'        => $syncResult
        );

        return $this->replyJson($result);
    }

    /**
     * Entry point to pushing consortia to Salesforce
     *
     * Normally called from Admin Gateway when the user clicks 'Save' on consortia page
     *
     * @throws  Exception
     */
    public function pushConsortiaAction()
    {
        try {
            $this->verifySalesforceApiKey();
        } catch (Myshipserv_Salesforce_Exception $e) {
            return $this->replyJsonError($e);
        }

        $logger = new Myshipserv_Logger_File('consortia-push-consortia');
        $client = new Myshipserv_Salesforce_Consortia_Client_Consortia($logger);

        $oldExecTime = ini_set('max_execution_time', 5 * 60);
        $timeStart = microtime(true);

        try {
            $syncResult = $client->sync();
            ini_set('max_execution_time', $oldExecTime);

        } catch (Exception $e) {
            ini_set('max_execution_time', $oldExecTime);
            $this->handleSynchronisationException($e, $logger, "consortia");
            return $this->replyJsonError($e);
        }

        return $this->replyJson(
            array(
                'success' => true,
                'elapsed' => round(microtime(true) - $timeStart, 2),
                'result'  => $syncResult
            )
        );
    }

    /**
     * Entry point to pushing buyer-supplier relationships in consortia to Salesforce
     *
     * Normally called from Admin Gateway when the user clicks 'Save' on relationships page
     *
     * @throws Exception
     */
    public function pushBuyerSupplierRelationshipsAction()
    {
        try {
            //@todo we might change $user = null it if it does not work properly
            $user = null;
            $this->verifySalesforceApiKey();
            $consortiaId = $this->validateConsortiaId($this->_getParam('consortiaId'));

        } catch (Myshipserv_Salesforce_Exception $e) {
            return $this->replyJsonError($e);
        } catch (Myshipserv_Consortia_Validation_Exception $e) {
            return $this->replyJsonError($e);
        }

        $logger = new Myshipserv_Logger_File('consortia-push-buyer-supplier-relationship');
        $client = new Myshipserv_Salesforce_Consortia_Client_BuyerSupplier($consortiaId, $logger, $user);

        $timeStart = microtime(true);
        $oldExecTime = ini_set('max_execution_time', 5 * 60);

        try {
            $syncResult = $client->sync();
            ini_set('max_execution_time', $oldExecTime);

        } catch (Exception $e) {
            ini_set('max_execution_time', $oldExecTime);

            $this->handleSynchronisationException($e, $logger, "buyer supplier relationships");
            return $this->replyJsonError($e);
        }

        return $this->replyJson(
            array(
                'success'       => true,
                'consortiaId'   => $consortiaId,
                'elapsed'       => round(microtime(true) - $timeStart, 2),
                'result'        => $syncResult
            )
        );
    }

    /**
     * Returns consortia bill for the given calendar month
     *
     * @return bool|mixed
     * @throws Exception
     * @throws Zend_Controller_Response_Exception
     */
    public function getMonthlyBillAction()
    {
        try {
            $this->verifySalesforceApiKey();

            $month = $this->_getParam('month');
            if (strlen($month) === 0) {
                throw new Myshipserv_Consortia_Validation_Exception("Calendar month not provided for the bill");
            }

            $consortiaId = $this->validateConsortiaId($this->_getParam('consortiaId'), true);
            $supplierIds = $this->validateSupplierIds($this->_getParam('supplierId'), true);

        } catch (Myshipserv_Salesforce_Exception $e) {
            return $this->replyJsonError($e);

        } catch (Myshipserv_Consortia_Validation_Exception $e) {
            return $this->replyJsonError($e);

        } catch (Exception $e) {
            return $this->replyJsonError($e);
        }

        $logger = new Myshipserv_Logger_File('consortia-get-monthly-bill');
        $timeStart = microtime(true);

        try {
            $client = new Myshipserv_Salesforce_Consortia_Client_Billing($logger, $month, $consortiaId, $supplierIds);
            $billRows = $client->getGroupedBilledPeriodOrders();

        } catch (Myshipserv_Consortia_Validation_Exception $e) {
            return $this->replyJsonError($e);
        }

        $result = array();
        foreach ($billRows as $row) {
            $resultRow = array(
                'supplierTnid'  => (int) $row['SUPPLIER_TNID'],

                'consortiaId'   => (int) $row['CONSORTIA_TNID'],
                'rateType'      => $row['RATE_TYPE'],
                'rateValue'     => (strlen($row['RATE'])) ? (float) $row['RATE'] : null,

                'supplierAgreementSalesforceId' => $row['SUPPLIER_AGREEMENT'],

                'bill'      => (float) $row['BILL'],
                'gmv'       => (float) $row['GMV'],
                'unitCount' => (float) $row['TOTAL_UNIT_COUNT']
            );

            $result[] = $resultRow;
        }

        return $this->replyJson(
            array(
                'success'      => true,
                'month'        => $month,
                'refundPeriod' => Myshipserv_Salesforce_Consortia_Client_Billing::REFUND_PERIOD_DAYS,
                'consortiaId'  => is_null($consortiaId) ? null : (int) $consortiaId,
                'supplierId'   => empty($supplierIds) ? null : $supplierIds,
                'elapsed'      => round(microtime(true) - $timeStart, 2),

                'bill' => $result
            )
        );
    }

    /**
     * Returns orders billed under Consortia model in the given calendar month
     *
     * @throws  Zend_Controller_Response_Exception
     * @throws  Exception
     */
    public function getBilledMonthOrdersAction()
    {
        try {
            $this->verifySalesforceApiKey();

            $month = $this->_getParam('month');
            if (strlen($month) === 0) {
                throw new Myshipserv_Consortia_Validation_Exception("Calendar month not provided for the bill");
            }

            $consortiaId = $this->validateConsortiaId($this->_getParam('consortiaId'), true);
            $supplierIds = $this->validateSupplierIds($this->_getParam('supplierId'), true);

        } catch (Myshipserv_Salesforce_Exception $e) {
            return $this->replyJsonError($e);

        } catch (Myshipserv_Consortia_Validation_Exception $e) {
            return $this->replyJsonError($e);

        } catch (Exception $e) {
            return $this->replyJsonError($e);
        }

        $logger = new Myshipserv_Logger_File('consortia-get-billed-month-orders');
        $timeStart = microtime(true);

        try {
            $client = new Myshipserv_Salesforce_Consortia_Client_Billing($logger, $month, $consortiaId, $supplierIds);
            $orderRows = $client->getBilledPeriodOrders();

        } catch (Myshipserv_Consortia_Validation_Exception $e) {
            return $this->replyJsonError($e);
        }

        $result = array();
        foreach ($orderRows as $row) {
            $resultRow = array(
				'orderEvent'	=> $row['EVENT_STATUS'],
				'chainId'	=> (int) $row['TRANSACTION_GROUP_ID'],
                'orderId'   => (int) $row['SHIPSERV_REF_NO'],
                'orderDate' => $row['SUBMITTED_DATE'],
				'previousSubmittedDate' => $row['PREVIOUS_DOC_SUBMITTED_DATE'],
				'initialSubmittedDate' => $row['ORDER_INITIAL_SUBMITTED_DATE'],
                'orderReferenceNumber' => $row['PO_REF_NO'],
                'buyerTnid'     => (int) $row['BUYER_TNID'],
                'buyerName'     => $row['BUYER_NAME'],
                'supplierTnid'  => (int) $row['SUPPLIER_TNID'],
				'vesselName'	=> $row['VESSEL_NAME'],
				'vesselIMO'		=> $row['IMO_NO'],
                'consortiaId'   => (int) $row['CONSORTIA_TNID'],
                'rateType'      => $row['RATE_TYPE'],
                'rateValue'     => (strlen($row['RATE'])) ? (float) $row['RATE'] : null,
                'supplierAgreementSalesforceId' => $row['SUPPLIER_AGREEMENT'],
                'orderBill'         => (float) $row['CREDITS'],
				'localCost'			=> (float) $row['TOTAL_COST'],
				'currencyStr'		=> $row['ORDER_CURRENCY'],
				'currencyRate'		=> (float) $row['CURRENCY_RATE'],
				'poCost'			=> (float) $row['TOTAL_COST_IN_USD'],
				'previousCost'			=> (float) $row['TOTAL_PREVIOUS_COST'],
                'orderValue'        => (float) $row['ADJUSTED_COST'],
                'orderUnitCount'    => 0
                // orderStatus?
            );

            $result[] = $resultRow;
        }

        return $this->replyJson(
            array(
                'success'     => true,
                'month'       => $month,
                'consortiaId' => is_null($consortiaId) ? null : (int) $consortiaId,
                'supplierId'  => empty($supplierIds) ? null : $supplierIds,
                'elapsed'     => round(microtime(true) - $timeStart, 2),

                'orders' => $result
            )
        );
    }
}