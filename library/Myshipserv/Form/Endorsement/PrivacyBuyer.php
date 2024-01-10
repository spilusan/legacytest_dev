<?php

/**
 * Specialisation to capture privacy settings for buyers.
 */
class Myshipserv_Form_Endorsement_PrivacyBuyer extends Myshipserv_Form_Endorsement_PrivacyAbstract
{
	public function init ()
	{
		parent::init();
		
		// Add 3rd option: 'anonymization off but with exceptions ...'
		$this->getElement(Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON)
			 ->setLabel('Do you want to allow your suppliers to use your company name in their customer lists?')
			 ->setMultiOptions(array(
				self::FIELD_ANON_ON => 'No, anonymise my company on all customer lists',
				self::FIELD_ANON_OFF => 'Yes, allow my suppliers to use my company name',
				self::FIELD_ANON_TN_ONLY => 'Yes, but display it for other TradeNet buyers only',
				self::FIELD_ANON_SELECT => 'Yes, allow for all suppliers except the following:'))
             ->removeDecorator('HtmlTag')
			 ->removeDecorator('DtDdWrapper');
		
		// Add group of checkboxes (an 'array') to capture exception ids
		$anonForList = new Zend_Form_Element_MultiCheckbox(self::FIELD_SELECTIVE_ANON, array('separator' => '',
																							 'class' => 'checkbox'));
		$anonForList->setRegisterInArrayValidator(false)
					->setOrder(self::FIELD_SELECTIVE_ANON_ORDER)
					->removeDecorator('HtmlTag')
					->removeDecorator('label')
					->removeDecorator('DtDdWrapper');
		$this->addElement($anonForList);
	}
}
