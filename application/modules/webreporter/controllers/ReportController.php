<?php

class Webreporter_ReportController extends Myshipserv_Controller_Action
{
    /**
    * Init session variables
    * @return unknown
    */
    public function init()
    {
        // services have no views
        parent::init();
        Myshipserv_Webreporter_Init::getInstance();
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
    }

    /**
    * Index action for JSON responses
    * @return unknown
    */
    public function indexAction()
    {
        // Get global variables
        $registry      = Zend_Registry::getInstance();
        $appUseLog     = $registry['app_use_log'];
        $appMessages   = $registry['app_config_messages'];
        $appNamespace  = $registry['app_namespace'];
        $appConfigEnv  = $registry['app_config_env'];
        $appReportInfo = $registry['app_report_info'];
        // $appCustomReportInfo = $registry['app_custom_report_info'];

        $params = json_decode($this->getRequest()->getPost('_params'), true);

        session_id($params['_session_id']);
        if (isset($_SESSION) === false) { 
            _session_start();
        }

        $casRest = Myshipserv_CAS_CasRest::getInstance();
        $appUsername = ($casRest->casCheckLoggedIn()) ? $casRest->getUserName() : null;

        $parameters = array(
            'app_code'        => $params['_app_code'],
            'rpt_code'        => $params['_rpt_code'],
            'rpt_type'        => $params['_rpt_type'],
            'rpt_title'       => $params['_rpt_title'],
            'sort_field'      => $params['_sort_field'],
            'sort_is_asc'     => $params['_sort_is_asc'],
            'rows_per_page'   => $params['_rows_per_page'],
            'action'          => $params['_action'],
            'prev_page'       => $params['_prev_page'],
            'curr_page'       => $params['_curr_page'],
            'vessel_id'       => $params['_vessel_id'],
            'vessel_name'     => $params['_vessel_name'],
            'to_date'         => $params['_to_date'],
            'rpt_is_rfrsh'    => $params['_rpt_is_rfrsh'],
            'rpt_is_cnsldt'   => $params['_rpt_is_cnsldt'],
            'rpt_is_inctst'   => $params['_rpt_is_inctst'],
            'byb_tnid'        => $params['_byb_tnid'],
            'byb_name'        => $params['_byb_name'],
            'spb_tnid'        => $params['_spb_tnid'],
            'spb_name'        => $params['_spb_name'],
            'user_name'       => $params['_user_name'],
            'from_date'       => $params['_from_date'],
            'ord_int_no'      => $params['_ord_int_no'],
            'ord_ref_no'      => $params['_ord_ref_no'],
            'rate_code'       => $params['_rate_code'],
            'cntc_code'       => $params['_cntc_code'],
            'cntc_name'       => $params['_cntc_name'],
            'rfq_is_dec'      => $params['_rfq_is_dec'],
            'ord_is_acc'      => $params['_ord_is_acc'],
            'ord_is_dec'      => $params['_ord_is_dec'],
            'ord_is_poc'      => $params['_ord_is_poc'],
            'rfq_cutoff_days' => $params['_rfq_cutoff_days'],
            'qot_cutoff_days' => $params['_qot_cutoff_days'],
            'ord_cutoff_days' => $params['_ord_cutoff_days'],
            'ord_prchsr_code' => $params['_ord_prchsr_code'],
            'rpt_as_of_date'  => $params['_rpt_as_of_date'],
            'num_fmt'         => $params['_num_fmt'],
            'date_range'      => $params['_date_range'],
            'ord_srch'        => $params['_ord_srch'],
            'cntc_doc'        => 'NA',
        );

        // Check if POST is available else redirect to index
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $appUseLog->info($appMessages->en->RedirectNonPostRequest . $appUsername);
            $this->redirect('/');
            exit;
        }

        $data = array();
        $exception = '';

        $apiId  = mt_rand(10000, 99999);
        $apiUrl = (string)$appConfigEnv->app->url->api;

