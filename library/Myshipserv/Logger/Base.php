<?php 
class Myshipserv_Logger_Base extends Shipserv_Object
{
	public $buffer = "";
	protected $uniqueId; 
	
	function __construct()
	{
		$this->uniqueId = substr(strtoupper(md5(uniqid(""))), 0, 4);
		
	}
	
	public function log($string, $data = null)
	{	
		$msg = "[" . $this->uniqueId . "]  " . date("Y-m-d H:i:s") . "\t" . $string . "\n";
		if( $data !== null )
		{
				
			$msg .= "[" . $this->uniqueId . "]\t" . "---------------------------------------------------------------------------\n";
			foreach(explode(PHP_EOL, $data) as $line ){
				$msg .= "[" . $this->uniqueId . "]\t" . $line . "\n";
			}
			$msg .= "[" . $this->uniqueId . "]\t" ."---------------------------------------------------------------------------\n";
		}
		$this->buffer .= $msg;
		return $msg;
	}
	
	public function getBuffer()
	{
		return $this->buffer;
	}
}

