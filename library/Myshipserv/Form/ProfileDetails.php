<?php

class Myshipserv_Form_ProfileDetails extends Zend_Form
{
	
	public function init ()
	{
		$db = $GLOBALS['application']->getBootstrap()->getResource('db');
		$oracleReference = new Shipserv_Oracle_Reference($db);
		
        // Set the method for the display form to POST
        $this->setMethod('post');
		
        // Add an email element
        $this->addElement('text', 'email', array(
            'label'      => 'Email',
            'filters'    => array('StringTrim'),
            'validators' => array(
                'EmailAddress',
            ),
			
			// Read only field for now
			'ignore'	=> true,
			'disabled'	=> true,
        ));
		
        // Add first name
        $this->addElement('text', 'name', array(
            'label'      => 'First name',
            'required'   => true,
            'filters'    => array('StringTrim'),
        ));
		
        // Add last name
        $this->addElement('text', 'surname', array(
            'label'      => 'Last name',
            'required'   => true,
            'filters'    => array('StringTrim'),
        ));
		
		$this->addElement('text', 'alias', array(
            'label'      => 'Alias',
            'required'   => false,
            'filters'    => array('StringTrim'),
        ));
		
		$this->addElement('text', 'company', array(
            'label'      => 'Company',
            'required'   => true,
            'filters'    => array('StringTrim'),
        ));
		
		$this->addElement('text', 'cZipcode', array(
			'label'      => 'Zip/Postcode',
			'required'   => true,
			'filters'    => array('StringTrim'),
		));
		
		$this->addElement('text', 'cCountryCode', array(
			'label'      => 'Country code',
			'required'   => true,
			'filters'    => array('StringTrim'),
		));

		$this->addElement('text', 'cAddress1', array(
			'label'      => 'Address 1',
			'required'   => true,
			'filters'    => array('StringTrim'),
		));
		

		$this->addElement('text', 'cAddress2', array(
			'label'      => 'Address 2',
			'required'   => false,
			'filters'    => array('StringTrim'),
		));
		

		$this->addElement('text', 'cAddress3', array(
			'label'      => 'Address 3',
			'required'   => false,
			'filters'    => array('StringTrim'),
		));
		
		$this->addElement('text', 'cPhone', array(
			'label'      => 'Phone',
			'required'   => false,
			'filters'    => array('StringTrim'),
		));

		$this->addElement('text', 'cWebsite', array(
			'label'      => 'Website',
			'required'   => false,
			'filters'    => array('StringTrim'),
		));
		
		$this->addElement('text', 'cSpending', array(
			'label'      => 'Spending',
			'required'   => false,
			'filters'    => array('StringTrim'),
		));
		
		$this->addElement('text', 'cNoOfVessel', array(
			'label'      => 'Number of Vessel',
			'required'   => false,
			'filters'    => array('StringTrim'),
		));
		
		$this->addElement('text', 'cCountryCode', array(
			'label'      => 'Country',
			'required'   => true,
		));
		
		// -----------------------------------
		// Company types
		// -----------------------------------
		$companyTypes = $oracleReference->fetchCompanyTypes();
		$selectCompanyTypes = array();
		foreach ($companyTypes as $companyType)
		{
			$selectCompanyTypes[$companyType['PCT_ID']] = $companyType['PCT_COMPANY_TYPE'];
		}
		
		$this->addElement('select', 'companyType', array(
			'multiOptions' => $selectCompanyTypes,
			'validators' => array(),
			'required'     => true
		));
		
		$this->addElement('text', 'otherCompanyType', array(
			'validators' => array(),
			'required' => false));
		
		
		// -----------------------------------
		// Job functions
		// -----------------------------------		
		$jobFunctions = $oracleReference->fetchJobFunctions();
		$selectJobFunctions = array();
		foreach ($jobFunctions as $jobFunction)
		{
			$selectJobFunctions[$jobFunction['PJF_ID']] = $jobFunction['PJF_JOB_FUNCTION'];
		}
		
		$this->addElement('select', 'jobFunction', array(
			'multiOptions' => $selectJobFunctions,
			'validators' => array(),
			'required'     => true
		));
		
		$this->addElement('text', 'otherJobFunction', array(
			'validators' => array(),
			'required' => false));
				
		// -----------------------------------
		// Vessel type
		// -----------------------------------		
		$vesselDao = new Shipserv_Oracle_Vessel;				
		$vesselTypeObject = new Zend_Form_Element_MultiCheckbox('vesselType[]', array(
			'multiOptions' => array(
				$vesselDao->getTypes('Zend_Form')
			)
		));
		$this->addElement($vesselTypeObject);

		// -----------------------------------
		// Decision maker
		// -----------------------------------
		$decisionMakerObject = new Zend_Form_Element_Checkbox('isDecisionMaker', array(
			'options' => array(
				'checkedValue' => 1,
				'uncheckedValue' => null
			)
		));
		$this->addElement($decisionMakerObject);
		
		
        // Add the submit button
        $this->addElement('submit', 'submit', array(
            'ignore'   => true,
            'label'    => 'Save',
        ));
	}
}
