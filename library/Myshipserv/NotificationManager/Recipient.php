<?php
/**
 * Class to store list of recipient grouped by "TO, CC, BCC"
 * 
 */
class Myshipserv_NotificationManager_Recipient 
{
    const
        RECIPIENTS_TO  = 'TO',
        RECIPIENTS_CC  = 'CC',
        RECIPIENTS_BCC = 'BCC'
    ;

    const
        RECIPIENT_NAME  = 'name',
        RECIPIENT_EMAIL = 'email'
    ;

	public $list;
	
	public function getHash()
	{
		$list = (array) $this->list;
		$t = "";
		foreach((array)$list['TO'] as $row)
		{
			$t .= $row['email'];
		}
		foreach((array)$list['CC'] as $row)
		{
			$t .= $row['email'];
		}
		return md5($t);
	}
	
	/**
	 * 
	 */
	public function getTo()
	{
		return $this->list['TO'];
	}
	
	/**
	 * 
	 */
	public function getCc()
	{
		return $this->list['CC'];
	}
	
	public function getBcc()
	{
		return $this->list['BCC'];
	}
	
	
	public function processZendMail($zm)
	{
		foreach( (array) $this->getTo() as $to)
		{
			//if( filter_var($to['email'], FILTER_VALIDATE_EMAIL) !== false )
			$zm->addTo($to['email'], $to['name']);
		}
		
		foreach( (array) $this->getCc() as $to)
		{
			//if( filter_var($to['email'], FILTER_VALIDATE_EMAIL) !== false )
			$zm->addCc($to['email'], $to['name']);
		}
		
		foreach( (array) $this->getBcc() as $to)
		{
			//if( filter_var($to['email'], FILTER_VALIDATE_EMAIL) !== false )
			$zm->addBcc($to['email'], $to['name']);
		}

		return $zm;
	}
	
	public function getDebugMessageForNonProductionEnvironment()
	{
		$list = (array) $this->list;
		$t = "DEBUG - this e-mail was re-routed here from intended recipient: \n\n";
		
		foreach((array)$list['TO'] as $row)
		{
			$t .= "TO: ";
			$t .= ($row['name']!="") ? $row['name'] . " - " . $row['email'] : $row['email'];
			$t .= "\n";
		}
		
		foreach((array)$list['CC'] as $row)
		{
			$t .= "CC: ";
			$t .= ($row['name']!="") ? $row['name'] . " - " . $row['email'] : $row['email'];
			$t .= "\n";
		}
		
		return $t;
	}
}
