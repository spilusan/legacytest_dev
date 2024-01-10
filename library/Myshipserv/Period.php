<?php
class Myshipserv_Period{
	
	function __construct()
	{
		// set the default date
		$this->now = new MyDateTime();
		$this->past = new MyDateTime();
		$this->now->setDate(date('Y'), date('m'), date('d')+1);
		$this->db = $GLOBALS['application']->getBootstrap()->getResource('db');
	}
	public static function yearAgo( $no = 1 )
	{
		$object = new self;
		$object->past->setDate(date('Y')-$no, date('m'), date('d'));
		return $object;
	}
	
	public static function dayAgo( $no = 1 )
	{
		$object = new self;
		//$object->past->setTimestamp( $object->now->getTimestamp() - ( 60 * 60 * 24 * $no ) );
		$sql = "SELECT TO_CHAR(sysdate - " . $no . ", 'DD-MM-YYYY') D FROM DUAL";
		$res = $object->db->fetchAll( $sql );
		$date = explode("-", $res[0]['D']);
		$object->past->setDate($date[2], $date[1], $date[0]);
		return $object;
	}
	
	public static function monthAgo( $no = 1 )
	{
		$sql = "SELECT TO_CHAR(ADD_MONTHS(sysdate, -" . $no . ", 'DD-MM-YYYY') D FROM DUAL";
		$res = $object->db->fetchAll( $sql );
		$date = explode("-", $res[0]['D']);
		$object->past->setDate($date[2], $date[1], $date[0]);
		return $object;
	}
	
	public function toArray()
	{
		return array('start' => $this->now, 'end' => $this->past );
	}
}

class MyDateTime extends DateTime
{
    public function setTimestamp( $timestamp )
    {
        $date = getdate( ( int ) $timestamp );
        $this->setDate( $date['year'] , $date['mon'] , $date['mday'] );
        $this->setTime( $date['hours'] , $date['minutes'] , $date['seconds'] );
    }
    
    public function getTimestamp()
    {
        return $this->format( 'U' );
    }
}

