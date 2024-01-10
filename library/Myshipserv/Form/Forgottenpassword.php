<?php

/**
 * Forgotten Password form object
 *
 * @package Myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Myshipserv_Form_Forgottenpassword extends Zend_Form
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
		
		$this->setAttrib('method', 'post');
		
		$validator = new Zend_Validate_EmailAddress();
		// Add an email element
		$this->addElement('text', 'forgEmail', array(
				'filters'    => array('StringTrim'),
				'validators' => array(
					$validator
				),
				'disabled'	=> true,
		));
		
		
	}
}