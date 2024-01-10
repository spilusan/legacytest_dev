<?php

class Myshipserv_Form_CompanySettings extends Zend_Form
{
	const FIELD_FORM_ID = 'company-settings';
	const FIELD_IS_JOIN_REQABLE = 'isJoinReqable';
	const FIELD_AUTO_REV_SOLICIT = 'isAutoRevSolicit';
	
	public static function fromPost (array $params)
	{
		if (array_key_exists(self::FIELD_FORM_ID, $params))
		{
			$form = new self();
			$isValid = $form->isValid($params);
			return array('isValid' => $isValid, 'form' => $form);
		}
	}
	
	public static function fromDb ($companyType, $companyId)
	{
		$pco = Shipserv_Oracle_PagesCompany::getInstance()->fetchById($companyType, $companyId);
		$form = new self();
		$form->populate(array(
			self::FIELD_IS_JOIN_REQABLE => $pco['PCO_IS_JOIN_REQUESTABLE'] == 'Y',
			self::FIELD_AUTO_REV_SOLICIT => $pco['PCO_AUTO_REV_SOLICIT'] == 'Y',
		));
		return $form;
	}
	
	public function init ()
	{
		$this->setAttrib('method', 'post');		
		$this->setAction('');
		
		$isJoinReqable = new Zend_Form_Element_Checkbox(self::FIELD_IS_JOIN_REQABLE);
		$isJoinReqable->setRequired();
		$this->addElement($isJoinReqable);

		$isAutoRevSolicit = new Zend_Form_Element_Checkbox(self::FIELD_AUTO_REV_SOLICIT);
		$isAutoRevSolicit->setRequired();
		$this->addElement($isAutoRevSolicit);
		
		//$submitButton = new Zend_Form_Element_Submit('submit_button');
		//$this->addElement($submitButton);
	}
}
