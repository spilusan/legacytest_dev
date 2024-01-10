<?php

class Myshipserv_Logger extends Shipserv_Object
{
	public $sendEmail;

	private $f;
	private $fileName = '/tmp/mailer.txt';
	
	public function __construct( $sendEmail = false, $fileName = null)
	{
		$this->sendEmail = $sendEmail;
		
		if( $sendEmail == true )
		{
		   $this->f = fopen($this->fileName, 'w+');
		}
	}

	public function log ($msg, $noNewLine = false)
	{
		$msg = date('Y-m-d H:i:s') . "\t" . $msg;
		
		if( $noNewLine == false ) $msg .= "\n";
				
		if( $this->sendEmail ) $this->saveToFile( $msg );
		
		echo $msg;
	}
	
	public function logSimple ($msg, $noNewLine = true)
	{
		if( $noNewLine == false ) $msg .= "\n";

		if( $this->sendEmail ) $this->saveToFile( $msg );
		
		echo $msg;
	}	
	
	public static function newLine ($obj)
	{
		$msg = "\n";

		if( $obj->sendEmail ) $obj->saveToFile( $msg );
		
		echo $msg;		
	}
	
	private function saveToFile( $message )
	{
        fwrite($this->f, $message);
	}
	
	public function sendEmail( $email = null, $subject = null )
	{
		fclose($this->f);
				
		$mail = new Zend_Mail();
		$mail->createAttachment(file_get_contents($this->fileName),
		                        'text/txt',
		                        Zend_Mime::DISPOSITION_INLINE,
		                        Zend_Mime::ENCODING_BASE64, 
		                        basename($this->fileName));
		
		$mail->setFrom('support@shipserv.com', 'ShipServ Pages');
		$mail->setSubject("Logger for: " . $subject);
		$mail->addTo($email);
		$mail->setBodyText("AAAA");
		
		$mail->send();		
		
		unlink( $this->fileName);
	}
}