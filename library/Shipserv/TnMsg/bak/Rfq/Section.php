<?php

/**
 * Section describes the on-board equipment for which a part is ordered. E.g.
 * when buying a main-engine spare, Section describes the main engine.
 */
class Shipserv_TnMsg_Rfq_Section
{
	private $departmentType;
	private $description;
	private $drawingNumber;
	private $manufacturer;
	private $modelNumber;
	private $name;
	private $serialNumber;
	
	public function __construct ()
	{
		// Do nothing
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
	 * @param string $departmentType
	 */
	public function setDepartmentType ($departmentType = null)
	{
		$departmentType = trim($departmentType);
		
		if ($departmentType == '')
		{
			$this->departmentType = null;
		}
		else
		{
			$this->departmentType = $departmentType;
		}
	}
	
	/**
	 * @param string $description
	 */
	public function setDescription ($description = null)
	{
		$description = trim($description);
		
		if ($description == '')
		{
			$this->description = null;
		}
		else
		{
			$this->description = $description;
		}
	}
	
	/**
	 * @param string $drawingNumber
	 */
	public function setDrawingNumber ($drawingNumber = null)
	{
		$drawingNumber = trim($drawingNumber);
		
		if ($drawingNumber == '')
		{
			$this->drawingNumber = null;
		}
		else
		{
			$this->drawingNumber = $drawingNumber;
		}
	}
	
	/**
	 * @param string $manufacturer
	 */
	public function setManufacturer ($manufacturer = null)
	{
		$manufacturer = trim($manufacturer);
		
		if ($manufacturer == '')
		{
			$this->manufacturer = null;
		}
		else
		{
			$this->manufacturer = $manufacturer;
		}
	}
	
	/**
	 * @param string $modelNumber
	 */
	public function setModelNumber ($modelNumber = null)
	{
		$modelNumber = trim($modelNumber);
		
		if ($modelNumber == '')
		{
			$this->modelNumber = null;
		}
		else
		{
			$this->modelNumber = $modelNumber;
		}
	}
	
	/**
	 * The name of the ship section, e.g. 'Main Engine'.
	 * 
	 * @param string $name
	 */
	public function setName ($name = null)
	{
		$name = trim($name);
		
		if ($name == '')
		{
			$this->name = null;
		}
		else
		{
			$this->name = $name;
		}
	}
	
	/**
	 * @param string $serialNumber
	 */
	public function setSerialNumber ($serialNumber = null)
	{
		$serialNumber = trim($serialNumber);
		
		if ($serialNumber == '')
		{
			$this->serialNumber = null;
		}
		else
		{
			$this->serialNumber = $serialNumber;
		}
	}
	
	/**
	 * Returns true if all fields are null.
	 *
	 * @return bool
	 */
	public function isNull ()
	{
		static $fieldsToCheck = array(
			'departmentType',
			'description',
			'drawingNumber',
			'manufacturer',
			'modelNumber',
			'name',
			'serialNumber',
		);
		
		// If one field is not null, return false
		foreach ($fieldsToCheck as $f)
		{
			if ($this->$f !== null)
			{
				return false;
			}
		}
		
		// All fields null
		return true;
	}
}
