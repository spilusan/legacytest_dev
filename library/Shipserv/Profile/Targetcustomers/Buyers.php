<?
/**
* Managing JSON resaponses for target cujstomers 
*/
class Shipserv_Profile_Targetcustomers_Buyers
{
	
	private 
		$params,
		$result = array()
	;

	/**
	* Requires an array, HTTP Post, or Get params
	* @param array $param
	*/
	public function __construct( $params = null)
	{
		$this->params = $params;
		$this->getRequestByType();
	}

	/**
	* Getter for the pre-processed response
	*/
	public function getResponseArray()
	{
		return $this->result;
	}

	/**
	* Delegate the proper class for the requested service, and return the data
	*/
	protected function getRequestByType()
	{
		switch ($this->params['type']) {
			case 'pending':
				$report = new Shipserv_Profile_Targetcustomers_Reports_Pending($this->params);
				break;
			case 'targeted':
				$report = new Shipserv_Profile_Targetcustomers_Reports_Targeted($this->params);
				break;
			case 'excluded':
				$report = new Shipserv_Profile_Targetcustomers_Reports_Excluded($this->params);
				break;
			case 'add':
				$report = new Shipserv_Profile_Targetcustomers_Reports_Add($this->params);
				break;
			case 'exclude':
				$report = new Shipserv_Profile_Targetcustomers_Reports_Exclude($this->params);
				break;
			case 'settings':
				$report = new Shipserv_Profile_Targetcustomers_Reports_Settings($this->params);
				break;
				case 'store-settings':
				$report = new Shipserv_Profile_Targetcustomers_Reports_Storesetting($this->params);
				break;
			case 'store-max-quote-count':
				$report = new Shipserv_Profile_Targetcustomers_Reports_Storemaxquote($this->params);
				break;	
			case 'store-user-settings':
				$report = new Shipserv_Profile_Targetcustomers_Reports_Storetargetstate($this->params);
				break;	
			default:
				throw new Myshipserv_Exception_MessagedException("Invalid report type", 500);
				break;
		}

		$this->result = $report->getData();
	}


}