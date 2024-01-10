<?php
/**
 * RequireJS loader
 * @param module - the name of a requireJS module relative to the /js/modules directory, omitting the '.js' suffix
 * @example $this->requirejs()->addDefaultModule('mynamespace/mymodule') or addModule() will add a js module to be loaded. $this->requirejs()->render() is needed in the layout for it to work.
 * mymodule.js should then be used to load in further requirable javascript modules
 * using require like this will allow the optimiser to work correctly
 */
class Zend_View_Helper_Requirejs extends Zend_View_Helper_Abstract
{
	private static $modules = array();
	private static $definitions = array();
    private static $paths = array(
        /*css path is added during the render() method*/
        'jquery'        => 'backbone/lib/jquery.min.1.8.3',
        'jqueryui'      => 'backbone/lib/jquery-ui-1.10.3',
        'underscore'    => 'backbone/lib/underscore',
		'Backbone'      => 'backbone/lib/backbone',
        'handlebars'    => 'backbone/lib/handlebars',
        'modal'    		=> 'backbone/lib/modal',
        'libs'			=> 'backbone/lib',
        'templates'		=> '/webreporter/js/modules/backbone',
        'components'	=> 'backbone/src/components',
        'text'          => 'backbone/lib/require/text'
    );
    
	private static $defaultModule;

	private $_view;
	private $scriptIncluded;

	function __construct()
	{
        
	}

	public function setView(Zend_View_Interface $view) 
	{
		$this->_view = $view;
	}

	/**
	 * Set the default module
	 */
	public function addDefaultModule($module) {
		self::$defaultModule = $module;
		$this->addModule($module);
		return $this;
	}

	/**
	 * Add module to the queue
	 * 
	 * @param string $module
	 */
	public function addModule($module)
	{
		array_push( self::$modules, $module );
		//Make the first module the default, defaultModule...
		if (empty(self::$defaultModule)) {
			self::$defaultModule = $module;
		}
		return $this;
	}

	/**
	 * Directly define an inline module, for example some config
	 * @param string $name the module name by which this will be accessible through requirejs
	 * @param string $script the javascript making up the module, probably a literal object or a function
	 * @example
	 * $this->requirejs()->addDefinition('shipserv/config','{ foo: "bar" }');
	 * ...will result in a module called shipserv/config which is an object with a foo property
	 */
	public function addDefinition($name, $script) {
		self::$definitions[$name] = (strlen($script) > 0) ? $script: 'null';
		return $this;
	}
    
    /**
     * Add a path definition, as per http://requirejs.org/docs/api.html#config-paths
     * 
     */
    public function addPath($name, $path) {
		self::$paths[$name] = $path;
		return $this;
    }
    
    /**
     * Add multiple paths as an associative array
     */
    public function addPaths($paths) {
        foreach($paths as $name => $path) {
            $this->addPath($name, $path);
        }
    }

	public function requirejs(  ) 
	{
		return $this;		
	}

	public function render() {
		if (count(self::$modules) > 0) {
			$config = $GLOBALS['application']->getBootstrap()->getOptions();

			$modulesPath = '/webreporter/js/' . Myshipserv_Config::getCachebusterTagAddition() . 'modules/';
			$cssPath = '/css/' . Myshipserv_Config::getCachebusterTag();
            
            self::$paths['css'] = $cssPath;

			if ($config['shipserv']['cdn']['use'] == 1) 
			{
	            $modulesPath = $config['shipserv']['cdn']['javascript'] . $modulesPath;
				$cssPath = $config['shipserv']['cdn']['css'] . $cssPath;
	        }

			$headscript = $this->_view->headScript();
			$headscript->setAllowArbitraryAttributes(true); //Require will optimiser better using a data-head attribute to load the initial module

			$output = "var require = {\n";
			$output .= "	baseUrl:'$modulesPath',\n";
            
			$output .= "	paths: {\n";
			foreach( self::$paths as $name => $path ) {
				$output .= "		'$name': '$path',";
			}
			$output = rtrim($output, ',');
			$output .= "	},\n";
                
			$output .= "	waitSeconds: 500\n"; //May allow override of this, but ultimately if some module doesn't load, you're probably SOL
			
			if (!empty(self::$definitions)) {
				$output .= "	,define: {\n";
				foreach( self::$definitions as $name => $definition ) {
					$output .= "		'$name': $definition,";
				}
				$output = rtrim($output, ',');
				$output .= "	}\n";
			}
			
			if (count(self::$modules) > 1) {
				//$headscript->appendScript("var require = {baseUrl: '$modulesPath', paths:{ css: '$cssPath' } };");
				$output .= "	,deps:[\n";
				$modules = array_slice(self::$modules, 1, count(self::$modules ) );
				$count = count($modules);
				for($i = 0; $i < $count; $i++) {
					$output .= "	'" . $modules[$i] . "'";
					if ($i < ($count - 1)) {
						$output .= ",";
					}
					$output .= "\n";
				}
				$output .= "	]\n";
			}

			$output .= "};\n";

			$attr = array();
			if (!empty(self::$defaultModule)) {
				$attr['data-main'] = self::$modules[0];
			}

			foreach( array_reverse(self::$definitions) as $name => $definition ) {
				$headscript->prependScript("define('" . $name . "', " . $definition . ");");
			}
			
			$headscript->prependFile('/js/modules/require.js', 'text/javascript', $attr);
			$headscript->prependScript( $output );
		}

		return $this;
	}
}
