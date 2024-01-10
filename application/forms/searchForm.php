<?php

class Forms_SearchForm extends Zend_Form
{
	public function __construct ($options)
	{
		parent::__construct($options);
		
		$this->setAction('/search/results')
				   ->setMethod('post')
				   ->setAttrib('id', 'anonSearch');
		
		$this->addElement('text', 'search-what');
		$this->addElement('text', 'search-where');
		$this->addElement('submit', 'search-submit');
	}
	
}