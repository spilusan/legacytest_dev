<?php

class Shipserv_Match_Measure extends Shipserv_Object {
	
	public static function writeVarSizeToFile($varname, $var, $file){
                return false;
//   		$serialized = serialize($var);
//   		$size = (strlen($serialized) / 1024);
//   		unset($serialized);
//
//   		file_put_contents($file, $varname . " : " . $size . "\n", FILE_APPEND);
   		

   	}
}