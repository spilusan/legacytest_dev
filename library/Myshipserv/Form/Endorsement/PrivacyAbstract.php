<?php

/**
 * Form to capture privacy settings regarding owner's trading partners. This
 * abstract is generic to buyers & suppliers.
 */
abstract class Myshipserv_Form_Endorsement_PrivacyAbstract extends Zend_Form
{
	// Field for global anonymization policy (off / off with exceptions / on)
	const FIELD_ANON = 'global_anon';
	
	// Field for id-specific anonymization exceptions
	const FIELD_SELECTIVE_ANON = 'selective_anon';
	const FIELD_SELECTIVE_ANON_ORDER = 200;
	
	// Values for FIELD_ANON
	const FIELD_ANON_ON = 'always';
	const FIELD_ANON_OFF = 'never';
	const FIELD_ANON_SELECT = 'selective';
	const FIELD_ANON_TN_ONLY = 'tn_only';
	
	public function init ()
	{
		$this->setAttrib('method', 'post');		
		$this->setAction('');
		
		// Add FIELD_ANON
		$anonRadio = new Zend_Form_Element_Radio(self::FIELD_ANON);
		$anonRadio->setLabel('Choose whether your company name can be shown in customer lists')
			->addMultiOptions(array(
				self::FIELD_ANON_OFF => 'Never',
				self::FIELD_ANON_ON => 'Always'))
			->setRequired()
			->setAttrib('label_class', 'radio')
			->setSeparator('')
			->removeDecorator('label')
            ->removeDecorator('HtmlTag')
			->removeDecorator('DtDdWrapper')
			->setOrder(100);
		$this->addElement($anonRadio);
		
		/**
		// nb change the damn var name!
		$anonRadio = new Zend_Form_Element_Select('email_updates');
		$anonRadio->setLabel('Anonymize:')
			->addMultiOptions(array(
				'off' => 'Do not e-mail notifications',
				'once_a_week' => 'Email me summary of requests once a week'))
			->setRequired()
			->setOrder(250)
			->setIgnore(true);
		$this->addElement($anonRadio);
		*/
		
		$submitButton = new Zend_Form_Element_Submit('submit_button');
		$submitButton->setOrder(300)->setLabel('Save')->removeDecorator('HtmlTag')->removeDecorator('DtDdWrapper');
		$this->addElement($submitButton);
	}
}
