<?php


/**
 * Scoring Rules for Match, plus the ability via DB to Enable/Disable rules.
 * @package myshipserv
 * @author Shane O'Connor <soconnor@shipserv.com>
 * @copyright Copyright (c) 2012, ShipServ
 */

class Shipserv_Match_ScoreManager {
	
	private $db;
	
	private $rulesOnOffSettings = array();
	
	private $rulesScoringSettings = array();
	
	private $rulesRegexSettings = array();
	
	
	public function __construct($db) {
		$this->db = $db;
		
		$this->settings = $this->getSettings;
	}
	
	/*
	 * Rules implementation
	 */
	
	
	
	/*
	 * Construct Functions 
	 */
	private function getSettings(){
		if($this->db){
			$sql = "Select * from Match_Settings";
			$results = $this->db->query($sql);
			
			foreach($results as $result){
				$this->rulesOnOffSettings[] = array($result['MSE_NAME'] => $result['MSE_VALUE']);
			}
			
		}else{
			return array();
		}
	}
	/*
	 * Helper functions for managing the arrays and their scoring before being passed back to the Caller. 
	 */
}


