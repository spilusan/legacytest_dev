<?php

class Myshipserv_Webreporter_Init
{
    private static $_instance;
    protected $config;
    
    /**
    * Get the single instance of the object
    * @return object
    */
    public static function getInstance()
    {
        if (null === static::$_instance) {
            static::$_instance = new static();
        }
        
        return static::$_instance;
    }

	/**
	* Protected classes to prevent creating a new instance 
	* @return unknown
	*/
    protected function __construct()
    {
    	$this->_initWebreporter();
    }

	/**
	* Protected classes to prevent creating a new instance 
	* @return unknown
	*/
    private function __clone()
    {

    }

    /**
    * Init WebReporter sessions and registry
    * @return unknown
    */
    protected function _initWebreporter()
    {
        set_time_limit(0);
		$this->config = new Zend_Config_Ini(APPLICATION_PATH . '/configs/webreporter/application.ini', $_SERVER['APPLICATION_ENV']);

        $registry = Zend_Registry::getInstance();

        $registry['app'] = $registry['app_namespace']  = $this->config->app->namespace;

        $registry['app_ini']        = $appIni       = APPLICATION_PATH . $this->config->app->ini;
        $registry['app_messages']   = $appMessages  = APPLICATION_PATH . $this->config->app->messages;
        $registry['app_log_prefix'] = $appLogPrefix = $this->config->app->log->prefix;
        $registry['app_env_name']   = $_SERVER['APPLICATION_ENV'];

        // Resolve the app host
        $requestScheme = 'http';
        if (array_key_exists('REQUEST_SCHEME', $_SERVER) === true && empty($_SERVER['REQUEST_SCHEME']) === false) {
            $requestScheme = $_SERVER['REQUEST_SCHEME'];
        }
        $registry['app_host'] = $requestScheme . '://' . $_SERVER['HTTP_HOST'] . '/';

        // Get WebReporter app config
        $registry['app_config_base'] = $appConfigBase = new Zend_Config_Ini($appIni, 'base');
        $registry['app_config_messages'] = new Zend_Config_Ini($appMessages, 'messages');

        // Resolve the list of reports
        $reports = explode(';', $appConfigBase->app->report->list);
        $appReportList = array();
        foreach ($reports as $report) {
            $tmp = explode('#', $report);
            $appReportList[] = array('rptcode' => $tmp[1], 'rptname' => $tmp[0],);
        }

        $registry['app_report_list'] = $appReportList;
        $registry['app_report_rows'] = $appConfigBase->app->report->rows;

        $reportCsvParamList = explode(';', $appConfigBase->app->report->csv->params->list);
        $reportCsvParamJson = explode(';', $appConfigBase->app->report->csv->params->json);

        // Get all WebReporter app report configs
        $appReportInfo = array();
        foreach ($appReportList as $report) {
            $reportConfig     = new Zend_Config_Ini($appIni, strtolower($report['rptcode']));
            $reportName       = $report['rptcode'];
            $reportCode       = $reportConfig->report->code;
            $reportTdTotal    = $reportConfig->report->td->total;
            $reportTdHeader   = $reportConfig->report->td->header;
            $reportCsvFile    = $reportConfig->report->csv->file;
            $reportColumnMax  = $reportConfig->report->column->max;
            $reportColumnSort = $reportConfig->report->column->sort;

            $reportTotalJson    = explode(';', $reportConfig->report->total->json);
            $reportColumnList   = explode(';', $reportConfig->report->column->list);
            $reportColumnJson   = explode(';', $reportConfig->report->column->json);
            $reportColumnWidth  = explode(';', $reportConfig->report->column->width);

            $reportTdColumn = array();
            foreach ($reportConfig->report->td->column as $column) {
                $reportTdColumn[] = $column;
            }

            $appReportInfo[$report['rptcode']] = array(
                'rptname'         => $reportName,
                'rptcode'         => $reportCode,
                'rptcolmax'       => $reportColumnMax,
                'rptcollist'      => $reportColumnList,
                'rptcoljson'      => $reportColumnJson,
                'rptcolsort'      => $reportColumnSort,
                'rpttotjson'      => $reportTotalJson,
                'rpttdtotal'      => $reportTdTotal,
                'rpttdheader'     => $reportTdHeader,
                'rptcolwidth'     => $reportColumnWidth,
                'rpttdcolumn'     => $reportTdColumn,
                'rptcsvfile'      => $reportCsvFile,
                'rptcsvparamlist' => $reportCsvParamList,
                'rptcsvparamjson' => $reportCsvParamJson,
            );
        }
        $registry['app_report_info'] = $appReportInfo;

        $registry['app_config_env'] = $appConfigEnv = $this->config;
        $registry['app_log_path']   = $appLogPath   = $appConfigEnv->app->log->path;

        $registry['app_csv_path'] = $appConfigEnv->app->csv->path;
        $registry['app_cas_path'] = $appConfigEnv->app->cas->path;

        Zend_Controller_Action_HelperBroker::addPrefix('Service_Helper');

        // Set the loggers
        date_default_timezone_set('UTC');
        $file = $appLogPath . $appLogPrefix . date('dMY');
        $writer = new Zend_Log_Writer_Stream($file . '.log');
        $registry['app_use_log'] = $appUseLog = new Zend_Log($writer);

        $appUseLog->info('BOOTSTRAP');
    }

}

