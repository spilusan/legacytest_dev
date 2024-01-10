<?php
class Logger
{
	private function __construct () { }
	
	public static function log ($msg, $noNewLine = false)
	{
		echo date('Y-m-d H:i:s') . "\t" . $msg;
		if( $noNewLine == false ) echo "\n";
	}
	
	public static function logSimple ($msg, $noNewLine = false)
	{
		echo $msg;
		if( $noNewLine == false ) echo "\n";
	}	
	
	public static function newLine ()
	{
		echo "\n";
	}	
}

