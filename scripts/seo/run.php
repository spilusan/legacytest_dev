<?php

define('SCRIPT_PATH', dirname(__FILE__));
require_once dirname(dirname(SCRIPT_PATH)) . '/application/Bootstrap-cli.php';
require_once getcwd() . '/lib/SEO/Sitemap/Generator.php';
require_once getcwd() . '/lib/SEO/Sitemap/IO.php';
require_once getcwd() . '/lib/SEO/Sitemap/Templates.php';
require_once getcwd() . '/lib/SEO/LinkingModule/Footer.php';
require_once getcwd() . '/lib/SEO/RelatedCategory/Search.php';



class Cl_Main extends Myshipserv_Cli
{

	const PARAM_KEY_MODE = 'c';
	const PARAM_CATEGORY_ID = 'i';
	const MODE_GENERATE_INITIAL_SITEMAP = 'generate-initial-sitemap';
	const MODE_GENERATE_SITEMAP = 'generate-sitemap';
	const MODE_INITIALISE_RELATED_CATEGORIES = 'initialise-related-categories';
	const MODE_INITIALISE_FOOTER_CATEGORY_LINKING_MODULE = 'initialise-footer-category-linking-module';
	
	protected $db = null;

	protected function getParamDefinition() {
		// please see displayHelp() function for parameter description
		return array(
				array(
						self::PARAM_DEF_NAME        => self::PARAM_KEY_MODE,
						self::PARAM_DEF_KEYS        => '-c',
						self::PARAM_DEF_OPTIONAL    => false,
						self::PARAM_DEF_REGEX       =>
						'/^(' . implode('|', array(
								self::MODE_GENERATE_INITIAL_SITEMAP,
								self::MODE_GENERATE_SITEMAP,
								self::MODE_INITIALISE_RELATED_CATEGORIES,
								self::MODE_INITIALISE_FOOTER_CATEGORY_LINKING_MODULE
						)) . ')$/',
				),
				array(
						self::PARAM_DEF_NAME        => self::PARAM_CATEGORY_ID,
						self::PARAM_DEF_KEYS        => '-i',
						self::PARAM_DEF_OPTIONAL    => true,
						self::PARAM_DEF_REGEX       => '/^([a-zA-Z0-9])+$/',
				)
		);
	}
	
	
	public function displayHelp() {
		print implode(PHP_EOL, array(
				"Usage: " . basename(__FILE__) . " ENVIRONMENT [OPTIONS]",
				"",
				"ENVIRONMENT has to be development|testing|test2|ukdev|production",
				"",
				"Available options:",
				"   -c          Mandatory option - mode to operate in.",
				"               Allowed modes are:",
				"                 " . self::MODE_GENERATE_INITIAL_SITEMAP .   ": Create initial sitemaps",
				"                 " . self::MODE_GENERATE_SITEMAP .   ": Create subsequent sitemap",
				"                 " . self::MODE_INITIALISE_RELATED_CATEGORIES .   ": initialise related category page",
				"                 " . self::MODE_INITIALISE_FOOTER_CATEGORY_LINKING_MODULE .   ": initialise footer category linking module sitemap",
				"",
		)) . PHP_EOL;
			

		//	echo "-- invalid command:".$method."}\n";
		//	echo "-- usage: php run.php development|testing|production -c generate-initial-sitemap|generate-sitemap|initialise-related-categories|initialise-footer-category-linking-module \n";
	}
	

	
	
	
	public function run ()
	{
		$this->db = $GLOBALS['application']->getBootstrap()->getResource('db');
		
		// No max execution time
		ini_set('max_execution_time', 0);

		// No upper memory limit
		ini_set('memory_limit', -1);
		
		
		// get the mode
		try {
			$params = $this->getParams();
		} catch (Exception $e) {
			$this->output("Parameter error: " . $e->getMessage());
			$this->displayHelp();
			return 1;
		}
		
		$params = $params[self::PARAM_GROUP_DEFINED];
		$categoryName = $params[self::PARAM_CATEGORY_ID];

		$cronLogger = new Myshipserv_Logger_Cron('SEO_' . $params[self::PARAM_KEY_MODE] );
		$cronLogger->log();
		
		switch ($params[self::PARAM_KEY_MODE]) {
							
			case self::MODE_GENERATE_INITIAL_SITEMAP:
				
				Logger::log("Start - Creating INITIAL sitemap for search engine");
				$collection = new SEO_Sitemap_IO();
				$collection->checkIfDirExist();
				
				$generator = new SEO_Sitemap_Generator();
				$generator->createSitemapForSupplier($collection);
				$generator->createSitemapForCategory($collection);
				$generator->createSitemapForCategoryByCountry($collection);
				$generator->createSitemapForBrand($collection);
				$generator->createSitemapForBrandByCountry($collection);
				
				$generator->createSitemapForCountry($collection);
				$generator->createSitemapForPort($collection);
				$generator->createSitemapForProduct($collection);
				
				$generator->createSitemapForHomepage($collection);
				$collection->updateIndexFile();
				Logger::log("End");
				
				break;
		
			case self::MODE_GENERATE_SITEMAP:
				Logger::log("Start - Creating monthly sitemap for search engine");
				$collection = new SEO_Sitemap_IO();
				$collection->checkIfDirExist();
					
				$generator = new SEO_Sitemap_Generator();
				$generator->createSitemapForNewSupplier($collection);
					
				$collection->updateIndexFile();
				Logger::log("End");
				
				break;
				
			case self::MODE_INITIALISE_RELATED_CATEGORIES:
				$application = new SEO_RelatedCategory_Search;
				if( $categoryName != "" )
					$application->run($categoryName);
				else
					$application->run();
				
				break;
			
			case self::MODE_INITIALISE_FOOTER_CATEGORY_LINKING_MODULE:
				Logger::log("Start - Initialising");
				$db = $GLOBALS['application']->getBootstrap()->getResource('db');
				$main = new SEO_LinkingModule_Footer();
				if( $categoryName != "" )
					$main->populate($categoryName);
				else
					$main->populate();
					
				Logger::log("End");
				break;
		}
	}
}

$script = new Cl_Main();
$status = $script->run();

exit($status);