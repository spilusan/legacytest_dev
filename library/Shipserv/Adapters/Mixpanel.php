<?php

/**
 * Logging class for Mixpanel. Allows non-blocking metrics tracking through PHP
 * 
 * @package ShipServ
 * @author Dave Starling <dstarling@shipserv.com>
 * @copyright Copyright (c) 2009, ShipServ
 */
class Shipserv_Adapters_Mixpanel
{
	/**
	 * Mixpanel auth token
	 *
	 * @access private
	 * @var string
	 */
    private $token;
	
	/**
	 * Mixpanel analytics host
	 *
	 * @access private
	 * @var string
	 */
    private $host;
	
	/**
	 * Constructor - sets up the token and host
	 * 
	 * @access public
	 */
    public function __construct ()
	{
		$config  = Zend_Registry::get('config');
		
		$this->token = $config->analytics->mixpanel->token;
		$this->host  = $config->analytics->mixpanel->host;
    }
	
	/**
	 * Tracks an event
	 * 
	 * @access public
	 * @param string $event The event to track
	 * @param array $properties An array of properties of this event
	 * @return void
	 */
    public function track ($event, $properties = array())
	{
        $params = array(
            'event'      => $event,
            'properties' => $properties
        );
        
        if (!isset($params['token']))
		{
            $params['properties']['token'] = $this->token;
        }
		
        $url = $this->host . 'track/?data=' . base64_encode(json_encode($params));
        exec("curl " . $url . "  & disown"); // non-blocking metric logging
    }
    
	/**
	 * Triggers a Mixpanel Track Funnel event
	 * 
	 * @access public
	 * @param string $funnel The name of the funnel to track
	 * @param int $step The position in the funnel (1-255). Mixpanel expects
	 * consecutive steps, users will not be counted if they go from step 1 to 3
	 * in step 3. The service will check to make sure the user was seen in
	 * previous steps before counting them and only counts unique visitors.
	 * @param string $goal the name of what you're tracking at a certain step
	 * @return void
	 */
    public function track_funnel ($funnel, $step, $goal, $properties = array())
	{
        $properties['funnel'] = $funnel;
        $properties['step']   = $step;
        $properties['goal']   = $goal;
        $this->track('mp_funnel', $properties);
    }
}
