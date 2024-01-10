<?php

/**
 * An RFQ line item.
 */
class Shipserv_TnMsg_Rfq_LineItem
{
	private $description;
	private $quantity;
	private $uom;
	private $partType;
	private $partCode;
	private $comment;
	private $section;
	
	/**
	 * @param string $decription
	 * @param float $quantity
	 * @param string $uom Unit Of Measure
	 */
	public function __construct ($decription, $quantity, $uom)
	{
		// Description
		$this->decription = trim($decription);
		
		if ($this->decription == '')
		{
			throw new Exception("Description cannot be empty");
		}
		
		// Quantity
		$this->quantity = (float) $quantity;
		
		// UOM
		$this->uom = trim($uom);
		
		if ($this->uom == '')
		{
			throw new Exception("UOM cannot be empty");
		}
	}
	
	public function __get ($name)
	{
		if (property_exists($this, $name))
		{
			return $this->$name;
		}
		else
		{
			throw new Exception("Requested property does not exist");
		}
	}
	
	/**
	 * Part code, and type of code (optional).
	 *
	 * Type indicates whether code is an IMPA number,
	 * or a manufacturer's part number, etc.
	 *
	 * @param string $code
	 * @param string $type
	 */
	public function setPartNumber ($code = null, $type = null)
	{
		$type = trim($type);
		$code = trim($code);
		
		// Type specified without a code
		if ($code == '' && $type != '')
		{
			throw new Exception("Type specified without code");
		}
		
		if ($type == '')
		{
			$this->partType = null;
		}
		else
		{
			$this->partType = $type;
		}
		
		if ($code == '')
		{
			$this->code = null;
		}
		else
		{
			$this->partCode = $code;
		}
	}
	
	/**
	 * Line-item comment (optional).
	 *
	 * To remove comment, set to null, or empty string.
	 * 
	 * @param string $comment
	 */
	public function setComment ($comment = null)
	{
		$strComment = trim($comment);
		
		if ($strComment == '')
		{
			$this->comment = null;
		}
		else
		{
			$this->comment = $strComment;
		}
	}
	
	/**
	 * Line-item section (optional).
	 *
	 * Section describes the on-board equipment for which a part is ordered. E.g.
	 * when buying a main-engine spare, Section describes the main engine.
	 */
	public function setSection (Shipserv_TnMsg_Rfq_Section $section = null)
	{
		$this->section = $section;
	}
}
