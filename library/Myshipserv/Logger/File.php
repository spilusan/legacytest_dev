<?php 
/**
 * Class responsible to writing log to a physical file
 * 
 * @author Elvir <eleonard@shipserv.com>
 */
class Myshipserv_Logger_File extends Myshipserv_Logger_Base
{
	protected $folder = "/prod/logs/pages/";
	protected $type;
	protected $fileSystem;
	protected $uniqueId;
	
	function __construct( $type ) 
	{
		$this->type = $type;
		
		$this->uniqueId = substr(strtoupper(md5(uniqid(''))), 0, 4);
		
		
		if ($this->checkFolders() )
		{
			$this->fileSystem = fopen( $this->getFilename(),"a+");
			@chmod($this->getFilename(), 0775);	
		}
	}
	
	/**
	 * Making sure that the folder is exist
	 * 
	 * @return true or false if *un*success
	 */
	public function checkFolders()
	{
		// check log folder on the server
		if (!is_dir($this->folder)) {
			if (mkdir($this->folder, 0777, true) === false) {
				return false;
			}
		}

		if (!is_dir($this->getFolder())) {
			if (mkdir($this->getFolder(), 0777, true) === false) {
				return false;
			}
		}
		return true;
	}
	
	/**
	 * logging the message and writing it to the log file (physical) file
	 * 
	 * @param   string  $string
	 * @param   mixed   $data
	 */
	public function log($string, $data = null)
	{
		$msg = parent::log($string, $data);
		$this->saveToFile( $msg );
	}
	
	private function saveToFile( $message )
	{
		@chmod($this->getFilename(), 0775);
		@fwrite($this->fileSystem, $message);
	}

	public function getFolder()
	{
		return $this->folder . $this->type;
	}
	
	public function getFilename()
	{
		return $this->getFolder() ."/". date("Y-m-d") . ".log";
	}	
}
