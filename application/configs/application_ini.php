<?php
/**
 * We are cachning the pharsed application.ini for quicker page response, as this file
 * is big and takes time to pharse it request by request.
 * The application ini pharsing can be cached by turning this PHP on pubic/index.php
 * $application = new Zend_Application(
 *   APPLICATION_ENV,
 *   APPLICATION_PATH . '/configs/application.ini' << change it to application_ini.php
 * )
 * ;
 * Currently we use only localhost 11211, but later we may change it to environment specific
 * 
 * @var Memcache $memcache
 */

/*
 * Set the environment varables here for memcache settings, We cannot get it from application.ini yet
 * but other functions in pages are using the application.ini so it is important to do the changes in both places
 * Currently for all environment is the same, It will automatically fall back to production if the value is not in the list
 */

//Do not cache for the following environments

//@todo add ini location (maybe per environment, if we agreed where)

$iniFile = '/prod/application.ini';

switch ($_SERVER['APPLICATION_ENV']) {
	case 'development':
	case 'ukdev':
	case 'ukdev2':
	case 'ukdev3':
	case 'ukdev4':
	case 'ukdev5':
	case 'ukdev6':
	case 'ukdev7':
	case 'ukdev8':
		$options = new Zend_Config_Ini($iniFile, $_SERVER['APPLICATION_ENV']);
		$config = $options->toArray();
		return $config;
		break;
	default:
		$shipservMemcaceSettings = array(
			'production' => array(
			'host' => 'localhost',
			'port' => 11211
			)
		);
		
		$shipservMemcaceSetting =  (array_key_exists($_SERVER['APPLICATION_ENV'], $shipservMemcaceSettings)) ? $shipservMemcaceSettings[$_SERVER['APPLICATION_ENV']]: $shipservMemcaceSettings['production'];
		
		$memcache = new Memcache;
		
		$memcacheConnection = $memcache->connect($shipservMemcaceSetting['host'], $shipservMemcaceSetting['port']);
		if ($memcacheConnection) {
			$config = $memcache->get('appini_' . $_SERVER['APPLICATION_ENV']);
			
			if (!$config) {

				if (!file_exists($iniFile)) {
					trigger_error('Application configuration error, cannot load ini file', E_USER_ERROR);
				}

				$options = new Zend_Config_Ini($iniFile, $_SERVER['APPLICATION_ENV']);
				$config = $options->toArray();
				$memcache->set('appini_'.$_SERVER['APPLICATION_ENV'], $config);
			}
		} else {

			if (!file_exists($iniFile)) {
				trigger_error('Application configuration error, cannot load ini file', E_USER_ERROR);
			}

			$options = new Zend_Config_Ini($iniFile, $_SERVER['APPLICATION_ENV']);
			$config = $options->toArray();
		}

		return $config;
		break;
}
