<?php

class Myshipserv_Activity {
	
	protected $url;
	protected $user;
	protected $ip;
	
	public function __construct()
	{
		$this->url = $_SERVER['REQUEST_URI'];
		$this->user = Shipserv_User::isLoggedIn();
	}
	
	public static function log()
	{
		
	}
}
?>
