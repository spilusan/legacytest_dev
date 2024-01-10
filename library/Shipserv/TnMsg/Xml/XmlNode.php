<?php

/**
 * Custom XML helper written to avoid *serious* shortcomings in PHP's native XML libraries.
 */
class Shipserv_TnMsg_Xml_XmlNode
{
	private $name;
	private $attributes = array();
	private $children = array();
	
	/**
	 * Create a new XML node.
	 * 
	 * @param string $name Element name
	 * @param array $attributes Map of attribute names and values
	 */
	public function __construct ($name, array $attributes = array())
	{
		$this->name = (string) $name;
		
		foreach ($attributes as $k => $v)
		{
			$this->addAttribute($k, $v);
		}
	}
	
	/**
	 * Add an attribute to this node.
	 * 
	 * @param string $name
	 * @param string $value
	 */
	public function addAttribute ($name, $value)
	{
		$this->attributes[$name] = (string) $value;
		return $this;
	}
	
	/**
	 * Add a child node to this node.
	 */
	public function addChild (Shipserv_TnMsg_Xml_XmlNode $node)
	{
		$this->children[] = $node;
		return $this;
	}
	
	/**
	 * Add textual content to this node.
	 */
	public function addText ($text)
	{
		$this->children[] = (string) $text;
		return $this;
	}
	
	public function getAttributes()
	{
		return $this->attributes;
	}
	
	public function getChildren()
	{
		return $this->children;
	}
	
	/**
	 * Generate XML string.
	 *
	 * @return string
	 */
	public function toXml ()
	{
		$xml = "<" . $this->name;
		foreach ($this->attributes as $k => $v)
		{
			$vEsc = $this->escapeXml($v);
			$xml .= " $k=\"$vEsc\"";
		}
		$xml .= ">";
		
		foreach ($this->children as $c)
		{
			if (is_string($c))
			{
				$xml .= $this->escapeXml($c);
			}
			else
			{
				$xml .= $c->toXml();
			}
		}
		
		$xml .= "</" . $this->name . ">";
		
		return $xml;
	}
	
	private function escapeXml ($str)
	{
		$escStr = htmlspecialchars($str, ENT_COMPAT);
		$escStr = str_replace("'", '&apos;', $escStr);
		return $escStr;
	}
}
