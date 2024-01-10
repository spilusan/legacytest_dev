<?php

class Shipserv_Unicode
{
	public function UTF8entities ($content = "")
	{
		$contents = $this->unicode_string_to_array($content);
		$swap = "";
		$iCount = lg_count($contents);
		for ($o = 0; $o < $iCount; $o++)
		{
			$contents[$o] = $this->unicode_entity_replace($contents[$o]);
			$swap .= $contents[$o];
		}
		//return $swap;
		return mb_convert_encoding($swap,"UTF-8"); //not really necessary, but why not.
	}

	public function unicode_string_to_array ($string)
	{
		$strlen = mb_strlen($string);
		
		while ($strlen)
		{
			$array[] = mb_substr($string, 0, 1, "UTF-8");
			$string  = mb_substr($string, 1, $strlen, "UTF-8");
			$strlen  = mb_strlen($string);
		}
		
		return $array;
	}

	public function unicode_entity_replace ($c)
	{
		$h = ord(mb_substr($c, 0, 1, 'UTF-8'));   
		if ($h <= 0x7F) {
			return $c;
		} else if ($h < 0xC2) {
			return $c;
		}
	   
		if ($h <= 0xDF) {
			$h = ($h & 0x1F) << 6 | (ord(mb_substr($c, 1, 1, 'UTF-8')) & 0x3F);
			$h = "&#" . $h . ";";
			return $h;
		} else if ($h <= 0xEF) {
			$h = ($h & 0x0F) << 12 | (ord(mb_substr($c, 1, 1, 'UTF-8')) & 0x3F) << 6 | (ord(mb_substr($c, 2, 1, 'UTF-8')) & 0x3F);
			$h = "&#" . $h . ";";
			return $h;
		} else if ($h <= 0xF4) {
			$h = ($h & 0x0F) << 18 | (ord(mb_substr($c, 1, 1, 'UTF-8')) & 0x3F) << 12 | (ord(mb_substr($c, 2, 1, 'UTF-8')) & 0x3F) << 6 | (ord(mb_substr($c, 3, 1, 'UTF-8')) & 0x3F);
			$h = "&#" . $h . ";";
			return $h;
		}
	}
}
