<?php
class Myshipserv_Security_CAS extends Shipserv_Object
{
	
	const PATH_FOR_GRANTING_TICKET = '/auth/cas/v1/tickets';
	const PATH_FOR_VALIDATING_TICKET = '/auth/cas/serviceValidate';
	
	public $grantingTicket;
	public $serviceTicket;
	
	public function __construct()
	{
		$this->db = $this->getDb();
		$this->config = $this->getConfig();
		$this->host = 'https://' . $this->config->shipserv->services->sso->cas->host . ':' . $this->config->shipserv->services->sso->cas->port;
	}
	
	public function login($username, $password, $service = null)
	{
		if( $service == null )
		{
			$this->service = 'https://' . $_SERVER['HTTP_HOST'];
		}
		else
		{
			$this->service = $service;
		}

		$this->grantingTicket = $this->getGrantingTicketFromCAS($username, $password);
		
		if( $this->grantingTicket !== false )
		{
			$this->serviceTicket = $this->getServiceTicket($this->service);
		}
	}
	
	public function getGrantingTicketFromCAS($username, $password)
	{
		$url = $this->host . self::PATH_FOR_GRANTING_TICKET;
		$params = array('username' => $username, 'password' => $password);
		$response = $this->request($url, $params);
		
		if( $response->getStatus() !== 201 )
		{
			return false;
			//throw new Myshipserv_Security_CAS_InvalidUsernameOrPassword();
		}
		
		preg_match_all('/action="([^"]*)"/i', $response, $result, PREG_PATTERN_ORDER);
		
		// getting the ticket
		$result = str_replace('action="', '', $result[0]);
		$result = str_replace('"', '', $result);
		$url = $result[0];
		$tmp = parse_url($url);
		$tmp = explode("/", $tmp['path']);
		$ticket = $tmp[5];
		$this->urlToGetServiceTicket = $url;
		return $ticket;
	}
	
	public function getServiceTicket($service)
	{
		//$url = $this->urlToGetServiceTicket . '?service=' . $service;
		//header("Location: " . $url);
		//die();
		
		$params = array('ticket' => $this->grantingTicket, 'service' => $service);
		$response = $this->request($this->urlToGetServiceTicket, $params);
		
		if( $response->getStatus() !== 200 )
		{
			throw new Myshipserv_Security_CAS_UnableToGetServiceTicket();
		}
		
		return $response->getBody();
	}
	
	public static function getUsername( $serviceTicket, $service = null )
	{
		return self::validate(  $serviceTicket, $service = null );
	}
	
	public static function validate( $serviceTicket, $service = null )
	{
		if( $service == null )
		{
			$service = 'https://' . $_SERVER['HTTP_HOST'];
		}
		
		$cas = new self;
		
		$params = array('ticket' => $serviceTicket, 'service' => $service);
		$response = self::request($cas->host . self::PATH_FOR_VALIDATING_TICKET, $params);
		
		if( strstr($response->getBody(), 'authenticationFailure') !== false )
			return false;
		else 
		{
			$xml = simplexml_load_string($response->getBody(),null, LIBXML_NOCDATA);
			
			$result = self::xpath($xml, '/cas:serviceResponse/cas:authenticationSuccess/cas:user');
			return $result;
		}
	}
	
	public static function xpath($xml, $xpath)
	{
		$result = $xml->xpath($xpath);
		return (string)$result[0];
	}
	
	
	public function request($url, $parameters)
	{	
		$client = new Zend_Http_Client($url, array(
			'maxredirects' => 0,
			'timeout' => 30
		));
		$client->setMethod(Zend_Http_Client::POST);
		
		$client->setParameterPost($parameters);
		
		try
		{
			$response = $client->request();
		}
		catch(Exception $e)
		{
			throw new Myshipserv_Security_CAS_Unavailable;
		}
		
		return $response;
	}
}

class Myshipserv_Security_CAS_InvalidUsernameOrPassword extends Exception
{
	public function __construct($message = 'Invalid username or password', $code = 400, Exception $previous = null)
	{
		parent::__construct($message, $code);
	}
}

class Myshipserv_Security_CAS_Unavailable extends Exception
{
	public function __construct($message = 'Shipserv CAS server is unavailable', $code = 400, Exception $previous = null)
	{
		parent::__construct($message, $code);
	}
}

class Myshipserv_Security_CAS_UnableToGetServiceTicket extends Exception
{
	public function __construct($message = 'CAS: Unable to get Service Ticket', $code = 400, Exception $previous = null)
	{
		parent::__construct($message, $code);
	}
}
