<?php

/**
 * Enquiry form object
 *
 * @package Myshipserv
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Myshipserv_Form_RFQ extends Zend_Form
{
	// ------------------------------------------------------------------------
	// BUYER DETAILS
	// ------------------------------------------------------------------------
	private function addBuyerElements()
	{
		$this->addElement('text', 'bEmail', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a email for this RFQ.'
						))),
				),
				'required' => true));
		
		
		$this->addElement('text', 'bName', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a contact name for this RFQ.'
						))),
				),
				'required' => true));
		
		$this->addElement('text', 'bCompanyName', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a company name for this RFQ.'
						))),
				),
				'required' => true));
		
		$this->addElement('text', 'bTelephone', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a telephone for this RFQ.'
						))),
				),
				'required' => true));
		
		$this->addElement('text', 'bAccountReference', array('validators' => array(), 'required' => false));
		
		$this->addElement('text', 'bAddress1', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a address for this RFQ.'
						))),
				),
				'required' => true));
		
		$this->addElement('text', 'bAddress2', array('validators' => array(), 'required' => false));
		
		$this->addElement('text', 'bCity', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a city for this RFQ.'
						))),
				),
				'required' => true));
		
		$this->addElement('text', 'bStateProvince', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a state/province for this RFQ.'
						))),
				),
				'required' => true));
		
		$this->addElement('text', 'bZipPostcode', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a zip/postcode for this RFQ.'
						))),
				),
				'required' => true));
		
		$this->addElement('text', 'bCountry', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a country for this RFQ.'
						))),
				),
				'required' => true));		
	}

	// ------------------------------------------------------------------------
	// DELIVERY DETAILS
	// ------------------------------------------------------------------------
	private function addDeliveryElements()
	{
		$this->addElement('text', 'dDeliveryTo', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a delivery contact for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'dDeliveryBy', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a delivery time for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'dAddress1', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a delivery address for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'dAddress2', array('validators' => array(), 'required' => false));
			
		$this->addElement('text', 'dCity', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a delivery city for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'dStateProvince', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a delivery state/province for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'dZipPostcode', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a delivery zip/postcode for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'dCountry', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a delivery country for this RFQ.'
						))),
				),
				'required' => true));
			
		$this->addElement('text', 'dDeliveryPort', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a delivery port for this RFQ.'
						))),
				),
				'required' => true));
		
		$this->addElement('text', 'dTransportMode', array('validators' => array(), 'required' => false));
		
		$this->addElement('text', 'dPackagingInstructions', array('validators' => array(), 'required' => false));
	}
	
	// ------------------------------------------------------------------------
	// REQUISITION DETAILS
	// ------------------------------------------------------------------------
	private function addRequisitionElements()
	{
		
		$this->addElement('text', 'rRfqSubject', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a subject for this RFQ.'
						))),
				),
				'required' => true));
		
		$this->addElement('text', 'rRfqReference', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a reference number for this RFQ.'
						))),
				),
				'required' => true));
		
		$this->addElement('text', 'rPriority', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a priority for this RFQ.'
						))),
				),
				'required' => true));
		
		$this->addElement('text', 'rReplyBy', array('validators' => array(), 'required' => false));
						
	}
	
	// ------------------------------------------------------------------------
	// VESSEL DETAILS
	// ------------------------------------------------------------------------
	private  function addVesselElements()
	{
		$this->addElement('text', 'vVesselName', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a vessel name for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'vImoNumber', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a IMO number for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'vClassification', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a classification for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'vVesselType', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a vessel type for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'vYearBuilt', array('validators' => array(), 'required' => false));
		$this->addElement('text', 'vYard', array('validators' => array(), 'required' => false));
		$this->addElement('text', 'vVesselEta', array('validators' => array(), 'required' => false));
		$this->addElement('text', 'vVesselEtd', array('validators' => array(), 'required' => false));
	}
	
	// ------------------------------------------------------------------------
	// TERMS DETAILS
	// ------------------------------------------------------------------------
	private function addTermsElements()
	{
		$this->addElement('text', 'tPaymentTerms', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a vessel type for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'tTaxStatus', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a vessel type for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'tCurrencyInstruction', array(
				'validators' => array(
						array('NotEmpty', true, array('messages' => array(
								Zend_Validate_NotEmpty::IS_EMPTY => 'Please enter a vessel type for this RFQ.'
						))),
				),
				'required' => true));
		$this->addElement('text', 'tGeneralTC', array('validators' => array(), 'required' => false));
		
	}
	
	private function addLineItemsElements()
	{
		$this->addElement('text', 'tGeneralTC', array('validators' => array(), 'required' => false));
		
	}
	
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
		
		// adding all required elements
		$this->addBuyerElements();
		$this->addDeliveryElements();
		$this->addRequisitionElements();
		$this->addVesselElements();
		
		$this->addLineItemsElements();
		
		/*
		
		// ------------------------------------------------------------------------------------------------------------------------------------------------ //
		$this->addElement('text', 'buyerName', array(
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
								array('size', false, array('max' => $config->shipserv->enquiryBasket->attachments->maxFileSize
														   )),
								array('count', false, array('min' => 0,
															'max' => $config->shipserv->enquiryBasket->attachments->count))
							),
							'multiFile' => $config->shipserv->enquiryBasket->attachments->count,
							'required' => false));
		*/
	}
}
