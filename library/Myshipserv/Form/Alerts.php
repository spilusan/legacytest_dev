<?php

/**
 * User profile form to use with /profile/notifications
 *
 * Started by Elvir, finished and somewhat refactored by Yuriy Akopov on 2014-09-24
 *
 * @author  Elvir Leonard
 * @story   S11020
 *
 * Class Myshipserv_Form_Alerts
 */
class Myshipserv_Form_Alerts extends Zend_Form
{
    const
        ELEMENT_ALERTS      = 'alertsFlag',
        ELEMENT_ANONYMITY   = 'anonymityFlag',
        ELEMENT_SUBMIT      = 'submitBtn'
    ;

	public function init ()
	{
		$this->setAttrib('method', 'post');		
		$this->setAction('');

        /*
		$el = new Zend_Form_Element_Select('status');
		$el->setLabel('Choose how often notifications will be delivered by e-mail')
			->addMultiOptions(array(
				'never'         => 'Never',
				'weekly'        => 'Weekly',
				'immediately'   => 'Immediately'))
			->setRequired();
		$this->addElement($el);
        */

        $alertsElement = new Zend_Form_Element_Select(self::ELEMENT_ALERTS);
        $alertsElement
            ->addMultiOptions(array(
                Shipserv_User::ALERTS_IMMEDIATELY => 'Notify me immediately',
                Shipserv_User::ALERTS_WEEKLY      => 'Send me a weekly summary email',
                Shipserv_User::ALERTS_NEVER       => 'Never notify me'
            ))
            ->setRequired(true)
        ;
        $this->addElement($alertsElement);

        $anonymityElement = new Zend_Form_Element_Select(self::ELEMENT_ANONYMITY);
        $anonymityElement
            ->addMultiOptions(array(
                Shipserv_User::ANON_LEVEL_ALL           => 'It is okay to show my name, job title and company name',
                Shipserv_User::ANON_LEVEL_COMPANY_JOB   => 'Anonymise my name, but it is okay to show my job title and company name',
                Shipserv_User::ANON_LEVEL_COMPANY       => 'Anonymise my name and job title, but it is okay to show my company name',
                Shipserv_user::ANON_LEVEL_NONE          => 'Anonymise my name, job title and company name'
            ))
            ->setRequired(true)
        ;
		$this->addElement($anonymityElement);
		
		$submitButton = new Zend_Form_Element_Submit(self::ELEMENT_SUBMIT);
		$submitButton->setLabel('Save changes');
		$this->addElement($submitButton);
	}

    public function getSubmitElement() {
        return $this->{self::ELEMENT_SUBMIT};
    }

    /**
     * @return  Zend_Form_Element_Select
     */
    public function getAlertsElement() {
        return $this->{self::ELEMENT_ALERTS};
    }

    /**
     * @return  Zend_Form_Element_Select
     */
    public function getAnonymityElement() {
        return $this->{self::ELEMENT_ANONYMITY};
    }

    /**
     * @return  string
     */
    public function getAlertsFlag() {
        $values = $this->getValues();
        return $values[self::ELEMENT_ALERTS];
    }

    /**
     * @return  string
     */
    public function getAnonymityFlag() {
        $values = $this->getValues();
        return $values[self::ELEMENT_ANONYMITY];
    }
}
