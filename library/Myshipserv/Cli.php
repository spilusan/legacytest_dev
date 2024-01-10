<?php
/**
 * A base class for command line scripts implementing functions like accessing command line parameters
 *
 * @author  Yuriy Akopov
 * @date    2013-09-03
 * @story   S8133
 */

abstract class Myshipserv_Cli {
    const
        // keys for restulting params array
        PARAM_GROUP_UNDEFINED = 'undefined',  // parameters defined and recognised, key-value pairs with keys as per specification
        PARAM_GROUP_DEFINED   = 'defined',    // undefined parameters, plain array of values
        // keys for param definition array
        PARAM_DEF_KEYS      = 'keys',       // parameter keys accepted in the command line, scalar value or array of them
        PARAM_DEF_NAME      = 'name',       // parameter name (if not specified, a value will be created in the resulting array for every key defined for this param)
        PARAM_DEF_NOVALUE   = 'novalue',    // if true, parameter is considered to be a boolean flag (true if supplied, false otherwise) with no value following it
        PARAM_DEF_OPTIONAL  = 'optional',   // if true, parameter is considered optional. Doesn't have any effect on "no value" params.
        PARAM_DEF_DEFAULT   = 'default',    // default value for optional parameters if they're missing
        PARAM_DEF_REGEX     = 'regex'       // regular expression to validate parameter value. Is not applied to default values assigned to missing optional parameters
    ;

    /**
     * Returns supported command line parameters
     *
     * @return  array
     */
    protected function getParamDefinition() {
        // defining sample set of parameters - needs to be overridden in descendant classes
        /*
        return array(
            // optional flag parameter, if -d specified, will be true, otherwise will be false
            array(
                self::PARAM_DEF_KEYS    => '-d',
                self::PARAM_DEF_NOVALUE => true
            ),
            // optional filename parameters, is supposed to be supplied as -f FILENAME or --file FILENAME
            // if omitted, will have default value
            array(
                self::PARAM_DEF_KEYS => array(
                    '--file',
                    '-f'
                ),
                self::PARAM_DEF_OPTIONAL    => true,
                self::PARAM_DEF_DEFAULT     => '/tmp/foobar.txt',
                self::PARAM_DEF_REGEX       => '#^(\w+/){1,2}\w+\.\w+$#'
            )
        );
        */

        return array();
    }

    /**
     * Displays help message - typically when the script is run with no parameters
     */
    public function displayHelp() {
        // defining default help message - needs to be overridden in descendant classes
        print 'Override this function to display an explanation of your command line parameters' . PHP_EOL;
    }

    /**
     * Validates and returns the command line parameters
     *
     * @return  array
     * @throws  Exception
     */
    protected function getParams() {
        global $argv;
        $arguments = $argv;         // leaving the original $argv intact in case something else depends on it
        array_shift($arguments);    // removing the script name
        array_shift($arguments);    // removing the environment name (parameter required by bootstrap)

        $definitions = $this->getParamDefinition();
        $params = array(
            self::PARAM_GROUP_DEFINED     => array(),
            self::PARAM_GROUP_UNDEFINED   => array()
        );

        // looping through supplied parameters matching them against the definitions we have
        while (($p = array_shift($arguments)) !== null) {
            $definitionFound = false;

            foreach ($definitions as $paramDef) {
                // requesting/building an array of parameter keys
                $keys = $paramDef[self::PARAM_DEF_KEYS];
                if (!is_array($keys)) {
                    $keys = array($keys);
                }

                if (!in_array($p, $keys)) {
                    // current parameter doesn't match the definition, continue with the next definition
                    continue;
                }

                $definitionFound = true;    // found the definition

                // now checking if the parameter value fits the description found in the matched definition

                if ($paramDef[self::PARAM_DEF_NOVALUE]) {
                    // parameter is not supposed to have a value, it's a boolean flag with key only
                    // if the flag is found, its value is true, otherwise it's false
                    $value = true;
                } else {
                    // parameter is supposed to have a value, so shifting the arguments to pop it
                    $value = array_shift($arguments);
                    if ((strlen($value) === 0) and !$paramDef[self::PARAM_DEF_OPTIONAL]) {
                        // no value available for a mandatory param
                        throw new Exception("Parameter " . $p . " is mandatory and is supposed to have a value supplied");
                    }

                    if (strlen($paramDef[self::PARAM_DEF_REGEX])) {
                        if (!preg_match($paramDef[self::PARAM_DEF_REGEX], $value)) {
                            throw new Exception("Parameter " . $p . " value \"" . $value . "\" is invalid");
                        }
                    }
                }

                if (array_key_exists(self::PARAM_DEF_NAME, $paramDef)) {
                    // assigning the value to the key requested in definition
                    $params[self::PARAM_GROUP_DEFINED][$paramDef[self::PARAM_DEF_NAME]] = $value;
                } else {
                    // assigning the value in the resulting array to every key defined for this parameter
                    foreach ($keys as $k) {
                        $params[self::PARAM_GROUP_DEFINED][$k] = $value;
                    }
                }

                break;  // no need to proceed with other definitions
            }

            if (!$definitionFound) {
                // current parameter is not defined, so collecting it into another bucket
                $params[self::PARAM_GROUP_UNDEFINED][] = $p;
            }
        }

        // all the supplied parameters were processed and they were fine. now we need:
        // a) for any mandatory parameters not supplied and about if they're found
        // b) add default values for optional parameters if they were omitted

        foreach ($definitions as $paramDef) {
            // requesting/building an array of parameter keys
            if (array_key_exists(self::PARAM_DEF_NAME, $paramDef)) {
                $keys = array($paramDef[self::PARAM_DEF_NAME]);
            } else {
                $keys = $paramDef[self::PARAM_DEF_KEYS];
                if (!is_array($keys)) {
                    $keys = array($keys);
                }
            }

            // checking if we have a value for every key defined
            foreach ($keys as $k) {
                if (!array_key_exists($k, $params[self::PARAM_GROUP_DEFINED])) {
                    // value not found - what to do next depends on parameter optional flags
                    if ($paramDef[self::PARAM_DEF_NOVALUE]) {
                        $params[self::PARAM_GROUP_DEFINED][$k] = false;
                    } else {
                        if ($paramDef[self::PARAM_DEF_OPTIONAL]) {
                            $params[self::PARAM_GROUP_DEFINED][$k] = $paramDef[self::PARAM_DEF_DEFAULT];
                        } else {
                            if ($paramDef[self::PARAM_DEF_KEYS]) {
                                $paramDesc = implode(' | ', $paramDef[self::PARAM_DEF_KEYS]);
                            } else {
                                $paramDesc = $paramDef[self::PARAM_DEF_KEYS];
                            }

                            throw new Exception("Mandatory parameter " . $paramDesc . " is missing");
                        }
                    }
                }
            }
        }

        return $params;
    }

    public function __construct() {
        error_reporting(E_ALL & ~E_STRICT & ~E_NOTICE); // removing rubbish output which we have way too much in our legacy code obstructing the CLI script output

        if (!(php_sapi_name() === 'cli')) {
            throw new Exception(__CLASS__ . ' is only supposed to be called from the command line environment');
        }
    }

    public abstract function run();

    public function output($message) {
        print "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL;
    }
}