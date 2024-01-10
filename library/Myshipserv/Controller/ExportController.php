<?php
/**
* Controller resulting to Export to CSV fole format, 
*/
abstract class Myshipserv_Controller_ExportController extends Myshipserv_Controller_Action_SSO
{
    use Myshipserv_Controller_AuthExport;

    protected $params = array();
    protected static $isLoggedIn = false;

    /**
    * Make sure that we are logged in, if not it will throw a JSON error
    * @return undefined 
    */
    public function preDispatch()
    {
        set_time_limit(0);
        ini_set("memory_limit", "-1");


        if (!self::$isLoggedIn) {
            // This may be good to refactor not using die() eand echo
            $e = new Myshipserv_Exception_JSONException("You are not logged in", 1);
            $error =   array(
            	'error' => 'Export error',
                'code' => $e->getCode(),
                'type' => get_class($e),
                'message' => $e->getMessage(),
                'trace' => $e->getTrace()
            );
            print_r($error);
            die();
        }

        $this->params = $this->_getAllParams();
        //TODO do html strip tag, and sanitize
    }
    
    /**
     * Set file name for export, if not set it will use report.csv as default
     * @param string $fileName
     */
    protected function setExportFileName($fileName)
    {
    	if (is_string($fileName) && strlen($fileName) > 0) {
    		$this->exportFileName = $fileName;
    	}
    }

    /**
     * Convert array and changing the headers to readable headers for more readable excel export
     *
     * @param array $data
     * @param array $conversionArray
     * @return array
     */
    public function columnHeaders(array $data, array $conversionArray)
    {
        $result = array();

        foreach ($data as $record) {
            $tmpRecord = array();
            foreach ($conversionArray as $key => $value) {
                $tmpRecord[$value] = $record[$key];
            }
            array_push($result, $tmpRecord);
        }

        return $result;
    }
  
}