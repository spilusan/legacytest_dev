<?php

/**
 * Brand form object
 *
 * @package Myshipserv
 * @copyright Copyright (c) 2009, ShipServ
 */
class Myshipserv_Form_Brand extends Zend_Form
{
	/**
	 * Constructor - generate the form and the elements
	 *
	 * @access public
	 * @param array $options
	 */
	public function __construct ($options = null)
	{
		parent::__construct($options);

		$config  = Zend_Registry::get('config');

		$this->setAttrib('enctype', 'multipart/form-data');
		$this->setAttrib('method', 'post');


		$element = new Zend_Form_Element_File('brandLogo');
	    $element->addValidator('Count', false, 1);
		$element->addValidator('Size', false, 302400);
		$element->addValidator('Extension', false, 'jpg,png,gif');

		$this->addElement($element, 'brandLogo');


	}
}
