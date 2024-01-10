<?php

/**
 * Login form object
 *
 * @package Myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Myshipserv_Form_Login extends Zend_Form
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
		
		$this->addElement('text', 'loginUsername', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter your username (this is normally your email address).'
								))),
							),
							'required' => true));
		
		$this->addElement('text', 'loginPassword', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter your password.'
								))),
							),
							'required' => true));
		
		$this->addElement('hidden', 'loginRedirectUrl', array('required' => false));
		
		$this->addElement('checkbox', 'loginRememberMe', array('required' => false));
	}
}