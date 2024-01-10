<?php

class Myshipserv_View_Helper_Uri extends Zend_View_Helper_Abstract
{
	
	/**
	 * Sets up the Uri helper
	 *
	 * @access public
	 * @return Myshipserv_View_Helper_Uri
	 */
	public function uri ()
	{
		return $this;
	}
	
	/**
	 * 
	 * 
	 * @access public
	 * @return string Javascript code to initialise Mixpanel
	 */
	public function init ()
	{
		
	}
	
	/**
	* Returns the path to the images directory, taking into account public asset versioning
	*/
	public function imagePath()
	{
		$config = $GLOBALS['application']->getBootstrap()->getOptions();
		return '/images/' . Myshipserv_Config::getCachebusterTagAddition();
	}

	public function sanitise ($string)
	{
		$temp   = preg_replace('/(\W){1,}/', '-', $string);
		$string = strtolower(preg_replace('/-$/', '', $temp));

		return $string;
	}

	public function obfuscate($url) {
		return $this->strToHex($url);
	}

	public function deobfuscate($url) {
		return $this->hexToString($url);
	}

	private function strToHex( $string )
	{
	    $hex='';
	    for ($i=0; $i < strlen($string); $i++)
	    {
	        $hex .= dechex(ord($string[$i]));
	    }
	    return $hex;		
	}

	private function hexToString( $hex )
	{
	    $string='';
	    for ($i=0; $i < strlen($hex)-1; $i+=2)
	    {
	        $string .= chr(hexdec($hex[$i].$hex[$i+1]));
	    }
	    return $string;		
	}
}