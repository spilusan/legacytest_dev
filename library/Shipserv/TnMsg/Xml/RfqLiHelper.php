<?php

/**
 * Helper class which wraps a line item as DB row and provides parameters for
 * TN XML generation.
 */
class Shipserv_TnMsg_Xml_RfqLiHelper
{
	private $liColPrefix = Shipserv_Oracle_Util_PagesRfqTableDef::LI_COL_PREFIX;
	private $lineItem;
	
	/**
	 * @param array $lineItem Row of Pages RFQ line item table as associative array
	 */
	public function __construct (array $lineItem)
	{
		$this->lineItem = $lineItem;
	}
	
	/**
	 * @return string
	 */
	public function getLineItemNo ()
	{
		// DB row always has line item number
		return (string) $this->lineItem[$this->liColPrefix . 'LINE_ITEM_NO'];
	}
	
	/**
	 * @return string
	 * @throws Exception if not set
	 */
	public function getProductDesc ()
	{
		$desc = $this->lineItem[$this->liColPrefix . 'PRODUCT_DESC'];
		if ($desc != '')
		{
			return $desc;
		}
		else
		{
			throw new Exception("Missing product description");
		}
	}
	
	/**
	 * @return string
	 * @throws Exception if not set
	 */
	public function getQuantity ()
	{
		$n = $this->lineItem[$this->liColPrefix . 'QUANTITY'];
		if ($n > 0)
		{
			return (string) $n;
		}
		else
		{
			throw new Exception("Missing quantity");
		}
	}
	
	/**
	 * @return string or null
	 */
	public function getIdType ()
	{
		return $this->lineItem[$this->liColPrefix . 'ID_TYPE'];
	}
	
	/**
	 * @return string or null
	 */
	public function getIdCode ()
	{
		return $this->lineItem[$this->liColPrefix . 'ID_CODE'];
	}
	
	/**
	 * @return string or null
	 */
	public function getUnit ()
	{
		return $this->lineItem[$this->liColPrefix . 'UNIT'];
	}
	
	/**
	 * @return string or null
	 */
	public function getComment ()
	{
		return $this->lineItem[$this->liColPrefix . 'COMMENTS'];
	}
	
	/**
	 * @return string or null
	 */
	public function getConfDeptType ()
	{
		return $this->lineItem[$this->liColPrefix . 'CONFG_DEPT_TYPE'];
	}
	
	/**
	 * @return string or null
	 */
	public function getConfDesc ()
	{
		return $this->lineItem[$this->liColPrefix . 'CONFG_DESC'];
	}
	
	/**
	 * @return string or null
	 */
	public function getConfDrawingNo ()
	{
		return $this->lineItem[$this->liColPrefix . 'CONFG_DRAWING_NO'];
	}
	
	/**
	 * @return string or null
	 */
	public function getConfManufacturer ()
	{
		return $this->lineItem[$this->liColPrefix . 'CONFG_MANUFACTURER'];
	}
	
	/**
	 * @return string or null
	 */
	public function getConfModelNo ()
	{
		return $this->lineItem[$this->liColPrefix . 'CONFG_MODEL_NO'];
	}
	
	/**
	 * @return string or null
	 */
	public function getConfName ()
	{
		return $this->lineItem[$this->liColPrefix . 'CONFG_NAME'];
	}
	
	/**
	 * @return string or null
	 */
	public function getConfSerialNo ()
	{
		return $this->lineItem[$this->liColPrefix . 'CONFG_SERIAL_NO'];
	}
	
	public function sectionIsNull ()
	{
		if ($this->getConfDeptType() !== null)
		{
			return false;
		}
		
		if ($this->getConfDesc() !== null)
		{
			return false;
		}
		
		if ($this->getConfDrawingNo() !== null)
		{
			return false;
		}
		
		if ($this->getConfManufacturer() !== null)
		{
			return false;
		}
		
		if ($this->getConfModelNo() !== null)
		{
			return false;
		}
		
		if ($this->getConfName() !== null)
		{
			return false;
		}
		
		if ($this->getConfSerialNo() !== null)
		{
			return false;
		}
		
		return true;
	}
}