        $logMsg = $_SESSION[$appUsername]['logPrefix'] . 'id = ' . $apiId;
        $appUseLog->info($logMsg . ' ; url = ' . $apiUrl);
        $appUseLog->info($logMsg . ' ; post params = ' . json_encode($parameters));

        $service = new Zend_Http_Client($apiUrl, array('timeout' => 1800, 'useragent' => $appNamespace,));
        $service->setParameterPost($parameters);

        try {
            $data = $service->request(Zend_Http_Client::POST)->getBody();
            $data = json_decode($data, true);
            //In case of report can be served, lets log thhe proper activity
            $user = Shipserv_User::isLOggedIn();
            if ($user) {
                switch ($parameters['rpt_code']) {
                    case 'GET-ALL-ORD':
                        $user->logActivity(Shipserv_User_Activity::WEBREPORTER_ALL_POS, 'PAGES_USER', $user->userId, $user->email);
                        break;
                    case 'GET-ALL-RFQ':
                        $user->logActivity(Shipserv_User_Activity::WEBREPORTER_ALL_RFQS, 'PAGES_USER', $user->userId, $user->email);
                        break;
                    case 'GET-ORD-SUPPLIERS':
                        $user->logActivity(Shipserv_User_Activity::WEBREPORTER_POS_BY_SUPPLIER, 'PAGES_USER', $user->userId, $user->email);
                        break;
                    case 'GET-ORD-VESSELS':
                        $user->logActivity(Shipserv_User_Activity::WEBREPORTER_POS_BY_VESSEL, 'PAGES_USER', $user->userId, $user->email);
                        break;
                    case 'GET-SPB-ANALYSIS':
                        $user->logActivity(Shipserv_User_Activity::WEBREPORTER_SUPPLIER_ANALYSIS, 'PAGES_USER', $user->userId, $user->email);
                        break;
                    case 'GET-TXN-SUPPLIERS':
                        $user->logActivity(Shipserv_User_Activity::WEBREPORTER_TRANSACTIONS_BY_SUPPLIER, 'PAGES_USER', $user->userId, $user->email);
                        break;
                    case 'GET-TXN-VESSELS':
                        $user->logActivity(Shipserv_User_Activity::WEBREPORTER_TRANSACTIONS_BY_VESSEL, 'PAGES_USER', $user->userId, $user->email);
                        break;
                    default:
                        //We do not log other events
                        break;
                }
            }
        } catch (Zend_Exception $e) {
            $exception = $e->getMessage();
            $appUseLog->info($logMsg . ' ; post exception = ' . $exception);
        }

        if (strtolower($data['status']) !== 'ok') {

            $appUseLog->info($logMsg . ' ; post data = ' . json_encode($data));

            // Email error to someone
            $mail = new Zend_Mail();

            $mail->setBodyText(
                $logMsg . ' ; url = ' . $apiUrl . "\n" .
                $logMsg . ' ; post params = ' . json_encode($parameters) . "\n" .
                $logMsg . ' ; post data = ' . json_encode($data) . "\n" .
                $logMsg . ' ; post exception = ' . $exception . "\n"
            );

            $mail->setFrom($appConfigEnv->api->error->email->from);
            $mail->addTo($appConfigEnv->api->error->email->to);
            $mail->setSubject($appConfigEnv->api->error->email->subject);
            $mail->send();

        }

        $data['app_domain'] = $appConfigEnv->app->domain;

        // Get the report details, columns, etc
        $data['rpt_info'] = array();
        if (empty($appReportInfo[$parameters['rpt_code']]) === true ||
            isset($appReportInfo[$parameters['rpt_code']]) === false) {
            $data['status'] = $appMessages->en->InvalidReport;
            $appUseLog->info($appMessages->en->InvalidReport . $appUsername);
        } else {
            $data['rpt_info'] = $appReportInfo[$parameters['rpt_code']];
        }

        if ($parameters['action'] === 'CSV') {
        	if (empty($data['rpt_info']) === true || isset($data['rpt_info']) === false) {
                $data['status'] = $appMessages->en->InvalidReport;
                $appUseLog->info($appMessages->en->InvalidReport . $appUsername);
            } else {
            	$this->csv($apiId, $parameters, $data);
            }
        }

