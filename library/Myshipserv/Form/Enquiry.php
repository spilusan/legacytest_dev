<?php

/**
 * Enquiry form object
 *
 * @package Myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Myshipserv_Form_Enquiry extends Zend_Form
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
		
		$this->addElement('text', 'enquiry-subject', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a subject for this enquiry.'
								))),
							),
							'required' => true));
		
		$this->addElement('text', 'sender-name', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter your name.'
								))),
							),
							'required' => true));
		
		$this->addElement('text', 'company-name', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter your company name.'
								))),
							),
							'required' => true));
		
        $this->addElement('text', 'sender-email', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter your email address.'
								))),
								'emailAddress'
							),
							'required' => true));
		
        $this->addElement('text', 'sender-phone', array(
							'validators' => array( ),
							'required' => false));
		
		$this->addElement('text', 'vessel-name', array(
							'validators' => array( ),
							'required' => false));
		
		$this->addElement('text', 'imo', array(
							'validators' => array( ),
							'required' => false));
		
		$this->addElement('text', 'delivery-location', array(
							'validators' => array( ),
							'required' => false));
		
		$this->addElement('text', 'delivery-date', array(
							'validators' => array( ),
							'required' => false));
		
        $this->addElement('text', 'enquiry-text', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a message for your enquiry.'
								))),
								array('stringLength', false, array(0, 3500,
									'messages' => array(
									  Zend_Validate_StringLength::TOO_SHORT => 'Please enter a message for your enquiry.',
									  Zend_Validate_StringLength::TOO_LONG => 'Please limit your enquiry to 3,500 characters'))),
							),
							'required' => true));
							
        $this->addElement('text', 'sender-country', array(
							'validators' => array(
								array('NotEmpty', true, array('messages' => array(
									Zend_Validate_NotEmpty::IS_EMPTY => 'Please choose your country.'
								))),
   							),
							'required' => true));
							
		$this->addElement('file', 'enquiryFile', array(
							'validators' => array(
								array('size', false, array('max' => $config->shipserv->enquiryBasket->attachments->maxFileSize)),
								array('count', false, array('min' => 0,
															'max' => $config->shipserv->enquiryBasket->attachments->count))
							),
							'multiFile' => $config->shipserv->enquiryBasket->attachments->count,
							'required' => false));
	}
}
