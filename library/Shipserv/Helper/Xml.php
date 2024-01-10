<?php

class Shipserv_Helper_Xml extends Zend_Controller_Action_Helper_Abstract
{
	/**
	* Transforms a SimpleXML object into an array (need to move into a helper)
	* 
	*/
	public static function simpleXml2Array ($xml)
	{
		if ($xml instanceof SimpleXMLElement)
		{
			$attributes = $xml->attributes();
			
			foreach ($attributes as $k => $v)
			{
				if ($v)
				{
					$a[$k] = (string) $v;
				}
			}
			
			$x   = $xml;
			$xml = get_object_vars($xml);
		}
		
		if (is_array($xml))
		{
			if (count($xml) == 0) // zero children
			{
				$r = (string) $x; // for CDATA
			}
			
			foreach ($xml as $key => $value)
			{
				$r[$key] = self::simpleXml2Array($value);
			}
			
			if (isset($a))
			{
				$r['@'] = $a;    // Attributes
			}
			
			return $r;
		}
		
		return (string) $xml;
	}
}