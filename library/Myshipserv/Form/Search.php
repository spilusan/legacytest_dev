<?php

class Myshipserv_Form_Search extends Zend_Form
{
	public function __construct ($options = null)
	{
		parent::__construct($options);
		
		$this->addElement('text', 'searchWhat');
		$this->addElement('text', 'searchType');
        $this->addElement('text', 'searchCountry');
        $this->addElement('text', 'searchPort');
		$this->addElement('text', 'searchWhere');
		$this->addElement('text', 'searchText');
		$this->addElement('text', 'zone');
        $this->addElement('text', 'searchStart', array('filter' => 'Int'));
        $this->addElement('text', 'searchRows', array('filter' => 'Int'));
		$this->addElement('text', 'categoryRows', array('filter' => 'Int'));
        $this->addElement('text', 'membershipRows', array('filter' => 'Int'));
        $this->addElement('text', 'certificationRows', array('filter' => 'Int'));
	}
}
