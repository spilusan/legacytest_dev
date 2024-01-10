<?php

class Myshipserv_View_Helper_Mixpanel extends Zend_View_Helper_Abstract
{
	private $token;
	
    private $host;
	
	/**
	 * Sets up the Mixpanel helper
	 *
	 * @access public
	 * @return Myshipserv_View_Helper_Mixpanel
	 */
	public function mixpanel ()
	{
		$config  = Zend_Registry::get('config');
		
		$this->token = $config->analytics->mixpanel->token;
		$this->host  = $config->analytics->mixpanel->host;
		
		return $this;
	}
	
	/**
	 * Provides the initialisation Javascript for Mixpanel
	 * 
	 * @access public
	 * @return string Javascript code to initialise Mixpanel
	 */
	public function init ()
	{
		return 'mpmetrics.init("'.$this->token.'");} catch(err) {}';
	}
	
	/**
	 * Returns the javascript to trigger a Mixpanel Track event
	 * 
	 * @access public
	 * @param string $event The event to track
	 * @param array $properties An array of properties of this event
	 * @return string The javascript code to trigger the event
	 */
	public function track ($event, $properties = array())
	{
		$propertiesJs = self::formatProperties($properties);
		
		$js = "mpmetrics.track('$event', $propertiesJs);";
		
		return $js;
	}
	
	/**
	 * Returns the javascript to trigger a Mixpanel Track Funnel event
	 * 
	 * @access public
	 * @param string $funnel The name of the funnel to track
	 * @param int $step The position in the funnel (1-255). Mixpanel expects
	 * consecutive steps, users will not be counted if they go from step 1 to 3
	 * in step 3. The service will check to make sure the user was seen in
	 * previous steps before counting them and only counts unique visitors.
	 * @param string $goal the name of what you're tracking at a certain step
	 * @return string The javascript code to trigger the event
	 */
	public function trackFunnel ($funnel, $step, $goal, $properties = array())
	{
		$propertiesJs = self::formatProperties($properties);
		
		$js = "mpmetrics.track_funnel('$funnel', $step, '$goal', $propertiesJs)";
		
		return $js;
	}
	
	/**
	 * Formats the properties from a PHP array into Javascript
	 * 
	 * @access private
	 * @static
	 * @param array $properties The array of properties to turn into Javascript
	 * @return string
	 */
	private static function formatProperties (array $properties)
	{
		$propertiesJs = '{';
		$count = 0;
		foreach ($properties as $key => $value)
		{
			if ($count > 0)
			{
				$propertiesJs.= ', ';
			}
			$propertiesJs.= "'$key' : '$value'";
			
			$count++;
		}
		
		$propertiesJs.= '}';
		
		return $propertiesJs;
	}
}