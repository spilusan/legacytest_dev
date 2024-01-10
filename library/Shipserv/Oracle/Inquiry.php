<?php

class Shipserv_Oracle_Inquiry extends Shipserv_Oracle
{
	protected $db;
	
	function __construct( )
	{
		$this->db = $this->getDb();
	}
	
}
