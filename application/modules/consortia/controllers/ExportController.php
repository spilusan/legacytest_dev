<?php
/*
* Buyer export controller
*/

class Consortia_ExportController extends Myshipserv_Controller_ExportController
{

    /**
     * Set default document properties, like export filename
     * {@inheritDoc}
     * @see Myshipserv_Controller_Action_SSO::init()
     *
     */
    public function init()
    {


        parent::init();

        $user = Shipserv_User::isLoggedIn();

        $this->setExportFileName('transaction-report-' . date('Ymd') . '.csv');

        if (!$user) {
            return $this->_replyCsvError(new Myshipserv_Exception_JSONException("This page requires you to be logged in", 500), 500);
        } else {
            if ($user->canAccessTransactionReport() === false) {
                return $this->_replyCsvError(new Myshipserv_Exception_JSONException("You do not have right to access this page", 500), 500);
            }
        }

    }

    /**
     * Index action for main entry point
     * @return array|bool
     */
    public function indexAction()
    {
        $sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
        $reporService = Myshipserv_ReportService_Gateway::getInstance(Myshipserv_ReportService_Gateway::REPORT_SPR_CONSORTIA);


        $lowerdate = $this->getRequest()->getParam('lowerdate', null);
        $upperdate  = $this->getRequest()->getParam('upperdate', null);

        //validate mandantory input
        if ($lowerdate === null || $upperdate === null) {
            return $this->_replyCsvError(new Myshipserv_Exception_JSONException("Report type, or lowerdate or  upperdate is missing, incomplete URL", 500), 500);
        }

        $result = $reporService->forward($sessionActiveCompany->id . '/export-csv', array('lowerdate' => $lowerdate, 'upperdate' => $upperdate));

        if (isset($result['pos'])) {
            $convertedData = $this->columnHeaders(
                $result['pos'],
                array(
                    'po_int_ref_no' => 'PO Internal Ref No.',
                    'submitted_date' => 'Submitted Date',
                    'po_ref' => 'PO Reference',
                    'subject' => 'Subject',
                    'byb_tnid' => 'Buyer Branch',
                    'byb_name' => 'Buyer Name',
                    'parent_byb_tnid' => 'Parent Buyer TNID',
                    'parent_byb_name' => 'Parent Buyer Name',
                    'spb_tnid' => 'Supplier Branch',
                    'spb_name' => 'Supplier Name',
                    'parent_spb_tnid' => 'Parent Supplier TNID',
                    'parent_spb_name' => 'Parent Supplier Name',
                    'imo' => 'IMO',
                    'ship' => 'Ship',
                    'po_value' => 'PO Value'
                )
            );

        } else {
            return $this->_replyCsvError(new Myshipserv_Exception_JSONException("Backend server error"), 200);
        }

        return $this->_replyCsv($convertedData);
    }

}