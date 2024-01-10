<?php 
/**
 * Class responsible to writing log to a physical file
 * 
 * @author Elvir <eleonard@shipserv.com>
 */
class Myshipserv_Logger_Db extends Myshipserv_Logger_Base
{
	protected $folder = "pages_job";
	protected $type;
	protected $fileSystem;
	protected $uniqueId;
	
	function __construct( $type ) 
	{
		$this->type = $type;
		$this->uniqueId = substr(strtoupper(md5(uniqid(''))), 0, 4);
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
		$msg = trim($msg);
		$sql = "
			MERGE INTO pages_job USING DUAL ON (job_type = :type)
				WHEN MATCHED THEN
					UPDATE SET
						job_description=:command,
						job_run=SYSDATE
				WHEN NOT MATCHED THEN
					INSERT
						(
							job_type, 
							job_run, 
							job_description
						)
					VALUES
						(
							:type, 
							sysdate, 
							:command
						)
		";
		
		$db = parent::getSSReport2Db();
		$db->query($sql, array('type' => $this->type, 'command' => $msg));
	}
}