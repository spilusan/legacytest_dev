<?php

/**
 * Access Code Reminder form object
 *
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class Myshipserv_Form_Accesscodereminder extends Zend_Form
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

		$reminderEmail = new Zend_Form_Element_Text('email', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a valid email address.'
								))),

								'emailAddress'
							),
							'required' => true));
		
		$reminderEmail->addErrorMessage('Please, enter valid email address.');

		$this->addElement($reminderEmail);
	}
}