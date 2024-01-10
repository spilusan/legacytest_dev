<?php
/*
* Application Usage Dashboard drilldown management, export different reports to excel
*/
class Spr_ExportController extends Myshipserv_Controller_ExportController
{

    /**
     * Set default document properties, like export filename
     * {@inheritDoc}
     * @see Myshipserv_Controller_Action_SSO::init()
     *
     */
    public function init()
    {
        $this->setExportFileName('supplier-usage-report.csv');
        parent::init();
    }

    /**
     * Sample call: /reports/export/supplier-performance-order?type=common-items&page=2&tnid=52323&byb=10529&period=month
     * @return array|bool
     */
    public function orderAction()
    {

        $type = $this->getRequest()->getParam('type', null);

        if ($type === null) {
            return $this->_replyCsvError(new Myshipserv_Exception_JSONException("Report type is missing, incomplete URL", 500), 500);
        }

        // As backend requires item count for all data export 999999 pases should be enough
        $pageSize = $this->getRequest()->getParam('pagesize', 10) * $this->getRequest()->getParam('page', 999999);

        $params = array(
            'tnid' => $this->getRequest()->getParam('tnid'),
            'byb' => $this->getRequest()->getParam('byb'),
            'period' => $this->getRequest()->getParam('period'),
            'page' => 1,
            'pagesize' => $pageSize
        );

        if ($params['period'] === null) {
            return $this->_replyCsvError(new Myshipserv_Exception_JSONException("period parameter is mandantory", 500), 500);
        }

        $reporService = Myshipserv_Spr_OrderGateway::getInstance();
        $reply = $reporService->getReport($type, $params);

        if ($reporService->getStatus() !== true || $reply === null) {
            if ($reply === null) {
                return $this->_replyCsvError(new Myshipserv_Exception_JSONException("Invalid report type"), 404);
            }

        }

        if (isset($reply['data'])) {
            if (is_array($reply['data'])) {
                if (count($reply['data']) > 0) {
                    return $this->_replyCsv(Myshipserv_Spr_ExportConverter::convert($reply['data'], $type));
                }
            }
        }

        return $this->_replyCsv(array());

    }
}