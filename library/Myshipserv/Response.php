<?php
/**
 * This class wrap all response thrown to the user
 * Any additional logic in relation to the response should 
 * be written here
 * @author elvirleonard
 */
class Myshipserv_Response 
{
	public $code;
	public $message;
	protected $status;
	protected $data;
	protected $error;
	protected $warning;
	protected $debug;
	protected $availableRow;
	
	function __construct( $code, $message, $data = "" )
	{
		$this->code = $code;
		$this->message = $message;
		if( $data != "" )
		$this->data = $data;
	}
	public function setTotal($total)
	{
		$this->total = $total;
	}
	/**
	 * Convert the response to array
	 * @return array
	 */
	public function toArray()
	{
		$response = array(
			"status" => (int) $this->code,
			"message" => $this->message,
			"total" => ($this->total != null) ? $this->total:(int) count( $this->data )
		);
		
		if( $this->availableRow != '' )
		{
			$response["totalFound"] = (int) $this->availableRow;
		}
		
		$response["data"] = $this->data;
		
		
		if( count( $this->error ) > 0 )
		{
			$response["errors"] = $this->error;
		}
		
		if( count( $this->warning ) > 0 )
		{
			$response["warning"] = $this->warning;
		}
		
		if( count( $this->debug ) > 0 )
		{
			$response["debug"] = $this->debug;
		}
		
		return $response;
	}
	
	/**
	 * If there's an error, we need to let user know
	 * and you can use this function to add the message
	 * @param string $message
	 */
	public function setError( $message )
	{
		$this->error[] = $message;
	}
	
	/**
	 * If there's an warning, we need to let user know
	 * and you can use this function to add the message
	 * @param string $message
	 */	
	public function setWarning( $message )
	{
		$this->debug[] = $message;
	}

	/**
	 * If you're debugging something you can put the data
	 * by passing it to this function and it'll appear on 
	 * the response
	 * 
	 * @param mixed $message
	 */	
	public function setDebug( $data )
	{
		$this->debug[] = $data;
	}	
	
	/**
	 * Get time of the reponse
	 * @return string
	 */
	private function getTime()
	{
		return date("Y-m-d H:i:s");
	}
	
	/**
	 * Sometimes front end needs to know total found to
	 * do the pagination. eg: you're looking at 10 out of 100 
	 * rows
	 * @param int $value
	 */
	public function setTotalFound( $value )
	{
		$this->availableRow = $value;
	}
}