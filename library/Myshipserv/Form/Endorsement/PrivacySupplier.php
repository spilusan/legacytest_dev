<?php

class Myshipserv_Form_Endorsement_PrivacySupplier extends Myshipserv_Form_Endorsement_PrivacyAbstract
{
	public function init ()
	{
		parent::init();
		
		$this->getElement(Myshipserv_Form_Endorsement_PrivacyAbstract::FIELD_ANON)
			 ->setLabel('Choose how your customer names are displayed:')
			 ->setMultiOptions(array(
				self::FIELD_ANON_OFF => "Show my customers' company details if they are willing (recommended).",
				self::FIELD_ANON_ON => 'Anonymise all my customers'))
             ->removeDecorator('HtmlTag')
			 ->removeDecorator('DtDdWrapper');

	}
}
