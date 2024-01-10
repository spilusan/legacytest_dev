<?php

/**
 * Register form object
 * 
 * @package Myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Myshipserv_Form_Register extends Zend_Form
{
	/**
	 * Constructor - generate the form and the elements
	 * 
	 * @access public
	 * @param array $companyTypes
	 * @param array $jobFunctions
	 * @param array $options
	 */
	public function __construct (array $companyTypes, array $jobFunctions, $options = null)
	{
		parent::__construct($options);
		
		$this->setAttrib('method', 'post');
		
		$this->addElementPrefixPath('Myshipserv_Validate', 'Myshipserv/Validate', 'validate');
		
		$registerEmail = new Zend_Form_Element_Text('registerEmail', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a valid email address.'
								))),
								
								'emailAddress'
							),
							'required' => true));
		$registerEmail->addErrorMessage('A valid email address is required to register.');
		
		$this->addElement($registerEmail);
		
		$this->addElement('text', 'registerFirstName', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter your first name.'
								)))
							),
							'required' => true));
		
		$this->addElement('text', 'registerLastName', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter your last name.'
								)))
							),
							'required' => true));
		
		$this->addElement('text', 'registerCompany', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter the name of your company.'
								)))
							),
							'required' => true));
		
		$this->addElement('password', 'registerPassword', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a password.'
								))),
								array('stringLength', false, array(6, 20,
                                          'messages' => array(
                                          	Zend_Validate_StringLength::TOO_SHORT => 'Your password must be at least %min% characters long.',
                                          	Zend_Validate_StringLength::TOO_LONG => 'Your password can be no longer than %max% characters.'))),
								array('PasswordConfirmation', false, array('registerConfirmPassword'))
							),
							'required' => true));
		
		$this->addElement('password', 'registerConfirmPassword', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a password confirmation.'
								))),
							),
							'required' => true));
		
		$selectCompanyTypes = array();
		foreach ($companyTypes as $companyType)
		{
			$selectCompanyTypes[$companyType['PCT_ID']] = $companyType['PCT_COMPANY_TYPE'];
		}
		
		$this->addElement('select', 'registerCompanyType', array(
							'multiOptions' => $selectCompanyTypes,
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please select your company type.'
								)))
							),
							'required'     => true));
		$this->addElement('text', 'registerOtherCompanyType', array(
							'validators' => array(
							//	'alnum'
							),
							'required' => false));
		
		$selectJobFunctions = array();
		foreach ($jobFunctions as $jobFunction)
		{
			$selectJobFunctions[$jobFunction['PJF_ID']] = $jobFunction['PJF_JOB_FUNCTION'];
		}
		
		$this->addElement('select', 'registerJobFunction', array(
							'multiOptions' => $selectJobFunctions,
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									    Zend_Validate_NotEmpty::IS_EMPTY => 'Please select your job function.'
								)))
							),
							'required'     => true));
		$this->addElement('text', 'registerOtherJobFunction', array(
							'validators' => array(
							//	'alnum'
							),
							'required' => false));
		
		$this->addElement('checkbox', 'registerMarketingUpdated', array());

        // added by Yuriy Akopov on 2013-09-06, S8093
        $this->addElement(
            'checkbox',
            'tocConfirmed',
            array(
                'validators' => array(
                    array(
                        'GreaterThan',
                        true,
                        array(
                            'messages' => array(
                                Zend_Validate_GreaterThan::NOT_GREATER => 'You must to agree to the terms and conditions to proceed.'
                            ),
                            'options' => 0
                        )
                    )
                )
            )
        );
	}
}
