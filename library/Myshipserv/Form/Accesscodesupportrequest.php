<?php

/**
 * Access Code Support Request form object
 *
 * @author Uladzimir Maroz <umaroz@shipserv.com>
 * @copyright Copyright (c) 2010, ShipServ
 */
class Myshipserv_Form_Accesscodesupportrequest extends Zend_Form
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

		$reminderEmail->addErrorMessage('Please enter valid email address.');

		$this->addElement($reminderEmail);



		$companyName = new Zend_Form_Element_Text('companyName', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a company name.'
								)))
							),
							'required' => true));

		$companyName->addErrorMessage('Please enter a company name.');

		$this->addElement($companyName);

		$contactName = new Zend_Form_Element_Text('contactName', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter contact name.'
								)))
							),
							'required' => true));

		$contactName->addErrorMessage('Please enter a contact name.');

		$this->addElement($contactName);

	}
}