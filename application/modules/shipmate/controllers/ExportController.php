<?php

/*
* Export data to CSV
*/
class Shipmate_ExportController extends Myshipserv_Controller_ExportController
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

        $this->setExportFileName('sir-pct-report-' . date('Ymd') . '.csv');

        if (!$user) {
            return $this->_replyCsvError(new Myshipserv_Exception_JSONException("This page requires you to be logged in", 500), 500);
        }

    }

    /**
     * Index action for main entry point
     * 
     * @return array|bool
     */
    public function indexAction()
    {
        $sessionActiveCompany = Myshipserv_Helper_Session::getActiveCompanyNamespace();
        $tnid = (int)$sessionActiveCompany->id;
        if ($sessionActiveCompany->type !== 'v') {
            return $this->_replyCsvError(new Myshipserv_Exception_JSONException("Selected company type must be a supplier", 500), 500);
        }

        
        $lowerdate = $this->getRequest()->getParam('lowerdate', null);
        $upperdate  = $this->getRequest()->getParam('upperdate', null);

        //validate mandantory input
        if ($lowerdate === null || $upperdate === null) {
            return $this->_replyCsvError(new Myshipserv_Exception_JSONException("Report type, or lowerdate or  upperdate is missing, incomplete URL", 500), 500);
        }

        $reportService = Myshipserv_ReportService_Gateway::getInstance(Myshipserv_ReportService_Gateway::REPORT_SIR3);
        $result = $reportService->forward(
            'pages-impression-stats',
            array(
                'tnid' => $tnid,
                'lowerdate' => $lowerdate,
                'upperdate' => $upperdate
            )
        );

        $convertedData = $this->columnHeaders(
            $result,
            array(
                'tnid'  => 'Supplier TNID',
                'view-date'  => 'View Date',
                'source'  => 'Source',
                'browser'  => 'Browser',
                'url-of-referrer'  => 'URL of Referrer',
                'email-viewed'  => 'Email View',
                'contact-viewed'  => 'Contact/Telephone View',
                'tnid-viewed'  => 'TNID Number View',
                'website-clicked'  => 'Website Click Through'
            )
        );

        return $this->_replyCsv($convertedData);
    }

}
