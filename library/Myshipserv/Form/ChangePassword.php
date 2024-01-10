<?php

/**
 * Register form object
 * 
 * @package Myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
/*class Myshipserv_Form_ChangePassword extends Zend_Form
{
	public function __construct ($options = null)
	{
		parent::__construct($options);
		
		$this->setAttrib('method', 'post');
		
		$this->addElementPrefixPath('Myshipserv_Validate', 'Myshipserv/Validate', 'validate');
		
		$this->addElement('text', 'oldPassword', array(
				'required'   => true,
				'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter your old password.'
								))),
								array('stringLength', false, array(6, 20,
                                          'messages' => array(
                                          	Zend_Validate_StringLength::TOO_SHORT => 'Your password must be at least %min% characters long.',
                                          	Zend_Validate_StringLength::TOO_LONG => 'Your password can be no longer than %max% characters.')))
								
				),
		));
		
		$this->addElement('password', 'password', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a password.'
								))),
								array('stringLength', false, array(6, 20,
                                          'messages' => array(
                                          	Zend_Validate_StringLength::TOO_SHORT => 'Your password must be at least %min% characters long.',
                                          	Zend_Validate_StringLength::TOO_LONG => 'Your password can be no longer than %max% characters.'))),
								array('PasswordConfirmation', false, array('confirmPassword'))
							),
							'required' => true));
		
		// Tell password confirmation which field to check against
		$this->getElement('password')->getValidator('PasswordConfirmation')->setConfirmFieldName('confirmPassword');
		
		$this->addElement('password', 'confirmPassword', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a password confirmation.'
								))),
							),
							'required' => true));		
	}
}*/
class Myshipserv_Form_ChangePassword extends Zend_Form
{
   public function __construct($options = null)
   {
       parent::__construct($options);

       $this->setAttrib('method', 'post');

       $this->addElementPrefixPath('Myshipserv_Validate', 'Myshipserv/Validate', 'validate');

       $this->addElement('text', 'oldPassword', array(
           'required'   => true,
           'validators' => array(
               array('NotEmpty', true, array('messages' => array(
                   Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter your old password.'
               ))),
               array('stringLength', false, array(8, 20, 'messages' => array(
                   Zend_Validate_StringLength::TOO_SHORT => 'Your password must be at least %min% characters long.',
                   Zend_Validate_StringLength::TOO_LONG => 'Your password can be no longer than %max% characters.'
               )))
           ),
       ));

       $this->addElement('password', 'password', array(
           'validators' => array(
               array('NotEmpty', true, array('messages' => array(
                   Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a password.'
               ))),
               array('stringLength', false, array(8, 20, 'messages' => array(
                   Zend_Validate_StringLength::TOO_SHORT => 'Your password must be at least %min% characters long.',
                   Zend_Validate_StringLength::TOO_LONG => 'Your password can be no longer than %max% characters.'
               ))),
               array('regex', false, array('/[A-Z]/', 'messages' => array(
                   Zend_Validate_Regex::NOT_MATCH => 'Your password must contain at least one uppercase letter.'
               ))),
               array('regex', false, array('/[a-z]/', 'messages' => array(
                   Zend_Validate_Regex::NOT_MATCH => 'Your password must contain at least one lowercase letter.'
               ))),
               array('regex', false, array('/[!@#$%^&*()\[\]{};:<>?~\-_+=|\/]/', 'messages' => array(
                   Zend_Validate_Regex::NOT_MATCH => 'Your password must contain at least one special character.'
               ))),
               array('PasswordConfirmation', false, array('confirmPassword'))
           ),
           'required' => true
       ));

       // Tell password confirmation which field to check against
       $this->getElement('password')->getValidator('PasswordConfirmation')->setConfirmFieldName('confirmPassword');

       $this->addElement('password', 'confirmPassword', array(
           'validators' => array(
               array('NotEmpty', true, array('messages' => array(
                   Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a password confirmation.'
               ))),
               array('stringLength', false, array(8, 20, 'messages' => array(
                   Zend_Validate_StringLength::TOO_SHORT => 'Your password confirmation must be at least %min% characters long.',
                   Zend_Validate_StringLength::TOO_LONG => 'Your password confirmation can be no longer than %max% characters.'
               )))
           ),
           'required' => true
       ));
   }
}