        echo json_encode($data);
    }

    /**
    * Error handler
    * @param integer $errno   Error number
    * @param string  $errstr  Error message text
    * @param string  $errfile The file where the error was raised
    * @param integer $errline The error line mumber 
    * @return boolean
    */
    public function myErrorHandler($errno, $errstr, $errfile, $errline)
    {
        // Get global variables
        $registry  = Zend_Registry::getInstance();
        $appUseLog = $registry['app_use_log'];

        $casRest = Myshipserv_CAS_CasRest::getInstance();
        $appUsername = ($casRest->casCheckLoggedIn()) ? $casRest->getUserName() : null;

        $logMsg = $_SESSION[$appUsername]['logPrefix'] . 'php = ' . "[$errno] $errstr in file: $errfile, in line: $errline";
        $appUseLog->info($logMsg);

        return true;
    }

    /**
    * get CSV
    * @param integer $apiId Application id
    * @param array $parameters Application id
    * @param array $data Application id
    * @return nothing dies, and echos JSON string
    */
    protected function csv($apiId, $parameters, $data)
    {
        // Get global variables
        $registry     = Zend_Registry::getInstance();
        $appUseLog    = $registry['app_use_log'];
        $appCsvPath   = $registry['app_csv_path'];
        $appConfigEnv = $registry['app_config_env'];

        date_default_timezone_set('UTC');
        set_error_handler(array(&$this, 'myErrorHandler'));

        // Get session namespace
        $casRest = Myshipserv_CAS_CasRest::getInstance();
        $appUsername = ($casRest->casCheckLoggedIn()) ? $casRest->getUserName() : null;

        $rptInfo = $data['rpt_info'];

        $logMsg = $_SESSION[$appUsername]['logPrefix'] . 'id = ' . $apiId . ' ; ';
        $appUseLog->info($logMsg . 'msg = Creating CSV file...');

        // Create CSV file
        $isConsolidated = '';
        if ($parameters['rpt_is_cnsldt'] === '1') {
            $isConsolidated = '-consolidated';
        }

        $rptCsvFileFolder = 'Id' . mt_rand(1000000, 9999999);
        mkdir($appCsvPath . $rptCsvFileFolder, 0777, true);

        $rptCsvFilename = date('dMY') . '-' . $parameters['byb_tnid'] . '-' . $rptInfo['rptcsvfile'] . $isConsolidated . '.csv';
        $rptCsvFilePath = $rptCsvFileFolder . '/' . $rptCsvFilename;

        $rptZipFilename = date('dMY') . '-' . $parameters['byb_tnid'] . '-' . $rptInfo['rptcsvfile'] . $isConsolidated . '.zip';
        $rptZipFilePath = $rptCsvFileFolder . '/' . $rptZipFilename;

        $rptCsvFile = fopen($appCsvPath . $rptCsvFilePath, 'w');

        // Determine the CSV delimiter
        $delimiter = ',';
        if ($parameters['num_fmt'] === 'EU') {
            $delimiter = ';';
        }

        // CSV report parameter header
        $csvHeader = '';
        $rptCsvParamList = $rptInfo['rptcsvparamlist'];

        foreach ($rptCsvParamList as $header) {
            $csvHeader = $csvHeader . '"' . $header . '"' . $delimiter;
        }

        $csvHeader = substr($csvHeader, 0, -1);
        $csvHeader = $csvHeader . "\n";

        fwrite($rptCsvFile, $csvHeader);
        fflush($rptCsvFile);

        // CSV report parameter header data
        $csvRow = '';
        $rptCsvParamJson = $rptInfo['rptcsvparamjson'];

        foreach ($rptCsvParamJson as $index) {
            $value = '';

            if (array_key_exists($index, $parameters) === true) {
                $value = $parameters[$index];

                if ($index === 'date_range') {
                    switch ($parameters[$index]) {
                        case 'CSTMDTS':
                            $value = 'Custom Dates';
                            break;
                        case 'PRV01MO':
                            $value = 'Previous Month';
                            break;
                        case 'PRV03MO':
                            $value = 'Previous 3 Months';
                            break;
                        case 'PRV12MO':
                            $value = 'Previous 12 Months';
                            break;
                        case 'PRVYEAR':
                            $value = 'Previous Year';
                            break;
                        case 'PRVWEEK':
                            $value = 'Previous Week';
                            break;
                        default:
                            $value = '';
                            break;
                    }
                }

                if ($index === 'num_fmt') {
                    $value = ($parameters[$index] === 'EN' ? 'EN Format' : 'EU Format');
                }
                if ($index === 'rpt_is_cnsldt') {
                    $value = ($parameters[$index] === 1 ? 'Yes' : 'No');
                }
                if ($index === 'rpt_is_inctst') {
                    $value = ($parameters[$index] === 1 ? 'Yes' : 'No');
                }
            }

            $csvRow = $csvRow . '"' . $value . '"' . $delimiter;
        }

        $csvRow = substr($csvRow, 0, -1);
        $csvRow = $csvRow . "\n" . "\n";

        fwrite($rptCsvFile, $csvRow);
        fflush($rptCsvFile);

        // CSV report total records and total values
        $firstCol = 0;
        $csvTotal = '';

        $rawCountFilename = $appCsvPath . $rptCsvFileFolder . '/' . $data['rows_count']['raw_count'];

        // changing from HTTP to file system
        $rowsCountLinkFile = $this->getFileUrlForBackend($data['rows_count']['link']);

        $status = file_put_contents($rawCountFilename, fopen($rowsCountLinkFile, 'r'));

        if ($status !== false) {
            $rawCountFile = fopen($rawCountFilename, 'r');

            while (feof($rawCountFile) === false) {
                $row = json_decode(fgets($rawCountFile), true);

                if (is_array($row) === true && empty($row) === false) {
                    if (array_key_exists('rows_count', $row) === true) {
                        $value = trim($row['rows_count']);
                        $value = str_replace('"', '', $value);

                        if ($parameters['num_fmt'] === 'EN') {
                            $value = number_format($row['rows_count'], 0, '.', ',');
                        }
                        if ($parameters['num_fmt'] === 'EU') {
                            $value = number_format($row['rows_count'], 0, ',', '.');
                        }

                        $csvTotal = $csvTotal . '"Total Records of ' . $value . '"' . $delimiter;
                        break;
                    }
                }
            }
        }

        $rawTotalFilename = $appCsvPath . $rptCsvFileFolder . '/' . $data['rows_total']['raw_total'];

        // changing from HTTP to file system
        $rowsTotalLinkFile = $this->getFileUrlForBackend($data['rows_total']['link']);

        $status = file_put_contents($rawTotalFilename, fopen($rowsTotalLinkFile, 'r'));

        if ($status !== false) {
            $rawTotalFile = fopen($rawTotalFilename, 'r');
            $rptTotJson = $rptInfo['rpttotjson'];

            while (feof($rawTotalFile) === false) {
                $row = json_decode(fgets($rawTotalFile), true);

                if (is_array($row) === true && empty($row) === false) {
                    foreach ($rptTotJson as $index) {
                        $value = '';

                        if (array_key_exists($index, $row) === true) {
                            $value = trim($row[$index]);
                            $value = str_replace('"', '', $value);

                            /* Check for "ave", "cost", "conv", "pct" */
                            if (strpos($index, 'ave')  !== false ||
                                strpos($index, 'pct')  !== false ||
                                strpos($index, 'cost') !== false ||
                                strpos($index, 'conv') !== false) {

                                if ($parameters['num_fmt'] === 'EN') {
                                    $value = number_format($row[$index], 2, '.', ',');
                                }
                                if ($parameters['num_fmt'] === 'EU') {
                                    $value = number_format($row[$index], 2, ',', '.');
                                }

                            } else {

                                if ($parameters['num_fmt'] === 'EN') {
                                    $value = number_format($row[$index], 0, '.', ','); 
                                }
                                if ($parameters['num_fmt'] === 'EU') {
                                    $value = number_format($row[$index], 0, ',', '.');
                                }
                            }
                        }

                        if ($firstCol === 0) {
                            $firstCol++;
                        } else {
                            $csvTotal = $csvTotal . '"' . $value . '"' . $delimiter;
                        }
                    }

                    break;
                }
            }
        }

        $csvTotal = substr($csvTotal, 0, -1);
        $csvTotal = $csvTotal . "\n" . "\n";

        fwrite($rptCsvFile, $csvTotal);
        fflush($rptCsvFile);

        // CSV report main header
        $csvHeader  = '';
        $rptColList = $rptInfo['rptcollist'];

        foreach ($rptColList as $header) {
            $csvHeader = $csvHeader . '"' . $header . '"' . $delimiter;
        }

        $csvHeader = str_replace('<currency>', $parameters['rate_code'], $csvHeader);
        $csvHeader = substr($csvHeader, 0, -1);
        $csvHeader = $csvHeader . "\n";

        fwrite($rptCsvFile, $csvHeader);
        fflush($rptCsvFile);

        // CSV report main header data
        $rows = 0;

        $rawFilename = $appCsvPath . $rptCsvFileFolder . '/' . $data['rows']['raw'];

        // changing from HTTP to file system
        $rowsLinkFile = $this->getFileUrlForBackend($data['rows']['link']);

        $status = file_put_contents($rawFilename, fopen($rowsLinkFile, 'r'));

        if ($status !== false) {
            $rawFile = fopen($rawFilename, 'r');
            $rptColJson = $rptInfo['rptcoljson'];



            while (feof($rawFile) === false) {
            	//try{
                $row    = json_decode(fgets($rawFile), true);
            	//}catch(Zend_Json_Exception $e){
            	//	echo fgets($rawFile);
            	//	echo $e->getMessage();
            	//}
                $csvRow = '';

                if (is_array($row) === true && empty($row) === false) {
                    foreach ($rptColJson as $index) {
                        $value = '';

                        if (array_key_exists($index, $row) === true) {
                            $value = trim($row[$index]);
                            $value = str_replace('"', '', $value);

                            // Make these as numeric strings and not just numeric
                            if ($index === 'spbtnid' || $index === 'bybtnid') {
                                $value = " " . $value;
                            }

                            if ($index === 'ordsts') {
                                switch ($row[$index]) {
                                    case 'ACC':
                                        $value = 'Accepted';
                                        break;
                                    case 'ACK':
                                        $value = 'Acknowledged';
                                        break;
                                    case 'CON':
                                        $value = 'Confirmed'; 
                                        break;
                                    case 'DEC':
                                        $value = 'Declined';
                                        break;
                                    case 'NEW':
                                        $value = 'Awaiting';
                                        break;
                                    case 'OPN':
                                        $value = 'Open';
                                        break;
                                    default:
                                        $value = '';
                                }
                            }

                            /* Check for "subdt" */
                            if (strpos($index, 'subdt') !== false) {
                                list($value) = explode(' ', $row[$index]);
                            }

                            /* Check for "ave", "cost", "conv", "pct" */
                            if (strpos($index, 'ave')  !== false ||
                                strpos($index, 'pct')  !== false ||
                                strpos($index, 'cost') !== false ||
                                strpos($index, 'conv') !== false) {

                                if ($parameters['num_fmt'] === 'EN') {
                                    $value = number_format($row[$index], 2, '.', ',');
                                }
                                if ($parameters['num_fmt'] === 'EU') {
                                    $value = number_format($row[$index], 2, ',', '.');
                                }
                            }

                            /* Check for "cnt" */
                            if (strpos($index, 'cnt') !== false && $index !== 'cntcode') {

                                if ($parameters['num_fmt'] === 'EN') {
                                    $value = number_format($row[$index], 0, '.', ',');
                                }
                                if ($parameters['num_fmt'] === 'EU') {
                                    $value = number_format($row[$index], 0, ',', '.');
                                }
                            }
                        }

                        $csvRow = $csvRow . '"' . $value . '"' . $delimiter;
                    }
                }

                if (strlen($csvRow) > $rptInfo['rptcolmax']) {
                    $rows++;

                    $csvRow = substr($csvRow, 0, -1);
                    $csvRow = $csvRow . "\n";

                    fwrite($rptCsvFile, $csvRow);
                    fflush($rptCsvFile);
                }
            }

            fclose($rawFile);
        }

        fclose($rptCsvFile);
        $appUseLog->info($logMsg . 'msg = Closing CSV file...');

        if ($rows > 0) {
            $appUseLog->info($logMsg . 'msg = Creating CSV Zip file...');

            // Zip CSV file
            $zip = new ZipArchive();
            $zip->open($appCsvPath . $rptZipFilePath, ZIPARCHIVE::CREATE);
            $zip->addFile($appCsvPath . $rptCsvFilePath, $rptCsvFilename);
            $zip->close();

            $appUseLog->info($logMsg . 'msg = Closing CSV Zip file...');

            unlink($rawFilename);
            $appUseLog->info($logMsg . 'msg = Deleting CSV Raw file...');

            unlink($appCsvPath . $rptCsvFilePath);
            $appUseLog->info($logMsg . 'msg = Deleting CSV file...');

            $csvLink = (string)$appConfigEnv->app->url->base;
            $csvLink = str_replace('public', '', $csvLink);
            $csvLink = $csvLink . '/report/download?p=' . $rptZipFilePath . '&h=' . $this->calculateHashForCsv($rptZipFilePath);

            $appUseLog->info($logMsg . 'csv zip = ' . $csvLink);

            $data = array('csv' => $rptCsvFilename, 'link' => $csvLink, 'status' => 'ok');
        } else {
        	$data = array();
        }

        echo json_encode($data);
        die();
    }

    /**
    * Download action (Deprecated, instead of this action we are using the server URL to downoad the file)
    * @return nothing exits, and echo file with download header
    */
    public function downloadAction()
    {
        $registry     = Zend_Registry::getInstance();
        $appConfigEnv = $registry['app_config_env'];
        $p = $_GET['p'];

        // check hash
        if ($this->calculateHashForCsv($p) != $_GET['h']) {
            throw Exception("Invalid request");
        }

        $file = $appConfigEnv->app->csv->path . $p;

        if (file_exists($file)) {
            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename='.basename($file));
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Pragma: public');
            header('Content-Length: ' . filesize($file));
            ob_clean();
            flush();
            readfile($file);
            exit;
        }
    }

    /**
    * Calcluate an MD5 Hash according to a filename
    * @param string $filename File Name
    * @return string The hash
    */
    protected function calculateHashForCsv($filename)
    {
    	return md5($filename . 'webreporter123');
    }

    /**
    * Get the file for the backend
    * @param string $file file name
    * @param string $type File type, default raws
    * @return string Full file with path
    */
    protected function getFileForBackend($file, $type = 'raws')
    {

    	$registry     = Zend_Registry::getInstance();
       	$appConfigEnv = $registry['app_config_env'];

    	if ($type == 'raws') {
    		return $appConfigEnv->app->backend->raw->path . basename($file);
    	}
    }

    /**
    * Get the file URL for the backend. This will replace the above version, instead of loading file from a shared folder load from service. This will change the application to be stateless
    * @param string $file file name
    * @param string $type File type, default raws
    * @return string Full file with path
    */
    protected function getFileUrlForBackend($file, $type = 'raws')
    {

        $registry     = Zend_Registry::getInstance();
        $appConfigEnv = $registry['app_config_env'];

        if ($type == 'raws') {
            $p = basename($file);
            return $appConfigEnv->app->url->api . '/download?p=' . urlencode($p) . '&h=' . $this->calculateHashForCsv($p);
        }
    }

}
