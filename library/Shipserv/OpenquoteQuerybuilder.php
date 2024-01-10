<?php 

class Shipserv_OpenquoteQuerybuilder
{
	
	private $columnsAvailable = array(
		'rfl_id_code' => array(	'friendlyName' => 'Item Code', 
								'type'=> 'string',
								'context' => 'A'
							  ),
		'rfl_product_desc' => array(	'friendlyName' => 'Item Description', 
								'type'=> 'string',
								'context' => 'A'
							  ),
		'rfl_comments' => array(	'friendlyName' => 'Item Comments', 
								'type'=> 'string',
								'context' => 'A'
							  ),
		'rfl_unit_cost' => array(	'friendlyName' => 'Unit Cost', 
								'type'=> 'number',
								'context' => 'A'
							  ),
		'rfl_confg_manufacturer' => array(	'friendlyName' => 'Item Manufacturer', 
								'type'=> 'string',
								'context' => 'A'
							  ),
		'rfl_confg_model_no' => array(	'friendlyName' => 'Item Model No', 
								'type'=> 'string',
								'context' => 'A'
							  ),
		'rfl_id_code' => array(	'friendlyName' => 'Item Code', 
								'type'=> 'string',
								'context' => 'A'
							  ),
		'rfq_delivwery_port' => array(	'friendlyName' => 'Delivery Port', 
								'type'=> 'string',
								'comment' => 'The',
								'context' => 'A'
							  ),
		'buyerName' => array(	'friendlyName' => 'Buyer', 
								'type'=> 'string',
								'comment' => 'The name of the buyer/supplier who the ',
								'context' => 'S',
							  ),
	);
	
	public $operators = array(
		'equals' => array('display' => '=',
							'dbOperator' => '=',
							'wrappedBy'  => '',
							'use' => array('string' => true, 
											'number' => true)
							),
		'gt' => array('display' => 'More than',
							'dbOperator' => '>',
							'wrappedBy'  => '',
							'use' => array('string' => false, 
											'number' => true)
							),
		'lt' => array('display' => 'Less than',
							'dbOperator' => '<',
							'wrappedBy'  => '',
							'use' => array('string' => false, 
											'number' => true)
							),
		'lte' => array('display' => 'Less than or eaqual to',
							'dbOperator' => '<=',
							'wrappedBy'  => '',
							'use' => array('string' => false, 
											'number' => true)
							),
		'gte' => array('display' => 'Greater than or equal to',
							'dbOperator' => '>=',
							'wrappedBy'  => '',
							'use' => array('string' => false, 
											'number' => true)
							),
		'contains' => array('display' => 'Contains',
							'dbOperator' => 'like ',
							'wrappedBy'  => '%',
							'use' => array('string' => true, 
											'number' => false)
							),
	);
			
	private $context;
	
	public $column_array;
	public $operator_array;
	
	/**
	 *
	 * @param char $context B or S for Buyer or supplier context which other parts will rely on. 
	 */
	public function __construct($context = "B") {
		$this->context = $context;
		$this->column_array		= $this->columnsAvailable;
		$this->operator_array	= $this->operators;
		
		
		
	}
	
	
}