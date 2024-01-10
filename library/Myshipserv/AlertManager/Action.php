<?php
/**
 * Hold any actions of an alert
 * @author Elvir <eleonard@shipserv.com>
 */
class Myshipserv_AlertManager_Action{
	
	protected $id;
	protected $title;
	protected $url;
	protected $callType;
	protected $type;
	
	const POSITIVE_ACTION = 1;
	const NEGATIVE_ACTION = 0;
	
	public function get( $var )
	{
		return $this->$var;
	}
	
	function __construct( $title, $url, $callType, $type )
	{
		$this->title = $title;
		$this->callType = $callType;
		$this->url = $url;
		$this->id = $this->getId();
		$this->type = $type;
	}
	
	/**
	 * @return string of unique ID
	 */
	public function getId()
	{
		return md5( "action" . $this->user->id . $this->url . $this->title );
	}
	
	/**
	 * Convert action to array
	 * @return array of this object
	 */
	public function toArray()
	{
		return array(
			"id" => $this->id,
			"title" => $this->title,
			"url" => $this->url,
			"type" => $this->callType,
			"actionType" => $this->type
		);
	}
}

